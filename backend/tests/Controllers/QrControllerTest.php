<?php

declare(strict_types=1);

use App\Controllers\QrController;
use App\Http\Request;
use App\Repository\DailyVehicleReportRepository;
use App\Repository\EquipmentRepository;
use App\Repository\IncidentRepository;
use App\Services\QrCodeService;
use App\Services\QrService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->equipmentRepository = m::mock(EquipmentRepository::class, [$pdo]);
    $this->incidentRepository = m::mock(IncidentRepository::class, [$pdo]);
    $this->vehicleReportRepository = m::mock(DailyVehicleReportRepository::class, [$pdo]);

    $this->qrService = new QrService(
        $this->equipmentRepository,
        $this->incidentRepository,
        $this->vehicleReportRepository,
        new QrCodeService(),
        'http://localhost:4200',
    );
    $this->controller = new QrController($this->qrService);
});

afterEach(function (): void {
    m::close();
});

it('machine returns machine info for valid token', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok123')
        ->andReturn(['id' => 5, 'kategoria' => 'pojazd', 'nazwa' => 'Ford', 'numer_seryjny' => 'FT-1', 'is_active' => true]);

    $response = $this->controller->machine(new Request(query: [], body: [], headers: []), ['token' => 'tok123']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['nazwa'])->toBe('Ford');
});

it('machine returns 404 for unknown token', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('bad')->andReturnNull();

    $response = $this->controller->machine(new Request(query: [], body: [], headers: []), ['token' => 'bad']);
    expect($response->statusCode())->toBe(404);
});

it('createIncident creates and returns 201', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')
        ->andReturn(['id' => 5, 'kategoria' => 'pojazd', 'nazwa' => 'Ford', 'numer_seryjny' => 'FT-1', 'is_active' => true]);
    $this->incidentRepository->shouldReceive('createIncident')->with(m::on(fn (array $d): bool => $d['zrodlo'] === 'qr'))
        ->andReturn(['id' => 7]);

    $response = $this->controller->createIncident(new Request(query: [], body: ['opis' => 'Nie dziala'], headers: []), ['token' => 'tok']);
    expect($response->statusCode())->toBe(201);
    expect($response->data()['numer_zgloszenia'])->toBe('AWR-000007');
});

it('createIncident returns 422 on validation error', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')
        ->andReturn(['id' => 5, 'kategoria' => 'pojazd', 'nazwa' => 'Ford', 'numer_seryjny' => null, 'is_active' => true]);

    $response = $this->controller->createIncident(new Request(query: [], body: ['opis' => ''], headers: []), ['token' => 'tok']);
    expect($response->statusCode())->toBe(422);
});

it('createDailyReport creates and returns 201', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')
        ->andReturn(['id' => 5, 'kategoria' => 'pojazd', 'nazwa' => 'Ford', 'numer_seryjny' => null, 'is_active' => true]);
    $this->vehicleReportRepository->shouldReceive('existsForEquipmentAndDate')->with(5, date('Y-m-d'))->andReturn(false);
    $this->vehicleReportRepository->shouldReceive('create')->with(m::on(fn (array $d): bool => $d['zrodlo'] === 'qr'))
        ->andReturn(['id' => 3, 'data_raportu' => '2026-08-20']);

    $response = $this->controller->createDailyReport(new Request(query: [], body: ['aktualny_przebieg' => 500, 'przebieg_oc' => 'OK'], headers: []), ['token' => 'tok']);
    expect($response->statusCode())->toBe(201);
    expect($response->data()['data_raportu'])->toBe(date('Y-m-d'));
});

it('createDailyReport returns 404 for unknown token', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('bad')->andReturnNull();

    $response = $this->controller->createDailyReport(new Request(query: [], body: [], headers: []), ['token' => 'bad']);
    expect($response->statusCode())->toBe(404);
});
