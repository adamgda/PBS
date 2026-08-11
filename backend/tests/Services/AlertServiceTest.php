<?php

declare(strict_types=1);

use App\Repository\AlertConfigRepository;
use App\Repository\AlertNotificationRepository;
use App\Repository\AlertSourceRepository;
use App\Services\AlertService;
use App\Services\MailService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->alertConfigRepository = m::mock(AlertConfigRepository::class, [$pdo]);
    $this->alertNotificationRepository = m::mock(AlertNotificationRepository::class, [$pdo]);
    $this->alertSourceRepository = m::mock(AlertSourceRepository::class, [$pdo]);
    $this->mailService = m::mock(MailService::class);

    $this->alertService = new AlertService(
        $this->alertConfigRepository,
        $this->alertNotificationRepository,
        $this->alertSourceRepository,
        $this->mailService,
    );
});

afterEach(function (): void {
    m::close();
});

function alertConfig(int $id = 1, string $type = 'certyfikat_wygasa', string $email = 'ops@pbs.pl', ?string $czas = null): array
{
    return [
        'id' => $id,
        'typ_alertu' => $type,
        'email_odbiorcy' => $email,
        'czy_aktywny' => true,
        'czas_wysylki' => $czas,
    ];
}

// --- Scenariusz: certyfikat wygasa (≤ 30 dni) ---

it('sends alert for expiring certificates and marks as sent', function (): void {
    $this->alertConfigRepository->shouldReceive('findAllActive')->once()->andReturn([
        alertConfig(1, 'certyfikat_wygasa'),
    ]);
    $this->alertSourceRepository->shouldReceive('expiringCertificates')->with(30)->once()->andReturn([
        ['id' => 10, 'nazwa' => 'UDT', 'numer_dokumentu' => 'UDT-1', 'data_waznosci' => '2026-09-01', 'imie' => 'Jan', 'nazwisko' => 'Kowalski'],
    ]);
    $this->alertNotificationRepository->shouldReceive('alreadySent')->with(1, 'cert', 10, '2026-08-10')->once()->andReturn(false);
    $this->mailService->shouldReceive('sendAlert')
        ->with(
            'ops@pbs.pl',
            m::on(static fn (string $s): bool => str_contains($s, 'wygasa')),
            m::on(static fn (string $s): bool => str_contains($s, 'UDT')),
        )
        ->once()
        ->andReturn(true);
    $this->alertNotificationRepository->shouldReceive('markSent')->with(1, 'certyfikat_wygasa', 'cert', 10, '2026-08-10')->once();

    $result = $this->alertService->run(new DateTimeImmutable('2026-08-10 08:00:00'));

    expect($result['sent'])->toBe(1);
    expect($result['checked'])->toBe(1);
    expect($result['by_type']['certyfikat_wygasa'])->toBe(1);
});

it('does not resend an already-sent alert (dedupe)', function (): void {
    $this->alertConfigRepository->shouldReceive('findAllActive')->once()->andReturn([
        alertConfig(1, 'certyfikat_wygasa'),
    ]);
    $this->alertSourceRepository->shouldReceive('expiringCertificates')->with(30)->once()->andReturn([
        ['id' => 10, 'nazwa' => 'UDT', 'numer_dokumentu' => null, 'data_waznosci' => '2026-09-01', 'imie' => 'Jan', 'nazwisko' => 'Kowalski'],
    ]);
    $this->alertNotificationRepository->shouldReceive('alreadySent')->with(1, 'cert', 10, '2026-08-10')->once()->andReturn(true);
    $this->mailService->shouldNotReceive('sendAlert');

    $result = $this->alertService->run(new DateTimeImmutable('2026-08-10 08:00:00'));

    expect($result['sent'])->toBe(0);
    expect($result['checked'])->toBe(0);
});

// --- Scenariusz: brak raportu OC do godziny (np. 10:00) ---

it('does not send missing OC report before configured send time', function (): void {
    $this->alertConfigRepository->shouldReceive('findAllActive')->once()->andReturn([
        alertConfig(2, 'brak_raportu_oc', 'bok@pbs.pl', '10:00:00'),
    ]);
    $this->alertSourceRepository->shouldNotReceive('vehiclesMissingOcReport');
    $this->mailService->shouldNotReceive('sendAlert');

    // Jest 09:00, konfiguracja ustawiona na 10:00 → alert nie powinien wyjść.
    $result = $this->alertService->run(new DateTimeImmutable('2026-08-10 09:00:00'));

    expect($result['sent'])->toBe(0);
    expect($result['by_type']['brak_raportu_oc'])->toBe(0);
});

