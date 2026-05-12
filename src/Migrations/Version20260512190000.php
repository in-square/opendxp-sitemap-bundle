<?php

namespace InSquare\OpendxpSitemapBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_seen_run_token to sitemap_item for token-based stale cleanup before dump.';
    }

    public function up(Schema $schema): void
    {
        $platform = strtolower($this->connection->getDatabasePlatform()->getName());
        $this->abortIf(
            !in_array($platform, ['mysql', 'mariadb'], true),
            'Migration can only be executed safely on "mysql" and "mariadb".'
        );

        if (!$this->hasColumn('last_seen_run_token')) {
            $this->addSql("ALTER TABLE `sitemap_item` ADD `last_seen_run_token` VARCHAR(32) DEFAULT NULL");
        }

        if (!$this->hasIndex('idx_sitemap_item_last_seen_run_token')) {
            $this->addSql('CREATE INDEX `idx_sitemap_item_last_seen_run_token` ON `sitemap_item` (`last_seen_run_token`)');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = strtolower($this->connection->getDatabasePlatform()->getName());
        $this->abortIf(
            !in_array($platform, ['mysql', 'mariadb'], true),
            'Migration can only be executed safely on "mysql" and "mariadb".'
        );

        if ($this->hasIndex('idx_sitemap_item_last_seen_run_token')) {
            $this->addSql('DROP INDEX `idx_sitemap_item_last_seen_run_token` ON `sitemap_item`');
        }

        if ($this->hasColumn('last_seen_run_token')) {
            $this->addSql('ALTER TABLE `sitemap_item` DROP `last_seen_run_token`');
        }
    }

    private function hasColumn(string $columnName): bool
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns('sitemap_item');

        return array_key_exists(strtolower($columnName), $columns);
    }

    private function hasIndex(string $indexName): bool
    {
        $indexes = $this->connection->createSchemaManager()->listTableIndexes('sitemap_item');

        return array_key_exists(strtolower($indexName), $indexes);
    }
}
