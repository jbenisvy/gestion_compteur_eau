<?php

namespace App\DataFixtures;

use App\Entity\Compteur;
use App\Entity\Coproprietaire;
use App\Entity\EtatCompteur;
use App\Entity\Lot;
use App\Entity\LotCoproprietaire;
use App\Entity\Releve;
use App\Entity\ReleveItem;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TestDataFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Evite les doublons si on rejoue les fixtures
        $existing = $manager->getRepository(User::class)->findOneBy([
            'email' => 'seed_lot01_a@example.com',
        ]);
        if ($existing) {
            return;
        }

        // Etat compteur "actif"
        $etatActif = $manager->getRepository(EtatCompteur::class)->findOneBy(['code' => 'actif']);
        if (!$etatActif) {
            $etatActif = new EtatCompteur();
            $etatActif->setCode('actif');
            $etatActif->setLibelle('En fonctionnement');
            $manager->persist($etatActif);
        }

        $years = [2023, 2024, 2025];
        $lotCount = 10;

        for ($i = 1; $i <= $lotCount; $i++) {
            // 2 copropriétaires pour l’historique (A: 2023-2024, B: 2025+)
            $userA = new User();
            $userA->setEmail(sprintf('seed_lot%02d_a@example.com', $i));
            $userA->setRoles(['ROLE_USER']);
            $userA->setPassword($this->passwordHasher->hashPassword($userA, 'test1234'));
            $manager->persist($userA);

            $coproA = new Coproprietaire();
            $coproA->setNom('CoproA');
            $coproA->setPrenom('Lot' . $i);
            $coproA->setEmail($userA->getEmail());
            $coproA->setTelephone('06000000' . str_pad((string)$i, 2, '0', STR_PAD_LEFT));
            $coproA->setUser($userA);
            $manager->persist($coproA);

            $userB = new User();
            $userB->setEmail(sprintf('seed_lot%02d_b@example.com', $i));
            $userB->setRoles(['ROLE_USER']);
            $userB->setPassword($this->passwordHasher->hashPassword($userB, 'test1234'));
            $manager->persist($userB);

            $coproB = new Coproprietaire();
            $coproB->setNom('CoproB');
            $coproB->setPrenom('Lot' . $i);
            $coproB->setEmail($userB->getEmail());
            $coproB->setTelephone('07000000' . str_pad((string)$i, 2, '0', STR_PAD_LEFT));
            $coproB->setUser($userB);
            $manager->persist($coproB);

            $lot = new Lot();
            $lot->setNumeroLot('TST-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT));
            $lot->setTypeAppartement($i % 2 === 0 ? 'T3' : 'T2');
            $lot->setEmplacement('Etage ' . (($i % 5) + 1));
            $lot->setTantieme(80 + ($i * 3));
            $lot->setOccupant('Occupant Lot ' . $i);
            $manager->persist($lot);

            // Historique copro
            $linkA = new LotCoproprietaire();
            $linkA->setLot($lot);
            $linkA->setCoproprietaire($coproA);
            $linkA->setDateDebut(new \DateTimeImmutable('2023-01-01'));
            $linkA->setDateFin(new \DateTimeImmutable('2024-12-31'));
            $linkA->setIsPrincipal(true);
            $manager->persist($linkA);

            $linkB = new LotCoproprietaire();
            $linkB->setLot($lot);
            $linkB->setCoproprietaire($coproB);
            $linkB->setDateDebut(new \DateTimeImmutable('2025-01-01'));
            $linkB->setDateFin(null);
            $linkB->setIsPrincipal(true);
            $manager->persist($linkB);

            // Compteurs (2 par lot)
            $compteurs = [];
            foreach (['EC' => 'Cuisine', 'EF' => 'Salle de bain'] as $type => $piece) {
                $cmp = new Compteur();
                $cmp->setType($type);
                $cmp->setEmplacement($piece);
                $cmp->setDateInstallation(new \DateTimeImmutable('2022-01-01'));
                $cmp->setNumeroSerie(sprintf('CMP-%02d-%s', $i, $type));
                $cmp->setActif(true);
                $cmp->setEtatCompteur($etatActif);
                $cmp->setLot($lot);
                $manager->persist($cmp);
                $compteurs[] = $cmp;
            }

            // Relevés sur 3 ans
            $indexBase = 100 * $i;
            foreach ($years as $y) {
                $releve = new Releve();
                $releve->setLot($lot);
                $releve->setAnnee($y);
                $releve->setCreatedAt(new \DateTimeImmutable($y . '-09-01'));
                $releve->setUpdatedAt(new \DateTimeImmutable($y . '-09-15'));
                $releve->setVerrouille(false);
                $manager->persist($releve);

                foreach ($compteurs as $pos => $cmp) {
                    $item = new ReleveItem();
                    $item->setReleve($releve);
                    $item->setCompteur($cmp);

                    $delta = 15 + ($pos * 5);
                    $indexN1 = $indexBase + (($y - 2023) * $delta);
                    $indexN = $indexN1 + $delta;

                    $item->setIndexN1($indexN1);
                    $item->setIndexN($indexN);
                    $item->setIndexCompteurDemonté(null);
                    $item->setIndexNouveauCompteur(null);
                    $item->setEtatId($etatActif->getId());
                    $item->setForfait(false);
                    $item->setCommentaire(null);
                    $item->setConsommation((string)$delta);
                    $item->setCreatedAt(new \DateTimeImmutable($y . '-09-01'));
                    $item->setUpdatedAt(new \DateTimeImmutable($y . '-09-15'));

                    $releve->addItem($item);
                    $manager->persist($item);
                }
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['seed'];
    }
}
