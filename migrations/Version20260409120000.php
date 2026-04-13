<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations : jeton public opaque (public_token) pour les URLs à la place de l’ID numérique.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE organizations ADD COLUMN public_token VARCHAR(32) DEFAULT NULL');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE organizations ADD public_token VARCHAR(32) DEFAULT NULL');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;
        $ids = $conn->fetchFirstColumn('SELECT id FROM organizations');
        foreach ($ids as $id) {
            $conn->executeStatement(
                'UPDATE organizations SET public_token = ? WHERE id = ?',
                [bin2hex(random_bytes(16)), $id],
            );
        }

        $platform = $conn->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement('ALTER TABLE organizations MODIFY public_token VARCHAR(32) NOT NULL');
            $conn->executeStatement('CREATE UNIQUE INDEX UNIQ_organizations_public_token ON organizations (public_token)');
        }

        if ($platform instanceof SQLitePlatform) {
            $conn->executeStatement('CREATE UNIQUE INDEX UNIQ_organizations_public_token ON organizations (public_token)');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->abortIf(true, 'SQLite : retirer public_token nécessite une reconstruction de table.');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('DROP INDEX UNIQ_organizations_public_token ON organizations');
            $this->addSql('ALTER TABLE organizations DROP COLUMN public_token');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }
}
