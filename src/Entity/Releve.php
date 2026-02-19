<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'releve_new')]
#[ORM\UniqueConstraint(name: 'UNIQ_RELEVE_NEW_ANNEE_LOT', columns: ['annee','lot_id'])]
class Releve
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lot::class)]
    #[ORM\JoinColumn(nullable: false, name: 'lot_id', referencedColumnName: 'id')]
    private ?Lot $lot = null;

    #[ORM\Column(type: 'integer')]
    private int $annee;

    #[ORM\Column(type: 'datetime', name: 'created_at')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', name: 'updated_at')]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $verrouille = false;

    #[ORM\OneToMany(mappedBy: 'releve', targetEntity: ReleveItem::class, cascade: ['persist','remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->items = new ArrayCollection();
    }

    // --- getters/setters minimal ---
    public function getId(): ?int { return $this->id; }
    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(Lot $lot): self { $this->lot = $lot; return $this; }

    public function getAnnee(): int { return $this->annee; }
    public function setAnnee(int $annee): self { $this->annee = $annee; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $d): self { $this->createdAt = $d; return $this; }

    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $d): self { $this->updatedAt = $d; return $this; }

    public function isVerrouille(): bool { return $this->verrouille; }
    public function setVerrouille(bool $v): self { $this->verrouille = $v; return $this; }

    /** @return Collection<int, ReleveItem> */
    public function getItems(): Collection { return $this->items; }
    public function addItem(ReleveItem $item): self {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setReleve($this);
        }
        return $this;
    }
    public function removeItem(ReleveItem $item): self {
        if ($this->items->removeElement($item)) {
            if ($item->getReleve() === $this) $item->setReleve($this);
        }
        return $this;
    }

    public function __toString(): string
    {
        if (!$this->lot) {
            return 'Lot — / ' . $this->annee;
        }

        $numeroLot = trim((string) $this->lot->getNumeroLot());
        if ($numeroLot === '') {
            $numeroLot = (string) $this->lot->getId();
        }

        return 'Lot ' . $numeroLot . ' / ' . $this->annee;
    }
}
