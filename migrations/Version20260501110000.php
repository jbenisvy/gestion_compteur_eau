<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs de double authentification TOTP sur user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD two_factor_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD two_factor_secret VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP two_factor_enabled, DROP two_factor_secret');
    }
}
