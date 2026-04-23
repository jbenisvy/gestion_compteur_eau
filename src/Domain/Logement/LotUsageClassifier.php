<?php
declare(strict_types=1);

namespace App\Domain\Logement;

use App\Entity\Lot;

final class LotUsageClassifier
{
    public function isLotMarkedInoccupe(Lot $lot, ?string $commentaire = null): bool
    {
        return $this->isInoccupeText(
            $lot->getTypeAppartement() . ' ' . $lot->getOccupant() . ' ' . (string)$commentaire
        );
    }

    public function isInoccupeText(?string $text): bool
    {
        $text = $this->normalize($text ?? '');
        if ($text === '') {
            return false;
        }

        return str_contains($text, 'inoccup')
            || str_contains($text, 'innocup')
            || str_contains($text, 'inoccupe')
            || str_contains($text, 'vacant')
            || str_contains($text, 'vide');
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }
}
