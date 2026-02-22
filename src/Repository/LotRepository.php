<?php

namespace App\Repository;

use App\Entity\Lot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lot>
 */
class LotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lot::class);
    }

    /**
     * @return Lot[]
     */
    public function findAllForAdminDashboard(): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.coproprietaires', 'lc')->addSelect('lc')
            ->leftJoin('lc.coproprietaire', 'c')->addSelect('c')
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
