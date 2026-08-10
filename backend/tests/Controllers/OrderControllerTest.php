<?php

declare(strict_types=1);

use App\Controllers\OrderController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EquipmentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\OrderRepository;
use App\Repository\TerminalRepository;
use App\Services\OrderService;
use PDO;
use Mockery as m;

function orderRow(int $id = 1, string $numer = 'ZL-001'): array {
    return ['id' => $id, 'numer_zlecenia' => $numer, 'klient_nazwa' => 'K', 'terminal_id' => 1, 'terminal_nazwa' => 'T1', 'data_rozpoczecia' => '2026-01-01 08:00:00', 'data_zakonczenia' => '2026-01-01 16:00:00', 'zakres_prac' => '', 'wartosc_pln' => 0, 'status' => 'nowe', 'created_at' => null, 'updated_at' => null];
}

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->orderRepository = m::mock(OrderRepository::class, [$pdo]);
    $this->terminalRepository = m::mock(TerminalRepository::class, [$pdo]);
    $this->employeeRepository = m::mock(EmployeeRepository::class, [$pdo]);
    $this->equipmentRepository = m::mock(EquipmentRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();

    $this->orderService = new OrderService(
        $this->orderRepository,
        $this->terminalRepository,
        $this->employeeRepository,
        $this->equipmentRepository,
        $this->auditLogRepository,
    );
    $this->orderController = new OrderController($this->orderService);
});

afterEach(function (): void {
    m::close();
});

it('index returns paginated list', function (): void {
    $filters = ['numer' => '', 'klient' => '', 'terminal_id' => '', 'status' => '', 'date_from' => '', 'date_to' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->orderRepository->shouldReceive('search')->with($filters, 25, 0, 'id', 'asc')->andReturn([orderRow()]);
    $this->orderRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $response = $this->orderController->index(new Request(query: [], body: [], headers: []));

    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
    expect($response->data()['data'][0]['numer_zlecenia'])->toBe('ZL-001');
});

it('index applies filters from query string', function (): void {
    $filters = ['numer' => 'ZL', 'klient' => '', 'terminal_id' => '', 'status' => 'nowe', 'date_from' => '', 'date_to' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->orderRepository->shouldReceive('search')->with($filters, 10, 0, 'id', 'asc')->andReturn([]);
    $this->orderRepository->shouldReceive('countSearch')->with($filters)->andReturn(0);

    $request = new Request(query: ['numer' => 'ZL', 'status' => 'nowe', 'per_page' => '10'], body: [], headers: []);
    $response = $this->orderController->index($request);

    expect($response->data()['per_page'])->toBe(10);
});

it('store returns 422 when numer missing', function (): void {
    $request = new Request(query: [], body: ['klient_nazwa' => 'K', 'terminal_id' => 1, 'data_rozpoczecia' => '2026-01-01 08:00:00', 'data_zakonczenia' => '2026-01-01 16:00:00'], headers: []);
    expect($this->orderController->store($request)->statusCode())->toBe(422);
});

it('store returns 409 when number already exists', function (): void {
    $this->orderRepository->shouldReceive('findByNumber')->with('ZL-001')->andReturn(['id' => 5]);
    $request = new Request(query: [], body: ['numer_zlecenia' => 'ZL-001', 'klient_nazwa' => 'K', 'terminal_id' => 1, 'data_rozpoczecia' => '2026-01-01 08:00:00', 'data_zakonczenia' => '2026-01-01 16:00:00'], headers: []);
    expect($this->orderController->store($request)->statusCode())->toBe(409);
});

it('store returns 422 when terminal not found', function (): void {
    $this->orderRepository->shouldReceive('findByNumber')->with('ZL-002')->andReturnNull();
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturnNull();

    $request = new Request(query: [], body: ['numer_zlecenia' => 'ZL-002', 'klient_nazwa' => 'K', 'terminal_id' => 1, 'data_rozpoczecia' => '2026-01-01 08:00:00', 'data_zakonczenia' => '2026-01-01 16:00:00'], headers: []);
    expect($this->orderController->store($request)->statusCode())->toBe(422);
});

it('store creates order and returns 201', function (): void {
    $this->orderRepository->shouldReceive('findByNumber')->with('ZL-003')->andReturnNull();
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'T1']);
    $this->orderRepository->shouldReceive('createOrder')
        ->with(m::on(fn (array $d): bool => $d['numer_zlecenia'] === 'ZL-003' && $d['terminal_id'] === 1 && $d['status'] === 'nowe'))
        ->andReturn(orderRow(10, 'ZL-003'));

    $request = new Request(query: [], body: ['numer_zlecenia' => 'ZL-003', 'klient_nazwa' => 'K', 'terminal_id' => 1, 'data_rozpoczecia' => '2026-01-01 08:00:00', 'data_zakonczenia' => '2026-01-01 16:00:00'], headers: []);
    $response = $this->orderController->store($request);

    expect($response->statusCode())->toBe(201);
    expect($response->data()['numer_zlecenia'])->toBe('ZL-003');
});

