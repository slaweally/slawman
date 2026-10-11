<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ManifestController extends AbstractController
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
    }

    #[Route('/site.webmanifest', name: 'front_manifest')]
    public function index(): JsonResponse
    {
        $siteName = (string) ($this->settingsService->get('site_title', 'Slaweally') ?? 'Slaweally');
        $icon = trim((string) ($this->settingsService->get('site_favicon', '') ?? ''));
        $icons = [];
        if ($icon !== '') {
            $icons[] = ['src' => $icon, 'sizes' => 'any', 'type' => 'image/png'];
        }

        $response = new JsonResponse([
            'name' => $siteName,
            'short_name' => $siteName,
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => (string) ($this->settingsService->get('admin_theme_accent', '#2f5ea6') ?? '#2f5ea6'),
            'icons' => $icons,
        ]);
        $response->headers->set('Content-Type', 'application/manifest+json');

        return $response;
    }
}
