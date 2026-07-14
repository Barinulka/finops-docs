<?php

namespace App\Controller\Telegram;

use App\Service\Telegram\TelegramBotConfig;
use App\Service\Telegram\TelegramUpdateHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TelegramWebhookController extends AbstractController
{
    #[Route('/telegram/webhook/{secret}', name: 'telegram_webhook', methods: ['POST'])]
    public function __invoke(
        string $secret,
        Request $request,
        TelegramBotConfig $config,
        TelegramUpdateHandler $telegramUpdateHandler,
    ): JsonResponse {
        if ($secret !== $config->webhookSecret) {
            return new JsonResponse(['error' => 'Invalid webhook secret.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $telegramUpdateHandler->handle($data);

        return new JsonResponse(['ok' => true], Response::HTTP_OK);
    }
}
