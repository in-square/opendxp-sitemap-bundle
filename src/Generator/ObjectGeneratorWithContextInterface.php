<?php

namespace InSquare\OpendxpSitemapBundle\Generator;

interface ObjectGeneratorWithContextInterface
{
    public function getId(): string;

    public function getObjectClass(): string;

    /**
     * @return SitemapItemData|null
     */
    public function buildItem(object $object, SitemapGeneratorContext $context): ?SitemapItemData;
}
