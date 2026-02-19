<?php

namespace App\Controller;

use App\Entity\Coproprietaire;
use App\Entity\Lot;
use App\Entity\LotCoproprietaire;
use App\Repository\CompteurRepository;
use App\Repository\EtatCompteurRepository;
use App\Repository\ReleveRepository;
use App\Repository\CoproprietaireRepository;
use App\Repository\LotCoproprietaireRepository;
use App\Repository\LotRepository;
use App\Repository\ParametreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HistoriqueController extends AbstractController
{
    #[Route('/historique', name: 'copro_historique')]
    #[IsGranted('ROLE_USER')]
    public function historiqueCopro(
        CoproprietaireRepository $coproRepo,
        LotCoproprietaireRepository $lotCoproRepo,
        CompteurRepository $compteurRepo,
        EtatCompteurRepository $etatRepo,
        ReleveRepository $releveRepo,
        ParametreRepository $paramRepo
    ): Response {
        $user = $this->getUser();
        $copro = $user ? $coproRepo->findOneBy(['user' => $user]) : null;
        if (!$copro) {
            throw $this->createNotFoundException('Copropriétaire introuvable.');
        }

        $entries = $lotCoproRepo->findActiveLotsForCopro($copro, new \DateTimeImmutable('today'));
        $lots = [];
        foreach ($entries as $entry) {
            if ($entry instanceof Lot) {
                $lots[] = $entry;
            } elseif ($entry instanceof LotCoproprietaire && $entry->getLot() instanceof Lot) {
                $lots[] = $entry->getLot();
            }
        }
        $lots = $this->uniqueLotsById($lots);
        usort($lots, fn (Lot $a, Lot $b): int => $this->compareLotsByNumero($a, $b));
        $lot = $lots[0] ?? null;
        if (!$lot) {
            throw $this->createNotFoundException('Aucun lot actif pour ce copropriétaire.');
        }

        $allowedYears = $this->computeAllowedYearsForCoproOnLot($copro, $lot, $releveRepo);

        return $this->renderHistorique($lot, $compteurRepo, $etatRepo, $releveRepo, $paramRepo, [
            'copro' => $copro,
            'lot' => $lot,
            'title' => 'Historique de consommation',
            'isAdmin' => false,
            'allowedYears' => $allowedYears,
        ]);
    }

    #[Route('/admin/historique', name: 'admin_historique')]
    #[IsGranted('ROLE_ADMIN')]
    public function historiqueAdmin(
        Request $request,
        LotRepository $lotRepo,
        CompteurRepository $compteurRepo,
        EtatCompteurRepository $etatRepo,
        ReleveRepository $releveRepo,
        ParametreRepository $paramRepo
    ): Response {
        $lotId = (int)($request->query->get('lotId') ?? 0);
        $sortBy = (string)($request->query->get('sortBy') ?? 'lot');
        $sortDir = strtolower((string)($request->query->get('sortDir') ?? 'asc'));
        if (!in_array($sortBy, ['lot', 'copro', 'etage'], true)) {
            $sortBy = 'lot';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        $lot = $lotId > 0 ? $lotRepo->find($lotId) : null;

        if (!$lot) {
            $lots = $lotRepo->findBy([], ['id' => 'ASC']);
            $this->sortLots($lots, $sortBy, $sortDir);
            return $this->render('historique/admin_select.html.twig', [
                'lots' => $lots,
                'sortBy' => $sortBy,
                'sortDir' => $sortDir,
            ]);
        }

        return $this->renderHistorique($lot, $compteurRepo, $etatRepo, $releveRepo, $paramRepo, [
            'lot' => $lot,
            'title' => 'Historique global',
            'isAdmin' => true,
        ]);
    }

    /** @param array<int,\App\Entity\Lot> $lots */
    private function sortLots(array &$lots, string $sortBy, string $sortDir): void
    {
        $direction = $sortDir === 'desc' ? -1 : 1;
        usort($lots, function (\App\Entity\Lot $a, \App\Entity\Lot $b) use ($sortBy, $direction): int {
            if ($sortBy === 'copro') {
                $aValue = mb_strtolower($a->getCoproprietaire()?->getNomComplet() ?? '');
                $bValue = mb_strtolower($b->getCoproprietaire()?->getNomComplet() ?? '');
                $cmp = $aValue <=> $bValue;
            } elseif ($sortBy === 'etage') {
                $aValue = (string)$this->extractFloor($a->getEmplacement());
                $bValue = (string)$this->extractFloor($b->getEmplacement());
                $cmp = $aValue <=> $bValue;
            } else {
                $cmp = $this->compareLotsByNumero($a, $b);
            }
            return $cmp * $direction;
        });
    }

    private function compareLotsByNumero(Lot $a, Lot $b): int
    {
        $cmp = strnatcasecmp(trim((string) $a->getNumeroLot()), trim((string) $b->getNumeroLot()));
        if ($cmp !== 0) {
            return $cmp;
        }

        return $a->getId() <=> $b->getId();
    }

    /**
     * @param array<int,Lot> $lots
     * @return array<int,Lot>
     */
    private function uniqueLotsById(array $lots): array
    {
        $unique = [];
        foreach ($lots as $lot) {
            $unique[$lot->getId()] = $lot;
        }

        return array_values($unique);
    }

    private function extractFloor(string $emplacement): int
    {
        if (preg_match('/(\d+)/', $emplacement, $m)) {
            return (int)$m[1];
        }
        return 999;
    }

    #[Route('/historique-demo', name: 'historique_demo')]
    public function historiqueDemo(
        CoproprietaireRepository $coproRepo,
        CompteurRepository $compteurRepo,
        EtatCompteurRepository $etatRepo,
        ReleveRepository $releveRepo
    ): Response {
        // Choisir "Alice Martin"
        $copro = $coproRepo->findOneBy(['nom' => 'Martin', 'prenom' => 'Alice']);
        if (!$copro) {
            // fallback: premier copro dispo
            $copro = $coproRepo->findOneBy([]) ?? null;
        }
        if (!$copro) {
            throw $this->createNotFoundException('Aucun copropriétaire trouvé.');
        }

        // Son lot actif (ou le premier historique)
        $lot = null;
        $links = method_exists($copro, 'getLots') ? $copro->getLots() : [];
        foreach ($links as $link) {
            if (method_exists($link, 'isActiveAt') && $link->isActiveAt(new \DateTimeImmutable('today'))) {
                $lot = $link->getLot();
                break;
            }
            if (!$lot && method_exists($link, 'getLot')) {
                $lot = $link->getLot();
            }
        }
        if (!$lot) {
            throw $this->createNotFoundException('Aucun lot pour ce copropriétaire.');
        }

        // Relevés par compteur et par année
        $compteurs = $compteurRepo->findBy(['lot' => $lot], ['id' => 'ASC']);
        $years = range(2018, 2024);
        $rows = []; // [compteurId => [annee => index]]

        $etatMap = [];
        foreach ($etatRepo->findAll() as $etat) {
            $etatMap[$etat->getId()] = $etat;
        }

        $releves = $releveRepo->findByLotOrderedByAnnee($lot);
        $relevesByYear = [];
        foreach ($releves as $r) {
            $relevesByYear[$r->getAnnee()] = $r;
        }

        foreach ($compteurs as $cmp) {
            foreach ($years as $y) {
                $idx = null;
                $r = $relevesByYear[$y] ?? null;
                if ($r) {
                    foreach ($r->getItems() as $item) {
                        if ($item->getCompteur() && $item->getCompteur()->getId() === $cmp->getId()) {
                            $idx = $item->getIndexN();
                            break;
                        }
                    }
                }
                $rows[$cmp->getId()]['numero'] = method_exists($cmp,'getNumeroSerie') ? ($cmp->getNumeroSerie() ?: $cmp->getId()) : $cmp->getId();
                $rows[$cmp->getId()]['piece']  = method_exists($cmp,'getEmplacement') ? $cmp->getEmplacement() : '';
                $rows[$cmp->getId()]['type']   = method_exists($cmp,'getType') ? $cmp->getType() : '';
                $rows[$cmp->getId()][$y] = $idx;
            }
        }

        // Consommations annuelles par compteur (N - N-1) avec règles :
        // - supprimé => conso 0
        // - forfait  => conso 0 (calcul)
        // - remplacé => (pour l’instant on garde N - N-1 ; on affinera quand tu auras l’info "démonté/nouveau")
        $consos = []; // [annee => total_par_annee]
        foreach ($years as $pos => $y) {
            if ($y === 2018) continue; // pas de N-1 pour 2018
            $total = 0;
            foreach ($compteurs as $cmp) {
                $n   = $rows[$cmp->getId()][$y] ?? null;
                $n1  = $rows[$cmp->getId()][$y-1] ?? null;
                $delta = (is_numeric($n) && is_numeric($n1)) ? max(0, $n - $n1) : 0;

                $etatCode = null;
                $r = $relevesByYear[$y] ?? null;
                if ($r) {
                    foreach ($r->getItems() as $item) {
                        if ($item->getCompteur() && $item->getCompteur()->getId() === $cmp->getId()) {
                            $etatId = $item->getEtatId();
                            $etatCode = $etatId !== null && isset($etatMap[$etatId]) ? $etatMap[$etatId]->getCode() : null;
                            break;
                        }
                    }
                }

                if ($etatCode === 'supprime' || $etatCode === 'forfait') {
                    $delta = 0;
                }
                $total += $delta;
            }
            $consos[$y] = $total;
        }

        return $this->render('historique/copro_history.html.twig', [
            'copro' => $copro,
            'lot' => $lot,
            'years' => $years,
            'rows' => $rows,
            'consos' => $consos,
        ]);
    }

    private function renderHistorique(
        \App\Entity\Lot $lot,
        CompteurRepository $compteurRepo,
        EtatCompteurRepository $etatRepo,
        ReleveRepository $releveRepo,
        ParametreRepository $paramRepo,
        array $context
    ): Response {
        $compteurs = $compteurRepo->findBy(['lot' => $lot], ['id' => 'ASC']);
        $releves = $releveRepo->findByLotOrderedByAnnee($lot);

        $relevesByYear = [];
        foreach ($releves as $r) {
            $relevesByYear[$r->getAnnee()] = $r;
        }

        $allYears = array_keys($relevesByYear);
        sort($allYears);
        $years = $allYears;
        if (isset($context['allowedYears']) && is_array($context['allowedYears']) && $context['allowedYears'] !== []) {
            $years = array_values(array_intersect($years, $context['allowedYears']));
            sort($years);
        }

        $etatMap = [];
        foreach ($etatRepo->findAll() as $etat) {
            $etatMap[$etat->getId()] = $etat;
        }

        $rows = [];
        foreach ($compteurs as $cmp) {
            foreach ($allYears as $y) {
                $idx = null;
                $r = $relevesByYear[$y] ?? null;
                if ($r) {
                    foreach ($r->getItems() as $item) {
                        if ($item->getCompteur() && $item->getCompteur()->getId() === $cmp->getId()) {
                            $idx = $item->getIndexN();
                            break;
                        }
                    }
                }
                $rows[$cmp->getId()]['numero'] = $cmp->getNumeroSerie() ?: $cmp->getId();
                $rows[$cmp->getId()]['piece'] = $cmp->getEmplacement();
                $rows[$cmp->getId()]['type'] = $cmp->getType();
                $rows[$cmp->getId()][$y] = $idx;
            }
        }

        $consos = [];
        $consosByCompteur = [];
        $forfaitByCompteur = [];
        $forfaitValueByCompteur = [];
        $forfaitCountByYear = [];
        $forfaitTotalByYear = [];
        foreach ($allYears as $pos => $y) {
            if ($pos === 0) {
                continue;
            }
            $forfaitsYear = $paramRepo->getForfaitsForYear($y);
            $total = 0;
            $forfaitCount = 0;
            $forfaitTotal = 0.0;
            foreach ($compteurs as $cmp) {
                $n = $rows[$cmp->getId()][$y] ?? null;
                $prevYear = $allYears[$pos - 1];
                $n1 = $rows[$cmp->getId()][$prevYear] ?? null;
                $delta = (is_numeric($n) && is_numeric($n1)) ? max(0, $n - $n1) : 0;

                $etatCode = null;
                $isForfaitFlag = false;
                $indexCompteurDem = null;
                $indexNouveauCompteur = null;
                $r = $relevesByYear[$y] ?? null;
                if ($r) {
                    foreach ($r->getItems() as $item) {
                        if ($item->getCompteur() && $item->getCompteur()->getId() === $cmp->getId()) {
                            $etatId = $item->getEtatId();
                            $etatCode = $etatId !== null && isset($etatMap[$etatId]) ? $etatMap[$etatId]->getCode() : null;
                            $isForfaitFlag = $item->isForfait();
                            $indexCompteurDem = $item->getIndexCompteurDemonté();
                            $indexNouveauCompteur = $item->getIndexNouveauCompteur();
                            break;
                        }
                    }
                }

                $isRemplacement = in_array($etatCode, ['remplace', 'remplace_sans_date'], true)
                    || $indexCompteurDem !== null
                    || $indexNouveauCompteur !== null;
                if ($isRemplacement) {
                    $oldPart = (is_numeric($indexCompteurDem) && is_numeric($n1))
                        ? max(0, (int)$indexCompteurDem - (int)$n1)
                        : 0;
                    $newPart = is_numeric($indexNouveauCompteur)
                        ? max(0, (int)$indexNouveauCompteur)
                        : (is_numeric($n) ? max(0, (int)$n) : 0);
                    $delta = $oldPart + $newPart;
                }

                $isForfait = $etatCode === 'forfait' || $isForfaitFlag;
                if ($etatCode === 'supprime' || $isForfait) {
                    $delta = 0;
                }
                $forfaitValue = 0.0;
                if ($isForfait) {
                    $forfaitValue = $cmp->getType() === 'EF' ? (float)$forfaitsYear['ef'] : (float)$forfaitsYear['ec'];
                    $delta += $forfaitValue;
                    $forfaitCount++;
                    $forfaitTotal += $forfaitValue;
                }
                $total += $delta;
                $consosByCompteur[$cmp->getId()][$y] = $delta;
                $forfaitByCompteur[$cmp->getId()][$y] = $isForfait;
                $forfaitValueByCompteur[$cmp->getId()][$y] = $forfaitValue;
            }
            $consos[$y] = $total;
            $forfaitCountByYear[$y] = $forfaitCount;
            $forfaitTotalByYear[$y] = $forfaitTotal;
        }

        $ownerSegments = [];
        if (($context['isAdmin'] ?? false) === true) {
            $ownerSegments = $this->buildOwnerSegments(
                $lot,
                $years,
                $rows,
                $consos,
                $consosByCompteur,
                $forfaitByCompteur,
                $forfaitValueByCompteur,
                $forfaitCountByYear,
                $forfaitTotalByYear
            );
        }

        return $this->render('historique/copro_history.html.twig', $context + [
            'years' => $years,
            'rows' => $rows,
            'consos' => $consos,
            'consosByCompteur' => $consosByCompteur,
            'forfaitByCompteur' => $forfaitByCompteur,
            'forfaitValueByCompteur' => $forfaitValueByCompteur,
            'forfaitCountByYear' => $forfaitCountByYear,
            'forfaitTotalByYear' => $forfaitTotalByYear,
            'ownerSegments' => $ownerSegments,
        ]);
    }

    private function buildOwnerSegments(
        \App\Entity\Lot $lot,
        array $years,
        array $rows,
        array $consos,
        array $consosByCompteur,
        array $forfaitByCompteur,
        array $forfaitValueByCompteur,
        array $forfaitCountByYear,
        array $forfaitTotalByYear
    ): array
    {
        if ($years === []) {
            return [];
        }

        sort($years);

        $links = $lot->getCoproprietaires()->toArray();
        usort(
            $links,
            fn($a, $b) => ($a->getDateDebut()?->getTimestamp() ?? 0) <=> ($b->getDateDebut()?->getTimestamp() ?? 0)
        );

        $fallbackCopro = $lot->getCoproprietaire();
        $segments = [];

        $currentCopro = null;
        $currentYears = [];

        foreach ($years as $year) {
            $targetDate = new \DateTimeImmutable(sprintf('%d-12-31', $year));
            $yearCopro = null;
            $bestStartTs = null;

            foreach ($links as $link) {
                if ($link->isActiveAt($targetDate)) {
                    $startTs = $link->getDateDebut()?->getTimestamp() ?? 0;
                    if ($bestStartTs === null || $startTs >= $bestStartTs) {
                        $bestStartTs = $startTs;
                        $yearCopro = $link->getCoproprietaire();
                    }
                }
            }

            if (!$yearCopro) {
                $yearCopro = $fallbackCopro;
            }

            $yearCoproId = $yearCopro?->getId();
            $currentCoproId = $currentCopro?->getId();

            if ($currentYears === []) {
                $currentCopro = $yearCopro;
                $currentYears = [$year];
                continue;
            }

            if ($yearCoproId === $currentCoproId) {
                $currentYears[] = $year;
                continue;
            }

            $segments[] = $this->makeSegment(
                $currentCopro,
                $currentYears,
                $rows,
                $consos,
                $consosByCompteur,
                $forfaitByCompteur,
                $forfaitValueByCompteur,
                $forfaitCountByYear,
                $forfaitTotalByYear
            );
            $currentCopro = $yearCopro;
            $currentYears = [$year];
        }

        if ($currentYears !== []) {
            $segments[] = $this->makeSegment(
                $currentCopro,
                $currentYears,
                $rows,
                $consos,
                $consosByCompteur,
                $forfaitByCompteur,
                $forfaitValueByCompteur,
                $forfaitCountByYear,
                $forfaitTotalByYear
            );
        }

        return $segments;
    }

    private function makeSegment(
        ?Coproprietaire $copro,
        array $segmentYears,
        array $rows,
        array $consos,
        array $consosByCompteur,
        array $forfaitByCompteur,
        array $forfaitValueByCompteur,
        array $forfaitCountByYear,
        array $forfaitTotalByYear
    ): array
    {
        $firstYear = $segmentYears[0];
        $lastYear = $segmentYears[count($segmentYears) - 1];
        $consoYears = array_values(array_filter(
            $segmentYears,
            static fn (int $year): bool => isset($consos[$year])
        ));

        $segmentRows = [];
        foreach ($rows as $cmpId => $data) {
            $entry = [
                'numero' => $data['numero'] ?? '',
                'piece' => $data['piece'] ?? '',
                'type' => $data['type'] ?? '',
            ];
            foreach ($segmentYears as $year) {
                $entry[$year] = $data[$year] ?? null;
            }
            $segmentRows[$cmpId] = $entry;
        }

        $segmentConsos = [];
        foreach ($segmentYears as $year) {
            if (isset($consos[$year])) {
                $segmentConsos[$year] = $consos[$year];
            }
        }

        $segmentConsosByCompteur = [];
        $segmentForfaitByCompteur = [];
        $segmentForfaitValueByCompteur = [];
        foreach ($segmentRows as $cmpId => $_unused) {
            foreach ($segmentYears as $year) {
                if (isset($consosByCompteur[$cmpId][$year])) {
                    $segmentConsosByCompteur[$cmpId][$year] = $consosByCompteur[$cmpId][$year];
                }
                if (isset($forfaitByCompteur[$cmpId][$year])) {
                    $segmentForfaitByCompteur[$cmpId][$year] = $forfaitByCompteur[$cmpId][$year];
                }
                if (isset($forfaitValueByCompteur[$cmpId][$year])) {
                    $segmentForfaitValueByCompteur[$cmpId][$year] = $forfaitValueByCompteur[$cmpId][$year];
                }
            }
        }

        $segmentForfaitCountByYear = [];
        $segmentForfaitTotalByYear = [];
        foreach ($segmentYears as $year) {
            if (isset($forfaitCountByYear[$year])) {
                $segmentForfaitCountByYear[$year] = $forfaitCountByYear[$year];
            }
            if (isset($forfaitTotalByYear[$year])) {
                $segmentForfaitTotalByYear[$year] = $forfaitTotalByYear[$year];
            }
        }

        return [
            'copro' => $copro,
            'coproName' => $copro ? $copro->getNomComplet() : 'Non rattaché',
            'periodLabel' => sprintf('%d à %d', $firstYear, $lastYear),
            'years' => $segmentYears,
            'consoYears' => $consoYears,
            'rows' => $segmentRows,
            'consos' => $segmentConsos,
            'consosByCompteur' => $segmentConsosByCompteur,
            'forfaitByCompteur' => $segmentForfaitByCompteur,
            'forfaitValueByCompteur' => $segmentForfaitValueByCompteur,
            'forfaitCountByYear' => $segmentForfaitCountByYear,
            'forfaitTotalByYear' => $segmentForfaitTotalByYear,
        ];
    }

    private function computeAllowedYearsForCoproOnLot(Coproprietaire $copro, Lot $lot, ReleveRepository $releveRepo): array
    {
        $releves = $releveRepo->findByLotOrderedByAnnee($lot);
        $years = [];
        foreach ($releves as $releve) {
            $years[] = (int)$releve->getAnnee();
        }
        $years = array_values(array_unique($years));
        sort($years);
        if ($years === []) {
            return [];
        }

        $allowed = [];
        $links = $lot->getCoproprietaires();
        foreach ($years as $year) {
            $date = new \DateTimeImmutable(sprintf('%d-12-31', $year));
            foreach ($links as $link) {
                if ($link->getCoproprietaire()?->getId() === $copro->getId() && $link->isActiveAt($date)) {
                    $allowed[] = $year;
                    break;
                }
            }
        }

        return $allowed;
    }
}
