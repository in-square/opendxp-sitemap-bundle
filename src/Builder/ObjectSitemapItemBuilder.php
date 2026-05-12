<?php

namespace InSquare\OpendxpSitemapBundle\Builder;

use InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorInterface;
use InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorWithContextInterface;
use InSquare\OpendxpSitemapBundle\Generator\SitemapItemData;
use InSquare\OpendxpSitemapBundle\Generator\SitemapGeneratorContext;
use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use InSquare\OpendxpSitemapBundle\Registry\ObjectGeneratorRegistry;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use InSquare\OpendxpSitemapBundle\Util\HostNormalizer;
use OpenDxp\Model\DataObject\Concrete;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ObjectSitemapItemBuilder
{
    private SitemapItemRepository $repository;
    private ObjectGeneratorRegistry $registry;
    private array $hostBySiteId;

    public function __construct(
        SitemapItemRepository $repository,
        ObjectGeneratorRegistry $registry,
        #[Autowire('%in_square_opendxp_sitemap%')] array $config
    ) {
        $this->repository = $repository;
        $this->registry = $registry;
        $this->hostBySiteId = [];

        foreach ($config['sites'] ?? [] as $siteConfig) {
            $siteId = (int) ($siteConfig['id'] ?? 0);
            $host = (string) ($siteConfig['host'] ?? '');
            $normalizedHost = HostNormalizer::normalize($host);
            if ($normalizedHost !== '') {
                $this->hostBySiteId[$siteId] = $normalizedHost;
            }
        }
    }

    public function buildFromMessage(SitemapItemCreateMessage $message): void
    {
        $siteId = $message->getSiteId();
        $locale = $message->getLocale();
        $generatorId = $message->getGeneratorId();

        if ($siteId === null || $locale === null || $generatorId === null) {
            throw new \InvalidArgumentException('Object sitemap message requires siteId, locale, and generatorId.');
        }
        $runToken = $message->getRunToken();
        if (!is_string($runToken) || $runToken === '') {
            return;
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

        if ($generator instanceof ObjectGeneratorWithContextInterface) {
            $host = $this->hostBySiteId[$siteId] ?? '';
            if ($host === '') {
                return;
            }

            $item = $generator->buildItem(
                $object,
                new SitemapGeneratorContext($siteId, $locale, $host)
            );
        } elseif ($generator instanceof ObjectGeneratorInterface) {
            $item = $generator->buildItem($object, $siteId, $locale);
        } else {
            throw new \RuntimeException(sprintf(
                'Object generator "%s" must implement ObjectGeneratorWithContextInterface or ObjectGeneratorInterface.',
                $generatorId
            ));
        }

        if (!$item instanceof SitemapItemData) {
            return;
        }

        $this->repository->upsert([
            'element_type' => SitemapItemCreateMessage::TYPE_OBJECT,
            'element_id' => $item->getElementId(),
            'element_class' => $item->getElementClass(),
            'site_id' => $siteId,
            'locale' => $locale,
            'url' => $item->getUrl(),
            'lastmod' => $item->getLastmod(),
            'priority' => $item->getPriority(),
            'changefreq' => $item->getChangefreq(),
            'last_seen_run_token' => $runToken,
        ]);
    }
}
