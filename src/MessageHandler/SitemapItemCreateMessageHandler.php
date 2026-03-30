<?php

namespace InSquare\OpendxpSitemapBundle\MessageHandler;

use InSquare\OpendxpSitemapBundle\Builder\DocumentSitemapItemBuilder;
use InSquare\OpendxpSitemapBundle\Builder\ObjectSitemapItemBuilder;
use InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SitemapItemCreateMessageHandler
{
    private DocumentSitemapItemBuilder $documentBuilder;
    private ObjectSitemapItemBuilder $objectBuilder;

    public function __construct(
        DocumentSitemapItemBuilder $documentBuilder,
        ObjectSitemapItemBuilder $objectBuilder
    )
    {
        $this->documentBuilder = $documentBuilder;
        $this->objectBuilder = $objectBuilder;
    }

    public function __invoke(SitemapItemCreateMessage $message): void
    {
        if ($message->getElementType() === SitemapItemCreateMessage::TYPE_DOCUMENT) {
            $this->documentBuilder->buildFromDocumentId($message->getElementId());
            return;
        }

        if ($message->getElementType() === SitemapItemCreateMessage::TYPE_OBJECT) {
            $this->objectBuilder->buildFromMessage($message);
        }
    }
}
