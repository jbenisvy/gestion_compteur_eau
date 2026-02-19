<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute releve_item.numero_compteur pour historiser le numero de compteur saisi par annee.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        if (!$this->tableExists('releve_item')) {
            return;
        }

        if (!$this->columnExists('releve_item', 'numero_compteur')) {
            $this->addSql('ALTER TABLE releve_item ADD numero_compteur VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('releve_item') && $this->columnExists('releve_item', 'numero_compteur')) {
            $this->addSql('ALTER TABLE releve_item DROP COLUMN numero_compteur');
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

