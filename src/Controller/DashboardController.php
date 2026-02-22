<?php

namespace App\Controller;

use App\Entity\Lot;
use App\Entity\LotCoproprietaire;
use App\Entity\Coproprietaire;
use App\Repository\CoproprietaireRepository;
use App\Repository\LotCoproprietaireRepository;
use App\Repository\LotRepository;
use App\Repository\ParametreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'copro_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function coproDashboard(
        CoproprietaireRepository $coproRepo,
        LotCoproprietaireRepository $lotCoproRepo,
        ParametreRepository $paramRepo
    ): Response {
        $user = $this->getUser();
        $copro = $user ? $coproRepo->findOneBy(['user' => $user]) : null;
        if (!$copro) {
            throw $this->createNotFoundException('Copropriétaire introuvable.');
        }

        $anneeActive = $paramRepo->getAnneeEnCours((int)date('Y'));
        $activeLinks = $lotCoproRepo->findActiveLotsForCopro($copro, new \DateTimeImmutable('today'));
        $lots = [];
        foreach ($activeLinks as $entry) {
            if ($entry instanceof Lot) {
                $lots[] = $entry;
            } elseif ($entry instanceof LotCoproprietaire && $entry->getLot() instanceof Lot) {
                $lots[] = $entry->getLot();
            }
        }
        $lots = $this->uniqueLotsById($lots);
        usort($lots, fn (Lot $a, Lot $b): int => $this->compareLotsByNumero($a, $b));

        return $this->render('dashboard/copro.html.twig', [
            'copro' => $copro,
            'lots' => $lots,
            'anneeActive' => $anneeActive,
        ]);
    }

    #[Route('/admin', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(
        Request $request,
        LotRepository $lotRepo,
        ParametreRepository $paramRepo
    ): Response {
        $anneeActive = $paramRepo->getAnneeEnCours((int)date('Y'));
        $lots = $lotRepo->findAllForAdminDashboard();
        $today = new \DateTimeImmutable('today');
        $activeCoproNames = [];
        foreach ($lots as $lot) {
            $activeCoproNames[$lot->getId()] = $this->resolveActiveCoproName($lot, $today);
        }

        $sortBy = (string)($request->query->get('sortBy') ?? 'lot');
        $sortDir = strtolower((string)($request->query->get('sortDir') ?? 'asc'));
        if (!in_array($sortBy, ['lot', 'copro', 'etage'], true)) {
            $sortBy = 'lot';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        $this->sortLots($lots, $sortBy, $sortDir, $activeCoproNames);

        return $this->render('dashboard/admin.html.twig', [
            'lots' => $lots,
            'activeCoproNames' => $activeCoproNames,
            'anneeActive' => $anneeActive,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    /** @param array<int,Lot> $lots */
    private function sortLots(array &$lots, string $sortBy, string $sortDir, array $activeCoproNames = []): void
    {
        $direction = $sortDir === 'desc' ? -1 : 1;
        usort($lots, function (Lot $a, Lot $b) use ($sortBy, $direction, $activeCoproNames): int {
            if ($sortBy === 'copro') {
                $aValue = mb_strtolower((string)($activeCoproNames[$a->getId()] ?? ''));
                $bValue = mb_strtolower((string)($activeCoproNames[$b->getId()] ?? ''));
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
}
