<?php

declare(strict_types=1);

use App\Controllers\IncidentController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EquipmentRepository;
use App\Repository\IncidentRepository;
use App\Services\IncidentService;
use PDO;
use Mockery as m;

function incidentRow(int $id = 1, string $status = 'zgloszona', ?int $equipmentId = null): array {
    return ['id' => $id, 'typ' => 'sprzet', 'equipment_id' => $equipmentId, 'equipment_nazwa' => $equipmentId !== null ? 'Ford' : null, 'opis' => 'Opis', 'status' => $status, 'data_zgloszenia' => '2026-01-01 10:00:00', 'data_zakonczenia' => null, 'zgloszona_przez' => 1, 'zgloszona_przez_email' => 'admin@pbs.local', 'created_at' => null, 'updated_at' => null];
}

function authedRequest(array $body = [], array $query = []): Request {
    $request = new Request(query: $query, body: $body, headers: []);
    $request->setAttribute('user_id', 1);

    return $request;
}

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->incidentRepository = m::mock(IncidentRepository::class, [$pdo]);
    $this->equipmentRepository = m::mock(EquipmentRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();

    $this->incidentService = new IncidentService(
        $this->incidentRepository,
        $this->equipmentRepository,
        $this->auditLogRepository,
    );
    $this->incidentController = new IncidentController($this->incidentService);
});

afterEach(function (): void {
    m::close();
});

it('index returns paginated list', function (): void {
    $filters = ['typ' => '', 'status' => '', 'equipment_id' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->incidentRepository->shouldReceive('search')->with($filters, 25, 0, 'id', 'asc')->andReturn([incidentRow()]);
    $this->incidentRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $response = $this->incidentController->index(new Request(query: [], body: [], headers: []));
    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
});

it('store returns 422 when typ missing', function (): void {
    expect($this->incidentController->store(authedRequest(['opis' => 'Opis']))->statusCode())->toBe(422);
});

it('store returns 422 when opis missing', function (): void {
    expect($this->incidentController->store(authedRequest(['typ' => 'sprzet']))->statusCode())->toBe(422);
});

it('store returns 422 when equipment not found for sprzet', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(5)->andReturnNull();
    expect($this->incidentController->store(authedRequest(['typ' => 'sprzet', 'equipment_id' => 5, 'opis' => 'Opis']))->statusCode())->toBe(422);
});

it('store creates incident and returns 201', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(5)->andReturn(['id' => 5, 'nazwa' => 'Ford']);
    $this->incidentRepository->shouldReceive('createIncident')
        ->with(m::on(fn (array $d): bool => $d['typ'] === 'sprzet' && $d['equipment_id'] === 5 && $d['status'] === 'zgloszona' && $d['zgloszona_przez'] === 1))
        ->andReturn(incidentRow(1, 'zgloszona', 5));

    $response = $this->incidentController->store(authedRequest(['typ' => 'sprzet', 'equipment_id' => 5, 'opis' => 'Opis']));
    expect($response->statusCode())->toBe(201);
    expect($response->data()['typ'])->toBe('sprzet');
});

it('store creates incident of type other without equipment', function (): void {
    $this->incidentRepository->shouldReceive('createIncident')
        ->with(m::on(fn (array $d): bool => $d['typ'] === 'inne' && $d['equipment_id'] === null))
        ->andReturn(['id' => 2, 'typ' => 'inne', 'equipment_id' => null, 'equipment_nazwa' => null, 'opis' => 'Opis', 'status' => 'zgloszona', 'data_zgloszenia' => '2026-01-01 10:00:00', 'data_zakonczenia' => null, 'zgloszona_przez' => 1, 'zgloszona_przez_email' => null, 'created_at' => null, 'updated_at' => null]);

    expect($this->incidentController->store(authedRequest(['typ' => 'inne', 'opis' => 'Opis']))->statusCode())->toBe(201);
});

it('show returns 404 for missing incident', function (): void {
    $this->incidentRepository->shouldReceive('findById')->with(99)->andReturnNull();
    $this->incidentRepository->shouldReceive('findComments')->byDefault();
    $this->incidentRepository->shouldReceive('findStatusHistory')->byDefault();

    expect($this->incidentController->show(new Request(query: [], body: [], headers: []), ['id' => '99'])->statusCode())->toBe(404);
});

it('show returns incident with comments and history', function (): void {
    $this->incidentRepository->shouldReceive('findById')->with(1)->andReturn(incidentRow());
    $this->incidentRepository->shouldReceive('findComments')->with(1)->andReturn([['id' => 1, 'incident_id' => 1, 'tresc' => 'Komentarz', 'user_id' => 1, 'user_email' => 'admin@pbs.local', 'created_at' => null]]);
    $this->incidentRepository->shouldReceive('findStatusHistory')->with(1)->andReturn([]);

    $response = $this->incidentController->show(new Request(query: [], body: [], headers: []), ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['comments'])->toHaveCount(1);
});

it('updateStatus returns 404 for missing incident', function (): void {
    $this->incidentRepository->shouldReceive('findById')->with(5)->andReturnNull();
    expect($this->incidentController->updateStatus(authedRequest(['status' => 'w_trakcie_naprawy']), ['id' => '5'])->statusCode())->toBe(404);
});

it('updateStatus returns 422 for invalid status', function (): void {
    $this->incidentRepository->shouldReceive('findById')->with(1)->andReturn(incidentRow());
    expect($this->incidentController->updateStatus(authedRequest(['status' => 'bledny']), ['id' => '1'])->statusCode())->toBe(422);
});

it('updateStatus changes status and records history', function (): void {
    $this->incidentRepository->shouldReceive('findById')->with(1)->andReturn(incidentRow(1, 'zgloszona'));
    $this->incidentRepository->shouldReceive('updateIncident')->with(1, m::on(fn (array $d): bool => $d['status'] === 'naprawiona' && $d['data_zakonczenia'] !== null))->andReturn(incidentRow(1, 'naprawiona'));
    $this->incidentRepository->shouldReceive('addStatusHistory')->with(1, 'zgloszona', 'naprawiona', 1)->once();

    $response = $this->incidentController->updateStatus(authedRequest(['status' => 'naprawiona']), ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['status'])->toBe('naprawiona');
});

it('addComment returns 422 when content empty', function (): void {
    $this->incidentRepository->shouldReceive('findById')->with(1)->andReturn(incidentRow());
    expect($this->incidentController->addComment(authedRequest(['tresc' => '   ']), ['id' => '1'])->statusCode())->toBe(422);
});

it('addComment adds and returns 201', function (): void {
    $this->incidentRepository->shouldReceive('findById')->with(1)->andReturn(incidentRow());
    $this->incidentRepository->shouldReceive('addComment')->with(1, 1, 'Nowy komentarz')->andReturn(['id' => 9, 'incident_id' => 1, 'tresc' => 'Nowy komentarz', 'user_id' => 1, 'user_email' => null, 'created_at' => null]);

    $response = $this->incidentController->addComment(authedRequest(['tresc' => 'Nowy komentarz']), ['id' => '1']);
    expect($response->statusCode())->toBe(201);
    expect($response->data()['tresc'])->toBe('Nowy komentarz');
});