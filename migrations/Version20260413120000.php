<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Jeton organisation : 12 caractères hex (au lieu de 32) avec unicité conservée.
 */
final class Version20260413120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations : public_token limité à 12 caractères hexadécimaux (régénération si besoin).';
    }

    public function up(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;
        $platform = $conn->getDatabasePlatform();

        $this->reassignOrganizationTokensIfNeeded($conn);

        if ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement('ALTER TABLE organizations MODIFY public_token VARCHAR(12) NOT NULL');
        }

        if ($platform instanceof SQLitePlatform) {
            // La colonne reste logiquement large en SQLite ; les valeurs font 12 caractères.
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Retour arrière non pris en charge (longueur des jetons).');
    }

    /**
     * Remplace les jetons invalides ou en doublon par des hex sur 12 caractères uniques.
     *
     * @param \Doctrine\DBAL\Connection $conn
     */
    private function reassignOrganizationTokensIfNeeded(object $conn): void
    {
        $rows = $conn->fetchAllAssociative('SELECT id, public_token FROM organizations ORDER BY id ASC');
        if ($rows === []) {
            return;
        }

        /** @var array<string, true> $usedTokens */
        $usedTokens = [];
        /** @var array<int, string> $idToToken */
        $idToToken = [];

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
                $usedTokens[$t] = true;
                $idToToken[$id] = $t;
            }
        }

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (isset($idToToken[$id])) {
                continue;
            }

            do {
                $candidate = bin2hex(random_bytes(6));
            } while (isset($usedTokens[$candidate]));

            $usedTokens[$candidate] = true;
            $idToToken[$id] = $candidate;
        }

        foreach ($idToToken as $id => $token) {
            $conn->update('organizations', ['public_token' => $token], ['id' => $id]);
        }
    }
}
