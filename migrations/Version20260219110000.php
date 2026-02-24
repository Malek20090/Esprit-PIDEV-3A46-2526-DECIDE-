<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Face ID fields to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD COLUMN IF NOT EXISTS face_id_credential_id VARCHAR(255) DEFAULT NULL, ADD COLUMN IF NOT EXISTS face_id_enabled TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP face_id_credential_id, DROP face_id_enabled');
    }
}
