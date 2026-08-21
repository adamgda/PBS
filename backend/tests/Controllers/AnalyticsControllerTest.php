<?php

declare(strict_types=1);

use App\Controllers\AnalyticsController;
use App\Http\Request;
use App\Repository\AnalyticsRepository;
use App\Services\AnalyticsService;
use Mockery as m;

function analyticsOverviewRow(): array {
    return [
        'total_orders' => 128,
        'total_value' => '1920000.00',
        'total_hours' => '1440.00',
        'total_wages' => '20056.00',
        'total_incidents' => 5,
        'incident_downtime_hours' => '48.00',
    ];
}

function analyticsTerminalRow(int $id = 1, string $nazwa = 'BCT', int $orders = 52, string $hours = '620.00'): array {
    return [
        'terminal_id' => $id, 'nazwa' => $nazwa,
        'order_count' => $orders, 'total_hours' => $hours,
    ];
}

function analyticsEmployeeRow(int $id = 1, string $imie = 'Marek', string $nazwisko = 'Nowak', string $hours = '168.00', string $wages = '6720.00', ?string $rola = 'operator'): array {
    return [
        'employee_id' => $id, 'imie' => $imie, 'nazwisko' => $nazwisko,
        'total_hours' => $hours, 'total_wages' => $wages, 'rola' => $rola,
    ];
}

function analyticsEquipmentRow(int $id = 1, string $nazwa = 'RS-02', string $kategoria = 'pojazd', int $count = 22): array {
    return [
        'equipment_id' => $id, 'nazwa' => $nazwa, 'kategoria' => $kategoria,
        'assignment_count' => $count,
    ];
}

function analyticsRelationRow(int $id = 1, string $imie = 'Marek', string $nazwisko = 'Nowak', int $count = 22, string $terminal = 'BCT', string $equipment = 'RS-02', string $hours = '168.00'): array {
    return [
        'employee_id' => $id, 'imie' => $imie, 'nazwisko' => $nazwisko,
        'assignment_count' => $count, 'terminal_nazwa' => $terminal,
        'equipment_nazwa' => $equipment, 'total_hours' => $hours,
    ];
}

function analyticsRequest(array $query = []): Request {
    return new Request(query: $query, body: [], headers: []);
}

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->analyticsRepository = m::mock(AnalyticsRepository::class, [$pdo]);
    $this->analyticsService = new AnalyticsService($this->analyticsRepository);
    $this->analyticsController = new AnalyticsController($this->analyticsService);
});

afterEach(function (): void {
    m::close();
});

it('overview returns KPI data', function (): void {
    $this->analyticsRepository->shouldReceive('overview')
        ->with('2026-07-01 00:00:00', '2026-07-31 23:59:59')
        ->andReturn(analyticsOverviewRow());

    $response = $this->analyticsController->overview(analyticsRequest(['date_from' => '2026-07-01', 'date_to' => '2026-07-31']));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['total_orders'])->toBe(128);
    expect($response->data()['total_hours'])->toBe(1440.0);
    expect($response->data()['incident_downtime_hours'])->toBe(48.0);
});

it('overview returns 422 for invalid date_from', function (): void {
    $response = $this->analyticsController->overview(analyticsRequest(['date_from' => 'not-a-date']));
    expect($response->statusCode())->toBe(422);
});

it('terminals returns per-terminal stats', function (): void {
    $this->analyticsRepository->shouldReceive('terminals')
        ->with('2026-07-01 00:00:00', '2026-07-31 23:59:59')
        ->andReturn([analyticsTerminalRow()]);

    $response = $this->analyticsController->terminals(analyticsRequest(['date_from' => '2026-07-01', 'date_to' => '2026-07-31']));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'][0]['nazwa'])->toBe('BCT');
    expect($response->data()['data'][0]['order_count'])->toBe(52);
});

it('employees returns per-employee stats', function (): void {
    $this->analyticsRepository->shouldReceive('employees')
        ->with('2026-07-01 00:00:00', '2026-07-31 23:59:59')
        ->andReturn([analyticsEmployeeRow()]);

    $response = $this->analyticsController->employees(analyticsRequest(['date_from' => '2026-07-01', 'date_to' => '2026-07-31']));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'][0]['total_wages'])->toBe(6720.0);
});

it('equipment returns per-equipment stats', function (): void {
    $this->analyticsRepository->shouldReceive('equipment')
        ->with('2026-07-01 00:00:00', '2026-07-31 23:59:59')
        ->andReturn([analyticsEquipmentRow()]);

    $response = $this->analyticsController->equipment(analyticsRequest(['date_from' => '2026-07-01', 'date_to' => '2026-07-31']));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'][0]['assignment_count'])->toBe(22);
});

it('ordersInTime returns daily order counts', function (): void {
    $this->analyticsRepository->shouldReceive('ordersInTime')
        ->with('2026-07-01 00:00:00', '2026-07-31 23:59:59')
        ->andReturn([
            ['day' => '2026-07-01', 'count' => 2],
            ['day' => '2026-07-02', 'count' => 0],
        ]);

    $response = $this->analyticsController->ordersInTime(analyticsRequest(['date_from' => '2026-07-01', 'date_to' => '2026-07-31']));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'][0])->toBe(['day' => '2026-07-01', 'count' => 2]);
    expect($response->data()['data'][1])->toBe(['day' => '2026-07-02', 'count' => 0]);
});

it('relations returns top employees', function (): void {
    $this->analyticsRepository->shouldReceive('relations')
        ->with('2026-07-01 00:00:00', '2026-07-31 23:59:59')
        ->andReturn([analyticsRelationRow()]);

    $response = $this->analyticsController->relations(analyticsRequest(['date_from' => '2026-07-01', 'date_to' => '2026-07-31']));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'][0]['terminal_nazwa'])->toBe('BCT');
    expect($response->data()['data'][0]['equipment_nazwa'])->toBe('RS-02');
});

it('uses default 30-day range when no dates provided', function (): void {
    $this->analyticsRepository->shouldReceive('overview')
        ->withArgs(fn (string $from, string $to): bool => str_contains($from, ' 00:00:00') && str_contains($to, ' 23:59:59'))
        ->andReturn(analyticsOverviewRow());

    $response = $this->analyticsController->overview(analyticsRequest());
    expect($response->statusCode())->toBe(200);
});
