<?php
declare(strict_types=1);

namespace App\Tests\Domain\Consommation;

use App\Domain\Consommation\ForfaitConsommationResolver;
use App\Entity\Compteur;
use App\Entity\EtatCompteur;
use PHPUnit\Framework\TestCase;

final class ForfaitConsommationResolverTest extends TestCase
{
    public function testFourMeterLotAppliesOneForfaitPerBlockedMeter(): void
    {
        $resolver = new ForfaitConsommationResolver();
        $compteurs = [
            $this->compteur('EC', 'Cuisine EC'),
            $this->compteur('EF', 'Cuisine EF'),
            $this->compteur('EC', 'SDB EC'),
            $this->compteur('EF', 'SDB EF'),
        ];

        self::assertSame(75.0, $resolver->resolveForCompteur($compteurs[0], ['ec' => 75, 'ef' => 150], $compteurs));
        self::assertSame(150.0, $resolver->resolveForCompteur($compteurs[1], ['ec' => 75, 'ef' => 150], $compteurs));
    }

    public function testTwoMeterLotDoublesForfaitForSingleMeterByWaterType(): void
    {
        $resolver = new ForfaitConsommationResolver();
        $compteurs = [
            $this->compteur('EC', 'Appartement EC'),
            $this->compteur('EF', 'Appartement EF'),
        ];

        self::assertSame(150.0, $resolver->resolveForCompteur($compteurs[0], ['ec' => 75, 'ef' => 150], $compteurs));
        self::assertSame(300.0, $resolver->resolveForCompteur($compteurs[1], ['ec' => 75, 'ef' => 150], $compteurs));
    }

    public function testDeletedMetersDoNotPreventTwoMeterRule(): void
    {
        $resolver = new ForfaitConsommationResolver();
        $deleted = $this->compteur('EF', 'Ancien EF SDB');
        $deleted->setActif(false);

        $compteurs = [
            $this->compteur('EC', 'Appartement EC'),
            $this->compteur('EF', 'Appartement EF'),
            $deleted,
        ];

        self::assertSame(300.0, $resolver->resolveForCompteur($compteurs[1], ['ec' => 75, 'ef' => 150], $compteurs));
    }

    private function compteur(string $type, string $emplacement): Compteur
    {
        $etat = (new EtatCompteur())->setCode('actif')->setLibelle('En fonctionnement');

        return (new Compteur())
            ->setType($type)
            ->setEmplacement($emplacement)
            ->setEtatCompteur($etat);
    }
}
