<?php
declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Service\Export\ExcelCompteursExportService;

final class DashboardSummaryService
{
    public function __construct(
        private readonly ExcelCompteursExportService $exportService,
    ) {
    }

    /**
     * @return array<int, array{year:int, categories:array<int, array{key:string,label:string,count:int,items:array<int,string>}>}>
     */
    public function buildYearlySummary(): array
    {
        $payload = $this->exportService->export();
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        $summaries = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $year = (int) ($row['annee'] ?? 0);
            if ($year <= 0) {
                continue;
            }

            if (!isset($summaries[$year])) {
                $summaries[$year] = $this->createYearBucket($year);
            }

            $rowText = $this->normalizeText(implode(' ', [
                (string) ($row['releve_etat_code'] ?? ''),
                (string) ($row['releve_etat_libelle'] ?? ''),
                (string) ($row['compteur_etat_code'] ?? ''),
                (string) ($row['compteur_etat_libelle'] ?? ''),
                (string) ($row['forfait_motif'] ?? ''),
                (string) ($row['commentaire'] ?? ''),
            ]));

            $lotLabel = $this->buildLotLabel($row);
            $compteurLabel = $this->buildCompteurLabel($row);
            $compteurKey = 'compteur:' . (string) ($row['compteur_id'] ?? $compteurLabel);
            $lotKey = 'lot:' . (string) ($row['lot_id'] ?? $lotLabel);
            $statut = $this->normalizeText((string) ($row['compteur_statut'] ?? ''));

            if (str_contains($rowText, 'bloqu')) {
                $this->registerItem($summaries[$year]['categories']['blocked'], $compteurKey, $compteurLabel);
            }

            if (
                $statut === 'remplace'
                || str_contains($rowText, 'remplac')
                || str_contains($rowText, 'nouveau')
                || str_contains($rowText, 'demont')
                || str_contains($rowText, 'demonte')
            ) {
                $this->registerItem($summaries[$year]['categories']['replaced'], $compteurKey, $compteurLabel);
            }

            if ((bool) ($row['lot_inoccupe'] ?? false) || str_contains($rowText, 'inoccupe')) {
                $this->registerItem($summaries[$year]['categories']['vacant'], $lotKey, $lotLabel);
            }

            if ((bool) ($row['compteur_supprime'] ?? false) || $statut === 'supprime' || str_contains($rowText, 'supprim')) {
                $this->registerItem($summaries[$year]['categories']['deleted_lot'], $lotKey, $lotLabel);
            }

            if (str_contains($rowText, 'non communiqu')) {
                $this->registerItem($summaries[$year]['categories']['not_reported'], $compteurKey, $compteurLabel);
            }
        }

        krsort($summaries);

        return array_values(array_map(function (array $summary): array {
            $summary['categories'] = array_values(array_map(function (array $category): array {
                unset($category['_seen']);
                $category['items'] = array_slice($category['items'], 0, 8);

                return $category;
            }, $summary['categories']));

            return $summary;
        }, $summaries));
    }

    /**
     * @return array{year:int, categories:array<string, array{key:string,label:string,count:int,items:array<int,string>,_seen:array<string,bool>}>}
     */
    private function createYearBucket(int $year): array
    {
        return [
            'year' => $year,
            'categories' => [
                'blocked' => $this->createCategory('blocked', 'Compteurs bloqués'),
                'replaced' => $this->createCategory('replaced', 'Compteurs remplacés'),
                'vacant' => $this->createCategory('vacant', 'Appartements inoccupés'),
                'deleted_lot' => $this->createCategory('deleted_lot', 'Lots avec compteurs supprimés'),
                'not_reported' => $this->createCategory('not_reported', 'Relevés non communiqués'),
            ],
        ];
    }

    /**
     * @return array{key:string,label:string,count:int,items:array<int,string>,_seen:array<string,bool>}
     */
    private function createCategory(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => 0,
            'items' => [],
            '_seen' => [],
        ];
    }

    /**
     * @param array{count:int,items:array<int,string>,_seen:array<string,bool>} $category
     */
    private function registerItem(array &$category, string $uniqueKey, string $label): void
    {
        if (isset($category['_seen'][$uniqueKey])) {
            return;
        }

        $category['_seen'][$uniqueKey] = true;
        $category['count']++;
        $category['items'][] = $label;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function buildLotLabel(array $row): string
    {
        $lot = trim((string) ($row['lot_numero'] ?? '?'));
        $desc = trim((string) ($row['lot_description'] ?? ''));

        return $desc !== '' ? sprintf('Lot %s - %s', $lot, $desc) : sprintf('Lot %s', $lot);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function buildCompteurLabel(array $row): string
    {
        $reference = trim((string) ($row['compteur_reference'] ?? ''));
        $nature = trim((string) ($row['compteur_nature'] ?? ''));
        $emplacement = trim((string) ($row['compteur_emplacement'] ?? ''));

        $parts = [$this->buildLotLabel($row)];
        if ($reference !== '') {
            $parts[] = $reference;
        }
        if ($emplacement !== '' || $nature !== '') {
            $parts[] = trim($emplacement . ($nature !== '' ? ' (' . $nature . ')' : ''));
        }

        return implode(' - ', array_filter($parts, static fn (string $value): bool => $value !== ''));
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return $value;
    }
}
