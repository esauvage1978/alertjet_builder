<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tickets : ITIL (type/source/status + timestamps + raisons).';
    }

    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;
        $platform = $conn->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $conn->executeStatement("ALTER TABLE tickets ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'incident'");
            $conn->executeStatement("ALTER TABLE tickets ADD COLUMN acknowledged_at DATETIME DEFAULT NULL");
            $conn->executeStatement("ALTER TABLE tickets ADD COLUMN closed_at DATETIME DEFAULT NULL");
            $conn->executeStatement("ALTER TABLE tickets ADD COLUMN cancelled_at DATETIME DEFAULT NULL");
            $conn->executeStatement("ALTER TABLE tickets ADD COLUMN on_hold_reason CLOB DEFAULT NULL");
            $conn->executeStatement("ALTER TABLE tickets ADD COLUMN cancel_reason CLOB DEFAULT NULL");
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement("ALTER TABLE tickets ADD type VARCHAR(20) NOT NULL DEFAULT 'incident'");
            $conn->executeStatement('ALTER TABLE tickets ADD acknowledged_at DATETIME DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE tickets ADD closed_at DATETIME DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE tickets ADD cancelled_at DATETIME DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE tickets ADD on_hold_reason LONGTEXT DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE tickets ADD cancel_reason LONGTEXT DEFAULT NULL');
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }

        // Migration des valeurs existantes.
        $conn->executeStatement("UPDATE tickets SET type = 'incident' WHERE type IS NULL OR type = ''");
        $conn->executeStatement("UPDATE tickets SET source = 'webhook' WHERE source IS NULL OR source = ''");
        $conn->executeStatement("UPDATE tickets SET status = 'new' WHERE status IN ('open')");
        // in_progress / resolved existent déjà et restent valides
    }

    public function down(Schema $schema): void
    {
    }
}

