<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\SecurityAlert;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SecurityAlertService;
use App\Service\TrackerLogService;
use App\Service\TurnstileService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AdminAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'admin_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(service: 'limiter.admin_login')]
        private readonly RateLimiterFactory $adminLoginLimiter,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly TurnstileService $turnstileService,
        private readonly TrackerLogService $trackerLogService,
        private readonly SecurityAlertService $securityAlertService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('_email', ''));
        $limiter = $this->adminLoginLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            throw new CustomUserMessageAuthenticationException('Çok fazla başarısız giriş denemesi. 15 dakika sonra tekrar deneyin.');
        }

        $turnstileToken = trim((string) $request->request->get('cf-turnstile-response', ''));
        if ($this->turnstileService->isEnabled() && !$this->turnstileService->verify($turnstileToken, $request->getClientIp())) {
            $this->logger->warning('Admin login turnstile verification failed, blocking attempt.', [
                'ip' => $request->getClientIp(),
                'identifier' => $identifier,
            ]);

            throw new CustomUserMessageAuthenticationException('Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        return new Passport(
            new UserBadge($identifier, function (string $userIdentifier): User {
                $user = $this->userRepository->createQueryBuilder('u')
                    ->andWhere('LOWER(u.email) = :identifier OR LOWER(u.username) = :identifier')
                    ->setParameter('identifier', mb_strtolower($userIdentifier))
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Kullanıcı adı/e-posta veya parola hatalı.');
                }

                return $user;
            }),
            new PasswordCredentials((string) $request->request->get('_password', '')),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $loginUrl = $this->urlGenerator->generate(self::LOGIN_ROUTE);

        $user = $token->getUser();
        if ($user instanceof User) {
            $roles = $user->getRoles();
            if (!in_array('ROLE_ADMIN', $roles, true) && !in_array('ROLE_SUPER_ADMIN', $roles, true)) {
                $request->getSession()->set(
                    SecurityRequestAttributes::AUTHENTICATION_ERROR,
                    new CustomUserMessageAuthenticationException('Bu hesabın admin panel erişim yetkisi yok.')
                );

                return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
            }

            $user->setLastLoginAt(new \DateTime());
            $this->entityManager->flush();

            try {
                $this->trackerLogService->log('login', $request);
            } catch (\Throwable) {
                // Takip paneli hatası girişi etkilememeli.
            }
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            if ($targetPath === $loginUrl || str_ends_with($targetPath, '/login')) {
                return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
            }

            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        try {
            $this->trackerLogService->logAlert('başarısız admin girişi denemesi', $request);
        } catch (\Throwable) {
            // Alert loglaması giriş akışını etkilememeli.
        }

        try {
            $this->securityAlertService->record(
                SecurityAlert::TYPE_LOGIN_FAILED,
                SecurityAlert::SEVERITY_HIGH,
                $request,
                sprintf('%s (deneme: %s)', $exception->getMessage(), (string) $request->request->get('_email', ''))
            );
        } catch (\Throwable) {
            // Kayıt/mail hatası giriş akışını etkilememeli.
        }

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
