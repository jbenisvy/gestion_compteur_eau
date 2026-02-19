<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nettoie les doublons de lots, supprime les lots sans copropriétaire et impose l\'unicité lot.numero_lot et releve(annee, lot_id)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        if (!$this->tableExists('lot')) {
            return;
        }

        $this->mergeDuplicateLotsByNumero($this->connection);
        $this->purgeLotsWithoutOwner($this->connection);
        $this->deduplicateRelevesByYearAndLot($this->connection);

        if (!$this->hasUniqueIndexOnColumns('lot', ['numero_lot'])) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_LOT_NUMERO_LOT ON lot (numero_lot)');
        }

        if ($this->tableExists('releve') && !$this->hasUniqueIndexOnColumns('releve', ['annee', 'lot_id'])) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_RELEVE_NEW_ANNEE_LOT ON releve (annee, lot_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('lot') && $this->indexExists('lot', 'UNIQ_LOT_NUMERO_LOT')) {
            $this->addSql('DROP INDEX UNIQ_LOT_NUMERO_LOT ON lot');
        }

        if ($this->tableExists('releve') && $this->indexExists('releve', 'UNIQ_RELEVE_NEW_ANNEE_LOT')) {
            $this->addSql('DROP INDEX UNIQ_RELEVE_NEW_ANNEE_LOT ON releve');
        }
    }

    private function mergeDuplicateLotsByNumero(Connection $conn): void
    {
        $duplicates = $conn->fetchFirstColumn('
            SELECT numero_lot
            FROM lot
            GROUP BY numero_lot
            HAVING COUNT(*) > 1
        ');

        foreach ($duplicates as $numeroLot) {
            $rows = $conn->fetchAllAssociative('
                SELECT
                    l.id,
                    CASE WHEN l.coproprietaire_id IS NULL THEN 0 ELSE 1 END AS has_legacy_owner,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM lot_coproprietaire lc WHERE lc.lot_id = l.id
                    ) THEN 1 ELSE 0 END AS has_history_owner
                FROM lot l
                WHERE l.numero_lot = :numeroLot
                ORDER BY has_history_owner DESC, has_legacy_owner DESC, l.id ASC
            ', ['numeroLot' => $numeroLot]);

            if (count($rows) < 2) {
                continue;
            }

            $keeperId = (int) $rows[0]['id'];
            for ($i = 1, $count = count($rows); $i < $count; $i++) {
                $duplicateId = (int) $rows[$i]['id'];
                $this->reassignLotReferences($conn, $duplicateId, $keeperId);
            }
        }
    }

    private function reassignLotReferences(Connection $conn, int $fromLotId, int $toLotId): void
    {
        if ($fromLotId === $toLotId) {
            return;
        }

        if ($this->tableExists('lot_coproprietaire')) {
            $conn->executeStatement(
                'UPDATE lot_coproprietaire SET lot_id = :toLot WHERE lot_id = :fromLot',
                ['toLot' => $toLotId, 'fromLot' => $fromLotId]
            );
        }

        if ($this->tableExists('compteur')) {
            $conn->executeStatement(
                'UPDATE compteur SET lot_id = :toLot WHERE lot_id = :fromLot',
                ['toLot' => $toLotId, 'fromLot' => $fromLotId]
            );
        }

        if ($this->tableExists('releve')) {
            $conn->executeStatement(
                'UPDATE releve SET lot_id = :toLot WHERE lot_id = :fromLot',
                ['toLot' => $toLotId, 'fromLot' => $fromLotId]
            );
        }

        if ($this->tableExists('releve_new')) {
            $conn->executeStatement(
                'UPDATE releve_new SET lot_id = :toLot WHERE lot_id = :fromLot',
                ['toLot' => $toLotId, 'fromLot' => $fromLotId]
            );
        }

        $conn->executeStatement('DELETE FROM lot WHERE id = :id', ['id' => $fromLotId]);
    }

    private function purgeLotsWithoutOwner(Connection $conn): void
    {
        $orphanLotIds = $conn->fetchFirstColumn('
            SELECT l.id
            FROM lot l
            WHERE l.coproprietaire_id IS NULL
              AND NOT EXISTS (
                    SELECT 1
                    FROM lot_coproprietaire lc
                    WHERE lc.lot_id = l.id
              )
        ');

        foreach ($orphanLotIds as $lotIdRaw) {
            $lotId = (int) $lotIdRaw;

            if ($this->tableExists('releve_item') && $this->tableExists('releve')) {
                $conn->executeStatement('
                    DELETE ri
                    FROM releve_item ri
                    INNER JOIN releve r ON r.id = ri.releve_id
                    WHERE r.lot_id = :lotId
                ', ['lotId' => $lotId]);
            }

            if ($this->tableExists('releve_item') && $this->tableExists('releve_new')) {
                $conn->executeStatement('
                    DELETE ri
                    FROM releve_item ri
                    INNER JOIN releve_new rn ON rn.id = ri.releve_id
                    WHERE rn.lot_id = :lotId
                ', ['lotId' => $lotId]);
            }

            if ($this->tableExists('releve')) {
                $conn->executeStatement('DELETE FROM releve WHERE lot_id = :lotId', ['lotId' => $lotId]);
            }

            if ($this->tableExists('releve_new')) {
                $conn->executeStatement('DELETE FROM releve_new WHERE lot_id = :lotId', ['lotId' => $lotId]);
            }

            if ($this->tableExists('compteur')) {
                $conn->executeStatement('DELETE FROM compteur WHERE lot_id = :lotId', ['lotId' => $lotId]);
            }

            if ($this->tableExists('lot_coproprietaire')) {
                $conn->executeStatement('DELETE FROM lot_coproprietaire WHERE lot_id = :lotId', ['lotId' => $lotId]);
            }

            $conn->executeStatement('DELETE FROM lot WHERE id = :lotId', ['lotId' => $lotId]);
        }
    }

    private function deduplicateRelevesByYearAndLot(Connection $conn): void
    {
        if (!$this->tableExists('releve')) {
            return;
        }

        $groups = $conn->fetchAllAssociative('
            SELECT lot_id, annee, MIN(id) AS keep_id
            FROM releve
            GROUP BY lot_id, annee
            HAVING COUNT(*) > 1
        ');

        foreach ($groups as $group) {
            $lotId = (int) $group['lot_id'];
            $annee = (int) $group['annee'];
            $keepId = (int) $group['keep_id'];

            $toDelete = $conn->fetchFirstColumn(
                'SELECT id FROM releve WHERE lot_id = :lotId AND annee = :annee AND id <> :keepId',
                ['lotId' => $lotId, 'annee' => $annee, 'keepId' => $keepId]
            );

            foreach ($toDelete as $idRaw) {
                $releveId = (int) $idRaw;
                if ($this->tableExists('releve_item')) {
                    $conn->executeStatement('DELETE FROM releve_item WHERE releve_id = :releveId', ['releveId' => $releveId]);
                }
                $conn->executeStatement('DELETE FROM releve WHERE id = :releveId', ['releveId' => $releveId]);
            }
        }
    }

    private function tableExists(string $tableName): bool
    {
        $db = (string) $this->connection->fetchOne('SELECT DATABASE()');
        if ($db === '') {
            return false;
        }

        $exists = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :table',
            ['db' => $db, 'table' => $tableName]
        );

        return ((int) $exists) > 0;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $count = $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s', $this->connection->quote($tableName), $this->connection->quote($indexName))
        );

        return ((int) $count) > 0;
    }

    /**
     * @param string[] $columns
     */
    private function hasUniqueIndexOnColumns(string $tableName, array $columns): bool
    {
        $indexes = $this->connection->fetchAllAssociative(
            'SELECT index_name, non_unique, seq_in_index, column_name
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = :table
             ORDER BY index_name, seq_in_index',
            ['table' => $tableName]
        );

        $target = array_map('strtolower', $columns);
        $byIndex = [];
        foreach ($indexes as $idx) {
            $indexName = (string) $this->readRowValue($idx, 'index_name');
            if ($indexName === '') {
                continue;
            }
            $byIndex[$indexName]['non_unique'] = (int) $this->readRowValue($idx, 'non_unique');
            $byIndex[$indexName]['columns'][] = strtolower((string) $this->readRowValue($idx, 'column_name'));
        }

        foreach ($byIndex as $index) {
            if ($index['non_unique'] !== 0) {
                continue;
            }

            if (($index['columns'] ?? []) === $target) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function readRowValue(array $row, string $key): mixed
    {
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }

        $upper = strtoupper($key);
        if (array_key_exists($upper, $row)) {
            return $row[$upper];
        }

        $lower = strtolower($key);
        if (array_key_exists($lower, $row)) {
            return $row[$lower];
        }

        return null;
    }
}
