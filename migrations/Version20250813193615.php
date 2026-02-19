<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250813193615 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5A8CBA5F7');
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5C74F30C7');
        $this->addSql('ALTER TABLE compteur ADD actif TINYINT(1) NOT NULL, DROP numero_compteur, CHANGE etat_compteur_id etat_compteur_id INT NOT NULL, CHANGE lot_id lot_id INT NOT NULL, CHANGE emplacement emplacement VARCHAR(255) NOT NULL, CHANGE photo numero_serie VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id)');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id)');
        $this->addSql('ALTER TABLE coproprietaire DROP INDEX UNIQ_1AB283E7A76ED395, ADD INDEX IDX_1AB283E7A76ED395 (user_id)');
        $this->addSql('DROP INDEX UNIQ_1AB283E7E7927C74 ON coproprietaire');
        $this->addSql('ALTER TABLE coproprietaire ADD prenom VARCHAR(255) NOT NULL, ADD telephone VARCHAR(255) NOT NULL, ADD occupant VARCHAR(255) NOT NULL, DROP date_entree, DROP date_sortie');
        $this->addSql('ALTER TABLE lot DROP FOREIGN KEY FK_B81291BFF2D1A27');
        $this->addSql('ALTER TABLE lot DROP occupant, CHANGE coproprietaire_id coproprietaire_id INT NOT NULL, CHANGE type_appartement type_appartement VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT FK_B81291BFF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lot DROP FOREIGN KEY FK_B81291BFF2D1A27');
        $this->addSql('ALTER TABLE lot ADD occupant VARCHAR(255) NOT NULL, CHANGE coproprietaire_id coproprietaire_id INT DEFAULT NULL, CHANGE type_appartement type_appartement VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT FK_B81291BFF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5C74F30C7');
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5A8CBA5F7');
        $this->addSql('ALTER TABLE compteur ADD numero_compteur VARCHAR(100) NOT NULL, DROP actif, CHANGE etat_compteur_id etat_compteur_id INT DEFAULT NULL, CHANGE lot_id lot_id INT DEFAULT NULL, CHANGE emplacement emplacement VARCHAR(50) NOT NULL, CHANGE numero_serie photo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE coproprietaire DROP INDEX IDX_1AB283E7A76ED395, ADD UNIQUE INDEX UNIQ_1AB283E7A76ED395 (user_id)');
        $this->addSql('ALTER TABLE coproprietaire ADD date_entree DATE DEFAULT NULL, ADD date_sortie DATE DEFAULT NULL, DROP prenom, DROP telephone, DROP occupant');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1AB283E7E7927C74 ON coproprietaire (email)');
    }
}
