<?php

declare(strict_types=1);

use App\Repository\DailyVehicleReportRepository;
use App\Repository\EquipmentRepository;
use App\Repository\IncidentRepository;
use App\Services\QrCodeService;
use App\Services\QrService;
use PDO;
use Mockery as m;

function equipmentRow(int $id = 5, string $kategoria = 'pojazd', ?string $qrToken = null): array
{
    return ['id' => $id, 'kategoria' => $kategoria, 'nazwa' => 'Ford Transit', 'numer_seryjny' => 'FT-1', 'is_active' => true, 'qr_token' => $qrToken];
}

function publicRow(int $id = 5): array
{
    return ['id' => $id, 'kategoria' => 'pojazd', 'nazwa' => 'Ford Transit', 'numer_seryjny' => 'FT-1', 'is_active' => true];
}

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
});

afterEach(function (): void {
    m::close();
});

it('generateToken returns 404 for missing equipment', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(9)->andReturnNull();
    expect($this->qrService->generateToken(9, new \App\Http\Request([], [], [])))->toBe(['error' => 'Equipment not found', 'code' => 404]);
});

it('generateToken refuses equipment not in pojazd/inne group', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'inny']);
    expect($this->qrService->generateToken(1, new \App\Http\Request([], [], [])))->toBe(['error' => 'QR code is available only for vehicles (or "inne")', 'code' => 422]);
});

it('generateToken creates 64-char token and public url', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(5)->andReturn(equipmentRow());
    $this->equipmentRepository->shouldReceive('setQrToken')->with(5, m::on(fn (string $t): bool => strlen($t) === 64 && ctype_xdigit($t)))
        ->andReturn(equipmentRow(5, 'pojazd', 'aaaaaaaa'));

    $result = $this->qrService->generateToken(5, new \App\Http\Request([], [], []));

    expect($result['qr_token'])->toBeString()->toHaveLength(64);
    expect(ctype_xdigit($result['qr_token']))->toBeTrue();
    expect($result['public_url'])->toBe('http://localhost:4200/qr/' . $result['qr_token']);
});

it('qrInfo returns 409 when no token generated yet', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(5)->andReturn(equipmentRow(5, 'pojazd', null));
    expect($this->qrService->qrInfo(5))->toBe(['error' => 'QR token not generated yet', 'code' => 409]);
});

it('qrInfo returns svg and sticker data', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(5)->andReturn(equipmentRow(5, 'pojazd', 'tok123'));
    $result = $this->qrService->qrInfo(5);

    expect($result['public_url'])->toBe('http://localhost:4200/qr/tok123');
    expect($result['qr_svg'])->toContain('<svg');
    expect($result['machine']['nazwa'])->toBe('Ford Transit');
});

it('machine returns 404 for unknown token', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('bad')->andReturnNull();
    expect($this->qrService->machine('bad'))->toBe(['error' => 'Machine not found', 'code' => 404]);
});

it('machine does not expose personal data', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')->andReturn(publicRow());
    $result = $this->qrService->machine('tok');

    expect($result)->not->toHaveKey('current_employee_id');
    expect($result)->not->toHaveKey('employee_imie');
    expect($result['nazwa'])->toBe('Ford Transit');
});

it('createIncident creates incident with zrodlo=qr and null author', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')->andReturn(publicRow());
    $this->incidentRepository->shouldReceive('createIncident')->with(m::on(function (array $d): bool {
        return $d['typ'] === 'sprzet' && $d['equipment_id'] === 5 && $d['zrodlo'] === 'qr' && $d['zgloszona_przez'] === null && $d['status'] === 'zgloszona';
    }))->andReturn(['id' => 7]);

    $result = $this->qrService->createIncident('tok', ['opis' => 'Silnik stuka']);

    expect($result['numer_zgloszenia'])->toBe('AWR-000007');
    expect($result['status'])->toBe('zgloszona');
});

it('createIncident prepends optional contact to opis', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')->andReturn(publicRow());
    $this->incidentRepository->shouldReceive('createIncident')->with(m::on(function (array $d): bool {
        return str_contains($d['opis'], 'Kontakt: 500-100-200') && str_contains($d['opis'], 'Silnik');
    }))->andReturn(['id' => 8]);

    $result = $this->qrService->createIncident('tok', ['opis' => 'Silnik', 'kontakt' => '500-100-200']);

    expect($result['id'])->toBe(8);
});

it('createIncident rejects empty opis', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')->andReturn(publicRow());
    expect($this->qrService->createIncident('tok', ['opis' => '   ']))->toBe(['error' => 'Opis is required', 'code' => 422]);
});

it('createDailyReport creates report with zrodlo=qr and null author', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')->andReturn(publicRow());
    $this->vehicleReportRepository->shouldReceive('existsForEquipmentAndDate')->with(5, date('Y-m-d'))->andReturn(false);
    $this->vehicleReportRepository->shouldReceive('create')->with(m::on(function (array $d): bool {
        return $d['equipment_id'] === 5 && $d['zrodlo'] === 'qr' && $d['utworzony_przez'] === null && $d['aktualny_przebieg'] === 500;
    }))->andReturn(['id' => 3, 'data_raportu' => '2026-08-20']);

    $result = $this->qrService->createDailyReport('tok', ['aktualny_przebieg' => 500, 'przebieg_oc' => 'Przeglad OK']);

    expect($result['id'])->toBe(3);
    expect($result['status'])->toBe('ok');
});

it('createDailyReport returns 409 on duplicate date', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')->andReturn(publicRow());
    $this->vehicleReportRepository->shouldReceive('existsForEquipmentAndDate')->with(5, date('Y-m-d'))->andReturn(true);

    $result = $this->qrService->createDailyReport('tok', ['aktualny_przebieg' => 100, 'przebieg_oc' => 'x']);

    expect($result)->toBe(['error' => 'Daily report for this equipment and date already exists', 'code' => 409]);
});

it('createDailyReport rejects negative mileage', function (): void {
    $this->equipmentRepository->shouldReceive('findPublicByQrToken')->with('tok')->andReturn(publicRow());
    expect($this->qrService->createDailyReport('tok', ['aktualny_przebieg' => -5, 'przebieg_oc' => 'x']))->toBe(['error' => 'Invalid aktualny_przebieg', 'code' => 422]);
});

