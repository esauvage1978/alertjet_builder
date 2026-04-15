<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projets : SLA par type + clôture automatique (resolved -> closed).';
    }

    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;
        $platform = $conn->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $conn->executeStatement('ALTER TABLE projects ADD COLUMN sla_incident_ack_target_minutes INTEGER DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD COLUMN sla_problem_ack_target_minutes INTEGER DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD COLUMN sla_request_ack_target_minutes INTEGER DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD COLUMN sla_incident_resolve_target_minutes INTEGER DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD COLUMN sla_problem_resolve_target_minutes INTEGER DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD COLUMN sla_request_resolve_target_minutes INTEGER DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD COLUMN auto_close_resolved_after_hours INTEGER NOT NULL DEFAULT 48');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement('ALTER TABLE projects ADD sla_incident_ack_target_minutes INT DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD sla_problem_ack_target_minutes INT DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD sla_request_ack_target_minutes INT DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD sla_incident_resolve_target_minutes INT DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD sla_problem_resolve_target_minutes INT DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD sla_request_resolve_target_minutes INT DEFAULT NULL');
            $conn->executeStatement('ALTER TABLE projects ADD auto_close_resolved_after_hours INT NOT NULL DEFAULT 48');
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }

        // Defaults ITIL demandés : incident prise en compte 2h, résolution 48h (si non défini).
        try {
            $conn->executeStatement(
                "UPDATE projects SET sla_incident_ack_target_minutes = COALESCE(sla_incident_ack_target_minutes, 120), sla_incident_resolve_target_minutes = COALESCE(sla_incident_resolve_target_minutes, 2880)",
            );
        } catch (\Throwable) {
            // no-op (compat)
        }
    }

    public function down(Schema $schema): void
    {
    }
}

