<?php

namespace InSquare\OpendxpSitemapBundle\Command;

use InSquare\OpendxpProcessManagerBundle\ExecutionTrait;
use InSquare\OpendxpSitemapBundle\Dumper\SitemapDumper;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use InSquare\OpendxpSitemapBundle\Sync\SitemapSyncStateStore;
use OpenDxp\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'insquare:sitemap:dump',
    description: 'Generate sitemap XML files from the database.'
)]
final class SitemapDumpCommand extends AbstractCommand
{
    use ExecutionTrait;

    private SitemapDumper $dumper;
    private SitemapItemRepository $repository;
    private SitemapSyncStateStore $syncStateStore;

    public function __construct(
        SitemapDumper $dumper,
        SitemapItemRepository $repository,
        SitemapSyncStateStore $syncStateStore
    )
    {
        parent::__construct();
        $this->dumper = $dumper;
        $this->repository = $repository;
        $this->syncStateStore = $syncStateStore;
    }

    protected function configure(): void
    {
        $this
            ->addOption('site-id', null, InputOption::VALUE_OPTIONAL, 'Limit dump to a single site ID.')
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Limit dump to a single locale.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $siteId = $input->getOption('site-id');
        $siteId = $siteId !== null ? (int) $siteId : null;

        $locale = $input->getOption('locale');
        $locale = $locale !== null && $locale !== '' ? (string) $locale : null;

        $runToken = $this->syncStateStore->getActiveRunToken();
        if (!is_string($runToken) || $runToken === '') {
            $output->writeln('<error>Active sitemap run token is missing. Run collect before dump.</error>');

            return Command::FAILURE;
        }

        $deleted = $this->repository->deleteRowsNotSeenInRunToken($runToken, $siteId, $locale);
        $output->writeln(sprintf(
            '<info>Removed %d stale sitemap row(s) for run token %s (site-id: %s, locale: %s).</info>',
            $deleted,
            $runToken,
            $siteId === null ? 'all' : (string) $siteId,
            $locale ?? 'all'
        ));

        $filesCreated = $this->dumper->dump($siteId, $locale);
        $output->writeln(sprintf('<info>Generated %d sitemap file(s).</info>', $filesCreated));

        return Command::SUCCESS;
    }
}
