<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "lot")]
class Lot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 50)]
    private string $numeroLot = 'Inconnu';

    #[ORM\Column(type: "string", length: 255)]
    private string $emplacement = 'Non spécifié';

    #[ORM\Column(type: "string", length: 255)]
    private string $typeAppartement = 'Non précisé';

    #[ORM\Column(type: "integer")]
    private int $tantieme = 0;

    #[ORM\Column(type: "string", length: 255)]
    private string $occupant = 'Non précisé';

    #[ORM\ManyToOne(targetEntity: Coproprietaire::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Coproprietaire $coproprietaire = null; // legacy (remplacé par lot_coproprietaire)

    #[ORM\OneToMany(mappedBy: 'lot', targetEntity: LotCoproprietaire::class, cascade: ['persist','remove'], orphanRemoval: true)]
    private Collection $coproprietaires;

    public function __construct()
    {
        $this->coproprietaires = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNumeroLot(): string { return $this->numeroLot; }
    public function setNumeroLot(?string $numeroLot): self { $this->numeroLot = $numeroLot ?: 'Inconnu'; return $this; }

    public function getEmplacement(): string { return $this->emplacement; }
    public function setEmplacement(?string $emplacement): self { $this->emplacement = $emplacement ?: 'Non spécifié'; return $this; }

    public function getTypeAppartement(): string { return $this->typeAppartement; }
    public function setTypeAppartement(?string $typeAppartement): self { $this->typeAppartement = $typeAppartement ?: 'Non précisé'; return $this; }

    public function getTantieme(): int { return $this->tantieme; }
    public function setTantieme(?int $tantieme): self { $this->tantieme = $tantieme ?? 0; return $this; }

    public function getOccupant(): string { return $this->occupant; }
    public function setOccupant(?string $occupant): self { $this->occupant = $occupant ?: 'Non précisé'; return $this; }

    /**
     * Retourne le copropriétaire actif à une date donnée (par défaut: aujourd'hui).
     * Fallback sur la relation legacy si aucun historique n'existe.
     */
    public function getCoproprietaire(?\DateTimeInterface $date = null): ?Coproprietaire
    {
        $date ??= new \DateTimeImmutable('today');

        foreach ($this->coproprietaires as $link) {
            if ($link->isActiveAt($date)) {
                return $link->getCoproprietaire();
            }
        }

        return $this->coproprietaire;
    }

    public function setCoproprietaire(?Coproprietaire $coproprietaire): self
    {
        $this->coproprietaire = $coproprietaire;
        return $this;
    }

    /** @return Collection<int, LotCoproprietaire> */
    public function getCoproprietaires(): Collection
    {
        return $this->coproprietaires;
    }

    public function addCoproprietaireLink(LotCoproprietaire $link): self
    {
        if (!$this->coproprietaires->contains($link)) {
            $this->coproprietaires->add($link);
            $link->setLot($this);
        }
        return $this;
    }

    public function removeCoproprietaireLink(LotCoproprietaire $link): self
    {
        if ($this->coproprietaires->removeElement($link)) {
            if ($link->getLot() === $this) {
                $link->setLot($this);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->numeroLot . ' — ' . $this->emplacement;
    }
}
