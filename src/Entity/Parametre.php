<?php

namespace App\Entity;

use App\Repository\ParametreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParametreRepository::class)]
#[ORM\Table(name: "parametre")]
class Parametre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // NULL = valeurs par défaut pour toutes les années
    #[ORM\Column(nullable: true)]
    private ?int $annee = null;

    #[ORM\Column(type: 'float')]
    private float $forfaitEf = 150.0;

    #[ORM\Column(type: 'float')]
    private float $forfaitEc = 75.0;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $prixM3Ef = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $prixM3Ec = null;

    #[ORM\Column(nullable: true)]
    private ?int $activeSaisieYear = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $coproSaisieBloquee = false;

    public function getId(): ?int { return $this->id; }
    public function getAnnee(): ?int { return $this->annee; }
    public function setAnnee(?int $annee): self { $this->annee = $annee; return $this; }
    public function getForfaitEf(): float { return $this->forfaitEf; }
    public function setForfaitEf(float $v): self { $this->forfaitEf = $v; return $this; }
    public function getForfaitEc(): float { return $this->forfaitEc; }
    public function setForfaitEc(float $v): self { $this->forfaitEc = $v; return $this; }
    public function getPrixM3Ef(): ?float { return $this->prixM3Ef; }
    public function setPrixM3Ef(?float $v): self { $this->prixM3Ef = $v; return $this; }
    public function getPrixM3Energie(): ?float { return $this->prixM3Ec; }
    public function setPrixM3Energie(?float $v): self { $this->prixM3Ec = $v; return $this; }
    public function getPrixM3Ec(): ?float { return $this->prixM3Ec; }
    public function setPrixM3Ec(?float $v): self { $this->prixM3Ec = $v; return $this; }
    public function getPrixM3Total(): ?float
    {
        if ($this->prixM3Ef === null && $this->prixM3Ec === null) {
            return null;
        }

        return (float) (($this->prixM3Ef ?? 0.0) + ($this->prixM3Ec ?? 0.0));
    }
    public function getPrixM3EauChaude(): ?float { return $this->getPrixM3Total(); }
    public function getActiveSaisieYear(): ?int { return $this->activeSaisieYear; }
    public function setActiveSaisieYear(?int $v): self { $this->activeSaisieYear = $v; return $this; }
    public function isCoproSaisieBloquee(): bool { return $this->coproSaisieBloquee; }
    public function setCoproSaisieBloquee(bool $v): self { $this->coproSaisieBloquee = $v; return $this; }
}
