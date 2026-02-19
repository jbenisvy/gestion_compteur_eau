<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Lot;
use App\Repository\LotCoproprietaireRepository;
use App\Repository\LotRepository;
use App\Repository\ReleveRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminReportController extends AbstractController
{
    #[Route('/admin/tableau', name: 'admin_tableau')]
    #[IsGranted('ROLE_ADMIN')]
    public function tableau(
        Request $request,
        LotRepository $lotRepo,
        ReleveRepository $releveRepo,
        LotCoproprietaireRepository $lotCoproRepo
    ): Response {
        $years = $releveRepo->findDistinctAnnees();
        if ($years === []) {
            $years = [(int) date('Y')];
        }

        $selectedYear = (int) $request->query->get('annee', 0);
        if ($selectedYear !== 0 && !in_array($selectedYear, $years, true)) {
            $selectedYear = 0;
        }

        $targetYears = $selectedYear > 0 ? [$selectedYear] : $years;
        sort($targetYears);

        $lots = $lotRepo->findBy([], ['id' => 'ASC']);
        $this->sortLotsByNumero($lots);

        $rowsByYear = [];
        foreach ($targetYears as $year) {
            $rowsByYear[$year] = [];
            // Règle métier: le copro affiché pour une année est celui en place au 1er janvier.
            $dateRef = new \DateTimeImmutable(sprintf('%d-01-01', $year));

            foreach ($lots as $lot) {
                $link = $lotCoproRepo->findActiveCoproForLot($lot, $dateRef);
                $copro = $link?->getCoproprietaire();
                if ($copro === null) {
                    continue;
                }

                $rowsByYear[$year][] = [
                    'lot' => $lot,
                    'coproNom' => $copro->getNomComplet(),
                ];
            }
        }

        return $this->render('admin/tableau.html.twig', [
            'rowsByYear' => $rowsByYear,
            'years' => $years,
            'annee' => $selectedYear,
        ]);
    }

    /**
     * @param array<int,Lot> $lots
     */
    private function sortLotsByNumero(array &$lots): void
    {
        usort($lots, static function (Lot $a, Lot $b): int {
            return strnatcasecmp($a->getNumeroLot(), $b->getNumeroLot());
        });
    }
}
