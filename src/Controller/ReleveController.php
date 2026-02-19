<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReleveController extends AbstractController
{
    #[Route('/user/releves', name: 'releve_index')]
    public function index(): Response
    {
        return $this->render('releve/index.html.twig', [
            'releves' => [], // À remplir plus tard
        ]);
    }
}
