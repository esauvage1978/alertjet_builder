<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Jeton d’invitation organisation (lien unique 24h) sur users.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE users ADD COLUMN organization_invite_token VARCHAR(64) DEFAULT NULL');
            $this->addSql('ALTER TABLE users ADD COLUMN organization_invite_expires_at DATETIME DEFAULT NULL');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE users ADD organization_invite_token VARCHAR(64) DEFAULT NULL');
            $this->addSql('ALTER TABLE users ADD organization_invite_expires_at DATETIME DEFAULT NULL');

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
            $this->addSql('ALTER TABLE users DROP organization_invite_token');
            $this->addSql('ALTER TABLE users DROP organization_invite_expires_at');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }
}
