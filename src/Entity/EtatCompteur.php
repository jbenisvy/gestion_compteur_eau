<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "etat_compteur")]
class EtatCompteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    // Exemple : actif | supprime | forfait | remplace
    #[ORM\Column(type: "string", length: 50)]
    private string $code = 'actif';

    // Exemple : En fonctionnement | Supprimé | Forfait | Remplacé
    #[ORM\Column(type: "string", length: 255)]
    private string $libelle = 'En fonctionnement';

    public function getId(): ?int { return $this->id; }

    public function getCode(): string { return $this->code; }
    public function setCode(?string $code): self
    {
        $this->code = $code ? strtolower(trim($code)) : 'actif';
        return $this;
    }

    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle ?: 'En fonctionnement';
        return $this;
    }
}
