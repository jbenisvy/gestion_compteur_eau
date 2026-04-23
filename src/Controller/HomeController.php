<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }

            if ($this->isGranted('ROLE_USER') && $this->isGranted('ROLE_SYNDIC')) {
                return $this->redirectToRoute('role_switch');
            }

            if ($this->isGranted('ROLE_SYNDIC')) {
                return $this->redirectToRoute('syndic_dashboard');
            }

            return $this->redirectToRoute('copro_dashboard');
        }

        return $this->render('home/landing.html.twig');
    }

    #[Route('/choix-espace', name: 'role_switch')]
    public function roleSwitch(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if (!($this->isGranted('ROLE_USER') && $this->isGranted('ROLE_SYNDIC'))) {
            if ($this->isGranted('ROLE_SYNDIC')) {
                return $this->redirectToRoute('syndic_dashboard');
            }

            return $this->redirectToRoute('copro_dashboard');
        }

        return $this->render('home/role_switch.html.twig');
    }
}
