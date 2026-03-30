<?php

namespace InSquare\OpendxpSitemapBundle\Generator;

final class SitemapItemData
{
    private int $elementId;
    private string $elementClass;
    private string $url;
    private \DateTimeInterface $lastmod;
    private ?float $priority;
    private ?string $changefreq;

    public function __construct(
        int $elementId,
        string $elementClass,
        string $url,
        \DateTimeInterface $lastmod,
        ?float $priority = null,
        ?string $changefreq = null
    ) {
        $this->elementId = $elementId;
        $this->elementClass = $elementClass;
        $this->url = $url;
        $this->lastmod = $lastmod;
        $this->priority = $priority;
        $this->changefreq = $changefreq;
    }

    public function getElementId(): int
    {
        return $this->elementId;
    }

    public function getElementClass(): string
    {
        return $this->elementClass;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getLastmod(): \DateTimeInterface
    {
        return $this->lastmod;
    }

    public function getPriority(): ?float
    {
        return $this->priority;
    }

    public function getChangefreq(): ?string
    {
        return $this->changefreq;
    }
}
