<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Entity\PostRevision;
use App\Entity\User;
use App\Repository\PostRevisionRepository;
use Doctrine\ORM\EntityManagerInterface;

class PostRevisionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostMetaService $postMetaService,
        private readonly PostRevisionRepository $postRevisionRepository,
    ) {
    }

    public function createRevision(Post $post, ?User $author, string $action): void
    {
        $revision = (new PostRevision())
            ->setPost($post)
            ->setAuthor($author)
            ->setAction($action)
            ->setStatus($post->getStatus()->value)
            ->setTitle($post->getTitle())
            ->setSlug($post->getSlug())
            ->setContent($post->getContent() ?? '')
            ->setExcerpt($post->getExcerpt())
            ->setMeta($this->postMetaService->all($post));

        $this->entityManager->persist($revision);
        $this->entityManager->flush();
    }

    /** @return PostRevision[] */
    public function latest(Post $post, int $limit = 20): array
    {
        return $this->postRevisionRepository->findLatestForPost($post, $limit);
    }

    public function restore(PostRevision $revision): Post
    {
        $post = $revision->getPost();
        if ($post === null) {
            throw new \RuntimeException('Revision içeriği bulunamadı.');
        }

        $post->setTitle($revision->getTitle());
        $post->setSlug((string) $revision->getSlug());
        $post->setContent($revision->getContent());
        $post->setExcerpt($revision->getExcerpt());

        $this->postMetaService->replaceAll($post, $revision->getMeta());
        $this->entityManager->flush();

        return $post;
    }
}
