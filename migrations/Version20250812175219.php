<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812175219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D0D7B34577153098 ON etat_compteur (code)');
        $this->addSql('ALTER TABLE parametre ADD annee INT DEFAULT NULL, ADD forfait_ef DOUBLE PRECISION NOT NULL, ADD forfait_ec DOUBLE PRECISION NOT NULL, DROP cle, DROP valeur');
        $this->addSql('ALTER TABLE releve ADD index_nouveau DOUBLE PRECISION DEFAULT NULL, ADD numero_nouveau_compteur VARCHAR(50) DEFAULT NULL, ADD occupant_annee VARCHAR(255) NOT NULL, CHANGE index_n index_n DOUBLE PRECISION DEFAULT NULL, CHANGE index_nmoins1 index_nmoins1 DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_D0D7B34577153098 ON etat_compteur');
        $this->addSql('ALTER TABLE releve DROP index_nouveau, DROP numero_nouveau_compteur, DROP occupant_annee, CHANGE index_n index_n DOUBLE PRECISION NOT NULL, CHANGE index_nmoins1 index_nmoins1 DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE parametre ADD cle VARCHAR(100) NOT NULL, ADD valeur VARCHAR(255) NOT NULL, DROP annee, DROP forfait_ef, DROP forfait_ec');
    }
}
