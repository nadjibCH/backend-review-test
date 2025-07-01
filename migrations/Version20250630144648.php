<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250630144648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename create_at to created_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event RENAME COLUMN create_at TO created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "event" RENAME COLUMN created_at TO create_at');
    }
}
