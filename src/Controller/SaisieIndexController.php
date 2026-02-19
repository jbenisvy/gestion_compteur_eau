<?php

namespace App\Controller;

use App\Dto\SaisieIndexItem;
use App\Entity\Compteur;
use App\Entity\EtatCompteur;
use App\Entity\Releve;
use App\Entity\ReleveItem;
use App\Form\SaisieIndexType;
use App\Repository\CompteurRepository;
use App\Repository\EtatCompteurRepository;
use App\Repository\LotRepository;
use App\Repository\ParametreRepository;
use App\Repository\ReleveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SaisieIndexController extends AbstractController
{
    #[Route(
        '/saisie/{lotId}/{annee}',
        name: 'saisie_index_form',
        requirements: ['lotId' => '\d+', 'annee' => '\d{4}'],
        defaults: ['lotId' => 1, 'annee' => 2024]
    )]
    public function saisir(
        int $lotId,
        int $annee,
        Request $request,
        LotRepository $lotRepo,
        CompteurRepository $compteurRepo,
        EtatCompteurRepository $etatRepo,
        ReleveRepository $releveRepo,
        ParametreRepository $paramRepo,
        EntityManagerInterface $em
    ): Response
    {
        $lot = $lotRepo->find($lotId);
        if (!$lot) {
            throw $this->createNotFoundException('Lot introuvable');
        }

        // On affiche TOUT (même supprimés) pour permettre le changement d'état
        /** @var Compteur[] $compteurs */
        $compteurs = $compteurRepo->createQueryBuilder('c')
            ->leftJoin('c.etatCompteur', 'e')->addSelect('e')
            ->andWhere('c.lot = :lot')->setParameter('lot', $lot)
            ->orderBy('c.id', 'ASC')
            ->getQuery()->getResult();
        $compteurs = $this->uniqueCompteursForSaisie($compteurs);

        if (!$compteurs) {
            $this->addFlash('warning', "Aucun compteur trouvé pour ce lot.");
        }

        // --- Éditabilité (pilotée par Parametre.annee_encours) ---
        $qbMax = $em->createQuery('SELECT MAX(r.annee) FROM App\Entity\Releve r WHERE r.lot = :lot')
            ->setParameter('lot', $lot);
        $maxAnnee = $qbMax->getSingleScalarResult();
        $maxAnnee = $maxAnnee !== null ? (int)$maxAnnee : null;

        $anneeActive = $paramRepo->getAnneeEnCours((int)date('Y'));
        if ($anneeActive !== null && $annee > $anneeActive) {
            return $this->redirectToRoute('saisie_index_form', ['lotId' => $lotId, 'annee' => $anneeActive]);
        }
        $isEditable = ($anneeActive !== null) ? ($annee === $anneeActive) : false;
        $forfaitsAnnee = $paramRepo->getForfaitsForYear($annee);

        // 1) Précharger les états par id (pour remonter les codes)
        $etatMap = [];
        foreach ($etatRepo->findAll() as $etat) {
            $etatMap[$etat->getId()] = $etat;
        }

        // 2) Précharger relevés N et N-1 (nouveau modèle)
        $relevePrev = $releveRepo->findOneByAnneeAndLot($annee - 1, $lot);
        $releveCurr = $releveRepo->findOneByAnneeAndLot($annee, $lot);

        $itemsPrevByCompteurId = [];
        if ($relevePrev) {
            foreach ($relevePrev->getItems() as $item) {
                $cmp = $item->getCompteur();
                if ($cmp) {
                    $itemsPrevByCompteurId[$cmp->getId()] = $item;
                }
            }
        }

        $itemsCurrByCompteurId = [];
        if ($releveCurr) {
            foreach ($releveCurr->getItems() as $item) {
                $cmp = $item->getCompteur();
                if ($cmp) {
                    $itemsCurrByCompteurId[$cmp->getId()] = $item;
                }
            }
        }

        // 3) Construire les DTO
        $items = [];
        foreach ($compteurs as $c) {
            $dto = new SaisieIndexItem();
            $dto->compteur = $c;
            $dto->etat = method_exists($c, 'getEtatCompteur') ? $c->getEtatCompteur() : ($etatRepo->findOneBy([]) ?: null);
            $dto->dateInstallation = method_exists($c, 'getDateInstallation') ? $c->getDateInstallation() : null;

            // Détection pièce/typeEau à partir d'emplacement
            $empRaw  = (string) $c->getEmplacement();
            $emp     = mb_strtolower($empRaw);
            $empNorm = preg_replace('/[^a-z0-9]+/u', ' ', $emp);

            if (preg_match('/\b(cuis|cuisine)\b/u', $empNorm)) {
                $dto->piece = 'cuisine';
            } elseif (preg_match('/\b(sdb|salle\s*de\s*bains?|salle\s*d[\'’]?eau|sde)\b/u', $empNorm)) {
                $dto->piece = 'sdb';
            } else {
                $dto->piece = null;
            }

            if (preg_match('/\b(e\s*c|eau\s*chaud[e]?|chaud[e]?|hot)\b/u', $empNorm)) {
                $dto->typeEau = 'chaude';
            } elseif (preg_match('/\b(e\s*f|eau\s*froid[e]?|froid[e]?|cold)\b/u', $empNorm)) {
                $dto->typeEau = 'froide';
            } else {
                $dto->typeEau = null;
            }

            // Forfait par défaut selon type compteur et paramètres de l'année
            $dto->forfait = (int)round($c->getType() === 'EF' ? (float)$forfaitsAnnee['ef'] : (float)$forfaitsAnnee['ec']);

            // Préremplissage N-1 (releve_item)
            $prevItem = $itemsPrevByCompteurId[$c->getId()] ?? null;
            if ($prevItem instanceof ReleveItem) {
                $prevEtat = $prevItem->getEtatId() !== null ? ($etatMap[$prevItem->getEtatId()] ?? null) : null;
                $prevCode = $prevEtat ? $prevEtat->getCode() : null;
                if ($prevCode && mb_strtolower($prevCode) === 'remplace') {
                    $dto->indexPrevious = $prevItem->getIndexNouveauCompteur();
                } else {
                    $dto->indexPrevious = $prevItem->getIndexN();
                }
            } else {
                $dto->indexPrevious = null;
            }

            // Préremplissage année courante (releve_item)
            $currItem = $itemsCurrByCompteurId[$c->getId()] ?? null;
                if ($currItem instanceof ReleveItem) {
                    $currCode = null;
                    if ($currItem->getEtatId() !== null && isset($etatMap[$currItem->getEtatId()])) {
                        $dto->etat = $etatMap[$currItem->getEtatId()];
                        $currCode = mb_strtolower((string)$etatMap[$currItem->getEtatId()]->getCode());
                        $currLabel = mb_strtolower((string)$etatMap[$currItem->getEtatId()]->getLibelle());
                        if ($currCode === '') {
                            $currCode = $currLabel;
                        } else {
                            $currCode .= ' ' . $currLabel;
                        }
                    }
                    $dto->indexN       = $currItem->getIndexN();
                    $dto->indexDemonte = $currItem->getIndexCompteurDemonté();
                    $dto->indexNouveau = $currItem->getIndexNouveauCompteur();
                    $dto->commentaire  = $currItem->getCommentaire();
                    $dto->consommationCalculee = $currItem->getConsommation() !== null ? (int)((float)$currItem->getConsommation()) : null;
                if ($currCode !== null && (
                        str_contains($currCode, 'forfait')
                        || str_contains($currCode, 'bloqu')
                        || str_contains($currCode, 'non communiqu')
                        || str_contains($currCode, 'index compteur non')
                    ) && $dto->consommationCalculee !== null && $dto->consommationCalculee > 0) {
                        $dto->forfait = $dto->consommationCalculee;
                    }
                }

            // Par défaut pour remplacé : index démonté = N-1
            if ($dto->indexDemonte === null) {
                $dto->indexDemonte = $dto->indexPrevious ?? 0;
            }

            // --- Numéro lisible + ID + photo existante / vignette
            $dto->compteurId = (int) $c->getId();

            // 1) Essaye plusieurs getters pour le N° de compteur
            $numeroLisible = null;
            foreach (['getNumeroSerie', 'getNumero', 'getNumeroCompteur', 'getNumero_compteur', 'getNumero_serie'] as $getter) {
                if (method_exists($c, $getter)) {
                    $val = trim((string) ($c->$getter() ?? ''));
                    if ($val !== '') { $numeroLisible = $val; break; }
                }
            }
            $dto->compteurNumero = $numeroLisible;

            // 2) Photo : d'abord via entité, sinon via fichier déjà présent (compteur_{id}.*)
            $dto->photoUrl = null;
            if (method_exists($c, 'getPhoto')) {
                $dto->photoUrl = $c->getPhoto() ?: null;
            }
            if ($dto->photoUrl === null) {
                $dto->photoUrl = $this->findLatestCompteurPhotoUrl($c);
            }

            $items[] = $dto;
        }

        // 2) Répartition par pièce pour typer ceux inconnus
        $byPiece = [];
        foreach ($items as $i => $dto) {
            $p = $dto->piece ?? 'cuisine';
            $byPiece[$p] ??= [];
            $byPiece[$p][] = $i;
        }
        foreach ($byPiece as $piece => $idxs) {
            $hot     = array_filter($idxs, fn($i) => $items[$i]->typeEau === 'chaude');
            $cold    = array_filter($idxs, fn($i) => $items[$i]->typeEau === 'froide');
            $unknown = array_filter($idxs, fn($i) => $items[$i]->typeEau === null);

            if (count($unknown) > 0) {
                if (count($hot) === 0 && count($cold) === 0) {
                    $toggleHot = true;
                    foreach ($unknown as $k) { $items[$k]->typeEau = $toggleHot ? 'chaude' : 'froide'; $toggleHot = !$toggleHot; }
                } elseif (count($hot) === 0) {
                    $first = array_shift($unknown);
                    if ($first !== null) { $items[$first]->typeEau = 'chaude'; }
                    foreach ($unknown as $k) { $items[$k]->typeEau = 'froide'; }
                } elseif (count($cold) === 0) {
                    $first = array_shift($unknown);
                    if ($first !== null) { $items[$first]->typeEau = 'froide'; }
                    foreach ($unknown as $k) { $items[$k]->typeEau = 'chaude'; }
                } else {
                    $toHot = count($cold) > count($hot);
                    foreach ($unknown as $k) { $items[$k]->typeEau = $toHot ? 'chaude' : 'froide'; $toHot = !$toHot; }
                }
            }
            foreach ($idxs as $k) { if ($items[$k]->piece === null) { $items[$k]->piece = 'cuisine'; } }
        }

        // 3) Formulaire
        $form = $this->createForm(SaisieIndexType::class, ['items' => $items], [
            'is_editable' => $isEditable,
        ]);
        $form->handleRequest($request);

        $totaux = [
            'cuisine' => ['chaude' => 0, 'froide' => 0],
            'sdb'     => ['chaude' => 0, 'froide' => 0],
            'global'  => ['chaude' => 0, 'froide' => 0, 'total' => 0],
        ];

        // --------- SAUVEGARDE ---------
        if ($form->isSubmitted()) {
            // Affiche les erreurs de validation (utile au debug)
            if (!$form->isValid()) {
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('danger', $error->getMessage());
                }
            }

            if (!$isEditable) {
                $this->addFlash('warning', "L'année $annee est figée. Aucune modification n'a été enregistrée.");
                return $this->redirectToRoute('saisie_index_form', ['lotId' => $lotId, 'annee' => $annee]);
            }

            // Récupération robuste des items soumis
            /** @var SaisieIndexItem[] $submittedItems */
            $submittedItems = [];
            if ($form->has('items')) {
                $submittedItems = $form->get('items')->getData() ?? [];
            }
            if (empty($submittedItems)) {
                $rootData = $form->getData();
                if (is_array($rootData) && isset($rootData['items']) && is_iterable($rootData['items'])) {
                    $submittedItems = $rootData['items'];
                }
            }
            if (!is_iterable($submittedItems) || count($submittedItems) === 0) {
                $this->addFlash('warning', 'Aucun élément à enregistrer (la collection d’items est vide).');
                return $this->redirectToRoute('saisie_index_form', ['lotId' => $lotId, 'annee' => $annee]);
            }

            $conn = $em->getConnection();
            $conn->beginTransaction();
            try {
                // On a besoin d'accéder aux SOUS-FORMULAIRES pour lire le champ photo unmapped
                $itemForms = $form->get('items');

                // Upsert fiche maître (releve_new)
                $releve = $releveRepo->findOneByAnneeAndLot($annee, $lot);
                if (!$releve) {
                    $releve = new Releve();
                    $releve->setLot($lot);
                    $releve->setAnnee($annee);
                }
                $now = new \DateTimeImmutable();
                $releve->setUpdatedAt($now);
                $em->persist($releve);

                $existingItemsByCompteurId = [];
                foreach ($releve->getItems() as $item) {
                    $cmp = $item->getCompteur();
                    if ($cmp) {
                        $existingItemsByCompteurId[$cmp->getId()] = $item;
                    }
                }

                foreach ($submittedItems as $idx => $dto) {
                    if (!$dto instanceof SaisieIndexItem || !$dto->compteur) {
                        continue;
                    }

                    // ---- Upsert ReleveItem (précoce pour pouvoir fusionner proprement) ----
                    $compteurId = $dto->compteur->getId();
                    $item = $existingItemsByCompteurId[$compteurId] ?? new ReleveItem();
                    $isExistingItem = (bool) $item->getId();
                    if (!$isExistingItem) {
                        $item->setReleve($releve);
                        $item->setCompteur($dto->compteur);
                        $releve->addItem($item);
                    }

                    // Fusion anti-écrasement: si un champ est vide au POST, on garde la valeur déjà enregistrée.
                    $indexN1 = $dto->indexPrevious;
                    $indexN = $dto->indexN;
                    $indexDemonte = $dto->indexDemonte;
                    $indexNouveau = $dto->indexNouveau;
                    $commentaire = $dto->commentaire;

                    if ($isExistingItem) {
                        if ($indexN1 === null) {
                            $indexN1 = $item->getIndexN1();
                        }
                        if ($indexN === null) {
                            $indexN = $item->getIndexN();
                        }
                        if ($indexDemonte === null) {
                            $indexDemonte = $item->getIndexCompteurDemonté();
                        }
                        if ($indexNouveau === null) {
                            $indexNouveau = $item->getIndexNouveauCompteur();
                        }
                        if ($commentaire === null || trim((string) $commentaire) === '') {
                            $commentaire = $item->getCommentaire();
                        }
                    }

                    // ---- Calcul consommation (mêmes règles que dans le JS) ----
                    $prev = (int)($indexN1 ?? 0);
                    $codeEtat = null;
                    if ($dto->etat instanceof EtatCompteur) {
                        $code = method_exists($dto->etat, 'getCode') ? (string)$dto->etat->getCode() : '';
                        $label = method_exists($dto->etat, 'getLibelle') ? (string)$dto->etat->getLibelle() : '';
                        $merged = trim($code . ' ' . $label);
                        $codeEtat = $merged !== '' ? mb_strtolower($merged) : null;
                    } elseif (is_string($dto->etat)) {
                        $codeEtat = mb_strtolower($dto->etat);
                    }

                    $isForfaitLike = (bool)($codeEtat && (
                        str_contains($codeEtat, 'forfait')
                        || str_contains($codeEtat, 'bloqu')
                        || str_contains($codeEtat, 'non communiqu')
                        || str_contains($codeEtat, 'index compteur non')
                    ));

                    $isNouveauCompteur = (bool)($codeEtat && str_contains($codeEtat, 'nouveau'));
                    $isRemplacement = (bool)($codeEtat && (str_contains($codeEtat, 'remplac') || str_contains($codeEtat, 'démont') || str_contains($codeEtat, 'demonte')));

                    if ($codeEtat && str_contains($codeEtat, 'suppr')) {
                        $cons = 0;
                    } elseif ($isForfaitLike) {
                        $cons = (int)($dto->forfait ?? 0);
                    } elseif ($isNouveauCompteur) {
                        $ancienActif = $dto->ancienFonctionnaitEncore
                            || ((int)($indexDemonte ?? 0) > $prev);
                        if ($ancienActif) {
                            $cons = max(0, (int)($indexDemonte ?? 0) - $prev)
                                + max(0, (int)($indexNouveau ?? 0));
                        } else {
                            $cons = max(0, (int)($indexNouveau ?? 0));
                        }
                    } elseif ($isRemplacement) {
                        $cons = max(0, (int)($indexDemonte ?? 0) - $prev) + max(0, (int)($indexNouveau ?? 0));
                    } else {
                        $cons = max(0, (int)($indexN ?? 0) - $prev);
                    }
                    $dto->consommationCalculee = $cons;

                    // Totaux (pour réaffichage)
                    $piece = $dto->piece ?? 'autre';
                    $type  = $dto->typeEau ?? 'autre';
                    if (in_array($piece, ['cuisine','sdb'], true) && in_array($type, ['chaude','froide'], true)) {
                        $totaux[$piece][$type] += $cons;
                        $totaux['global'][$type] += $cons;
                        $totaux['global']['total'] += $cons;
                    } else {
                        $totaux['global']['total'] += $cons;
                    }

                    $etatId = ($dto->etat instanceof EtatCompteur) ? $dto->etat->getId() : null;
                    if ($etatId === null && $isExistingItem) {
                        $etatId = $item->getEtatId();
                    }
                    $item->setEtatId($etatId);
                    $item->setForfait($isForfaitLike);
                    $item->setIndexN1($indexN1 !== null ? (int)$indexN1 : null);
                    $item->setIndexN($indexN !== null ? (int)$indexN : null);
                    $item->setIndexCompteurDemonté($indexDemonte !== null ? (int)$indexDemonte : null);
                    $item->setIndexNouveauCompteur($indexNouveau !== null ? (int)$indexNouveau : null);
                    $item->setCommentaire($commentaire !== null ? (string)$commentaire : null);

                    $numeroSaisi = trim((string)($dto->compteurNumero ?? ''));
                    $numeroSnapshot = $numeroSaisi !== '' ? $numeroSaisi : trim((string)($dto->compteur->getNumeroSerie() ?? ''));
                    if ($numeroSnapshot === '' && $isExistingItem) {
                        $numeroSnapshot = trim((string)($item->getNumeroCompteur() ?? ''));
                    }
                    $item->setNumeroCompteur($numeroSnapshot !== '' ? $numeroSnapshot : null);

                    if ($numeroSaisi !== '' && $numeroSaisi !== trim((string)($dto->compteur->getNumeroSerie() ?? ''))) {
                        $dto->compteur->setNumeroSerie($numeroSaisi);
                        $em->persist($dto->compteur);
                    }

                    $item->setConsommation((string)$cons);
                    $item->setUpdatedAt($now);

                    $em->persist($item);

                    // Persiste la date d'installation choisie dans la fiche compteur.
                    if ($dto->dateInstallation instanceof \DateTimeInterface) {
                        $dto->compteur->setDateInstallation($dto->dateInstallation);
                        $em->persist($dto->compteur);
                    }

                    // ---- Photo (champ unmapped) : lire via le SOUS-FORMULAIRE ----
                    $photoFile = null;
                    if ($itemForms && $itemForms->offsetExists($idx) && $itemForms->get($idx)->has('photoFile')) {
                        $photoFile = $itemForms->get($idx)->get('photoFile')->getData();
                    }
                    $removePhoto = (bool)($dto->removePhoto ?? false);
                    if ($removePhoto && !($photoFile instanceof UploadedFile)) {
                        // Conserver l'historique: on dissocie la photo active sans supprimer les fichiers.
                        if (method_exists($dto->compteur, 'setPhoto')) {
                            $dto->compteur->setPhoto(null);
                            $em->persist($dto->compteur);
                        }
                    }
                    if ($photoFile instanceof UploadedFile) {
                        $webPath = $this->replaceCompteurPhoto(
                            $dto->compteur->getId(),
                            $photoFile,
                            $dto->compteurNumero ?? null,
                            $dto->compteur
                        );
                        if (method_exists($dto->compteur, 'setPhoto')) {
                            $dto->compteur->setPhoto($webPath);
                            $em->persist($dto->compteur);
                        }
                    }
                }

                $em->flush();
                $conn->commit();

                $this->addFlash('success', "Relevés $annee enregistrés avec succès pour le lot {$lot->getId()}.");
                return $this->redirectToRoute('saisie_index_form', ['lotId' => $lotId, 'annee' => $annee]);

            } catch (\Throwable $e) {
                $conn->rollBack();
                $this->addFlash('danger', "Erreur lors de l'enregistrement : " . $e->getMessage());
            }
        }
        // --------- /SAUVEGARDE ---------

        // Données pour la barre lot/année
        $allLots = $lotRepo->findAll();
        usort($allLots, static function ($a, $b): int {
            $numeroA = trim((string) $a->getNumeroLot());
            $numeroB = trim((string) $b->getNumeroLot());
            $cmp = strnatcasecmp($numeroA, $numeroB);

            if ($cmp !== 0) {
                return $cmp;
            }

            return $a->getId() <=> $b->getId();
        });
        $uniqueLots = [];
        foreach ($allLots as $candidateLot) {
            $numeroKey = mb_strtolower(trim((string) $candidateLot->getNumeroLot()));
            if (!isset($uniqueLots[$numeroKey])) {
                $uniqueLots[$numeroKey] = $candidateLot;
            }
        }
        $allLots = array_values($uniqueLots);
        $anneeBase = $anneeActive ?? (int)date('Y');
        $annees = range(max(2000, $anneeBase - 3), $anneeBase);
        $copro   = method_exists($lot, 'getCoproprietaire')
            ? $lot->getCoproprietaire(new \DateTimeImmutable('today'))
            : null;

        return $this->render('saisie_index/saisie.index.html.twig', [
            'form'       => $form->createView(),
            'lot'        => $lot,
            'copro'      => $copro,
            'annee'      => $annee,
            'items'      => $items,
            'totaux'     => $totaux,
            'allLots'    => $allLots,
            'annees'     => $annees,
            'isEditable' => $isEditable,
            'maxAnnee'   => $maxAnnee,
            'anneeActive'=> $anneeActive,
        ]);
    }

    /**
     * Remplace la photo du compteur {ID} dans /public/uploads/compteurs :
     * - conserve les anciennes versions (historique)
     * - enregistre la nouvelle sous compteur_{ID}_{timestamp}_{rand}.{ext}
     * - retourne le chemin web (ex: "/uploads/compteurs/compteur_12.jpg")
     */
    private function replaceCompteurPhoto(
        int $compteurId,
        UploadedFile $file,
        ?string $compteurNumero = null,
        ?Compteur $compteur = null
    ): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        [$uploadDir, $publicPrefix] = $this->resolveCompteurUploadTarget($projectDir, $compteur);

        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        if (!preg_match('/^(jpe?g|png|webp|gif|heic|heif)$/', $ext)) {
            $ext = 'jpg';
        }

        $numeroSlug = trim((string) $compteurNumero);
        $numeroSlug = $numeroSlug !== '' ? mb_strtolower($numeroSlug) : 'sans-numero';
        $numeroSlug = preg_replace('/[^a-z0-9]+/u', '-', $numeroSlug) ?? 'sans-numero';
        $numeroSlug = trim($numeroSlug, '-');
        if ($numeroSlug === '') {
            $numeroSlug = 'sans-numero';
        }

        $basename = sprintf(
            'compteur_%d_%s_%s_%s.%s',
            $compteurId,
            $numeroSlug,
            date('YmdHis'),
            substr(bin2hex(random_bytes(3)), 0, 6),
            $ext
        );
        $file->move($uploadDir, $basename);

        return $publicPrefix . '/' . $basename;
    }

    /**
     * @param array<int,Compteur> $compteurs
     * @return array<int,Compteur>
     */
    private function uniqueCompteursForSaisie(array $compteurs): array
    {
        $unique = [];
        foreach ($compteurs as $compteur) {
            $numeroSerie = mb_strtolower(trim((string) ($compteur->getNumeroSerie() ?? '')));
            // Déduplication stricte:
            // on ne fusionne que les vrais doublons (même type + même emplacement + même numéro).
            // Cela évite de masquer des compteurs distincts qui partagent un numéro proche.
            $type = mb_strtolower(trim((string) $compteur->getType()));
            $emplacement = mb_strtolower(trim((string) $compteur->getEmplacement()));
            $key = $numeroSerie !== ''
                ? $type . '|' . $emplacement . '|' . $numeroSerie
                : $type . '|' . $emplacement . '|#' . $compteur->getId();

            if (!isset($unique[$key])) {
                $unique[$key] = $compteur;
            }
        }

        return array_values($unique);
    }

    private function findLatestCompteurPhotoUrl(Compteur $compteur): ?string
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $compteurId = (int) $compteur->getId();
        $numeroSerie = trim((string) ($compteur->getNumeroSerie() ?? ''));

        $patterns = [
            $projectDir . '/public/uploads/compteurs/compteur_' . $compteurId . '*.*',
            $projectDir . '/public/uploads/compteurs/compteur-' . $compteurId . '*.*',
            $projectDir . '/public/uploads/coproprietaires/*/compteur_' . $compteurId . '_*.*',
        ];
        if ($numeroSerie !== '') {
            $patterns[] = $projectDir . '/public/uploads/compteurs/' . $numeroSerie . '*.*';
        }

        $files = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $f) {
                if (is_file($f)) {
                    $files[$f] = true;
                }
            }
        }

        if ($files === []) {
            return null;
        }

        $list = array_keys($files);
        usort($list, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        $latest = $list[0] ?? null;
        if ($latest === null) {
            return null;
        }

        $relative = str_replace($projectDir . '/public', '', $latest);
        return str_starts_with($relative, '/') ? $relative : null;
    }

    /**
     * @return array{0:string,1:string} [absolute_dir, public_url_prefix]
     */
    private function resolveCompteurUploadTarget(string $projectDir, ?Compteur $compteur): array
    {
        $folder = 'general';
        if ($compteur !== null && $compteur->getLot() !== null) {
            $lot = $compteur->getLot();
            $copro = $lot->getCoproprietaire(new \DateTimeImmutable('today'));
            if ($copro !== null) {
                $raw = sprintf('%d_%s', (int) $copro->getId(), $copro->getNomComplet());
            } else {
                $raw = 'lot_' . trim((string) $lot->getNumeroLot());
            }

            $folder = mb_strtolower($raw);
            $folder = preg_replace('/[^a-z0-9]+/u', '-', $folder) ?? 'general';
            $folder = trim($folder, '-');
            if ($folder === '') {
                $folder = 'general';
            }
        }

        $relative = '/uploads/coproprietaires/' . $folder;
        return [$projectDir . '/public' . $relative, $relative];
    }
}
