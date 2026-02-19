<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812222909 extends AbstractMigration
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
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE lot DROP FOREIGN KEY FK_B81291BFF2D1A27');
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT FK_B81291BFF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83A8CBA5F7');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83AA3B9810');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83C74F30C7');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83FF2D1A27');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83AA3B9810 FOREIGN KEY (compteur_id) REFERENCES compteur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83FF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5C74F30C7');
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5A8CBA5F7');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lot DROP FOREIGN KEY FK_B81291BFF2D1A27');
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT FK_B81291BFF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83AA3B9810');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83A8CBA5F7');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83FF2D1A27');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83C74F30C7');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83AA3B9810 FOREIGN KEY (compteur_id) REFERENCES compteur (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83FF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
