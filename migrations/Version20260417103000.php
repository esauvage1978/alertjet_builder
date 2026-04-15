<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tickets : assignation à un membre du projet (assignee_id).';
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
                'ALTER TABLE tickets ADD COLUMN assignee_id INTEGER DEFAULT NULL REFERENCES users (id) ON DELETE SET NULL',
            );
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement('ALTER TABLE tickets ADD assignee_id INT DEFAULT NULL');
            $conn->executeStatement(
                'ALTER TABLE tickets ADD CONSTRAINT FK_54469DF4_assignee FOREIGN KEY (assignee_id) REFERENCES users (id) ON DELETE SET NULL',
            );
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Retour arrière non pris en charge (assignee_id).');
    }
}
