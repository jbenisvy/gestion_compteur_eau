<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la FK releve_item.etat_id -> etat_compteur.id (idempotent) et nettoie les etat_id orphelins.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        if (!$this->tableExists('releve_item') || !$this->tableExists('etat_compteur')) {
            return;
        }

        if (!$this->columnExists('releve_item', 'etat_id')) {
            return;
        }

        // Empêche l'échec de création de FK si des valeurs ne pointent sur aucun état.
        $this->addSql('
            UPDATE releve_item ri
            LEFT JOIN etat_compteur ec ON ec.id = ri.etat_id
            SET ri.etat_id = NULL
            WHERE ri.etat_id IS NOT NULL AND ec.id IS NULL
        ');

        if (!$this->indexExists('releve_item', 'IDX_RELEVE_ITEM_ETAT')) {
            $this->addSql('CREATE INDEX IDX_RELEVE_ITEM_ETAT ON releve_item (etat_id)');
        }

        if (!$this->hasForeignKeyOnColumn('releve_item', 'etat_id')) {
            $this->addSql('ALTER TABLE releve_item ADD CONSTRAINT FK_RELEVE_ITEM_ETAT FOREIGN KEY (etat_id) REFERENCES etat_compteur (id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('releve_item') && $this->foreignKeyExists('releve_item', 'FK_RELEVE_ITEM_ETAT')) {
            $this->addSql('ALTER TABLE releve_item DROP FOREIGN KEY FK_RELEVE_ITEM_ETAT');
        }

        if ($this->tableExists('releve_item') && $this->indexExists('releve_item', 'IDX_RELEVE_ITEM_ETAT')) {
            $this->addSql('DROP INDEX IDX_RELEVE_ITEM_ETAT ON releve_item');
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
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index',
            ['table' => $tableName, 'index' => $indexName]
        );

        return ((int) $count) > 0;
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = :table AND constraint_type = \'FOREIGN KEY\' AND constraint_name = :constraint',
            ['table' => $tableName, 'constraint' => $constraintName]
        );

        return ((int) $count) > 0;
    }

    private function hasForeignKeyOnColumn(string $tableName, string $columnName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column AND referenced_table_name IS NOT NULL',
            ['table' => $tableName, 'column' => $columnName]
        );

        return ((int) $count) > 0;
    }
}
