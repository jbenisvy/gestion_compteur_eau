<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Coproprietaire;
use App\Entity\Lot;
use App\Entity\Compteur;
use App\Entity\EtatCompteur;
use App\Entity\Releve;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    private function setAny(object $o, array $setters, mixed $value): void
    {
        foreach ($setters as $m) { if (method_exists($o, $m)) { $o->$m($value); return; } }
    }
    private function getAny(object $o, array $getters): mixed
    {
        foreach ($getters as $g) { if (method_exists($o, $g)) { return $o->$g(); } }
        return null;
    }

    public function load(ObjectManager $manager): void
    {
        // Admin
        $admin = new User();
        if (method_exists($admin,'setEmail')) $admin->setEmail('admin@example.com'); else if (method_exists($admin,'setUsername')) $admin->setUsername('admin@example.com');
        if (method_exists($admin,'setRoles')) $admin->setRoles(['ROLE_ADMIN']);
        if (method_exists($admin,'setPassword')) $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // États
        $etatLabels = ['actif'=>'En fonctionnement','supprime'=>'Supprimé','forfait'=>'Forfait','remplace'=>'Remplacé'];
        $etats = [];
        foreach ($etatLabels as $code=>$lib) {
            $e = new EtatCompteur();
            $this->setAny($e, ['setCode'], $code);
            $this->setAny($e, ['setLibelle','setLabel','setNom'], $lib);
            $manager->persist($e);
            $etats[$code] = $e;
        }

        // Copros + Users
        $identites = [
            ['Alice','Martin'],['Bruno','Durand'],['Chloé','Moreau'],['David','Petit'],['Emma','Garcia'],
            ['Fabien','Roux'],['Gina','Lambert'],['Hugo','Lefevre'],['Inès','Robert'],['Jules','Richard'],
            ['Karen','Simon'],['Léo','Michel'],['Maya','Dubois'],['Noah','Laurent'],['Ophélie','Fournier'],
        ];
        $copros = [];
        foreach ($identites as [$p,$n]) {
            $u = new User();
            $email = strtolower("$p.$n@example.com");
            if (method_exists($u,'setEmail')) $u->setEmail($email); elseif (method_exists($u,'setUsername')) $u->setUsername($email);
            if (method_exists($u,'setRoles')) $u->setRoles(['ROLE_USER']);
            if (method_exists($u,'setPassword')) $u->setPassword($this->passwordHasher->hashPassword($u, 'password'));
            $manager->persist($u);

            $c = new Coproprietaire();
            $this->setAny($c, ['setPrenom'], $p);
            $this->setAny($c, ['setNom'], $n);
            $this->setAny($c, ['setEmail'], $email);
            $this->setAny($c, ['setTelephone'], '06'.random_int(10000000,99999999));
            $this->setAny($c, ['setUser'], $u);
            $manager->persist($c);
            $copros[] = $c;
        }

        // Lots
        $typesApp = ['T1','T2','T3','T4'];
        $lots = [];
        for ($i=1;$i<=10;$i++) {
            $lot = new Lot();
            $num = 100+$i;
            $cop = $copros[($i-1)%count($copros)];
            $this->setAny($lot, ['setNumeroLot'], (string)$num);
            $this->setAny($lot, ['setEmplacement'], "Étage ".ceil($i/2)." — Lot $num");
            $this->setAny($lot, ['setTypeAppartement'], $typesApp[array_rand($typesApp)]);
            $this->setAny($lot, ['setTantieme'], random_int(50,250));
            $this->setAny($lot, ['setCoproprietaire'], $cop);
            $this->setAny($lot, ['setOccupant'], $cop->getNom());
            $manager->persist($lot);
            $lots[] = $lot;
        }

        // Compteurs (4 slots / lot)
        $compteurs = [];
        foreach ($lots as $idx=>$lot) {
            $specs = [
                ['Cuisine','EC','actif', true],
                ['Cuisine','EF','actif', true],
                ['Salle de bain','EC','actif', true],
                ['Salle de bain','EF','actif', true],
            ];
            // exemple historique: sur le 1er lot, ajouter un ancien supprimé
            if ($idx === 0) {
                $specs[] = ['Cuisine','EC','supprime', false];
            }

            foreach ($specs as [$piece,$type,$etatCode,$actif]) {
                $cmp = new Compteur();
                $this->setAny($cmp, ['setLot'], $lot);
                $this->setAny($cmp, ['setEmplacement'], $piece);
                $this->setAny($cmp, ['setType'], $type);
                $this->setAny($cmp, ['setEtatCompteur'], $etats[$etatCode]);
                $this->setAny($cmp, ['setActif'], (bool)$actif);
                $this->setAny($cmp, ['setDateInstallation'], new \DateTimeImmutable(sprintf('%d-%02d-%02d', random_int(2016,2024), random_int(1,12), random_int(1,28))));
                $manager->persist($cmp);
                $compteurs[] = $cmp;
            }
        }

        // Relevés 2018→2024
        $years = range(2018, 2024);
        foreach ($compteurs as $cmp) {
            $lot = $this->getAny($cmp, ['getLot']);
            $cop = $lot? $this->getAny($lot,['getCoproprietaire']): null;
            $etat = $this->getAny($cmp, ['getEtatCompteur']);
            $ecode = ($etat && method_exists($etat,'getCode'))? $etat->getCode() : null;

            $index = random_int(80, 200);
            foreach ($years as $y) {
                $r = new Releve();
                $this->setAny($r, ['setCompteur'], $cmp);
                $this->setAny($r, ['setLot'], $lot);
                $this->setAny($r, ['setCoproprietaire'], $cop);
                $this->setAny($r, ['setEtatCompteur'], $etat);
                $this->setAny($r, ['setAnnee'], $y);
                $this->setAny($r, ['setDateSaisie'], new \DateTimeImmutable());

                if ($ecode !== 'supprime') { $index += random_int(10,50); }
                $this->setAny($r, ['setIndexN'], $index);
                $this->setAny($r, ['setIndexNmoins1'], max(0, $index - random_int(10,60)));
                $this->setAny($r, ['setOccupantAnnee'], $cop ? $cop->getNom() : 'Occupant inconnu');

                $manager->persist($r);
            }
        }

        $manager->flush();
    }
}
