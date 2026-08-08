<?php

declare(strict_types=1);

use App\Controllers\UserController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;
use App\Services\MailService;
use App\Services\UserService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->userRepository = m::mock(UserRepository::class, [$pdo]);
    $this->passwordResetRepository = m::mock(PasswordResetRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->mailService = m::mock(MailService::class);
    $this->mailService->shouldReceive('sendPasswordResetEmail')->byDefault();
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();

    $this->userService = new UserService(
        $this->userRepository,
        $this->passwordResetRepository,
        $this->auditLogRepository,
        $this->mailService,
        'http://localhost:4200',
        true, // debug
    );
    $this->userController = new UserController($this->userService);
});

afterEach(function (): void {
    m::close();
});

// --- LIST (index) ---

it('index returns paginated list', function (): void {
    $filters = ['email' => '', 'role' => '', 'is_active' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->userRepository->shouldReceive('search')
        ->with($filters, 25, 0, 'id', 'asc')
        ->andReturn([
            ['id' => 1, 'email' => 'a@pbs.local', 'role' => 'admin', 'permissions' => '{}', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => null, 'updated_at' => null],
        ]);
    $this->userRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->userController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
    expect($response->data()['data'])->toHaveCount(1);
    // password_hash nigdy nie wychodzi z API
    expect($response->data()['data'][0])->not->toHaveKey('password_hash');
});

it('index applies filters from query string', function (): void {
    $filters = ['email' => 'admin', 'role' => '', 'is_active' => '1', 'sort' => 'id', 'direction' => 'asc'];
    $this->userRepository->shouldReceive('search')->with($filters, 10, 0, 'id', 'asc')->andReturn([]);
    $this->userRepository->shouldReceive('countSearch')->with($filters)->andReturn(0);

    $request = new Request(query: ['email' => 'admin', 'is_active' => '1', 'per_page' => '10'], body: [], headers: []);
    $response = $this->userController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['per_page'])->toBe(10);
});

// --- STORE (create) ---

it('store returns 422 when email missing', function (): void {
    $request = new Request(query: [], body: ['role' => 'user'], headers: []);
    $response = $this->userController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 for invalid email', function (): void {
    $request = new Request(query: [], body: ['email' => 'not-an-email', 'role' => 'user'], headers: []);
    $response = $this->userController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 409 when email already registered', function (): void {
    $this->userRepository->shouldReceive('findByEmail')->with('a@pbs.local')->andReturn(['id' => 1]);

    $request = new Request(query: [], body: ['email' => 'a@pbs.local', 'role' => 'user'], headers: []);
    $response = $this->userController->store($request);
    expect($response->statusCode())->toBe(409);
});

it('store creates user and returns 201', function (): void {
    $this->userRepository->shouldReceive('findByEmail')->with('new@pbs.local')->andReturnNull();
    $this->userRepository->shouldReceive('createUser')->andReturn([
        'id' => 5,
        'email' => 'new@pbs.local',
        'role' => 'user',
        'permissions' => '{}',
        'is_active' => 1,
        'must_change_password' => 1,
    ]);
    $this->passwordResetRepository->shouldReceive('createToken')->once();

    $request = new Request(query: [], body: ['email' => 'new@pbs.local', 'role' => 'user', 'permissions' => ['dashboard' => true]], headers: []);
    $response = $this->userController->store($request);

    expect($response->statusCode())->toBe(201);
    expect($response->data()['email'])->toBe('new@pbs.local');
    expect($response->data()['must_change_password'])->toBeTrue();
    // W trybie debug link set-password jest ujawniony
    expect($response->data())->toHaveKey('set_password_url');
});

// --- SHOW ---

it('show returns 404 for missing user', function (): void {
    $this->userRepository->shouldReceive('findById')->with(99)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->userController->show($request, ['id' => '99']);
    expect($response->statusCode())->toBe(404);
});

it('show returns user dto', function (): void {
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1, 'email' => 'a@pbs.local', 'role' => 'admin',
        'permissions' => '{"dashboard":true}', 'is_active' => 1, 'must_change_password' => 0,
        'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->userController->show($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['permissions']['dashboard'])->toBeTrue();
    expect($response->data())->not->toHaveKey('password_hash');
});

