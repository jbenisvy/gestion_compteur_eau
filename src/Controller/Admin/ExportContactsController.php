<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ExportContactsController extends AbstractController
{
    #[Route('/admin/export/contacts.json', name: 'admin_export_contacts_json', methods: ['GET'])]
    public function exportJson(Connection $conn): Response
    {
        $rows = $this->fetchRows($conn);
        $payload = [
            'generated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format(DATE_ATOM),
            'source' => 'application_compteurs_eau',
            'version' => '1.0',
            'row_count' => count($rows),
            'rows' => $rows,
        ];

        $response = new JsonResponse($payload);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }

    #[Route('/admin/export/contacts.csv', name: 'admin_export_contacts_csv', methods: ['GET'])]
    public function csv(Connection $conn): Response
    {
        $rows = $this->fetchRows($conn);

        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "sep=;\n");
            fputcsv($out, [
                'coproprietaire_id',
                'lot_numero',
                'locataire_nom',
                'coproprietaire_nom',
                'coproprietaire_prenom',
                'telephone',
                'email',
                'adresse',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['coproprietaire_id'],
                    $row['lot_numero'],
                    $row['locataire_nom'],
                    $row['coproprietaire_nom'],
                    $row['coproprietaire_prenom'],
                    $row['telephone'],
                    $row['email'],
                    $row['adresse'],
                ], ';');
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="contacts_lots.csv"');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function fetchRows(Connection $conn): array
    {
        $sql = <<<SQL
            SELECT
                c.id AS coproprietaire_id,
                l.numero_lot AS lot_numero,
                l.occupant AS locataire_nom,
                c.nom AS coproprietaire_nom,
                c.prenom AS coproprietaire_prenom,
                c.telephone AS telephone,
                c.email AS email
            FROM lot l
            LEFT JOIN coproprietaire c ON c.id = l.coproprietaire_id
            ORDER BY l.numero_lot ASC
        SQL;

        $raw = $conn->fetchAllAssociative($sql);
        $rows = [];
        foreach ($raw as $r) {
            $rows[] = [
                'coproprietaire_id' => $this->stringOrNull($r['coproprietaire_id'] ?? null),
                'lot_numero' => $this->stringOrNull($r['lot_numero'] ?? null),
                'locataire_nom' => $this->stringOrNull($r['locataire_nom'] ?? null),
                'coproprietaire_nom' => $this->stringOrNull($r['coproprietaire_nom'] ?? null),
                'coproprietaire_prenom' => $this->stringOrNull($r['coproprietaire_prenom'] ?? null),
                'telephone' => $this->stringOrNull($r['telephone'] ?? null),
                'email' => $this->stringOrNull($r['email'] ?? null),
                'adresse' => null,
            ];
        }
        return $rows;
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }
}
