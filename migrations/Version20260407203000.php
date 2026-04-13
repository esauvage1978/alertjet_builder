<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initialisation environnement : display_name, environment_initialized_at, organizations.plan, projects.organization_id.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE users ADD COLUMN display_name VARCHAR(120) DEFAULT NULL');
            $this->addSql('ALTER TABLE users ADD COLUMN environment_initialized_at DATETIME DEFAULT NULL');
            $this->addSql('ALTER TABLE organizations ADD COLUMN plan VARCHAR(32) DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD COLUMN organization_id INTEGER DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_29212BA632E8AE ON projects (organization_id)');
            $this->addSql("UPDATE organizations SET plan = 'starter' WHERE plan IS NULL");
            $this->addSql('UPDATE users SET environment_initialized_at = created_at WHERE environment_initialized_at IS NULL AND id IN (SELECT user_id FROM user_organization)');
            $this->addSql("UPDATE users SET display_name = substr(email, 1, instr(email, '@') - 1) WHERE display_name IS NULL AND instr(email, '@') > 0 AND id IN (SELECT user_id FROM user_organization)");

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE users ADD display_name VARCHAR(120) DEFAULT NULL, ADD environment_initialized_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            $this->addSql('ALTER TABLE organizations ADD plan VARCHAR(32) DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD organization_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_29212BA632E8AE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE SET NULL');
            $this->addSql("UPDATE organizations SET plan = 'starter' WHERE plan IS NULL");
            $this->addSql('UPDATE users SET environment_initialized_at = created_at WHERE environment_initialized_at IS NULL AND id IN (SELECT user_id FROM user_organization)');
            $this->addSql("UPDATE users SET display_name = SUBSTRING_INDEX(email, '@', 1) WHERE display_name IS NULL AND id IN (SELECT user_id FROM user_organization)");

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_29212BA632E8AE');
            $this->addSql('DROP INDEX IDX_29212BA632E8AE ON projects');
            $this->addSql('ALTER TABLE projects DROP organization_id');
            $this->addSql('ALTER TABLE organizations DROP plan');
            $this->addSql('ALTER TABLE users DROP display_name, DROP environment_initialized_at');

            return;
        }

        $this->abortIf(true, 'Migration irréversible sur cette plateforme (SQLite).');
    }
}
