<?php

namespace InSquare\OpendxpSitemapBundle\Command;

use InSquare\OpendxpProcessManagerBundle\ExecutionTrait;
use InSquare\OpendxpSitemapBundle\Generator\DocumentGenerator;
use InSquare\OpendxpSitemapBundle\Generator\ObjectMessageDispatcher;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use OpenDxp\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    private SitemapItemRepository $repository;

    public function __construct(
        DocumentGenerator $documentGenerator,
        ObjectMessageDispatcher $objectDispatcher,
        SitemapItemRepository $repository
    )
    {
        parent::__construct();
        $this->documentGenerator = $documentGenerator;
        $this->objectDispatcher = $objectDispatcher;
        $this->repository = $repository;
    }

    protected function configure(): void
    {
        $this->addOption(
            'no-truncate',
            null,
            InputOption::VALUE_NONE,
            'Do not truncate sitemap_item before dispatching messages.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('no-truncate')) {
            $this->repository->truncate();
            $output->writeln('<info>Truncated table "sitemap_item".</info>');
        }

        $dispatched = $this->documentGenerator->generate();
        $output->writeln(sprintf('<info>Dispatched %d document messages.</info>', $dispatched));

        $objectDispatched = $this->objectDispatcher->generate();
        $output->writeln(sprintf('<info>Dispatched %d object messages.</info>', $objectDispatched));

        return Command::SUCCESS;
    }
}
