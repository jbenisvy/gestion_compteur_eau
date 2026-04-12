<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Repository\ParametreRepository;
use Doctrine\DBAL\Connection;

final class ExcelCompteursExportService
{
    private Connection $conn;
    private ParametreRepository $paramRepo;

    /** @var array<int, array{nom:string, prenom:string}> */
    private array $coproById = [];

    /** @var array<int, array<int, array{coproprietaire_id:int, date_debut:string, date_fin:?string}>> */
    private array $ownerLinksByLot = [];

    private bool $ownerHistoryAvailable = false;

    public function __construct(Connection $conn, ParametreRepository $paramRepo)
    {
        $this->conn = $conn;
        $this->paramRepo = $paramRepo;
    }

    public function isEnabled(): bool
    {
        $raw = getenv('EXPORT_EXCEL_ENABLED');
        if ($raw === false || $raw === '') {
            return true;
        }
        $val = strtolower(trim($raw));
        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }

    public function isAuthorized(?string $token): bool
    {
        $expected = getenv('EXPORT_EXCEL_TOKEN');
        if ($expected === false || $expected === '') {
            return true; // ouvert si pas de token configure
        }
        $token = $token ?? '';
        return hash_equals($expected, $token);
    }

    /**
     * @param array{annee?:int, from?:int, to?:int, lot_id?:int, compteur_id?:int} $filters
     */
    public function export(array $filters = []): array
    {
        $rows = $this->fetchRows($filters);
        if ($rows === []) {
            return $this->buildPayload([], $filters, []);
        }

        $this->loadCoproprietaires();
        $this->loadOwnerLinksIfAny();

        $years = [];
        foreach ($rows as $r) {
            $y = (int)$r['annee'];
            $years[$y] = true;
        }
        $years = array_keys($years);
        sort($years);

        $forfaitsByYear = [];
        foreach ($years as $y) {
            $forfaitsByYear[$y] = $this->paramRepo->getForfaitsForYear($y);
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->formatRow($r, $forfaitsByYear);
        }

        return $this->buildPayload($out, $filters, $years);
    }

