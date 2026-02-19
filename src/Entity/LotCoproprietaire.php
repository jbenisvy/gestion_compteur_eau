<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "lot_coproprietaire")]
class LotCoproprietaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lot::class, inversedBy: 'coproprietaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lot $lot = null;

    #[ORM\ManyToOne(targetEntity: Coproprietaire::class, inversedBy: 'lots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Coproprietaire $coproprietaire = null;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: "boolean")]
    private bool $isPrincipal = true;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->dateDebut = new \DateTimeImmutable('today');
    }

    public function getId(): ?int { return $this->id; }

    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(?Lot $lot): self { $this->lot = $lot; return $this; }

    public function getCoproprietaire(): ?Coproprietaire { return $this->coproprietaire; }
    public function setCoproprietaire(?Coproprietaire $coproprietaire): self { $this->coproprietaire = $coproprietaire; return $this; }

    public function getDateDebut(): \DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $dateDebut): self { $this->dateDebut = $dateDebut; return $this; }

    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $dateFin): self { $this->dateFin = $dateFin; return $this; }

    public function isPrincipal(): bool { return $this->isPrincipal; }
    public function setIsPrincipal(bool $isPrincipal): self { $this->isPrincipal = $isPrincipal; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): self { $this->commentaire = $commentaire; return $this; }

    public function isActiveAt(\DateTimeInterface $date): bool
    {
        $start = $this->dateDebut->format('Y-m-d');
        $target = $date->format('Y-m-d');
        $end = $this->dateFin?->format('Y-m-d');

        if ($target < $start) {
            return false;
        }
        if ($end !== null && $target > $end) {
            return false;
        }
        return true;
    }
}
