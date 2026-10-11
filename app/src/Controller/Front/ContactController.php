<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\ContactMessage;
use App\Form\ContactFormType;
use App\Dto\Input\ContactInput;
use App\Input\InputSanitizer;
use App\Input\InputValidator;
use App\Service\AdminNotificationService;
use App\Service\SeoMetaBuilderService;
use App\Service\SettingsService;
use App\Service\SiteOwnerService;
use App\Service\TurnstileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TurnstileService $turnstileService,
        private readonly AdminNotificationService $adminNotificationService,
        private readonly SettingsService $settingsService,
        private readonly SiteOwnerService $siteOwnerService,
        private readonly SeoMetaBuilderService $seoMetaBuilderService,
        private readonly RateLimiterFactory $contactFormLimiter,
        private readonly InputSanitizer $inputSanitizer,
        private readonly InputValidator $inputValidator,
    ) {
    }

    #[Route('/iletisim', name: 'front_contact')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $this->contactFormLimiter->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'Çok fazla mesaj gönderdiniz. Lütfen bir süre bekleyin.');

                return $this->redirectToRoute('front_contact');
            }

            if (!$this->turnstileService->verify(
                $form->get('cf_turnstile_response')->getData(),
                $request->getClientIp()
            )) {
                $this->addFlash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');

                return $this->redirectToRoute('front_contact');
            }

            $message = new ContactMessage();
            $input = new ContactInput();
            $input->name = $this->inputSanitizer->text((string) $form->get('name')->getData(), 120);
            $input->email = $this->inputSanitizer->text((string) $form->get('email')->getData(), 180);
            $input->subject = $this->inputSanitizer->text((string) $form->get('subject')->getData(), 180);
            $input->message = $this->inputSanitizer->multiline((string) $form->get('message')->getData(), 5000);

            if (!$this->inputValidator->isValid($input)) {
                $this->addFlash('error', 'Form verisi geçersiz.');

                return $this->redirectToRoute('front_contact');
            }

            $message->setName($input->name);
            $message->setEmail($input->email);
            $message->setSubject($input->subject);
            $message->setMessage($input->message);
            $message->setIpAddress($request->getClientIp());
            $message->setUserAgent($request->headers->get('User-Agent'));

            $this->entityManager->persist($message);
            $this->entityManager->flush();

            $this->adminNotificationService->notifyNewContact($message);

            $this->addFlash('success', 'Mesajınız gönderildi. En kısa sürede dönüş yapacağım.');

            return $this->redirectToRoute('front_contact');
        }

        return $this->render('front/contact/index.html.twig', [
            'form' => $form,
            'siteEmail' => $this->settingsService->get('site_email'),
            'socialGithub' => $this->siteOwnerService->getGithub(),
            'socialTwitter' => $this->siteOwnerService->getTwitter(),
            'socialLinkedin' => $this->siteOwnerService->getLinkedin(),
            'seoMeta' => $this->seoMetaBuilderService->forArchive('İletişim'),
        ]);
    }
}
