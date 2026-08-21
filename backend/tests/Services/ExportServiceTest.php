<?php

declare(strict_types=1);

use App\Repository\ExportRepository;
use App\Services\ExportService;
use Mockery as m;

beforeEach(function (): void {
    $this->repository = m::mock(ExportRepository::class, [m::mock(PDO::class)]);
    $this->service = new ExportService($this->repository);
});

afterEach(function (): void {
    m::close();
});

it('export returns error for unsupported type', function (): void {
    $result = $this->service->export('bad-type', '', '');
    expect($result['error'])->toBe('Unsupported export type');
    expect($result['code'])->toBe(422);
});

it('export builds CSV with BOM, headers and escaped values', function (): void {
    $this->repository
        ->shouldReceive('orders')
        ->once()
        ->with('2026-01-01', '2026-01-31')
        ->andReturn([
            [
                'order_id' => 1,
                'numer_zlecenia' => 'ZL/1',
                'klient_nazwa' => 'Firma, sp. z o.o.',
                'terminal_nazwa' => 'BCT',
                'data_rozpoczecia' => '2026-01-01 08:00:00',
                'data_zakonczenia' => '2026-01-01 16:00:00',
                'zakres_prac' => "Przeładunek\nkontenerów",
                'wartosc_pln' => '1250.50',
                'status' => 'zakonczone',
                'pracownik' => 'Jan Kowalski',
                'rola' => 'operator',
                'godziny' => '8.00',
                'stawka_godzinowa' => '45.00',
            ],
        ]);

    $result = $this->service->export('orders', '2026-01-01', '2026-01-31');

    expect($result['filename'])->toBe('zlecenia-rozliczenia');
    $csv = $result['content'];
    expect($csv)->toStartWith("\xEF\xBB\xBF");
    expect($csv)->toContain('Id zlecenia,Numer zlecenia');
    expect($csv)->toContain('"Firma, sp. z o.o."');
    expect($csv)->toContain("Przeładunek\nkontenerów");
    expect($csv)->toContain('operator');
});

it('export uses no filters when from/to empty', function (): void {
    $this->repository
        ->shouldReceive('incidents')
        ->once()
        ->with('', '')
        ->andReturn([]);

    $result = $this->service->export('incidents', '', '');
    expect($result['content'])->toContain('Id awarii');
});

it('export builds equipment CSV with vehicle details columns', function (): void {
    $this->repository
        ->shouldReceive('equipment')
        ->once()
        ->withNoArgs()
        ->andReturn([
            [
                'equipment_id' => 1,
                'kategoria' => 'pojazd',
                'nazwa' => 'Scania R450',
                'numer_seryjny' => 'SC-01',
                'is_active' => true,
                'terminal_nazwa' => 'BCT',
                'przypisany_pracownik' => 'Jan Kowalski',
                'ostatni_przebieg' => 14820,
                'ostatni_serwis_olejowy' => '2026-05-01',
                'ostatnia_awaria' => null,
                'data_ostatniej_oc' => '2026-06-01',
                'wynik_ostatniej_oc' => 'Pozytywny',
                'data_nastepnego_przegladu' => '2026-09-01',
            ],
        ]);

    $result = $this->service->export('equipment', '', '');

    expect($result['filename'])->toBe('sprzet');
    expect($result['content'])->toContain('Id sprzętu,Nazwa,Kategoria');
    expect($result['content'])->toContain('Scania R450');
    expect($result['content'])->toContain('14820');
    expect($result['content'])->toContain('Pozytywny');
});
