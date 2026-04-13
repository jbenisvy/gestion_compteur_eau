<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StatsPivotPreset;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class StatsPivotPresetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatsPivotPreset::class);
    }

    /**
     * @return StatsPivotPreset[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndName(User $user, string $name): ?StatsPivotPreset
    {
        return $this->findOneBy(['user' => $user, 'name' => $name]);
    }
}
