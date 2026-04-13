<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations « plan_exempt » : hors parcours / contraintes des offres vitrine.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE organizations ADD COLUMN plan_exempt BOOLEAN NOT NULL DEFAULT 0');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE organizations ADD plan_exempt TINYINT(1) NOT NULL DEFAULT 0');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->abortIf(true, 'SQLite : revenir en arrière nécessite recréer la table organizations.');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE organizations DROP plan_exempt');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }
}
