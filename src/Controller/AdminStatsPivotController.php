<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Stats\StatsPivotStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminStatsPivotController extends AbstractController
{
    private const ALLOWED_KEYS = [
        'rows',
        'cols',
        'vals',
        'rowOrder',
        'colOrder',
        'aggregatorName',
        'rendererName',
        'exclusions',
        'inclusions',
    ];

    #[Route('/admin/stats/pivots', name: 'admin_stats_pivots_list', methods: ['GET'])]
    public function list(StatsPivotStorage $storage): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['items' => []]);
        }
        $items = $storage->loadForUser($user);
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/admin/stats/pivots', name: 'admin_stats_pivots_save', methods: ['POST'])]
    public function save(Request $request, StatsPivotStorage $storage): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $name = (string)($payload['name'] ?? '');
        $config = $payload['config'] ?? null;
        if (!is_array($config)) {
            return new JsonResponse(['error' => 'invalid_config'], Response::HTTP_BAD_REQUEST);
        }

        $sanitized = $this->sanitizeConfig($config);
        try {
            $items = $storage->saveForUser($user, $name, $sanitized);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'invalid_name'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/admin/stats/pivots/{name}', name: 'admin_stats_pivots_delete', methods: ['DELETE'])]
    public function delete(string $name, StatsPivotStorage $storage): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $items = $storage->deleteForUser($user, $name);
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/admin/stats/pivots/export', name: 'admin_stats_pivots_export', methods: ['GET'])]
    public function export(StatsPivotStorage $storage): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new Response('unauthorized', Response::HTTP_FORBIDDEN);
        }

        $items = $storage->loadForUser($user);
        $payload = [
            'exported_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
            'user_id' => $user->getId(),
            'items' => $items,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = new Response($json ?: '{}');
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'stats_pivots.json'));
        return $response;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function sanitizeConfig(array $config): array
    {
        $clean = [];
        foreach (self::ALLOWED_KEYS as $k) {
            if (array_key_exists($k, $config)) {
                $clean[$k] = $config[$k];
            }
        }
        return $clean;
    }
}
