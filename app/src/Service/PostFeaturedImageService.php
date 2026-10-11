<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;

class PostFeaturedImageService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostRepository $postRepository,
        private readonly PostMetaService $postMetaService,
    ) {
    }

    /** @return array{updated: int, skipped: int} */
    public function fixMissingFeaturedImages(): array
    {
        $posts = $this->postRepository->createAdminListQueryBuilder()
            ->andWhere('p.featuredImage IS NULL OR p.featuredImage = :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $updated = 0;
        $skipped = 0;

        foreach ($posts as $post) {
            if (!$post instanceof Post) {
                continue;
            }

            $image = $this->resolveFeaturedImage($post);
            if ($image === null) {
                ++$skipped;
                continue;
            }

            $post->setFeaturedImage($image);
            $this->entityManager->persist($post);
            ++$updated;
        }

        $this->entityManager->flush();

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    private function resolveFeaturedImage(Post $post): ?string
    {
        foreach (['og_image', 'wp_rank_math_facebook_image', 'wp_rank_math_twitter_image', 'wp_rank_math_opengraph_image'] as $key) {
            $meta = $this->postMetaService->get($post, $key);
            if ($meta !== null && $meta !== '') {
                $local = $this->normalizeImageUrl($meta);
                if ($local !== null) {
                    return $local;
                }
            }
        }

        return $this->extractFirstImageFromContent($post->getContent() ?? '');
    }

    private function extractFirstImageFromContent(string $content): ?string
    {
        if (preg_match('#src=["\'](/uploads/[^"\']+)["\']#i', $content, $m)) {
            return $m[1];
        }

        if (preg_match('#src=["\'](https?://[^"\']+/uploads/[^"\']+)["\']#i', $content, $m)) {
            return $this->normalizeImageUrl($m[1]);
        }

        if (preg_match('#/uploads/wp-import/[^\s"\'<>]+#i', $content, $m)) {
            return $m[0];
        }

        return null;
    }

    private function normalizeImageUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/uploads/')) {
            return $url;
        }

        if (preg_match('#(/uploads/[^\s"\'<>]+)$#i', $url, $m)) {
            return $m[1];
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
