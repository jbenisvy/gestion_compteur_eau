<?php

declare(strict_types=1);

namespace App\Service\Stats;

use App\Service\Export\ExcelCompteursExportService;

final class StatsDatasetService
{
    private ExcelCompteursExportService $exportService;

    public function __construct(ExcelCompteursExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * @param array{annee?:int, from?:int, to?:int, lot_id?:int, compteur_id?:int} $filters
     * @param array{include_supprime?:bool, include_inactif?:bool, include_forfait?:bool, include_grise?:bool} $options
     * @return array<string, mixed>
     */
    public function build(array $filters, array $options = []): array
    {
        $payload = $this->exportService->export($filters);

        $rows = $payload['rows'] ?? [];

        // Options d'inclusion/exclusion (par defaut: tout afficher).
        $includeSupprime = $options['include_supprime'] ?? true;
        $includeInactif = $options['include_inactif'] ?? true;
        $includeForfait = $options['include_forfait'] ?? true;
        $includeGrise = $options['include_grise'] ?? true;

        $filtered = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $statut = (string)($row['compteur_statut'] ?? 'actif');
            $releveEtat = $this->normalize((string)($row['releve_etat_code'] ?? ''));

            $isGrise = in_array($statut, ['supprime', 'inactif'], true)
                || ($releveEtat !== '' && str_contains($releveEtat, 'supprime'));

            $isForfait = (bool)($row['forfait_applique'] ?? false);

            if (!$includeSupprime && $statut === 'supprime') {
                continue;
            }
            if (!$includeInactif && $statut === 'inactif') {
                continue;
            }
            if (!$includeForfait && $isForfait) {
                continue;
            }
            if (!$includeGrise && $isGrise) {
                continue;
            }

            $row['ligne_grisee'] = $isGrise;
            $row['consommation_type'] = $isForfait ? 'forfait' : 'reelle';

            $filtered[] = $row;
        }

        $payload['rows'] = $filtered;
        $payload['meta'] = array_merge(
            (array)($payload['meta'] ?? []),
            [
                'row_count_filtered' => count($filtered),
                'options' => [
                    'include_supprime' => $includeSupprime,
                    'include_inactif' => $includeInactif,
                    'include_forfait' => $includeForfait,
                    'include_grise' => $includeGrise,
                ],
            ]
        );

        return $payload;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        return $value;
    }
}
