<?php
namespace App\Controller;

use App\Repository\ReleveRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DebugReleveController extends AbstractController
{
    #[Route('/debug/releves', name: 'debug_releves', methods: ['GET'])]
    public function index(ReleveRepository $repo): Response
    {
        $lines = [];
        foreach ($repo->findBy([], ['annee' => 'DESC'], 5) as $r) {
            $lines[] = sprintf(
                'Releve id=%d | annee=%d | lot=%d | items=%d',
                $r->getId(),
                $r->getAnnee(),
                $r->getLot()->getId(),
                $r->getItems()->count()
            );
        }

        return new Response('<pre>'.implode("\n", $lines ?: ['(aucun relevé trouvé)']).'</pre>');
    }
}
