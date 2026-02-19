<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lot;
use App\Entity\Releve;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ReleveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Releve::class);
    }

    /** Retourne la fiche Releve (maître) pour un lot et une année */
    public function findOneByAnneeAndLot(int $annee, Lot $lot): ?Releve
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.annee = :annee')->setParameter('annee', $annee)
            ->andWhere('r.lot = :lot')->setParameter('lot', $lot)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Retourne toutes les fiches Releve d’un lot, triées par année décroissante */
    public function findByLotOrderedByAnnee(Lot $lot): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.lot = :lot')->setParameter('lot', $lot)
            ->orderBy('r.annee', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Retourne l’année max connue pour un lot */
    public function getMaxAnneeForLot(Lot $lot): ?int
    {
        $val = $this->createQueryBuilder('r')
            ->select('MAX(r.annee)')
            ->andWhere('r.lot = :lot')->setParameter('lot', $lot)
            ->getQuery()
            ->getSingleScalarResult();

        return $val !== null ? (int)$val : null;
    }

    /**
     * Retourne toutes les années présentes dans les relevés, triées par ordre croissant.
     *
     * @return int[]
     */
    public function findDistinctAnnees(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT r.annee AS annee')
            ->orderBy('r.annee', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): int => (int) $row['annee'],
            $rows
        );
    }
}
