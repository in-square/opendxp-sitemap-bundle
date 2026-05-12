<?php

namespace InSquare\OpendxpSitemapBundle\Tests\Repository;

use Doctrine\DBAL\DriverManager;
use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use PHPUnit\Framework\TestCase;

final class SitemapItemRepositoryTest extends TestCase
{
    public function testUpsertDoesNotCreateDuplicateAndUpdatesExistingRow(): void
    {
        $repository = $this->createRepository();

        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 100,
            'element_class' => null,
            'site_id' => 0,
            'locale' => 'pl',
            'url' => 'https://example.com/start',
            'lastmod' => new \DateTimeImmutable('2026-05-12 10:00:00'),
            'priority' => null,
            'changefreq' => null,
        ]);

        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 100,
            'element_class' => null,
            'site_id' => 0,
            'locale' => 'pl',
            'url' => 'https://example.com/updated',
            'lastmod' => new \DateTimeImmutable('2026-05-12 12:30:00'),
            'priority' => 0.7,
            'changefreq' => 'daily',
        ]);

        $allRows = $repository->findBy([]);
        self::assertCount(1, $allRows);

        $row = $allRows[0];
        self::assertSame('', $row['element_class']);
        self::assertSame('https://example.com/updated', $row['url']);
        self::assertSame('2026-05-12 12:30:00', $row['lastmod']);
        self::assertSame(0.7, (float) $row['priority']);
        self::assertSame('daily', $row['changefreq']);
    }

    public function testDeleteRowsNotSeenInRunTokenRemovesStaleRows(): void
    {
        $repository = $this->createRepository();

        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 101,
            'element_class' => null,
            'site_id' => 0,
            'locale' => 'pl',
            'url' => 'https://example.com/fresh',
            'lastmod' => new \DateTimeImmutable('2026-05-12 10:00:00'),
            'last_seen_run_token' => '20260512100000000000',
        ]);
        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 102,
            'element_class' => null,
            'site_id' => 0,
            'locale' => 'pl',
            'url' => 'https://example.com/stale',
            'lastmod' => new \DateTimeImmutable('2026-05-12 10:00:00'),
            'last_seen_run_token' => '20260512110000000000',
        ]);
        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_OBJECT,
            'element_id' => 201,
            'element_class' => 'OpenDxp\\Model\\DataObject\\Post',
            'site_id' => 0,
            'locale' => 'en',
            'url' => 'https://example.com/post',
            'lastmod' => new \DateTimeImmutable('2026-05-12 10:00:00'),
            'last_seen_run_token' => null,
        ]);

        $deleted = $repository->deleteRowsNotSeenInRunToken('20260512100000000000');
        self::assertSame(2, $deleted);

        $rows = $repository->findBy([]);
        self::assertCount(1, $rows);
        self::assertSame('https://example.com/fresh', $rows[0]['url']);
    }

    public function testDeleteRowsNotSeenInRunTokenCanBeScopedBySiteAndLocale(): void
    {
        $repository = $this->createRepository();

        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 301,
            'element_class' => null,
            'site_id' => 1,
            'locale' => 'pl',
            'url' => 'https://example.com/site1-pl-fresh',
            'lastmod' => new \DateTimeImmutable('2026-05-12 10:00:00'),
            'last_seen_run_token' => '20260512120000000000',
        ]);
        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 302,
            'element_class' => null,
            'site_id' => 1,
            'locale' => 'pl',
            'url' => 'https://example.com/site1-pl-stale',
            'lastmod' => new \DateTimeImmutable('2026-05-12 10:00:00'),
            'last_seen_run_token' => '20260512110000000000',
        ]);
        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 303,
            'element_class' => null,
            'site_id' => 1,
            'locale' => 'en',
            'url' => 'https://example.com/site1-en-stale',
            'lastmod' => new \DateTimeImmutable('2026-05-12 10:00:00'),
            'last_seen_run_token' => '20260512110000000000',
        ]);
        $repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 304,
            'element_class' => null,
            'site_id' => 2,
            'locale' => 'pl',
            'url' => 'https://example.com/site2-pl-stale',
            'lastmod' => new \DateTimeImmutable('2026-05-12 10:00:00'),
            'last_seen_run_token' => '20260512110000000000',
        ]);

        $deleted = $repository->deleteRowsNotSeenInRunToken('20260512120000000000', 1, 'pl');
        self::assertSame(1, $deleted);

        $rows = $repository->findBy([]);
        self::assertCount(3, $rows);
    }

    private function createRepository(): SitemapItemRepository
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE sitemap_item (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                element_type VARCHAR(16) NOT NULL,
                element_id INTEGER NOT NULL,
                element_class VARCHAR(255) NOT NULL DEFAULT \'\',
                site_id INTEGER NOT NULL,
                locale VARCHAR(16) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                lastmod DATETIME NOT NULL,
                priority DOUBLE PRECISION DEFAULT NULL,
                changefreq VARCHAR(16) DEFAULT NULL,
                last_seen_run_token VARCHAR(32) DEFAULT NULL
            )'
        );
        $connection->executeStatement(
            'CREATE INDEX idx_sitemap_item_dump ON sitemap_item (site_id, locale, element_type, element_class, id)'
        );
        $connection->executeStatement(
            'CREATE UNIQUE INDEX uniq_sitemap_item_identity ON sitemap_item (element_type, element_id, element_class, site_id, locale)'
        );

        return new SitemapItemRepository($connection);
    }
}
