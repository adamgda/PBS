<?php

declare(strict_types=1);

use App\Controllers\ExportController;
use App\Http\Request;
use App\Repository\ExportRepository;
use App\Services\ExportService;
use Mockery as m;

beforeEach(function (): void {
    $this->exportRepository = m::mock(ExportRepository::class, [m::mock(PDO::class)]);
    $this->exportService = new ExportService($this->exportRepository);
    $this->exportController = new ExportController($this->exportService);
});

afterEach(function (): void {
    m::close();
});

it('export returns 422 for unsupported type', function (): void {
    $response = $this->exportController->export(new Request(query: [], body: [], headers: []), ['type' => 'nieznany']);
    expect($response->statusCode())->toBe(422);
});

it('export returns 422 for invalid from date', function (): void {
    $this->exportRepository->shouldNotReceive('orders');

    $response = $this->exportController->export(new Request(query: ['from' => 'nie-data'], body: [], headers: []), ['type' => 'orders']);
    expect($response->statusCode())->toBe(422);
});

it('export returns raw CSV with attachment headers for valid type', function (): void {
    $this->exportRepository
        ->shouldReceive('orders')
        ->once()
        ->with('2026-01-01', '2026-01-31')
        ->andReturn([
            [
                'order_id' => 1,
                'numer_zlecenia' => 'ZL/1',
                'klient_nazwa' => 'Firma',
                'terminal_nazwa' => 'BCT',
                'data_rozpoczecia' => '2026-01-01 08:00:00',
                'data_zakonczenia' => '2026-01-01 16:00:00',
                'zakres_prac' => 'Przeładunek',
                'wartosc_pln' => '1250.50',
                'status' => 'zakonczone',
                'pracownik' => 'Jan Kowalski',
                'rola' => 'operator',
                'godziny' => '8.00',
                'stawka_godzinowa' => '45.00',
            ],
        ]);

    $response = $this->exportController->export(
        new Request(query: ['from' => '2026-01-01', 'to' => '2026-01-31'], body: [], headers: []),
        ['type' => 'orders'],
    );

    expect($response->statusCode())->toBe(200);
    expect($response->getHeader('Content-Type'))->toBe('text/csv; charset=utf-8');
    expect($response->getHeader('Content-Disposition'))->toContain('attachment; filename="zlecenia-rozliczenia_');
    expect($response->getHeader('Cache-Control'))->toBe('no-store');
    expect($response->rawBody())->toContain('Id zlecenia,Numer zlecenia');
    expect($response->rawBody())->toContain('ZL/1');
});
