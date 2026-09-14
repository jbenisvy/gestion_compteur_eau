<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute releve_item.index_virtuel pour conserver l index calcule apres forfait.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('releve_item') || $this->columnExists('releve_item', 'index_virtuel')) {
            return;
        }

        $this->addSql('ALTER TABLE releve_item ADD index_virtuel INT DEFAULT NULL');
        $this->addSql('
            UPDATE releve_item
            SET index_virtuel = CASE
                WHEN forfait = 1 AND index_n1 IS NOT NULL THEN index_n1 + COALESCE(ROUND(consommation), 0)
                WHEN index_n IS NOT NULL THEN index_n
                ELSE index_nouveau_compteur
            END
            WHERE index_virtuel IS NULL
        ');
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('releve_item') || !$this->columnExists('releve_item', 'index_virtuel')) {
            return;
        }

        $this->addSql('ALTER TABLE releve_item DROP index_virtuel');
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $tableName]
        );
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $tableName, 'column' => $columnName]
        );
    }
}
