<?php

namespace InSquare\OpendxpSitemapBundle\Dumper;

use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use InSquare\OpendxpSitemapBundle\Registry\ObjectGeneratorRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SitemapDumper
{
    private SitemapItemRepository $repository;
    private array $sites;
    private string $outputDir;
    private int $maxUrlsPerFile;
    private array $objectGenerators;
    private ObjectGeneratorRegistry $objectGeneratorRegistry;

    public function __construct(
        SitemapItemRepository $repository,
        ObjectGeneratorRegistry $objectGeneratorRegistry,
        #[Autowire('%in_square_opendxp_sitemap%')] array $config
    ) {
        $this->repository = $repository;
        $this->objectGeneratorRegistry = $objectGeneratorRegistry;
        $this->sites = $config['sites'] ?? [];
        $this->objectGenerators = $config['object_generators'] ?? [];

        $output = $config['output'] ?? [];
        $this->outputDir = rtrim((string) ($output['dir'] ?? ''), '/');
        $this->maxUrlsPerFile = (int) ($output['max_urls_per_file'] ?? 50000);
    }

    public function dump(?int $siteId = null, ?string $locale = null): int
    {
        $this->ensureOutputDir();

        $filesCreated = 0;

        foreach ($this->sites as $siteConfig) {
            $currentSiteId = (int) ($siteConfig['id'] ?? 0);
            if ($siteId !== null && $siteId !== $currentSiteId) {
                continue;
            }

            $host = (string) ($siteConfig['host'] ?? '');
            if ($host === '') {
                continue;
            }

            $languages = $siteConfig['languages'] ?? [];
            $languages = array_values(array_map('strval', $languages));
            if ($locale !== null) {
                $languages = array_values(array_intersect($languages, [$locale]));
            }

            $siteIndexEntries = [];

            foreach ($languages as $language) {
                $localeIndexEntries = [];

                $documentsFilename = $this->buildDocumentsFileName($currentSiteId, $language);
                $documentsPath = $this->outputDir . '/' . $documentsFilename;

                $documentsLastmod = $this->writeUrlSetForType(
                    $documentsPath,
                    $currentSiteId,
                    $language,
                    SitemapItemCreateMessage::TYPE_DOCUMENT,
                    null
                );

                if ($documentsLastmod instanceof \DateTimeInterface) {
                    $localeIndexEntries[] = [
                        'path' => $this->buildPublicPath($documentsFilename),
                        'lastmod' => $documentsLastmod,
                    ];
                    $filesCreated++;
                }

                foreach (array_keys($this->objectGenerators) as $generatorName) {
                    $generator = $this->objectGeneratorRegistry->getById((string) $generatorName);
                    if ($generator === null) {
                        continue;
                    }

                    $objectClass = $generator->getObjectClass();
                    $objectFilename = $this->buildObjectFileName($currentSiteId, $language, $generatorName);
                    $objectPath = $this->outputDir . '/' . $objectFilename;

                    $objectLastmod = $this->writeUrlSetForType(
                        $objectPath,
                        $currentSiteId,
                        $language,
                        SitemapItemCreateMessage::TYPE_OBJECT,
                        $objectClass
                    );

                    if ($objectLastmod instanceof \DateTimeInterface) {
                        $localeIndexEntries[] = [
                            'path' => $this->buildPublicPath($objectFilename),
                            'lastmod' => $objectLastmod,
                        ];
                        $filesCreated++;
                    }
                }

                if ($localeIndexEntries === []) {
                    continue;
                }

                $localeIndexFilename = $this->buildLocaleIndexFileName($currentSiteId, $language);
                $localeIndexPath = $this->outputDir . '/' . $localeIndexFilename;
                $this->writeSitemapIndex($localeIndexPath, $host, $localeIndexEntries);
                $filesCreated++;

                $siteIndexEntries[] = [
                    'path' => $this->buildPublicPath($localeIndexFilename),
                    'lastmod' => $this->maxLastmodFromEntries($localeIndexEntries),
                ];
            }

            if ($siteIndexEntries === []) {
                continue;
            }

            $siteIndexFilename = $this->buildSiteIndexFileName($currentSiteId);
            $siteIndexPath = $this->outputDir . '/' . $siteIndexFilename;
            $this->writeSitemapIndex($siteIndexPath, $host, $siteIndexEntries);
            $filesCreated++;
        }

        return $filesCreated;
    }

    private function ensureOutputDir(): void
    {
        if ($this->outputDir === '') {
            throw new \RuntimeException('Sitemap output directory is not configured.');
        }

        if (!is_dir($this->outputDir)) {
            if (!mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
                throw new \RuntimeException(sprintf('Unable to create directory: %s', $this->outputDir));
            }
        }
    }

    private function buildDocumentsFileName(int $siteId, string $locale): string
    {
        return sprintf('sitemap.%d.%s.documents.xml', $siteId, $locale);
    }

    private function buildObjectFileName(int $siteId, string $locale, string $generatorName): string
    {
        return sprintf('sitemap.%d.%s.%s.xml', $siteId, $locale, $generatorName);
    }

    private function buildLocaleIndexFileName(int $siteId, string $locale): string
    {
        return sprintf('sitemap.%d.%s.xml', $siteId, $locale);
    }

    private function buildSiteIndexFileName(int $siteId): string
    {
        return sprintf('sitemap.%d.xml', $siteId);
    }

    private function buildPublicPath(string $filename): string
    {
        $normalizedDir = str_replace('\\', '/', $this->outputDir);
        $publicMarker = '/public/';
        $position = strpos($normalizedDir, $publicMarker);
        if ($position === false) {
            return '/' . ltrim($filename, '/');
        }

        $relative = substr($normalizedDir, $position + strlen($publicMarker));
        $relative = trim($relative, '/');
        if ($relative === '') {
            return '/' . ltrim($filename, '/');
        }

        return '/' . $relative . '/' . ltrim($filename, '/');
    }

    private function writeUrlSetForType(
        string $filePath,
        int $siteId,
        string $locale,
        string $elementType,
        ?string $elementClass
    ): ?\DateTimeInterface
    {
        $offset = 0;
        $rows = $elementClass === null
            ? $this->repository->fetchBatchBySiteLocaleAndType(
                $siteId,
                $locale,
                $elementType,
                $this->maxUrlsPerFile,
                $offset
            )
            : $this->repository->fetchBatchBySiteLocaleTypeAndClass(
                $siteId,
                $locale,
                $elementType,
                $elementClass,
                $this->maxUrlsPerFile,
                $offset
            );
        if ($rows === []) {
            return null;
        }

        $writer = new \XMLWriter();
        $writer->openURI($filePath);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $writer->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        $maxLastmod = null;

        while ($rows !== []) {
            foreach ($rows as $row) {
                $writer->startElement('url');
                $writer->writeElement('loc', (string) $row['url']);

                $lastmod = $this->normalizeLastmod($row['lastmod'] ?? null);
                if ($lastmod !== null) {
                    $writer->writeElement('lastmod', $lastmod->format(DATE_ATOM));
                    if ($maxLastmod === null || $lastmod->getTimestamp() > $maxLastmod->getTimestamp()) {
                        $maxLastmod = $lastmod;
                    }
                }

                if (!empty($row['changefreq'])) {
                    $writer->writeElement('changefreq', (string) $row['changefreq']);
                }

                if ($row['priority'] !== null && $row['priority'] !== '') {
                    $writer->writeElement('priority', (string) $row['priority']);
                }

                $writer->endElement();
            }

            $offset += $this->maxUrlsPerFile;
            $rows = $elementClass === null
                ? $this->repository->fetchBatchBySiteLocaleAndType(
                    $siteId,
                    $locale,
                    $elementType,
                    $this->maxUrlsPerFile,
                    $offset
                )
                : $this->repository->fetchBatchBySiteLocaleTypeAndClass(
                    $siteId,
                    $locale,
                    $elementType,
                    $elementClass,
                    $this->maxUrlsPerFile,
                    $offset
                );
        }

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        return $maxLastmod;
    }

    private function writeSitemapIndex(string $filePath, string $host, array $entries): void
    {
        $writer = new \XMLWriter();
        $writer->openURI($filePath);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $baseHost = rtrim($this->normalizeHost($host), '/');

        foreach ($entries as $entry) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $baseHost . $entry['path']);

            $lastmod = $entry['lastmod'] ?? null;
            if ($lastmod instanceof \DateTimeInterface) {
                $writer->writeElement('lastmod', $lastmod->format(DATE_ATOM));
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
    }

    private function maxLastmodFromEntries(array $entries): ?\DateTimeInterface
    {
        $max = null;

        foreach ($entries as $entry) {
            $lastmod = $entry['lastmod'] ?? null;
            if (!$lastmod instanceof \DateTimeInterface) {
                continue;
            }

            if ($max === null || $lastmod->getTimestamp() > $max->getTimestamp()) {
                $max = $lastmod;
            }
        }

        return $max;
    }

    private function normalizeHost(string $host): string
    {
        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            return $host;
        }

        return 'https://' . $host;
    }

    private function normalizeLastmod(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (is_int($value)) {
            return (new \DateTimeImmutable('@' . $value))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        if (is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
