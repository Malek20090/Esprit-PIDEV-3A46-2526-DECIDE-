<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Face++ fields to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD COLUMN IF NOT EXISTS face_plus_token VARCHAR(255) DEFAULT NULL, ADD COLUMN IF NOT EXISTS face_plus_enabled TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP COLUMN IF EXISTS face_plus_token, DROP COLUMN IF EXISTS face_plus_enabled');
    }
}
