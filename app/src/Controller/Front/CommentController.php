<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Post;
use App\Enum\PostStatus;
use App\Dto\Input\CommentInput;
use App\Form\CommentFormType;
use App\Input\InputSanitizer;
use App\Input\InputValidator;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Service\CommentService;
use App\Service\SettingsService;
use App\Service\TrackerLogService;
use App\Service\TurnstileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class CommentController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly CommentService $commentService,
        private readonly TrackerLogService $trackerLogService,
        private readonly TurnstileService $turnstileService,
        private readonly SettingsService $settingsService,
        private readonly RateLimiterFactory $commentFormLimiter,
        private readonly InputSanitizer $inputSanitizer,
        private readonly InputValidator $inputValidator,
    ) {
    }

    #[Route('/comment', name: 'front_comment_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        if (!$this->commentService->isEnabled()) {
            throw $this->createNotFoundException();
        }

        $postId = $request->request->getInt('post_id');
        $post = $this->postRepository->find($postId);
        if (!$post instanceof Post || $post->getStatus() !== PostStatus::Published) {
            throw $this->createNotFoundException();
        }

        $limiter = $this->commentFormLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Çok fazla yorum gönderdiniz. Lütfen bir süre bekleyin.');

            return $this->redirectToPost($post);
        }

        $form = $this->createForm(CommentFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Yorum gönderilemedi. Lütfen alanları kontrol edin.');

            return $this->redirectToPost($post);
        }

        if (!$this->turnstileService->verify(
            $form->get('cf_turnstile_response')->getData(),
            $request->getClientIp()
        )) {
            $this->addFlash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');

            return $this->redirectToPost($post);
        }

        $parent = null;
        $parentId = (int) $form->get('parentId')->getData();
        if ($parentId > 0) {
            $parent = $this->commentRepository->find($parentId);
            if ($parent === null || $parent->getPost()?->getId() !== $post->getId()) {
                $parent = null;
            }
        }

        $input = new CommentInput();
        $input->postId = $postId;
        $input->authorName = $this->inputSanitizer->text((string) $form->get('authorName')->getData(), 120);
        $input->authorEmail = $this->inputSanitizer->text((string) $form->get('authorEmail')->getData(), 180);
        $input->authorWebsite = $this->inputSanitizer->website((string) $form->get('authorWebsite')->getData());
        $input->content = $this->inputSanitizer->multiline((string) $form->get('content')->getData(), 3000);
        $input->parentId = $parentId;
        if (!$this->inputValidator->isValid($input)) {
            $this->addFlash('error', 'Yorum verisi geçersiz.');

            return $this->redirectToPost($post);
        }

        $this->commentService->create(
            $post,
            $input->authorName,
            $input->authorEmail,
            $input->content,
            $parent,
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
            $input->authorWebsite,
        );

        try {
            $this->trackerLogService->log('comment', $request, $post->getTitle(), $request->cookies->get('tvid'));
        } catch (\Throwable) {
            // Takip paneli hatası yorum akışını etkilememeli.
        }

        $this->addFlash(
            'success',
            $this->settingsService->isTruthy('moderation_enabled')
                ? 'Yorumunuz moderasyon için gönderildi.'
                : 'Yorumunuz yayınlandı.'
        );

        return $this->redirectToPost($post);
    }

    private function redirectToPost(Post $post): Response
    {
        $url = $this->generateUrl('front_post_show', [
            'type' => $post->getType()->value,
            'slug' => $post->getSlug(),
        ]) . '#comments';

        return $this->redirect($url);
    }
}
