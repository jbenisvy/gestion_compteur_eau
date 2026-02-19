<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fixtures:preflight', description: "Liste les colonnes NOT NULL sans valeur par défaut.")]
class FixturesPreflightCommand extends Command
{
    public function __construct(private Connection $db) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dbName = $this->db->getDatabase();
        $sql = <<<SQL
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IFNULL(CHARACTER_MAXIMUM_LENGTH,'') AS LEN, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = :db AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL
ORDER BY TABLE_NAME, ORDINAL_POSITION
SQL;
        $rows = $this->db->fetchAllAssociative($sql, ['db' => $dbName]);

        if (!$rows) { $output->writeln('<info>✅ RAS</info>'); return Command::SUCCESS; }

        $t = new Table($output);
        $t->setHeaders(['Table','Colonne','Type','Taille','Nullable','Default']);
        foreach ($rows as $r) $t->addRow([$r['TABLE_NAME'],$r['COLUMN_NAME'],$r['DATA_TYPE'],$r['LEN'],$r['IS_NULLABLE'],$r['COLUMN_DEFAULT']]);
        $t->render();
        $output->writeln('<comment>⚠️ Assure-toi que les fixtures renseignent toutes ces colonnes.</comment>');
        return Command::SUCCESS;
    }
}
