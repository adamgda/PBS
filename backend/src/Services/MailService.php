<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;

/**
 * Serwis wysyłki e-mail — powiadomienia bezpieczeństwa.
 *
 * Implementacja oparta na PHP mail() / SMTP (rozszerzenie w Etap 14).
 * W dev mode (brak SMTP_HOST) loguje komunikat zamiast wysyłać.
 */
class MailService
{
    private readonly string $smtpHost;
    private readonly string $smtpFrom;
    private readonly string $smtpFromName;
    private readonly bool $enabled;

    public function __construct(Config $config)
    {
        $this->smtpHost = $config->get('SMTP_HOST', '') ?? '';
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
     * @param string $to Adres odbiorcy
     * @param string $subject Temat wiadomości
     * @param string $body Treść wiadomości
     */
    private function send(string $to, string $subject, string $body): void
    {
        if (!$this->enabled) {
            // Dev mode — logowanie zamiast wysyłki
            error_log("[MAIL] (dev) To: {$to}, Subject: {$subject}");
            return;
        }

        $headers = [
            'From: ' . $this->encodeFromName() . ' <' . $this->smtpFrom . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        // W produkcji docelowo: PHPMailer/Symfony Mailer z SMTP
        // Na razie: PHP mail() jako fallback
        @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    }

    private function encodeFromName(): string
    {
        return '=?UTF-8?B?' . base64_encode($this->smtpFromName) . '?=';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}