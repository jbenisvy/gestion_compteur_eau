<?php

namespace App\Dto;

use App\Entity\Compteur;
use App\Entity\EtatCompteur;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * 🛡️ SAFE-GUARD
 * DTO utilisé par le formulaire de saisie d’un compteur (SaisieIndexItemType).
 * - Ne pas supprimer/renommer des propriétés existantes sans vérifier le contrôleur et le FormType.
 * - Les nouvelles propriétés ajoutées ci-dessous (compteurNumero, compteurId, photoFile, dateInstallation)
 *   sont NON persistées par défaut : elles servent à l’UI et au contrôleur.
 */
class SaisieIndexItem
{
    /** Compteur concerné */
    public ?Compteur $compteur = null;

    /** État sélectionné (En Fonctionnement, Bloqué, Remplacé, Supprimé, Inoccupé) */
    public ?EtatCompteur $etat = null;

    /** Pièce détectée/choisie (ex: 'cuisine', 'sdb', etc.) */
    public ?string $piece = null;

    /** Type d’eau (ex: 'chaude' | 'froide') */
    public ?string $typeEau = null;

    /** Index N-1 (prérempli depuis la base si dispo) */
    public ?int $indexPrevious = null;

    /** Index N (valeur saisie) */
    public ?int $indexN = null;

    /** Index compteur démonté (cas remplacement) */
    public ?int $indexDemonte = null;

    /** Index du nouveau compteur (cas remplacement) */
    public ?int $indexNouveau = null;

    /** Commentaire libre */
    public ?string $commentaire = null;

    /** Consommation calculée (facultatif : pour affichage/synthèse) */
    public ?int $consommationCalculee = null;

    /** Indique que le compteur/relevé est supprimé: les index historiques restent en base mais sont masqués à l'écran. */
    public bool $isSupprime = false;

    /**
     * Forfait éventuel (si ton flux l’utilise ; laisse null sinon).
     * 🛡️ SAFE-GUARD: ne pas supprimer si déjà référencé dans le contrôleur ou le template.
     */
    public ?int $forfait = null;

    // ─────────────────────────────────────────────────────────────
    // 🔵 AJOUTS pour le numéro cliquable + upload photo
    // ─────────────────────────────────────────────────────────────

    /**
     * Numéro lisible du compteur (ex: "CUI-EC-01").
     * ⚠️ Non persisté : rempli par le contrôleur depuis $compteur->getNumeroSerie() ou fallback #ID.
     */
    public ?string $compteurNumero = null;

    /**
     * ID technique du compteur (pour lier l’ancre au bon input file).
     * ⚠️ Non persisté : rempli par le contrôleur depuis $compteur->getId().
     */
    public ?int $compteurId = null;

    /**
     * Fichier image transmis par le FormType (non mappé / non persisté).
     * Géré par le contrôleur (remplacement atomique dans /public/uploads/compteurs).
     */
    public ?UploadedFile $photoFile = null;

    /**
     * Demande de suppression explicite de la photo actuelle.
     * ⚠️ Non persisté : traité par le contrôleur au submit.
     */
    public bool $removePhoto = false;

    /**
     * URL (ou chemin web) de la photo existante du compteur (si déjà enregistrée).
     * ⚠️ Non persisté : sert uniquement à l’affichage de la vignette dans le template.
     * Exemple: "/uploads/compteurs/compteur-12-a1b2c3d4.jpg"
     */
    public ?string $photoUrl = null;

    // ─────────────────────────────────────────────────────────────
    // 🔵 AJOUTS liés au remplacement de compteur (template)
    // ─────────────────────────────────────────────────────────────

    /**
     * Date d'installation du nouveau compteur (widget DateType single_text).
     * ⚠️ Non persisté par défaut : le contrôleur peut la propager vers l'entité si un setter existe.
     */
    public ?\DateTimeInterface $dateInstallation = null;

    /**
     * Cas "Nouveau compteur" :
     * - false => conso = index nouveau compteur
     * - true  => conso = (index ancien démonte - index N-1) + index nouveau compteur
     */
    public bool $ancienFonctionnaitEncore = false;
}
