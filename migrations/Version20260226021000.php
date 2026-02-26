<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226021000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reclamation module tables and user moderation block columns.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('user')) {
            $this->addColumnIfMissing('user', 'is_blocked', 'ALTER TABLE user ADD is_blocked TINYINT(1) NOT NULL DEFAULT 0');
            $this->addColumnIfMissing('user', 'blocked_reason', 'ALTER TABLE user ADD blocked_reason VARCHAR(255) DEFAULT NULL');
            $this->addColumnIfMissing('user', 'blocked_at', "ALTER TABLE user ADD blocked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }

        if (!$this->tableExists('reclamation')) {
            $this->addSql('CREATE TABLE reclamation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, admin_responder_id INT DEFAULT NULL, subject VARCHAR(160) NOT NULL, message LONGTEXT NOT NULL, admin_response LONGTEXT DEFAULT NULL, status VARCHAR(30) NOT NULL, contains_bad_words TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', resolved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CE606404A76ED395 (user_id), INDEX IDX_CE606404E75B4574 (admin_responder_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404E75B4574 FOREIGN KEY (admin_responder_id) REFERENCES user (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('reclamation')) {
            $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404A76ED395');
            $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404E75B4574');
            $this->addSql('DROP TABLE reclamation');
        }

        if ($this->tableExists('user')) {
            $this->dropColumnIfExists('user', 'is_blocked');
            $this->dropColumnIfExists('user', 'blocked_reason');
            $this->dropColumnIfExists('user', 'blocked_at');
        }
    }

    private function tableExists(string $table): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return $result !== false;
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $result !== false;
    }

    private function addColumnIfMissing(string $table, string $column, string $sql): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->connection->executeStatement($sql);
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->columnExists($table, $column)) {
            return;
        }

        $this->connection->executeStatement(sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $column));
    }
}
