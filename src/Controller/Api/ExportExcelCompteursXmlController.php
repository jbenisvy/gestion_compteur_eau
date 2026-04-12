<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Export\ExcelCompteursExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ExportExcelCompteursXmlController extends AbstractController
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

    #[Route('/api/export/excel-compteurs.xml', name: 'api_export_excel_compteurs_xml', methods: ['GET'])]
    public function __invoke(Request $request, ExcelCompteursExportService $service): Response
    {
        if (!$service->isEnabled()) {
            throw $this->createNotFoundException('Export XML des compteurs desactive.');
        }

        $token = $request->query->get('token');
        if (!$service->isAuthorized(is_string($token) ? $token : null)) {
            return new Response('unauthorized', Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $payload = $service->export($filters);
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        $xml = $this->buildXml($payload, $rows);

        $response = new Response($xml);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    #[Route('/api/export/excel-compteurs.xsd', name: 'api_export_excel_compteurs_xsd', methods: ['GET'])]
    public function xsd(Request $request, ExcelCompteursExportService $service): Response
    {
        if (!$service->isEnabled()) {
            throw $this->createNotFoundException('Export XSD des compteurs desactive.');
        }

        $token = $request->query->get('token');
        if (!$service->isAuthorized(is_string($token) ? $token : null)) {
            return new Response('unauthorized', Response::HTTP_FORBIDDEN);
        }

        $xsd = $this->buildXsd();
        $response = new Response($xsd);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

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

    private function buildXml(array $payload, array $rows): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('export');
        $dom->appendChild($root);

        $this->appendText($dom, $root, 'generated_at', (string)($payload['generated_at'] ?? ''));
        $this->appendText($dom, $root, 'source', (string)($payload['source'] ?? ''));
        $this->appendText($dom, $root, 'version', (string)($payload['version'] ?? '1.0'));

        $meta = $dom->createElement('meta');
        $root->appendChild($meta);
        $this->appendText($dom, $meta, 'row_count', (string)count($rows));

        $rowsNode = $dom->createElement('rows');
        $root->appendChild($rowsNode);

        foreach ($rows as $row) {
            $rowNode = $dom->createElement('row');
            foreach (self::COLUMNS as $col) {
                $val = $row[$col] ?? null;
                $this->appendText($dom, $rowNode, $col, $this->stringify($val));
            }
            $rowsNode->appendChild($rowNode);
        }

        return $dom->saveXML();
    }

    private function appendText(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElement($name);
        if ($value !== '') {
            $el->appendChild($dom->createTextNode($value));
        }
        $parent->appendChild($el);
    }

    private function stringify($val): string
    {
        if ($val === null) {
            return '';
        }
        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }
        return (string)$val;
    }

    private function buildXsd(): string
    {
        // Simple XSD for LibreOffice XML data source
        return <<<XSD
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:element name="export">
    <xs:complexType>
      <xs:sequence>
        <xs:element name="generated_at" type="xs:string" minOccurs="0"/>
        <xs:element name="source" type="xs:string" minOccurs="0"/>
        <xs:element name="version" type="xs:string" minOccurs="0"/>
        <xs:element name="meta" minOccurs="0">
          <xs:complexType>
            <xs:sequence>
              <xs:element name="row_count" type="xs:int" minOccurs="0"/>
            </xs:sequence>
          </xs:complexType>
        </xs:element>
        <xs:element name="rows" minOccurs="0">
          <xs:complexType>
            <xs:sequence>
              <xs:element name="row" minOccurs="0" maxOccurs="unbounded">
                <xs:complexType>
                  <xs:sequence>
                    <xs:element name="annee" type="xs:int" minOccurs="0"/>
                    <xs:element name="lot_id" type="xs:int" minOccurs="0"/>
                    <xs:element name="lot_numero" type="xs:string" minOccurs="0"/>
                    <xs:element name="lot_description" type="xs:string" minOccurs="0"/>
                    <xs:element name="lot_type_appartement" type="xs:string" minOccurs="0"/>
                    <xs:element name="lot_tantieme" type="xs:int" minOccurs="0"/>
                    <xs:element name="locataire_nom" type="xs:string" minOccurs="0"/>
                    <xs:element name="proprietaire_id" type="xs:int" minOccurs="0"/>
                    <xs:element name="proprietaire_nom" type="xs:string" minOccurs="0"/>
                    <xs:element name="compteur_id" type="xs:int" minOccurs="0"/>
                    <xs:element name="compteur_reference" type="xs:string" minOccurs="0"/>
                    <xs:element name="compteur_numero_releve" type="xs:string" minOccurs="0"/>
                    <xs:element name="compteur_nature" type="xs:string" minOccurs="0"/>
                    <xs:element name="compteur_emplacement" type="xs:string" minOccurs="0"/>
                    <xs:element name="compteur_emplacement_norm" type="xs:string" minOccurs="0"/>
                    <xs:element name="compteur_actif" type="xs:boolean" minOccurs="0"/>
                    <xs:element name="compteur_etat_code" type="xs:string" minOccurs="0"/>
                    <xs:element name="compteur_etat_libelle" type="xs:string" minOccurs="0"/>
                    <xs:element name="compteur_statut" type="xs:string" minOccurs="0"/>
                    <xs:element name="releve_id" type="xs:int" minOccurs="0"/>
                    <xs:element name="releve_item_id" type="xs:int" minOccurs="0"/>
                    <xs:element name="releve_etat_code" type="xs:string" minOccurs="0"/>
                    <xs:element name="releve_etat_libelle" type="xs:string" minOccurs="0"/>
                    <xs:element name="index_n_1" type="xs:int" minOccurs="0"/>
                    <xs:element name="index_n" type="xs:int" minOccurs="0"/>
                    <xs:element name="index_compteur_demonte" type="xs:int" minOccurs="0"/>
                    <xs:element name="index_nouveau_compteur" type="xs:int" minOccurs="0"/>
                    <xs:element name="consommation" type="xs:decimal" minOccurs="0"/>
                    <xs:element name="consommation_source" type="xs:string" minOccurs="0"/>
                    <xs:element name="forfait_applique" type="xs:boolean" minOccurs="0"/>
                    <xs:element name="forfait_valeur" type="xs:decimal" minOccurs="0"/>
                    <xs:element name="forfait_motif" type="xs:string" minOccurs="0"/>
                    <xs:element name="commentaire" type="xs:string" minOccurs="0"/>
                    <xs:element name="releve_created_at" type="xs:string" minOccurs="0"/>
                    <xs:element name="releve_updated_at" type="xs:string" minOccurs="0"/>
                  </xs:sequence>
                </xs:complexType>
              </xs:element>
            </xs:sequence>
          </xs:complexType>
        </xs:element>
      </xs:sequence>
    </xs:complexType>
  </xs:element>
</xs:schema>
XSD;
    }
}
