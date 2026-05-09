<?php

namespace InSquare\OpendxpSitemapBundle\Tests\Dumper;

use Doctrine\DBAL\DriverManager;
use InSquare\OpendxpSitemapBundle\Dumper\SitemapDumper;
use InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorInterface;
use InSquare\OpendxpSitemapBundle\Generator\SitemapItemData;
use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use InSquare\OpendxpSitemapBundle\Registry\ObjectGeneratorRegistry;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use PHPUnit\Framework\TestCase;

final class SitemapDumperTest extends TestCase
{
    private const OBJECT_CLASS = 'InSquare\\OpendxpSitemapBundle\\Tests\\Fixture\\Post';
    private const OBJECT_GENERATOR_CLASS = 'InSquare\\OpendxpSitemapBundle\\Tests\\Fixture\\PostGenerator';

    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/opendxp_sitemap_test_' . bin2hex(random_bytes(6)) . '/public/sitemap';
    }

    protected function tearDown(): void
    {
        $rootDir = dirname($this->outputDir, 2);
        if (!is_dir($rootDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($rootDir);
    }

    public function testDumpWritesFlatSiteIndexWithoutLocaleIndexes(): void
    {
        $repository = $this->createRepository();
        $repository->insert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 1,
            'element_class' => null,
            'site_id' => 0,
            'locale' => 'pl',
            'url' => 'https://example.com/',
            'lastmod' => new \DateTimeImmutable('2026-05-09 09:56:46'),
        ]);
        $repository->insert([
            'element_type' => SitemapItemCreateMessage::TYPE_OBJECT,
            'element_id' => 2,
            'element_class' => self::OBJECT_CLASS,
            'site_id' => 0,
            'locale' => 'pl',
            'url' => 'https://example.com/post',
            'lastmod' => new \DateTimeImmutable('2026-05-09 09:57:22'),
        ]);
        $repository->insert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => 3,
            'element_class' => null,
            'site_id' => 0,
            'locale' => 'en',
            'url' => 'https://example.com/en',
            'lastmod' => new \DateTimeImmutable('2026-05-09 10:00:00'),
        ]);

        $dumper = new SitemapDumper(
            $repository,
            new ObjectGeneratorRegistry([$this->createObjectGenerator('post', self::OBJECT_CLASS)]),
            [
                'sites' => [
                    [
                        'id' => 0,
                        'host' => 'example.com',
                        'languages' => ['pl', 'en'],
                    ],
                ],
                'object_generators' => [
                    'post' => self::OBJECT_GENERATOR_CLASS,
                ],
                'output' => [
                    'dir' => $this->outputDir,
                    'max_urls_per_file' => 50000,
                ],
            ]
        );

        self::assertSame(4, $dumper->dump());

        $siteIndex = file_get_contents($this->outputDir . '/sitemap.0.xml');

        self::assertIsString($siteIndex);
        self::assertStringContainsString('https://example.com/sitemap/sitemap.0.pl.documents.xml', $siteIndex);
        self::assertStringContainsString('https://example.com/sitemap/sitemap.0.pl.post.xml', $siteIndex);
        self::assertStringContainsString('https://example.com/sitemap/sitemap.0.en.documents.xml', $siteIndex);
        self::assertStringNotContainsString('https://example.com/sitemap/sitemap.0.pl.xml', $siteIndex);
        self::assertFileDoesNotExist($this->outputDir . '/sitemap.0.pl.xml');
        self::assertStringContainsString('<urlset', (string) file_get_contents($this->outputDir . '/sitemap.0.pl.documents.xml'));
        self::assertStringContainsString('<urlset', (string) file_get_contents($this->outputDir . '/sitemap.0.pl.post.xml'));
        self::assertStringContainsString('<urlset', (string) file_get_contents($this->outputDir . '/sitemap.0.en.documents.xml'));
    }

    private function createRepository(): SitemapItemRepository
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE sitemap_item (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                element_type VARCHAR(16) NOT NULL,
                element_id INTEGER NOT NULL,
                element_class VARCHAR(255) DEFAULT NULL,
                site_id INTEGER NOT NULL,
                locale VARCHAR(16) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                lastmod DATETIME NOT NULL,
                priority DOUBLE PRECISION DEFAULT NULL,
                changefreq VARCHAR(16) DEFAULT NULL
            )'
        );

        return new SitemapItemRepository($connection);
    }

    private function createObjectGenerator(string $id, string $objectClass): ObjectGeneratorInterface
    {
        return new class($id, $objectClass) implements ObjectGeneratorInterface {
            public function __construct(
                private readonly string $id,
                private readonly string $objectClass
            ) {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getObjectClass(): string
            {
                return $this->objectClass;
            }

            public function buildItem(object $object, int $siteId, string $locale): ?SitemapItemData
            {
                return null;
            }
        };
    }

}
