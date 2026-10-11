<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TrackerIdentity;
use App\Repository\TrackerIdentityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Aynı IP her zaman aynı takma adla eşleşsin diye HMAC-hash'lenmiş IP -> isim
 * eşleştirmesini yönetir. Ham IP burada asla saklanmaz.
 */
class UserTrackerService
{
    private const NAME_POOL = [
        'Ali', 'Ayşe', 'Mehmet', 'Zeynep', 'Mustafa', 'Elif', 'Ahmet', 'Fatma',
        'Emre', 'Buse', 'Kerem', 'Deniz', 'Burak', 'Selin', 'Can', 'Ece',
        'Onur', 'Gizem', 'Barış', 'Nazlı', 'Kirve', 'Reis', 'Abi', 'Hacker',
    ];

    public function __construct(
        private readonly TrackerIdentityRepository $identityRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {
    }

    /**
     * Belirtilen IP için sabit takma adı döndürür. Entity persist edilir
     * ancak flush edilmez — çağıran taraf (ör. TrackerLogService) kendi
     * flush'ıyla birlikte tek sorguda yazsın diye.
     */
    public function resolveDisplayName(string $ip): string
    {
        $hash = $this->hashIp($ip);
        $identity = $this->identityRepository->findByIpHash($hash);

        if ($identity === null) {
            $identity = new TrackerIdentity();
            $identity->setIpHash($hash);
            $identity->setDisplayName($this->pickUniqueName());
            $this->entityManager->persist($identity);
        } else {
            $identity->touch();
        }

        return $identity->getDisplayName();
    }

    public function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, $this->appSecret);
    }

    private function pickUniqueName(): string
    {
        $available = array_values(array_diff(self::NAME_POOL, $this->identityRepository->findUsedNames()));
        if ($available !== []) {
            return $available[array_rand($available)];
        }

        return self::NAME_POOL[array_rand(self::NAME_POOL)] . '-' . random_int(10, 99);
    }
}
