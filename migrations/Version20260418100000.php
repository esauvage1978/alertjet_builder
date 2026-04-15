<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contacts e-mail par organisation ; tickets : lien contact + Message-ID message entrant.';
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
                'CREATE TABLE organization_contacts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    organization_id INTEGER NOT NULL,
                    email VARCHAR(180) NOT NULL,
                    display_name VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME DEFAULT NULL,
                    CONSTRAINT FK_oc_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                    CONSTRAINT uniq_organization_contact_org_email UNIQUE (organization_id, email)
                )',
            );
            $conn->executeStatement('CREATE INDEX IDX_oc_org ON organization_contacts (organization_id)');
            $conn->executeStatement(
                'ALTER TABLE tickets ADD COLUMN organization_contact_id INTEGER DEFAULT NULL REFERENCES organization_contacts (id) ON DELETE SET NULL',
            );
            $conn->executeStatement(
                'ALTER TABLE tickets ADD COLUMN incoming_email_message_id VARCHAR(255) DEFAULT NULL',
            );
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement(
                'CREATE TABLE organization_contacts (
                    id INT AUTO_INCREMENT NOT NULL,
                    organization_id INT NOT NULL,
                    email VARCHAR(180) NOT NULL,
                    display_name VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    INDEX IDX_oc_org (organization_id),
                    UNIQUE INDEX uniq_organization_contact_org_email (organization_id, email),
                    PRIMARY KEY(id),
                    CONSTRAINT FK_oc_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            );
            $conn->executeStatement('ALTER TABLE tickets ADD organization_contact_id INT DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE tickets ADD incoming_email_message_id VARCHAR(255) DEFAULT NULL');
            $conn->executeStatement(
                'ALTER TABLE tickets ADD CONSTRAINT FK_tickets_org_contact FOREIGN KEY (organization_contact_id) REFERENCES organization_contacts (id) ON DELETE SET NULL',
            );
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Retour arrière non pris en charge (organization_contacts / tickets).');
    }
}
