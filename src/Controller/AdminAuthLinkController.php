<?php

namespace App\Controller;

use App\Service\AuthLinkLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminAuthLinkController extends AbstractController
{
    #[Route('/admin/liens-connexion', name: 'admin_auth_links', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, AuthLinkLogService $authLinkLogService): Response
    {
        $email = trim((string) $request->query->get('email', ''));
        $status = trim((string) $request->query->get('status', ''));
        $limit = max(10, min(500, (int) $request->query->get('limit', 100)));

        return $this->render('admin/auth_links.html.twig', [
            'entries' => $authLinkLogService->findEmailSendEvents($email, $status, $limit),
            'filters' => [
                'email' => $email,
                'status' => $status,
                'limit' => $limit,
            ],
            'logPath' => $authLinkLogService->getLogPath(),
        ]);
    }
}
