<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;

/**
 * Serwis wysyłki e-mail — powiadomienia bezpieczeństwa.
 *
 * Wysyłka przez bezpośrednie połączenie SMTP (TLS + AUTH LOGIN).
 * Obsługa portów:
 *  - 465  → SMTPS (niejawny TLS, transport ssl://)
 *  - 587  → STARTTLS (przejście na TLS po EHLO)
 *  - 25   → plain (bez szyfrowania — tylko dev/lokalne relay)
 *
 * Gdy SMTP_HOST jest pusty → tryb dev: logowanie treści przez error_log (bez wysyłki).
 */
class MailService
{
    private readonly string $smtpHost;
    private readonly string $smtpPort;
    private readonly string $smtpUser;
    private readonly string $smtpPassword;
    private readonly string $smtpFrom;
    private readonly string $smtpFromName;
    private readonly bool $enabled;

    public function __construct(Config $config)
    {
        $this->smtpHost = $config->get('SMTP_HOST', '') ?? '';
        $this->smtpPort = $config->get('SMTP_PORT', '465') ?? '465';
        $this->smtpUser = $config->get('SMTP_USER', '') ?? '';
        $this->smtpPassword = $config->get('SMTP_PASSWORD', '') ?? '';
        $this->smtpFrom = $config->get('SMTP_FROM', 'no-reply@pbs.local') ?? 'no-reply@pbs.local';
        $this->smtpFromName = $config->get('SMTP_FROM_NAME', 'Port Baltic Shipping') ?? 'Port Baltic Shipping';
        $this->enabled = $this->smtpHost !== '';
    }

    /**
     * Wysyła powiadomienie o blokadzie konta.
     */
    public function sendAccountLockedNotification(string $email, int $attempts, bool $manualUnlock = false): void
    {
        $subject = $manualUnlock
            ? 'Konto zablokowane — wymagane ręczne odblokowanie'
            : 'Konto zablokowane tymczasowo';

        $body = $manualUnlock
            ? "Twoje konto w systemie PBS zostało zablokowane po {$attempts} nieudanych próbach logowania.\n"
            . "Wymagane jest ręczne odblokowanie przez administratora.\n\n"
            . "Jeśli to nie Ty próbowałeś się zalogować, skontaktuj się z administratorem."
            : "Twoje konto w systemie PBS zostało tymczasowo zablokowane po {$attempts} nieudanych próbach logowania.\n"
            . "Konto zostanie odblokowane automatycznie po 15 minutach.\n\n"
            . "Jeśli to nie Ty próbowałeś się zalogować, skontaktuj się z administratorem.";

        $this->send($email, $subject, $body);
    }

    /**
     * Wysyła e-mail resetujący hasło (link z tokenem).
     */
    public function sendPasswordResetEmail(string $email, string $token, string $frontendBaseUrl): void
    {
        $link = rtrim($frontendBaseUrl, '/') . '/set-password?token=' . $token;
        $subject = 'Resetowanie hasła — PBS';
        $body = "Otrzymaliśmy prośbę o reset hasła w systemie PBS.\n\n"
            . "Kliknij poniższy link, aby ustawić nowe hasło:\n{$link}\n\n"
            . "Link wygasa po 1 godzinie.\n\n"
            . "Jeśli to nie Ty wysłałeś tę prośbę, zignoruj tę wiadomość.";

        $this->send($email, $subject, $body);
    }

    /**
     * Wysyła e-mail alertowy (powiadomienie operacyjne — Etap 14).
     *
     * Zwraca `true`, gdy wiadomość została wysłana (lub zalogowana w trybie dev),
     * `false` przy błędzie SMTP. W trybie dev (pusty SMTP_HOST) zawsze zwraca `true`.
     */
    public function sendAlert(string $to, string $subject, string $body): bool
    {
        return $this->send($to, $subject, $body);
    }

