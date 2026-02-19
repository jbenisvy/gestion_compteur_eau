<?php

namespace App\Command;

use App\Entity\Lot;
use App\Entity\Compteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:data:check', description: 'Contrôle les règles métier (4 slots par lot, 1 compteur actif par slot, champs obligatoires).')]
class DataCheckCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lotRepo = $this->em->getRepository(Lot::class);
        $compteurRepo = $this->em->getRepository(Compteur::class);
        $slots = [['Cuisine','EC'], ['Cuisine','EF'], ['Salle de bain','EC'], ['Salle de bain','EF']];

        $issues = [];
        foreach ($lotRepo->findAll() as $lot) {
            if (!$lot->getCoproprietaire()) $issues[] = ['LOT '.$lot->getNumeroLot(),'copro','Manquant','Aucun copropriétaire rattaché'];
            foreach ($slots as [$piece,$type]) {
                $all = $compteurRepo->createQueryBuilder('c')
                    ->andWhere('c.lot = :lot')->andWhere('c.emplacement = :e')->andWhere('c.type = :t')
                    ->setParameters(['lot'=>$lot,'e'=>$piece,'t'=>$type])
                    ->getQuery()->getResult();
                $actifs = array_filter($all, fn(Compteur $c) => $c->isActif());
                if (count($actifs) === 0) $issues[] = ['LOT '.$lot->getNumeroLot(),"$piece/$type",'Actif manquant','Aucun compteur actif'];
                if (count($actifs) > 1)  $issues[] = ['LOT '.$lot->getNumeroLot(),"$piece/$type",'Actifs multiples',count($actifs).' actifs'];
                foreach ($all as $c) {
                    if ($c->getEtatCompteur() === null) $issues[] = ['LOT '.$lot->getNumeroLot(),"$piece/$type",'Etat manquant','etatCompteur NULL'];
                    if ($c->getDateInstallation() === null) $issues[] = ['LOT '.$lot->getNumeroLot(),"$piece/$type",'dateInstallation','NULL'];
                }
            }
        }

        $cntNullDateSaisie = (int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM releve WHERE date_saisie IS NULL");
        if ($cntNullDateSaisie > 0) $issues[] = ['RELEVES','date_saisie','NULL',"$cntNullDateSaisie lignes"];

        if (!$issues) { $output->writeln('<info>✅ Données conformes aux règles métier.</info>'); return Command::SUCCESS; }

        $t = new Table($output);
        $t->setHeaders(['Objet','Slot/Champ','Problème','Détail']);
        foreach ($issues as $r) $t->addRow($r);
        $t->render();
        $output->writeln('<comment>⚠️ Corrige les éléments ci-dessus puis relance.</comment>');
        return Command::FAILURE;
    }
}
