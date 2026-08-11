<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repozytorium wysyłek alertów — tabela `alert_notifications` (Etap 14).
 *
 * Append-only log, który pozwala uniknąć wielokrotnego wysyłania tego samego
 * powiadomienia w ciągu dnia (dedupe: konfiguracja + typ + encja + data).
 */
class AlertNotificationRepository extends BaseRepository
{
    protected string $table = 'alert_notifications';
    protected string $primaryKey = 'id';

    /**
     * Sprawdza, czy dla danej konfiguracji + encji wysłano już alert danego dnia.
     */
    public function alreadySent(int $configId, string $refType, int $refId, string $date): bool
    {
        $sql = 'SELECT COUNT(*) FROM `alert_notifications`
                WHERE `alert_config_id` = :config
                  AND `ref_type` = :ref_type
                  AND `ref_id` = :ref_id
                  AND `data_wysylki` = :date';
        $stmt = $this->executeQuery($sql, [
            ':config' => $configId,
            ':ref_type' => $refType,
            ':ref_id' => $refId,
            ':date' => $date,
        ]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }

    /**
     * Rejestruje wysyłkę alertu (do dedupe i audytu).
     */
    public function markSent(int $configId, string $typ, string $refType, int $refId, string $date): void
    {
        $this->executeQuery(
            'INSERT INTO `alert_notifications` (`alert_config_id`, `typ`, `ref_type`, `ref_id`, `data_wysylki`)
             VALUES (:config, :typ, :ref_type, :ref_id, :date)',
            [
                ':config' => $configId,
                ':typ' => $typ,
                ':ref_type' => $refType,
                ':ref_id' => $refId,
                ':date' => $date,
            ],
        );
    }
}
