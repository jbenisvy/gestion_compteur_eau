<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend coproprietaire.user_id nullable pour permettre la creation d un coproprietaire sans compte utilisateur.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        if (!$this->tableExists('coproprietaire') || !$this->columnExists('coproprietaire', 'user_id')) {
            return;
        }

        $this->addSql('ALTER TABLE coproprietaire MODIFY user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('coproprietaire') && $this->columnExists('coproprietaire', 'user_id')) {
            $this->addSql('ALTER TABLE coproprietaire MODIFY user_id INT NOT NULL');
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

