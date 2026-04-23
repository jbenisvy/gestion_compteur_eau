<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un verrou de saisie specifique au profil coproprietaire dans parametre.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('parametre')) {
            return;
        }

        $table = $schema->getTable('parametre');
        if (!$table->hasColumn('copro_saisie_bloquee')) {
            $this->addSql('ALTER TABLE parametre ADD copro_saisie_bloquee TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('parametre')) {
            return;
        }

        $table = $schema->getTable('parametre');
        if ($table->hasColumn('copro_saisie_bloquee')) {
            $this->addSql('ALTER TABLE parametre DROP copro_saisie_bloquee');
        }
    }
}