it('show returns 404 for missing order', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(99)->andReturnNull();
    $this->orderRepository->shouldReceive('findAssignedEmployees')->byDefault();
    $this->orderRepository->shouldReceive('findAssignedEquipment')->byDefault();

    expect($this->orderController->show(new Request(query: [], body: [], headers: []), ['id' => '99'])->statusCode())->toBe(404);
});

it('show returns order with assignments', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->orderRepository->shouldReceive('findAssignedEmployees')->with(1)->andReturn([['id' => 1, 'order_id' => 1, 'employee_id' => 2, 'imie' => 'Jan', 'nazwisko' => 'Kowalski', 'email' => 'j@k.pl']]);
    $this->orderRepository->shouldReceive('findAssignedEquipment')->with(1)->andReturn([]);

    $response = $this->orderController->show(new Request(query: [], body: [], headers: []), ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['employees'][0]['employee_name'])->toBe('Jan Kowalski');
});

it('delete returns 404 for missing order', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(5)->andReturnNull();
    expect($this->orderController->destroy(new Request(query: [], body: [], headers: []), ['id' => '5'])->statusCode())->toBe(404);
});

it('delete removes order and returns success', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(3)->andReturn(orderRow(3, 'ZL-003'));
    $this->orderRepository->shouldReceive('deleteOrder')->with(3)->andReturn(true);

    $response = $this->orderController->destroy(new Request(query: [], body: [], headers: []), ['id' => '3']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});

it('copyWeek returns 422 for invalid dates', function (): void {
    $response = $this->orderController->copyWeek(new Request(query: [], body: ['source_week_start' => 'bad'], headers: []));
    expect($response->statusCode())->toBe(422);
});

it('copyWeek copies orders and returns count', function (): void {
    $this->orderRepository->shouldReceive('findBetweenDates')->andReturn([orderRow(1, 'ZL-001')]);
    $this->orderRepository->shouldReceive('findByNumber')->andReturnNull();
    $this->orderRepository->shouldReceive('createOrder')->andReturn(['id' => 20]);
    $this->orderRepository->shouldReceive('findAssignedEmployees')->andReturn([]);
    $this->orderRepository->shouldReceive('findAssignedEquipment')->andReturn([]);

    $response = $this->orderController->copyWeek(new Request(query: [], body: ['source_week_start' => '2026-01-05', 'target_week_start' => '2026-01-12'], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['copied'])->toBe(1);
});

it('assignEmployee returns 404 for missing order', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturnNull();
    expect($this->orderController->assignEmployee(new Request(query: [], body: ['employee_id' => 2], headers: []), ['id' => '1'])->statusCode())->toBe(404);
});

it('assignEmployee returns 422 when employee not found', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->employeeRepository->shouldReceive('findById')->with(2)->andReturnNull();
    expect($this->orderController->assignEmployee(new Request(query: [], body: ['employee_id' => 2], headers: []), ['id' => '1'])->statusCode())->toBe(422);
});

