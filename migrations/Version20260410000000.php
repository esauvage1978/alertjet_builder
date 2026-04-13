<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projet : messagerie IMAP (paramètres chiffrés), objectifs SLA, pièces jointes logique inbox.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE projects ADD COLUMN imap_enabled BOOLEAN DEFAULT 0 NOT NULL');
            $this->addSql('ALTER TABLE projects ADD COLUMN imap_host VARCHAR(255) DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD COLUMN imap_port INTEGER DEFAULT 993 NOT NULL');
            $this->addSql('ALTER TABLE projects ADD COLUMN imap_tls BOOLEAN DEFAULT 1 NOT NULL');
            $this->addSql('ALTER TABLE projects ADD COLUMN imap_username VARCHAR(255) DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD COLUMN imap_password_cipher CLOB DEFAULT NULL');
            $this->addSql("ALTER TABLE projects ADD COLUMN imap_mailbox VARCHAR(128) DEFAULT 'INBOX' NOT NULL");
            $this->addSql('ALTER TABLE projects ADD COLUMN sla_ack_target_minutes INTEGER DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD COLUMN sla_resolve_target_minutes INTEGER DEFAULT NULL');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE projects ADD imap_enabled TINYINT(1) DEFAULT 0 NOT NULL');
            $this->addSql('ALTER TABLE projects ADD imap_host VARCHAR(255) DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD imap_port INT DEFAULT 993 NOT NULL');
            $this->addSql('ALTER TABLE projects ADD imap_tls TINYINT(1) DEFAULT 1 NOT NULL');
            $this->addSql('ALTER TABLE projects ADD imap_username VARCHAR(255) DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD imap_password_cipher LONGTEXT DEFAULT NULL');
            $this->addSql("ALTER TABLE projects ADD imap_mailbox VARCHAR(128) DEFAULT 'INBOX' NOT NULL");
            $this->addSql('ALTER TABLE projects ADD sla_ack_target_minutes INT DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD sla_resolve_target_minutes INT DEFAULT NULL');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->abortIf(true, 'Migration irréversible sur SQLite (DROP COLUMN multiple).');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE projects DROP imap_enabled, DROP imap_host, DROP imap_port, DROP imap_tls, DROP imap_username, DROP imap_password_cipher, DROP imap_mailbox, DROP sla_ack_target_minutes, DROP sla_resolve_target_minutes');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }
}