    /**
     * @param array{annee?:int, from?:int, to?:int, lot_id?:int, compteur_id?:int} $filters
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(array $filters): array
    {
        $where = [];
        $params = [];

        if (isset($filters['annee'])) {
            $where[] = 'r.annee = :annee';
            $params['annee'] = (int)$filters['annee'];
        }
        if (isset($filters['from'])) {
            $where[] = 'r.annee >= :from';
            $params['from'] = (int)$filters['from'];
        }
        if (isset($filters['to'])) {
            $where[] = 'r.annee <= :to';
            $params['to'] = (int)$filters['to'];
        }
        if (isset($filters['lot_id'])) {
            $where[] = 'r.lot_id = :lot_id';
            $params['lot_id'] = (int)$filters['lot_id'];
        }
        if (isset($filters['compteur_id'])) {
            $where[] = 'ri.compteur_id = :compteur_id';
            $params['compteur_id'] = (int)$filters['compteur_id'];
        }

        $sql = <<<SQL
            SELECT
                ri.id AS releve_item_id,
                r.id AS releve_id,
                r.annee,
                r.lot_id,
                r.created_at AS releve_created_at,
                r.updated_at AS releve_updated_at,
                r.verrouille AS releve_verrouille,
                l.numero_lot,
                l.emplacement AS lot_emplacement,
                l.type_appartement,
                l.tantieme,
                l.occupant,
                l.coproprietaire_id,
                c.id AS compteur_id,
                c.numero_serie,
                c.type AS compteur_type,
                c.emplacement AS compteur_emplacement,
                c.actif AS compteur_actif,
                c.etat_compteur_id,
                ec.code AS compteur_etat_code,
                ec.libelle AS compteur_etat_libelle,
                ri.index_n1,
                ri.index_n,
                ri.index_compteur_demonte,
                ri.index_nouveau_compteur,
                ri.etat_id AS releve_etat_id,
                er.code AS releve_etat_code,
                er.libelle AS releve_etat_libelle,
                ri.forfait AS releve_forfait_flag,
                ri.commentaire,
                ri.consommation,
                ri.numero_compteur,
                ri.created_at,
                ri.updated_at
            FROM releve_item ri
            INNER JOIN releve_new r ON r.id = ri.releve_id
            INNER JOIN lot l ON l.id = r.lot_id
            INNER JOIN compteur c ON c.id = ri.compteur_id
            LEFT JOIN etat_compteur ec ON ec.id = c.etat_compteur_id
            LEFT JOIN etat_compteur er ON er.id = ri.etat_id
        SQL;

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY r.annee ASC, l.numero_lot ASC, c.id ASC, ri.id ASC';

        return $this->conn->fetchAllAssociative($sql, $params);
    }

    private function loadCoproprietaires(): void
    {
        if ($this->coproById !== []) {
            return;
        }

        $rows = $this->conn->fetchAllAssociative('SELECT id, nom, prenom FROM coproprietaire');
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $this->coproById[$id] = [
                'nom' => (string)($row['nom'] ?? ''),
                'prenom' => (string)($row['prenom'] ?? ''),
            ];
        }
    }

    private function loadOwnerLinksIfAny(): void
    {
        if ($this->ownerLinksByLot !== [] || $this->ownerHistoryAvailable) {
            return;
        }

        $sm = $this->conn->createSchemaManager();
        if (!$sm->tablesExist(['lot_coproprietaire'])) {
            $this->ownerHistoryAvailable = false;
            return;
        }

        $rows = $this->conn->fetchAllAssociative(
            'SELECT lot_id, coproprietaire_id, date_debut, date_fin FROM lot_coproprietaire ORDER BY date_debut ASC'
        );
        foreach ($rows as $row) {
            $lotId = (int)$row['lot_id'];
            $this->ownerLinksByLot[$lotId][] = [
                'coproprietaire_id' => (int)$row['coproprietaire_id'],
                'date_debut' => (string)$row['date_debut'],
                'date_fin' => $row['date_fin'] !== null ? (string)$row['date_fin'] : null,
            ];
        }

        $this->ownerHistoryAvailable = $rows !== [];
    }

    /**
     * @param array<int, array{ef:float, ec:float}> $forfaitsByYear
     * @return array<string, mixed>
     */
    private function formatRow(array $r, array $forfaitsByYear): array
    {
        $annee = (int)$r['annee'];
        $lotId = (int)$r['lot_id'];
        $compteurType = (string)($r['compteur_type'] ?? 'EC');

        $owner = $this->resolveOwner($lotId, $annee, $r['coproprietaire_id'] ?? null);

        $releveEtatCode = $this->normalizeCode($r['releve_etat_code'] ?? null, $r['releve_etat_libelle'] ?? null);
        $compteurEtatCode = $this->normalizeCode($r['compteur_etat_code'] ?? null, $r['compteur_etat_libelle'] ?? null);

        $isForfait = $this->isForfait($releveEtatCode, (bool)$r['releve_forfait_flag']);
        $forfaits = $forfaitsByYear[$annee] ?? ['ef' => null, 'ec' => null];
        $defaultForfait = $compteurType === 'EF' ? (float)($forfaits['ef'] ?? 0.0) : (float)($forfaits['ec'] ?? 0.0);

        $savedConsommation = $this->asFloatOrNull($r['consommation'] ?? null);
        $consommationSource = 'calculated';
        $consommation = null;

        if ($savedConsommation !== null) {
            $consommation = max(0.0, round($savedConsommation, 3));
            $consommationSource = 'saved';
        } else {
            $consommation = $this->computeConsommation($r, $releveEtatCode, $defaultForfait, $isForfait);
        }

        $forfaitValeur = null;
        $forfaitMotif = null;
        if ($isForfait) {
            if ($savedConsommation !== null && $savedConsommation > 0) {
                $forfaitValeur = max(0.0, round($savedConsommation, 3));
            } else {
                $forfaitValeur = $defaultForfait > 0.0 ? $defaultForfait : null;
            }
            $forfaitMotif = $this->guessForfaitMotif($releveEtatCode);
        }

        return [
            'annee' => $annee,
            'lot_id' => $lotId,
            'lot_numero' => (string)($r['numero_lot'] ?? ''),
            'lot_description' => (string)($r['lot_emplacement'] ?? ''),
            'lot_type_appartement' => (string)($r['type_appartement'] ?? ''),
            'lot_tantieme' => (int)($r['tantieme'] ?? 0),
            'locataire_nom' => $this->nullIfEmpty($r['occupant'] ?? null),
            'proprietaire_id' => $owner['id'],
            'proprietaire_nom' => $owner['name'],
            'compteur_id' => (int)$r['compteur_id'],
            'compteur_reference' => $this->nullIfEmpty($r['numero_serie'] ?? null),
            'compteur_numero_releve' => $this->nullIfEmpty($r['numero_compteur'] ?? null),
            'compteur_nature' => $compteurType,
            'compteur_emplacement' => (string)($r['compteur_emplacement'] ?? ''),
            'compteur_emplacement_norm' => $this->normalizeEmplacement((string)($r['compteur_emplacement'] ?? '')),
            'compteur_actif' => (bool)$r['compteur_actif'],
            'compteur_etat_code' => $compteurEtatCode,
            'compteur_etat_libelle' => $this->nullIfEmpty($r['compteur_etat_libelle'] ?? null),
            'compteur_statut' => $this->deriveCompteurStatut($r['compteur_actif'] ?? null, $compteurEtatCode),
            'releve_id' => (int)$r['releve_id'],
            'releve_item_id' => (int)$r['releve_item_id'],
            'releve_etat_code' => $releveEtatCode,
            'releve_etat_libelle' => $this->nullIfEmpty($r['releve_etat_libelle'] ?? null),
            'index_n_1' => $this->asIntOrNull($r['index_n1'] ?? null),
            'index_n' => $this->asIntOrNull($r['index_n'] ?? null),
            'index_compteur_demonte' => $this->asIntOrNull($r['index_compteur_demonte'] ?? null),
            'index_nouveau_compteur' => $this->asIntOrNull($r['index_nouveau_compteur'] ?? null),
            'consommation' => $consommation,
            'consommation_source' => $consommationSource,
            'forfait_applique' => $isForfait,
            'forfait_valeur' => $forfaitValeur,
            'forfait_motif' => $forfaitMotif,
            'commentaire' => $this->nullIfEmpty($r['commentaire'] ?? null),
            'releve_created_at' => $this->nullIfEmpty($r['releve_created_at'] ?? null),
            'releve_updated_at' => $this->nullIfEmpty($r['releve_updated_at'] ?? null),
        ];
    }

