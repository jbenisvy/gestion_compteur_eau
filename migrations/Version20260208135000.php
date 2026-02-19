<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208135000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute lot_coproprietaire (historique), migre les liens existants, rend lot.coproprietaire_id nullable';
    }

    public function up(Schema $schema): void
    {
        // 1) table d’historique lot <-> copropriétaire
        $this->addSql('
            CREATE TABLE lot_coproprietaire (
                id INT AUTO_INCREMENT NOT NULL,
                lot_id INT NOT NULL,
                coproprietaire_id INT NOT NULL,
                date_debut DATE NOT NULL,
                date_fin DATE DEFAULT NULL,
                is_principal TINYINT(1) NOT NULL,
                commentaire VARCHAR(255) DEFAULT NULL,
                INDEX IDX_LOT_COPRO_LOT (lot_id),
                INDEX IDX_LOT_COPRO_COPRO (coproprietaire_id),
                INDEX IDX_LOT_COPRO_LOT_DEBUT (lot_id, date_debut),
                INDEX IDX_LOT_COPRO_LOT_FIN (lot_id, date_fin),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
        $this->addSql('ALTER TABLE lot_coproprietaire ADD CONSTRAINT FK_LOT_COPRO_LOT FOREIGN KEY (lot_id) REFERENCES lot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lot_coproprietaire ADD CONSTRAINT FK_LOT_COPRO_COPRO FOREIGN KEY (coproprietaire_id) REFERENCES coproprietaire (id) ON DELETE CASCADE');

        // 2) migration des liens existants (date_debut = 2017-01-01)
        $this->addSql("
            INSERT INTO lot_coproprietaire (lot_id, coproprietaire_id, date_debut, date_fin, is_principal, commentaire)
            SELECT id, coproprietaire_id, '2017-01-01', NULL, 1, NULL
            FROM lot
            WHERE coproprietaire_id IS NOT NULL
        ");

        // 3) on rend la colonne legacy nullable
        $this->addSql('ALTER TABLE lot MODIFY coproprietaire_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // rollback minimal
        $this->addSql('DROP TABLE lot_coproprietaire');
        $this->addSql('ALTER TABLE lot MODIFY coproprietaire_id INT NOT NULL');
    }
}
