<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406200342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(180) NOT NULL, webhook_token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C93B3A4D5E74442 ON projects (webhook_token)');
        $this->addSql('CREATE TABLE ticket_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(32) NOT NULL, message CLOB NOT NULL, context CLOB DEFAULT NULL, created_at DATETIME NOT NULL, ticket_id INTEGER NOT NULL, CONSTRAINT FK_C254D364700047D2 FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C254D364700047D2 ON ticket_logs (ticket_id)');
        $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, priority VARCHAR(20) NOT NULL, source VARCHAR(32) NOT NULL, fingerprint VARCHAR(64) NOT NULL, event_count INTEGER DEFAULT 1 NOT NULL, silenced BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, project_id INTEGER NOT NULL, CONSTRAINT FK_54469DF4166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4166D1F9C ON tickets (project_id)');
        $this->addSql('CREATE INDEX ticket_fingerprint_idx ON tickets (project_id, fingerprint, status)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE ticket_logs');
        $this->addSql('DROP TABLE tickets');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
