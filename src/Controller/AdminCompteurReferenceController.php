<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Compteur;
use App\Entity\Lot;
use App\Repository\CompteurRepository;
use App\Repository\LotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminCompteurReferenceController extends AbstractController
{
    private const SLOTS = ['EC_SDB', 'EC_CUISINE', 'EF_SDB', 'EF_CUISINE'];

    #[Route('/admin/compteurs-reference', name: 'admin_compteurs_reference', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        LotRepository $lotRepo,
        CompteurRepository $compteurRepo,
        EntityManagerInterface $em
    ): Response {
        [$lots, $byLotAndSlot] = $this->buildGrid($lotRepo, $compteurRepo);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_compteurs_reference_save', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_compteurs_reference');
            }

            $postedRows = $request->request->all('rows');
            $updated = 0;

            foreach ($postedRows as $lotId => $row) {
                $lotId = (int) $lotId;
                if (!isset($byLotAndSlot[$lotId]) || !is_array($row)) {
                    continue;
                }

                foreach (self::SLOTS as $slot) {
                    if (!isset($byLotAndSlot[$lotId][$slot]) || !$byLotAndSlot[$lotId][$slot] instanceof Compteur) {
                        continue;
                    }

                    $value = isset($row[$slot]) ? trim((string) $row[$slot]) : '';
                    $newNumero = $value !== '' ? $value : null;
                    $compteur = $byLotAndSlot[$lotId][$slot];

                    if ($compteur->getNumeroSerie() !== $newNumero) {
                        $compteur->setNumeroSerie($newNumero);
                        $em->persist($compteur);
                        $updated++;
                    }
                }
            }

            if ($updated > 0) {
                $em->flush();
                $this->addFlash('success', sprintf('%d compteur(s) mis à jour.', $updated));
            } else {
                $this->addFlash('info', 'Aucune modification détectée.');
            }

            return $this->redirectToRoute('admin_compteurs_reference');
        }

        return $this->render('admin/compteur_reference.html.twig', [
            'lots' => $lots,
            'byLotAndSlot' => $byLotAndSlot,
            'slots' => self::SLOTS,
        ]);
    }

    #[Route('/admin/compteurs-reference/export.csv', name: 'admin_compteurs_reference_export', methods: ['GET'])]
    public function exportCsv(LotRepository $lotRepo, CompteurRepository $compteurRepo): Response
    {
        [$lots, $byLotAndSlot] = $this->buildGrid($lotRepo, $compteurRepo);

        $response = new StreamedResponse(function () use ($lots, $byLotAndSlot): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['lot_id', 'lot_numero', 'coproprietaire', 'ec_sdb', 'ec_cuisine', 'ef_sdb', 'ef_cuisine'], ';');
            foreach ($lots as $lot) {
                $copro = $lot->getCoproprietaire(new \DateTimeImmutable('today'));
                fputcsv($out, [
                    $lot->getId(),
                    $lot->getNumeroLot(),
                    $copro?->getNomComplet() ?? 'N/A',
                    $byLotAndSlot[$lot->getId()]['EC_SDB']?->getNumeroSerie() ?? '',
                    $byLotAndSlot[$lot->getId()]['EC_CUISINE']?->getNumeroSerie() ?? '',
                    $byLotAndSlot[$lot->getId()]['EF_SDB']?->getNumeroSerie() ?? '',
                    $byLotAndSlot[$lot->getId()]['EF_CUISINE']?->getNumeroSerie() ?? '',
                ], ';');
            }

            fclose($out);
        });

        $filename = sprintf('compteurs_reference_%s.csv', (new \DateTimeImmutable())->format('Ymd_His'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/admin/compteurs-reference/import.csv', name: 'admin_compteurs_reference_import', methods: ['POST'])]
    public function importCsv(
        Request $request,
        LotRepository $lotRepo,
        CompteurRepository $compteurRepo,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('admin_compteurs_reference_import', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_compteurs_reference');
        }

        /** @var UploadedFile|null $csv */
        $csv = $request->files->get('csv_file');
        if (!$csv instanceof UploadedFile) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier CSV.');
            return $this->redirectToRoute('admin_compteurs_reference');
        }

        $path = $csv->getPathname();
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            $this->addFlash('error', 'Impossible de lire le fichier CSV.');
            return $this->redirectToRoute('admin_compteurs_reference');
        }

        $firstLine = (string) fgets($handle);
        rewind($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        if (!is_array($header)) {
            fclose($handle);
            $this->addFlash('error', 'Le fichier CSV est vide.');
            return $this->redirectToRoute('admin_compteurs_reference');
        }

        $normalizedHeader = array_map(
            static fn ($v): string => mb_strtolower(trim((string) $v)),
            $header
        );
        $col = array_flip($normalizedHeader);

        $required = ['lot_numero', 'ec_sdb', 'ec_cuisine', 'ef_sdb', 'ef_cuisine'];
        foreach ($required as $column) {
            if (!array_key_exists($column, $col)) {
                fclose($handle);
                $this->addFlash('error', 'Colonnes attendues: lot_numero;ec_sdb;ec_cuisine;ef_sdb;ef_cuisine.');
                return $this->redirectToRoute('admin_compteurs_reference');
            }
        }

        [$lots, $byLotAndSlot] = $this->buildGrid($lotRepo, $compteurRepo);
        $byLotNumero = [];
        foreach ($lots as $lot) {
            $key = mb_strtolower(trim($lot->getNumeroLot()));
            if ($key !== '') {
                $byLotNumero[$key] = $lot;
            }
        }

        $updated = 0;
        $lineNumber = 1;
        $unknownLots = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;
            if (!is_array($row) || count($row) === 0) {
                continue;
            }

            $lotNumero = mb_strtolower(trim((string) ($row[$col['lot_numero']] ?? '')));
            if ($lotNumero === '' || !isset($byLotNumero[$lotNumero])) {
                $unknownLots++;
                continue;
            }

            $lot = $byLotNumero[$lotNumero];
            $lotId = (int) $lot->getId();

            foreach (self::SLOTS as $slot) {
                $key = mb_strtolower($slot);
                $newNumero = trim((string) ($row[$col[$key]] ?? ''));
                $newNumero = $newNumero !== '' ? $newNumero : null;
                $compteur = $byLotAndSlot[$lotId][$slot] ?? null;

                if (!$compteur instanceof Compteur) {
                    continue;
                }

                if ($compteur->getNumeroSerie() !== $newNumero) {
                    $compteur->setNumeroSerie($newNumero);
                    $em->persist($compteur);
                    $updated++;
                }
            }
        }
        fclose($handle);

        if ($updated > 0) {
            $em->flush();
        }

        $this->addFlash('success', sprintf('Import terminé: %d compteur(s) mis à jour.', $updated));
        if ($unknownLots > 0) {
            $this->addFlash('warning', sprintf('%d ligne(s) ignorée(s): lot introuvable.', $unknownLots));
        }

        return $this->redirectToRoute('admin_compteurs_reference');
    }

    /**
     * @return array{0: array<int,Lot>, 1: array<int,array<string,?Compteur>>}
     */
    private function buildGrid(LotRepository $lotRepo, CompteurRepository $compteurRepo): array
    {
        /** @var array<int,Lot> $lots */
        $lots = $lotRepo->findAll();
        usort($lots, static function (Lot $a, Lot $b): int {
            $cmp = strnatcasecmp(trim($a->getNumeroLot()), trim($b->getNumeroLot()));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        $byLotAndSlot = [];
        foreach ($lots as $lot) {
            $byLotAndSlot[$lot->getId()] = array_fill_keys(self::SLOTS, null);
        }

        /** @var array<int,Compteur> $compteurs */
        $compteurs = $compteurRepo->createQueryBuilder('c')
            ->leftJoin('c.lot', 'l')->addSelect('l')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($compteurs as $compteur) {
            $lot = $compteur->getLot();
            if (!$lot || !isset($byLotAndSlot[$lot->getId()])) {
                continue;
            }

            $slot = $this->resolveSlot($compteur);
            if ($slot === null) {
                continue;
            }

            if (!isset($byLotAndSlot[$lot->getId()][$slot]) || !$byLotAndSlot[$lot->getId()][$slot] instanceof Compteur) {
                $byLotAndSlot[$lot->getId()][$slot] = $compteur;
            }
        }

        return [$lots, $byLotAndSlot];
    }

    private function resolveSlot(Compteur $compteur): ?string
    {
        $type = strtoupper(trim($compteur->getType()));
        if (!in_array($type, ['EC', 'EF'], true)) {
            return null;
        }

        $label = mb_strtolower((string) $compteur->getEmplacement());
        $isSdb = (bool) preg_match('/\b(sdb|salle\s*de\s*bains?|salle\s*d[\'’]?eau|sde)\b/u', $label);

        if ($type === 'EC') {
            return $isSdb ? 'EC_SDB' : 'EC_CUISINE';
        }

        return $isSdb ? 'EF_SDB' : 'EF_CUISINE';
    }
}
