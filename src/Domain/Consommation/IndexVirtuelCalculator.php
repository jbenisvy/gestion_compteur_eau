<?php
declare(strict_types=1);

namespace App\Domain\Consommation;

use App\Entity\ReleveItem;

final class IndexVirtuelCalculator
{
    public function calculate(ReleveItem $item, ?int $forfaitValue = null, ?string $etatCode = null, int $forfaitsAnterieurs = 0): ?int
    {
        $etatCode = $etatCode !== null ? mb_strtolower(trim($etatCode)) : null;
        if ($etatCode !== null && (str_contains($etatCode, 'supprim') || str_contains($etatCode, 'suppr'))) {
            return null;
        }

        if ($item->isForfait()) {
            $base = $item->getIndexN1();
            if ($base === null) {
                return null;
            }

            $forfaitValue ??= $this->asInt($item->getConsommation());

            return $base + max(0, $forfaitsAnterieurs) + max(0, $forfaitValue ?? 0);
        }

        return $item->getIndexN() ?? $item->getIndexNouveauCompteur();
    }

    private function asInt(?string $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value);
    }
}
