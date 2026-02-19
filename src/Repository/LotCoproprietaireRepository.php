<?php

namespace App\Repository;

use App\Entity\Coproprietaire;
use App\Entity\Lot;
use App\Entity\LotCoproprietaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LotCoproprietaire>
 */
class LotCoproprietaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotCoproprietaire::class);
    }

    /**
     * Retourne les lots actifs pour un copropriétaire à une date donnée.
     *
     * @return Lot[]
     */
    public function findActiveLotsForCopro(Coproprietaire $copro, \DateTimeInterface $date): array
    {
        $d = $date->format('Y-m-d');

        return $this->createQueryBuilder('lc')
            ->innerJoin('lc.lot', 'l')->addSelect('l')
            ->andWhere('lc.coproprietaire = :copro')->setParameter('copro', $copro)
            ->andWhere('lc.dateDebut <= :d')->setParameter('d', $d)
            ->andWhere('lc.dateFin IS NULL OR lc.dateFin >= :d')
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le copropriétaire actif d'un lot à une date donnée.
     */
    public function findActiveCoproForLot(Lot $lot, \DateTimeInterface $date): ?LotCoproprietaire
    {
        $d = $date->format('Y-m-d');

        return $this->createQueryBuilder('lc')
            ->andWhere('lc.lot = :lot')->setParameter('lot', $lot)
            ->andWhere('lc.dateDebut <= :d')->setParameter('d', $d)
            ->andWhere('lc.dateFin IS NULL OR lc.dateFin >= :d')
            ->orderBy('lc.dateDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne tous les liens actifs d'un lot à une date donnée.
     *
     * @return LotCoproprietaire[]
     */
    public function findActiveLinksForLot(Lot $lot, \DateTimeInterface $date): array
    {
        $d = $date->format('Y-m-d');

        return $this->createQueryBuilder('lc')
            ->andWhere('lc.lot = :lot')->setParameter('lot', $lot)
            ->andWhere('lc.dateDebut <= :d')->setParameter('d', $d)
            ->andWhere('lc.dateFin IS NULL OR lc.dateFin >= :d')
            ->orderBy('lc.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasAnyLinkForCoproAndLot(Coproprietaire $copro, Lot $lot): bool
    {
        $count = (int)$this->createQueryBuilder('lc')
            ->select('COUNT(lc.id)')
            ->andWhere('lc.lot = :lot')->setParameter('lot', $lot)
            ->andWhere('lc.coproprietaire = :copro')->setParameter('copro', $copro)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
