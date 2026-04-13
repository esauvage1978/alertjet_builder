<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations et rattachement utilisateurs (user_organization).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_427C1C7F5E237E06 ON organizations (name)');
            $this->addSql('CREATE TABLE user_organization (user_id INTEGER NOT NULL, organization_id INTEGER NOT NULL, PRIMARY KEY(user_id, organization_id), CONSTRAINT FK_9A0F58A8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9A0F58A832E8AE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_9A0F58A832E8AE ON user_organization (organization_id)');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE organizations (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_427C1C7F5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE TABLE user_organization (user_id INT NOT NULL, organization_id INT NOT NULL, INDEX IDX_9A0F58A832E8AE (organization_id), PRIMARY KEY(user_id, organization_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE user_organization ADD CONSTRAINT FK_9A0F58A8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE user_organization ADD CONSTRAINT FK_9A0F58A832E8AE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_organization');
        $this->addSql('DROP TABLE organizations');
    }
}
