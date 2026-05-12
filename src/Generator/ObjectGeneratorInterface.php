<?php

namespace InSquare\OpendxpSitemapBundle\Generator;

/**
 * @deprecated Use ObjectGeneratorWithContextInterface instead.
 */
interface ObjectGeneratorInterface
{
    public function getId(): string;

    public function getObjectClass(): string;

    /**
     * @return SitemapItemData|null
     */
    public function buildItem(object $object, int $siteId, string $locale): ?SitemapItemData;
}
