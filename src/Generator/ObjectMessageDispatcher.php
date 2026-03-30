<?php

namespace InSquare\OpendxpSitemapBundle\Generator;

use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use InSquare\OpendxpSitemapBundle\Registry\ObjectGeneratorRegistry;
use OpenDxp\Model\DataObject\Concrete;
use OpenDxp\Model\DataObject\Listing as DataObjectListing;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final class ObjectMessageDispatcher
{
    private const BATCH_SIZE = 200;

    private MessageBusInterface $messageBus;
    private ObjectGeneratorRegistry $registry;
    private array $sites;
    private array $allowedGeneratorIds;

    public function __construct(
        MessageBusInterface $messageBus,
        ObjectGeneratorRegistry $registry,
        #[Autowire('%in_square_opendxp_sitemap%')] array $config
    ) {
        $this->messageBus = $messageBus;
        $this->registry = $registry;
        $this->sites = $config['sites'] ?? [];
        $this->allowedGeneratorIds = array_map('strval', array_keys($config['object_generators'] ?? []));
    }

    public function generate(): int
    {
        $dispatched = 0;

        foreach ($this->sites as $siteConfig) {
            $siteId = (int) ($siteConfig['id'] ?? 0);
            $languages = $this->normalizeStringList($siteConfig['languages'] ?? []);
            $objectClasses = $this->normalizeStringList($siteConfig['objects'] ?? []);

            if ($languages === [] || $objectClasses === []) {
                continue;
            }

            foreach ($objectClasses as $objectClass) {
                $generator = $this->registry->getByObjectClass($objectClass);
                if ($generator === null) {
                    continue;
                }

                $listing = $this->createListing($objectClass);
                if ($listing === null) {
                    continue;
                }

                $generatorId = $generator->getId();
                if (!in_array($generatorId, $this->allowedGeneratorIds, true)) {
                    throw new \RuntimeException(sprintf(
                        'Object generator "%s" is not configured under object_generators.',
                        $generatorId
                    ));
                }

                $listing->setUnpublished(false);
                $listing->setLimit(self::BATCH_SIZE);
                $listing->setOrderKey('id');
                $listing->setOrder('asc');

                $offset = 0;

                while (true) {
                    $listing->setOffset($offset);
                    $ids = $listing->loadIdList();
                    if ($ids === []) {
                        break;
                    }

                    foreach ($ids as $id) {
                        foreach ($languages as $locale) {
                            $this->messageBus->dispatch(new SitemapItemCreateMessage(
                                SitemapItemCreateMessage::TYPE_OBJECT,
                                (int) $id,
                                $siteId,
                                $locale,
                                $generatorId
                            ));
                            $dispatched++;
                        }
                    }

                    if (count($ids) < self::BATCH_SIZE) {
                        break;
                    }

                    $offset += self::BATCH_SIZE;
                }
            }
        }

        return $dispatched;
    }

    private function createListing(string $objectClass): ?DataObjectListing
    {
        if ($objectClass === '' || !class_exists($objectClass)) {
            return null;
        }

        if (!is_subclass_of($objectClass, Concrete::class) || !method_exists($objectClass, 'getList')) {
            return null;
        }

        $listing = $objectClass::getList();

        return $listing instanceof DataObjectListing ? $listing : null;
    }

    /**
     * @return string[]
     */
    private function normalizeStringList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }
}
