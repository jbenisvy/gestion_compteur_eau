<?php
declare(strict_types=1);

namespace App\Domain\Consommation;

final class CalculateurConsommation
{
    /**
     * Règles:
     * - EN_FONCTIONNEMENT : conso = max(0, indexN - indexN1)
     * - SUPPRIME          : conso = 0
     * - BLOQUE            : conso = forfait (ou 0 si non fourni)
     * - REMPLACE          : (indexDemonte - indexN1) [=0 si HS] + indexNouveau
     */
    public function calculer(
        string $etatCode,
        ?int $indexN1,
        ?int $indexN,
        ?int $indexDemonte,
        ?int $indexNouveau,
        ?float $forfait = null,
        bool $ancienHs = false
    ): float {
        $indexN1      = (int)($indexN1 ?? 0);
        $indexN       = (int)($indexN ?? 0);
        $indexDemonte = (int)($indexDemonte ?? 0);
        $indexNouveau = (int)($indexNouveau ?? 0);

        switch ($etatCode) {
            case 'SUPPRIME':
                return 0.0;
            case 'BLOQUE':
                return (float)($forfait ?? 0.0);
            case 'REMPLACE':
                $partAncien  = $ancienHs ? 0 : max(0, $indexDemonte - $indexN1);
                $partNouveau = max(0, $indexNouveau);
                return (float)($partAncien + $partNouveau);
            default: // EN_FONCTIONNEMENT
                return (float)max(0, $indexN - $indexN1);
        }
    }
}
