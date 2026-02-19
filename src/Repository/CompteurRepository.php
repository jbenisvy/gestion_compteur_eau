<?php

namespace App\Repository;

use App\Entity\Compteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Compteur>
 */
class CompteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Compteur::class);
    }

    /**
     * Retourne les compteurs d'un lot classés par type et eau.
     * @param int $lotId
     * @return Compteur[]
     */
    public function findByLotSorted(int $lotId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.lot = :lotId')
            ->setParameter('lotId', $lotId)
            ->orderBy('c.type', 'ASC')
            ->addOrderBy('c.eau', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
