<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute parametre.active_saisie_year pour piloter explicitement l\'annee active de saisie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre ADD active_saisie_year INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre DROP active_saisie_year');
    }
}
