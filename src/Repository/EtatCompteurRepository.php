<?php

namespace App\Repository;

use App\Entity\EtatCompteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EtatCompteur>
 */
class EtatCompteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EtatCompteur::class);
    }

    // Ajoute ici des méthodes personnalisées si besoin
}
