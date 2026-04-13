<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Projets : jeton public 12 caractères hex, unique (URLs).
 */
final class Version20260413140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projets : public_token VARCHAR(12) unique avec affectation pour les lignes existantes.';
    }

    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;
        $platform = $conn->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $conn->executeStatement('ALTER TABLE projects ADD COLUMN public_token VARCHAR(12) DEFAULT NULL');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement('ALTER TABLE projects ADD public_token VARCHAR(12) DEFAULT NULL');
        } else {
            $this->abortIf(true, 'Plateforme SQL non prise en charge : '.$platform::class);
        }

        $this->assignUniqueProjectPublicTokens($conn);

        if ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement('ALTER TABLE projects MODIFY public_token VARCHAR(12) NOT NULL');
            $conn->executeStatement('CREATE UNIQUE INDEX UNIQ_projects_public_token ON projects (public_token)');
        }

        if ($platform instanceof SQLitePlatform) {
            $conn->executeStatement('CREATE UNIQUE INDEX UNIQ_projects_public_token ON projects (public_token)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Retour arrière non pris en charge (public_token).');
    }

    private function assignUniqueProjectPublicTokens(Connection $conn): void
    {
        $rows = $conn->fetchAllAssociative('SELECT id, public_token FROM projects ORDER BY id ASC');
        if ($rows === []) {
            return;
        }

        /** @var array<string, true> $used */
        $used = [];
        /** @var array<int, string> $assignments */
        $assignments = [];

        $valid12 = static function (mixed $t): bool {
            return \is_string($t) && \strlen($t) === 12 && 1 === preg_match('/^[a-f0-9]{12}$/', $t);
        };

        $byToken = [];
        foreach ($rows as $row) {
            $t = isset($row['public_token']) ? (string) $row['public_token'] : '';
            if ($valid12($t)) {
                $byToken[$t][] = (int) $row['id'];
            }
        }

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $t = isset($row['public_token']) ? (string) $row['public_token'] : '';
            if ($valid12($t) && isset($byToken[$t]) && \count($byToken[$t]) === 1) {
                $used[$t] = true;
                $assignments[$id] = $t;
            }
        }

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (isset($assignments[$id])) {
                continue;
            }

            do {
                $candidate = bin2hex(random_bytes(6));
            } while (isset($used[$candidate]));

            $used[$candidate] = true;
            $assignments[$id] = $candidate;
        }

        foreach ($assignments as $id => $token) {
            $conn->update('projects', ['public_token' => $token], ['id' => $id]);
        }
    }
}
