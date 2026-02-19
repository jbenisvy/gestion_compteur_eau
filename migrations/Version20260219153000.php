<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute unicite compteur (lot_id, numero_serie) avec nettoyage des doublons existants.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        if (
            !$this->tableExists('compteur')
            || !$this->columnExists('compteur', 'lot_id')
            || !$this->columnExists('compteur', 'numero_serie')
        ) {
            return;
        }

        // Normalise les numeros de serie: trim et null si vide.
        $this->connection->executeStatement(
            "UPDATE compteur
             SET numero_serie = NULL
             WHERE numero_serie IS NOT NULL AND TRIM(numero_serie) = ''"
        );
        $this->connection->executeStatement(
            "UPDATE compteur
             SET numero_serie = TRIM(numero_serie)
             WHERE numero_serie IS NOT NULL"
        );

        // Pour chaque doublon (lot + numero_serie), conserve le plus petit id.
        $groups = $this->connection->fetchAllAssociative(
            "SELECT lot_id, LOWER(TRIM(numero_serie)) AS normalized_serie, MIN(id) AS keep_id, COUNT(*) AS cnt
             FROM compteur
             WHERE numero_serie IS NOT NULL AND TRIM(numero_serie) <> ''
             GROUP BY lot_id, LOWER(TRIM(numero_serie))
             HAVING COUNT(*) > 1"
        );

        foreach ($groups as $group) {
            $lotId = (int) ($group['lot_id'] ?? 0);
            $keepId = (int) ($group['keep_id'] ?? 0);
            $serie = (string) ($group['normalized_serie'] ?? '');

            if ($lotId <= 0 || $keepId <= 0 || $serie === '') {
                continue;
            }

            $duplicateIds = $this->connection->fetchFirstColumn(
                "SELECT id
                 FROM compteur
                 WHERE lot_id = :lotId
                   AND numero_serie IS NOT NULL
                   AND LOWER(TRIM(numero_serie)) = :serie
                   AND id <> :keepId
                 ORDER BY id ASC",
                [
                    'lotId' => $lotId,
                    'serie' => $serie,
                    'keepId' => $keepId,
                ]
            );

            foreach ($duplicateIds as $duplicateIdRaw) {
                $duplicateId = (int) $duplicateIdRaw;
                if ($duplicateId <= 0) {
                    continue;
                }

                if (
                    $this->tableExists('releve_item')
                    && $this->columnExists('releve_item', 'releve_id')
                    && $this->columnExists('releve_item', 'compteur_id')
                ) {
                    // Evite une collision sur UNIQ_RELEVE_ITEM_MAIN lors du re-pointage.
                    $this->connection->executeStatement(
                        "DELETE ri_dup
                         FROM releve_item ri_dup
                         INNER JOIN releve_item ri_keep
                           ON ri_keep.releve_id = ri_dup.releve_id
                          AND ri_keep.compteur_id = :keepId
                         WHERE ri_dup.compteur_id = :duplicateId",
                        [
                            'keepId' => $keepId,
                            'duplicateId' => $duplicateId,
                        ]
                    );

                    $this->connection->executeStatement(
                        'UPDATE releve_item SET compteur_id = :keepId WHERE compteur_id = :duplicateId',
                        [
                            'keepId' => $keepId,
                            'duplicateId' => $duplicateId,
                        ]
                    );
                }

                if (
                    $this->tableExists('releve')
                    && $this->columnExists('releve', 'compteur_id')
                ) {
                    $this->connection->executeStatement(
                        'UPDATE releve SET compteur_id = :keepId WHERE compteur_id = :duplicateId',
                        [
                            'keepId' => $keepId,
                            'duplicateId' => $duplicateId,
                        ]
                    );
                }

                $this->connection->executeStatement(
                    'DELETE FROM compteur WHERE id = :duplicateId',
                    ['duplicateId' => $duplicateId]
                );
            }
        }

        if (!$this->indexExists('compteur', 'UNIQ_COMPTEUR_LOT_NUMERO_SERIE')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_COMPTEUR_LOT_NUMERO_SERIE ON compteur (lot_id, numero_serie)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('compteur') && $this->indexExists('compteur', 'UNIQ_COMPTEUR_LOT_NUMERO_SERIE')) {
            $this->addSql('DROP INDEX UNIQ_COMPTEUR_LOT_NUMERO_SERIE ON compteur');
        }
    }

    private function tableExists(string $tableName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $tableName]
        );

        return ((int) $count) > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $tableName, 'column' => $columnName]
        );

        return ((int) $count) > 0;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :indexName',
            [
                'table' => $tableName,
                'indexName' => $indexName,
            ]
        );

        return ((int) $count) > 0;
    }
}

