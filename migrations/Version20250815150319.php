<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250815150319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration safe/idempotente pour la table releve : colonnes, defaults DATETIME valides, contraintes et index.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $table = 'releve';

        if (!$sm->tablesExist([$table])) {
            // Table absente : rien à faire (safe)
            return;
        }

        // ---- État actuel
        $columns     = array_change_key_case($sm->listTableColumns($table), CASE_LOWER);
        $indexes     = $sm->listTableIndexes($table);
        $foreignKeys = $sm->listTableForeignKeys($table);

        // ---- Helpers
        $hasColumn = function (string $col) use ($columns): bool {
            return array_key_exists(strtolower($col), $columns);
        };

        $hasIndexByName = function (string $name) use ($indexes): bool {
            foreach ($indexes as $idx) {
                if (strcasecmp($idx->getName(), $name) === 0) {
                    return true;
                }
            }
            return false;
        };

        $hasUniqueIndexOn = function (array $cols) use ($indexes): bool {
            $want = array_map('strtolower', $cols);
            sort($want);
            foreach ($indexes as $idx) {
                if (!$idx->isUnique()) {
                    continue;
                }
                $colNames = array_map('strtolower', $idx->getColumns());
                sort($colNames);
                if ($colNames === $want) {
                    return true;
                }
            }
            return false;
        };

        $findFkByName = function (string $name) use ($foreignKeys) {
            foreach ($foreignKeys as $fk) {
                if (strcasecmp($fk->getName(), $name) === 0) {
                    return $fk;
                }
            }
            return null;
        };

        $hasFkOnColumn = function (string $col) use ($foreignKeys): bool {
            $col = strtolower($col);
            foreach ($foreignKeys as $fk) {
                $local = array_map('strtolower', $fk->getLocalColumns());
                if (count($local) === 1 && $local[0] === $col) {
                    return true;
                }
            }
            return false;
        };

        // ---- 1) DROP FKs/INDEX hérités (uniquement s’ils existent)
        $oldFks = ['FK_DDABFF83C74F30C7', 'FK_DDABFF83FF2D1A27', 'FK_DDABFF83A8CBA5F7', 'FK_DDABFF83AA3B9810'];
        foreach ($oldFks as $fkName) {
            if ($findFkByName($fkName)) {
                $this->addSql("ALTER TABLE $table DROP FOREIGN KEY $fkName");
            }
        }

        $oldIdx = ['IDX_DDABFF83C74F30C7', 'IDX_DDABFF83FF2D1A27'];
        foreach ($oldIdx as $idxName) {
            if ($hasIndexByName($idxName)) {
                $this->addSql("DROP INDEX $idxName ON $table");
            }
        }

        // ---- 2) Ajout / modification des colonnes (avec DEFAULT pour DATETIME)
        if (!$hasColumn('etat_id')) {
            $this->addSql("ALTER TABLE $table ADD etat_id INT DEFAULT NULL");
        }
        if (!$hasColumn('index_n1')) {
            $this->addSql("ALTER TABLE $table ADD index_n1 INT DEFAULT NULL");
        }
        if (!$hasColumn('index_compteur_demonte')) {
            $this->addSql("ALTER TABLE $table ADD index_compteur_demonte INT DEFAULT NULL");
        }
        if (!$hasColumn('index_nouveau_compteur')) {
            $this->addSql("ALTER TABLE $table ADD index_nouveau_compteur INT DEFAULT NULL");
        }

        // DATETIME avec DEFAULT valides (MySQL strict)
        if (!$hasColumn('created_at')) {
            $this->addSql("ALTER TABLE $table ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)'");
        } else {
            $this->addSql("ALTER TABLE $table MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)'");
        }

        if (!$hasColumn('updated_at')) {
            $this->addSql("ALTER TABLE $table ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)'");
        } else {
            $this->addSql("ALTER TABLE $table MODIFY updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)'");
        }

        // Colonnes à supprimer (uniquement si présentes)
        $toDrop = [
            'coproprietaire_id', 'etat_compteur_id', 'date_saisie', 'index_nmoins1', 'forfait',
            'date_remplacement', 'index_demonte', 'index_nouveau', 'numero_nouveau_compteur', 'occupant_annee',
        ];
        foreach ($toDrop as $oldCol) {
            if ($hasColumn($oldCol)) {
                $this->addSql("ALTER TABLE $table DROP $oldCol");
            }
        }

        // Adaptations de type/contrainte si colonnes présentes
        if ($hasColumn('compteur_id')) {
            $this->addSql("ALTER TABLE $table MODIFY compteur_id INT NOT NULL");
        }
        if ($hasColumn('lot_id')) {
            $this->addSql("ALTER TABLE $table MODIFY lot_id INT NOT NULL");
        }
        if ($hasColumn('index_n')) {
            $this->addSql("ALTER TABLE $table MODIFY index_n INT DEFAULT NULL");
        }
        if ($hasColumn('consommation')) {
            $this->addSql("ALTER TABLE $table MODIFY consommation NUMERIC(10, 3) DEFAULT NULL");
        }

        // ---- 3) (Re)création FKs et index cibles (uniquement s’ils n’existent pas déjà)
        if ($hasColumn('etat_id') && !$hasFkOnColumn('etat_id')) {
            $this->addSql("ALTER TABLE $table ADD CONSTRAINT FK_DDABFF83D5E86FF FOREIGN KEY (etat_id) REFERENCES etat_compteur (id)");
        }
        if ($hasColumn('lot_id') && !$hasFkOnColumn('lot_id')) {
            $this->addSql("ALTER TABLE $table ADD CONSTRAINT FK_DDABFF83A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id)");
        }
        if ($hasColumn('compteur_id') && !$hasFkOnColumn('compteur_id')) {
            $this->addSql("ALTER TABLE $table ADD CONSTRAINT FK_DDABFF83AA3B9810 FOREIGN KEY (compteur_id) REFERENCES compteur (id)");
        }

        if ($hasColumn('etat_id') && !$hasIndexByName('IDX_DDABFF83D5E86FF')) {
            $this->addSql("CREATE INDEX IDX_DDABFF83D5E86FF ON $table (etat_id)");
        }

        if ($hasColumn('compteur_id') && $hasColumn('annee') && !$hasUniqueIndexOn(['compteur_id', 'annee'])) {
            $this->addSql("CREATE UNIQUE INDEX uniq_compteur_annee ON $table (compteur_id, annee)");
        }
    }

    public function down(Schema $schema): void
    {
        // Mode safe : pas de rollback automatique pour éviter les pertes de données.
        // Si besoin, créer une migration dédiée sur l’état courant.
    }
}
