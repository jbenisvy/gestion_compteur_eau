<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Renseigne type_appartement selon l'emplacement (Couloir Face/Gauche, Coursive 1/2, RDC)";
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        $this->addSql("
            UPDATE lot
            SET type_appartement = CASE
                WHEN LOWER(emplacement) LIKE '%couloir face%' THEN '5 pièces'
                WHEN LOWER(emplacement) LIKE '%couloir gauche%' THEN '3 pièces'
                WHEN LOWER(emplacement) LIKE '%coursive 1%' THEN '2 pièces ou studio'
                WHEN LOWER(emplacement) LIKE '%coursive 2%' THEN '4 pièces'
                WHEN LOWER(emplacement) LIKE 'rdc%' OR LOWER(emplacement) LIKE '% rdc%' THEN 'Bureaux'
                ELSE type_appartement
            END
        ");
    }

    public function down(Schema $schema): void
    {
        // Pas de rollback fiable de données métier.
    }
}
