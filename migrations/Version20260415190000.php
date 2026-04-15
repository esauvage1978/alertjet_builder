<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contacts organisation : ajout validated_at (validation manuelle client).';
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
                'ALTER TABLE organization_contacts ADD COLUMN validated_at DATETIME DEFAULT NULL',
            );
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement(
                'ALTER TABLE organization_contacts ADD validated_at DATETIME DEFAULT NULL',
            );
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
    }
}

