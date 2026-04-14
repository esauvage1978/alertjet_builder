<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projets : description (texte) et accent_color (#RRGGBB).';
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
                'ALTER TABLE projects ADD COLUMN description CLOB DEFAULT NULL',
            );
            $conn->executeStatement(
                "ALTER TABLE projects ADD COLUMN accent_color VARCHAR(7) NOT NULL DEFAULT '#64748b'",
            );
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement(
                'ALTER TABLE projects ADD description LONGTEXT DEFAULT NULL',
            );
            $conn->executeStatement(
                "ALTER TABLE projects ADD accent_color VARCHAR(7) NOT NULL DEFAULT '#64748b'",
            );
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