    private function normalizeCode($code, $libelle): ?string
    {
        $code = is_string($code) ? trim($code) : '';
        $libelle = is_string($libelle) ? trim($libelle) : '';
        if ($code === '' && $libelle === '') {
            return null;
        }
        $raw = $code !== '' ? $code : $libelle;
        $raw = mb_strtolower($raw);
        return $raw !== '' ? $raw : null;
    }

    private function isForfait(?string $etatCode, bool $forfaitFlag): bool
    {
        if ($forfaitFlag) {
            return true;
        }
        if ($etatCode === null) {
            return false;
        }
        return str_contains($etatCode, 'forfait')
            || str_contains($etatCode, 'bloqu')
            || str_contains($etatCode, 'non communiqu')
            || str_contains($etatCode, 'index compteur non');
    }

    private function guessForfaitMotif(?string $etatCode): ?string
    {
        if ($etatCode === null) {
            return null;
        }
        if (str_contains($etatCode, 'bloqu')) {
            return 'compteur bloque';
        }
        if (str_contains($etatCode, 'non communiqu') || str_contains($etatCode, 'index compteur non')) {
            return 'index non communique';
        }
        if (str_contains($etatCode, 'supprime')) {
            return 'compteur supprime';
        }
        if (str_contains($etatCode, 'forfait')) {
            return 'forfait';
        }
        return $etatCode;
    }

