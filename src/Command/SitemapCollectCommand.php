<?php

namespace InSquare\OpendxpSitemapBundle\Command;

use InSquare\OpendxpProcessManagerBundle\ExecutionTrait;
use InSquare\OpendxpSitemapBundle\Generator\DocumentGenerator;
use InSquare\OpendxpSitemapBundle\Generator\ObjectMessageDispatcher;
use InSquare\OpendxpSitemapBundle\Sync\SitemapSyncStateStore;
use OpenDxp\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'insquare:sitemap:collect',
    description: 'Dispatch sitemap collect messages to the queue.'
)]
final class SitemapCollectCommand extends AbstractCommand
{
    use ExecutionTrait;

    private DocumentGenerator $documentGenerator;
    private ObjectMessageDispatcher $objectDispatcher;
    private SitemapSyncStateStore $syncStateStore;

    public function __construct(
        DocumentGenerator $documentGenerator,
        ObjectMessageDispatcher $objectDispatcher,
        SitemapSyncStateStore $syncStateStore
    )
    {
        parent::__construct();
        $this->documentGenerator = $documentGenerator;
        $this->objectDispatcher = $objectDispatcher;
        $this->syncStateStore = $syncStateStore;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runToken = $this->generateRunToken();
        $this->syncStateStore->setActiveRunToken($runToken);
        $output->writeln(sprintf('<info>Active sitemap run token: %s</info>', $runToken));

        $dispatched = $this->documentGenerator->generate($runToken);
        $output->writeln(sprintf('<info>Dispatched %d document messages.</info>', $dispatched));

        $objectDispatched = $this->objectDispatcher->generate($runToken);
        $output->writeln(sprintf('<info>Dispatched %d object messages.</info>', $objectDispatched));

        return Command::SUCCESS;
    }

    private function generateRunToken(): string
    {
        $microtime = microtime(true);
        $seconds = (int) $microtime;
        $microseconds = (int) (($microtime - $seconds) * 1_000_000);

        return gmdate('YmdHis', $seconds) . sprintf('%06d', $microseconds);
    }
}
