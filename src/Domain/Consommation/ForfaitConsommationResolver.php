<?php
declare(strict_types=1);

namespace App\Domain\Consommation;

use App\Entity\Compteur;

final class ForfaitConsommationResolver
{
    /**
     * @param array{ef?:float|int|null, ec?:float|int|null} $forfaits
     * @param array<int, Compteur> $lotCompteurs
     */
    public function resolveForCompteur(Compteur $compteur, array $forfaits, array $lotCompteurs): float
    {
        return $this->baseForfait($compteur, $forfaits) * $this->multiplierForCompteur($compteur, $lotCompteurs);
    }

    /**
     * @param array{ef?:float|int|null, ec?:float|int|null} $forfaits
     */
    public function baseForfait(Compteur $compteur, array $forfaits): float
    {
        return $this->normalizeType($compteur->getType()) === 'EF'
            ? (float)($forfaits['ef'] ?? 0.0)
            : (float)($forfaits['ec'] ?? 0.0);
    }

    /**
     * Dans les appartements à 2 compteurs, un compteur EC/EF représente les deux points d'eau
     * historiques: le forfait voté par compteur doit donc être doublé.
     *
     * @param array<int, Compteur> $lotCompteurs
     */
    public function multiplierForCompteur(Compteur $compteur, array $lotCompteurs): int
    {
        $effectiveById = [];
        foreach ($lotCompteurs as $candidate) {
            if (!$candidate instanceof Compteur || !$this->isEffectiveCompteur($candidate)) {
                continue;
            }

            $id = $candidate->getId();
            $key = $id !== null
                ? 'id:' . $id
                : $this->normalizeType($candidate->getType()) . '|' . mb_strtolower(trim($candidate->getEmplacement()));
            $effectiveById[$key] = $candidate;
        }

        $effective = array_values($effectiveById);
        if (count($effective) > 2) {
            return 1;
        }

        $type = $this->normalizeType($compteur->getType());
        $sameTypeCount = 0;
        foreach ($effective as $candidate) {
            if ($this->normalizeType($candidate->getType()) === $type) {
                $sameTypeCount++;
            }
        }

        return $sameTypeCount === 1 ? 2 : 1;
    }

    private function isEffectiveCompteur(Compteur $compteur): bool
    {
        if (!$compteur->isActif()) {
            return false;
        }

        $etat = $compteur->getEtatCompteur();
        $etatCode = $etat !== null ? mb_strtolower(trim($etat->getCode() . ' ' . $etat->getLibelle())) : '';

        return !str_contains($etatCode, 'supprim') && !str_contains($etatCode, 'suppr');
    }

    private function normalizeType(string $type): string
    {
        $type = mb_strtoupper(trim($type));
        return $type === 'EF' ? 'EF' : 'EC';
    }
}
