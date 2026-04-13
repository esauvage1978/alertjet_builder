<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parcours invité : pending_profile_onboarding sur users.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE users ADD COLUMN pending_profile_onboarding BOOLEAN NOT NULL DEFAULT 0');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE users ADD pending_profile_onboarding TINYINT(1) NOT NULL DEFAULT 0');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->abortIf(true, 'Migration irréversible sur SQLite (DROP COLUMN).');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE users DROP pending_profile_onboarding');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }
}
