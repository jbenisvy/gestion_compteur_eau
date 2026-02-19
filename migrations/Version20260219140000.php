<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.is_verified et user.updated_at pour magic-link + verification email.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        if (!$this->tableExists('user')) {
            return;
        }

        if (!$this->columnExists('user', 'is_verified')) {
            $this->addSql("ALTER TABLE user ADD is_verified TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (!$this->columnExists('user', 'updated_at')) {
            $this->addSql("ALTER TABLE user ADD updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
            $this->addSql('UPDATE user SET updated_at = NOW() WHERE updated_at IS NULL');
            $this->addSql("ALTER TABLE user MODIFY updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('user') && $this->columnExists('user', 'is_verified')) {
            $this->addSql('ALTER TABLE user DROP COLUMN is_verified');
        }

        if ($this->tableExists('user') && $this->columnExists('user', 'updated_at')) {
            $this->addSql('ALTER TABLE user DROP COLUMN updated_at');
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
