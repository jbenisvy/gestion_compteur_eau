<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\CoproprietaireRepository;
use App\Repository\LotCoproprietaireRepository;
use App\Repository\LotRepository;
use App\Repository\ReleveRepository;
use App\Service\Facturation\FacturationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class FacturationController extends AbstractController
{
    #[Route('/facturation', name: 'copro_facturation')]
    #[IsGranted('ROLE_USER')]
    public function copro(
        Request $request,
        CoproprietaireRepository $coproRepo,
        LotCoproprietaireRepository $lotCoproRepo,
        ReleveRepository $releveRepo,
        FacturationService $facturationService
    ): Response {
        $user = $this->getUser();
        $copro = $user ? $coproRepo->findOneBy(['user' => $user]) : null;
        if (!$copro) {
            throw $this->createNotFoundException('Copropriétaire introuvable.');
        }

        $filters = $this->extractFilters($request);
        $payload = $facturationService->build($filters, (int)$copro->getId());

        return $this->render('facturation/index.html.twig', [
            'title' => 'Ma facturation',
            'isAdmin' => false,
            'payload' => $payload,
            'years' => $this->availableYears($releveRepo),
            'copros' => [],
            'lots' => $this->extractLotsFromEntries($lotCoproRepo->findActiveLotsForCopro($copro, new \DateTimeImmutable('today'))),
        ]);
    }

    #[Route('/admin/facturation', name: 'admin_facturation')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(
        Request $request,
        CoproprietaireRepository $coproRepo,
        LotRepository $lotRepo,
        ReleveRepository $releveRepo,
        FacturationService $facturationService
    ): Response {
        $filters = $this->extractFilters($request, true);
        $payload = $facturationService->build($filters);

        return $this->render('facturation/index.html.twig', [
            'title' => 'État de facturation',
            'isAdmin' => true,
            'payload' => $payload,
            'years' => $this->availableYears($releveRepo),
            'copros' => $coproRepo->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']),
            'lots' => $this->sortedLots($lotRepo->findAllForAdminDashboard()),
        ]);
    }

    /**
     * @return array{annee?:int, coproprietaire_id?:int, lot_id?:int, eau?:string, piece?:string, sort?:string}
     */
    private function extractFilters(Request $request, bool $includeCopro = false): array
    {
        $filters = [];

        $annee = $request->query->get('annee');
        if (is_numeric($annee)) {
            $filters['annee'] = (int)$annee;
        }

        $eau = trim((string)$request->query->get('eau', ''));
        if ($eau !== '') {
            $filters['eau'] = $eau;
        }

        $piece = trim((string)$request->query->get('piece', ''));
        if ($piece !== '') {
            $filters['piece'] = $piece;
        }

        $lotId = $request->query->get('lot_id');
        if (is_numeric($lotId)) {
            $filters['lot_id'] = (int)$lotId;
        }

        $sort = trim((string)$request->query->get('sort', ''));
        if (in_array($sort, ['copro', 'lot'], true)) {
            $filters['sort'] = $sort;
        }

        if ($includeCopro) {
            $coproId = $request->query->get('coproprietaire_id');
            if (is_numeric($coproId)) {
                $filters['coproprietaire_id'] = (int)$coproId;
            }
        }

        return $filters;
    }

    /**
     * @param array<int,mixed> $entries
     * @return array<int,\App\Entity\Lot>
     */
    private function extractLotsFromEntries(array $entries): array
    {
        $lots = [];
        foreach ($entries as $entry) {
            if ($entry instanceof \App\Entity\Lot) {
                $lots[$entry->getId()] = $entry;
            } elseif (method_exists($entry, 'getLot') && $entry->getLot() instanceof \App\Entity\Lot) {
                $lot = $entry->getLot();
                $lots[$lot->getId()] = $lot;
            }
        }

        return $this->sortedLots(array_values($lots));
    }

    /**
     * @param array<int,\App\Entity\Lot> $lots
     * @return array<int,\App\Entity\Lot>
     */
    private function sortedLots(array $lots): array
    {
        usort($lots, static fn ($a, $b): int => strnatcasecmp($a->getNumeroLot(), $b->getNumeroLot()));

        return $lots;
    }

    /**
     * @return int[]
     */
    private function availableYears(ReleveRepository $releveRepo): array
    {
        $years = $releveRepo->findDistinctAnnees();
        rsort($years);

        return $years;
    }
}
