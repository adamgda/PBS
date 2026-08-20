<?php

declare(strict_types=1);

use App\Config\Config;

it('loads config from env file', function (): void {
    $tmpFile = tmpfile();
    if ($tmpFile === false) {
        throw new RuntimeException('Cannot create temp file');
    }
    $path = stream_get_meta_data($tmpFile)['uri'];
    fwrite($tmpFile, "DATABASE_HOST=localhost\nDATABASE_NAME=pbs\n# comment\nAPP_DEBUG=true\n");

    $config = Config::fromEnvFile($path);

    expect($config->get('DATABASE_HOST'))->toBe('localhost');
    expect($config->get('DATABASE_NAME'))->toBe('pbs');
    expect($config->get('APP_DEBUG'))->toBe('true');
});

it('returns default for missing key', function (): void {
    $config = new Config(['KEY1' => 'value1']);

    expect($config->get('KEY1'))->toBe('value1');
    expect($config->get('MISSING', 'fallback'))->toBe('fallback');
    expect($config->get('MISSING'))->toBeNull();
});

it('throws for missing required key', function (): void {
    $config = new Config([]);

    $config->getRequired('MISSING');
})->throws(RuntimeException::class);

it('returns required value when present', function (): void {
    $config = new Config(['JWT_SECRET' => 'abc123']);

    expect($config->getRequired('JWT_SECRET'))->toBe('abc123');
});

it('handles empty or missing env file gracefully', function (): void {
    $config = Config::fromEnvFile('/nonexistent/path/.env');

    expect($config->get('ANY_KEY'))->toBeNull();
});

it('strips quotes from env values', function (): void {
    $tmpFile = tmpfile();
    if ($tmpFile === false) {
        throw new RuntimeException('Cannot create temp file');
    }
    $path = stream_get_meta_data($tmpFile)['uri'];
    fwrite($tmpFile, 'KEY_DOUBLE="double_value"' . "\n" . 'KEY_SINGLE=\'single_value\'' . "\n");

    $config = Config::fromEnvFile($path);

    expect($config->get('KEY_DOUBLE'))->toBe('double_value');
    expect($config->get('KEY_SINGLE'))->toBe('single_value');
});

it('parses multi-line quoted values (e.g. RSA PEM keys)', function (): void {
    $tmpFile = tmpfile();
    if ($tmpFile === false) {
        throw new RuntimeException('Cannot create temp file');
    }
    $path = stream_get_meta_data($tmpFile)['uri'];
    $pem = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQ\n-----END PRIVATE KEY-----";
    fwrite($tmpFile, "JWT_ALGORITHM=RS256\n");
    fwrite($tmpFile, 'JWT_PRIVATE_KEY="' . $pem . '"' . "\n");
    fwrite($tmpFile, "OTHER_KEY=plain\n");

    $config = Config::fromEnvFile($path);

    expect($config->get('JWT_ALGORITHM'))->toBe('RS256');
    expect($config->get('JWT_PRIVATE_KEY'))->toBe($pem);
    expect($config->get('OTHER_KEY'))->toBe('plain');
});