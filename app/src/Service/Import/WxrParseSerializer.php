<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Dto\Wxr\WxrAttachment;
use App\Dto\Wxr\WxrComment;
use App\Dto\Wxr\WxrParseResult;
use App\Dto\Wxr\WxrPost;
use App\Dto\Wxr\WxrTerm;

final class WxrParseSerializer
{
    /** @return array<string, mixed> */
    public static function toArray(WxrParseResult $parse): array
    {
        return [
            'siteTitle' => $parse->siteTitle,
            'baseUrl' => $parse->baseUrl,
            'blogUrl' => $parse->blogUrl,
            'attachments' => array_map(static fn (WxrAttachment $a): array => [
                'id' => $a->id,
                'url' => $a->url,
                'title' => $a->title,
                'alt' => $a->alt,
                'mimeType' => $a->mimeType,
            ], array_values($parse->attachments)),
            'posts' => array_map(static fn (WxrPost $p): array => [
                'id' => $p->id,
                'type' => $p->type,
                'title' => $p->title,
                'slug' => $p->slug,
                'content' => $p->content,
                'excerpt' => $p->excerpt,
                'status' => $p->status,
                'postDate' => $p->postDate,
                'isSticky' => $p->isSticky,
                'terms' => $p->terms,
                'meta' => $p->meta,
                'comments' => array_map(static fn (WxrComment $c): array => [
                    'id' => $c->id,
                    'parentId' => $c->parentId,
                    'author' => $c->author,
                    'email' => $c->email,
                    'content' => $c->content,
                    'date' => $c->date,
                    'approved' => $c->approved,
                    'ip' => $c->ip,
                    'userAgent' => $c->userAgent,
                    'authorUrl' => $c->authorUrl,
                ], $p->comments),
                'link' => $p->link,
            ], array_values($parse->posts)),
            'categories' => array_map(static fn (WxrTerm $t): array => [
                'slug' => $t->slug,
                'name' => $t->name,
                'taxonomy' => $t->taxonomy,
            ], array_values($parse->categories)),
            'tags' => array_map(static fn (WxrTerm $t): array => [
                'slug' => $t->slug,
                'name' => $t->name,
                'taxonomy' => $t->taxonomy,
            ], array_values($parse->tags)),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): WxrParseResult
    {
        $parse = new WxrParseResult();
        $parse->siteTitle = (string) ($data['siteTitle'] ?? '');
        $parse->baseUrl = (string) ($data['baseUrl'] ?? '');
        $parse->blogUrl = (string) ($data['blogUrl'] ?? '');

        foreach ($data['attachments'] ?? [] as $row) {
            $id = (int) $row['id'];
            $parse->attachments[$id] = new WxrAttachment(
                $id,
                (string) $row['url'],
                (string) ($row['title'] ?? ''),
                $row['alt'] ?? null,
                (string) ($row['mimeType'] ?? $row['mime'] ?? 'application/octet-stream'),
            );
        }

        foreach ($data['categories'] ?? [] as $row) {
            $slug = (string) $row['slug'];
            $parse->categories[$slug] = new WxrTerm($slug, (string) $row['name'], 'category');
        }

        foreach ($data['tags'] ?? [] as $row) {
            $slug = (string) $row['slug'];
            $parse->tags[$slug] = new WxrTerm($slug, (string) $row['name'], 'post_tag');
        }

        foreach ($data['posts'] ?? [] as $row) {
            $comments = [];
            foreach ($row['comments'] ?? [] as $c) {
                $comments[] = new WxrComment(
                    (int) $c['id'],
                    (int) ($c['parentId'] ?? 0),
                    (string) ($c['author'] ?? 'Anonim'),
                    (string) ($c['email'] ?? 'noreply@import.local'),
                    (string) ($c['content'] ?? ''),
                    (string) ($c['date'] ?? ''),
                    (bool) ($c['approved'] ?? false),
                    $c['ip'] ?? null,
                    $c['userAgent'] ?? null,
                    $c['authorUrl'] ?? null,
                );
            }

            $id = (int) $row['id'];
            $parse->posts[$id] = new WxrPost(
                $id,
                (string) $row['type'],
                (string) ($row['title'] ?? ''),
                (string) ($row['slug'] ?? ''),
                (string) ($row['content'] ?? ''),
                (string) ($row['excerpt'] ?? ''),
                (string) ($row['status'] ?? 'draft'),
                (string) ($row['postDate'] ?? ''),
                (bool) ($row['isSticky'] ?? false),
                (array) ($row['terms'] ?? []),
                (array) ($row['meta'] ?? []),
                $comments,
                (string) ($row['link'] ?? ''),
            );
        }

        return $parse;
    }
}