it('assignEmployee returns 409 when already assigned', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->employeeRepository->shouldReceive('findById')->with(2)->andReturn(['id' => 2]);
    $this->orderRepository->shouldReceive('isEmployeeAssigned')->with(1, 2)->andReturn(true);
    expect($this->orderController->assignEmployee(new Request(query: [], body: ['employee_id' => 2], headers: []), ['id' => '1'])->statusCode())->toBe(409);
});

it('assignEmployee attaches and returns 201', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->employeeRepository->shouldReceive('findById')->with(2)->andReturn(['id' => 2]);
    $this->orderRepository->shouldReceive('isEmployeeAssigned')->with(1, 2)->andReturn(false);
    $this->orderRepository->shouldReceive('attachEmployee')->with(1, 2, null, null)->andReturn(true);

    $response = $this->orderController->assignEmployee(new Request(query: [], body: ['employee_id' => 2], headers: []), ['id' => '1']);
    expect($response->statusCode())->toBe(201);
    expect($response->data()['assigned'])->toBeTrue();
});

it('assignEmployee saves rola and godziny', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->employeeRepository->shouldReceive('findById')->with(2)->andReturn(['id' => 2]);
    $this->orderRepository->shouldReceive('isEmployeeAssigned')->with(1, 2)->andReturn(false);
    $this->orderRepository->shouldReceive('attachEmployee')->with(1, 2, 'operator', 8.0)->andReturn(true);

    $response = $this->orderController->assignEmployee(
        new Request(query: [], body: ['employee_id' => 2, 'rola' => 'operator', 'godziny' => 8], headers: []),
        ['id' => '1'],
    );
    expect($response->statusCode())->toBe(201);
    expect($response->data()['rola'])->toBe('operator');
    expect($response->data()['godziny'])->toBe(8.0);
});

it('assignEmployee rejects invalid rola with 422', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->employeeRepository->shouldReceive('findById')->with(2)->andReturn(['id' => 2]);
    $this->orderRepository->shouldReceive('isEmployeeAssigned')->with(1, 2)->andReturn(false);

    $response = $this->orderController->assignEmployee(
        new Request(query: [], body: ['employee_id' => 2, 'rola' => 'nieistniejaca'], headers: []),
        ['id' => '1'],
    );
    expect($response->statusCode())->toBe(422);
});

it('unassignEmployee returns 404 when not assigned', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->orderRepository->shouldReceive('isEmployeeAssigned')->with(1, 2)->andReturn(false);
    expect($this->orderController->unassignEmployee(new Request(query: [], body: [], headers: []), ['id' => '1', 'employee_id' => '2'])->statusCode())->toBe(404);
});

it('unassignEmployee removes and returns success', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->orderRepository->shouldReceive('isEmployeeAssigned')->with(1, 2)->andReturn(true);
    $this->orderRepository->shouldReceive('detachEmployee')->with(1, 2)->andReturn(true);
    $response = $this->orderController->unassignEmployee(new Request(query: [], body: [], headers: []), ['id' => '1', 'employee_id' => '2']);
    expect($response->data()['success'])->toBeTrue();
});

it('assignEquipment returns 422 when equipment not found', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->equipmentRepository->shouldReceive('findById')->with(5)->andReturnNull();
    expect($this->orderController->assignEquipment(new Request(query: [], body: ['equipment_id' => 5], headers: []), ['id' => '1'])->statusCode())->toBe(422);
});

it('unassignEquipment removes and returns success', function (): void {
    $this->orderRepository->shouldReceive('findById')->with(1)->andReturn(orderRow());
    $this->orderRepository->shouldReceive('isEquipmentAssigned')->with(1, 5)->andReturn(true);
    $this->orderRepository->shouldReceive('detachEquipment')->with(1, 5)->andReturn(true);
    $response = $this->orderController->unassignEquipment(new Request(query: [], body: [], headers: []), ['id' => '1', 'equipment_id' => '5']);
    expect($response->data()['success'])->toBeTrue();
});