<?php

namespace InSquare\OpendxpSitemapBundle\Dumper;

use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use InSquare\OpendxpSitemapBundle\Registry\ObjectGeneratorRegistry;
use InSquare\OpendxpSitemapBundle\Util\HostNormalizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SitemapDumper
{
    private SitemapItemRepository $repository;
    private array $sites;
    private string $outputDir;
    private int $maxUrlsPerFile;
    private array $objectGenerators;
    private ObjectGeneratorRegistry $objectGeneratorRegistry;
    private bool $hreflangEnabled;
    private ?string $xDefaultLanguage;
    private ?string $xDefaultFallbackLanguage;

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

        $hreflang = $config['hreflang'] ?? [];
        $this->hreflangEnabled = (bool) ($hreflang['enabled'] ?? true);
        $configuredXDefault = isset($hreflang['x_default_language']) ? trim((string) $hreflang['x_default_language']) : '';
        $this->xDefaultLanguage = $configuredXDefault !== '' ? $configuredXDefault : null;
        $configuredFallback = isset($hreflang['x_default_fallback_language']) ? trim((string) $hreflang['x_default_fallback_language']) : '';
        $this->xDefaultFallbackLanguage = $configuredFallback !== '' ? $configuredFallback : null;
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
                    $siteIndexEntries[] = [
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
                        $siteIndexEntries[] = [
                            'path' => $this->buildPublicPath($objectFilename),
                            'lastmod' => $objectLastmod,
                        ];
                        $filesCreated++;
                    }
                }

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
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $writer->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        $maxLastmod = null;

        while ($rows !== []) {
            $alternatesByElementId = $this->buildAlternatesByElementId(
                $siteId,
                $elementType,
                $elementClass,
                $rows
            );

            foreach ($rows as $row) {
                $writer->startElement('url');
                $writer->writeElement('loc', (string) $row['url']);

                if ($this->hreflangEnabled) {
                    $this->writeAlternateLinks($writer, $row, $alternatesByElementId);
                }

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
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $baseHost = HostNormalizer::normalize($host);

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

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, string>>
     */
    private function buildAlternatesByElementId(
        int $siteId,
        string $elementType,
        ?string $elementClass,
        array $rows
    ): array {
        if (!$this->hreflangEnabled) {
            return [];
        }

        $elementIds = [];
        foreach ($rows as $row) {
            $elementId = (int) ($row['element_id'] ?? 0);
            if ($elementId > 0) {
                $elementIds[] = $elementId;
            }
        }

        $rawAlternates = $this->repository->fetchAlternatesBySiteTypeAndElementIds(
            $siteId,
            $elementType,
            $elementClass,
            $elementIds
        );

        $result = [];
        foreach ($rawAlternates as $elementId => $alternates) {
            $links = [];

            foreach ($alternates as $alternate) {
                $locale = $this->normalizeHreflang((string) ($alternate['locale'] ?? ''));
                $url = (string) ($alternate['url'] ?? '');

                if ($locale === '' || $url === '') {
                    continue;
                }

                $links[$locale] = $url;
            }

            if ($links === []) {
                continue;
            }

            ksort($links);
                $links = $this->appendXDefault($links);
                $result[(int) $elementId] = $links;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, string>> $alternatesByElementId
     */
    private function writeAlternateLinks(\XMLWriter $writer, array $row, array $alternatesByElementId): void
    {
        $elementId = (int) ($row['element_id'] ?? 0);
        if ($elementId <= 0) {
            return;
        }

        $links = $alternatesByElementId[$elementId] ?? [];
        $currentLocale = $this->normalizeHreflang((string) ($row['locale'] ?? ''));
        $currentUrl = (string) ($row['url'] ?? '');

        if ($currentLocale !== '' && $currentUrl !== '') {
            $links[$currentLocale] = $currentUrl;
        }

        if ($links === []) {
            return;
        }

        ksort($links);
        $links = $this->appendXDefault($links);

        foreach ($links as $hreflang => $href) {
            $writer->startElement('xhtml:link');
            $writer->writeAttribute('rel', 'alternate');
            $writer->writeAttribute('hreflang', $hreflang);
            $writer->writeAttribute('href', $href);
            $writer->endElement();
        }
    }

    /**
     * @param array<string, string> $links
     *
     * @return array<string, string>
     */
    private function appendXDefault(array $links): array
    {
        if ($links === []) {
            return $links;
        }

        if ($this->xDefaultLanguage !== null) {
            $configured = $this->normalizeHreflang($this->xDefaultLanguage);
            if (isset($links[$configured])) {
                $links['x-default'] = $links[$configured];

                return $links;
            }
        }

        if ($this->xDefaultFallbackLanguage !== null) {
            $fallback = $this->normalizeHreflang($this->xDefaultFallbackLanguage);
            if ($fallback !== '' && isset($links[$fallback])) {
                $links['x-default'] = $links[$fallback];
            }
        }

        return $links;
    }

    private function normalizeHreflang(string $language): string
    {
        $language = trim($language);
        if ($language === '') {
            return '';
        }

        $parts = preg_split('/[-_]/', $language) ?: [];
        if ($parts === []) {
            return strtolower($language);
        }

        $primary = strtolower((string) array_shift($parts));
        $normalized = [$primary];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (strlen($part) === 4) {
                $normalized[] = ucfirst(strtolower($part));
                continue;
            }

            $normalized[] = strtoupper($part);
        }

        return implode('-', $normalized);
    }
}
