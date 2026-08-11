<?php

declare(strict_types=1);

use App\Controllers\TerminalController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EmployeeRateRepository;
use App\Repository\OrderRepository;
use App\Repository\TerminalRepository;
use App\Services\TerminalService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->terminalRepository = m::mock(TerminalRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();
    $this->orderRepository = m::mock(OrderRepository::class, [$pdo]);
    $this->employeeRateRepository = m::mock(EmployeeRateRepository::class, [$pdo]);

    $this->terminalService = new TerminalService(
        $this->terminalRepository,
        $this->auditLogRepository,
        $this->orderRepository,
        $this->employeeRateRepository,
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

// --- HOURS SUMMARY ---

it('hoursSummary returns per-port hours and wages with total row', function (): void {
    $this->orderRepository->shouldReceive('settlementDetail')->with('2026-06', 'all')->andReturn([
        ['employee_id' => 1, 'data_zlecenia' => '2026-06-05', 'godziny' => 8, 'terminal_id' => 1, 'rola' => 'operator'],
        ['employee_id' => 2, 'data_zlecenia' => '2026-06-06', 'godziny' => 7, 'terminal_id' => 1, 'rola' => 'operator'],
        ['employee_id' => 3, 'data_zlecenia' => '2026-06-10', 'godziny' => 10, 'terminal_id' => 2, 'rola' => 'brygadzista'],
    ]);
    $this->employeeRateRepository->shouldReceive('findAllByEmployeeIds')->with([1, 2, 3])->andReturn([
        1 => [['data_od' => '2026-01-01', 'stawka_godzinowa' => '45']],
        2 => [['data_od' => '2026-01-01', 'stawka_godzinowa' => '45']],
        3 => [['data_od' => '2026-01-01', 'stawka_godzinowa' => '40']],
    ]);
    $this->terminalRepository->shouldReceive('findAll')->with(['is_active' => 1], 1000, 0)->andReturn([
        ['id' => 1, 'nazwa' => 'BCT'],
        ['id' => 2, 'nazwa' => 'DCT'],
        ['id' => 3, 'nazwa' => 'GCT'],
    ]);

    $request = new Request(query: ['month' => '2026-06', 'period' => 'all'], body: [], headers: []);
    $response = $this->terminalController->hoursSummary($request);

    expect($response->statusCode())->toBe(200);
    $data = $response->data()['data'];

    // BCT: 15 h (8 + 7), 675 zł (360 + 315)
    $bct = null;
    foreach ($data as $row) {
        if (($row['terminal_id'] ?? null) === 1) {
            $bct = $row;
        }
    }
    expect($bct['suma_godzin'])->toBe(15.0);
    expect($bct['suma_wynagrodzen'])->toBe(675.0);

    // GCT bez zleceń — zera
    $gct = null;
    foreach ($data as $row) {
        if (($row['terminal_id'] ?? null) === 3) {
            $gct = $row;
        }
    }
    expect($gct['suma_godzin'])->toBe(0.0);

    // Wiersz „Razem"
    $total = end($data);
    expect($total['terminal_nazwa'])->toBe('Razem (wszystkie porty)');
    expect($total['suma_godzin'])->toBe(25.0);
    expect($total['suma_wynagrodzen'])->toBe(1075.0);
});

it('hoursSummary defaults to current month and all period', function (): void {
    $currentMonth = date('Y-m');
    $this->orderRepository->shouldReceive('settlementDetail')->with($currentMonth, 'all')->andReturn([]);
    $this->employeeRateRepository->shouldReceive('findAllByEmployeeIds')->with([])->andReturn([]);
    $this->terminalRepository->shouldReceive('findAll')->with(['is_active' => 1], 1000, 0)->andReturn([]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->terminalController->hoursSummary($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['month'])->toBe($currentMonth);
    expect($response->data()['period'])->toBe('all');
    expect($response->data()['data'])->toHaveCount(1); // tylko wiersz „Razem"
});