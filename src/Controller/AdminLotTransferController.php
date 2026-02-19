<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\LotTransferData;
use App\Entity\LotCoproprietaire;
use App\Form\LotTransferType;
use App\Repository\LotCoproprietaireRepository;
use App\Repository\LotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminLotTransferController extends AbstractController
{
    #[Route('/admin/transfert-lot', name: 'admin_lot_transfer')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        Request $request,
        LotRepository $lotRepo,
        LotCoproprietaireRepository $lotCoproRepo,
        EntityManagerInterface $em
    ): Response {
        $data = new LotTransferData();
        $minimumEffectiveDate = new \DateTimeImmutable('2017-01-01');
        $maximumEffectiveDate = new \DateTimeImmutable('today');
        $data->effectiveDate = $maximumEffectiveDate;

        $prefilledLotId = (int) $request->query->get('lotId', 0);
        if ($prefilledLotId > 0) {
            $prefilledLot = $lotRepo->find($prefilledLotId);
            if ($prefilledLot !== null) {
                $data->lot = $prefilledLot;
            }
        }

        $form = $this->createForm(LotTransferType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lot = $data->lot;
            $newCoproprietaire = $data->newCoproprietaire;
            $effectiveDate = $data->effectiveDate;

            if ($lot === null || $newCoproprietaire === null || $effectiveDate === null) {
                $this->addFlash('error', 'Le formulaire est incomplet.');
                return $this->redirectToRoute('admin_lot_transfer');
            }

            $effective = \DateTimeImmutable::createFromInterface($effectiveDate)->setTime(0, 0);
            if ($effective < $minimumEffectiveDate) {
                $this->addFlash('error', sprintf(
                    "La date d'effet doit être au moins le %s.",
                    $minimumEffectiveDate->format('d/m/Y')
                ));
                return $this->redirectToRoute('admin_lot_transfer', ['lotId' => $lot->getId()]);
            }
            if ($effective > $maximumEffectiveDate) {
                $this->addFlash('error', sprintf(
                    "La date d'effet ne peut pas être dans le futur (max %s).",
                    $maximumEffectiveDate->format('d/m/Y')
                ));
                return $this->redirectToRoute('admin_lot_transfer', ['lotId' => $lot->getId()]);
            }
            $dayBefore = $effective->modify('-1 day');
            $activeLinks = $lotCoproRepo->findActiveLinksForLot($lot, $effective);

            $hasTargetAlready = false;
            $closedCount = 0;
            $removedCount = 0;

            foreach ($activeLinks as $link) {
                $linkCopro = $link->getCoproprietaire();
                if ($linkCopro !== null && $linkCopro->getId() === $newCoproprietaire->getId()) {
                    $hasTargetAlready = true;
                    $link->setIsPrincipal(true);
                    continue;
                }

                $linkStart = \DateTimeImmutable::createFromInterface($link->getDateDebut())->setTime(0, 0);
                if ($linkStart >= $effective) {
                    $em->remove($link);
                    $removedCount++;
                    continue;
                }

                $link->setDateFin($dayBefore);
                $link->setIsPrincipal(false);
                $closedCount++;
            }

            if (!$hasTargetAlready) {
                $newLink = (new LotCoproprietaire())
                    ->setLot($lot)
                    ->setCoproprietaire($newCoproprietaire)
                    ->setDateDebut($effective)
                    ->setDateFin(null)
                    ->setIsPrincipal(true);

                $commentaire = trim((string) $data->commentaire);
                if ($commentaire !== '') {
                    $newLink->setCommentaire($commentaire);
                }

                $em->persist($newLink);
            }

            // Fallback legacy utilisé par une partie du code.
            $lot->setCoproprietaire($newCoproprietaire);

            $em->flush();

            if ($hasTargetAlready && $closedCount === 0 && $removedCount === 0) {
                $this->addFlash('warning', sprintf(
                    'Le lot %s est déjà rattaché à %s à la date choisie.',
                    $lot->getNumeroLot(),
                    $newCoproprietaire->getNomComplet()
                ));
            } else {
                $this->addFlash('success', sprintf(
                    'Transfert du lot %s appliqué vers %s (liens clôturés: %d, liens supprimés: %d).',
                    $lot->getNumeroLot(),
                    $newCoproprietaire->getNomComplet(),
                    $closedCount,
                    $removedCount
                ));
            }

            return $this->redirectToRoute('admin_lot_transfer', ['lotId' => $lot->getId()]);
        }

        $selectedLot = $data->lot;
        $currentLink = null;
        if ($selectedLot !== null) {
            $dateRef = $data->effectiveDate ?? new \DateTimeImmutable('today');
            $currentLink = $lotCoproRepo->findActiveCoproForLot($selectedLot, $dateRef);
        }

        return $this->render('admin/lot_transfer.html.twig', [
            'form' => $form->createView(),
            'selectedLot' => $selectedLot,
            'currentLink' => $currentLink,
            'minimumEffectiveDate' => $minimumEffectiveDate,
            'maximumEffectiveDate' => $maximumEffectiveDate,
        ]);
    }
}
