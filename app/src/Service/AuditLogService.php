<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function log(
        ?User $user,
        string $action,
        string $entityType,
        int $entityId,
        array $oldValue = [],
        array $newValue = [],
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $log = new AuditLog();
        $log->setUser($user);
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setOldValue($oldValue !== [] ? $oldValue : null);
        $log->setNewValue($newValue !== [] ? $newValue : null);
        $log->setIpAddress($request?->getClientIp());
        $log->setUserAgent($request?->headers->get('User-Agent'));

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
