<?php
declare(strict_types=1);

namespace App\Doctrine;

use App\Domain\Consommation\ForfaitConsommationResolver;
use App\Domain\Consommation\IndexVirtuelCalculator;
use App\Entity\Compteur;
use App\Entity\EtatCompteur;
use App\Entity\ReleveItem;
use App\Repository\ParametreRepository;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

final class ReleveItemConsumptionSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly ParametreRepository $parametreRepository,
        private readonly ForfaitConsommationResolver $forfaitResolver,
        private readonly IndexVirtuelCalculator $indexVirtuelCalculator,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ReleveItem) {
            return;
        }

        $this->refreshComputedFields($args->getObjectManager(), $entity);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ReleveItem) {
            return;
        }

        $em = $args->getObjectManager();
        $this->refreshComputedFields($em, $entity);
        $uow = $em->getUnitOfWork();
        $meta = $em->getClassMetadata(ReleveItem::class);
        $uow->recomputeSingleEntityChangeSet($meta, $entity);
    }

    private function refreshComputedFields(EntityManagerInterface $entityManager, ReleveItem $item): void
    {
        $compteur = $item->getCompteur();
        $releve = $item->getReleve();
        if (!$compteur instanceof Compteur || $releve === null) {
            return;
        }

        $etatCode = $this->resolveEtatCode($entityManager, $item->getEtatId());
        $forfaits = $this->parametreRepository->getForfaitsForYear((int) $releve->getAnnee());
        $lotCompteurs = $compteur->getLot() !== null
            ? $entityManager->getRepository(Compteur::class)->findBy(['lot' => $compteur->getLot()])
            : [];

        $prev = (int) ($item->getIndexN1() ?? 0);
        $indexN = $item->getIndexN();
        $indexDemonte = $item->getIndexCompteurDemonté();
        $indexNouveau = $item->getIndexNouveauCompteur();

        $isForfaitLike = $etatCode !== null && (
            str_contains($etatCode, 'forfait')
            || str_contains($etatCode, 'bloqu')
            || str_contains($etatCode, 'non communiqu')
            || str_contains($etatCode, 'index compteur non')
        );
        $isNouveauCompteur = $etatCode !== null && str_contains($etatCode, 'nouveau');
        $isRemplacement = $etatCode !== null && (
            str_contains($etatCode, 'remplac')
            || str_contains($etatCode, 'démont')
            || str_contains($etatCode, 'demonte')
        );

        if ($etatCode !== null && str_contains($etatCode, 'suppr')) {
            $cons = 0;
        } elseif ($isForfaitLike) {
            $cons = (int) round($this->forfaitResolver->resolveForCompteur($compteur, $forfaits, $lotCompteurs));
            if ($item->getIndexN() === null) {
                $item->setIndexN($item->getIndexN1());
            }
        } elseif ($isNouveauCompteur) {
            $ancienActif = (int) ($indexDemonte ?? 0) > $prev;
            $cons = $ancienActif
                ? max(0, (int) ($indexDemonte ?? 0) - $prev) + max(0, (int) ($indexNouveau ?? $indexN ?? 0))
                : max(0, (int) ($indexNouveau ?? $indexN ?? 0));
        } elseif ($isRemplacement) {
            $cons = max(0, (int) ($indexDemonte ?? 0) - $prev) + max(0, (int) ($indexNouveau ?? $indexN ?? 0));
        } else {
            $curr = (int) ($indexN ?? 0);
            $cons = $curr < $prev ? max(0, $curr) : max(0, $curr - $prev);
        }

        $item->setForfait($isForfaitLike);
        $item->setConsommation(number_format($cons, 3, '.', ''));
        $item->setIndexVirtuel($this->indexVirtuelCalculator->calculate(
            $item,
            $cons,
            $etatCode,
            $this->sumForfaitsForCompteurBeforeYear($entityManager, $compteur, $lotCompteurs, (int)$releve->getAnnee(), $item->getId())
        ));
        $item->setUpdatedAt(new \DateTimeImmutable());
    }

    /**
     * @param array<int, Compteur> $lotCompteurs
     */
    private function sumForfaitsForCompteurBeforeYear(EntityManagerInterface $entityManager, Compteur $compteur, array $lotCompteurs, int $annee, ?int $excludeItemId = null): int
    {
        $sql = '
            SELECT ri.id, r.annee, ri.consommation
            FROM releve_item ri
            INNER JOIN releve_new r ON r.id = ri.releve_id
            WHERE ri.compteur_id = :compteurId
              AND r.annee < :annee
              AND ri.forfait = 1
        ';
        $params = ['compteurId' => (int)$compteur->getId(), 'annee' => $annee];
        if ($excludeItemId !== null) {
            $sql .= ' AND ri.id <> :excludeItemId';
            $params['excludeItemId'] = $excludeItemId;
        }

        $total = 0;
        foreach ($entityManager->getConnection()->fetchAllAssociative($sql, $params) as $row) {
            $saved = isset($row['consommation']) && is_numeric($row['consommation'])
                ? (int)round((float)$row['consommation'])
                : 0;
            if ($saved > 0) {
                $total += $saved;
                continue;
            }

            $forfaits = $this->parametreRepository->getForfaitsForYear((int)$row['annee']);
            $total += (int)round($this->forfaitResolver->resolveForCompteur($compteur, $forfaits, $lotCompteurs));
        }

        return $total;
    }

    private function resolveEtatCode(EntityManagerInterface $entityManager, ?int $etatId): ?string
    {
        if ($etatId === null) {
            return null;
        }

        $etat = $entityManager->getRepository(EtatCompteur::class)->find($etatId);
        if (!$etat instanceof EtatCompteur) {
            return null;
        }

        $merged = trim($etat->getCode() . ' ' . $etat->getLibelle());

        return $merged !== '' ? mb_strtolower($merged) : null;
    }
}
