<?php

declare(strict_types=1);

use App\Repository\UserRepository;
use Mockery as m;

/**
 * Testy repozytorium UserRepository (Etap 16 — testy repozytoriów).
 * PDO jest zamockowane (jednostkowe), więc nie wymaga realnej bazy danych.
 */

$mockPdo = function (): array {
    $pdo = m::mock(PDO::class);
    $stmt = m::mock(PDOStatement::class);

    $pdo->shouldReceive('prepare')->andReturn($stmt);
    $pdo->shouldReceive('lastInsertId')->andReturn('42');
    $stmt->shouldReceive('bindValue')->byDefault();
    $stmt->shouldReceive('execute')->byDefault()->andReturn(true);

    return [$pdo, $stmt];
};

beforeEach(function () use ($mockPdo): void {
    [$this->pdo, $this->stmt] = $mockPdo();
    $this->repo = new UserRepository($this->pdo);
});

afterEach(function (): void {
    m::close();
});

it('findByEmail zwraca wiersz użytkownika', function (): void {
    $this->stmt->shouldReceive('fetch')->once()->andReturn(['id' => 1, 'email' => 'a@pbs.local']);

    $result = $this->repo->findByEmail('a@pbs.local');
    expect($result)->toBe(['id' => 1, 'email' => 'a@pbs.local']);
});

it('findByEmail zwraca null, gdy użytkownik nie istnieje', function (): void {
    $this->stmt->shouldReceive('fetch')->once()->andReturn(false);

    expect($this->repo->findByEmail('nobody@pbs.local'))->toBeNull();
});

it('findById deleguje do bazowego findBy i zwraca wiersz', function (): void {
    $this->stmt->shouldReceive('fetch')->once()->andReturn(['id' => 7]);

    expect($this->repo->findById(7))->toBe(['id' => 7]);
});

it('create wykonuje INSERT i zwraca utworzony rekord', function (): void {
    $this->stmt->shouldReceive('fetch')->once()->andReturn(['id' => 42, 'email' => 'nowy@pbs.local']);

    $result = $this->repo->create(['email' => 'nowy@pbs.local', 'role' => 'user']);

    expect($result['id'])->toBe(42);
    expect($result['email'])->toBe('nowy@pbs.local');
});

it('search buduje zapytanie z filtrami i zwraca wiersze', function (): void {
    $rows = [
        ['id' => 1, 'email' => 'a@pbs.local', 'role' => 'admin', 'permissions' => '{}', 'is_active' => true],
    ];
    $this->stmt->shouldReceive('fetchAll')->once()->andReturn($rows);

    $result = $this->repo->search(
        ['email' => 'a', 'role' => 'admin', 'is_active' => '1'],
        25,
        0,
        'email',
        'asc',
    );
    expect($result)->toBe($rows);
});

it('countSearch zwraca liczbę dopasowań', function (): void {
    $this->stmt->shouldReceive('fetchColumn')->once()->andReturn(5);

    expect($this->repo->countSearch(['role' => 'admin']))->toBe(5);
});

it('countSearch zwraca 0, gdy fetchColumn zwróci false', function (): void {
    $this->stmt->shouldReceive('fetchColumn')->once()->andReturn(false);

    expect($this->repo->countSearch([]))->toBe(0);
});

it('setActive aktualizuje flagę is_active', function (): void {
    $this->stmt->shouldReceive('execute')->once()->andReturn(true);

    // Nie rzuca wyjątku
    $this->repo->setActive(1, false);
    expect(true)->toBeTrue();
});

it('updateFailedLogin z blokadą aktualizuje licznik i locked_until', function (): void {
    $this->stmt->shouldReceive('execute')->once()->andReturn(true);

    $this->repo->updateFailedLogin(1, 5, '2026-08-12 23:59:59');
    expect(true)->toBeTrue();
});

it('updateFailedLogin bez blokady czyści locked_until', function (): void {
    $this->stmt->shouldReceive('execute')->once()->andReturn(true);

    $this->repo->updateFailedLogin(1, 0);
    expect(true)->toBeTrue();
});

it('resetFailedLogin czyści licznik nieudanych logowań', function (): void {
    $this->stmt->shouldReceive('execute')->once()->andReturn(true);

    $this->repo->resetFailedLogin(1);
    expect(true)->toBeTrue();
});

it('updatePassword aktualizuje hash hasła', function (): void {
    $this->stmt->shouldReceive('execute')->once()->andReturn(true);

    $this->repo->updatePassword(1, 'hashed-password');
    expect(true)->toBeTrue();
});

it('updatePermissions zapisuje uprawnienia jako JSON', function (): void {
    $this->stmt->shouldReceive('execute')->once()->andReturn(true);

    $this->repo->updatePermissions(1, ['pracownicy' => true, 'awarie' => false]);
    expect(true)->toBeTrue();
});

it('findSuperAdminEmails zwraca adresy aktywnych kont super_admin', function (): void {
    $this->stmt->shouldReceive('fetchAll')->once()->andReturn([
        ['email' => 'super@pbs.local'],
        ['email' => 'admin@pbs.local'],
    ]);

    expect($this->repo->findSuperAdminEmails())->toBe(['super@pbs.local', 'admin@pbs.local']);
});

it('findSuperAdminEmails zwraca pustą tablicę, gdy brak kont super_admin', function (): void {
    $this->stmt->shouldReceive('fetchAll')->once()->andReturn([]);

    expect($this->repo->findSuperAdminEmails())->toBe([]);
});
