<?php

namespace InSquare\OpendxpSitemapBundle\Generator;

use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use OpenDxp\Model\Document\Listing;
use Symfony\Component\Messenger\MessageBusInterface;

final class DocumentGenerator
{
    private const BATCH_SIZE = 200;

    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function generate(): int
    {
        $listing = new Listing();
        $listing->setCondition('type = ?', ['page']);
        $listing->setUnpublished(false);
        $listing->setLimit(self::BATCH_SIZE);

        $offset = 0;
        $dispatched = 0;

        while (true) {
            $listing->setOffset($offset);
            $ids = $listing->loadIdList();
            if ($ids === []) {
                break;
            }

            foreach ($ids as $id) {
                $this->messageBus->dispatch(new SitemapItemCreateMessage(
                    SitemapItemCreateMessage::TYPE_DOCUMENT,
                    (int) $id
                ));
                $dispatched++;
            }

            if (count($ids) < self::BATCH_SIZE) {
                break;
            }

            $offset += self::BATCH_SIZE;
        }

        return $dispatched;
    }
}
