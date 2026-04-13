<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal des erreurs applicatives détaillées (consultation admin).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('CREATE TABLE application_error_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, exception_class VARCHAR(191) NOT NULL, message CLOB NOT NULL, code INTEGER NOT NULL, file VARCHAR(512) DEFAULT NULL, line INTEGER DEFAULT NULL, trace CLOB NOT NULL, previous_chain CLOB DEFAULT NULL, http_method VARCHAR(16) DEFAULT NULL, request_uri VARCHAR(2048) DEFAULT NULL, route VARCHAR(128) DEFAULT NULL, http_status INTEGER DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, context CLOB DEFAULT NULL, source VARCHAR(32) NOT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_C8E74152A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX app_error_log_created_idx ON application_error_logs (created_at)');
            $this->addSql('CREATE INDEX app_error_log_class_idx ON application_error_logs (exception_class, created_at)');
            $this->addSql('CREATE INDEX IDX_C8E74152A76ED395 ON application_error_logs (user_id)');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE application_error_logs (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', exception_class VARCHAR(191) NOT NULL, message LONGTEXT NOT NULL, code INT NOT NULL, file VARCHAR(512) DEFAULT NULL, line INT DEFAULT NULL, trace LONGTEXT NOT NULL, previous_chain JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', http_method VARCHAR(16) DEFAULT NULL, request_uri VARCHAR(2048) DEFAULT NULL, route VARCHAR(128) DEFAULT NULL, http_status INT DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, context JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', source VARCHAR(32) NOT NULL, user_id INT DEFAULT NULL, INDEX app_error_log_created_idx (created_at), INDEX app_error_log_class_idx (exception_class, created_at), INDEX IDX_C8E74152A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE application_error_logs ADD CONSTRAINT FK_C8E74152A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non prise en charge pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE application_error_logs');
    }
}