it('sends missing OC report after configured send time', function (): void {
    $this->alertConfigRepository->shouldReceive('findAllActive')->once()->andReturn([
        alertConfig(2, 'brak_raportu_oc', 'bok@pbs.pl', '10:00:00'),
    ]);
    $this->alertSourceRepository->shouldReceive('vehiclesMissingOcReport')->with('2026-08-10')->once()->andReturn([
        ['id' => 20, 'nazwa' => 'Ford Transit', 'numer_seryjny' => 'FT-2024-001'],
    ]);
    $this->alertNotificationRepository->shouldReceive('alreadySent')->with(2, 'oc', 20, '2026-08-10')->once()->andReturn(false);
    $this->mailService->shouldReceive('sendAlert')
        ->with(
            'bok@pbs.pl',
            m::on(static fn (string $s): bool => str_contains($s, 'Brak raportu')),
            m::on(static fn (string $s): bool => str_contains($s, 'Ford Transit')),
        )
        ->once()
        ->andReturn(true);
    $this->alertNotificationRepository->shouldReceive('markSent')->with(2, 'brak_raportu_oc', 'oc', 20, '2026-08-10')->once();

    // Jest 10:30, minęła godzina 10:00 → alert wysyłany.
    $result = $this->alertService->run(new DateTimeImmutable('2026-08-10 10:30:00'));

    expect($result['sent'])->toBe(1);
    expect($result['by_type']['brak_raportu_oc'])->toBe(1);
});

// --- Scenariusz: nowa awaria ---

it('sends alert for a new incident', function (): void {
    $this->alertConfigRepository->shouldReceive('findAllActive')->once()->andReturn([
        alertConfig(3, 'awaria_zgloszona', 'szef@pbs.pl'),
    ]);
    $this->alertSourceRepository->shouldReceive('newIncidents')->with('2026-08-10 00:00:00')->once()->andReturn([
        ['id' => 30, 'opis' => 'Usterka silnika', 'status' => 'zgloszona', 'data_zgloszenia' => '2026-08-10 07:15:00', 'equipment_nazwa' => 'MS-1'],
    ]);
    $this->alertNotificationRepository->shouldReceive('alreadySent')->with(3, 'incident', 30, '2026-08-10')->once()->andReturn(false);
    $this->mailService->shouldReceive('sendAlert')->once()->andReturn(true);
    $this->alertNotificationRepository->shouldReceive('markSent')->with(3, 'awaria_zgloszona', 'incident', 30, '2026-08-10')->once();

    $result = $this->alertService->run(new DateTimeImmutable('2026-08-10 08:00:00'));

    expect($result['sent'])->toBe(1);
    expect($result['by_type']['awaria_zgloszona'])->toBe(1);
});

// --- Inactive / niepoprawne konfiguracje ---

it('ignores inactive configs', function (): void {
    $this->alertConfigRepository->shouldReceive('findAllActive')->once()->andReturn([]);
    $this->mailService->shouldNotReceive('sendAlert');

    $result = $this->alertService->run(new DateTimeImmutable('2026-08-10 08:00:00'));

    expect($result['sent'])->toBe(0);
    expect($result['checked'])->toBe(0);
});

it('counts failures when mail send returns false', function (): void {
    $this->alertConfigRepository->shouldReceive('findAllActive')->once()->andReturn([
        alertConfig(1, 'certyfikat_wygasa'),
    ]);
    $this->alertSourceRepository->shouldReceive('expiringCertificates')->with(30)->once()->andReturn([
        ['id' => 10, 'nazwa' => 'UDT', 'numer_dokumentu' => null, 'data_waznosci' => '2026-09-01', 'imie' => 'Jan', 'nazwisko' => 'Kowalski'],
    ]);
    $this->alertNotificationRepository->shouldReceive('alreadySent')->with(1, 'cert', 10, '2026-08-10')->once()->andReturn(false);
    $this->mailService->shouldReceive('sendAlert')->once()->andReturn(false);

    $result = $this->alertService->run(new DateTimeImmutable('2026-08-10 08:00:00'));

    expect($result['sent'])->toBe(0);
    expect($result['failed'])->toBe(1);
});

