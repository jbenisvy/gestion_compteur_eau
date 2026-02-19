<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GuideController extends AbstractController
{
    #[Route('/guides', name: 'guides_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('guides/index.html.twig');
    }

    #[Route('/guides/utilisateur', name: 'guide_utilisateur', methods: ['GET'])]
    public function utilisateur(): Response
    {
        return $this->render('guides/utilisateur.html.twig');
    }

    #[Route('/guides/admin', name: 'guide_admin', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(): Response
    {
        return $this->render('guides/admin.html.twig');
    }
}

