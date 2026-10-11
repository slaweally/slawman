<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * IndexNow, anahtarın site kökünde /{anahtar}.txt olarak servis edilmesini
 * doğrulama yöntemi olarak kullanır (Bing/Yandex bu dosyayı okuyup anahtarı
 * IndexNowService'ten gelen ping isteğiyle eşleştirir).
 */
class IndexNowController extends AbstractController
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
    }

    #[Route('/{key}.txt', name: 'front_indexnow_key', requirements: ['key' => '[a-f0-9]{32}'], priority: 10)]
    public function verify(string $key): Response
    {
        $configured = (string) ($this->settingsService->get('seo_indexnow_key', '') ?? '');
        if ($configured === '' || !hash_equals($configured, $key)) {
            throw $this->createNotFoundException();
        }

        return new Response($configured, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
