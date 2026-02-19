<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LotController extends AbstractController
{
    #[Route('/admin/lots', name: 'lot_index')]
    public function index(): Response
    {
        return $this->render('lot/index.html.twig', [
            'lots' => [], // Pour l'instant vide, on remplira plus tard avec Doctrine
        ]);
    }
}
