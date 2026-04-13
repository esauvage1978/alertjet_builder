<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ancienne valeur enterprise → pro (plans alignés site vitrine).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE organizations SET plan = 'pro' WHERE plan = 'enterprise'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Impossible de distinguer les lignes autrefois « enterprise » : migration irréversible.');
    }
}
