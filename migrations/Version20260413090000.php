<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute stats_pivot_preset pour memoriser les tableaux croises par utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stats_pivot_preset (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, config JSON NOT NULL, saved_at DATETIME NOT NULL, INDEX IDX_STATS_PIVOT_PRESET_USER (user_id), UNIQUE INDEX uniq_stats_pivot_user_name (user_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stats_pivot_preset ADD CONSTRAINT FK_STATS_PIVOT_PRESET_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stats_pivot_preset');
    }
}
