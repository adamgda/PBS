<?php

declare(strict_types=1);

use App\Controllers\AlertSettingsController;
use App\Http\Request;
use App\Repository\AlertConfigRepository;
use App\Repository\AuditLogRepository;
use App\Services\AlertSettingsService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->alertConfigRepository = m::mock(AlertConfigRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();

    $this->alertSettingsService = new AlertSettingsService($this->alertConfigRepository, $this->auditLogRepository);
    $this->alertController = new AlertSettingsController($this->alertSettingsService);
});

afterEach(function (): void {
    m::close();
});

function alertConfigRow(int $id = 1, string $email = 'ops@pbs.pl', string $type = 'certyfikat_wygasa', bool $active = true, ?string $czas = null): array
{
    return [
        'id' => $id,
        'email_odbiorcy' => $email,
        'typ_alertu' => $type,
        'czy_aktywny' => $active,
        'czas_wysylki' => $czas,
        'created_at' => null,
        'updated_at' => null,
    ];
}

function alertRequest(array $body = []): Request
{
    return new Request(query: [], body: $body, headers: []);
}

// --- INDEX ---

it('index returns list of alert configs', function (): void {
    $this->alertConfigRepository->shouldReceive('findAll')->once()->andReturn([
        alertConfigRow(1),
        alertConfigRow(2, 'bok@pbs.pl', 'brak_raportu_oc', true, '10:00:00'),
    ]);

    $response = $this->alertController->index(alertRequest());

    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toHaveCount(2);
    expect($response->data()['data'][0]['email_odbiorcy'])->toBe('ops@pbs.pl');
    expect($response->data()['data'][0])->not->toHaveKey('created_at_extra');
});

// --- STORE (create) ---

it('store returns 422 when email missing', function (): void {
    $response = $this->alertController->store(alertRequest(['typ_alertu' => 'awaria_zgloszona']));
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 for invalid email', function (): void {
    $response = $this->alertController->store(alertRequest(['email_odbiorcy' => 'not-an-email', 'typ_alertu' => 'awaria_zgloszona']));
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 for invalid alert type', function (): void {
    $response = $this->alertController->store(alertRequest(['email_odbiorcy' => 'ops@pbs.pl', 'typ_alertu' => 'spam']));
    expect($response->statusCode())->toBe(422);
});

it('store requires send time for missing OC report alert', function (): void {
    $response = $this->alertController->store(alertRequest(['email_odbiorcy' => 'ops@pbs.pl', 'typ_alertu' => 'brak_raportu_oc']));
    expect($response->statusCode())->toBe(422);
});

it('store creates alert config and logs audit', function (): void {
    $this->alertConfigRepository->shouldReceive('createConfig')->once()->with(m::on(function (array $data): bool {
        return $data['email_odbiorcy'] === 'ops@pbs.pl'
            && $data['typ_alertu'] === 'certyfikat_wygasa'
            && $data['czas_wysylki'] === null
            && $data['czy_aktywny'] === true;
    }))->andReturn(alertConfigRow(7));
    $this->auditLogRepository->shouldReceive('logFromRequest')
        ->with(null, 'alert_config_created', m::type(Request::class), 'alert_config', 7)
        ->once();

    $response = $this->alertController->store(alertRequest([
        'email_odbiorcy' => 'ops@pbs.pl',
        'typ_alertu' => 'certyfikat_wygasa',
        'czy_aktywny' => true,
    ]));

    expect($response->statusCode())->toBe(201);
    expect($response->data()['id'])->toBe(7);
});

// --- UPDATE ---

it('update returns 404 when config missing', function (): void {
    $this->alertConfigRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $response = $this->alertController->update(alertRequest([
        'email_odbiorcy' => 'ops@pbs.pl',
        'typ_alertu' => 'awaria_zgloszona',
    ]), ['id' => '9']);

    expect($response->statusCode())->toBe(404);
});

it('update edits config and keeps existing send time when not provided', function (): void {
    $this->alertConfigRepository->shouldReceive('findById')->with(2)->andReturn(alertConfigRow(2, 'old@pbs.pl', 'brak_raportu_oc', true, '10:00:00'));
    $this->alertConfigRepository->shouldReceive('updateConfig')->with(2, m::on(function (array $data): bool {
        return $data['email_odbiorcy'] === 'new@pbs.pl' && $data['czas_wysylki'] === '10:00:00';
    }))->andReturn(alertConfigRow(2, 'new@pbs.pl', 'brak_raportu_oc', true, '10:00:00'));

    $response = $this->alertController->update(alertRequest([
        'email_odbiorcy' => 'new@pbs.pl',
        'typ_alertu' => 'brak_raportu_oc',
    ]), ['id' => '2']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['email_odbiorcy'])->toBe('new@pbs.pl');
});

// --- DELETE ---

it('delete returns 404 when config missing', function (): void {
    $this->alertConfigRepository->shouldReceive('findById')->with(3)->andReturnNull();

    $response = $this->alertController->destroy(alertRequest(), ['id' => '3']);
    expect($response->statusCode())->toBe(404);
});

it('delete removes config and returns success', function (): void {
    $this->alertConfigRepository->shouldReceive('findById')->with(4)->andReturn(alertConfigRow(4));
    $this->alertConfigRepository->shouldReceive('delete')->with(4)->once();

    $response = $this->alertController->destroy(alertRequest(), ['id' => '4']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});
