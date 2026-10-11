<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\EventListener\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersListenerTest extends TestCase
{
    public function testAddsSecurityHeaders(): void
    {
        $listener = new SecurityHeadersListener('prod', 'backstage');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, Request::create('https://example.com/'), HttpKernelInterface::MAIN_REQUEST, new Response());

        $listener($event);
        $headers = $event->getResponse()->headers;

        self::assertTrue($headers->has('Content-Security-Policy'));
        self::assertTrue($headers->has('Strict-Transport-Security'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $csp = (string) $headers->get('Content-Security-Policy');
        self::assertMatchesRegularExpression("/script-src 'self' https:\/\/challenges\.cloudflare\.com;/", $csp);
        self::assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function testAdminAllowsInlineScriptsForImportUi(): void
    {
        $listener = new SecurityHeadersListener('prod', 'backstage');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent(
            $kernel,
            Request::create('https://example.com/backstage/import/wordpress'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $listener($event);
        $csp = (string) $event->getResponse()->headers->get('Content-Security-Policy');

        self::assertStringContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }
}
