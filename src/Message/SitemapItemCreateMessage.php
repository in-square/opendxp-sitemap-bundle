<?php

namespace InSquare\OpendxpSitemapBundle\Message;

final class SitemapItemCreateMessage
{
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_OBJECT = 'object';

    private string $elementType;
    private int $elementId;
    private ?int $siteId;
    private ?string $locale;
    private ?string $generatorId;

    public function __construct(
        string $elementType,
        int $elementId,
        ?int $siteId = null,
        ?string $locale = null,
        ?string $generatorId = null
    )
    {
        $this->elementType = $elementType;
        $this->elementId = $elementId;
        $this->siteId = $siteId;
        $this->locale = $locale;
        $this->generatorId = $generatorId;
    }

    public function getElementType(): string
    {
        return $this->elementType;
    }

    public function getElementId(): int
    {
        return $this->elementId;
    }

    public function getSiteId(): ?int
    {
        return $this->siteId;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getGeneratorId(): ?string
    {
        return $this->generatorId;
    }
}
