<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250908133801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute la colonne compteur.photo si absente.";
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'MySQL only.');

        if (!$this->tableExists('compteur')) {
            return;
        }

        if (!$this->columnExists('compteur', 'photo')) {
            $this->addSql('ALTER TABLE compteur ADD COLUMN photo VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'MySQL only.');

        if ($this->tableExists('compteur') && $this->columnExists('compteur', 'photo')) {
            $this->addSql('ALTER TABLE compteur DROP COLUMN photo');
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
}
