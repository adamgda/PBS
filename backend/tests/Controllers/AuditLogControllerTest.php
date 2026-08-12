<?php

declare(strict_types=1);

use App\Controllers\AuditLogController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Services\AuditLogService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogService = new AuditLogService($this->auditLogRepository);
    $this->auditLogController = new AuditLogController($this->auditLogService);
});

afterEach(function (): void {
    m::close();
});

// --- LIST (index) ---

it('index returns paginated list', function (): void {
    $filters = ['action' => '', 'user_email' => '', 'sort' => 'id', 'direction' => 'desc'];
    $this->auditLogRepository->shouldReceive('paginate')
        ->with($filters, 25, 0, 'id', 'desc')
        ->andReturn([
            [
                'id' => 1,
                'user_id' => 2,
                'user_email' => 'admin@pbs.local',
                'action' => 'user_created',
                'resource_type' => 'user',
                'resource_id' => 5,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'TestAgent',
                'details' => '{"email":"x@pbs.local"}',
                'created_at' => '2026-01-01 10:00:00',
            ],
        ]);
    $this->auditLogRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->auditLogController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
    expect($response->data()['data'])->toHaveCount(1);
    expect($response->data()['data'][0]['user_email'])->toBe('admin@pbs.local');
    expect($response->data()['data'][0]['details'])->toBe(['email' => 'x@pbs.local']);
});

it('index applies filters and pagination from query string', function (): void {
    $filters = ['action' => 'login', 'user_email' => 'adm', 'sort' => 'id', 'direction' => 'desc'];
    $this->auditLogRepository->shouldReceive('paginate')->with($filters, 10, 0, 'id', 'desc')->andReturn([]);
    $this->auditLogRepository->shouldReceive('countSearch')->with($filters)->andReturn(0);

    $request = new Request(
        query: ['action' => 'login', 'user_email' => 'adm', 'per_page' => '10'],
        body: [],
        headers: [],
    );
    $response = $this->auditLogController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['per_page'])->toBe(10);
});

// --- CLEAR ---

it('clear returns success and cleared count', function (): void {
    $this->auditLogRepository->shouldReceive('clear')->once()->andReturn(42);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->auditLogController->clear($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
    expect($response->data()['cleared'])->toBe(42);
});
