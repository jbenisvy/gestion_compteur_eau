<?php

namespace App\Repository;

use App\Entity\Parametre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ParametreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parametre::class);
    }

    /**
     * Retourne l'année en cours depuis la table `parametre`.
     * Priorité:
     * 1) activeSaisieYear explicite (si défini)
     * 2) plus grande année renseignée (fallback historique)
     */
    public function getAnneeEnCours(?int $fallback = null): ?int
    {
        $explicit = $this->createQueryBuilder('p')
            ->andWhere('p.activeSaisieYear IS NOT NULL')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($explicit instanceof Parametre && $explicit->getActiveSaisieYear() !== null) {
            return (int)$explicit->getActiveSaisieYear();
        }

        $max = $this->createQueryBuilder('p')
            ->select('MAX(p.annee)')
            ->andWhere('p.annee IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        if ($max === null) {
            return $fallback;
        }

        return (int) $max;
    }

    /**
     * (bonus) Récupère les forfaits s’ils te servent ailleurs.
     */
    public function getForfaits(): array
    {
        $row = $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$row) {
            return ['ef' => null, 'ec' => null];
        }

        $ef = \is_callable([$row, 'getForfaitEf']) ? $row->getForfaitEf() : null;
        $ec = \is_callable([$row, 'getForfaitEc']) ? $row->getForfaitEc() : null;

        return ['ef' => $ef, 'ec' => $ec];
    }

    /**
     * Retourne les forfaits à appliquer pour une année donnée.
     * Priorité:
     * 1) ligne avec annee = $annee
     * 2) ligne par défaut (annee NULL)
     * 3) fallback codé en dur
     */
    public function getForfaitsForYear(int $annee): array
    {
        $row = $this->findOneBy(['annee' => $annee]);
        if ($row instanceof Parametre) {
            return ['ef' => (float)$row->getForfaitEf(), 'ec' => (float)$row->getForfaitEc()];
        }

        $default = $this->findOneBy(['annee' => null], ['id' => 'DESC']);
        if ($default instanceof Parametre) {
            return ['ef' => (float)$default->getForfaitEf(), 'ec' => (float)$default->getForfaitEc()];
        }

        return ['ef' => 150.0, 'ec' => 75.0];
    }

    public function getLatestDefault(): ?Parametre
    {
        return $this->findOneBy(['annee' => null], ['id' => 'DESC']);
    }
}
