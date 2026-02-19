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

    #[ORM\Column(nullable: true)]
    private ?int $activeSaisieYear = null;

    public function getId(): ?int { return $this->id; }
    public function getAnnee(): ?int { return $this->annee; }
    public function setAnnee(?int $annee): self { $this->annee = $annee; return $this; }
    public function getForfaitEf(): float { return $this->forfaitEf; }
    public function setForfaitEf(float $v): self { $this->forfaitEf = $v; return $this; }
    public function getForfaitEc(): float { return $this->forfaitEc; }
    public function setForfaitEc(float $v): self { $this->forfaitEc = $v; return $this; }
    public function getActiveSaisieYear(): ?int { return $this->activeSaisieYear; }
    public function setActiveSaisieYear(?int $v): self { $this->activeSaisieYear = $v; return $this; }
}
