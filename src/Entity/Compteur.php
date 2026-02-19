<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "compteur")]
#[ORM\UniqueConstraint(name: "UNIQ_COMPTEUR_LOT_NUMERO_SERIE", columns: ["lot_id", "numero_serie"])]
class Compteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    // Type du compteur : EC / EF
    #[ORM\Column(type: "string", length: 2)]
    private string $type = 'EC';

    // Emplacement : Cuisine / Salle de bain...
    #[ORM\Column(type: "string", length: 255)]
    private string $emplacement = 'Non spécifié';

    #[ORM\Column(name: "date_installation", type: "date")]
    private \DateTimeInterface $dateInstallation;

    #[ORM\Column(name: "numero_serie", type: "string", length: 255, nullable: true)]
    private ?string $numeroSerie = null;

    #[ORM\Column(type: "boolean")]
    private bool $actif = true;

    #[ORM\ManyToOne(targetEntity: EtatCompteur::class)]
    #[ORM\JoinColumn(name: "etat_compteur_id", referencedColumnName: "id", nullable: false)]
    private ?EtatCompteur $etatCompteur = null;

    #[ORM\ManyToOne(targetEntity: Lot::class)]
    #[ORM\JoinColumn(name: "lot_id", referencedColumnName: "id", nullable: false)]
    private ?Lot $lot = null;

    #[ORM\Column(name: "photo", type: "string", length: 255, nullable: true)]
    private ?string $photo = null;

    public function __construct()
    {
        $this->dateInstallation = new \DateTimeImmutable();
    }

    // --- Getters & Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $t = strtoupper(substr($type, 0, 2));
        $this->type = in_array($t, ['EC', 'EF'], true) ? $t : 'EC';
        return $this;
    }

    public function getEmplacement(): string
    {
        return $this->emplacement;
    }

    public function setEmplacement(string $emplacement): self
    {
        $this->emplacement = $emplacement ?: 'Non spécifié';
        return $this;
    }

    public function getDateInstallation(): \DateTimeInterface
    {
        return $this->dateInstallation;
    }

    public function setDateInstallation(?\DateTimeInterface $d): self
    {
        $this->dateInstallation = $d ?? new \DateTimeImmutable();
        return $this;
    }

    public function getNumeroSerie(): ?string
    {
        return $this->numeroSerie;
    }

    public function setNumeroSerie(?string $numeroSerie): self
    {
        $numeroSerie = $numeroSerie !== null ? trim($numeroSerie) : null;
        $this->numeroSerie = $numeroSerie === '' ? null : $numeroSerie;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        return $this;
    }

    public function getEtatCompteur(): ?EtatCompteur
    {
        return $this->etatCompteur;
    }

    public function setEtatCompteur(?EtatCompteur $etatCompteur): self
    {
        $this->etatCompteur = $etatCompteur;
        return $this;
    }

    public function getLot(): ?Lot
    {
        return $this->lot;
    }

    public function setLot(?Lot $lot): self
    {
        $this->lot = $lot;
        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;
        return $this;
    }

    public function __toString(): string
    {
        $num = $this->numeroSerie ?: ('#' . $this->id);
        return $num . ' — ' . $this->emplacement . ' (' . $this->type . ')';
    }
}
