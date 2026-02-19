<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Compteur;
use App\Repository\CompteurRepository;
use App\Repository\CoproprietaireRepository;
use App\Repository\LotCoproprietaireRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CompteurPhotoController extends AbstractController
{
    #[Route('/compteur/{id}/photos', name: 'compteur_photo_history', requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function history(
        int $id,
        Request $request,
        CompteurRepository $compteurRepo,
        CoproprietaireRepository $coproRepo,
        LotCoproprietaireRepository $lotCoproRepo
    ): Response {
        /** @var Compteur|null $compteur */
        $compteur = $compteurRepo->find($id);
        if (!$compteur) {
            throw $this->createNotFoundException('Compteur introuvable.');
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            $copro = $user ? $coproRepo->findOneBy(['user' => $user]) : null;
            if (!$copro || !$lotCoproRepo->hasAnyLinkForCoproAndLot($copro, $compteur->getLot())) {
                throw $this->createAccessDeniedException('Accès non autorisé à ce compteur.');
            }
        }

        $projectDir = (string)$this->getParameter('kernel.project_dir');
        $uploadDir = $projectDir . '/public/uploads/compteurs';

        $files = [];
        $patterns = [
            $uploadDir . '/compteur_' . $id . '_*.*', // format actuel versionné
            $uploadDir . '/compteur_' . $id . '.*',   // format legacy underscore
            $uploadDir . '/compteur-' . $id . '-*.*', // ancien format versionné avec tirets
            $uploadDir . '/compteur-' . $id . '.*',   // ancien format simple avec tirets
            $projectDir . '/public/uploads/coproprietaires/*/compteur_' . $id . '_*.*',
        ];
        $numeroSerie = trim((string)($compteur->getNumeroSerie() ?? ''));
        if ($numeroSerie !== '') {
            // Certains imports ont pu nommer les fichiers avec le numéro de série.
            $patterns[] = $uploadDir . '/' . $numeroSerie . '*.*';
        }

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $f) {
                if (is_file($f)) {
                    $files[$f] = true;
                }
            }
        }

        $currentPhotoPath = $compteur->getPhoto();
        $virtualUrls = [];
        if ($currentPhotoPath) {
            $normalizedPath = parse_url($currentPhotoPath, PHP_URL_PATH) ?: $currentPhotoPath;
            $candidate = $projectDir . '/public' . $normalizedPath;
            if (is_file($candidate)) {
                $files[$candidate] = true;
            } else {
                // Fallback: l'URL peut être servie par un autre stockage/montage non visible en FS local.
                $virtualUrls[$normalizedPath] = true;
            }
        }
        $hintPhoto = (string) $request->query->get('photo', '');
        if ($hintPhoto !== '') {
            $hintPhotoPath = parse_url($hintPhoto, PHP_URL_PATH) ?: $hintPhoto;
            if (str_starts_with($hintPhotoPath, '/uploads/')) {
                $virtualUrls[$hintPhotoPath] = true;
            }
        }

        $list = array_keys($files);
        usort($list, fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        $photos = [];
        $seenUrls = [];
        foreach ($list as $path) {
            $basename = basename($path);
            $relativePath = str_replace($projectDir . '/public', '', $path);
            $url = str_starts_with($relativePath, '/') ? $relativePath : ('/uploads/compteurs/' . $basename);
            $seenUrls[$url] = true;
            $mtime = filemtime($path) ?: time();
            $photos[] = [
                'name' => $basename,
                'url' => $url,
                'size_kb' => round((filesize($path) ?: 0) / 1024, 1),
                'updated_at' => (new \DateTimeImmutable())->setTimestamp($mtime),
                'is_current' => $currentPhotoPath === $url,
                'source' => str_contains($url, '/uploads/coproprietaires/') ? 'Dossier copropriétaire' : 'Dossier compteurs',
            ];
        }

        $currentCoproFolder = $this->resolveCurrentCoproFolder($compteur, $projectDir);
        if ($currentCoproFolder !== null && is_dir($currentCoproFolder)) {
            foreach (glob($currentCoproFolder . '/*.{jpg,jpeg,png,webp,gif,heic,heif}', GLOB_BRACE) ?: [] as $path) {
                if (!is_file($path)) {
                    continue;
                }
                $relativePath = str_replace($projectDir . '/public', '', $path);
                $url = str_starts_with($relativePath, '/') ? $relativePath : null;
                if ($url === null || isset($seenUrls[$url])) {
                    continue;
                }
                $seenUrls[$url] = true;
                $mtime = filemtime($path) ?: time();
                $photos[] = [
                    'name' => basename($path),
                    'url' => $url,
                    'size_kb' => round((filesize($path) ?: 0) / 1024, 1),
                    'updated_at' => (new \DateTimeImmutable())->setTimestamp($mtime),
                    'is_current' => $currentPhotoPath === $url,
                    'source' => 'Dossier copropriétaire',
                ];
            }
        }

        foreach (array_keys($virtualUrls) as $url) {
            if (isset($seenUrls[$url])) {
                continue;
            }
            $photos[] = [
                'name' => basename((string) parse_url($url, PHP_URL_PATH)),
                'url' => $url,
                'size_kb' => null,
                'updated_at' => null,
                'is_current' => ($currentPhotoPath === $url),
                'source' => 'URL externe',
            ];
        }

        usort($photos, static function (array $a, array $b): int {
            $timeA = $a['updated_at'] instanceof \DateTimeInterface ? $a['updated_at']->getTimestamp() : 0;
            $timeB = $b['updated_at'] instanceof \DateTimeInterface ? $b['updated_at']->getTimestamp() : 0;
            return $timeB <=> $timeA;
        });

        return $this->render('compteur/photo_history.html.twig', [
            'compteur' => $compteur,
            'photos' => $photos,
        ]);
    }

    private function resolveCurrentCoproFolder(Compteur $compteur, string $projectDir): ?string
    {
        $lot = $compteur->getLot();
        if ($lot === null) {
            return null;
        }

        $copro = $lot->getCoproprietaire(new \DateTimeImmutable('today'));
        if ($copro !== null) {
            $raw = sprintf('%d_%s', (int) $copro->getId(), $copro->getNomComplet());
        } else {
            $raw = 'lot_' . trim((string) $lot->getNumeroLot());
        }

        $folder = mb_strtolower($raw);
        $folder = preg_replace('/[^a-z0-9]+/u', '-', $folder) ?? '';
        $folder = trim($folder, '-');
        if ($folder === '') {
            return null;
        }

        return $projectDir . '/public/uploads/coproprietaires/' . $folder;
    }
}
