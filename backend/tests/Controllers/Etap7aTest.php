<?php

declare(strict_types=1);

use App\Controllers\EmployeeController;
use App\Controllers\InvoiceController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EmployeeDocumentRepository;
use App\Repository\EmployeeRateRepository;
use App\Repository\EmployeeRepository;
use App\Repository\EmployeeVacationRepository;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use App\Services\EmployeeService;
use App\Services\FileUploadService;
use App\Services\InvoiceService;
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
    $this->invoiceRepository = m::mock(InvoiceRepository::class, [$pdo]);

    $this->rateRepository->shouldReceive('findCurrentRatesForEmployees')->byDefault()->andReturn([]);
    $this->orderRepository->shouldReceive('hoursPerEmployeeInMonth')->byDefault()->andReturn([]);
    $this->orderRepository->shouldReceive('currentRolesByDate')->byDefault()->andReturn([]);
    $this->vacationRepository->shouldReceive('findOnLeaveEmployeeIds')->byDefault()->andReturn([]);

    $scanner = m::mock(VirusScannerInterface::class);
    $scanner->shouldReceive('isAvailable')->byDefault()->andReturn(false);
    $fileUploadService = new FileUploadService(
        storageDir: sys_get_temp_dir() . '/pbs-7a-' . uniqid('', true),
        baseUrl: 'http://localhost',
        hmacSecret: 'test-secret',
        scanner: $scanner,
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

    $this->invoiceService = new InvoiceService($this->invoiceRepository, $this->auditLogRepository);
    $this->invoiceController = new InvoiceController($this->invoiceService);
});

afterEach(function (): void {
    m::close();
});

// --- Stawki ---

it('listRates returns 404 for missing employee', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(99)->andReturnNull();
    $response = $this->employeeController->listRates(new Request(query: [], body: [], headers: []), ['id' => '99']);
    expect($response->statusCode())->toBe(404);
});

it('listRates returns history', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $this->rateRepository->shouldReceive('findByEmployeeId')->with(1)->andReturn([
        ['id' => 2, 'employee_id' => 1, 'stawka_godzinowa' => 50, 'data_od' => '2026-01-01', 'data_do' => null, 'created_at' => null, 'updated_at' => null],
    ]);
    $response = $this->employeeController->listRates(new Request(query: [], body: [], headers: []), ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toHaveCount(1);
    expect($response->data()['data'][0]['stawka_godzinowa'])->toBe(50.0);
});

