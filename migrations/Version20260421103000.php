<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les prix annuels du m3 pour l eau froide et l eau chaude dans parametre.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        if (!$this->tableExists('parametre')) {
            return;
        }

        if (!$this->columnExists('parametre', 'prix_m3_ef')) {
            $this->addSql('ALTER TABLE parametre ADD prix_m3_ef DOUBLE PRECISION DEFAULT NULL');
        }

        if (!$this->columnExists('parametre', 'prix_m3_ec')) {
            $this->addSql('ALTER TABLE parametre ADD prix_m3_ec DOUBLE PRECISION DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        if (!$this->tableExists('parametre')) {
            return;
        }

        if ($this->columnExists('parametre', 'prix_m3_ef')) {
            $this->addSql('ALTER TABLE parametre DROP prix_m3_ef');
        }

        if ($this->columnExists('parametre', 'prix_m3_ec')) {
            $this->addSql('ALTER TABLE parametre DROP prix_m3_ec');
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
