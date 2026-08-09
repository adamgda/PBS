<?php

declare(strict_types=1);

use App\Controllers\TerminalController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\TerminalRepository;
use App\Services\TerminalService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->terminalRepository = m::mock(TerminalRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();

    $this->terminalService = new TerminalService(
        $this->terminalRepository,
        $this->auditLogRepository,
    );
    $this->terminalController = new TerminalController($this->terminalService);
});

afterEach(function (): void {
    m::close();
});

// --- LIST (index) ---

it('index returns paginated list', function (): void {
    $filters = ['nazwa' => '', 'operator' => '', 'is_active' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->terminalRepository->shouldReceive('search')
        ->with($filters, 25, 0, 'id', 'asc')
        ->andReturn([
            ['id' => 1, 'nazwa' => 'Terminal Gdańsk', 'adres' => 'ul. Portowa 1', 'operator' => 'Baltic Operator', 'telefon_operatora' => '581234567', 'email_operatora' => 'kontakt@baltic.pl', 'is_active' => 1, 'created_at' => null, 'updated_at' => null],
        ]);
    $this->terminalRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->terminalController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
    expect($response->data()['data'])->toHaveCount(1);
    expect($response->data()['data'][0]['nazwa'])->toBe('Terminal Gdańsk');
});

it('index applies filters from query string', function (): void {
    $filters = ['nazwa' => 'Gda', 'operator' => '', 'is_active' => '1', 'sort' => 'id', 'direction' => 'asc'];
    $this->terminalRepository->shouldReceive('search')->with($filters, 10, 0, 'id', 'asc')->andReturn([]);
    $this->terminalRepository->shouldReceive('countSearch')->with($filters)->andReturn(0);

    $request = new Request(query: ['nazwa' => 'Gda', 'is_active' => '1', 'per_page' => '10'], body: [], headers: []);
    $response = $this->terminalController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['per_page'])->toBe(10);
});

// --- STORE (create) ---

it('store returns 422 when name missing', function (): void {
    $request = new Request(query: [], body: ['adres' => 'ul. 1', 'operator' => 'Op'], headers: []);
    $response = $this->terminalController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 when address missing', function (): void {
    $request = new Request(query: [], body: ['nazwa' => 'T', 'operator' => 'Op'], headers: []);
    $response = $this->terminalController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 when operator missing', function (): void {
    $request = new Request(query: [], body: ['nazwa' => 'T', 'adres' => 'ul. 1'], headers: []);
    $response = $this->terminalController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 for invalid operator email', function (): void {
    $request = new Request(query: [], body: ['nazwa' => 'T', 'adres' => 'ul. 1', 'operator' => 'Op', 'email_operatora' => 'not-an-email'], headers: []);
    $response = $this->terminalController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 409 when name already exists', function (): void {
    $this->terminalRepository->shouldReceive('findByName')->with('Terminal Gdańsk')->andReturn(['id' => 1]);

    $request = new Request(query: [], body: ['nazwa' => 'Terminal Gdańsk', 'adres' => 'ul. 1', 'operator' => 'Op'], headers: []);
    $response = $this->terminalController->store($request);
    expect($response->statusCode())->toBe(409);
});

it('store creates terminal and returns 201', function (): void {
    $this->terminalRepository->shouldReceive('findByName')->with('Nowy Terminal')->andReturnNull();
    $this->terminalRepository->shouldReceive('createTerminal')->andReturn([
        'id' => 5, 'nazwa' => 'Nowy Terminal', 'adres' => 'ul. Portowa 1', 'operator' => 'Baltic Operator',
        'telefon_operatora' => '581234567', 'email_operatora' => 'kontakt@baltic.pl', 'is_active' => 1, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: ['nazwa' => 'Nowy Terminal', 'adres' => 'ul. Portowa 1', 'operator' => 'Baltic Operator', 'is_active' => true], headers: []);
    $response = $this->terminalController->store($request);

    expect($response->statusCode())->toBe(201);
    expect($response->data()['nazwa'])->toBe('Nowy Terminal');
    expect($response->data()['is_active'])->toBeTrue();
});

// --- SHOW ---

it('show returns 404 for missing terminal', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(99)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->terminalController->show($request, ['id' => '99']);
    expect($response->statusCode())->toBe(404);
});

it('show returns terminal dto', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1, 'nazwa' => 'Terminal Gdańsk', 'adres' => 'ul. Portowa 1', 'operator' => 'Baltic Operator',
        'telefon_operatora' => null, 'email_operatora' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->terminalController->show($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['nazwa'])->toBe('Terminal Gdańsk');
    expect($response->data()['telefon_operatora'])->toBeNull();
});

// --- UPDATE ---

it('update returns 404 for missing terminal', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: ['nazwa' => 'T', 'adres' => 'ul. 1', 'operator' => 'Op'], headers: []);
    $response = $this->terminalController->update($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('update returns 422 when name missing', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'T', 'adres' => 'ul. 1', 'operator' => 'Op', 'is_active' => 1]);

    $request = new Request(query: [], body: ['adres' => 'ul. 1', 'operator' => 'Op'], headers: []);
    $response = $this->terminalController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('update returns 409 for duplicate name (different id)', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'T', 'adres' => 'ul. 1', 'operator' => 'Op', 'is_active' => 1]);
    $this->terminalRepository->shouldReceive('findByName')->with('Inny Terminal')->andReturn(['id' => 2]);

    $request = new Request(query: [], body: ['nazwa' => 'Inny Terminal', 'adres' => 'ul. 1', 'operator' => 'Op'], headers: []);
    $response = $this->terminalController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(409);
});

it('update allows same name for same terminal', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'T', 'adres' => 'ul. 1', 'operator' => 'Op', 'is_active' => 1]);
    $this->terminalRepository->shouldReceive('findByName')->with('T')->andReturn(['id' => 1]);
    $this->terminalRepository->shouldReceive('updateTerminal')->with(1, m::on(function (array $d): bool {
        return $d['nazwa'] === 'T' && $d['adres'] === 'ul. Nowa 2' && $d['is_active'] === true;
    }))->andReturn([
        'id' => 1, 'nazwa' => 'T', 'adres' => 'ul. Nowa 2', 'operator' => 'Op', 'telefon_operatora' => null, 'email_operatora' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: ['nazwa' => 'T', 'adres' => 'ul. Nowa 2', 'operator' => 'Op'], headers: []);
    $response = $this->terminalController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['adres'])->toBe('ul. Nowa 2');
});

// --- DESTROY ---

it('delete returns 404 for missing terminal', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->terminalController->destroy($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('delete removes terminal and returns success', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'T', 'adres' => 'ul. 1', 'operator' => 'Op', 'is_active' => 1]);
    $this->terminalRepository->shouldReceive('delete')->with(1)->andReturn(true);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->terminalController->destroy($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});