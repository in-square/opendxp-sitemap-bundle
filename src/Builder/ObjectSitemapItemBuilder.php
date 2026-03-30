<?php

namespace InSquare\OpendxpSitemapBundle\Builder;

use InSquare\OpendxpSitemapBundle\Generator\SitemapItemData;
use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use InSquare\OpendxpSitemapBundle\Registry\ObjectGeneratorRegistry;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use OpenDxp\Model\DataObject\Concrete;

final class ObjectSitemapItemBuilder
{
    private SitemapItemRepository $repository;
    private ObjectGeneratorRegistry $registry;

    public function __construct(
        SitemapItemRepository $repository,
        ObjectGeneratorRegistry $registry
    ) {
        $this->repository = $repository;
        $this->registry = $registry;
    }

    public function buildFromMessage(SitemapItemCreateMessage $message): void
    {
        $siteId = $message->getSiteId();
        $locale = $message->getLocale();
        $generatorId = $message->getGeneratorId();

        if ($siteId === null || $locale === null || $generatorId === null) {
            throw new \InvalidArgumentException('Object sitemap message requires siteId, locale, and generatorId.');
        }

        $generator = $this->registry->getById($generatorId);
        if ($generator === null) {
            throw new \RuntimeException(sprintf('Object generator "%s" not found.', $generatorId));
        }

        $objectClass = $generator->getObjectClass();
        if (!class_exists($objectClass) || !is_subclass_of($objectClass, Concrete::class)) {
            return;
        }

        $object = $objectClass::getById($message->getElementId());
        if (!$object instanceof Concrete) {
            return;
        }

        $item = $generator->buildItem($object, $siteId, $locale);
        if (!$item instanceof SitemapItemData) {
            return;
        }

        $this->repository->insert([
            'element_type' => SitemapItemCreateMessage::TYPE_OBJECT,
            'element_id' => $item->getElementId(),
            'element_class' => $item->getElementClass(),
            'site_id' => $siteId,
            'locale' => $locale,
            'url' => $item->getUrl(),
            'lastmod' => $item->getLastmod(),
            'priority' => $item->getPriority(),
            'changefreq' => $item->getChangefreq(),
        ]);
    }
}
