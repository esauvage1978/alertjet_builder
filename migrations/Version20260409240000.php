<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unicité du nom de projet par organisation (index unique + dédoublonnage si besoin).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform || $platform instanceof AbstractMySQLPlatform) {
            $this->deduplicateProjectNamesPerOrganization();
        }

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('CREATE UNIQUE INDEX uniq_project_organization_name ON projects (organization_id, name)');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE UNIQUE INDEX uniq_project_organization_name ON projects (organization_id, name)');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('DROP INDEX uniq_project_organization_name');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('DROP INDEX uniq_project_organization_name ON projects');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    private function deduplicateProjectNamesPerOrganization(): void
    {
        $conn = $this->connection;
        $dupGroups = $conn->fetchAllAssociative(
            'SELECT organization_id, name FROM projects WHERE organization_id IS NOT NULL GROUP BY organization_id, name HAVING COUNT(*) > 1',
        );

        foreach ($dupGroups as $row) {
            $orgId = $row['organization_id'];
            $name = $row['name'];
            $allIds = $conn->fetchFirstColumn(
                'SELECT id FROM projects WHERE organization_id = ? AND name = ? ORDER BY id ASC',
                [$orgId, $name],
            );
            if (\count($allIds) < 2) {
                continue;
            }
            array_shift($allIds);
            foreach ($allIds as $dupId) {
                $newName = $name.' ('.$dupId.')';
                $conn->executeStatement(
                    'UPDATE projects SET name = ? WHERE id = ?',
                    [$newName, $dupId],
                );
            }
        }
    }
}
