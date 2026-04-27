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
     * @param array{annee?:int, coproprietaire_id?:int, lot_id?:int, eau?:string, piece?:string, sort?:string} $filters
     * @return array{filters:array<string,mixed>, years:int[], groups:array<int,array<string,mixed>>, totals:array<string,mixed>, skipped:array<string,mixed>}
     */
    public function build(array $filters = [], ?int $restrictedCoproprietaireId = null): array
    {
        $exportFilters = [];
        if (isset($filters['annee'])) {
            $exportFilters['annee'] = (int)$filters['annee'];
        }
        if (isset($filters['lot_id'])) {
            $exportFilters['lot_id'] = (int)$filters['lot_id'];
        }

        $payload = $this->exportService->export($exportFilters);
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        $targetCoproId = $restrictedCoproprietaireId ?? ($filters['coproprietaire_id'] ?? null);
        $targetCoproId = $targetCoproId !== null ? (int)$targetCoproId : null;
        $targetLotId = isset($filters['lot_id']) ? (int)$filters['lot_id'] : null;
        $eauFilter = $this->normalizeEau($filters['eau'] ?? null);
        $pieceFilter = $this->normalizePiece($filters['piece'] ?? null);
        $sort = in_array(($filters['sort'] ?? 'copro'), ['copro', 'lot'], true) ? (string)$filters['sort'] : 'copro';

        $pricesByYear = [];
        $groups = [];
        $years = [];
        $globalTotals = $this->emptyTotals();
        $skipped = [
            'count' => 0,
            'items' => [],
        ];

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
            $lotId = (int)($row['lot_id'] ?? 0);
            if ($targetLotId !== null && $lotId !== $targetLotId) {
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

            if (!is_numeric($row['consommation'] ?? null)) {
                if ($annee === 2018 && !is_numeric($row['index_n_1'] ?? null)) {
                    $skipped['count']++;
                    $skipped['items'][] = [
                        'annee' => $annee,
                        'lot_numero' => (string)($row['lot_numero'] ?? ''),
                        'compteur' => (string)($row['compteur_reference'] ?? ('#' . (string)($row['compteur_id'] ?? ''))),
                        'piece' => $piece,
                        'eau' => $eau,
                        'motif' => 'Facturation 2018 masquee: index 2017 absent',
                    ];
                }
                continue;
            }

            $pricesByYear[$annee] ??= $this->pricesForYear($annee);
            $prixM3 = $eau === 'chaude' ? $pricesByYear[$annee]['chaude'] : $pricesByYear[$annee]['froide'];
            $consommation = max(0.0, (float)$row['consommation']);
            $montant = round($consommation * $prixM3, 2);

            $groupKey = $annee . ':' . ($coproId ?? 0);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'annee' => $annee,
                    'coproprietaire_id' => $coproId,
                    'coproprietaire_nom' => (string)($row['proprietaire_nom'] ?? 'Non rattaché'),
                    'lot_sort' => (string)($row['lot_numero'] ?? ''),
                    'lots' => [],
                    'totals' => $this->emptyTotals(),
                    'rows' => [],
                    'skipped_rows' => 0,
                ];
            }

            $lotKey = (string)$lotId;
            $groups[$groupKey]['lots'][$lotKey] = [
                'id' => $lotId,
                'numero' => (string)($row['lot_numero'] ?? ''),
                'type' => (string)($row['lot_type_appartement'] ?? ''),
                'localisation' => (string)($row['lot_description'] ?? ''),
            ];
            if (($groups[$groupKey]['lot_sort'] ?? '') === '') {
                $groups[$groupKey]['lot_sort'] = (string)($row['lot_numero'] ?? '');
            }

            $detail = [
                'annee' => $annee,
                'lot_id' => $lotId,
                'lot_numero' => (string)($row['lot_numero'] ?? ''),
                'lot_description' => (string)($row['lot_description'] ?? ''),
                'lot_type_appartement' => (string)($row['lot_type_appartement'] ?? ''),
                'piece' => $piece,
                'eau' => $eau,
                'compteur' => (string)($row['compteur_reference'] ?? ('#' . (string)($row['compteur_id'] ?? ''))),
                'consommation' => $consommation,
                'prix_m3' => $prixM3,
                'montant' => $montant,
                'forfait' => (bool)($row['forfait_applique'] ?? false),
                'forfait_valeur' => isset($row['forfait_valeur']) ? (float)$row['forfait_valeur'] : null,
                'forfait_motif' => $this->nullIfEmpty($row['forfait_motif'] ?? null),
                'compteur_supprime' => (bool)($row['compteur_supprime'] ?? false),
                'compteur_statut' => $this->nullIfEmpty($row['compteur_statut'] ?? null),
                'lot_inoccupe' => (bool)($row['lot_inoccupe'] ?? false),
                'lot_inoccupe_motif' => $this->nullIfEmpty($row['lot_inoccupe_motif'] ?? null),
                'commentaire' => $this->nullIfEmpty($row['commentaire'] ?? null),
                'notes' => $this->buildDetailNotes($row),
            ];

            $groups[$groupKey]['rows'][] = $detail;
            $this->addToTotals($groups[$groupKey]['totals'], $piece, $eau, $consommation, $montant);
            $this->addToTotals($globalTotals, $piece, $eau, $consommation, $montant);
            $years[$annee] = true;
        }

        foreach ($skipped['items'] as $item) {
            $groupKey = ((int)$item['annee']) . ':' . 0;
            foreach ($groups as $existingKey => $group) {
                if ((int)$group['annee'] === (int)$item['annee']) {
                    if ((string)($group['lot_sort'] ?? '') === (string)$item['lot_numero']) {
                        $groups[$existingKey]['skipped_rows']++;
                        continue 2;
                    }
                    if (array_filter((array)($group['lots'] ?? []), static fn (array $lot): bool => (string)($lot['numero'] ?? '') === (string)$item['lot_numero'])) {
                        $groups[$existingKey]['skipped_rows']++;
                        continue 2;
                    }
                }
            }
        }

        $groups = array_values($groups);
        foreach ($groups as &$group) {
            $group['lots'] = array_values((array)$group['lots']);
            usort($group['lots'], static fn (array $a, array $b): int => strnatcasecmp((string)$a['numero'], (string)$b['numero']));
        }
        unset($group);

        usort($groups, static function (array $a, array $b) use ($sort): int {
            $yearCmp = (int)$b['annee'] <=> (int)$a['annee'];
            if ($yearCmp !== 0) {
                return $yearCmp;
            }

            if ($sort === 'lot') {
                return strnatcasecmp((string)$a['lot_sort'], (string)$b['lot_sort'])
                    ?: strnatcasecmp((string)$a['coproprietaire_nom'], (string)$b['coproprietaire_nom']);
            }

            return strnatcasecmp((string)$a['coproprietaire_nom'], (string)$b['coproprietaire_nom'])
                ?: strnatcasecmp((string)$a['lot_sort'], (string)$b['lot_sort']);
        });

        $years = array_keys($years);
        rsort($years);

        return [
            'filters' => [
                'annee' => $filters['annee'] ?? null,
                'coproprietaire_id' => $targetCoproId,
                'lot_id' => $targetLotId,
                'eau' => $eauFilter,
                'piece' => $pieceFilter,
                'sort' => $sort,
            ],
            'years' => $years,
            'groups' => $groups,
            'totals' => $globalTotals,
            'skipped' => [
                'count' => $skipped['count'],
                'items' => array_slice($skipped['items'], 0, 20),
            ],
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

    /**
     * @param array<string,mixed> $row
     * @return string[]
     */
    private function buildDetailNotes(array $row): array
    {
        $notes = [];

        if ((bool)($row['forfait_applique'] ?? false)) {
            $motif = $this->nullIfEmpty($row['forfait_motif'] ?? null);
            $notes[] = $motif !== null ? 'Forfait: ' . $motif : 'Forfait applique';
        }

        $compteurStatut = mb_strtolower(trim((string)($row['compteur_statut'] ?? '')));
        $releveEtat = mb_strtolower(trim((string)($row['releve_etat_code'] ?? '')));
        $compteurEmplacement = mb_strtolower(trim((string)($row['compteur_emplacement_norm'] ?? $row['compteur_emplacement'] ?? '')));

        if ($compteurStatut === 'remplace' || str_contains($releveEtat, 'remplac') || str_contains($releveEtat, 'démont') || str_contains($releveEtat, 'demonte')) {
            $notes[] = 'Compteur remplace';
        }

        if ((bool)($row['lot_inoccupe'] ?? false)) {
            $notes[] = (string)($row['lot_inoccupe_motif'] ?? 'Appartement inoccupe');
        }

        if (str_contains($releveEtat, 'non communiqu')) {
            $notes[] = 'Releve non communique';
        }

        if ((bool)($row['compteur_supprime'] ?? false)) {
            $notes[] = str_contains($compteurEmplacement, 'cuis') ? 'Compteur cuisine supprime' : 'Compteur supprime';
        }

        $commentaire = $this->nullIfEmpty($row['commentaire'] ?? null);
        if ($commentaire !== null) {
            $notes[] = 'Commentaire: ' . $commentaire;
        }

        return array_values(array_unique($notes));
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }
}
