<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user 
            ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS email_verified_at DATETIME DEFAULT NULL');

        // Keep existing accounts usable: mark already-created users as verified.
        $this->addSql('UPDATE user SET email_verified = 1, email_verified_at = NOW(), email_verification_token = NULL WHERE email_verified = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user 
            DROP COLUMN IF EXISTS email_verified,
            DROP COLUMN IF EXISTS email_verification_token,
            DROP COLUMN IF EXISTS email_verified_at');
    }
}
