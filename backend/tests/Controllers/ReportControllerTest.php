<?php

declare(strict_types=1);

use App\Controllers\ReportController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\DailyTerminalReportRepository;
use App\Repository\DailyVehicleReportRepository;
use App\Repository\EquipmentRepository;
use App\Repository\OrderRepository;
use App\Repository\TerminalRepository;
use App\Services\ReportService;
use PDO;
use Mockery as m;

function terminalReportRow(int $id = 1, int $terminalId = 1, string $date = '2026-06-17'): array {
    return [
        'id' => $id, 'terminal_id' => $terminalId, 'terminal_nazwa' => 'BCT',
        'data_raportu' => $date, 'opis' => 'Opis', 'uwagi' => null,
        'utworzony_przez' => 1, 'utworzony_przez_email' => 'admin@pbs.local',
        'created_at' => null, 'updated_at' => null,
    ];
}

function vehicleReportRow(int $id = 1, int $equipmentId = 1, string $date = '2026-06-17'): array {
    return [
        'id' => $id, 'equipment_id' => $equipmentId, 'equipment_nazwa' => 'RS-02',
        'equipment_numer_seryjny' => 'RS-02', 'equipment_kategoria' => 'pojazd',
        'data_raportu' => $date, 'aktualny_przebieg' => 14820, 'przebieg_oc' => 'OK',
        'uwagi' => null, 'utworzony_przez' => 1, 'utworzony_przez_email' => 'admin@pbs.local',
        'created_at' => null, 'updated_at' => null,
    ];
}

function reportAuthedRequest(array $body = [], array $query = []): Request {
    $request = new Request(query: $query, body: $body, headers: []);
    $request->setAttribute('user_id', 1);

    return $request;
}

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->terminalReportRepository = m::mock(DailyTerminalReportRepository::class, [$pdo]);
    $this->vehicleReportRepository = m::mock(DailyVehicleReportRepository::class, [$pdo]);
    $this->terminalRepository = m::mock(TerminalRepository::class, [$pdo]);
    $this->equipmentRepository = m::mock(EquipmentRepository::class, [$pdo]);
    $this->orderRepository = m::mock(OrderRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();

    $this->reportService = new ReportService(
        $this->terminalReportRepository,
        $this->vehicleReportRepository,
        $this->terminalRepository,
        $this->equipmentRepository,
        $this->orderRepository,
        $this->auditLogRepository,
    );
    $this->reportController = new ReportController($this->reportService);
});

afterEach(function (): void {
    m::close();
});

// --- Raporty terminalowe ---

it('terminalIndex returns paginated list', function (): void {
    $filters = ['terminal_id' => '', 'date_from' => '', 'date_to' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->terminalReportRepository->shouldReceive('search')->with($filters, 25, 0, 'id', 'asc')->andReturn([terminalReportRow()]);
    $this->terminalReportRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $response = $this->reportController->terminalIndex(new Request(query: [], body: [], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
});

it('terminalStore returns 422 when terminal missing', function (): void {
    expect($this->reportController->terminalStore(reportAuthedRequest(['data_raportu' => '2026-06-17', 'opis' => 'Opis']))->statusCode())->toBe(422);
});

it('terminalStore returns 422 when opis missing', function (): void {
    expect($this->reportController->terminalStore(reportAuthedRequest(['terminal_id' => 1, 'data_raportu' => '2026-06-17']))->statusCode())->toBe(422);
});

it('terminalStore returns 422 when terminal not found', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(5)->andReturnNull();
    expect($this->reportController->terminalStore(reportAuthedRequest(['terminal_id' => 5, 'data_raportu' => '2026-06-17', 'opis' => 'Opis']))->statusCode())->toBe(422);
});

it('terminalStore returns 409 on duplicate date', function (): void {
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'BCT']);
    $this->terminalReportRepository->shouldReceive('existsForTerminalAndDate')->with(1, '2026-06-17')->andReturn(true);
    expect($this->reportController->terminalStore(reportAuthedRequest(['terminal_id' => 1, 'data_raportu' => '2026-06-17', 'opis' => 'Opis']))->statusCode())->toBe(409);
});

it('terminalShow returns report with auto data', function (): void {
    $this->terminalReportRepository->shouldReceive('findById')->with(1)->andReturn(terminalReportRow());
    $this->orderRepository->shouldReceive('findOrdersForTerminalOnDate')->with(1, '2026-06-17')->andReturn([]);

    $response = $this->reportController->terminalShow(new Request(query: [], body: [], headers: []), ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['id'])->toBe(1);
    expect($response->data()['auto_data'])->toBeArray();
});

it('terminalShow returns 404 for missing report', function (): void {
    $this->terminalReportRepository->shouldReceive('findById')->with(99)->andReturnNull();

    $response = $this->reportController->terminalShow(new Request(query: [], body: [], headers: []), ['id' => '99']);
    expect($response->statusCode())->toBe(404);
});

it('terminalUpdate returns 404 for missing report', function (): void {
    $this->terminalReportRepository->shouldReceive('findById')->with(99)->andReturnNull();

    expect($this->reportController->terminalUpdate(
        reportAuthedRequest(['terminal_id' => 1, 'data_raportu' => '2026-06-17', 'opis' => 'Opis']),
        ['id' => '99'],
    )->statusCode())->toBe(404);
});

