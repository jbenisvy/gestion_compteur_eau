<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\CoproprietaireRepository;
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
        ]);
    }

    #[Route('/admin/facturation', name: 'admin_facturation')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(
        Request $request,
        CoproprietaireRepository $coproRepo,
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
        ]);
    }

    /**
     * @return array{annee?:int, coproprietaire_id?:int, eau?:string, piece?:string}
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

        if ($includeCopro) {
            $coproId = $request->query->get('coproprietaire_id');
            if (is_numeric($coproId)) {
                $filters['coproprietaire_id'] = (int)$coproId;
            }
        }

        return $filters;
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
