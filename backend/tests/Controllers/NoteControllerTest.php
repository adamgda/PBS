<?php

declare(strict_types=1);

use App\Controllers\NoteController;
use App\Http\Request;
use App\Repository\UserNoteRepository;
use App\Services\NoteService;
use Mockery as m;

function noteRow(int $id = 1, string $tresc = 'Zadanie', bool $isDone = false, int $kolejnosc = 0): array
{
    return [
        'id' => $id,
        'user_id' => 1,
        'tresc' => $tresc,
        'is_done' => $isDone ? 1 : 0,
        'kolejnosc' => $kolejnosc,
        'created_at' => null,
        'updated_at' => null,
    ];
}

function noteAuthedRequest(array $body = [], array $query = [], int $userId = 1): Request
{
    $request = new Request(query: $query, body: $body, headers: []);
    $request->setAttribute('user_id', $userId);

    return $request;
}

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->noteRepository = m::mock(UserNoteRepository::class, [$pdo]);
    $this->noteService = new NoteService($this->noteRepository);
    $this->noteController = new NoteController($this->noteService);
});

afterEach(function (): void {
    m::close();
});

it('index returns list of notes for the user', function (): void {
    $this->noteRepository
        ->shouldReceive('findAllForUser')
        ->with(1, null, 'kolejnosc', 'asc')
        ->andReturn([noteRow(1, 'Zadanie', false), noteRow(2, 'Zrobione', true)]);

    $response = $this->noteController->index(noteAuthedRequest());

    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toHaveCount(2);
    expect($response->data()['data'][1]['is_done'])->toBe(true);
});

it('index passes is_done filter from query', function (): void {
    $this->noteRepository
        ->shouldReceive('findAllForUser')
        ->with(1, true, 'kolejnosc', 'asc')
        ->andReturn([]);

    $response = $this->noteController->index(noteAuthedRequest(query: ['is_done' => '1']));

    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toBe([]);
});

it('store returns 422 when tresc missing', function (): void {
    expect($this->noteController->store(noteAuthedRequest([]))->statusCode())->toBe(422);
});

it('store returns 422 when tresc exceeds 500 characters', function (): void {
    $long = str_repeat('a', 501);
    expect($this->noteController->store(noteAuthedRequest(['tresc' => $long]))->statusCode())->toBe(422);
});

it('store creates note assigned to user from JWT', function (): void {
    $this->noteRepository
        ->shouldReceive('create')
        ->with(m::on(fn (array $d): bool => $d['user_id'] === 1 && $d['tresc'] === 'Zadanie' && $d['is_done'] === 0))
        ->andReturn(noteRow());

    $response = $this->noteController->store(noteAuthedRequest(['tresc' => 'Zadanie']));

    expect($response->statusCode())->toBe(201);
    expect($response->data()['tresc'])->toBe('Zadanie');
});

it('update returns 404 for note of another user (IDOR)', function (): void {
    $this->noteRepository->shouldReceive('findByIdForUser')->with(99, 1)->andReturnNull();

    $response = $this->noteController->update(noteAuthedRequest(['tresc' => 'Nowe']), ['id' => '99']);

    expect($response->statusCode())->toBe(404);
});

it('update edits note content', function (): void {
    $this->noteRepository->shouldReceive('findByIdForUser')->with(1, 1)->andReturn(noteRow());
    $this->noteRepository
        ->shouldReceive('updateForUser')
        ->with(1, 1, m::on(fn (array $d): bool => $d['tresc'] === 'Nowe'))
        ->andReturn(noteRow(1, 'Nowe'));

    $response = $this->noteController->update(noteAuthedRequest(['tresc' => 'Nowe']), ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['tresc'])->toBe('Nowe');
});

it('toggleDone returns 404 for foreign note', function (): void {
    $this->noteRepository->shouldReceive('findByIdForUser')->with(5, 1)->andReturnNull();

    $response = $this->noteController->toggleDone(noteAuthedRequest([]), ['id' => '5']);

    expect($response->statusCode())->toBe(404);
});

it('toggleDone toggles is_done when body empty', function (): void {
    $this->noteRepository->shouldReceive('findByIdForUser')->with(1, 1)->andReturn(noteRow(1, 'Zadanie', false));
    $this->noteRepository
        ->shouldReceive('updateForUser')
        ->with(1, 1, m::on(fn (array $d): bool => $d['is_done'] === 1))
        ->andReturn(noteRow(1, 'Zadanie', true));

    $response = $this->noteController->toggleDone(noteAuthedRequest([]), ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['is_done'])->toBe(true);
});

it('toggleDone respects explicit is_done body', function (): void {
    $this->noteRepository->shouldReceive('findByIdForUser')->with(1, 1)->andReturn(noteRow(1, 'Zadanie', true));
    $this->noteRepository
        ->shouldReceive('updateForUser')
        ->with(1, 1, m::on(fn (array $d): bool => $d['is_done'] === 0))
        ->andReturn(noteRow(1, 'Zadanie', false));

    $response = $this->noteController->toggleDone(noteAuthedRequest(['is_done' => false]), ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['is_done'])->toBe(false);
});

it('destroy returns 404 for note not owned by user (IDOR)', function (): void {
    $this->noteRepository->shouldReceive('deleteForUser')->with(99, 1)->andReturn(false);

    $response = $this->noteController->destroy(noteAuthedRequest([]), ['id' => '99']);

    expect($response->statusCode())->toBe(404);
});

it('destroy deletes own note', function (): void {
    $this->noteRepository->shouldReceive('deleteForUser')->with(1, 1)->andReturn(true);

    $response = $this->noteController->destroy(noteAuthedRequest([]), ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBe(true);
});

it('clear removes all notes of the user', function (): void {
    $this->noteRepository->shouldReceive('clearForUser')->with(1, null)->andReturn(3);

    $response = $this->noteController->clear(noteAuthedRequest([]));

    expect($response->statusCode())->toBe(200);
    expect($response->data()['deleted'])->toBe(3);
});

it('clear removes only done notes when is_done=1', function (): void {
    $this->noteRepository->shouldReceive('clearForUser')->with(1, true)->andReturn(2);

    $response = $this->noteController->clear(noteAuthedRequest(query: ['is_done' => '1']));

    expect($response->statusCode())->toBe(200);
    expect($response->data()['deleted'])->toBe(2);
});

