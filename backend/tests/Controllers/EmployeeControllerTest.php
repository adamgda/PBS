<?php

declare(strict_types=1);

use App\Controllers\EmployeeController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EmployeeDocumentRepository;
use App\Repository\EmployeeRateRepository;
use App\Repository\EmployeeRepository;
use App\Repository\EmployeeVacationRepository;
use App\Repository\OrderRepository;
use App\Services\EmployeeService;
use App\Services\FileUploadService;
use App\Services\VirusScannerInterface;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->employeeRepository = m::mock(EmployeeRepository::class, [$pdo]);
    $this->documentRepository = m::mock(EmployeeDocumentRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();
    $this->rateRepository = m::mock(EmployeeRateRepository::class, [$pdo]);
    $this->vacationRepository = m::mock(EmployeeVacationRepository::class, [$pdo]);
    $this->orderRepository = m::mock(OrderRepository::class, [$pdo]);
    // Domyślne puste odpowiedzi dla metod wzbogacających listę (Etap 7a).
    $this->rateRepository->shouldReceive('findCurrentRatesForEmployees')->byDefault()->andReturn([]);
    $this->orderRepository->shouldReceive('hoursPerEmployeeInMonth')->byDefault()->andReturn([]);
    $this->orderRepository->shouldReceive('currentRolesByDate')->byDefault()->andReturn([]);
    $this->vacationRepository->shouldReceive('findOnLeaveEmployeeIds')->byDefault()->andReturn([]);
    $this->documentRepository->shouldReceive('findForEmployeeIds')->byDefault()->andReturn([]);

    $this->scanner = m::mock(VirusScannerInterface::class);
    $this->scanner->shouldReceive('isAvailable')->byDefault()->andReturn(false);

    $tempDir = sys_get_temp_dir() . '/pbs-employee-docs-' . uniqid('', true);
    $fileUploadService = new FileUploadService(
        storageDir: $tempDir,
        baseUrl: 'http://localhost',
        hmacSecret: 'test-secret',
        scanner: $this->scanner,
    );

    $this->employeeService = new EmployeeService(
        $this->employeeRepository,
        $this->documentRepository,
        $this->auditLogRepository,
        $fileUploadService,
        $this->rateRepository,
        $this->vacationRepository,
        $this->orderRepository,
    );
    $this->employeeController = new EmployeeController($this->employeeService);
});

afterEach(function (): void {
    m::close();
});

// --- LIST (index) ---

