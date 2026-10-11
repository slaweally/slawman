<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\CommentRepository;
use App\Repository\ContactMessageRepository;
use App\Repository\SecurityAlertRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminExtension extends AbstractExtension
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly ContactMessageRepository $contactMessageRepository,
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_comments_count', [$this, 'getPendingCommentsCount']),
            new TwigFunction('unread_messages_count', [$this, 'getUnreadMessagesCount']),
            new TwigFunction('security_alerts_24h_count', [$this, 'getSecurityAlerts24hCount']),
            new TwigFunction('consume_maintenance_report', [$this, 'consumeMaintenanceReport']),
        ];
    }

    public function getSecurityAlerts24hCount(): int
    {
        return $this->securityAlertRepository->countSince(new \DateTimeImmutable('-24 hours'));
    }

    public function getPendingCommentsCount(): int
    {
        return $this->commentRepository->countPending();
    }

    public function getUnreadMessagesCount(): int
    {
        return $this->contactMessageRepository->countUnread();
    }

    /** @return array<string, mixed>|null */
    public function consumeMaintenanceReport(): ?array
    {
        if ($this->requestStack === null) {
            return null;
        }

        $session = $this->requestStack->getSession();
        if (!$session instanceof Session) {
            return null;
        }

        $report = $session->get('maintenance_report');
        if (!is_array($report)) {
            return null;
        }

        $session->remove('maintenance_report');

        return $report;
    }
}
