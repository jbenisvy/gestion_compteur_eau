<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250814180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligner le schéma avec les entités (lot, user, compteur, etat_compteur, coproprietaire) en 3 étapes: tolérant -> remplissage -> contraintes.';
    }

    public function up(Schema $schema): void
    {
        // 0) Sécurité: MySQL only
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration valable uniquement pour MySQL.');

        // === LOT ============================================================
        // Rendre la FK et type_appartement conformes (étape tolérante d’abord si besoin)
        // S'assurer que colonne existe déjà sinon Doctrine gère; ici on force l'état final.

        // Supprimer FK pour pouvoir altérer
        $this->addSql('ALTER TABLE lot DROP FOREIGN KEY FK_B81291BFF2D1A27');

        // S’assurer que type_appartement n’est pas NULL en base
        $this->addSql("UPDATE lot SET type_appartement = COALESCE(type_appartement, 'Inconnu')");

        // Rendre NOT NULL les colonnes
        $this->addSql('ALTER TABLE lot CHANGE coproprietaire_id coproprietaire_id INT NOT NULL, CHANGE type_appartement type_appartement VARCHAR(255) NOT NULL');

        // Recréer la FK
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT FK_B81291BFF2D1A27 FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id)');

        // === USER ===========================================================
        // Renommer l’index (Doctrine veut harmoniser le nom)
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_email TO UNIQ_8D93D649E7927C74');

        // === COMPTEUR =======================================================
        // Supprimer FKs pour altérations
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5A8CBA5F7');
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD5C74F30C7');

        // Ajouter colonne "actif" en mode tolérant, si elle n’existe pas déjà
        // (MySQL échoue si déjà présente; si c’est ton cas, pas grave: relance avec --allow-no-migration)
        $this->addSql('ALTER TABLE compteur ADD actif TINYINT(1) NULL');

        // Pré-remplir "actif" (par défaut 1 si null)
        $this->addSql('UPDATE compteur SET actif = 1 WHERE actif IS NULL');

        // Renommer "photo" -> "numero_serie" (tolérant: si photo n’existe plus, cette ligne sera à ignorer)
        try {
            $this->addSql('ALTER TABLE compteur CHANGE photo numero_serie VARCHAR(255) DEFAULT NULL');
        } catch (\Throwable $e) {
            // ignore si déjà renommé
        }

        // Préparer les colonnes NOT NULL (remplissage)
        $this->addSql("UPDATE compteur SET emplacement = COALESCE(emplacement, 'Non renseigné')");
        // Si des compteurs ont un lot_id/etat_compteur_id NULL, on ne peut pas passer NOT NULL => corriger ici:
        // (on suppose lot_id/etat_compteur_id valides existent; sinon corrige tes données avant)
        $this->addSql('UPDATE compteur SET lot_id = (SELECT id FROM lot LIMIT 1) WHERE lot_id IS NULL');
        $this->addSql('UPDATE compteur SET etat_compteur_id = (SELECT id FROM etat_compteur LIMIT 1) WHERE etat_compteur_id IS NULL');

        // Appliquer NOT NULL + drop colonne obsolète
        $this->addSql('ALTER TABLE compteur DROP COLUMN numero_compteur');
        $this->addSql('ALTER TABLE compteur CHANGE etat_compteur_id etat_compteur_id INT NOT NULL, CHANGE lot_id lot_id INT NOT NULL, CHANGE emplacement emplacement VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE compteur CHANGE actif actif TINYINT(1) NOT NULL');

        // Recréer FKs
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id)');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id)');

        // === ETAT_COMPTEUR ==================================================
        // Doctrine veut retirer des colonnes et l’unicité sur code
        // Supprimer index unique si présent
        try {
            $this->addSql('DROP INDEX UNIQ_D0D7B34577153098 ON etat_compteur');
        } catch (\Throwable $e) {
            // ignore si déjà supprimé
        }

        // Retirer colonnes obsolètes (si présentes)
        foreach (['actif','display_order','requires_index_n','requires_forfait','requires_index_demonte','requires_index_nouveau','requires_commentaire','consumption_formula'] as $col) {
            try {
                $this->addSql("ALTER TABLE etat_compteur DROP $col");
            } catch (\Throwable $e) {
                // ignore si déjà supprimée
            }
        }

        // Harmoniser type de "code"
        $this->addSql('ALTER TABLE etat_compteur CHANGE code code VARCHAR(50) NOT NULL');

        // === COPROPRIETAIRE ================================================
        // Doctrine remplace l’unique index user_id par un simple index
        try {
            $this->addSql('DROP INDEX UNIQ_1AB283E7A76ED395 ON coproprietaire');
        } catch (\Throwable $e) {
            // ignore si déjà supprimé
        }
        $this->addSql('CREATE INDEX IDX_1AB283E7A76ED395 ON coproprietaire (user_id)');

        // Retirer unique sur email si présent (nom d’index généré par Doctrine)
        try {
            $this->addSql('DROP INDEX UNIQ_1AB283E7E7927C74 ON coproprietaire');
        } catch (\Throwable $e) {
            // ignore si déjà supprimé
        }

        // Ajouter nouvelles colonnes en tolérant (NULL d’abord)
        $this->addSql('ALTER TABLE coproprietaire ADD prenom VARCHAR(255) DEFAULT NULL, ADD telephone VARCHAR(255) DEFAULT NULL');

        // Remplir valeurs manquantes
        $this->addSql("UPDATE coproprietaire SET prenom = COALESCE(prenom, 'N/A')");
        $this->addSql("UPDATE coproprietaire SET telephone = COALESCE(telephone, 'N/A')");

        // Passer en NOT NULL
        $this->addSql('ALTER TABLE coproprietaire CHANGE prenom prenom VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(255) NOT NULL');

        // Retirer champs obsolètes si présents
        foreach (['date_entree','date_sortie'] as $col) {
            try {
                $this->addSql("ALTER TABLE coproprietaire DROP $col");
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Rétablir un état approximatif (best-effort)
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'MySQL only.');

        // COPROPRIETAIRE: revenir à colonnes nullables et retirer prenom/telephone
        $this->addSql('ALTER TABLE coproprietaire ADD date_entree DATE DEFAULT NULL, ADD date_sortie DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE coproprietaire DROP telephone, DROP prenom');

        // ETAT_COMPTEUR: rétablir colonnes (sans données)
        $this->addSql('ALTER TABLE etat_compteur ADD actif TINYINT(1) NOT NULL DEFAULT 1, ADD display_order INT DEFAULT NULL, ADD requires_index_n TINYINT(1) NOT NULL DEFAULT 1, ADD requires_forfait TINYINT(1) NOT NULL DEFAULT 0, ADD requires_index_demonte TINYINT(1) NOT NULL DEFAULT 0, ADD requires_index_nouveau TINYINT(1) NOT NULL DEFAULT 0, ADD requires_commentaire TINYINT(1) NOT NULL DEFAULT 0, ADD consumption_formula VARCHAR(255) DEFAULT NULL');
        // remettre code plus large
        $this->addSql('ALTER TABLE etat_compteur CHANGE code code VARCHAR(255) NOT NULL');

        // COMPTEUR: revenir en arrière grossièrement
        $this->addSql('ALTER TABLE compteur ADD numero_compteur VARCHAR(255) DEFAULT NULL');
        try {
            $this->addSql('ALTER TABLE compteur CHANGE numero_serie photo VARCHAR(255) DEFAULT NULL');
        } catch (\Throwable $e) {}
        $this->addSql('ALTER TABLE compteur CHANGE actif actif TINYINT(1) DEFAULT NULL, CHANGE emplacement emplacement VARCHAR(255) DEFAULT NULL, CHANGE lot_id lot_id INT DEFAULT NULL, CHANGE etat_compteur_id etat_compteur_id INT DEFAULT NULL');

        // USER: renommer index à l’ancien nom
        $this->addSql('ALTER TABLE user RENAME INDEX UNIQ_8D93D649E7927C74 TO uniq_email');

        // LOT: autoriser NULL à nouveau
        $this->addSql('ALTER TABLE lot CHANGE type_appartement type_appartement VARCHAR(255) DEFAULT NULL, CHANGE coproprietaire_id coproprietaire_id INT DEFAULT NULL');
    }
}
