<?php

namespace InSquare\OpendxpSitemapBundle\Generator;

interface ObjectGeneratorInterface
{
    public function getId(): string;

    public function getObjectClass(): string;

    /**
     * @return SitemapItemData|null
     */
    public function buildItem(object $object, int $siteId, string $locale): ?SitemapItemData;
}
