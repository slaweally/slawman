<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Admin oturumu: hareketsizlik ve mutlak süre sınırı.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final class AdminSessionIdleListener
{
    private const SESSION_LAST_ACTIVITY = '_admin_last_activity';
    private const SESSION_STARTED_AT = '_admin_session_started_at';

    /** Hareketsizlik sonrası çıkış (30 dk) */
    private const IDLE_TIMEOUT = 1800;

    /** Mutlak oturum üst sınırı (8 saat) */
    private const ABSOLUTE_TIMEOUT = 28800;

    public function __construct(
        #[Autowire('%admin_prefix%')]
        private readonly string $adminPrefix,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isAdminPath($request)) {
            return;
        }

        if ($this->isPublicAdminRoute($request)) {
            return;
        }

        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $now = time();
        $lastActivity = (int) $session->get(self::SESSION_LAST_ACTIVITY, 0);
        $startedAt = (int) $session->get(self::SESSION_STARTED_AT, 0);

        if ($startedAt === 0) {
            $session->set(self::SESSION_STARTED_AT, $now);
            $session->set(self::SESSION_LAST_ACTIVITY, $now);

            return;
        }

        $idleSeconds = $now - $lastActivity;
        $sessionAge = $now - $startedAt;

        if ($idleSeconds > self::IDLE_TIMEOUT || $sessionAge > self::ABSOLUTE_TIMEOUT) {
            $this->terminateSession($session);
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_login')));

            return;
        }

        $session->set(self::SESSION_LAST_ACTIVITY, $now);
    }

    private function isAdminPath(Request $request): bool
    {
        $prefix = '/' . ltrim($this->adminPrefix, '/');

        return str_starts_with($request->getPathInfo(), $prefix);
    }

    private function isPublicAdminRoute(Request $request): bool
    {
        $path = $request->getPathInfo();
        $prefix = '/' . ltrim($this->adminPrefix, '/');

        return $path === $prefix . '/login'
            || str_starts_with($path, $prefix . '/2fa');
    }

    private function terminateSession(\Symfony\Component\HttpFoundation\Session\SessionInterface $session): void
    {
        $this->tokenStorage->setToken(null);
        $session->invalidate();
    }
}
