<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messages e-mail liés aux tickets (threading) + métadonnées + pièces jointes par message.';
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
                'CREATE TABLE ticket_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    ticket_id INTEGER NOT NULL,
                    sender_type VARCHAR(12) NOT NULL,
                    sender_email VARCHAR(255) NOT NULL,
                    content TEXT NOT NULL,
                    message_id VARCHAR(191) NOT NULL,
                    in_reply_to VARCHAR(191) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT FK_tm_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                    CONSTRAINT uniq_tm_message_id UNIQUE (message_id)
                )',
            );
            $conn->executeStatement('CREATE INDEX IDX_tm_ticket_created ON ticket_messages (ticket_id, created_at)');

            $conn->executeStatement(
                'CREATE TABLE ticket_email_meta (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    message_id INTEGER NOT NULL,
                    raw_headers TEXT NOT NULL,
                    references JSON DEFAULT NULL,
                    CONSTRAINT FK_tem_msg FOREIGN KEY (message_id) REFERENCES ticket_messages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                    CONSTRAINT uniq_tem_message UNIQUE (message_id)
                )',
            );

            $conn->executeStatement(
                'CREATE TABLE ticket_message_attachments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    message_id INTEGER NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    mime_type VARCHAR(128) DEFAULT NULL,
                    size_bytes INTEGER NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT FK_tma_msg FOREIGN KEY (message_id) REFERENCES ticket_messages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
                )',
            );
            $conn->executeStatement('CREATE INDEX IDX_tma_msg_created ON ticket_message_attachments (message_id, created_at)');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement(
                'CREATE TABLE ticket_messages (
                    id INT AUTO_INCREMENT NOT NULL,
                    ticket_id INT NOT NULL,
                    sender_type VARCHAR(12) NOT NULL,
                    sender_email VARCHAR(255) NOT NULL,
                    content LONGTEXT NOT NULL,
                    message_id VARCHAR(191) NOT NULL,
                    in_reply_to VARCHAR(191) DEFAULT NULL,
                    created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    UNIQUE INDEX uniq_tm_message_id (message_id),
                    INDEX IDX_tm_ticket_created (ticket_id, created_at),
                    PRIMARY KEY(id),
                    CONSTRAINT FK_tm_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            );

            $conn->executeStatement(
                'CREATE TABLE ticket_email_meta (
                    id INT AUTO_INCREMENT NOT NULL,
                    message_id INT NOT NULL,
                    raw_headers LONGTEXT NOT NULL,
                    references_json JSON DEFAULT NULL,
                    UNIQUE INDEX uniq_tem_message (message_id),
                    PRIMARY KEY(id),
                    CONSTRAINT FK_tem_msg FOREIGN KEY (message_id) REFERENCES ticket_messages (id) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            );

            $conn->executeStatement(
                'CREATE TABLE ticket_message_attachments (
                    id INT AUTO_INCREMENT NOT NULL,
                    message_id INT NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    mime_type VARCHAR(128) DEFAULT NULL,
                    size_bytes INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    INDEX IDX_tma_msg_created (message_id, created_at),
                    PRIMARY KEY(id),
                    CONSTRAINT FK_tma_msg FOREIGN KEY (message_id) REFERENCES ticket_messages (id) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            );
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Retour arrière non pris en charge (ticket_messages / ticket_email_meta / ticket_message_attachments).');
    }
}

