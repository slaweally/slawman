<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Dto\Wxr\WxrAttachment;
use App\Dto\Wxr\WxrComment;
use App\Dto\Wxr\WxrParseResult;
use App\Dto\Wxr\WxrPost;
use App\Dto\Wxr\WxrTerm;

class WxrParserService
{
  private const WP_NS = 'http://wordpress.org/export/1.2/';
  private const CONTENT_NS = 'http://purl.org/rss/1.0/modules/content/';
  private const EXCERPT_NS = 'http://wordpress.org/export/1.2/excerpt/';

    public function parse(string $filePath): WxrParseResult
    {
        $result = new WxrParseResult();

        if (!is_readable($filePath)) {
            $result->errors[] = 'Dosya okunamıyor.';

            return $result;
        }

        $reader = new \XMLReader();
        if (!$reader->open($filePath)) {
            $result->errors[] = 'XML dosyası açılamadı.';

            return $result;
        }

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'title' && $reader->depth <= 2 && $result->siteTitle === '') {
                $result->siteTitle = trim($reader->readString());
            }

            if ($reader->name === 'link' && $reader->depth <= 2 && $result->blogUrl === '') {
                $result->blogUrl = trim($reader->readString());
            }

            if ($reader->name === 'wp:base_site_url' && $reader->namespaceURI === self::WP_NS) {
                $result->baseUrl = rtrim(trim($reader->readString()), '/');
            }

            if (($reader->name === 'wp:category' || $reader->localName === 'category') && $reader->namespaceURI === self::WP_NS) {
                $node = $this->nodeFromReader($reader);
                if ($node !== null) {
                    $wp = $node->children(self::WP_NS);
                    $slug = trim((string) ($wp->category_nicename ?? ''));
                    $name = trim((string) ($wp->cat_name ?? $slug));
                    if ($slug !== '') {
                        $result->categories[$slug] = new WxrTerm($slug, $name, 'category');
                    }
                }
            }

            if (($reader->name === 'wp:tag' || $reader->localName === 'tag') && $reader->namespaceURI === self::WP_NS) {
                $node = $this->nodeFromReader($reader);
                if ($node !== null) {
                    $wp = $node->children(self::WP_NS);
                    $slug = trim((string) ($wp->tag_slug ?? ''));
                    $name = trim((string) ($wp->tag_name ?? $slug));
                    if ($slug !== '') {
                        $result->tags[$slug] = new WxrTerm($slug, $name, 'post_tag');
                    }
                }
            }

            if ($reader->name === 'item') {
                $this->parseItem($this->nodeFromReader($reader), $result);
            }
        }

        $reader->close();

        if ($result->baseUrl === '' && $result->blogUrl !== '') {
            $result->baseUrl = rtrim($result->blogUrl, '/');
        }

        $this->collectTermsFromPosts($result);

        return $result;
    }

    private function collectTermsFromPosts(WxrParseResult $result): void
    {
        foreach ($result->posts as $post) {
            foreach ($post->terms as $term) {
                $slug = trim($term['slug']);
                $name = trim($term['name'] !== '' ? $term['name'] : $slug);
                if ($slug === '') {
                    continue;
                }

                if ($term['domain'] === 'category' && !isset($result->categories[$slug])) {
                    $result->categories[$slug] = new WxrTerm($slug, $name, 'category');
                }

                if ($term['domain'] === 'post_tag' && !isset($result->tags[$slug])) {
                    $result->tags[$slug] = new WxrTerm($slug, $name, 'post_tag');
                }
            }
        }
    }

    private function parseItem(?\SimpleXMLElement $item, WxrParseResult $result): void
    {
        if ($item === null) {
            return;
        }

        $item->registerXPathNamespace('wp', self::WP_NS);
        $item->registerXPathNamespace('content', self::CONTENT_NS);
        $item->registerXPathNamespace('excerpt', self::EXCERPT_NS);

        $wp = $item->children(self::WP_NS);
        $postType = (string) $wp->post_type;
        $postId = (int) $wp->post_id;

        if ($postType === 'attachment') {
            $url = (string) ($wp->attachment_url ?: $item->guid);
            if ($url === '' || $postId <= 0) {
                return;
            }

            $alt = null;
            foreach ($wp->postmeta ?? [] as $meta) {
                if ((string) $meta->meta_key === '_wp_attachment_image_alt') {
                    $alt = (string) $meta->meta_value ?: null;
                }
            }

            $result->attachments[$postId] = new WxrAttachment(
                $postId,
                $url,
                (string) $item->title,
                $alt,
                $this->guessMimeFromUrl($url),
            );

            return;
        }

        if (!in_array($postType, ['post', 'page'], true)) {
            return;
        }

        if (!in_array((string) $wp->status, ['publish', 'draft', 'private', 'pending', 'future'], true)) {
            return;
        }

        $contentNs = $item->children(self::CONTENT_NS);
        $excerptNs = $item->children(self::EXCERPT_NS);

        $terms = [];
        foreach ($item->category ?? [] as $cat) {
            $terms[] = [
                'domain' => (string) $cat['domain'],
                'slug' => (string) $cat['nicename'],
                'name' => (string) $cat,
            ];
        }

        $meta = [];
        foreach ($wp->postmeta ?? [] as $postmeta) {
            $key = (string) $postmeta->meta_key;
            if ($key !== '') {
                $meta[$key] = (string) $postmeta->meta_value;
            }
        }

        $comments = [];
        foreach ($wp->comment ?? [] as $wpComment) {
            $commentId = (int) $wpComment->comment_id;
            if ($commentId <= 0) {
                continue;
            }
            $comments[] = new WxrComment(
                $commentId,
                (int) $wpComment->comment_parent,
                (string) ($wpComment->comment_author ?: 'Anonim'),
                (string) ($wpComment->comment_author_email ?: 'noreply@import.local'),
                (string) $wpComment->comment_content,
                (string) $wpComment->comment_date,
                (string) $wpComment->comment_approved === '1',
                ((string) $wpComment->comment_author_IP) ?: null,
                ((string) $wpComment->comment_agent) ?: null,
                $this->normalizeAuthorUrl((string) ($wpComment->comment_author_url ?? '')),
            );
        }

        $result->posts[$postId] = new WxrPost(
            $postId,
            $postType,
            (string) $item->title ?: 'Başlıksız',
            (string) ($wp->post_name ?: 'post-' . $postId),
            (string) ($contentNs->encoded ?: $item->description),
            (string) ($excerptNs->encoded ?? ''),
            (string) $wp->status,
            (string) $wp->post_date,
            (int) $wp->is_sticky === 1,
            $terms,
            $meta,
            $comments,
            (string) ($item->link ?? ''),
        );
    }

    private function nodeFromReader(\XMLReader $reader): ?\SimpleXMLElement
    {
        $xml = $reader->readOuterXML();
        if ($xml === '') {
            return null;
        }

        return @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA) ?: null;
    }

    private function guessMimeFromUrl(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }

    private function normalizeAuthorUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 500) : null;
    }
}
