<?php

namespace InSquare\OpendxpSitemapBundle\Generator;

final class SitemapGeneratorContext
{
    private int $siteId;
    private string $locale;
    private string $host;

    public function __construct(int $siteId, string $locale, string $host)
    {
        $this->siteId = $siteId;
        $this->locale = $locale;
        $this->host = $host;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getHost(): string
    {
        return $this->host;
    }
}
