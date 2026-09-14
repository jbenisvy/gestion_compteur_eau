<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914134500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corrige le recalcul index_virtuel avec forfaits historiques parametres si consommation nulle.';
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

        $this->addSql(<<<'SQL'
            UPDATE releve_item ri
            LEFT JOIN (
                SELECT
                    ri_target.id AS target_id,
                    COALESCE(SUM(
                        CASE
                            WHEN ri_forfait.consommation IS NOT NULL AND CAST(ri_forfait.consommation AS DECIMAL(10,3)) > 0
                                THEN CAST(ROUND(ri_forfait.consommation) AS SIGNED)
                            WHEN c_forfait.type = 'EF'
                                THEN CAST(ROUND(COALESCE(py.forfait_ef, pd.forfait_ef, 150)) AS SIGNED)
                            ELSE CAST(ROUND(COALESCE(py.forfait_ec, pd.forfait_ec, 75)) AS SIGNED)
                        END
                    ), 0) AS forfaits_cumules
                FROM releve_item ri_target
                INNER JOIN releve_new r_target ON r_target.id = ri_target.releve_id
                INNER JOIN releve_item ri_forfait
                    ON ri_forfait.compteur_id = ri_target.compteur_id
                   AND ri_forfait.forfait = 1
                INNER JOIN releve_new r_forfait
                    ON r_forfait.id = ri_forfait.releve_id
                   AND r_forfait.annee <= r_target.annee
                INNER JOIN compteur c_forfait ON c_forfait.id = ri_forfait.compteur_id
                LEFT JOIN parametre py ON py.annee = r_forfait.annee
                LEFT JOIN parametre pd ON pd.id = (
                    SELECT pdefault.id
                    FROM parametre pdefault
                    WHERE pdefault.annee IS NULL
                    ORDER BY pdefault.id DESC
                    LIMIT 1
                )
                GROUP BY ri_target.id
            ) cumul ON cumul.target_id = ri.id
            SET ri.index_virtuel = CASE
                WHEN ri.forfait = 1 AND ri.index_n1 IS NOT NULL THEN ri.index_n1 + COALESCE(cumul.forfaits_cumules, 0)
                WHEN ri.index_n IS NOT NULL THEN ri.index_n
                ELSE ri.index_nouveau_compteur
            END
        SQL);
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
