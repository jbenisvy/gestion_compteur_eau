<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Export\ExcelCompteursExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ExportExcelCompteursController extends AbstractController
{
    #[Route('/api/export/excel-compteurs', name: 'api_export_excel_compteurs', methods: ['GET'])]
    public function __invoke(Request $request, ExcelCompteursExportService $service): Response
    {
        if (!$service->isEnabled()) {
            throw $this->createNotFoundException('Export JSON des compteurs desactive.');
        }

        $token = $request->query->get('token');
        if (!$service->isAuthorized(is_string($token) ? $token : null)) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $payload = $service->export($filters);

        $response = new JsonResponse($payload);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');

        return $response;
    }

    /**
     * @return array{annee?:int, from?:int, to?:int, lot_id?:int, compteur_id?:int}
     */
    private function parseFilters(Request $request): array
    {
        $filters = [];
        $annee = $request->query->get('annee');
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $lotId = $request->query->get('lot_id');
        $compteurId = $request->query->get('compteur_id');

        if (is_numeric($annee)) {
            $filters['annee'] = (int)$annee;
        }
        if (is_numeric($from)) {
            $filters['from'] = (int)$from;
        }
        if (is_numeric($to)) {
            $filters['to'] = (int)$to;
        }
        if (is_numeric($lotId)) {
            $filters['lot_id'] = (int)$lotId;
        }
        if (is_numeric($compteurId)) {
            $filters['compteur_id'] = (int)$compteurId;
        }

        return $filters;
    }
}
