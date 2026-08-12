<?php

declare(strict_types=1);

use App\Services\FileUploadService;
use App\Services\VirusScannerInterface;
use Mockery as m;

/**
 * Testy serwisu uploadu plików (Etap 7 / Etap 16 — testy bezpieczeństwa uploadu).
 * Pokrywają: walidację MIME, rozmiar, rozszerzenie, generowanie nazwy UUID
 * oraz signed URL (HMAC, TTL, manipulacja).
 */

$storageDir = sys_get_temp_dir() . '/pbs-upload-test-' . bin2hex(random_bytes(4));

beforeEach(function () use ($storageDir): void {
    $this->scanner = m::mock(VirusScannerInterface::class);
    $this->scanner->shouldReceive('isAvailable')->byDefault()->andReturn(false);
    $this->scanner->shouldReceive('scan')->byDefault()->andReturn(true);

    $this->storageDir = $storageDir;
    @mkdir($this->storageDir, 0o700, true);

    $this->service = new FileUploadService(
        $this->storageDir,
        'http://localhost:8080',
        'test-hmac-secret',
        $this->scanner,
    );
});

afterEach(function (): void {
    $files = glob($this->storageDir . '/*');
    if ($files !== false) {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    @rmdir($this->storageDir);
    m::close();
});

it('validate odrzuca plik bez tmp_name', function (): void {
    expect($this->service->validate([]))->toContain('No file uploaded');
});

it('validate odrzuca plik z kodem błędu uploadu', function (): void {
    $file = ['tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 10, 'name' => 'a.pdf'];
    $errors = $this->service->validate($file);
    expect(implode(' ', $errors))->toContain('Upload failed');
});

it('validate odrzuca plik większy niż 5 MB', function (): void {
    $file = ['tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK, 'size' => 6 * 1024 * 1024, 'name' => 'a.pdf'];
    $errors = $this->service->validate($file);
    expect(implode(' ', $errors))->toContain('File too large');
});

it('validate odrzuca niedozwolone rozszerzenie', function (): void {
    $file = ['tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK, 'size' => 10, 'name' => 'malware.exe'];
    $errors = $this->service->validate($file);
    expect(implode(' ', $errors))->toContain('Unsupported file type');
});

it('validate odrzuca plik, którego realny MIME nie pasuje do rozszerzenia', function (): void {
    // Plik PNG zawierający treść PDF → realny MIME (application/pdf) nie pasuje do .png (image/png)
    $pdf = "%PDF-1.4\n%âãÏÓ\n" . str_repeat('x', 32);
    $tmp = tempnam(sys_get_temp_dir(), 'pbsmime');
    file_put_contents($tmp, $pdf);
    try {
        $file = ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($pdf), 'name' => 'fake.png'];
        $errors = $this->service->validate($file);
        expect(implode(' ', $errors))->toContain('MIME type mismatch');
    } finally {
        @unlink($tmp);
    }
});

it('validate akceptuje poprawny plik PDF', function (): void {
    $pdf = "%PDF-1.4\n%âãÏÓ\n" . str_repeat('x', 32);
    $tmp = tempnam(sys_get_temp_dir(), 'pbsmime');
    file_put_contents($tmp, $pdf);
    try {
        $file = ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($pdf), 'name' => 'dokument.pdf'];
        expect($this->service->validate($file))->toBe([]);
    } finally {
        @unlink($tmp);
    }
});

it('store zapisuje plik pod nazwą UUID i zwraca uuid.ext', function (): void {
    $content = '%PDF-1.4 test content';
    $tmp = tempnam(sys_get_temp_dir(), 'pbsstore');
    file_put_contents($tmp, $content);
    try {
        $name = $this->service->store([
            'tmp_name' => $tmp,
            'name' => 'dokument.pdf',
        ]);
        expect($name)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}\\.pdf$/');
        expect(file_exists($this->storageDir . '/' . $name))->toBeTrue();
        expect(file_get_contents($this->storageDir . '/' . $name))->toBe($content);
    } finally {
        @unlink($tmp);
    }
});

it('store rzuca wyjątek, gdy skaner wykryje wirusa', function (): void {
    $this->scanner->shouldReceive('isAvailable')->andReturn(true);
    $this->scanner->shouldReceive('scan')->andReturn(false);

    $tmp = tempnam(sys_get_temp_dir(), 'pbsvirus');
    file_put_contents($tmp, 'EICAR');
    try {
        $this->service->store(['tmp_name' => $tmp, 'name' => 'x.pdf']);
    } finally {
        @unlink($tmp);
    }
})->throws(RuntimeException::class, 'virus scan');

it('signedUrl generuje token, który verifySignedToken poprawnie weryfikuje', function (): void {
    $url = $this->service->signedUrl('uuid.pdf');
    expect($url)->toContain('/api/v1/documents/download?token=');

    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    expect($this->service->verifySignedToken($query['token']))->toBe('uuid.pdf');
});

it('verifySignedToken zwraca null dla zmodyfikowanego tokena', function (): void {
    $url = $this->service->signedUrl('uuid.pdf');
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    $tampered = $query['token'] . 'x';

    expect($this->service->verifySignedToken($tampered))->toBeNull();
});

it('verifySignedToken zwraca null dla nieprawidłowego base64', function (): void {
    expect($this->service->verifySignedToken('!!!not-base64!!!'))->toBeNull();
});
