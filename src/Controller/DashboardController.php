<?php

namespace App\Controller;

use App\Entity\Lot;
use App\Entity\LotCoproprietaire;
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
        $lots = $lotRepo->findBy([], ['id' => 'ASC']);
        $sortBy = (string)($request->query->get('sortBy') ?? 'lot');
        $sortDir = strtolower((string)($request->query->get('sortDir') ?? 'asc'));
        if (!in_array($sortBy, ['lot', 'copro', 'etage'], true)) {
            $sortBy = 'lot';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        $this->sortLots($lots, $sortBy, $sortDir);

        return $this->render('dashboard/admin.html.twig', [
            'lots' => $lots,
            'anneeActive' => $anneeActive,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    /** @param array<int,Lot> $lots */
    private function sortLots(array &$lots, string $sortBy, string $sortDir): void
    {
        $direction = $sortDir === 'desc' ? -1 : 1;
        usort($lots, function (Lot $a, Lot $b) use ($sortBy, $direction): int {
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
}
