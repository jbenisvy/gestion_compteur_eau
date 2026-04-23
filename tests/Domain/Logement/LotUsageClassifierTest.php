<?php
declare(strict_types=1);

namespace App\Tests\Domain\Logement;

use App\Domain\Logement\LotUsageClassifier;
use App\Entity\Lot;
use PHPUnit\Framework\TestCase;

final class LotUsageClassifierTest extends TestCase
{
    public function testDetectsInoccupeEvenWithCommonTypo(): void
    {
        $classifier = new LotUsageClassifier();
        $lot = (new Lot())->setTypeAppartement('Appartement innocupé');

        self::assertTrue($classifier->isLotMarkedInoccupe($lot));
    }

    public function testDoesNotFlagRegularApartment(): void
    {
        $classifier = new LotUsageClassifier();
        $lot = (new Lot())->setTypeAppartement('T2 occupé');

        self::assertFalse($classifier->isLotMarkedInoccupe($lot));
    }
}
