<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Entity\PostMeta;
use App\PostType\PostTypeConfig;
use Doctrine\ORM\EntityManagerInterface;

class PostMetaService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(Post $post, string $key, ?string $default = null): ?string
    {
        foreach ($post->getMeta() as $meta) {
            if ($meta->getMetaKey() === $key) {
                return $meta->getMetaValue();
            }
        }

        return $default;
    }

    public function set(Post $post, string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            $this->remove($post, $key);

            return;
        }

        $meta = $this->findMeta($post, $key);
        if ($meta === null) {
            $meta = new PostMeta();
            $meta->setMetaKey($key);
            $post->addMeta($meta);
        }
        $meta->setMetaValue($value);
    }

    public function remove(Post $post, string $key): void
    {
        $meta = $this->findMeta($post, $key);
        if ($meta !== null) {
            $post->removeMeta($meta);
            $this->entityManager->remove($meta);
        }
    }

    /** @return array<string, string> */
    public function all(Post $post): array
    {
        $out = [];
        foreach ($post->getMeta() as $meta) {
            $out[$meta->getMetaKey()] = (string) $meta->getMetaValue();
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    public function replaceAll(Post $post, array $data): void
    {
        foreach ($post->getMeta()->toArray() as $meta) {
            $post->removeMeta($meta);
            $this->entityManager->remove($meta);
        }

        foreach ($data as $key => $value) {
            $this->set($post, (string) $key, $this->stringOrNull($value));
        }
    }

    /** @return array<string, string|null> */
    public function getSeoData(Post $post): array
    {
        $data = [];
        foreach (PostTypeConfig::seoMetaKeys() as $key) {
            $data[$key] = $this->get($post, $key);
        }

        return $data;
    }

    /** @return array<string, string|null> */
    public function getTypeMeta(Post $post): array
    {
        $data = [];
        foreach (PostTypeConfig::metaKeysFor($post->getType()) as $key) {
            $data[$key] = $this->get($post, $key);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function applySeoData(Post $post, array $data): void
    {
        foreach (PostTypeConfig::seoMetaKeys() as $key) {
            if (array_key_exists($key, $data)) {
                $this->set($post, $key, $this->stringOrNull($data[$key]));
            }
        }
    }

    /** @param array<string, mixed> $data */
    public function applyTypeMeta(Post $post, array $data): void
    {
        foreach (PostTypeConfig::metaKeysFor($post->getType()) as $key) {
            if (array_key_exists($key, $data)) {
                $this->set($post, $key, $this->stringOrNull($data[$key]));
            }
        }
    }

    /** İçerik tipi değişince artık geçerli olmayan meta kayıtlarını siler. */
    public function purgeIrrelevantMeta(Post $post): void
    {
        $allowed = PostTypeConfig::metaKeysFor($post->getType());
        if (PostTypeConfig::hasSeo($post->getType())) {
            $allowed = array_merge($allowed, PostTypeConfig::seoMetaKeys());
        }

        foreach ($post->getMeta()->toArray() as $meta) {
            if (!in_array($meta->getMetaKey(), $allowed, true)) {
                $this->remove($post, $meta->getMetaKey());
            }
        }
    }

    /** @return list<string> */
    public function splitList(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $parts = preg_split('/[\n,]+/', $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $item): bool => $item !== ''));
    }

    private function findMeta(Post $post, string $key): ?PostMeta
    {
        foreach ($post->getMeta() as $meta) {
            if ($meta->getMetaKey() === $key) {
                return $meta;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
