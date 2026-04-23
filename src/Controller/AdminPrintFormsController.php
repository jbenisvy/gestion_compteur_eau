<?php

namespace App\Controller;

use App\Entity\Compteur;
use App\Entity\Coproprietaire;
use App\Entity\Lot;
use App\Entity\LotCoproprietaire;
use App\Entity\ReleveItem;
use App\Repository\CompteurRepository;
use App\Repository\EtatCompteurRepository;
use App\Repository\LotRepository;
use App\Repository\ParametreRepository;
use App\Repository\ReleveRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminPrintFormsController extends AbstractController
{
    #[Route('/admin/impression/fiches', name: 'admin_print_forms_select', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function select(
        LotRepository $lotRepo,
        ParametreRepository $paramRepo
    ): Response {
        $rows = $this->buildLotRows($lotRepo->findAllForAdminDashboard(), new \DateTimeImmutable('today'));
        $anneeActive = $paramRepo->getAnneeEnCours((int) date('Y')) ?? (int) date('Y');

        return $this->render('admin/print_forms_select.html.twig', [
            'rows' => $rows,
            'anneeActive' => $anneeActive,
        ]);
    }

    #[Route('/admin/impression/fiches/apercu', name: 'admin_print_forms', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function preview(
        Request $request,
        LotRepository $lotRepo,
        ParametreRepository $paramRepo,
        CompteurRepository $compteurRepo,
        ReleveRepository $releveRepo,
        EtatCompteurRepository $etatRepo
    ): Response {
        $today = new \DateTimeImmutable('today');
        $anneeActive = $paramRepo->getAnneeEnCours((int) date('Y')) ?? (int) date('Y');
        $annee = (int) ($request->query->get('annee', $anneeActive) ?? $anneeActive);
        if ($annee < 2000 || $annee > 2100) {
            $annee = $anneeActive;
        }
        $forfaitsAnnee = $paramRepo->getForfaitsForYear($annee);

        $scope = (string) ($request->query->get('scope', 'all') ?? 'all');
        $selectedIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $request->query->all('lotIds')
        ), static fn (int $id): bool => $id > 0)));

        $allLots = $lotRepo->findAllForAdminDashboard();
        $rows = $this->buildLotRows($allLots, $today);
        $selectedRows = [];
        if ($scope === 'selection') {
            $selectedRows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => in_array($row['lot']->getId(), $selectedIds, true)
            ));
        } else {
            $selectedRows = $rows;
        }

        $etatCodeById = [];
        $etatLabelById = [];
        foreach ($etatRepo->findAll() as $etat) {
            if ($etat->getId() !== null) {
                $etatCodeById[$etat->getId()] = mb_strtolower((string) $etat->getCode());
                $etatLabelById[$etat->getId()] = trim((string) $etat->getLibelle());
            }
        }

        $fiches = [];
        foreach ($selectedRows as $row) {
            /** @var Lot $lot */
            $lot = $row['lot'];

            $compteurs = $compteurRepo->createQueryBuilder('c')
                ->leftJoin('c.etatCompteur', 'e')->addSelect('e')
                ->andWhere('c.lot = :lot')->setParameter('lot', $lot)
                ->orderBy('c.id', 'ASC')
                ->getQuery()
                ->getResult();

            $relevePrev = $releveRepo->findOneByAnneeAndLot($annee - 1, $lot);
            $prevByCompteurId = [];
            if ($relevePrev) {
                foreach ($relevePrev->getItems() as $item) {
                    $compteur = $item->getCompteur();
                    if ($compteur && $compteur->getId() !== null) {
                        $prevByCompteurId[$compteur->getId()] = $item;
                    }
                }
            }

            $releveCurr = $releveRepo->findOneByAnneeAndLot($annee, $lot);
            $currByCompteurId = [];
            if ($releveCurr) {
                foreach ($releveCurr->getItems() as $item) {
                    $compteur = $item->getCompteur();
                    if ($compteur && $compteur->getId() !== null) {
                        $currByCompteurId[$compteur->getId()] = $item;
                    }
                }
            }

            $slots = [
                'cuisine_chaude' => ['indexN1' => null, 'etatN1' => null, 'description' => null, 'supprime' => false],
                'sdb_chaude' => ['indexN1' => null, 'etatN1' => null, 'description' => null, 'supprime' => false],
                'cuisine_froide' => ['indexN1' => null, 'etatN1' => null, 'description' => null, 'supprime' => false],
                'sdb_froide' => ['indexN1' => null, 'etatN1' => null, 'description' => null, 'supprime' => false],
            ];

            foreach ($compteurs as $compteur) {
                if (!$compteur instanceof Compteur || $compteur->getId() === null) {
                    continue;
                }

                $slotKey = $this->resolveSlotKey($compteur);
                if ($slotKey === null || $slots[$slotKey]['description'] !== null) {
                    continue;
                }

                $prevItem = $prevByCompteurId[$compteur->getId()] ?? null;
                $currItem = $currByCompteurId[$compteur->getId()] ?? null;
                $isSupprime = $this->isSlotSupprime($prevItem, $currItem, $compteur, $etatCodeById);
                $slots[$slotKey] = [
                    'indexN1' => $isSupprime ? null : $this->resolveIndexN1($prevItem, $currItem, $etatCodeById),
                    'etatN1' => $this->resolveEtatN1($prevItem, $currItem, $compteur, $etatLabelById, $etatCodeById),
                    'description' => $this->formatCompteurDescription($compteur),
                    'supprime' => $isSupprime,
                ];
            }

            $fiches[] = [
                'lot' => $lot,
                'coproName' => $row['coproName'],
                'descriptionLot' => trim((string) $lot->getTypeAppartement()) !== '' ? $lot->getTypeAppartement() : null,
                'slots' => $slots,
            ];
        }

        return $this->render('admin/print_forms.html.twig', [
            'annee' => $annee,
            'forfaits' => $forfaitsAnnee,
            'fiches' => $fiches,
            'scope' => $scope,
        ]);
    }

    /**
     * @param Lot[] $lots
     * @return array<int,array{lot:Lot,coproName:?string}>
     */
    private function buildLotRows(array $lots, \DateTimeInterface $date): array
    {
        usort($lots, fn (Lot $a, Lot $b): int => $this->compareLotsByNumero($a, $b));

        $rows = [];
        foreach ($lots as $lot) {
            $rows[] = [
                'lot' => $lot,
                'coproName' => $this->resolveActiveCoproName($lot, $date),
            ];
        }

        return $rows;
    }

    private function compareLotsByNumero(Lot $a, Lot $b): int
    {
        $cmp = strnatcasecmp(trim((string) $a->getNumeroLot()), trim((string) $b->getNumeroLot()));
        if ($cmp !== 0) {
            return $cmp;
        }

        return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
    }

    private function resolveActiveCoproName(Lot $lot, \DateTimeInterface $date): ?string
    {
        $activeLink = null;
        foreach ($lot->getCoproprietaires() as $link) {
            if (!$link->isActiveAt($date)) {
                continue;
            }

            if (
                $activeLink === null
                || $link->getDateDebut() > $activeLink->getDateDebut()
                || (
                    $link->getDateDebut() == $activeLink->getDateDebut()
                    && $link->getId() !== null
                    && $activeLink->getId() !== null
                    && $link->getId() > $activeLink->getId()
                )
            ) {
                $activeLink = $link;
            }
        }

        $copro = $activeLink?->getCoproprietaire() ?? $lot->getCoproprietaire($date);
        if (!$copro instanceof Coproprietaire) {
            return null;
        }

        $name = trim($copro->getNomComplet());
        return $name !== '' ? $name : null;
    }

    private function resolveSlotKey(Compteur $compteur): ?string
    {
        $emp = mb_strtolower((string) $compteur->getEmplacement());
        $empNorm = preg_replace('/[^a-z0-9]+/u', ' ', $emp);

        $piece = null;
        if (preg_match('/\b(cuis|cuisine)\b/u', $empNorm)) {
            $piece = 'cuisine';
        } elseif (preg_match('/\b(sdb|salle\s*de\s*bains?|salle\s*d[\'’]?eau|sde|wc)\b/u', $empNorm)) {
            $piece = 'sdb';
        }

        $eau = null;
        if (preg_match('/\b(e\s*c|eau\s*chaud[e]?|chaud[e]?|hot)\b/u', $empNorm)) {
            $eau = 'chaude';
        } elseif (preg_match('/\b(e\s*f|eau\s*froid[e]?|froid[e]?|cold)\b/u', $empNorm)) {
            $eau = 'froide';
        } else {
            $eau = $compteur->getType() === 'EC' ? 'chaude' : 'froide';
        }

        if ($piece === null || $eau === null) {
            return null;
        }

        return $piece . '_' . $eau;
    }

    private function resolveIndexN1(?ReleveItem $prevItem, ?ReleveItem $currItem, array $etatCodeById): ?int
    {
        if ($prevItem instanceof ReleveItem) {
            $etatId = $prevItem->getEtatId();
            $etatCode = $etatId !== null ? ($etatCodeById[$etatId] ?? '') : '';
            if ($etatCode === 'remplace') {
                return $prevItem->getIndexNouveauCompteur();
            }

            return $prevItem->getIndexN();
        }

        if ($currItem instanceof ReleveItem) {
            return $currItem->getIndexN1();
        }

        return null;
    }

    private function isSlotSupprime(?ReleveItem $prevItem, ?ReleveItem $currItem, Compteur $compteur, array $etatCodeById): bool
    {
        foreach ([$currItem, $prevItem] as $item) {
            if (!$item instanceof ReleveItem) {
                continue;
            }

            $etatId = $item->getEtatId();
            $etatCode = $etatId !== null ? (string)($etatCodeById[$etatId] ?? '') : '';
            if ($this->isSuppressionCode($etatCode)) {
                return true;
            }
        }

        $etat = $compteur->getEtatCompteur();
        $etatCode = $etat !== null ? $etat->getCode() . ' ' . $etat->getLibelle() : '';

        return $this->isSuppressionCode($etatCode);
    }

    private function isSuppressionCode(string $etatCode): bool
    {
        $etatCode = mb_strtolower(trim($etatCode));
        return $etatCode !== '' && (str_contains($etatCode, 'supprim') || str_contains($etatCode, 'suppr'));
    }

    private function resolveEtatN1(
        ?ReleveItem $prevItem,
        ?ReleveItem $currItem,
        Compteur $compteur,
        array $etatLabelById,
        array $etatCodeById
    ): ?string
    {
        if ($prevItem instanceof ReleveItem) {
            $label = $this->resolveEtatLabelFromId($prevItem->getEtatId(), $etatLabelById, $etatCodeById);
            if ($label !== null) {
                return $label;
            }
        }

        if ($currItem instanceof ReleveItem) {
            $label = $this->resolveEtatLabelFromId($currItem->getEtatId(), $etatLabelById, $etatCodeById);
            if ($label !== null) {
                return $label;
            }
        }

        $compteurEtat = $compteur->getEtatCompteur();
        if ($compteurEtat !== null) {
            $label = trim((string) $compteurEtat->getLibelle());
            if ($label !== '') {
                return $label;
            }

            $code = trim((string) $compteurEtat->getCode());
            if ($code !== '') {
                return ucfirst(str_replace('_', ' ', $code));
            }
        }

        return null;
    }

    private function resolveEtatLabelFromId(?int $etatId, array $etatLabelById, array $etatCodeById): ?string
    {
        if ($etatId === null) {
            return null;
        }

        $label = trim((string) ($etatLabelById[$etatId] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $code = trim((string) ($etatCodeById[$etatId] ?? ''));
        if ($code === '') {
            return null;
        }

        return ucfirst(str_replace('_', ' ', $code));
    }

    private function formatCompteurDescription(Compteur $compteur): string
    {
        $parts = [];
        $emplacement = trim((string) $compteur->getEmplacement());
        if ($emplacement !== '') {
            $parts[] = $emplacement;
        }
        $numero = trim((string) ($compteur->getNumeroSerie() ?? ''));
        if ($numero !== '') {
            $parts[] = 'N° ' . $numero;
        }

        return implode(' - ', $parts);
    }
}
