<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Export\ExcelCompteursExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

final class ExportExcelCompteursCsvController extends AbstractController
{
    private const COLUMNS = [
        'annee',
        'lot_id',
        'lot_numero',
        'lot_description',
        'lot_type_appartement',
        'lot_tantieme',
        'locataire_nom',
        'proprietaire_id',
        'proprietaire_nom',
        'compteur_id',
        'compteur_reference',
        'compteur_numero_releve',
        'compteur_nature',
        'compteur_emplacement',
        'compteur_emplacement_norm',
        'compteur_actif',
        'compteur_etat_code',
        'compteur_etat_libelle',
        'compteur_statut',
        'releve_id',
        'releve_item_id',
        'releve_etat_code',
        'releve_etat_libelle',
        'index_n_1',
        'index_n',
        'index_compteur_demonte',
        'index_nouveau_compteur',
        'consommation',
        'consommation_source',
        'forfait_applique',
        'forfait_valeur',
        'forfait_motif',
        'commentaire',
        'releve_created_at',
        'releve_updated_at',
    ];

    private const DECIMAL_COLUMNS = [
        'consommation',
        'forfait_valeur',
    ];

    private const INT_COLUMNS = [
        'annee',
        'lot_id',
        'lot_tantieme',
        'proprietaire_id',
        'compteur_id',
        'releve_id',
        'releve_item_id',
        'index_n_1',
        'index_n',
        'index_compteur_demonte',
        'index_nouveau_compteur',
    ];

    #[Route('/api/export/excel-compteurs.csv', name: 'api_export_excel_compteurs_csv', methods: ['GET'])]
    public function __invoke(Request $request, ExcelCompteursExportService $service): Response
    {
        if (!$service->isEnabled()) {
            throw $this->createNotFoundException('Export CSV des compteurs desactive.');
        }

        $token = $request->query->get('token');
        if (!$service->isAuthorized(is_string($token) ? $token : null)) {
            return new Response('unauthorized', Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $payload = $service->export($filters);
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            // Force delimiter detection for LibreOffice/Excel (European CSV)
            fwrite($out, "sep=;\n");
            fputcsv($out, self::COLUMNS, ';');
            foreach ($rows as $row) {
                $line = [];
                foreach (self::COLUMNS as $col) {
                    $val = $row[$col] ?? null;
                    $val = $this->normalizeValue($col, $val);
                    $line[] = $val;
                }
                fputcsv($out, $line, ';');
            }

            fclose($out);
        });

        $filename = sprintf('export_compteurs_%s.csv', (new \DateTimeImmutable())->format('Ymd_His'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function normalizeValue(string $col, $val)
    {
        if ($val === null) {
            return '';
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        if (in_array($col, self::INT_COLUMNS, true)) {
            return is_numeric($val) ? (string)(int)$val : (string)$val;
        }
        if (in_array($col, self::DECIMAL_COLUMNS, true)) {
            if (is_numeric($val)) {
                // LibreOffice FR: separateur decimal ',' avec CSV ';'
                $txt = (string)(float)$val;
                return str_replace('.', ',', $txt);
            }
            return (string)$val;
        }
        return (string)$val;
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
