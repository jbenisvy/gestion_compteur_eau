<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250918185915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migre les releves legacy vers releve_new/releve_item (idempotent).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'MySQL only.');

        if (!$this->tableExists('releve') || !$this->tableExists('releve_new') || !$this->tableExists('releve_item')) {
            return;
        }

        // 1) Créer la fiche maître (annee, lot) si absente
        $this->addSql('
            INSERT IGNORE INTO releve_new (lot_id, annee, created_at, updated_at, verrouille)
            SELECT r.lot_id, r.annee, MIN(r.created_at), MAX(r.updated_at), 0
            FROM releve r
            GROUP BY r.annee, r.lot_id
        ');

        // 2) Insérer les lignes détail sans doublons (UNIQ_RELEVE_ITEM_MAIN)
        $this->addSql('
            INSERT INTO releve_item (
                releve_id, compteur_id,
                index_n1, index_n, index_compteur_demonte, index_nouveau_compteur,
                etat_id, forfait, commentaire, consommation,
                created_at, updated_at
            )
            SELECT rn.id AS releve_id, r.compteur_id,
                   r.index_n1, r.index_n, r.index_compteur_demonte, r.index_nouveau_compteur,
                   r.etat_id, 0, r.commentaire, r.consommation,
                   r.created_at, r.updated_at
            FROM releve r
            JOIN releve_new rn ON rn.annee = r.annee AND rn.lot_id = r.lot_id
            LEFT JOIN releve_item ri ON ri.releve_id = rn.id AND ri.compteur_id = r.compteur_id
            WHERE ri.id IS NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // Migration de données: rollback non fiable.
    }

    private function tableExists(string $tableName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $tableName]
        );

        return ((int) $count) > 0;
    }
}
