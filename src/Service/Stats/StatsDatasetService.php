<?php

declare(strict_types=1);

namespace App\Service\Stats;

use App\Repository\ParametreRepository;
use App\Service\Export\ExcelCompteursExportService;

final class StatsDatasetService
{
    /** @var array<int, array{ef:?float, ec:?float}> */
    private array $pricesByYear = [];

    public function __construct(
        private readonly ExcelCompteursExportService $exportService,
        private readonly ?ParametreRepository $parametreRepository = null,
    ) {
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
            $isSupprime = (bool)($row['compteur_supprime'] ?? false) || $statut === 'supprime';

            $isGrise = $isSupprime
                || in_array($statut, ['inactif'], true)
                || ($releveEtat !== '' && str_contains($releveEtat, 'supprime'));

            $isForfait = (bool)($row['forfait_applique'] ?? false);

            if (!$includeSupprime && $isSupprime) {
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
            $row['compteur_supprime'] = $isSupprime;
            $row['index_masque'] = (bool)($row['index_masque'] ?? $isSupprime);
            $row['consommation_type'] = $isForfait ? 'forfait' : 'reelle';
            $row['prix_m3_applicable'] = $this->resolveApplicablePrice($row);
            $row['valorisation_eur'] = $this->computeValorisation($row);

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

    /**
     * @param array<string, mixed> $row
     */
    private function resolveApplicablePrice(array $row): ?float
    {
        $year = $this->asInt($row['annee'] ?? null);
        if ($year === null || $this->parametreRepository === null) {
            return null;
        }

        if (!array_key_exists($year, $this->pricesByYear)) {
            $prices = $this->parametreRepository->getPrixM3ForYear($year);
            $this->pricesByYear[$year] = [
                'ef' => isset($prices['ef']) && is_numeric($prices['ef']) ? (float) $prices['ef'] : null,
                'ec' => isset($prices['prix_m3_ec']) && is_numeric($prices['prix_m3_ec'])
                    ? (float) $prices['prix_m3_ec']
                    : (isset($prices['ec']) && is_numeric($prices['ec']) ? (float) $prices['ec'] : null),
            ];
        }

        $nature = strtoupper(trim((string) ($row['compteur_nature'] ?? '')));

        return $nature === 'EF'
            ? $this->pricesByYear[$year]['ef']
            : $this->pricesByYear[$year]['ec'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function computeValorisation(array $row): ?float
    {
        $consommation = $row['consommation'] ?? null;
        $prixM3 = $row['prix_m3_applicable'] ?? null;

        if (!is_numeric($consommation) || !is_numeric($prixM3)) {
            return null;
        }

        return round(max(0.0, (float) $consommation) * (float) $prixM3, 2);
    }

    private function asInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
