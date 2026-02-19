<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "coproprietaire")]
class Coproprietaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $nom = 'Nom inconnu';

    #[ORM\Column(type: "string", length: 255)]
    private string $prenom = 'Prénom inconnu';

    #[ORM\Column(type: "string", length: 255)]
    private string $email = 'email@inconnu.com';

    #[ORM\Column(type: "string", length: 255)]
    private string $telephone = 'Non communiqué';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'coproprietaire', targetEntity: LotCoproprietaire::class)]
    private Collection $lots;

    public function __construct()
    {
        $this->lots = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(?string $nom): self { $this->nom = $nom ?: 'Nom inconnu'; return $this; }

    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(?string $prenom): self { $this->prenom = $prenom ?: 'Prénom inconnu'; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email ?: 'email@inconnu.com'; return $this; }

    public function getTelephone(): string { return $this->telephone; }
    public function setTelephone(?string $telephone): self { $this->telephone = $telephone ?: 'Non communiqué'; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    /** @return Collection<int, LotCoproprietaire> */
    public function getLots(): Collection { return $this->lots; }

    public function getNomComplet(): string
    {
        $nom = trim($this->nom ?? '');
        $prenom = trim($this->prenom ?? '');
        $full = trim($prenom . ' ' . $nom);

        return $full !== '' ? $full : 'Nom inconnu';
    }
}