    /**
     * @param string $to Adres odbiorcy
     * @param string $subject Temat wiadomości
     * @param string $body Treść wiadomości
     */
    private function send(string $to, string $subject, string $body): bool
    {
        if (!$this->enabled) {
            // Dev mode — logowanie pełnej treści (z linkiem resetującym) zamiast wysyłki.
            error_log("[MAIL] (dev) To: {$to}, Subject: {$subject}, Body: {$body}");
            return true;
        }

        try {
            $this->sendViaSmtp($to, $subject, $body);

            return true;
        } catch (\Throwable $e) {
            // Awaria SMTP — logujemy błąd (nie przerywamy flow użytkownika).
            error_log("[MAIL] SMTP error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Wysyła wiadomość przez bezpośrednie połączenie SMTP z TLS i AUTH LOGIN.
     *
     * @throws \RuntimeException w przypadku błędu protokołu/połączenia
     */
    private function sendViaSmtp(string $to, string $subject, string $body): void
    {
        $port = (int) $this->smtpPort;
        $sock = $this->connect($port);

        try {
            $this->expect($sock, 220);
            $ehlo = $this->ehloHost();

            $this->write($sock, "EHLO {$ehlo}\r\n");
            $capabilities = $this->readResponse($sock);
            $this->assertCode($capabilities, 250, 'EHLO');

            // STARTTLS dla portu 587 (i 25 jeśli serwer ogłasza).
            $useStarttls = $port !== 465 && str_contains($capabilities, 'STARTTLS');
            if ($useStarttls) {
                $this->write($sock, "STARTTLS\r\n");
                $this->expect($sock, 220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed');
                }
                $this->write($sock, "EHLO {$ehlo}\r\n");
                $this->assertCode($this->readResponse($sock), 250, 'EHLO after TLS');
            }

            // AUTH LOGIN (gdy podano dane logowania).
            if ($this->smtpUser !== '') {
                $this->write($sock, "AUTH LOGIN\r\n");
                $this->expect($sock, 334);
                $this->write($sock, base64_encode($this->smtpUser) . "\r\n");
                $this->expect($sock, 334);
                $this->write($sock, base64_encode($this->smtpPassword) . "\r\n");
                $this->expect($sock, 235);
            }

            $this->write($sock, "MAIL FROM:<{$this->smtpFrom}>\r\n");
            $this->expect($sock, 250);
            $this->write($sock, "RCPT TO:<{$to}>\r\n");
            $this->expect($sock, 250);
            $this->write($sock, "DATA\r\n");
            $this->expect($sock, 354);

            $message = $this->buildMessage($to, $subject, $body);
            $this->write($sock, $message . "\r\n.\r\n");
            $this->expect($sock, 250);

            $this->write($sock, "QUIT\r\n");
        } finally {
            if (is_resource($sock)) {
                fclose($sock);
            }
        }
    }

    /**
     * Nawiązuje połączenie: ssl:// dla 465 (niejawny TLS), tcp:// dla pozostałych.
     *
     * @return resource
     * @throws \RuntimeException
     */
    private function connect(int $port)
    {
        if ($port === 465) {
            $remote = "ssl://{$this->smtpHost}:{$port}";
            $ctx = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);
        } else {
            $remote = "tcp://{$this->smtpHost}:{$port}";
            $ctx = stream_context_create([]);
        }

        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if ($sock === false) {
            throw new \RuntimeException("Connect to {$remote} failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($sock, 15);

        return $sock;
    }

    /**
     * Buduje pełną wiadomość RFC 5322 z nagłówkami.
     */
    private function buildMessage(string $to, string $subject, string $body): string
    {
        $fromName = '=?UTF-8?B?' . base64_encode($this->smtpFromName) . '?=';
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $date = date('r');
        $messageId = '<' . bin2hex(random_bytes(16)) . '@pbs.local>';

        $headers = [
            "From: {$fromName} <{$this->smtpFrom}>",
            "To: <{$to}>",
            "Subject: {$subjectEnc}",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            "Date: {$date}",
            "Message-ID: {$messageId}",
        ];

        // base64 + chunk_split (SMTP wymaga linie <= 76 znaków).
        $encodedBody = chunk_split(base64_encode($body));

        return implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;
    }

    /**
     * Host dla EHLO/HELO — domena z SMTP_FROM lub localhost.
     */
    private function ehloHost(): string
    {
        $parts = explode('@', $this->smtpFrom);
        return $parts[1] ?? 'localhost';
    }

    /**
     * @param resource $sock
     */
    private function write($sock, string $data): void
    {
        fwrite($sock, $data);
    }

    /**
     * Oczekuje odpowiedzi o wskazanym kodzie (wielolinijkowej).
     *
     * @param resource $sock
     * @throws \RuntimeException
     */
    private function expect($sock, int $expectedCode, string $context = ''): void
    {
        $response = $this->readResponse($sock);
        $this->assertCode($response, $expectedCode, $context);
    }

    /**
     * Czyta pełną odpowiedź SMTP (obsługa linii kontynuacji `nnn-`).
     *
     * @param resource $sock
     */
    private function readResponse($sock): string
    {
        $response = '';
        while (!feof($sock)) {
            $line = fgets($sock, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // Linia bez '-' na 4. pozycji kończy odpowiedź wielolinijkową.
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
            if (trim($line) === '') {
                break;
            }
        }

        return $response;
    }

    /**
     * @throws \RuntimeException
     */
    private function assertCode(string $response, int $expected, string $context): void
    {
        $code = $response !== '' ? (int) substr($response, 0, 3) : 0;
        if ($code !== $expected) {
            $ctxLabel = $context !== '' ? " [{$context}]" : '';
            throw new \RuntimeException("SMTP unexpected response{$ctxLabel}: expected {$expected}, got: " . trim($response));
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}