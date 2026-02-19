<?php

namespace App\Command;

use App\Entity\Lot;
use App\Entity\Compteur;
use App\Entity\Releve;
use App\Entity\EtatCompteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:import:releves',
    description: 'Import CSV des relevés: lot_numero;emplacement;type;annee;index_n;[index_n_1];[etat];[forfait];[date_releve];[commentaire];[occupant_annee]'
)]
class ImportRelevesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Chemin du CSV (ex: var/import/releves.csv)')
             ->addOption('delimiter', null, InputOption::VALUE_REQUIRED, 'Délimiteur CSV', ';')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Valider sans écrire')
             ->addOption('create-missing-compteur', null, InputOption::VALUE_NONE, "Créer un compteur actif si le slot n'existe pas");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $delim = (string)$input->getOption('delimiter');
        $dry   = (bool)$input->getOption('dry-run');
        $autoCreate = (bool)$input->getOption('create-missing-compteur');

        if (!is_file($file)) { $output->writeln("<error>Fichier introuvable: $file</error>"); return Command::FAILURE; }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        $etatRepo = $this->em->getRepository(EtatCompteur::class);
        $lotRepo  = $this->em->getRepository(Lot::class);
        $cmpRepo  = $this->em->getRepository(Compteur::class);

        $mapEtat = [];
        foreach (['actif','supprime','forfait','remplace'] as $code) {
            $e = $etatRepo->findOneBy(['code' => $code]) ?? $etatRepo->findOneBy(['libelle' => ucfirst($code)]);
            if ($e) $mapEtat[$code] = $e;
        }

        $fh = fopen($file, 'r');
        if (!$fh) { $output->writeln('<error>Impossible d’ouvrir le fichier.</error>'); return Command::FAILURE; }

        $headers = fgetcsv($fh, 0, $delim);
        $required = ['lot_numero','emplacement','type','annee','index_n'];
        if (!$headers || array_diff($required, array_map('trim', $headers))) {
            $output->writeln('<error>En-têtes invalides. Colonnes obligatoires: '.implode(', ', $required).'</error>');
            fclose($fh); $conn->rollBack(); return Command::FAILURE;
        }
        $idx = array_flip(array_map('trim', $headers));

        $rowNum = 1; $created = 0; $errors = 0;

        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            $rowNum++; if (count($row) === 1 && trim((string)$row[0]) === '') continue;

            $get = fn(string $key) => isset($idx[$key]) ? trim((string)($row[$idx[$key]] ?? '')) : '';

            $lotNumero   = $get('lot_numero');
            $emplacement = $get('emplacement');
            $type        = strtoupper(substr($get('type'),0,2));
            $annee       = (int)$get('annee');
            $indexN      = $get('index_n') === '' ? null : (float)$get('index_n');

            if ($lotNumero === '' || $emplacement === '' || !in_array($type,['EC','EF'],true) || !$annee || $indexN === null) {
                $output->writeln("<error>L.$rowNum: Données obligatoires manquantes/invalides</error>"); $errors++; continue;
            }

            $lot = $lotRepo->findOneBy(['numeroLot' => $lotNumero]) ?? $lotRepo->findOneBy(['numero' => $lotNumero]);
            if (!$lot) { $output->writeln("<error>L.$rowNum: Lot $lotNumero introuvable</error>"); $errors++; continue; }

            $compteur = $cmpRepo->createQueryBuilder('c')
                ->andWhere('c.lot = :lot')->andWhere('c.emplacement = :e')->andWhere('c.type = :t')->andWhere('c.actif = 1')
                ->setParameters(['lot'=>$lot,'e'=>$emplacement,'t'=>$type])->getQuery()->getOneOrNullResult();

            if (!$compteur) {
                if ($autoCreate) {
                    $compteur = new Compteur();
                    $compteur->setLot($lot)->setEmplacement($emplacement)->setType($type)->setActif(true);
                    if (isset($mapEtat['actif'])) $compteur->setEtatCompteur($mapEtat['actif']);
                    $compteur->setDateInstallation(new \DateTimeImmutable($annee.'-01-01'));
                    $this->em->persist($compteur);
                } else {
                    $output->writeln("<error>L.$rowNum: Aucun compteur actif pour $lotNumero/$emplacement/$type</error>"); $errors++; continue;
                }
            }

            $releve = new Releve();
            $releve->setCompteur($compteur)->setLot($lot)->setCoproprietaire($lot->getCoproprietaire());
            $releve->setAnnee($annee)->setDateSaisie(new \DateTimeImmutable());
            $releve->setIndexN($indexN);

            $idxN1 = $get('index_n_1'); if ($idxN1 !== '') $releve->setIndexNmoins1((float)$idxN1);
            $etat  = strtolower($get('etat')); if ($etat && isset($mapEtat[$etat])) $releve->setEtatCompteur($mapEtat[$etat]);
            $forf  = $get('forfait'); if ($forf !== '') $releve->setForfait((float)$forf);
            $comm  = $get('commentaire'); if ($comm !== '') $releve->setCommentaire($comm);
            $occ   = $get('occupant_annee'); if ($occ !== '') $releve->setOccupantAnnee($occ);

            $date = $get('date_releve'); if ($date !== '') { try { $releve->setDateSaisie(new \DateTimeImmutable($date)); } catch (\Throwable) {} }

            $this->em->persist($releve);
            $created++;
        }

        fclose($fh);

        if ($errors > 0) { $conn->rollBack(); $output->writeln("<error>⛔ Import interrompu: $errors erreur(s).</error>"); return Command::FAILURE; }
        if ($dry) { $conn->rollBack(); $output->writeln("<info>✅ Dry-run OK : $created relevé(s) prêts.</info>"); return Command::SUCCESS; }

        $this->em->flush(); $conn->commit();
        $output->writeln("<info>✅ Import terminé : $created relevé(s) créés.</info>");
        return Command::SUCCESS;
    }
}
