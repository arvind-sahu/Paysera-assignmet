<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240522000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_at index on transfers for recent/list queries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_transfers_created_at ON transfers (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_transfers_created_at ON transfers');
    }
}
