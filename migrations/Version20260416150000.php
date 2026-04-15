<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projets : accent_text_color et accent_border_color (pastille).';
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
                "ALTER TABLE projects ADD COLUMN accent_text_color VARCHAR(7) NOT NULL DEFAULT '#ffffff'",
            );
            $conn->executeStatement(
                "ALTER TABLE projects ADD COLUMN accent_border_color VARCHAR(7) NOT NULL DEFAULT '#475569'",
            );
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement(
                "ALTER TABLE projects ADD accent_text_color VARCHAR(7) NOT NULL DEFAULT '#ffffff'",
            );
            $conn->executeStatement(
                "ALTER TABLE projects ADD accent_border_color VARCHAR(7) NOT NULL DEFAULT '#475569'",
            );
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
