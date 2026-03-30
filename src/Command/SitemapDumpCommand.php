<?php

namespace InSquare\OpendxpSitemapBundle\Command;

use InSquare\OpendxpProcessManagerBundle\ExecutionTrait;
use InSquare\OpendxpSitemapBundle\Dumper\SitemapDumper;
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

    public function __construct(SitemapDumper $dumper)
    {
        parent::__construct();
        $this->dumper = $dumper;
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

        $filesCreated = $this->dumper->dump($siteId, $locale);
        $output->writeln(sprintf('<info>Generated %d sitemap file(s).</info>', $filesCreated));

        return Command::SUCCESS;
    }
}
