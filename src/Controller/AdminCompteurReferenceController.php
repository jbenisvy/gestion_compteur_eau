<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Compteur;
use App\Entity\Lot;
use App\Repository\CompteurRepository;
use App\Repository\LotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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
            $postedPhotos = $request->files->all('photos');
            $postedRemovePhotos = $request->request->all('removePhotos');
            $updated = 0;
            $photosUpdated = 0;
            $photosRemoved = 0;
            $photoErrors = 0;

            foreach ($byLotAndSlot as $lotId => $slotMap) {
                $lotId = (int) $lotId;
                $row = $postedRows[$lotId] ?? [];
                $photoRow = $postedPhotos[$lotId] ?? [];
                $removeRow = $postedRemovePhotos[$lotId] ?? [];
                if (!is_array($row)) {
                    $row = [];
                }
                if (!is_array($photoRow)) {
                    $photoRow = [];
                }
                if (!is_array($removeRow)) {
                    $removeRow = [];
                }

                foreach (self::SLOTS as $slot) {
                    if (!isset($slotMap[$slot]) || !$slotMap[$slot] instanceof Compteur) {
                        continue;
                    }

                    $compteur = $slotMap[$slot];
                    if (array_key_exists($slot, $row)) {
                        $value = trim((string) $row[$slot]);
                        $newNumero = $value !== '' ? $value : null;

                        if ($compteur->getNumeroSerie() !== $newNumero) {
                            $compteur->setNumeroSerie($newNumero);
                            $em->persist($compteur);
                            $updated++;
                        }
                    }

                    $shouldRemovePhoto = isset($removeRow[$slot]) && (string) $removeRow[$slot] === '1';
                    if ($shouldRemovePhoto && $compteur->getPhoto()) {
                        $this->deleteCompteurPhotoFile($compteur->getPhoto());
                        $compteur->setPhoto(null);
                        $em->persist($compteur);
                        $photosRemoved++;
                    }

                    $photoFile = $photoRow[$slot] ?? null;
                    if ($photoFile instanceof UploadedFile) {
                        if (!$photoFile->isValid()) {
                            $photoErrors++;
                            continue;
                        }

                        try {
                            $webPath = $this->storeCompteurPhoto($compteur, $photoFile);
                            if ($compteur->getPhoto() !== $webPath) {
                                $compteur->setPhoto($webPath);
                                $em->persist($compteur);
                                $photosUpdated++;
                            }
                        } catch (FileException $e) {
                            $photoErrors++;
                        }
                    }
                }
            }

            if ($updated > 0 || $photosUpdated > 0 || $photosRemoved > 0) {
                $em->flush();
                $this->addFlash(
                    'success',
                    sprintf(
                        '%d numéro(s) mis à jour, %d photo(s) ajoutée(s), %d photo(s) supprimée(s).',
                        $updated,
                        $photosUpdated,
                        $photosRemoved
                    )
                );
            } else {
                $this->addFlash('info', 'Aucune modification détectée.');
            }
            if ($photoErrors > 0) {
                $this->addFlash(
                    'error',
                    sprintf(
                        '%d photo(s) non enregistrée(s): dossier d’upload non accessible en écriture.',
                        $photoErrors
                    )
                );
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

    private function storeCompteurPhoto(Compteur $compteur, UploadedFile $file): string
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $uploadDir = $projectDir . '/public/uploads/compteurs';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new FileException('Impossible de créer le dossier d’upload.');
            }
        }
        if (!is_writable($uploadDir)) {
            throw new FileException('Dossier d’upload non accessible en écriture.');
        }

        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        if (!preg_match('/^(jpe?g|png|webp|gif|heic|heif)$/', $ext)) {
            $ext = 'jpg';
        }

        $numeroSlug = trim((string) ($compteur->getNumeroSerie() ?? ''));
        $numeroSlug = $numeroSlug !== '' ? mb_strtolower($numeroSlug) : 'sans-numero';
        $numeroSlug = preg_replace('/[^a-z0-9]+/u', '-', $numeroSlug) ?? 'sans-numero';
        $numeroSlug = trim($numeroSlug, '-');
        if ($numeroSlug === '') {
            $numeroSlug = 'sans-numero';
        }

        $basename = sprintf(
            'compteur_%d_%s_%s_%s.%s',
            (int) $compteur->getId(),
            $numeroSlug,
            date('YmdHis'),
            substr(bin2hex(random_bytes(3)), 0, 6),
            $ext
        );
        $file->move($uploadDir, $basename);

        return '/uploads/compteurs/' . $basename;
    }

    private function deleteCompteurPhotoFile(?string $webPath): void
    {
        $path = trim((string) $webPath);
        if ($path === '' || !str_starts_with($path, '/uploads/')) {
            return;
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $absolutePath = $projectDir . '/public' . $path;
        if (!is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
    }
}
