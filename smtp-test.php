<?php

declare(strict_types=1);

/**
 * Test połączenia SMTP PBS — weryfikuje wysyłkę przez h55.seohost.pl:465.
 * Używa danych z backend/.env (SMTP_*).
 */

require __DIR__ . '/backend/vendor/autoload.php';

use App\Config\Config;

$config = Config::fromEnvFile(__DIR__ . '/backend/.env');
$host = $config->get('SMTP_HOST', '') ?? '';
$port = (int) ($config->get('SMTP_PORT', '465') ?? '465');
$user = $config->get('SMTP_USER', '') ?? '';
$pass = $config->get('SMTP_PASSWORD', '') ?? '';
$from = $config->get('SMTP_FROM', '') ?? '';
$fromName = $config->get('SMTP_FROM_NAME', 'PBS') ?? 'PBS';

$to = 'adammz.gda@gmail.com';

echo "=== Konfiguracja SMTP ===\n";
echo "Host: {$host}:{$port}\nUser: {$user}\nFrom: {$from}\nTo: {$to}\n\n";

if ($host === '') {
    echo "BŁĄD: SMTP_HOST pusty — tryb dev (brak wysyłki)\n";
    exit(1);
}

$remote = $port === 465 ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
$ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
echo "Łączenie z {$remote}...\n";
$sock = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
if ($sock === false) {
    echo "BŁĄD POŁĄCZENIA: {$errstr} ({$errno})\n";
    exit(1);
}
stream_set_timeout($sock, 15);
echo "Połączono.\n";

function readResp($sock): string {
    $r = '';
    while (!feof($sock)) {
        $line = fgets($sock, 515);
        if ($line === false) break;
        $r .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
        if (trim($line) === '') break;
    }
    return $r;
}
function step($sock, string $cmd, ?int $expect = null): string {
    fwrite($sock, $cmd);
    $resp = readResp($sock);
    $label = explode("\r\n", $cmd)[0];
    echo ">> {$label}\n<< " . trim($resp) . "\n";
    if ($expect !== null && (int)substr($resp, 0, 3) !== $expect) {
        echo "!!! Oczekiwano {$expect}\n";
    }
    return $resp;
}

step($sock, '', 220);
$ehlo = explode('@', $from)[1] ?? 'localhost';
step($sock, "EHLO {$ehlo}\r\n", 250);
step($sock, "AUTH LOGIN\r\n", 334);
step($sock, base64_encode($user) . "\r\n", 334);
step($sock, base64_encode($pass) . "\r\n", 235);
step($sock, "MAIL FROM:<{$from}>\r\n", 250);
step($sock, "RCPT TO:<{$to}>\r\n", 250);
step($sock, "DATA\r\n", 354);

$subject = '=?UTF-8?B?' . base64_encode('Test SMTP PBS') . '?=';
$fromNameEnc = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
$headers = [
    "From: {$fromNameEnc} <{$from}>",
    "To: <{$to}>",
    "Subject: {$subject}",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    'Date: ' . date('r'),
];
$body = "To jest testowa wiadomość z systemu PBS — weryfikacja połączenia SMTP.\n\nWysłano: " . date('Y-m-d H:i:s');
$msg = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
step($sock, $msg . "\r\n.\r\n", 250);
step($sock, "QUIT\r\n");
fclose($sock);

echo "\n=== WYNIK: wysyłka zakończona — sprawdź skrzynkę {$to} ===\n";