<?php

namespace InSquare\OpendxpSitemapBundle\Builder;

use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use OpenDxp\Model\Document;
use OpenDxp\Model\Document\Page;
use OpenDxp\Model\Document\Service;
use OpenDxp\Model\Site;
use OpenDxp\Tool\Frontend;
use InSquare\OpendxpSitemapBundle\Util\HostNormalizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DocumentSitemapItemBuilder
{
    private SitemapItemRepository $repository;
    private array $hostBySiteId;
    private array $languagesBySiteId;
    private Service $documentService;

    public function __construct(
        SitemapItemRepository $repository,
        #[Autowire('%in_square_opendxp_sitemap%')] array $config
    ) {
        $this->repository = $repository;
        $this->documentService = new Service();
        $this->hostBySiteId = [];
        $this->languagesBySiteId = [];

        foreach ($config['sites'] ?? [] as $siteConfig) {
            $siteId = (int) ($siteConfig['id'] ?? 0);
            $host = (string) ($siteConfig['host'] ?? '');
            $normalizedHost = HostNormalizer::normalize($host);
            if ($normalizedHost !== '') {
                $this->hostBySiteId[$siteId] = $normalizedHost;
            }

            $languages = $siteConfig['languages'] ?? [];
            $this->languagesBySiteId[$siteId] = array_values(array_map('strval', $languages));
        }
    }

    public function buildFromDocumentId(int $documentId, string $runToken): void
    {
        $document = Document::getById($documentId);
        if (!$document instanceof Page) {
            return;
        }

        if ($this->isNoIndex($document)) {
            return;
        }

        $siteId = $this->resolveSiteId($document);
        $host = $this->hostBySiteId[$siteId] ?? null;
        if ($host === null) {
            return;
        }

        $locale = $this->resolveLocale($document);
        if ($locale === null || !$this->isLocaleAllowed($siteId, $locale)) {
            return;
        }

        $path = $this->resolvePath($document, $siteId);
        if ($path === null) {
            return;
        }

        $url = $this->buildUrl($host, $path);
        $lastmod = $this->resolveLastmod($document);
        $translationGroupId = $this->resolveTranslationGroupId($document);

        $this->repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_DOCUMENT,
            'element_id' => $translationGroupId,
            'element_class' => null,
            'site_id' => $siteId,
            'locale' => $locale,
            'url' => $url,
            'lastmod' => $lastmod,
            'priority' => null,
            'changefreq' => null,
            'last_seen_run_token' => $runToken,
        ]);
    }

    private function resolveSiteId(Document $document): int
    {
        return Frontend::getSiteIdForDocument($document) ?? 0;
    }

    private function resolveLocale(Document $document): ?string
    {
        $locale = $document->getProperty('language');
        if (!is_string($locale) || $locale === '') {
            return null;
        }

        return $locale;
    }

    private function resolveLastmod(Document $document): \DateTimeInterface
    {
        $timestamp = (int) $document->getModificationDate();
        $date = new \DateTimeImmutable('@' . $timestamp);

        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    private function resolveTranslationGroupId(Document $document): int
    {
        try {
            $sourceId = (int) $this->documentService->getTranslationSourceId($document);
            if ($sourceId > 0) {
                return $sourceId;
            }
        } catch (\Throwable) {
        }

        return (int) $document->getId();
    }

    private function isNoIndex(Document $document): bool
    {
        return (bool) $document->getProperty('seo_noindex');
    }

    private function isLocaleAllowed(int $siteId, string $locale): bool
    {
        $allowed = $this->languagesBySiteId[$siteId] ?? [];
        if ($allowed === []) {
            return true;
        }

        return in_array($locale, $allowed, true);
    }

    private function buildUrl(string $host, string $prettyUrl): string
    {
        return HostNormalizer::buildAbsoluteUrl($host, $prettyUrl);
    }

    private function resolvePath(Document $document, int $siteId): ?string
    {
        $prettyUrl = $document instanceof Page ? $document->getPrettyUrl() : null;
        if (is_string($prettyUrl) && $prettyUrl !== '') {
            return $prettyUrl;
        }

        $realPath = $document->getRealFullPath();
        if ($realPath === '') {
            return null;
        }

        $path = $realPath;
        $site = $siteId > 0 ? Site::getById($siteId) : null;
        if ($site instanceof Site) {
            $rootPath = rtrim($site->getRootPath(), '/');
            if ($rootPath === '') {
                return $this->encodePath($path);
            }

            if ($realPath === $rootPath) {
                return '/';
            }

            if (str_starts_with($realPath, $rootPath . '/')) {
                $relative = substr($realPath, strlen($rootPath));

                $path = '/' . ltrim($relative, '/');
                return $this->encodePath($path);
            }
        }

        return $this->encodePath($path);
    }

    private function encodePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        $segments = explode('/', ltrim($path, '/'));
        $encoded = array_map(static function (string $segment): string {
            return rawurlencode(rawurldecode($segment));
        }, $segments);

        return '/' . implode('/', $encoded);
    }
}