it('createRate returns 422 for non-positive stawka', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $request = new Request(query: [], body: ['stawka_godzinowa' => 0, 'data_od' => '2026-01-01'], headers: []);
    $response = $this->employeeController->createRate($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('createRate closes previous and creates new', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $this->rateRepository->shouldReceive('closePreviousRate')->with(1, '2026-02-01')->once();
    $this->rateRepository->shouldReceive('createRate')->with(m::on(function (array $d): bool {
        return $d['employee_id'] === 1 && $d['stawka_godzinowa'] === 55.0 && $d['data_od'] === '2026-02-01' && $d['data_do'] === null;
    }))->andReturn(['id' => 9, 'employee_id' => 1, 'stawka_godzinowa' => 55, 'data_od' => '2026-02-01', 'data_do' => null, 'created_at' => null, 'updated_at' => null]);

    $request = new Request(query: [], body: ['stawka_godzinowa' => 55, 'data_od' => '2026-02-01'], headers: []);
    $response = $this->employeeController->createRate($request, ['id' => '1']);
    expect($response->statusCode())->toBe(201);
    expect($response->data()['stawka_godzinowa'])->toBe(55.0);
});

// --- Urlopy ---

it('createVacation returns 422 when data_do before data_od', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $request = new Request(query: [], body: ['data_od' => '2026-02-10', 'data_do' => '2026-02-01'], headers: []);
    $response = $this->employeeController->createVacation($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('createVacation rejects invalid type', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $request = new Request(query: [], body: ['data_od' => '2026-02-01', 'data_do' => '2026-02-05', 'typ' => 'nieznany'], headers: []);
    $response = $this->employeeController->createVacation($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('createVacation creates vacation', function (): void {
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $this->vacationRepository->shouldReceive('createVacation')->with(m::on(function (array $d): bool {
        return $d['employee_id'] === 1 && $d['data_od'] === '2026-02-01' && $d['data_do'] === '2026-02-05' && $d['typ'] === 'wypoczynkowy' && $d['status'] === 'oczekujacy';
    }))->andReturn(['id' => 1, 'employee_id' => 1, 'data_od' => '2026-02-01', 'data_do' => '2026-02-05', 'typ' => 'wypoczynkowy', 'status' => 'oczekujacy', 'created_at' => null, 'updated_at' => null]);

    $request = new Request(query: [], body: ['data_od' => '2026-02-01', 'data_do' => '2026-02-05'], headers: []);
    $response = $this->employeeController->createVacation($request, ['id' => '1']);
    expect($response->statusCode())->toBe(201);
    expect($response->data()['typ'])->toBe('wypoczynkowy');
});

it('updateVacationStatus rejects invalid status', function (): void {
    $this->vacationRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $request = new Request(query: [], body: ['status' => 'bzdura'], headers: []);
    $response = $this->employeeController->updateVacationStatus($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('updateVacationStatus updates status', function (): void {
    $this->vacationRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $this->vacationRepository->shouldReceive('updateVacation')->with(1, ['status' => 'zatwierdzony'])->andReturn(['id' => 1, 'employee_id' => 1, 'data_od' => '2026-02-01', 'data_do' => '2026-02-05', 'typ' => 'wypoczynkowy', 'status' => 'zatwierdzony', 'created_at' => null, 'updated_at' => null]);
    $request = new Request(query: [], body: ['status' => 'zatwierdzony'], headers: []);
    $response = $this->employeeController->updateVacationStatus($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['status'])->toBe('zatwierdzony');
});

it('deleteVacation returns 404 for missing', function (): void {
    $this->vacationRepository->shouldReceive('findById')->with(99)->andReturnNull();
    $response = $this->employeeController->deleteVacation(new Request(query: [], body: [], headers: []), ['id' => '99']);
    expect($response->statusCode())->toBe(404);
});
// --- Rozliczenia ---

it('settlement returns per-employee aggregates', function (): void {
    $this->orderRepository->shouldReceive('settlementDetail')->with('2026-02', 'all')->andReturn([
        ['employee_id' => 1, 'data_zlecenia' => '2026-02-03', 'godziny' => 8, 'terminal_id' => 1, 'rola' => 'operator'],
        ['employee_id' => 1, 'data_zlecenia' => '2026-02-20', 'godziny' => 6, 'terminal_id' => 1, 'rola' => 'operator'],
    ]);
    $this->rateRepository->shouldReceive('findAllByEmployeeIds')->with([1])->andReturn([1 => [
        ['id' => 1, 'employee_id' => 1, 'stawka_godzinowa' => 50, 'data_od' => '2026-01-01', 'data_do' => null, 'created_at' => null, 'updated_at' => null],
    ]]);
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'Kowalski']);

    $response = $this->employeeController->settlement(new Request(query: ['month' => '2026-02', 'period' => 'all'], body: [], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toHaveCount(1);
    expect($response->data()['data'][0]['godziny_1_15'])->toBe(8.0);
    expect($response->data()['data'][0]['godziny_15_23'])->toBe(6.0);
    expect($response->data()['data'][0]['godziny_total'])->toBe(14.0);
    expect($response->data()['data'][0]['wynagrodzenie'])->toBe(700.0);
    expect($response->data()['total_godziny'])->toBe(14.0);
});

it('settlement applies period filter (1-15)', function (): void {
    $this->orderRepository->shouldReceive('settlementDetail')->with('2026-02', '1-15')->andReturn([
        ['employee_id' => 1, 'data_zlecenia' => '2026-02-03', 'godziny' => 8, 'terminal_id' => 1, 'rola' => 'operator'],
    ]);
    $this->rateRepository->shouldReceive('findAllByEmployeeIds')->with([1])->andReturn([1 => [
        ['id' => 1, 'employee_id' => 1, 'stawka_godzinowa' => 50, 'data_od' => '2026-01-01', 'data_do' => null, 'created_at' => null, 'updated_at' => null],
    ]]);
    $this->employeeRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'imie' => 'Jan', 'nazwisko' => 'Kowalski']);

    $response = $this->employeeController->settlement(new Request(query: ['month' => '2026-02', 'period' => '1-15'], body: [], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['period'])->toBe('1-15');
    expect($response->data()['data'][0]['godziny_total'])->toBe(8.0);
});

it('settlementByPort returns per-port rows plus total', function (): void {
    $this->orderRepository->shouldReceive('settlementDetail')->with('2026-02', 'all')->andReturn([
        ['employee_id' => 1, 'data_zlecenia' => '2026-02-03', 'godziny' => 8, 'terminal_id' => 1, 'rola' => 'operator'],
        ['employee_id' => 2, 'data_zlecenia' => '2026-02-04', 'godziny' => 4, 'terminal_id' => 2, 'rola' => 'brygadzista'],
    ]);
    $this->rateRepository->shouldReceive('findAllByEmployeeIds')->with([1, 2])->andReturn([
        1 => [['id' => 1, 'employee_id' => 1, 'stawka_godzinowa' => 50, 'data_od' => '2026-01-01', 'data_do' => null, 'created_at' => null, 'updated_at' => null]],
        2 => [['id' => 2, 'employee_id' => 2, 'stawka_godzinowa' => 60, 'data_od' => '2026-01-01', 'data_do' => null, 'created_at' => null, 'updated_at' => null]],
    ]);

    $response = $this->employeeController->settlementByPort(new Request(query: ['month' => '2026-02', 'period' => 'all'], body: [], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toHaveCount(3);
    $totalRow = $response->data()['data'][2];
    expect($totalRow['terminal_nazwa'])->toBe('Razem (wszystkie porty)');
    expect($totalRow['suma_godzin'])->toBe(12.0);
    expect($totalRow['suma_wynagrodzen'])->toBe(640.0);
});
// --- Faktury ---

it('invoice index returns paginated list', function (): void {
    $this->invoiceRepository->shouldReceive('search')->with(m::on(function (array $f): bool {
        return $f['status'] === '' && $f['sort'] === 'id' && $f['direction'] === 'asc';
    }), 25, 0, 'id', 'asc')->andReturn([
        ['id' => 1, 'order_id' => null, 'numer_faktury' => 'F-1', 'klient_nazwa' => 'Klient', 'kwota_pln' => 100, 'data_wystawienia' => '2026-02-01', 'termin_platnosci' => null, 'status' => 'wystawiona', 'typ_wystawienia' => 'po_zleceniu', 'created_at' => null, 'updated_at' => null],
    ]);
    $this->invoiceRepository->shouldReceive('countSearch')->andReturn(1);

    $response = $this->invoiceController->index(new Request(query: [], body: [], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
    expect($response->data()['data'][0]['numer_faktury'])->toBe('F-1');
});

it('invoice store returns 422 when numer_faktury missing', function (): void {
    $request = new Request(query: [], body: ['klient_nazwa' => 'Klient', 'data_wystawienia' => '2026-02-01', 'kwota_pln' => 100], headers: []);
    $response = $this->invoiceController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('invoice store returns 409 when numer already exists', function (): void {
    $this->invoiceRepository->shouldReceive('findByNumber')->with('F-1')->andReturn(['id' => 5]);
    $request = new Request(query: [], body: ['numer_faktury' => 'F-1', 'klient_nazwa' => 'Klient', 'data_wystawienia' => '2026-02-01', 'kwota_pln' => 100], headers: []);
    $response = $this->invoiceController->store($request);
    expect($response->statusCode())->toBe(409);
});

it('invoice store creates invoice and returns 201', function (): void {
    $this->invoiceRepository->shouldReceive('findByNumber')->with('F-1')->andReturnNull();
    $this->invoiceRepository->shouldReceive('createInvoice')->with(m::on(function (array $d): bool {
        return $d['numer_faktury'] === 'F-1' && $d['klient_nazwa'] === 'Klient' && $d['kwota_pln'] === 100.0 && $d['typ_wystawienia'] === 'po_zleceniu';
    }))->andReturn(['id' => 1, 'order_id' => null, 'numer_faktury' => 'F-1', 'klient_nazwa' => 'Klient', 'kwota_pln' => 100, 'data_wystawienia' => '2026-02-01', 'termin_platnosci' => null, 'status' => 'wystawiona', 'typ_wystawienia' => 'po_zleceniu', 'created_at' => null, 'updated_at' => null]);

    $request = new Request(query: [], body: ['numer_faktury' => 'F-1', 'klient_nazwa' => 'Klient', 'data_wystawienia' => '2026-02-01', 'kwota_pln' => 100], headers: []);
    $response = $this->invoiceController->store($request);
    expect($response->statusCode())->toBe(201);
    expect($response->data()['numer_faktury'])->toBe('F-1');
});

it('invoice updateStatus rejects invalid status', function (): void {
    $this->invoiceRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $request = new Request(query: [], body: ['status' => 'bzdura'], headers: []);
    $response = $this->invoiceController->updateStatus($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('invoice updateStatus updates status', function (): void {
    $this->invoiceRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1]);
    $this->invoiceRepository->shouldReceive('updateInvoice')->with(1, ['status' => 'zaplacona'])->andReturn(['id' => 1, 'order_id' => null, 'numer_faktury' => 'F-1', 'klient_nazwa' => 'Klient', 'kwota_pln' => 100, 'data_wystawienia' => '2026-02-01', 'termin_platnosci' => null, 'status' => 'zaplacona', 'typ_wystawienia' => 'po_zleceniu', 'created_at' => null, 'updated_at' => null]);
    $request = new Request(query: [], body: ['status' => 'zaplacona'], headers: []);
    $response = $this->invoiceController->updateStatus($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['status'])->toBe('zaplacona');
});

it('invoice missing returns orders without invoice', function (): void {
    $this->invoiceRepository->shouldReceive('findOrdersWithoutInvoice')->with(25, 0)->andReturn([
        ['id' => 3, 'numer_zlecenia' => 'ZL-003', 'klient_nazwa' => 'Klient', 'terminal_id' => 1, 'data_zakonczenia' => '2026-02-01 16:00:00', 'wartosc_pln' => 3200],
    ]);
    $this->invoiceRepository->shouldReceive('countOrdersWithoutInvoice')->andReturn(1);

    $response = $this->invoiceController->missing(new Request(query: [], body: [], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
    expect($response->data()['data'][0]['numer_zlecenia'])->toBe('ZL-003');
});

it('invoice destroy returns 404 for missing', function (): void {
    $this->invoiceRepository->shouldReceive('findById')->with(99)->andReturnNull();
    $response = $this->invoiceController->destroy(new Request(query: [], body: [], headers: []), ['id' => '99']);
    expect($response->statusCode())->toBe(404);
});