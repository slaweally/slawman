<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\SubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class UnsubscribeController extends AbstractController
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/abonelik-iptal/{token}', name: 'front_unsubscribe')]
    public function unsubscribe(string $token): Response
    {
        if (!Uuid::isValid($token)) {
            return $this->render('front/unsubscribe/result.html.twig', [
                'success' => false,
                'message' => 'Geçersiz abonelik bağlantısı.',
            ]);
        }

        $subscriber = $this->subscriberRepository->findOneByToken($token);
        if ($subscriber === null) {
            return $this->render('front/unsubscribe/result.html.twig', [
                'success' => false,
                'message' => 'Abonelik kaydı bulunamadı.',
            ]);
        }

        if (!$subscriber->isActive()) {
            return $this->render('front/unsubscribe/result.html.twig', [
                'success' => true,
                'message' => 'Bu e-posta adresi zaten abonelikten çıkmış durumda.',
            ]);
        }

        $subscriber->setIsActive(false);
        $subscriber->setUnsubscribedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->render('front/unsubscribe/result.html.twig', [
            'success' => true,
            'message' => 'Abonelikten başarıyla çıktınız. Artık bülten e-postası almayacaksınız.',
        ]);
    }
}
