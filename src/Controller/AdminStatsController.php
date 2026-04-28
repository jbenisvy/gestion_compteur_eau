<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ReleveRepository;
use App\Service\Dashboard\DashboardSummaryService;
use App\Service\Stats\StatsDatasetService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminStatsController extends AbstractController
{
    #[Route('/admin/evenements', name: 'admin_dashboard_events')]
    public function events(DashboardSummaryService $dashboardSummaryService): Response
    {
        $this->denyUnlessAdminOrSyndic();

        return $this->render('admin/dashboard_events.html.twig', [
            'dashboardSummary' => $dashboardSummaryService->buildYearlySummary(),
            'isReadOnlyViewer' => !$this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/admin/stats', name: 'admin_stats')]
    public function index(ReleveRepository $releveRepo): Response
    {
        $this->denyUnlessAdminOrSyndic();

        $years = $releveRepo->findDistinctAnnees();
        if ($years === []) {
            $years = [(int)date('Y')];
        }

        return $this->render('admin/stats.html.twig', [
            'years' => $years,
            'defaultYear' => $years[count($years) - 1] ?? (int)date('Y'),
            'isReadOnlyViewer' => !$this->isGranted('ROLE_ADMIN'),
            'canManagePivotPresets' => $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SYNDIC'),
        ]);
    }

    #[Route('/admin/stats/data', name: 'admin_stats_data')]
    public function data(Request $request, StatsDatasetService $statsService): JsonResponse
    {
        $this->denyUnlessAdminOrSyndic();

        $filters = $this->extractFilters($request);
        $options = $this->extractOptions($request);

        // Donnees consolidees par service pour rester en lecture seule.
        $payload = $statsService->build($filters, $options);

        return $this->json($payload);
    }

    #[Route('/admin/stats/pdf', name: 'admin_stats_pdf')]
    public function pdf(Request $request, StatsDatasetService $statsService): Response
    {
        $this->denyUnlessAdminOrSyndic();

        $filters = $this->extractFilters($request);
        $options = $this->extractOptions($request);

        // PDF serveur: reproduit les filtres, sans dependance JS.
        $payload = $statsService->build($filters, $options);

        $orientation = strtolower((string)$request->query->get('orientation', 'landscape'));
        $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'landscape';

        $html = $this->renderView('admin/stats_pdf.html.twig', [
            'payload' => $payload,
            'filters' => $filters,
            'options' => $options,
            'orientation' => $orientation,
            'generatedAt' => new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')),
        ]);

        $optionsPdf = new Options();
        $optionsPdf->set('defaultFont', 'DejaVu Sans');
        $optionsPdf->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($optionsPdf);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $filename = sprintf('statistiques_%s.pdf', date('Ymd_His'));

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * @return array{annee?:int, from?:int, to?:int}
     */
    private function extractFilters(Request $request): array
    {
        $filters = [];

        $annee = $this->asInt($request->query->get('annee'));
        if ($annee !== null) {
            $filters['annee'] = $annee;
        }

        $from = $this->asInt($request->query->get('from'));
        $to = $this->asInt($request->query->get('to'));
        if ($from !== null) {
            $filters['from'] = $from;
        }
        if ($to !== null) {
            $filters['to'] = $to;
        }

        return $filters;
    }

    /**
     * @return array{include_supprime?:bool, include_inactif?:bool, include_forfait?:bool, include_grise?:bool}
     */
    private function extractOptions(Request $request): array
    {
        return [
            'include_supprime' => $this->asBool($request->query->get('include_supprime', '1')),
            'include_inactif' => $this->asBool($request->query->get('include_inactif', '1')),
            'include_forfait' => $this->asBool($request->query->get('include_forfait', '1')),
            'include_grise' => $this->asBool($request->query->get('include_grise', '1')),
        ];
    }

    private function asInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return null;
    }

    private function asBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function denyUnlessAdminOrSyndic(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SYNDIC')) {
            throw $this->createAccessDeniedException();
        }
    }
}
