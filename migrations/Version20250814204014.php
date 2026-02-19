<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250814230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronisation finale avec les entités : ajout occupant à lot, suppression occupant de coproprietaire, nettoyage etat_compteur, FK compteur';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'MySQL only.'
        );

        $sm = $this->connection->createSchemaManager();

        // ---------- LOT ----------
        if ($sm->tablesExist(['lot'])) {
            $lot = $sm->introspectTable('lot');
            $lotCols = array_map(fn($c) => $c->getName(), $lot->getColumns());
            if (!in_array('occupant', $lotCols, true)) {
                $this->addSql('ALTER TABLE lot ADD occupant VARCHAR(255) NOT NULL DEFAULT "N/A"');
            }
        }

        // ---------- COMPTEUR ----------
        if ($sm->tablesExist(['compteur'])) {
            $compteur = $sm->introspectTable('compteur');

            $hasFkLot = false;
            $hasFkEtat = false;
            foreach ($compteur->getForeignKeys() as $fk) {
                if (in_array('lot_id', $fk->getLocalColumns(), true)) {
                    $hasFkLot = true;
                }
                if (in_array('etat_compteur_id', $fk->getLocalColumns(), true)) {
                    $hasFkEtat = true;
                }
            }
            if (!$hasFkEtat) {
                $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5C74F30C7 FOREIGN KEY (etat_compteur_id) REFERENCES etat_compteur (id)');
            }
            if (!$hasFkLot) {
                $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD5A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id)');
            }
        }

        // ---------- ETAT_COMPTEUR ----------
        if ($sm->tablesExist(['etat_compteur'])) {
            $etat = $sm->introspectTable('etat_compteur');
            $etatCols = array_map(fn($c) => $c->getName(), $etat->getColumns());

            // Supprimer index unique sur code si présent
            foreach ($etat->getIndexes() as $idx) {
                if ($idx->isUnique() && $idx->getColumns() === ['code']) {
                    $this->addSql('DROP INDEX ' . $idx->getName() . ' ON etat_compteur');
                }
            }

            // Supprimer colonnes obsolètes
            $dropCols = [
                'actif','display_order','requires_index_n','requires_forfait',
                'requires_index_demonte','requires_index_nouveau','requires_commentaire','consumption_formula'
            ];
            foreach ($dropCols as $col) {
                if (in_array($col, $etatCols, true)) {
                    $this->addSql("ALTER TABLE etat_compteur DROP $col");
                }
            }

            // Adapter la taille de code
            $this->addSql('ALTER TABLE etat_compteur CHANGE code code VARCHAR(50) NOT NULL');
        }

        // ---------- COPROPRIETAIRE ----------
        if ($sm->tablesExist(['coproprietaire'])) {
            $cop = $sm->introspectTable('coproprietaire');
            $copCols = array_map(fn($c) => $c->getName(), $cop->getColumns());
            if (in_array('occupant', $copCols, true)) {
                $this->addSql('ALTER TABLE coproprietaire DROP occupant');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'MySQL only.'
        );

        // Retour arrière approximatif
        try { $this->addSql('ALTER TABLE lot DROP occupant'); } catch (\Throwable $e) {}
        try { $this->addSql('ALTER TABLE coproprietaire ADD occupant VARCHAR(255) DEFAULT NULL'); } catch (\Throwable $e) {}
        try { $this->addSql('ALTER TABLE etat_compteur CHANGE code code VARCHAR(255) NOT NULL'); } catch (\Throwable $e) {}
    }
}
