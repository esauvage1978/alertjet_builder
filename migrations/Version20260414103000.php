<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projets : webhook_integration_enabled (affichage intégration UI).';
    }

    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;
        $platform = $conn->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $conn->executeStatement(
                'ALTER TABLE projects ADD COLUMN webhook_integration_enabled BOOLEAN NOT NULL DEFAULT 1',
            );
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement(
                'ALTER TABLE projects ADD webhook_integration_enabled TINYINT(1) NOT NULL DEFAULT 1',
            );
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
