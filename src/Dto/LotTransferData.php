<?php

namespace App\Dto;

use App\Entity\Coproprietaire;
use App\Entity\Lot;
use Symfony\Component\Validator\Constraints as Assert;

class LotTransferData
{
    #[Assert\NotNull(message: 'Veuillez sélectionner un lot.')]
    public ?Lot $lot = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner le nouveau copropriétaire.')]
    public ?Coproprietaire $newCoproprietaire = null;

    #[Assert\NotNull(message: 'Veuillez indiquer la date d’effet.')]
    #[Assert\Type(type: \DateTimeInterface::class)]
    public ?\DateTimeInterface $effectiveDate = null;

    #[Assert\Length(max: 255, maxMessage: 'Le commentaire ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $commentaire = null;
}
