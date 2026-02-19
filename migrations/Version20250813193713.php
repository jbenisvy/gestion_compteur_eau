<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration sécurisée pour gérer la colonne numero_serie sur la table `compteur`
 * sans supposer l'existence préalable de la colonne `photo`.
 *
 * - Si `photo` existe et `numero_serie` n'existe pas : on renomme `photo` -> `numero_serie`.
 * - Si `photo` n'existe pas et `numero_serie` n'existe pas : on ajoute `numero_serie`.
 * - Si `numero_serie` existe déjà : on ne fait rien.
 */
final class Version20250813193713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rendre robuste la transition photo -> numero_serie dans compteur.';
    }

    public function up(Schema $schema): void
    {
        // MySQL uniquement (ajuste si besoin)
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        // Introspection du schéma courant
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('compteur');
        $columns = array_map(static fn($c) => $c->getName(), $table->getColumns());

        $hasPhoto       = in_array('photo',       $columns, true);
        $hasNumeroSerie = in_array('numero_serie', $columns, true);

        if ($hasPhoto && !$hasNumeroSerie) {
            // Renommer proprement si "photo" existe encore
            $this->addSql('ALTER TABLE compteur CHANGE photo numero_serie VARCHAR(255) DEFAULT NULL');
        } elseif (!$hasNumeroSerie) {
            // Sinon, simplement ajouter la colonne cible
            $this->addSql('ALTER TABLE compteur ADD numero_serie VARCHAR(255) DEFAULT NULL');
        }
        // Si numero_serie existe déjà, ne rien faire.
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration valable uniquement pour MySQL.'
        );

        // Introspection du schéma courant
        $sm = $this->connection->createSchemaManager();
        $table = $sm->introspectTable('compteur');
        $columns = array_map(static fn($c) => $c->getName(), $table->getColumns());

        $hasPhoto       = in_array('photo',       $columns, true);
        $hasNumeroSerie = in_array('numero_serie', $columns, true);

        // Stratégie de retour "best-effort":
        // - Si numero_serie existe et photo n'existe pas : on renomme numero_serie -> photo
        // - Sinon, si numero_serie existe : on la supprime
        if ($hasNumeroSerie && !$hasPhoto) {
            $this->addSql('ALTER TABLE compteur CHANGE numero_serie photo VARCHAR(255) DEFAULT NULL');
        } elseif ($hasNumeroSerie) {
            $this->addSql('ALTER TABLE compteur DROP numero_serie');
        }
    }
}
