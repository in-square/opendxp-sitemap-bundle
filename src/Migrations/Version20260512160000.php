<?php

namespace InSquare\OpendxpSitemapBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sitemap_item dump index, logical unique constraint, and normalize element_class for deterministic uniqueness.';
    }

    public function up(Schema $schema): void
    {
        $platform = strtolower($this->connection->getDatabasePlatform()->getName());
        $this->abortIf(
            !in_array($platform, ['mysql', 'mariadb'], true),
            'Migration can only be executed safely on "mysql" and "mariadb".'
        );

        $this->addSql("UPDATE `sitemap_item` SET `element_class` = '' WHERE `element_class` IS NULL");
        $this->addSql("ALTER TABLE `sitemap_item` MODIFY `element_class` VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql('CREATE INDEX `idx_sitemap_item_dump` ON `sitemap_item` (`site_id`, `locale`, `element_type`, `element_class`, `id`)');
        $this->addSql('CREATE UNIQUE INDEX `uniq_sitemap_item_identity` ON `sitemap_item` (`element_type`, `element_id`, `element_class`, `site_id`, `locale`)');
    }

    public function down(Schema $schema): void
    {
        $platform = strtolower($this->connection->getDatabasePlatform()->getName());
        $this->abortIf(
            !in_array($platform, ['mysql', 'mariadb'], true),
            'Migration can only be executed safely on "mysql" and "mariadb".'
        );

        $this->addSql('DROP INDEX `uniq_sitemap_item_identity` ON `sitemap_item`');
        $this->addSql('DROP INDEX `idx_sitemap_item_dump` ON `sitemap_item`');
        $this->addSql("ALTER TABLE `sitemap_item` MODIFY `element_class` VARCHAR(255) DEFAULT NULL");
        $this->addSql("UPDATE `sitemap_item` SET `element_class` = NULL WHERE `element_class` = ''");
    }
}
