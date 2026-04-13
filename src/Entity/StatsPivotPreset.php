<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StatsPivotPresetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatsPivotPresetRepository::class)]
#[ORM\Table(name: 'stats_pivot_preset')]
#[ORM\UniqueConstraint(name: 'uniq_stats_pivot_user_name', columns: ['user_id', 'name'])]
class StatsPivotPreset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\Column(type: 'datetime_immutable', name: 'saved_at')]
    private \DateTimeImmutable $savedAt;

    public function __construct()
    {
        $this->savedAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function getSavedAt(): \DateTimeImmutable
    {
        return $this->savedAt;
    }

    public function setSavedAt(\DateTimeImmutable $savedAt): self
    {
        $this->savedAt = $savedAt;
        return $this;
    }
}
