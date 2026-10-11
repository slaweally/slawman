<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Service\SettingsService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RobotsController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'admin_prefix')]
        private readonly string $adminPrefix,
    ) {
    }

    #[Route('/robots.txt', name: 'front_robots')]
    public function index(SettingsService $settingsService): Response
    {
        $siteUrl = rtrim((string) $settingsService->get('site_url', ''), '/');
        $custom = trim((string) $settingsService->get('seo_robots_custom', ''));

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /' . trim($this->adminPrefix, '/') . '/',
            'Disallow: /login',
            'Disallow: /logout',
        ];
        if ($siteUrl !== '') {
            $lines[] = 'Sitemap: ' . $siteUrl . '/sitemap.xml';
        }
        if ($custom !== '') {
            $lines[] = '';
            $lines[] = '# Custom rules';
            $lines[] = $custom;
        }

        return new Response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
