<?php

namespace App\Controller;

use App\Entity\Lot;
use App\Repository\CoproprietaireRepository;
use App\Repository\LotCoproprietaireRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CoproprietaireController extends AbstractController
{
    #[Route('/coproprietaire/saisie_index.php', name: 'coproprietaire_saisie_index')]
    #[IsGranted('ROLE_USER')]
    public function saisieIndex(
        CoproprietaireRepository $coproRepo,
        LotCoproprietaireRepository $lotCoproRepo
    ): Response
    {
        $user = $this->getUser();

        $copro = $user ? $coproRepo->findOneBy(['user' => $user]) : null;
        if (!$copro) {
            throw $this->createNotFoundException('Copropriétaire introuvable.');
        }

        // Récupérer le lot actif du copropriétaire connecté
        $lots = $lotCoproRepo->findActiveLotsForCopro($copro, new \DateTimeImmutable('today'));
        $lot = $lots[0] ?? null;

        if (!$lot) {
            throw $this->createNotFoundException('Aucun lot trouvé pour ce copropriétaire.');
        }

        // Redirige vers le nouveau flux de saisie (releve_new/releve_item).
        return $this->redirectToRoute('saisie_index_form', [
            'lotId' => $lot->getId(),
            'annee' => (int)date('Y'),
        ]);
    }
}
