<?php

namespace InSquare\OpendxpSitemapBundle\Command;

use InSquare\OpendxpProcessManagerBundle\ExecutionTrait;
use InSquare\OpendxpSitemapBundle\Repository\SitemapItemRepository;
use OpenDxp\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'insquare:sitemap:delete',
    description: 'Delete sitemap XML files and truncate sitemap_item table.'
)]
final class SitemapDeleteCommand extends AbstractCommand
{
    use ExecutionTrait;

    private SitemapItemRepository $repository;
    private string $outputDir;

    public function __construct(
        SitemapItemRepository $repository,
        #[Autowire('%in_square_opendxp_sitemap%')] array $config
    ) {
        parent::__construct();
        $this->repository = $repository;
        $output = $config['output'] ?? [];
        $this->outputDir = rtrim((string) ($output['dir'] ?? ''), '/');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deletedFiles = $this->deleteOutputFiles();
        if ($deletedFiles === 0) {
            $output->writeln('<info>No sitemap files to delete.</info>');
        } else {
            $output->writeln(sprintf('<info>Deleted %d sitemap file(s).</info>', $deletedFiles));
        }

        $this->repository->truncate();
        $output->writeln('<info>Truncated table "sitemap_item".</info>');

        return Command::SUCCESS;
    }

    private function deleteOutputFiles(): int
    {
        if ($this->outputDir === '' || !is_dir($this->outputDir)) {
            return 0;
        }

        $deleted = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->outputDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            if (@unlink($item->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
