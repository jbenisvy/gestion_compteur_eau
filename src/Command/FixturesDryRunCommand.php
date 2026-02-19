<?php

namespace App\Command;

use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Bundle\FixturesBundle\Executor\ORMExecutor;
use Doctrine\Bundle\FixturesBundle\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fixtures:dry-run', description: 'Charge les fixtures en transaction puis ROLLBACK (ne modifie pas la base).')]
class FixturesDryRunCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private SymfonyFixturesLoader $loader) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('append', null, InputOption::VALUE_NONE, 'Ne pas purger (comme --append)')
             ->addOption('purge-with-truncate', null, InputOption::VALUE_NONE, 'Purger avec TRUNCATE (toujours rollbacké)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $fixtures = $this->loader->getFixtures();
        if (!$fixtures) { $output->writeln('<comment>Aucune fixture.</comment>'); return Command::SUCCESS; }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $purger = new ORMPurger($this->em);
            $purger->setPurgeMode($input->getOption('purge-with-truncate') ? ORMPurger::PURGE_MODE_TRUNCATE : ORMPurger::PURGE_MODE_DELETE);
            $executor = new ORMExecutor($this->em, $purger);
            $executor->execute($fixtures, (bool)$input->getOption('append'));

            $output->writeln('<info>✅ Dry-run OK</info>');
            $conn->rollBack();
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $conn->rollBack();
            $output->writeln('<error>⛔ Dry-run KO:</error> '.$e->getMessage());
            if ($e->getPrevious()) $output->writeln('<error>Cause:</error> '.$e->getPrevious()->getMessage());
            return Command::FAILURE;
        }
    }
}