it('index returns paginated list', function (): void {
    $filters = ['q' => '', 'imie' => '', 'nazwisko' => '', 'terminal_id' => '', 'sprzet_id' => '', 'is_active' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->employeeRepository->shouldReceive('search')
        ->with($filters, 25, 0, 'id', 'asc')
        ->andReturn([
            ['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'Kowalski', 'telefon' => '601111222', 'email' => 'jan@x.pl', 'current_terminal_id' => 1, 'terminal_nazwa' => 'Terminal Gdańsk', 'current_sprzet_id' => null, 'sprzet_nazwa' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null],
        ]);
    $this->employeeRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
    expect($response->data()['data'])->toHaveCount(1);
    expect($response->data()['data'][0]['imie'])->toBe('Jan');
    expect($response->data()['data'][0]['terminal_nazwa'])->toBe('Terminal Gdańsk');
});

it('index applies filters from query string', function (): void {
    $filters = ['q' => '', 'imie' => 'Jan', 'nazwisko' => '', 'terminal_id' => '', 'sprzet_id' => '', 'is_active' => '1', 'sort' => 'id', 'direction' => 'asc'];
    $this->employeeRepository->shouldReceive('search')->with($filters, 10, 0, 'id', 'asc')->andReturn([]);
    $this->employeeRepository->shouldReceive('countSearch')->with($filters)->andReturn(0);

    $request = new Request(query: ['imie' => 'Jan', 'is_active' => '1', 'per_page' => '10'], body: [], headers: []);
    $response = $this->employeeController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['per_page'])->toBe(10);
});

it('index enriches employees with uprawnienie summary', function (): void {
    $filters = ['q' => '', 'imie' => '', 'nazwisko' => '', 'terminal_id' => '', 'sprzet_id' => '', 'is_active' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->employeeRepository->shouldReceive('search')
        ->with($filters, 25, 0, 'id', 'asc')
        ->andReturn([
            ['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'Kowalski', 'telefon' => null, 'email' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'current_sprzet_id' => null, 'sprzet_nazwa' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 2, 'imie' => 'Anna', 'nazwisko' => 'Nowak', 'telefon' => null, 'email' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'current_sprzet_id' => null, 'sprzet_nazwa' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null],
        ]);
    $this->employeeRepository->shouldReceive('countSearch')->with($filters)->andReturn(2);

    $expiring = date('Y-m-d', strtotime('+10 days'));
    $valid = date('Y-m-d', strtotime('+400 days'));
    $this->documentRepository->shouldReceive('findForEmployeeIds')->with([1, 2])->andReturn([
        ['id' => 1, 'employee_id' => 1, 'nazwa' => 'UDT HDS', 'numer_dokumentu' => null, 'data_wydania' => null, 'data_waznosci' => $expiring, 'plik' => null],
        ['id' => 2, 'employee_id' => 2, 'nazwa' => 'BHP', 'numer_dokumentu' => null, 'data_wydania' => null, 'data_waznosci' => $valid, 'plik' => null],
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'][0]['uprawnienie']['status'])->toBe('expiring');
    expect($response->data()['data'][0]['uprawnienie']['nazwa'])->toBe('UDT HDS');
    expect($response->data()['data'][0]['uprawnienie']['dni'])->toBe(10);
    expect($response->data()['data'][1]['uprawnienie']['status'])->toBe('ok');
    expect($response->data()['data'][1]['uprawnienie']['nazwa'])->toBe('BHP');
});

it('index passes q (imię/nazwisko) filter to repository', function (): void {
    $filters = ['q' => 'Jan', 'imie' => '', 'nazwisko' => '', 'terminal_id' => '', 'sprzet_id' => '', 'is_active' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->employeeRepository->shouldReceive('search')->with($filters, 25, 0, 'id', 'asc')->andReturn([]);
    $this->employeeRepository->shouldReceive('countSearch')->with($filters)->andReturn(0);

    $request = new Request(query: ['q' => 'Jan'], body: [], headers: []);
    $response = $this->employeeController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toHaveCount(0);
});

// --- STORE (create) ---

it('store returns 422 when first name missing', function (): void {
    $request = new Request(query: [], body: ['nazwisko' => 'Kowalski'], headers: []);
    $response = $this->employeeController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 when last name missing', function (): void {
    $request = new Request(query: [], body: ['imie' => 'Jan'], headers: []);
    $response = $this->employeeController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 for invalid email', function (): void {
    $request = new Request(query: [], body: ['imie' => 'Jan', 'nazwisko' => 'Kowalski', 'email' => 'not-an-email'], headers: []);
    $response = $this->employeeController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 409 when email already exists', function (): void {
    $this->employeeRepository->shouldReceive('findByEmail')->with('jan@x.pl')->andReturn(['id' => 5]);

    $request = new Request(query: [], body: ['imie' => 'Jan', 'nazwisko' => 'Kowalski', 'email' => 'jan@x.pl'], headers: []);
    $response = $this->employeeController->store($request);
    expect($response->statusCode())->toBe(409);
});

it('store creates employee and returns 201', function (): void {
    $this->employeeRepository->shouldReceive('findByEmail')->with('jan@x.pl')->andReturnNull();
    $this->employeeRepository->shouldReceive('createEmployee')->with(m::on(function (array $d): bool {
        return $d['imie'] === 'Jan' && $d['nazwisko'] === 'Kowalski' && $d['email'] === 'jan@x.pl' && $d['is_active'] === true;
    }))->andReturn([
        'id' => 7, 'imie' => 'Jan', 'nazwisko' => 'Kowalski', 'telefon' => null, 'email' => 'jan@x.pl',
        'current_terminal_id' => null, 'terminal_nazwa' => null, 'current_sprzet_id' => null, 'sprzet_nazwa' => null,
        'is_active' => 1, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: ['imie' => 'Jan', 'nazwisko' => 'Kowalski', 'email' => 'jan@x.pl'], headers: []);
    $response = $this->employeeController->store($request);

    expect($response->statusCode())->toBe(201);
    expect($response->data()['imie'])->toBe('Jan');
    expect($response->data()['email'])->toBe('jan@x.pl');
});

// --- SHOW ---

it('show returns 404 for missing employee', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->show($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('show returns employee dto with documents', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn([
        'id' => 1, 'imie' => 'Jan', 'nazwisko' => 'Kowalski', 'telefon' => null, 'email' => null,
        'current_terminal_id' => null, 'terminal_nazwa' => null, 'current_sprzet_id' => null, 'sprzet_nazwa' => null,
        'is_active' => 1, 'created_at' => null, 'updated_at' => null,
    ]);
    $this->documentRepository->shouldReceive('findByEmployeeId')->with(1)->andReturn([
        ['id' => 10, 'employee_id' => 1, 'nazwa' => 'UDT', 'numer_dokumentu' => 'X1', 'data_wydania' => '2024-01-01', 'data_waznosci' => '2099-01-01', 'plik' => null, 'created_at' => null, 'updated_at' => null],
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->show($request, ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['imie'])->toBe('Jan');
    expect($response->data()['documents'])->toHaveCount(1);
    expect($response->data()['documents'][0]['is_expired'])->toBe(false);
});

// --- UPDATE ---

it('update returns 404 for missing employee', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: ['imie' => 'Jan', 'nazwisko' => 'K'], headers: []);
    $response = $this->employeeController->update($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('update returns 422 when first name missing', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);

    $request = new Request(query: [], body: ['nazwisko' => 'Nowak'], headers: []);
    $response = $this->employeeController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('update returns 409 for duplicate email (different id)', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);
    $this->employeeRepository->shouldReceive('findByEmail')->with('inny@x.pl')->andReturn(['id' => 2]);

    $request = new Request(query: [], body: ['imie' => 'Jan', 'nazwisko' => 'K', 'email' => 'inny@x.pl'], headers: []);
    $response = $this->employeeController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(409);
});

it('update edits employee', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);
    $this->employeeRepository->shouldReceive('findByEmail')->with('jan2@x.pl')->andReturnNull();
    $this->employeeRepository->shouldReceive('updateEmployee')->with(1, m::on(function (array $d): bool {
        return $d['nazwisko'] === 'Nowak' && $d['email'] === 'jan2@x.pl' && $d['is_active'] === true;
    }))->andReturn([
        'id' => 1, 'imie' => 'Jan', 'nazwisko' => 'Nowak', 'telefon' => null, 'email' => 'jan2@x.pl',
        'current_terminal_id' => null, 'terminal_nazwa' => null, 'current_sprzet_id' => null, 'sprzet_nazwa' => null,
        'is_active' => 1, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: ['imie' => 'Jan', 'nazwisko' => 'Nowak', 'email' => 'jan2@x.pl'], headers: []);
    $response = $this->employeeController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['nazwisko'])->toBe('Nowak');
});

// --- DESTROY (anonymizacja RODO) ---

it('delete returns 404 for missing employee', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->destroy($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('delete removes employee physically and returns success', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);
    $this->employeeRepository->shouldReceive('delete')->with(1)->andReturn(true);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->destroy($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});

// --- ASSIGN ---

it('assign returns 404 for missing employee', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: ['current_terminal_id' => 2], headers: []);
    $response = $this->employeeController->assign($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('assign returns 422 when no assignment fields', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->assign($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('assign updates terminal and returns dto', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);
    $this->employeeRepository->shouldReceive('updateAssignment')->with(1, ['current_terminal_id' => 3])->andReturn([
        'id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'telefon' => null, 'email' => null,
        'current_terminal_id' => 3, 'terminal_nazwa' => 'Terminal Gdynia', 'current_sprzet_id' => null, 'sprzet_nazwa' => null,
        'is_active' => 1, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: ['current_terminal_id' => 3], headers: []);
    $response = $this->employeeController->assign($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['current_terminal_id'])->toBe(3);
});

// --- DOCUMENTS ---

it('listDocuments returns 404 for missing employee', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->listDocuments($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('listDocuments returns list with expiry detection', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);
    $past = date('Y-m-d', strtotime('-10 days'));
    $future = date('Y-m-d', strtotime('+10 days'));
    $this->documentRepository->shouldReceive('findByEmployeeId')->with(1)->andReturn([
        ['id' => 1, 'employee_id' => 1, 'nazwa' => 'Stary', 'numer_dokumentu' => null, 'data_wydania' => null, 'data_waznosci' => $past, 'plik' => null, 'created_at' => null, 'updated_at' => null],
        ['id' => 2, 'employee_id' => 1, 'nazwa' => 'Wkrótce', 'numer_dokumentu' => null, 'data_wydania' => null, 'data_waznosci' => $future, 'plik' => null, 'created_at' => null, 'updated_at' => null],
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->listDocuments($request, ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'][0]['is_expired'])->toBe(true);
    expect($response->data()['data'][1]['is_expiring_soon'])->toBe(true);
});

it('createDocument returns 422 when name missing', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->createDocument($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('createDocument returns 404 for missing employee', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: ['nazwa' => 'UDT'], headers: []);
    $response = $this->employeeController->createDocument($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('createDocument creates document without file', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'K', 'is_active' => 1]);
    $this->documentRepository->shouldReceive('createDocument')->with(m::on(function (array $d): bool {
        return $d['employee_id'] === 1 && $d['nazwa'] === 'UDT' && $d['plik'] === null;
    }))->andReturn([
        'id' => 99, 'employee_id' => 1, 'nazwa' => 'UDT', 'numer_dokumentu' => null, 'data_wydania' => null, 'data_waznosci' => null, 'plik' => null, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: ['nazwa' => 'UDT'], headers: []);
    $response = $this->employeeController->createDocument($request, ['id' => '1']);
    expect($response->statusCode())->toBe(201);
    expect($response->data()['nazwa'])->toBe('UDT');
});

it('updateDocument returns 404 for missing document', function (): void {
    $this->documentRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: ['nazwa' => 'X'], headers: []);
    $response = $this->employeeController->updateDocument($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('updateDocument edits document', function (): void {
    $this->documentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'employee_id' => 1, 'nazwa' => 'Stary', 'data_waznosci' => null]);
    $this->documentRepository->shouldReceive('updateDocument')->with(1, ['nazwa' => 'Nowy'])->andReturn([
        'id' => 1, 'employee_id' => 1, 'nazwa' => 'Nowy', 'numer_dokumentu' => null, 'data_wydania' => null, 'data_waznosci' => null, 'plik' => null, 'created_at' => null, 'updated_at' => null,
    ]);

    $request = new Request(query: [], body: ['nazwa' => 'Nowy'], headers: []);
    $response = $this->employeeController->updateDocument($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['nazwa'])->toBe('Nowy');
});

it('deleteDocument returns 404 for missing document', function (): void {
    $this->documentRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->deleteDocument($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('deleteDocument removes document and returns success', function (): void {
    $this->documentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'employee_id' => 1, 'nazwa' => 'UDT']);
    $this->documentRepository->shouldReceive('delete')->with(1)->andReturn(true);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->employeeController->deleteDocument($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});