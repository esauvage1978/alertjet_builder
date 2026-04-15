<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMAP: stockage des rapports détaillés app:project:fetch-inbox (runs + lignes projets).';
    }

    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;
        $platform = $conn->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $conn->executeStatement(<<<'SQL'
CREATE TABLE imap_fetch_runs (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  duration_ms INTEGER NOT NULL DEFAULT 0,
  project_filter_id INTEGER DEFAULT NULL,
  retention_days INTEGER NOT NULL DEFAULT 30,
  total_organizations INTEGER NOT NULL DEFAULT 0,
  total_projects INTEGER NOT NULL DEFAULT 0,
  total_unseen INTEGER NOT NULL DEFAULT 0,
  total_tickets INTEGER NOT NULL DEFAULT 0,
  total_failures INTEGER NOT NULL DEFAULT 0
)
SQL);
            $conn->executeStatement('CREATE INDEX imap_fetch_runs_started_at_idx ON imap_fetch_runs (started_at)');

            $conn->executeStatement(<<<'SQL'
CREATE TABLE imap_fetch_run_projects (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  run_id INTEGER NOT NULL,
  organization_id INTEGER DEFAULT NULL,
  project_id INTEGER DEFAULT NULL,
  organization_name VARCHAR(180) NOT NULL,
  project_name VARCHAR(180) NOT NULL,
  imap_host VARCHAR(255) NOT NULL,
  imap_port INTEGER NOT NULL,
  imap_tls BOOLEAN NOT NULL DEFAULT 1,
  imap_mailbox VARCHAR(128) NOT NULL,
  unseen_count INTEGER NOT NULL DEFAULT 0,
  tickets_created INTEGER NOT NULL DEFAULT 0,
  failure_count INTEGER NOT NULL DEFAULT 0,
  connection_error CLOB DEFAULT NULL,
  mailbox_error CLOB DEFAULT NULL,
  failures_json CLOB DEFAULT NULL,
  CONSTRAINT FK_imap_fetch_run_projects_run FOREIGN KEY (run_id) REFERENCES imap_fetch_runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
  CONSTRAINT FK_imap_fetch_run_projects_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
  CONSTRAINT FK_imap_fetch_run_projects_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
)
SQL);
            $conn->executeStatement('CREATE INDEX imap_fetch_run_projects_run_idx ON imap_fetch_run_projects (run_id)');
            $conn->executeStatement('CREATE INDEX imap_fetch_run_projects_org_idx ON imap_fetch_run_projects (organization_id)');
            $conn->executeStatement('CREATE INDEX imap_fetch_run_projects_project_idx ON imap_fetch_run_projects (project_id)');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement(<<<'SQL'
CREATE TABLE imap_fetch_runs (
  id INT AUTO_INCREMENT NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  duration_ms INT NOT NULL DEFAULT 0,
  project_filter_id INT DEFAULT NULL,
  retention_days INT NOT NULL DEFAULT 30,
  total_organizations INT NOT NULL DEFAULT 0,
  total_projects INT NOT NULL DEFAULT 0,
  total_unseen INT NOT NULL DEFAULT 0,
  total_tickets INT NOT NULL DEFAULT 0,
  total_failures INT NOT NULL DEFAULT 0,
  INDEX imap_fetch_runs_started_at_idx (started_at),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
            $conn->executeStatement(<<<'SQL'
CREATE TABLE imap_fetch_run_projects (
  id INT AUTO_INCREMENT NOT NULL,
  run_id INT NOT NULL,
  organization_id INT DEFAULT NULL,
  project_id INT DEFAULT NULL,
  organization_name VARCHAR(180) NOT NULL,
  project_name VARCHAR(180) NOT NULL,
  imap_host VARCHAR(255) NOT NULL,
  imap_port INT NOT NULL,
  imap_tls TINYINT(1) NOT NULL DEFAULT 1,
  imap_mailbox VARCHAR(128) NOT NULL,
  unseen_count INT NOT NULL DEFAULT 0,
  tickets_created INT NOT NULL DEFAULT 0,
  failure_count INT NOT NULL DEFAULT 0,
  connection_error LONGTEXT DEFAULT NULL,
  mailbox_error LONGTEXT DEFAULT NULL,
  failures_json JSON DEFAULT NULL,
  INDEX imap_fetch_run_projects_run_idx (run_id),
  INDEX imap_fetch_run_projects_org_idx (organization_id),
  INDEX imap_fetch_run_projects_project_idx (project_id),
  PRIMARY KEY(id),
  CONSTRAINT FK_imap_fetch_run_projects_run FOREIGN KEY (run_id) REFERENCES imap_fetch_runs (id) ON DELETE CASCADE,
  CONSTRAINT FK_imap_fetch_run_projects_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE SET NULL,
  CONSTRAINT FK_imap_fetch_run_projects_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
    }
}

