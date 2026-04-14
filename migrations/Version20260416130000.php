<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accès client par organisation (organization_client_access).';
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
                'CREATE TABLE organization_client_access (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    organization_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT FK_oca_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                    CONSTRAINT FK_oca_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                    CONSTRAINT uniq_organization_client_access_org_user UNIQUE (organization_id, user_id)
                )',
            );
            $conn->executeStatement('CREATE INDEX IDX_oca_org ON organization_client_access (organization_id)');
            $conn->executeStatement('CREATE INDEX IDX_oca_user ON organization_client_access (user_id)');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement(
                'CREATE TABLE organization_client_access (
                    id INT AUTO_INCREMENT NOT NULL,
                    organization_id INT NOT NULL,
                    user_id INT NOT NULL,
                    created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    INDEX IDX_oca_org (organization_id),
                    INDEX IDX_oca_user (user_id),
                    PRIMARY KEY(id),
                    UNIQUE INDEX uniq_organization_client_access_org_user (organization_id, user_id),
                    CONSTRAINT FK_oca_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
                    CONSTRAINT FK_oca_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            );
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
