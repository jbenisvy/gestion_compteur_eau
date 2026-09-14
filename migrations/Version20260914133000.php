<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recalcule index_virtuel avec cumul des forfaits anterieurs et conserve index_n reel.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('releve_item') || !$this->columnExists('releve_item', 'index_virtuel')) {
            return;
        }

        $this->addSql('
            UPDATE releve_item
            SET index_n = index_n1
            WHERE forfait = 1
              AND index_n IS NULL
              AND index_n1 IS NOT NULL
        ');

        $this->addSql('
            UPDATE releve_item ri
            LEFT JOIN (
                SELECT
                    ri_target.id AS target_id,
                    COALESCE(SUM(CAST(ROUND(COALESCE(ri_forfait.consommation, 0)) AS SIGNED)), 0) AS forfaits_cumules
                FROM releve_item ri_target
                INNER JOIN releve_new r_target ON r_target.id = ri_target.releve_id
                LEFT JOIN releve_item ri_forfait
                    ON ri_forfait.compteur_id = ri_target.compteur_id
                   AND ri_forfait.forfait = 1
                LEFT JOIN releve_new r_forfait
                    ON r_forfait.id = ri_forfait.releve_id
                   AND r_forfait.annee <= r_target.annee
                GROUP BY ri_target.id
            ) cumul ON cumul.target_id = ri.id
            SET ri.index_virtuel = CASE
                WHEN ri.forfait = 1 AND ri.index_n1 IS NOT NULL THEN ri.index_n1 + COALESCE(cumul.forfaits_cumules, 0)
                WHEN ri.index_n IS NOT NULL THEN ri.index_n
                ELSE ri.index_nouveau_compteur
            END
        ');
    }

    public function down(Schema $schema): void
    {
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
