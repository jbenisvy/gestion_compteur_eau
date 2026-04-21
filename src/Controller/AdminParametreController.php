<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Parametre;
use App\Repository\ParametreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminParametreController extends AbstractController
{
    private function parseNullableFloat(mixed $value): ?float
    {
        $normalized = trim(str_replace(',', '.', (string) $value));

        if ($normalized === '') {
            return null;
        }

        return max(0, (float) $normalized);
    }

    #[Route('/admin/parametres', name: 'admin_parametres')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, ParametreRepository $paramRepo, EntityManagerInterface $em): Response
    {
        $selectedYear = (int)($request->query->get('annee') ?? date('Y'));
        $activeYear = $paramRepo->getAnneeEnCours((int)date('Y'));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_parametres', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
                return $this->redirectToRoute('admin_parametres', ['annee' => $selectedYear]);
            }

            $action = (string)$request->request->get('action', 'forfaits');

            if ($action === 'active_year') {
                $year = (int)$request->request->get('active_saisie_year', $activeYear ?? date('Y'));
                if ($year < 2000 || $year > 2100) {
                    $this->addFlash('error', 'Année active invalide.');
                    return $this->redirectToRoute('admin_parametres', ['annee' => $selectedYear]);
                }

                $default = $paramRepo->getLatestDefault();
                if (!$default) {
                    $default = (new Parametre())
                        ->setAnnee(null)
                        ->setForfaitEc(75.0)
                        ->setForfaitEf(150.0);
                    $em->persist($default);
                }
                $default->setActiveSaisieYear($year);
                $em->flush();
                $this->addFlash('success', sprintf('Année active de saisie définie sur %d.', $year));

                return $this->redirectToRoute('admin_parametres', ['annee' => $selectedYear]);
            }

            $year = (int)$request->request->get('annee', $selectedYear);
            $forfaitEc = (float)str_replace(',', '.', (string)$request->request->get('forfait_ec', '0'));
            $forfaitEf = (float)str_replace(',', '.', (string)$request->request->get('forfait_ef', '0'));
            $prixM3Ec = $this->parseNullableFloat($request->request->get('prix_m3_ec'));
            $prixM3Ef = $this->parseNullableFloat($request->request->get('prix_m3_ef'));

            if ($year < 2000 || $year > 2100) {
                $this->addFlash('error', 'Année invalide.');
                return $this->redirectToRoute('admin_parametres', ['annee' => $selectedYear]);
            }

            $param = $paramRepo->findOneBy(['annee' => $year]);
            if (!$param) {
                $param = (new Parametre())->setAnnee($year);
                $em->persist($param);
            }
            $param
                ->setForfaitEc(max(0, $forfaitEc))
                ->setForfaitEf(max(0, $forfaitEf))
                ->setPrixM3Ec($prixM3Ec)
                ->setPrixM3Ef($prixM3Ef);

            $em->flush();
            $this->addFlash('success', sprintf('Paramètres forfait enregistrés pour %d.', $year));

            return $this->redirectToRoute('admin_parametres', ['annee' => $year]);
        }

        $current = $paramRepo->findOneBy(['annee' => $selectedYear]);
        if (!$current) {
            $forfaits = $paramRepo->getForfaitsForYear($selectedYear);
            $prixM3 = $paramRepo->getPrixM3ForYear($selectedYear);
            $currentEc = (float)$forfaits['ec'];
            $currentEf = (float)$forfaits['ef'];
            $currentPrixM3Ec = $prixM3['ec'];
            $currentPrixM3Ef = $prixM3['ef'];
        } else {
            $currentEc = $current->getForfaitEc();
            $currentEf = $current->getForfaitEf();
            $currentPrixM3Ec = $current->getPrixM3Ec();
            $currentPrixM3Ef = $current->getPrixM3Ef();
        }

        $params = $paramRepo->findBy([], ['annee' => 'DESC', 'id' => 'DESC']);

        return $this->render('admin/parametres.html.twig', [
            'selectedYear' => $selectedYear,
            'currentEc' => $currentEc,
            'currentEf' => $currentEf,
            'currentPrixM3Ec' => $currentPrixM3Ec,
            'currentPrixM3Ef' => $currentPrixM3Ef,
            'activeYear' => $activeYear,
            'params' => $params,
        ]);
    }
}
