<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250814205448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rendre la colonne occupant de lot NOT NULL';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'MySQL only.');

        // S'assurer qu'il n'y a pas de NULL avant de passer en NOT NULL
        $this->addSql("UPDATE lot SET occupant = 'N/A' WHERE occupant IS NULL");
        $this->addSql('ALTER TABLE lot CHANGE occupant occupant VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'MySQL only.');

        // Retour en NULLABLE
        $this->addSql('ALTER TABLE lot CHANGE occupant occupant VARCHAR(255) DEFAULT NULL');
    }
}
