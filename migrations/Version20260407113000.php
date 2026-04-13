<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes utilisateurs (User) et journal d’audit détaillé (UserActionLog).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, email_verified_at DATETIME DEFAULT NULL, email_verification_token VARCHAR(64) DEFAULT NULL, email_verification_expires_at DATETIME DEFAULT NULL, password_reset_token VARCHAR(64) DEFAULT NULL, password_reset_expires_at DATETIME DEFAULT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
            $this->addSql('CREATE TABLE user_action_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, action VARCHAR(64) NOT NULL, details CLOB DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_B20B1498A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_B20B1498A76ED395 ON user_action_logs (user_id)');
            $this->addSql('CREATE INDEX user_action_log_user_created_idx ON user_action_logs (user_id, created_at)');
            $this->addSql('CREATE INDEX user_action_log_action_idx ON user_action_logs (action, created_at)');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', email_verification_token VARCHAR(64) DEFAULT NULL, email_verification_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', password_reset_token VARCHAR(64) DEFAULT NULL, password_reset_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE TABLE user_action_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, action VARCHAR(64) NOT NULL, details JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B20B1498A76ED395 (user_id), INDEX user_action_log_user_created_idx (user_id, created_at), INDEX user_action_log_action_idx (action, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE user_action_logs ADD CONSTRAINT FK_B20B1498A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non prise en charge pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_action_logs');
        $this->addSql('DROP TABLE users');
    }
}
