<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Affectation des utilisateurs aux projets pour le traitement des tickets (project_ticket_handler).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('CREATE TABLE project_ticket_handler (project_id INTEGER NOT NULL, user_id INTEGER NOT NULL, PRIMARY KEY(project_id, user_id), CONSTRAINT FK_B4A2A11C166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B4A2A11CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_B4A2A11CA76ED395 ON project_ticket_handler (user_id)');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE project_ticket_handler (project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B4A2A11CA76ED395 (user_id), PRIMARY KEY(project_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE project_ticket_handler ADD CONSTRAINT FK_B4A2A11C166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE project_ticket_handler ADD CONSTRAINT FK_B4A2A11CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

            return;
        }

        $this->abortIf(true, 'Plateforme SQL non supportée pour cette migration : '.$platform::class);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_ticket_handler');
    }
}
