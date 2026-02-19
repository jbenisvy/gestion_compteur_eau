<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DevPagesController extends AbstractController
{
    #[Route('/saisie-index', name: 'dev_saisie_index')]
    public function saisieIndex(): Response
    {
        return $this->render('dev/placeholder.html.twig', [
            'title' => 'Saisie des index de compteurs',
        ]);
    }

    #[Route('/dev/historique', name: 'dev_historique')]
    public function historique(): Response
    {
        return $this->render('dev/placeholder.html.twig', [
            'title' => 'Consultation de l’historique',
        ]);
    }

    #[Route('/maintenance-tables', name: 'dev_maintenance_tables')]
    public function maintenance(): Response
    {
        return $this->render('dev/placeholder.html.twig', [
            'title' => 'Maintenance des tables',
        ]);
    }

    #[Route('/ajout-coproprietaire', name: 'dev_ajout_coproprietaire')]
    public function ajoutCopro(): Response
    {
        return $this->render('dev/placeholder.html.twig', [
            'title' => 'Ajout d’un copropriétaire',
        ]);
    }

    #[Route('/rattachement-lot', name: 'dev_rattachement_lot')]
    public function rattachementLot(): Response
    {
        return $this->render('dev/placeholder.html.twig', [
            'title' => 'Rattachement de lot',
        ]);
    }

    #[Route('/etat-consommations', name: 'dev_etat_consommations')]
    public function etatConsos(): Response
    {
        return $this->render('dev/placeholder.html.twig', [
            'title' => 'État des consommations',
        ]);
    }
}