// --- UPDATE ---

it('update returns 422 when email missing', function (): void {
    $request = new Request(query: [], body: ['role' => 'admin'], headers: []);
    $response = $this->userController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('update returns 404 for missing user', function (): void {
    $this->userRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: ['email' => 'a@pbs.local', 'role' => 'admin'], headers: []);
    $response = $this->userController->update($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('update edits user', function (): void {
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1, 'email' => 'old@pbs.local', 'role' => 'user', 'permissions' => '{}', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => null, 'updated_at' => null,
    ]);
    $this->userRepository->shouldReceive('findByEmail')->with('new@pbs.local')->andReturnNull();
    $this->userRepository->shouldReceive('update')->with(1, ['email' => 'new@pbs.local', 'role' => 'admin'])->andReturn([
        'id' => 1, 'email' => 'new@pbs.local', 'role' => 'admin', 'permissions' => '{}', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: ['email' => 'new@pbs.local', 'role' => 'admin'], headers: []);
    $response = $this->userController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['email'])->toBe('new@pbs.local');
});

// --- PERMISSIONS ---

it('permissions updates and normalizes permissions', function (): void {
    $this->userRepository->shouldReceive('findById')->andReturn([
        'id' => 2, 'email' => 'b@pbs.local', 'role' => 'user', 'permissions' => '{}', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => null, 'updated_at' => null,
    ]);
    $this->userRepository->shouldReceive('updatePermissions')->with(2, m::on(function (array $p): bool {
        // Tylko dopuszczalne sekcje — mass assignment protection
        return array_key_exists('dashboard', $p) && $p['dashboard'] === true
            && !array_key_exists('malicious_section', $p)
            && $p['pracownicy'] === false;
    }))->once();

    $request = new Request(query: [], body: ['permissions' => ['dashboard' => true, 'malicious_section' => true]], headers: []);
    $response = $this->userController->permissions($request, ['id' => '2']);
    expect($response->statusCode())->toBe(200);
});

it('permissions returns 404 for missing user', function (): void {
    $this->userRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: ['permissions' => []], headers: []);
    $response = $this->userController->permissions($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

// --- DESTROY (self-protection) ---

it('delete prevents self-deletion', function (): void {
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1, 'email' => 'me@pbs.local', 'role' => 'admin', 'permissions' => '{}', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $request->setAttribute('user_id', 1);
    $request->setAttribute('role', 'admin');
    $response = $this->userController->destroy($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('delete blocks non-super_admin from deleting super_admin', function (): void {
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1, 'email' => 'root@pbs.local', 'role' => 'super_admin', 'permissions' => '{}', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $request->setAttribute('user_id', 2);
    $request->setAttribute('role', 'admin');
    $response = $this->userController->destroy($request, ['id' => '1']);
    expect($response->statusCode())->toBe(403);
});

it('delete anonymizes and deactivates user', function (): void {
    $this->userRepository->shouldReceive('findById')->with(3)->andReturn([
        'id' => 3, 'email' => 'x@pbs.local', 'role' => 'user', 'permissions' => '{}', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => null, 'updated_at' => null,
    ]);
    $this->userRepository->shouldReceive('update')->with(3, m::on(function (array $data): bool {
        return str_starts_with($data['email'], 'deleted_3@')
            && $data['is_active'] === false;
    }))->once();

    $request = new Request(query: [], body: [], headers: []);
    $request->setAttribute('user_id', 1);
    $request->setAttribute('role', 'super_admin');
    $response = $this->userController->destroy($request, ['id' => '3']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});

it('toggleActive prevents self-block', function (): void {
    $this->userRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1, 'email' => 'me@pbs.local', 'role' => 'user', 'permissions' => '{}', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $request->setAttribute('user_id', 1);
    $request->setAttribute('role', 'admin');
    // Blokowanie odbywa się w serwisie (frontend wywołuje PUT /users/{id} z is_active).
    // Tu testujemy regułę ochrony własnego konta na poziomie serwisu.
    $result = $this->userService->toggleActive(1, false, $request);
    expect($result['code'])->toBe(422);
});