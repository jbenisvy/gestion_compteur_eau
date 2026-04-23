<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Export\ExcelCompteursExportService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ExportExcelCompteursXlsxController extends AbstractController
{
    private const COLUMNS = [
        'annee',
        'lot_id',
        'lot_numero',
        'lot_description',
        'lot_type_appartement',
        'lot_inoccupe',
        'lot_inoccupe_motif',
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
        'compteur_supprime',
        'index_masque',
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

    private const BOOL_COLUMNS = [
        'compteur_actif',
        'compteur_supprime',
        'index_masque',
        'forfait_applique',
        'lot_inoccupe',
    ];

    #[Route('/api/export/excel-compteurs.xlsx', name: 'api_export_excel_compteurs_xlsx', methods: ['GET'])]
    public function __invoke(Request $request, ExcelCompteursExportService $service): Response
    {
        if (!$service->isEnabled()) {
            throw $this->createNotFoundException('Export XLSX des compteurs desactive.');
        }

        $token = $request->query->get('token');
        if (!$service->isAuthorized(is_string($token) ? $token : null)) {
            return new Response('unauthorized', Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $payload = $service->export($filters);
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        if (!class_exists('ZipArchive')) {
            return new Response('Zip extension required to generate XLSX.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'export_compteurs_');
        if ($tmp === false) {
            return new Response('Unable to create temp file.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $tmpFile = $tmp . '.xlsx';

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('DATA_EXPORT');

            // Header
            $colIndex = 1;
            foreach (self::COLUMNS as $col) {
                $cell = Coordinate::stringFromColumnIndex($colIndex) . '1';
                $sheet->setCellValueExplicit($cell, $col, DataType::TYPE_STRING);
                $colIndex++;
            }

            // Rows
            $rowIndex = 2;
            foreach ($rows as $row) {
                $colIndex = 1;
                foreach (self::COLUMNS as $col) {
                    $val = $row[$col] ?? null;
                    $this->setTypedValue($sheet, $colIndex, $rowIndex, $col, $val);
                    $colIndex++;
                }
                $rowIndex++;
            }

            // Basic number formats for decimals
            foreach (self::DECIMAL_COLUMNS as $colName) {
                $colPos = array_search($colName, self::COLUMNS, true);
                if ($colPos !== false) {
                    $columnLetter = Coordinate::stringFromColumnIndex($colPos + 1);
                    $sheet->getStyle($columnLetter . '2:' . $columnLetter . ($rowIndex - 1))
                        ->getNumberFormat()
                        ->setFormatCode('0.000');
                }
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tmpFile);
        } catch (\Throwable $e) {
            return new Response('XLSX export failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $filename = 'export_compteurs.xlsx';
        $response = new BinaryFileResponse($tmpFile);
        $response->setContentDisposition('attachment', $filename);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function setTypedValue($sheet, int $col, int $row, string $name, $val): void
    {
        $cell = Coordinate::stringFromColumnIndex($col) . (string)$row;
        if ($val === null || $val === '') {
            $sheet->setCellValueExplicit($cell, '', DataType::TYPE_STRING);
            return;
        }

        if (in_array($name, self::BOOL_COLUMNS, true)) {
            $sheet->setCellValueExplicit($cell, $val ? 1 : 0, DataType::TYPE_NUMERIC);
            return;
        }

        if (in_array($name, self::INT_COLUMNS, true)) {
            if (is_numeric($val)) {
                $sheet->setCellValueExplicit($cell, (int)$val, DataType::TYPE_NUMERIC);
            } else {
                $sheet->setCellValueExplicit($cell, (string)$val, DataType::TYPE_STRING);
            }
            return;
        }

        if (in_array($name, self::DECIMAL_COLUMNS, true)) {
            if (is_numeric($val)) {
                $sheet->setCellValueExplicit($cell, (float)$val, DataType::TYPE_NUMERIC);
            } else {
                $sheet->setCellValueExplicit($cell, (string)$val, DataType::TYPE_STRING);
            }
            return;
        }

        $sheet->setCellValueExplicit($cell, (string)$val, DataType::TYPE_STRING);
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
