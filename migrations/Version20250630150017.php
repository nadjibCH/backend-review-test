<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250630150017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on created_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_EVENT_CREATED_AT ON event (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX IDX_EVENT_CREATED_AT');
    }
}
