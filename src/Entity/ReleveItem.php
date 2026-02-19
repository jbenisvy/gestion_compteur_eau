<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'releve_item')]
#[ORM\UniqueConstraint(name: 'UNIQ_RELEVE_ITEM_MAIN', columns: ['releve_id','compteur_id'])]
class ReleveItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Releve::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, name: 'releve_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Releve $releve = null;

    #[ORM\ManyToOne(targetEntity: Compteur::class)]
    #[ORM\JoinColumn(nullable: false, name: 'compteur_id', referencedColumnName: 'id')]
    private ?Compteur $compteur = null;

    #[ORM\Column(type:'integer', nullable:true, name:'index_n1')]
    private ?int $indexN1 = null;

    #[ORM\Column(type:'integer', nullable:true, name:'index_n')]
    private ?int $indexN = null;

    #[ORM\Column(type:'integer', nullable:true, name:'index_compteur_demonte')]
    private ?int $indexCompteurDemonté = null;

    #[ORM\Column(type:'integer', nullable:true, name:'index_nouveau_compteur')]
    private ?int $indexNouveauCompteur = null;

    #[ORM\Column(type:'integer', nullable:true, name:'etat_id')]
    private ?int $etatId = null;

    #[ORM\Column(type:'boolean', options:['default'=>false])]
    private bool $forfait = false;

    #[ORM\Column(type:'text', nullable:true)]
    private ?string $commentaire = null;

    #[ORM\Column(type:'string', length:255, nullable:true, name:'numero_compteur')]
    private ?string $numeroCompteur = null;

    #[ORM\Column(type:'decimal', precision:10, scale:3, nullable:true)]
    private ?string $consommation = null;

    #[ORM\Column(type:'datetime', name:'created_at')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type:'datetime', name:'updated_at')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    // --- getters/setters minimal ---
    public function getId(): ?int { return $this->id; }

    public function getReleve(): ?Releve { return $this->releve; }
    public function setReleve(Releve $r): self { $this->releve = $r; return $this; }

    public function getCompteur(): ?Compteur { return $this->compteur; }
    public function setCompteur(Compteur $c): self { $this->compteur = $c; return $this; }

    public function getIndexN1(): ?int { return $this->indexN1; }
    public function setIndexN1(?int $v): self { $this->indexN1 = $v; return $this; }

    public function getIndexN(): ?int { return $this->indexN; }
    public function setIndexN(?int $v): self { $this->indexN = $v; return $this; }

    public function getIndexCompteurDemonté(): ?int { return $this->indexCompteurDemonté; }
    public function setIndexCompteurDemonté(?int $v): self { $this->indexCompteurDemonté = $v; return $this; }

    public function getIndexNouveauCompteur(): ?int { return $this->indexNouveauCompteur; }
    public function setIndexNouveauCompteur(?int $v): self { $this->indexNouveauCompteur = $v; return $this; }

    public function getEtatId(): ?int { return $this->etatId; }
    public function setEtatId(?int $v): self { $this->etatId = $v; return $this; }

    public function isForfait(): bool { return $this->forfait; }
    public function setForfait(bool $v): self { $this->forfait = $v; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $v): self { $this->commentaire = $v; return $this; }

    public function getNumeroCompteur(): ?string { return $this->numeroCompteur; }
    public function setNumeroCompteur(?string $v): self
    {
        $v = $v !== null ? trim($v) : null;
        $this->numeroCompteur = $v === '' ? null : $v;
        return $this;
    }

    public function getConsommation(): ?string { return $this->consommation; }
    public function setConsommation(?string $v): self { $this->consommation = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $d): self { $this->createdAt = $d; return $this; }

    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $d): self { $this->updatedAt = $d; return $this; }
}
