<?php

declare(strict_types=1);

namespace App\Service\Stats;

use App\Entity\StatsPivotPreset;
use App\Entity\User;
use App\Repository\StatsPivotPresetRepository;
use Doctrine\ORM\EntityManagerInterface;

final class StatsPivotStorage
{
    private EntityManagerInterface $em;
    private StatsPivotPresetRepository $repo;

    public function __construct(EntityManagerInterface $em, StatsPivotPresetRepository $repo)
    {
        $this->em = $em;
        $this->repo = $repo;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadForUser(User $user): array
    {
        $items = [];
        foreach ($this->repo->findByUser($user) as $preset) {
            $items[$preset->getName()] = [
                'config' => $preset->getConfig(),
                'savedAt' => $preset->getSavedAt()->format(DATE_ATOM),
            ];
        }
        return $items;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveForUser(User $user, string $name, array $config): array
    {
        $name = trim($name);
        if ($name == '') {
            throw new \InvalidArgumentException('Nom invalide.');
        }

        $preset = $this->repo->findOneByUserAndName($user, $name);
        if (!$preset) {
            $preset = new StatsPivotPreset();
            $preset->setUser($user)->setName($name);
        }
        $preset->setConfig($config);
        $preset->setSavedAt(new \DateTimeImmutable('now'));

        $this->em->persist($preset);
        $this->em->flush();

        return $this->loadForUser($user);
    }

    public function deleteForUser(User $user, string $name): array
    {
        $preset = $this->repo->findOneByUserAndName($user, $name);
        if ($preset) {
            $this->em->remove($preset);
            $this->em->flush();
        }
        return $this->loadForUser($user);
    }
}
