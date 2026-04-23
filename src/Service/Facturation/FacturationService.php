<?php
declare(strict_types=1);

namespace App\Service\Facturation;

use App\Repository\ParametreRepository;
use App\Service\Export\ExcelCompteursExportService;

final class FacturationService
{
    public function __construct(
        private readonly ExcelCompteursExportService $exportService,
        private readonly ParametreRepository $parametreRepository,
    ) {
    }

    /**
     * @param array{annee?:int, coproprietaire_id?:int, eau?:string, piece?:string} $filters
     * @return array{filters:array<string,mixed>, years:int[], groups:array<int,array<string,mixed>>, totals:array<string,mixed>}
     */
    public function build(array $filters = [], ?int $restrictedCoproprietaireId = null): array
    {
        $exportFilters = [];
        if (isset($filters['annee'])) {
            $exportFilters['annee'] = (int)$filters['annee'];
        }

        $payload = $this->exportService->export($exportFilters);
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        $targetCoproId = $restrictedCoproprietaireId ?? ($filters['coproprietaire_id'] ?? null);
        $targetCoproId = $targetCoproId !== null ? (int)$targetCoproId : null;
        $eauFilter = $this->normalizeEau($filters['eau'] ?? null);
        $pieceFilter = $this->normalizePiece($filters['piece'] ?? null);

        $pricesByYear = [];
        $groups = [];
        $years = [];
        $globalTotals = $this->emptyTotals();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $annee = (int)($row['annee'] ?? 0);
            if ($annee <= 0) {
                continue;
            }

            $coproId = $row['proprietaire_id'] ?? null;
            $coproId = $coproId !== null ? (int)$coproId : null;
            if ($targetCoproId !== null && $coproId !== $targetCoproId) {
                continue;
            }

            $eau = $this->normalizeEau($row['compteur_nature'] ?? null);
            if ($eauFilter !== null && $eau !== $eauFilter) {
                continue;
            }

            $piece = $this->normalizePiece($row['compteur_emplacement_norm'] ?? $row['compteur_emplacement'] ?? null);
            if ($pieceFilter !== null && $piece !== $pieceFilter) {
                continue;
            }

            $pricesByYear[$annee] ??= $this->pricesForYear($annee);
            $prixM3 = $eau === 'chaude' ? $pricesByYear[$annee]['chaude'] : $pricesByYear[$annee]['froide'];
            $consommation = max(0.0, (float)($row['consommation'] ?? 0.0));
            $montant = round($consommation * $prixM3, 2);

            $groupKey = $annee . ':' . ($coproId ?? 0);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'annee' => $annee,
                    'coproprietaire_id' => $coproId,
                    'coproprietaire_nom' => (string)($row['proprietaire_nom'] ?? 'Non rattaché'),
                    'totals' => $this->emptyTotals(),
                    'rows' => [],
                ];
            }

            $detail = [
                'annee' => $annee,
                'lot_numero' => (string)($row['lot_numero'] ?? ''),
                'lot_description' => (string)($row['lot_description'] ?? ''),
                'piece' => $piece,
                'eau' => $eau,
                'compteur' => (string)($row['compteur_reference'] ?? ('#' . (string)($row['compteur_id'] ?? ''))),
                'consommation' => $consommation,
                'prix_m3' => $prixM3,
                'montant' => $montant,
                'forfait' => (bool)($row['forfait_applique'] ?? false),
                'compteur_supprime' => (bool)($row['compteur_supprime'] ?? false),
            ];

            $groups[$groupKey]['rows'][] = $detail;
            $this->addToTotals($groups[$groupKey]['totals'], $piece, $eau, $consommation, $montant);
            $this->addToTotals($globalTotals, $piece, $eau, $consommation, $montant);
            $years[$annee] = true;
        }

        $groups = array_values($groups);
        usort($groups, static function (array $a, array $b): int {
            return ((int)$b['annee'] <=> (int)$a['annee'])
                ?: strnatcasecmp((string)$a['coproprietaire_nom'], (string)$b['coproprietaire_nom']);
        });

        $years = array_keys($years);
        rsort($years);

        return [
            'filters' => [
                'annee' => $filters['annee'] ?? null,
                'coproprietaire_id' => $targetCoproId,
                'eau' => $eauFilter,
                'piece' => $pieceFilter,
            ],
            'years' => $years,
            'groups' => $groups,
            'totals' => $globalTotals,
        ];
    }

    /**
     * @return array{froide:float, chaude:float}
     */
    private function pricesForYear(int $annee): array
    {
        $prices = $this->parametreRepository->getPrixM3ForYear($annee);

        return [
            'froide' => (float)($prices['ef'] ?? 0.0),
            'chaude' => (float)($prices['prix_m3_ec'] ?? $prices['ec'] ?? 0.0),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyTotals(): array
    {
        return [
            'cuisine' => [
                'froide' => ['m3' => 0.0, 'montant' => 0.0],
                'chaude' => ['m3' => 0.0, 'montant' => 0.0],
            ],
            'sdb' => [
                'froide' => ['m3' => 0.0, 'montant' => 0.0],
                'chaude' => ['m3' => 0.0, 'montant' => 0.0],
            ],
            'global' => [
                'froide' => ['m3' => 0.0, 'montant' => 0.0],
                'chaude' => ['m3' => 0.0, 'montant' => 0.0],
                'total' => 0.0,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $totals
     */
    private function addToTotals(array &$totals, string $piece, string $eau, float $m3, float $montant): void
    {
        if (!isset($totals[$piece][$eau])) {
            return;
        }

        $totals[$piece][$eau]['m3'] = round((float)$totals[$piece][$eau]['m3'] + $m3, 3);
        $totals[$piece][$eau]['montant'] = round((float)$totals[$piece][$eau]['montant'] + $montant, 2);
        $totals['global'][$eau]['m3'] = round((float)$totals['global'][$eau]['m3'] + $m3, 3);
        $totals['global'][$eau]['montant'] = round((float)$totals['global'][$eau]['montant'] + $montant, 2);
        $totals['global']['total'] = round((float)$totals['global']['total'] + $montant, 2);
    }

    private function normalizeEau(mixed $value): ?string
    {
        $value = mb_strtolower(trim((string)$value));
        if ($value === '') {
            return null;
        }
        if (in_array($value, ['ef', 'froide', 'eau_froide', 'eau froide'], true)) {
            return 'froide';
        }
        if (in_array($value, ['ec', 'chaude', 'eau_chaude', 'eau chaude'], true)) {
            return 'chaude';
        }

        return null;
    }

    private function normalizePiece(mixed $value): ?string
    {
        $value = mb_strtolower(trim((string)$value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, 'cuis')) {
            return 'cuisine';
        }
        if (str_contains($value, 'sdb') || str_contains($value, 'salle') || str_contains($value, 'wc')) {
            return 'sdb';
        }

        return null;
    }
}