it('terminalUpdate returns 200 and updates report', function (): void {
    $this->terminalReportRepository->shouldReceive('findById')->with(1)->andReturn(terminalReportRow());
    $this->terminalRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'BCT']);
    $this->terminalReportRepository->shouldReceive('existsForTerminalAndDate')->with(1, '2026-06-17', 1)->andReturn(false);
    $this->terminalReportRepository->shouldReceive('update')
        ->with(1, m::on(fn (array $data): bool => $data['opis'] === 'Nowy opis'))
        ->andReturn(terminalReportRow(1, 1, '2026-06-17'));
    $this->orderRepository->shouldReceive('findOrdersForTerminalOnDate')->with(1, '2026-06-17')->andReturn([]);

    $response = $this->reportController->terminalUpdate(
        reportAuthedRequest(['terminal_id' => 1, 'data_raportu' => '2026-06-17', 'opis' => 'Nowy opis']),
        ['id' => '1'],
    );
    expect($response->statusCode())->toBe(200);
});


// --- Raporty pojazdowe ---

it('vehicleIndex returns paginated list', function (): void {
    $filters = ['equipment_id' => '', 'date_from' => '', 'date_to' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->vehicleReportRepository->shouldReceive('search')->with($filters, 25, 0, 'id', 'asc')->andReturn([vehicleReportRow()]);
    $this->vehicleReportRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $response = $this->reportController->vehicleIndex(new Request(query: [], body: [], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
});

it('vehicleStore returns 422 when equipment missing', function (): void {
    expect($this->reportController->vehicleStore(
        reportAuthedRequest(['data_raportu' => '2026-06-17', 'aktualny_przebieg' => 100, 'przebieg_oc' => 'OK']),
    )->statusCode())->toBe(422);
});

it('vehicleStore returns 422 when equipment not found', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(5)->andReturnNull();

    expect($this->reportController->vehicleStore(
        reportAuthedRequest(['equipment_id' => 5, 'data_raportu' => '2026-06-17', 'aktualny_przebieg' => 100, 'przebieg_oc' => 'OK']),
    )->statusCode())->toBe(422);
});

it('vehicleStore returns 409 on duplicate date', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'RS-02']);
    $this->vehicleReportRepository->shouldReceive('existsForEquipmentAndDate')->with(1, '2026-06-17')->andReturn(true);

    expect($this->reportController->vehicleStore(
        reportAuthedRequest(['equipment_id' => 1, 'data_raportu' => '2026-06-17', 'aktualny_przebieg' => 100, 'przebieg_oc' => 'OK']),
    )->statusCode())->toBe(409);
});

it('vehicleStore returns 201 and creates report', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'RS-02']);
    $this->vehicleReportRepository->shouldReceive('existsForEquipmentAndDate')->with(1, '2026-06-17')->andReturn(false);
    $this->vehicleReportRepository->shouldReceive('create')
        ->with(m::on(fn (array $data): bool => $data['equipment_id'] === 1 && $data['aktualny_przebieg'] === 100))
        ->andReturn(vehicleReportRow());

    $response = $this->reportController->vehicleStore(
        reportAuthedRequest(['equipment_id' => 1, 'data_raportu' => '2026-06-17', 'aktualny_przebieg' => 100, 'przebieg_oc' => 'OK']),
    );
    expect($response->statusCode())->toBe(201);
});

it('vehicleShow returns 404 for missing report', function (): void {
    $this->vehicleReportRepository->shouldReceive('findById')->with(99)->andReturnNull();

    $response = $this->reportController->vehicleShow(new Request(query: [], body: [], headers: []), ['id' => '99']);
    expect($response->statusCode())->toBe(404);
});

it('vehicleShow returns report', function (): void {
    $this->vehicleReportRepository->shouldReceive('findById')->with(1)->andReturn(vehicleReportRow());

    $response = $this->reportController->vehicleShow(new Request(query: [], body: [], headers: []), ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['aktualny_przebieg'])->toBe(14820);
});

it('vehicleUpdate returns 404 for missing report', function (): void {
    $this->vehicleReportRepository->shouldReceive('findById')->with(99)->andReturnNull();

    expect($this->reportController->vehicleUpdate(
        reportAuthedRequest(['equipment_id' => 1, 'data_raportu' => '2026-06-17', 'aktualny_przebieg' => 100, 'przebieg_oc' => 'OK']),
        ['id' => '99'],
    )->statusCode())->toBe(404);
});

it('vehicleUpdate returns 200 and updates report', function (): void {
    $this->vehicleReportRepository->shouldReceive('findById')->with(1)->andReturn(vehicleReportRow());
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'nazwa' => 'RS-02']);
    $this->vehicleReportRepository->shouldReceive('existsForEquipmentAndDate')->with(1, '2026-06-17', 1)->andReturn(false);
    $this->vehicleReportRepository->shouldReceive('update')
        ->with(1, m::on(fn (array $data): bool => $data['aktualny_przebieg'] === 15000))
        ->andReturn(vehicleReportRow(1, 1, '2026-06-17'));

    $response = $this->reportController->vehicleUpdate(
        reportAuthedRequest(['equipment_id' => 1, 'data_raportu' => '2026-06-17', 'aktualny_przebieg' => 15000, 'przebieg_oc' => 'OK']),
        ['id' => '1'],
    );
    expect($response->statusCode())->toBe(200);
});

