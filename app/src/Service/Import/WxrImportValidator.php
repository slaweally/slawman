<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Dto\Wxr\WxrParseResult;
use App\Repository\PostRepository;

class WxrImportValidator
{
    public function __construct(
        private readonly WxrParserService $parser,
        private readonly PostRepository $postRepository,
    ) {
    }

    /**
     * @return array{
     *     valid: bool,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     preview: array<string, mixed>,
     *     parse: WxrParseResult|null
     * }
     */
    public function validate(string $filePath): array
    {
        $errors = [];
        $warnings = [];

        if (!is_readable($filePath)) {
            return $this->result(false, ['Dosya okunamıyor.'], [], [], null);
        }

        $size = filesize($filePath);
        if ($size === false || $size < 100) {
            return $this->result(false, ['Dosya boş veya geçersiz.'], [], [], null);
        }

        if ($size > 200 * 1024 * 1024) {
            $warnings[] = 'Dosya 200MB üzerinde; işlem uzun sürebilir.';
        }

        $parse = $this->parser->parse($filePath);
        if ($parse->errors !== []) {
            return $this->result(false, $parse->errors, $warnings, [], $parse);
        }

        $counts = $parse->counts();
        if ($counts['posts'] === 0 && $counts['pages'] === 0) {
            $errors[] = 'İçe aktarılacak makale veya sayfa bulunamadı.';
        }

        if ($parse->baseUrl === '') {
            $warnings[] = 'Site URL bulunamadı; medya indirme göreli yollarla sınırlı kalabilir.';
        }

        $duplicates = 0;
        foreach ($parse->posts as $wpPost) {
            if ($this->postRepository->findOneByWpImportId($wpPost->id) !== null) {
                ++$duplicates;
            }
        }

        if ($duplicates > 0) {
            $warnings[] = sprintf('%d içerik daha önce aktarılmış; atlanacak.', $duplicates);
        }

        $mediaUrls = $this->collectRequiredMediaUrls($parse);
        if ($parse->baseUrl !== '' && $mediaUrls === []) {
            $warnings[] = 'İndirilecek medya URL bulunamadı.';
        }

        $preview = [
            'site_title' => $parse->siteTitle,
            'base_url' => $parse->baseUrl,
            'counts' => $counts,
            'duplicates' => $duplicates,
            'media_urls' => count($mediaUrls),
            'new_items' => count($parse->posts) - $duplicates,
        ];

        return $this->result($errors === [], $errors, $warnings, $preview, $parse);
    }

    /** @return list<string> */
    public function collectRequiredMediaUrls(WxrParseResult $parse): array
    {
        $urls = [];

        foreach ($parse->attachments as $attachment) {
            $urls[$attachment->url] = $attachment->url;
        }

        foreach ($parse->posts as $post) {
            if (isset($post->meta['_thumbnail_id'])) {
                $thumbId = (int) $post->meta['_thumbnail_id'];
                if (isset($parse->attachments[$thumbId])) {
                    $urls[$parse->attachments[$thumbId]->url] = $parse->attachments[$thumbId]->url;
                }
            }

            foreach ($this->extractContentUrls($post->content, $parse->baseUrl) as $url) {
                $urls[$url] = $url;
            }
        }

        return array_values($urls);
    }

    /** @return list<string> */
    private function extractContentUrls(string $content, string $baseUrl): array
    {
        $found = [];
        if (preg_match_all('#https?://[^\s"\'<>]+/wp-content/uploads/[^\s"\'<>]+#i', $content, $matches)) {
            foreach ($matches[0] as $url) {
                $found[] = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if ($baseUrl !== '' && preg_match_all('#/wp-content/uploads/[^\s"\'<>]+#i', $content, $relMatches)) {
            foreach ($relMatches[0] as $path) {
                $found[] = $baseUrl . $path;
            }
        }

        return $found;
    }

    /**
     * @param list<string>           $errors
     * @param list<string>           $warnings
     * @param array<string, mixed>   $preview
     *
     * @return array{valid: bool, errors: list<string>, warnings: list<string>, preview: array<string, mixed>, parse: WxrParseResult|null}
     */
    private function result(bool $valid, array $errors, array $warnings, array $preview, ?WxrParseResult $parse): array
    {
        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'preview' => $preview,
            'parse' => $parse,
        ];
    }
}
