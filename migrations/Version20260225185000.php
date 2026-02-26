<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225185000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill missing cas_relles columns (categorie, justificatif_file_name, updated_at)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('cas_relles')) {
            return;
        }

        $this->addColumnIfMissing(
            'cas_relles',
            'categorie',
            'ALTER TABLE cas_relles ADD categorie VARCHAR(40) DEFAULT NULL'
        );
        $this->addColumnIfMissing(
            'cas_relles',
            'justificatif_file_name',
            'ALTER TABLE cas_relles ADD justificatif_file_name VARCHAR(255) DEFAULT NULL'
        );
        $this->addColumnIfMissing(
            'cas_relles',
            'updated_at',
            "ALTER TABLE cas_relles ADD updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'"
        );
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('cas_relles')) {
            return;
        }

        $this->dropColumnIfExists('cas_relles', 'categorie');
        $this->dropColumnIfExists('cas_relles', 'justificatif_file_name');
        $this->dropColumnIfExists('cas_relles', 'updated_at');
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

