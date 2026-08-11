<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\DashboardRepository;

/**
 * Serwis dashboardu — sekcja Dashboard (Etap 13).
 *
 * Operacje (read-only): podsumowanie KPI (`summary`) oraz lista alertów (`alerts`).
 * Brak operacji mutujących — brak potrzeby audit logu.
 */
final class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboardRepository,
    ) {}

    /**
     * Podsumowanie KPI dla dashboardu.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $row = $this->dashboardRepository->summary();

        return [
            'active_employees' => $this->toInt($row['active_employees'] ?? 0),
            'active_terminals' => $this->toInt($row['active_terminals'] ?? 0),
            'vehicles_in_use' => $this->toInt($row['vehicles_in_use'] ?? 0),
            'active_incidents' => $this->toInt($row['active_incidents'] ?? 0),
            'hours_today' => $this->toFloat($row['hours_today'] ?? 0),
            'employees_on_leave' => $this->toInt($row['employees_on_leave'] ?? 0),
            'monthly_wages' => $this->toFloat($row['monthly_wages'] ?? 0),
        ];
    }

    /**
     * Lista alertów dla dashboardu.
     *
     * @return array<string, array{count: int, items: array<int, array<string, mixed>>}>
     */
    public function alerts(): array
    {
        $alerts = $this->dashboardRepository->alerts();

        return [
            'expiring_certs' => $this->alertGroup($alerts['expiring_certs'] ?? []),
            'upcoming_inspections' => $this->alertGroup($alerts['upcoming_inspections'] ?? []),
            'unresolved_incidents' => $this->alertGroup($alerts['unresolved_incidents'] ?? []),
            'returning_from_leave' => $this->alertGroup($alerts['returning_from_leave'] ?? []),
        ];
    }

    /**
     * Dane wykresów, aktywności i trendu area dla dashboardu.
     *
     * @return array<string, mixed>
     */
    public function charts(): array
    {
        $trend = $this->dashboardRepository->ordersTrend(14);
        $fleet = $this->dashboardRepository->fleetStructure();
        $turnover = $this->dashboardRepository->terminalTurnover(
            date('Y-m-01') . ' 00:00:00',
            date('Y-m-t') . ' 23:59:59',
        );
        $activity = $this->dashboardRepository->recentActivity(8);

        return [
            'orders_trend' => [
                'categories' => array_values(array_map(
                    static fn (mixed $v): string => is_string($v) ? $v : '',
                    $trend['categories'] ?? [],
                )),
                'series' => array_map(
                    fn (mixed $v): int => $this->toInt($v),
                    $trend['series'] ?? [],
                ),
                'trend_pct' => $this->toFloat($trend['trend_pct'] ?? 0),
            ],
            'fleet_structure' => [
                'labels' => ['Terminale', 'Pojazdy', 'Pracownicy', 'Inny sprzęt'],
                'series' => [
                    $this->toInt($fleet['terminals'] ?? 0),
                    $this->toInt($fleet['vehicles'] ?? 0),
                    $this->toInt($fleet['employees'] ?? 0),
                    $this->toInt($fleet['other_equipment'] ?? 0),
                ],
            ],
            'terminal_turnover' => [
                'categories' => array_map(
                    fn (array $row): string => $this->nullableString($row['nazwa'] ?? null) ?? '',
                    $turnover,
                ),
                'series' => array_map(
                    fn (array $row): float => $this->toFloat($row['turnover'] ?? 0),
                    $turnover,
                ),
            ],
            'activity' => array_map(
                fn (array $row): array => [
                    'type' => is_string($row['type'] ?? null) ? $row['type'] : 'other',
                    'title' => $this->nullableString($row['title'] ?? null) ?? '',
                    'time' => is_string($row['ts'] ?? null) ? $row['ts'] : null,
                ],
                $activity,
            ),
        ];
    }

    /**
     * Normalizuje grupę alertów (licznik + lista pozycji).
     *
     * @param array<string, mixed> $group
     * @return array{count: int, items: array<int, array<string, mixed>>}
     */
    private function alertGroup(array $group): array
    {
        $items = is_array($group['items'] ?? null) ? $group['items'] : [];
        $count = is_int($group['count'] ?? null) ? $group['count'] : count($items);

        return [
            'count' => $count,
            'items' => array_values(array_map(
                static fn (mixed $item): array => is_array($item) ? $item : [],
                $items,
            )),
        ];
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function toFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }
}
