<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250806101106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE compteur (id INT AUTO_INCREMENT NOT NULL, etat_compteur_id INT DEFAULT NULL, lot_id INT DEFAULT NULL, numero_compteur VARCHAR(100) NOT NULL, type VARCHAR(2) NOT NULL, emplacement VARCHAR(50) NOT NULL, date_installation DATE NOT NULL, photo VARCHAR(255) DEFAULT NULL, INDEX IDX_4D021BD5C74F30C7 (etat_compteur_id), INDEX IDX_4D021BD5A8CBA5F7 (lot_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE coproprietaire (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, date_entree DATE DEFAULT NULL, date_sortie DATE DEFAULT NULL, UNIQUE INDEX UNIQ_1AB283E7A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE etat_compteur (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lot (id INT AUTO_INCREMENT NOT NULL, coproprietaire_id INT DEFAULT NULL, numero_lot VARCHAR(50) NOT NULL, type_appartement VARCHAR(100) NOT NULL, emplacement VARCHAR(255) NOT NULL, tantieme INT NOT NULL, occupant VARCHAR(255) NOT NULL, INDEX IDX_B81291BFF2D1A27 (coproprietaire_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parametre (id INT AUTO_INCREMENT NOT NULL, cle VARCHAR(100) NOT NULL, valeur VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE releve (id INT AUTO_INCREMENT NOT NULL, compteur_id INT DEFAULT NULL, lot_id INT DEFAULT NULL, coproprietaire_id INT DEFAULT NULL, etat_compteur_id INT DEFAULT NULL, date_saisie DATETIME NOT NULL, annee INT NOT NULL, index_n DOUBLE PRECISION NOT NULL, index_nmoins1 DOUBLE PRECISION NOT NULL, consommation DOUBLE PRECISION DEFAULT NULL, forfait DOUBLE PRECISION DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, date_remplacement DATE DEFAULT NULL, index_demonte DOUBLE PRECISION DEFAULT NULL, INDEX IDX_DDABFF83AA3B9810 (compteur_id), INDEX IDX_DDABFF83A8CBA5F7 (lot_id), INDEX IDX_DDABFF83FF2D1A27 (coproprietaire_id), INDEX IDX_DDABFF83C74F30C7 (etat_compteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id)');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id)');
        $this->addSql('ALTER TABLE coproprietaire ADD CONSTRAINT FK_1AB283E7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT FK_B81291BFF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id)');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83AA3B9810 FOREIGN KEY (compteur_id) REFERENCES compteur (id)');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id)');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83FF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id)');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF83C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5C74F30C7');
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5A8CBA5F7');
        $this->addSql('ALTER TABLE coproprietaire DROP FOREIGN KEY FK_1AB283E7A76ED395');
        $this->addSql('ALTER TABLE lot DROP FOREIGN KEY FK_B81291BFF2D1A27');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83AA3B9810');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83A8CBA5F7');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83FF2D1A27');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF83C74F30C7');
        $this->addSql('DROP TABLE compteur');
        $this->addSql('DROP TABLE coproprietaire');
        $this->addSql('DROP TABLE etat_compteur');
        $this->addSql('DROP TABLE lot');
        $this->addSql('DROP TABLE parametre');
        $this->addSql('DROP TABLE releve');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
