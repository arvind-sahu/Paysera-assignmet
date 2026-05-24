<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240521000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounts and transfers tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE accounts (
                id CHAR(36) NOT NULL,
                account_number VARCHAR(34) NOT NULL,
                balance_minor BIGINT NOT NULL,
                currency CHAR(3) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                version INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                UNIQUE INDEX uniq_account_number (account_number)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE transfers (
                id CHAR(36) NOT NULL,
                reference CHAR(36) NOT NULL,
                from_account_id CHAR(36) NOT NULL,
                to_account_id CHAR(36) NOT NULL,
                amount_minor BIGINT NOT NULL,
                currency CHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL,
                idempotency_key VARCHAR(64) DEFAULT NULL,
                failure_reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                completed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                UNIQUE INDEX uniq_transfer_reference (reference),
                UNIQUE INDEX uniq_idempotency_key (idempotency_key),
                INDEX idx_from_account (from_account_id),
                INDEX idx_to_account (to_account_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE transfers');
        $this->addSql('DROP TABLE accounts');
    }
}
