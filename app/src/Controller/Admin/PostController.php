<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Enum\PostStatus;
use App\Enum\PostType;
use App\Event\PostLifecycleEvent;
use App\Form\PostFormType;
use App\PostType\PostTypeConfig;
use App\Entity\Tag;
use App\Repository\PostRevisionRepository;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Service\AuditLogService;
use App\Service\ContentSanitizerService;
use App\Service\PostRevisionService;
use App\Service\MediaUploadService;
use App\Service\PermalinkService;
use App\Service\PostFeaturedImageService;
use App\Service\PostMetaService;
use App\Service\SeoRedirectService;
use App\Service\SluggerService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/posts', name: 'admin_post_')]
#[IsGranted('ROLE_ADMIN')]
class PostController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentSanitizerService $sanitizer,
        private readonly SluggerService $slugger,
        private readonly AuditLogService $auditLog,
        private readonly PostMetaService $postMetaService,
        private readonly MediaUploadService $mediaUploadService,
        private readonly PostFeaturedImageService $featuredImageService,
        private readonly PostRevisionService $postRevisionService,
        private readonly PostRevisionRepository $postRevisionRepository,
        private readonly SeoRedirectService $seoRedirectService,
        private readonly PermalinkService $permalinkService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'type' => (string) $request->query->get('type', ''),
            'status' => (string) $request->query->get('status', ''),
            'category' => (string) $request->query->get('category', ''),
            'date_from' => (string) $request->query->get('date_from', ''),
            'date_to' => (string) $request->query->get('date_to', ''),
            'min_comments' => $request->query->get('min_comments') !== '' ? (int) $request->query->get('min_comments') : null,
            'sort' => (string) $request->query->get('sort', 'date'),
            'direction' => (string) $request->query->get('direction', 'desc'),
            'exclude_pages' => true,
        ];

        $qb = $this->postRepository->createAdminListQueryBuilder();
        $this->postRepository->applyAdminFilters($qb, $filters);

        $page = $request->query->get('page');
        $page = $page !== '' && $page !== null ? (int) $page : 1;
        
        $perPage = $request->query->get('per_page');
        $perPage = $perPage !== '' && $perPage !== null ? (int) $perPage : 20;

        $pagination = $paginator->paginate(
            $qb,
            $page,
            $perPage,
            [
                'distinct' => true,
                'sortFieldParameterName' => 'knp_sort',
                'sortDirectionParameterName' => 'knp_direction',
            ]
        );

        return $this->render('admin/post/index.html.twig', [
            'pagination' => $pagination,
            'filters' => $filters,
            'categories' => $this->categoryRepository->findBy([], ['name' => 'ASC']),
            'postTypes' => PostType::cases(),
            'postStatuses' => PostStatus::cases(),
        ]);
    }

    #[Route('/fix-featured-images', name: 'fix_featured', methods: ['POST'])]
    public function fixFeaturedImages(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('fix_featured', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_post_index');
        }

        $result = $this->featuredImageService->fixMissingFeaturedImages();
        $this->addFlash('success', sprintf('%d içeriğe kapak görseli atandı (%d görsel bulunamadı).', $result['updated'], $result['skipped']));

        return $this->redirectToRoute('admin_post_index');
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $post = new Post();
        $post->setContent('');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $post->setAuthor($user);

        if ($type = $request->query->get('type')) {
            try {
                $post->setType(PostType::from($type));
            } catch (\ValueError) {
            }
        }

        return $this->handleForm($request, $post, true);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $post = $this->postRepository->findActiveById($id);
        if ($post === null) {
            throw $this->createNotFoundException('İçerik bulunamadı veya silinmiş.');
        }

        $this->denyAccessUnlessGranted('POST_EDIT', $post);

        return $this->handleForm($request, $post, false);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        $post = $this->postRepository->findActiveById($id);
        if ($post === null) {
            throw $this->createNotFoundException('İçerik bulunamadı veya silinmiş.');
        }

        $this->denyAccessUnlessGranted('POST_DELETE', $post);

        if ($this->isCsrfTokenValid('delete' . $post->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($post);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'delete', Post::class, (int) $post->getId());
            $this->addFlash('success', 'İçerik silindi.');
        }

        return $this->redirectToRoute('admin_post_index');
    }

    #[Route('/{id}/status/{status}', name: 'change_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changeStatus(Request $request, int $id, string $status): Response
    {
        $post = $this->postRepository->findActiveById($id);
        if ($post === null) {
            throw $this->createNotFoundException('İçerik bulunamadı.');
        }

        if (!$this->isCsrfTokenValid('post_status_' . $post->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_post_edit', ['id' => $post->getId()]);
        }

        $next = PostStatus::tryFrom($status);
        if ($next === null) {
            $this->addFlash('error', 'Geçersiz durum.');

            return $this->redirectToRoute('admin_post_edit', ['id' => $post->getId()]);
        }

        if ($next === PostStatus::Published) {
            $this->denyAccessUnlessGranted('POST_PUBLISH', $post);
        } else {
            $this->denyAccessUnlessGranted('POST_EDIT', $post);
        }

        $post->setStatus($next);
        if ($next === PostStatus::Published && $post->getPublishedAt() === null) {
            $post->setPublishedAt(new \DateTime());
        }
        $this->entityManager->flush();
        $this->postRevisionService->createRevision($post, $this->currentUser(), 'status_change');
        $this->eventDispatcher->dispatch(new PostLifecycleEvent($post, 'status_change'));

        $this->addFlash('success', $this->buildSavedFlash($post, false, 'Durum güncellendi.'));

        return $this->redirectToRoute('admin_post_edit', ['id' => $post->getId()]);
    }

    #[Route('/{id}/revisions/{revisionId}/restore', name: 'restore_revision', methods: ['POST'], requirements: ['id' => '\d+', 'revisionId' => '\d+'])]
    public function restoreRevision(Request $request, int $id, int $revisionId): Response
    {
        $post = $this->postRepository->findActiveById($id);
        if ($post === null) {
            throw $this->createNotFoundException('İçerik bulunamadı.');
        }
        $this->denyAccessUnlessGranted('POST_EDIT', $post);

        if (!$this->isCsrfTokenValid('restore_revision_' . $revisionId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_post_edit', ['id' => $id]);
        }

        $revision = $this->postRevisionRepository->find($revisionId);
        if ($revision === null || $revision->getPost()?->getId() !== $post->getId()) {
            $this->addFlash('error', 'Revision bulunamadı.');

            return $this->redirectToRoute('admin_post_edit', ['id' => $id]);
        }

        $this->postRevisionService->restore($revision);
        $this->postRevisionService->createRevision($post, $this->currentUser(), 'restore');
        $this->eventDispatcher->dispatch(new PostLifecycleEvent($post, 'restore'));
        $this->addFlash('success', $this->buildSavedFlash($post, false, 'Revision geri yüklendi.'));

        return $this->redirectToRoute('admin_post_edit', ['id' => $id]);
    }

    private function handleForm(Request $request, Post $post, bool $isNew): Response
    {
        $oldSlug = $post->getSlug() ?? '';
        $oldType = $post->getType();

        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$isNew) {
                $this->denyAccessUnlessGranted('POST_EDIT', $post);
            }

            $this->sanitizeContentForType($post);
            $this->syncTagsFromRequest($post, $request);

            if ($post->getType() !== PostType::Snippet) {
                $post->setProgrammingLanguage(null);
            }

            try {
                $this->saveMetaFromForm($post, $form);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->render('admin/post/form.html.twig', [
                    'form' => $form,
                    'post' => $post,
                    'isNew' => $isNew,
                    'postTypeConfig' => $this->buildTypeConfigForJs(),
                    'postFormBlocks' => PostTypeConfig::adminFormBlocks(),
                    'revisions' => $isNew ? [] : $this->postRevisionService->latest($post, 15),
                ]);
            }

            $this->postMetaService->purgeIrrelevantMeta($post);

            if (!$post->getSlug()) {
                $base = $post->getTitle() ?: 'post-' . time();
                $post->setSlug($this->slugger->slugify($base));
            }
            $post->setSlug($this->ensureUniqueSlug($post->getSlug() ?? 'post-' . time(), $post->getId()));

            if ($post->getStatus() === PostStatus::Published && $post->getPublishedAt() === null) {
                $post->setPublishedAt(new \DateTime());
            }

            $this->entityManager->persist($post);
            $this->entityManager->flush();
            $this->postRevisionService->createRevision($post, $this->currentUser(), $isNew ? 'create' : 'update');
            $this->eventDispatcher->dispatch(new PostLifecycleEvent($post, $isNew ? 'created' : 'updated'));

            if (!$isNew && $oldSlug !== '') {
                $newSlug = $post->getSlug() ?? $oldSlug;
                if ($oldSlug !== $newSlug || $oldType !== $post->getType()) {
                    $this->seoRedirectService->upsert(
                        $this->slugger->buildPostPath($oldType->value, $oldSlug),
                        $this->slugger->buildPostPath($post->getType()->value, $newSlug)
                    );
                }
            }

            $this->auditLog->log(
                $this->getUser(),
                $isNew ? 'create' : 'update',
                Post::class,
                (int) $post->getId()
            );

            $this->addFlash('success', $this->buildSavedFlash($post, $isNew));

            return $this->redirectToRoute('admin_post_edit', ['id' => $post->getId()]);
        }

        return $this->render('admin/post/form.html.twig', [
            'form' => $form,
            'post' => $post,
            'isNew' => $isNew,
            'postTypeConfig' => $this->buildTypeConfigForJs(),
            'postFormBlocks' => PostTypeConfig::adminFormBlocks(),
            'revisions' => $isNew ? [] : $this->postRevisionService->latest($post, 15),
        ]);
    }

    /**
     * Formdaki #post-tags select'i (name="tags[]") Symfony Form'a bağlı değil —
     * Tom Select WordPress tarzı canlı arama/oluşturma sağlıyor. Değerler normalde
     * Tag ID'si olur; ancak kullanıcı yeni etiket oluşturduktan hemen sonra (JS'in
     * gerçek ID'yi alıp option'ı güncellemesini beklemeden) submit ederse ham etiket
     * adı da gelebilir — bu yüzden sayısal olmayan değerler isme göre bulunur/oluşturulur.
     */
    private function syncTagsFromRequest(Post $post, Request $request): void
    {
        $raw = $request->request->all('tags');
        if (!is_array($raw)) {
            return;
        }

        foreach ($post->getTags() as $existingTag) {
            $post->removeTag($existingTag);
        }

        foreach ($raw as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if (ctype_digit($value)) {
                $tag = $this->tagRepository->find((int) $value);
            } else {
                $tag = $this->tagRepository->findOneBy(['name' => $value]);
                if ($tag === null) {
                    $tag = new Tag();
                    $tag->setName($value);
                    $tag->setSlug($this->slugger->slugify($value));
                    $this->entityManager->persist($tag);
                }
            }

            if ($tag !== null) {
                $post->addTag($tag);
            }
        }
    }

    private function sanitizeContentForType(Post $post): void
    {
        $content = $post->getContent() ?? '';

        if (PostTypeConfig::usesRichEditor($post->getType())) {
            $post->setContent($this->sanitizer->sanitize($content));
        } elseif (PostTypeConfig::usesCodeEditor($post->getType())) {
            $post->setContent($content);
        } elseif (PostTypeConfig::isNote($post->getType())) {
            $post->setContent(mb_substr(strip_tags($content), 0, 250));
        } else {
            $post->setContent($this->sanitizer->sanitize($content));
        }
    }

    /** @param \Symfony\Component\Form\FormInterface $form */
    private function saveMetaFromForm(Post $post, $form): void
    {
        if (PostTypeConfig::hasSeo($post->getType())) {
            $this->postMetaService->applySeoData($post, [
                PostTypeConfig::META_SEO_TITLE => $form->get('seoTitle')->getData(),
                PostTypeConfig::META_SEO_DESCRIPTION => $form->get('seoDescription')->getData(),
                PostTypeConfig::META_SEO_KEYWORDS => $form->get('seoKeywords')->getData(),
                PostTypeConfig::META_OG_IMAGE => $form->get('ogImage')->getData(),
            ]);
        }

        $this->postMetaService->applyTypeMeta($post, [
            PostTypeConfig::META_REPO_URL => $form->get('repoUrl')->getData(),
            PostTypeConfig::META_DEMO_URL => $form->get('demoUrl')->getData(),
            PostTypeConfig::META_TECH_STACK => $form->get('techStack')->getData(),
            PostTypeConfig::META_GALLERY => $form->get('gallery')->getData(),
            PostTypeConfig::META_VERSION => $form->get('version')->getData(),
            PostTypeConfig::META_DISTRIBUTION => $form->get('distribution')->getData(),
            PostTypeConfig::META_DOWNLOAD_URL => $this->resolveDownloadUrl($form),
            PostTypeConfig::META_SOURCE_URL => $form->get('sourceUrl')->getData(),
            PostTypeConfig::META_CHANGELOG => $form->get('changelog')->getData(),
            PostTypeConfig::META_LICENSE => $form->get('license')->getData(),
            PostTypeConfig::META_DIFFICULTY => $form->get('difficulty')->getData(),
            PostTypeConfig::META_ESTIMATED_TIME => $form->get('estimatedTime')->getData(),
            PostTypeConfig::META_PREREQUISITES => $form->get('prerequisites')->getData(),
            PostTypeConfig::META_TOOLS_REQUIRED => $form->get('toolsRequired')->getData(),
            PostTypeConfig::META_ERROR_CONTENT => $form->get('errorContent')->getData(),
            PostTypeConfig::META_ERROR_CONTEXT => $form->get('errorContext')->getData(),
            PostTypeConfig::META_SNIPPET_NOTES => $this->sanitizeMetaHtml(
                $post,
                (string) ($form->get('snippetNotes')->getData() ?? '')
            ),
            PostTypeConfig::META_COMPARE_SIDE_A => $form->get('compareSideA')->getData(),
            PostTypeConfig::META_COMPARE_SIDE_B => $form->get('compareSideB')->getData(),
            PostTypeConfig::META_COMPARE_SIDE_A_SUB => $form->get('compareSideASub')->getData(),
            PostTypeConfig::META_COMPARE_SIDE_B_SUB => $form->get('compareSideBSub')->getData(),
            PostTypeConfig::META_COMPARE_WINNER => $form->get('compareWinner')->getData(),
            PostTypeConfig::META_COMPARE_VERDICT => $form->get('compareVerdict')->getData(),
            PostTypeConfig::META_COMPARE_CRITERIA => $form->get('compareCriteria')->getData(),
        ]);
    }

    /** @param \Symfony\Component\Form\FormInterface $form */
    private function resolveDownloadUrl($form): ?string
    {
        /** @var UploadedFile|null $file */
        $file = $form->get('downloadFile')->getData();
        if ($file instanceof UploadedFile) {
            $user = $this->getUser();
            if (!$user instanceof \App\Entity\User) {
                throw new \RuntimeException('Oturum açmış kullanıcı gerekli.');
            }

            try {
                $media = $this->mediaUploadService->uploadSoftwarePackage($file, $user);

                return $this->mediaUploadService->getPublicUrl($media);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('İndirme dosyası yüklenemedi: ' . $e->getMessage(), 0, $e);
            }
        }

        $url = trim((string) ($form->get('downloadUrl')->getData() ?? ''));

        return $url !== '' ? $url : null;
    }

    private function sanitizeMetaHtml(Post $post, string $html): ?string
    {
        if ($html === '') {
            return null;
        }

        if ($post->getType() === PostType::Snippet) {
            return $this->sanitizer->sanitize($html);
        }

        return $html;
    }

    /** @return array<string, mixed> */
    private function buildTypeConfigForJs(): array
    {
        $config = [];
        foreach (PostType::cases() as $type) {
            $config[$type->value] = [
                'label' => PostTypeConfig::label($type),
                'richEditor' => PostTypeConfig::usesRichEditor($type),
                'codeEditor' => PostTypeConfig::usesCodeEditor($type),
                'snippetNotesEditor' => PostTypeConfig::usesSnippetNotesEditor($type),
                'note' => PostTypeConfig::isNote($type),
                'hasSeo' => PostTypeConfig::hasSeo($type),
                'hasFeaturedImage' => PostTypeConfig::hasFeaturedImage($type),
                'hasExcerpt' => PostTypeConfig::hasExcerpt($type),
                'requiresTitle' => PostTypeConfig::requiresTitle($type),
                'contentPanelTitle' => PostTypeConfig::contentPanelTitle($type),
                'contentHint' => PostTypeConfig::contentHint($type),
            ];
        }

        return $config;
    }

    private function currentUser(): ?\App\Entity\User
    {
        $user = $this->getUser();

        return $user instanceof \App\Entity\User ? $user : null;
    }

    /**
     * @return array{text: string, viewUrl?: string}
     */
    private function buildSavedFlash(Post $post, bool $isNew, ?string $text = null): array
    {
        $payload = [
            'text' => $text ?? ($isNew ? 'İçerik oluşturuldu.' : 'İçerik güncellendi.'),
        ];

        if ($post->getStatus() === PostStatus::Published) {
            $payload['viewUrl'] = $this->permalinkService->buildPostUrl(
                $post,
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return $payload;
    }

    private function ensureUniqueSlug(string $slug, ?int $postId = null): string
    {
        $candidate = $slug;
        $i = 1;
        while ($this->postRepository->isSlugTaken($candidate)) {
            $existing = $this->postRepository->findOneBy(['slug' => $candidate]);
            if ($existing !== null && $postId !== null && $existing->getId() === $postId) {
                break;
            }
            $candidate = $slug . '-' . $i;
            ++$i;
        }

        return $candidate;
    }
}
