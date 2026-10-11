<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Enum\MediaType;
use App\Event\MediaLifecycleEvent;
use App\Form\MediaFormType;
use App\Repository\MediaRepository;
use App\Service\AuditLogService;
use App\Service\MediaUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/media', name: 'admin_media_')]
#[IsGranted('ROLE_ADMIN')]
class MediaController extends AbstractController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly MediaUploadService $uploadService,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogService $auditLog,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $this->mediaRepository->createListQueryBuilder();
        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            $request->query->getInt('per_page', 48)
        );

        $items = [];
        foreach ($pagination as $media) {
            if (!$media instanceof Media) {
                continue;
            }
            $items[] = [
                'entity' => $media,
                'url' => $this->uploadService->getPublicUrl($media),
                'thumb' => $this->uploadService->getThumbnailUrl($media),
            ];
        }

        return $this->render('admin/media/index.html.twig', [
            'pagination' => $pagination,
            'mediaItems' => $items,
        ]);
    }

    #[Route('/list-ajax', name: 'list_ajax', methods: ['GET'])]
    public function listAjax(Request $request): JsonResponse
    {
        $imagesOnly = $request->query->getBoolean('images', true);
        $limit = min(200, max(1, $request->query->getInt('limit', 60)));

        $criteria = [];
        if ($imagesOnly) {
            $criteria['type'] = MediaType::Image;
        }

        $items = $this->mediaRepository->findBy($criteria, ['createdAt' => 'DESC'], $limit);

        $data = array_map(fn (Media $media) => [
            'id' => $media->getId(),
            'url' => $this->uploadService->getPublicUrl($media),
            'thumb' => $this->uploadService->getThumbnailUrl($media),
            'name' => $media->getOriginalName(),
            'mime' => $media->getMimeType(),
        ], $items);

        return new JsonResponse(['items' => $data]);
    }

    #[Route('/upload-ajax', name: 'upload_ajax', methods: ['POST'])]
    public function uploadAjax(Request $request): JsonResponse
    {
        $token = (string) ($request->request->get('_token') ?? $request->headers->get('X-CSRF-TOKEN') ?? '');
        if (!$this->isCsrfTokenValid('media_upload', $token)) {
            return new JsonResponse(['error' => 'Geçersiz token'], 400);
        }

        $file = $request->files->get('file');
        if ($file === null) {
            $files = $request->files->all();
            $file = $files['files'][0] ?? $files['files[]'][0] ?? null;
        }

        if ($file === null) {
            return new JsonResponse(['error' => 'Dosya bulunamadı'], 400);
        }

        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $media = $request->query->getBoolean('software')
                ? $this->uploadService->uploadSoftwarePackage($file, $user)
                : $this->uploadService->upload($file, $user);
            $this->auditLog->log($user, 'create', Media::class, (int) $media->getId());
            $this->eventDispatcher->dispatch(new MediaLifecycleEvent($media, 'uploaded'));

            $url = $this->uploadService->getPublicUrl($media);

            if ($request->query->getBoolean('tinymce')) {
                // İçerik editörünün (CKEditor 5) upload adapter'ı { location: "url" } bekler.
                return new JsonResponse(['location' => $url]);
            }

            return new JsonResponse([
                'url' => $url,
                'thumb' => $this->uploadService->getThumbnailUrl($media),
                'alt' => $media->getAlt() ?? $media->getOriginalName(),
                'id' => $media->getId(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, Media $media): Response
    {
        $form = $this->createForm(MediaFormType::class, $media);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'update', Media::class, (int) $media->getId());
            $this->addFlash('success', 'Medya güncellendi.');

            return $this->redirectToRoute('admin_media_show', ['id' => $media->getId()]);
        }

        return $this->render('admin/media/show.html.twig', [
            'media' => $media,
            'form' => $form,
            'publicUrl' => $this->uploadService->getPublicUrl($media),
            'thumbUrl' => $this->uploadService->getThumbnailUrl($media),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Media $media): Response
    {
        if ($this->isCsrfTokenValid('delete' . $media->getId(), (string) $request->request->get('_token'))) {
            $this->uploadService->deleteFiles($media);
            $this->entityManager->remove($media);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'delete', Media::class, (int) $media->getId());
            $this->eventDispatcher->dispatch(new MediaLifecycleEvent($media, 'deleted'));
            $this->addFlash('success', 'Medya silindi.');

            return $this->redirectToRoute('admin_media_index');
        }

        return $this->redirectToRoute('admin_media_show', ['id' => $media->getId()]);
    }
}
