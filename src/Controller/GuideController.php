<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

    #[Route('/guides/syndic', name: 'guide_syndic', methods: ['GET'])]
    public function syndic(): Response
    {
        $this->denyUnlessAdminOrSyndic();

        return $this->render('guides/syndic.html.twig');
    }

    private function denyUnlessAdminOrSyndic(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SYNDIC')) {
            throw new AccessDeniedHttpException('Acces reserve aux profils admin et syndic.');
        }
    }
}
