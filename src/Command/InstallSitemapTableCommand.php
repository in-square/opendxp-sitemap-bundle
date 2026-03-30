<?php

namespace InSquare\OpendxpSitemapBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'insquare:sitemap:install',
    description: 'Create the sitemap_item table in the database.'
)]
final class InstallSitemapTableCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sqlPath = __DIR__ . '/../Resources/sql/mysql.sql';

        if (!is_file($sqlPath)) {
            $output->writeln(sprintf('<error>SQL file not found: %s</error>', $sqlPath));

            return Command::FAILURE;
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            $output->writeln(sprintf('<error>Unable to read SQL file: %s</error>', $sqlPath));

            return Command::FAILURE;
        }

        $sql = trim($sql);
        if ($sql === '') {
            $output->writeln(sprintf('<error>SQL file is empty: %s</error>', $sqlPath));

            return Command::FAILURE;
        }

        $this->connection->executeStatement($sql);

        $output->writeln('<info>SQL executed from mysql.sql.</info>');

        return Command::SUCCESS;
    }
}
