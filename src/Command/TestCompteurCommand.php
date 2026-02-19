<?php

namespace App\Command;

use App\Entity\Compteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-compteur',
    description: 'Liste tous les compteurs présents en base de données',
)]
class TestCompteurCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test de lecture des compteurs');

        // Récupération de tous les compteurs
        $compteurs = $this->entityManager->getRepository(Compteur::class)->findAll();

        if (empty($compteurs)) {
            $io->warning('Aucun compteur trouvé en base.');
            return Command::SUCCESS;
        }

        // Préparer les données pour affichage
        $rows = [];
        foreach ($compteurs as $compteur) {
            $rows[] = [
                $compteur->getId(),
                $compteur->getNumeroCompteur(),
                $compteur->getType(),
                $compteur->getEmplacement(),
                $compteur->getDateInstallation() ? $compteur->getDateInstallation()->format('Y-m-d') : '',
                $compteur->getLot() ? $compteur->getLot()->getNumeroLot() : '',
            ];
        }

        // Afficher le tableau
        $io->table(
            ['ID', 'Numéro', 'Type', 'Emplacement', 'Date Installation', 'Lot'],
            $rows
        );

        $io->success('Lecture terminée avec succès.');

        return Command::SUCCESS;
    }
}
