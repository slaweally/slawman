<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Subscriber;
use App\Repository\SubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubscriberService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriberRepository $subscriberRepository,
    ) {
    }

    public function subscribeFromComment(string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $existing = $this->subscriberRepository->findOneBy(['email' => $email]);
        if ($existing !== null) {
            if (!$existing->isActive()) {
                $existing->setIsActive(true);
                $existing->setUnsubscribedAt(null);
                $existing->setSubscribedAt(new \DateTime());
                $this->entityManager->flush();
            }

            return;
        }

        $subscriber = new Subscriber();
        $subscriber->setEmail($email);
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();
    }

    /** @return array{added: int, skipped: int} */
    public function syncFromCommentEmails(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('LOWER(TRIM(c.authorEmail)) AS email')
            ->from(Comment::class, 'c')
            ->where('c.authorEmail IS NOT NULL')
            ->andWhere('c.authorEmail != :empty')
            ->setParameter('empty', '')
            ->groupBy('email')
            ->getQuery()
            ->getArrayResult();

        $added = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $email = (string) ($row['email'] ?? '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ++$skipped;
                continue;
            }

            $existing = $this->subscriberRepository->findOneBy(['email' => $email]);
            if ($existing !== null) {
                ++$skipped;
                continue;
            }

            $subscriber = new Subscriber();
            $subscriber->setEmail($email);
            $this->entityManager->persist($subscriber);
            ++$added;
        }

        $this->entityManager->flush();

        return ['added' => $added, 'skipped' => $skipped];
    }

    /** @return array{added: int, skipped: int, invalid: int} */
    public function importFromCsv(string $raw): array
    {
        $added = 0;
        $skipped = 0;
        $invalid = 0;
        $seen = [];

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $isFirst = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $email = $line;
            if (str_contains($line, ',')) {
                $parts = str_getcsv($line);
                $email = trim((string) ($parts[0] ?? ''));
                if ($isFirst && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $header = strtolower($email);
                    if (in_array($header, ['email', 'e-mail', 'mail', 'eposta', 'e-posta'], true)) {
                        $isFirst = false;
                        continue;
                    }
                }
            }

            $isFirst = false;
            $email = strtolower(trim($email, " \t\"'"));
            if ($email === '' || isset($seen[$email])) {
                if ($email !== '' && isset($seen[$email])) {
                    ++$skipped;
                }
                continue;
            }
            $seen[$email] = true;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ++$invalid;
                continue;
            }

            $existing = $this->subscriberRepository->findOneBy(['email' => $email]);
            if ($existing !== null) {
                ++$skipped;
                continue;
            }

            $subscriber = new Subscriber();
            $subscriber->setEmail($email);
            $this->entityManager->persist($subscriber);
            ++$added;
        }

        $this->entityManager->flush();

        return ['added' => $added, 'skipped' => $skipped, 'invalid' => $invalid];
    }
}