    private function computeConsommation(array $r, ?string $etatCode, float $forfaitValue, bool $isForfait): float
    {
        $indexN1 = $this->asIntOrNull($r['index_n1'] ?? null) ?? 0;
        $indexN = $this->asIntOrNull($r['index_n'] ?? null) ?? 0;
        $indexDem = $this->asIntOrNull($r['index_compteur_demonte'] ?? null);
        $indexNew = $this->asIntOrNull($r['index_nouveau_compteur'] ?? null);

        $delta = max(0, $indexN - $indexN1);

        if ($etatCode !== null) {
            $isRemplacement = str_contains($etatCode, 'remplac')
                || str_contains($etatCode, 'demonte')
                || str_contains($etatCode, 'demont')
                || str_contains($etatCode, 'nouveau');

            if ($isRemplacement) {
                $oldPart = ($indexDem !== null) ? max(0, $indexDem - $indexN1) : 0;
                $newPart = ($indexNew !== null) ? max(0, $indexNew) : max(0, $indexN);
                $delta = $oldPart + $newPart;
            }

            if (str_contains($etatCode, 'supprime')) {
                $delta = 0;
            }
        }

        if ($isForfait && $forfaitValue > 0) {
            $delta += $forfaitValue;
        }

        return (float)$delta;
    }

    /**
     * @return array{id:?int, name:?string}
     */
    private function resolveOwner(int $lotId, int $annee, $fallbackCoproId): array
    {
        $fallbackId = $fallbackCoproId !== null ? (int)$fallbackCoproId : null;
        $selectedId = $fallbackId;

        if (isset($this->ownerLinksByLot[$lotId])) {
            $target = sprintf('%d-12-31', $annee);
            $bestStart = null;
            foreach ($this->ownerLinksByLot[$lotId] as $link) {
                $start = $link['date_debut'];
                $end = $link['date_fin'];
                if ($target < $start) {
                    continue;
                }
                if ($end !== null && $target > $end) {
                    continue;
                }
                if ($bestStart === null || $start >= $bestStart) {
                    $bestStart = $start;
                    $selectedId = $link['coproprietaire_id'];
                }
            }
        }

        $name = null;
        if ($selectedId !== null && isset($this->coproById[$selectedId])) {
            $nom = trim($this->coproById[$selectedId]['nom'] ?? '');
            $prenom = trim($this->coproById[$selectedId]['prenom'] ?? '');
            $full = trim($prenom . ' ' . $nom);
            $name = $full !== '' ? $full : null;
        }

        return [
            'id' => $selectedId,
            'name' => $name,
        ];
    }

    private function normalizeEmplacement(string $emplacement): string
    {
        $emp = mb_strtolower(trim($emplacement));
        if ($emp === '') {
            return 'autre';
        }
        $empNorm = preg_replace('/[^a-z0-9]+/u', ' ', $emp) ?? $emp;
        if (preg_match('/\b(cuis|cuisine)\b/u', $empNorm)) {
            return 'cuisine';
        }
        if (preg_match('/\b(sdb|salle\s*de\s*bains?|salle\s*d\'?eau|sde)\b/u', $empNorm)) {
            return 'sdb';
        }
        if (preg_match('/\b(wc|toilet|toilettes)\b/u', $empNorm)) {
            return 'wc';
        }
        return 'autre';
    }

    private function deriveCompteurStatut($actif, ?string $etatCode): string
    {
        $isActif = (bool)$actif;
        if (!$isActif) {
            return 'inactif';
        }
        if ($etatCode === null) {
            return 'actif';
        }
        if (str_contains($etatCode, 'supprime')) {
            return 'supprime';
        }
        if (str_contains($etatCode, 'forfait')) {
            return 'forfait';
        }
        if (str_contains($etatCode, 'remplac') || str_contains($etatCode, 'demonte') || str_contains($etatCode, 'demont') || str_contains($etatCode, 'nouveau')) {
            return 'remplace';
        }
        return 'actif';
    }

    private function nullIfEmpty($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function asIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return null;
    }

    private function asFloatOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array{annee?:int, from?:int, to?:int, lot_id?:int, compteur_id?:int} $filters
     * @param array<int, int> $years
     */
    private function buildPayload(array $rows, array $filters, array $years): array
    {
        $generatedAt = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format(DATE_ATOM);

        return [
            'generated_at' => $generatedAt,
            'source' => 'application_compteurs_eau',
            'version' => '1.0',
            'meta' => [
                'row_count' => count($rows),
                'years' => $years,
                'filters' => $filters,
                'owner_history_enabled' => $this->ownerHistoryAvailable,
            ],
            'rows' => $rows,
        ];
    }
}
