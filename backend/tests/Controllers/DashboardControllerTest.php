<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Http\Request;
use App\Repository\DashboardRepository;
use App\Services\DashboardService;
use Mockery as m;

function dashboardSummaryRow(): array
{
    return [
        'active_employees' => 248,
        'active_terminals' => 12,
        'vehicles_in_use' => 86,
        'active_incidents' => 3,
        'hours_today' => '42.50',
        'employees_on_leave' => 5,
        'monthly_wages' => '20056.00',
    ];
}

function dashboardAlertGroup(int $count = 7, array $items = []): array
{
    return ['count' => $count, 'items' => $items];
}

function dashboardRequest(): Request
{
    return new Request(query: [], body: [], headers: []);
}

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->dashboardRepository = m::mock(DashboardRepository::class, [$pdo]);
    $this->dashboardService = new DashboardService($this->dashboardRepository);
    $this->dashboardController = new DashboardController($this->dashboardService);
});

afterEach(function (): void {
    m::close();
});

it('summary returns KPI data', function (): void {
    $this->dashboardRepository->shouldReceive('summary')->once()->andReturn(dashboardSummaryRow());

    $response = $this->dashboardController->summary(dashboardRequest());
    expect($response->statusCode())->toBe(200);
    expect($response->data()['active_employees'])->toBe(248);
    expect($response->data()['active_terminals'])->toBe(12);
    expect($response->data()['vehicles_in_use'])->toBe(86);
    expect($response->data()['active_incidents'])->toBe(3);
    expect($response->data()['hours_today'])->toBe(42.5);
    expect($response->data()['employees_on_leave'])->toBe(5);
    expect($response->data()['monthly_wages'])->toBe(20056.0);
});

it('summary defaults missing KPI values to zero', function (): void {
    $this->dashboardRepository->shouldReceive('summary')->once()->andReturn([]);

    $response = $this->dashboardController->summary(dashboardRequest());
    expect($response->statusCode())->toBe(200);
    expect($response->data()['active_employees'])->toBe(0);
    expect($response->data()['monthly_wages'])->toBe(0.0);
});

it('alerts returns alert groups with counts and items', function (): void {
    $this->dashboardRepository->shouldReceive('alerts')->once()->andReturn([
        'expiring_certs' => dashboardAlertGroup(7, [['id' => 1, 'nazwa' => 'Prawo jazdy', 'data_waznosci' => '2026-09-01']]),
        'upcoming_inspections' => dashboardAlertGroup(4, []),
        'unresolved_incidents' => dashboardAlertGroup(3, []),
        'returning_from_leave' => dashboardAlertGroup(5, []),
    ]);

    $response = $this->dashboardController->alerts(dashboardRequest());
    expect($response->statusCode())->toBe(200);
    expect($response->data()['expiring_certs']['count'])->toBe(7);
    expect($response->data()['expiring_certs']['items'][0]['nazwa'])->toBe('Prawo jazdy');
    expect($response->data()['upcoming_inspections']['count'])->toBe(4);
    expect($response->data()['unresolved_incidents']['count'])->toBe(3);
    expect($response->data()['returning_from_leave']['count'])->toBe(5);
});

it('alerts normalizes missing groups to empty', function (): void {
    $this->dashboardRepository->shouldReceive('alerts')->once()->andReturn([]);

    $response = $this->dashboardController->alerts(dashboardRequest());
    expect($response->statusCode())->toBe(200);
    expect($response->data()['expiring_certs']['count'])->toBe(0);
    expect($response->data()['expiring_certs']['items'])->toBe([]);
    expect($response->data()['returning_from_leave']['count'])->toBe(0);
});

it('charts returns chart and activity data', function (): void {
    $this->dashboardRepository->shouldReceive('ordersTrend')->once()->andReturn([
        'categories' => ['10.08', '11.08'],
        'series' => [2, 4],
        'trend_pct' => 33.3,
    ]);
    $this->dashboardRepository->shouldReceive('fleetStructure')->once()->andReturn([
        'terminals' => 3,
        'vehicles' => 3,
        'employees' => 5,
        'other_equipment' => 3,
    ]);
    $this->dashboardRepository->shouldReceive('terminalTurnover')->once()->andReturn([
        ['nazwa' => 'Terminal Gdańsk', 'turnover' => '5000.00'],
        ['nazwa' => 'Terminal Gdynia', 'turnover' => '7500.00'],
    ]);
    $this->dashboardRepository->shouldReceive('recentActivity')->once()->andReturn([
        ['type' => 'order', 'title' => 'ZL-2026-001 · Baltic Operator Sp. z o.o.', 'ts' => '2026-08-11 08:00:00'],
        ['type' => 'incident', 'title' => 'Nieszczelny układ hydrauliczny', 'ts' => '2026-08-11 09:00:00'],
    ]);

    $response = $this->dashboardController->charts(dashboardRequest());
    expect($response->statusCode())->toBe(200);
    expect($response->data()['orders_trend']['categories'])->toBe(['10.08', '11.08']);
    expect($response->data()['orders_trend']['series'])->toBe([2, 4]);
    expect($response->data()['orders_trend']['trend_pct'])->toBe(33.3);
    expect($response->data()['fleet_structure']['series'])->toBe([3, 3, 5, 3]);
    expect($response->data()['terminal_turnover']['categories'])->toBe(['Terminal Gdańsk', 'Terminal Gdynia']);
    expect($response->data()['terminal_turnover']['series'])->toBe([5000.0, 7500.0]);
    expect($response->data()['activity'][0]['type'])->toBe('order');
    expect($response->data()['activity'][1]['title'])->toBe('Nieszczelny układ hydrauliczny');
});
