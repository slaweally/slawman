<?php

declare(strict_types=1);

namespace App\PostType;

use App\Enum\PostType;

/**
 * CMF — içerik tipi başına alan tanımları.
 */
final class PostTypeConfig
{
    public const META_SEO_TITLE = 'seo_title';
    public const META_SEO_DESCRIPTION = 'seo_description';
    public const META_SEO_KEYWORDS = 'seo_keywords';
    public const META_OG_IMAGE = 'og_image';

    public const META_REPO_URL = 'repo_url';
    public const META_DEMO_URL = 'demo_url';
    public const META_TECH_STACK = 'tech_stack';
    public const META_GALLERY = 'gallery';

    public const META_VERSION = 'version';
    public const META_DISTRIBUTION = 'distribution';
    public const META_DOWNLOAD_URL = 'download_url';
    public const META_SOURCE_URL = 'source_url';
    public const META_CHANGELOG = 'changelog';
    public const META_LICENSE = 'license';

    public const META_DIFFICULTY = 'difficulty';
    public const META_ESTIMATED_TIME = 'estimated_time';
    public const META_PREREQUISITES = 'prerequisites';
    public const META_TOOLS_REQUIRED = 'tools_required';

    public const META_ERROR_CONTENT = 'error_content';
    public const META_ERROR_CONTEXT = 'error_context';

    public const META_SNIPPET_NOTES = 'snippet_notes';

    public const META_COMPARE_SIDE_A = 'compare_side_a';
    public const META_COMPARE_SIDE_B = 'compare_side_b';
    public const META_COMPARE_SIDE_A_SUB = 'compare_side_a_sub';
    public const META_COMPARE_SIDE_B_SUB = 'compare_side_b_sub';
    public const META_COMPARE_WINNER = 'compare_winner';
    public const META_COMPARE_VERDICT = 'compare_verdict';
    public const META_COMPARE_CRITERIA = 'compare_criteria';

    /** @return list<string> */
    public static function seoMetaKeys(): array
    {
        return [
            self::META_SEO_TITLE,
            self::META_SEO_DESCRIPTION,
            self::META_SEO_KEYWORDS,
            self::META_OG_IMAGE,
        ];
    }

    /** @return list<string> */
    public static function metaKeysFor(PostType $type): array
    {
        return match ($type) {
            PostType::Project => [
                self::META_REPO_URL,
                self::META_DEMO_URL,
                self::META_TECH_STACK,
                self::META_GALLERY,
            ],
            PostType::Software => [
                self::META_VERSION,
                self::META_DISTRIBUTION,
                self::META_DOWNLOAD_URL,
                self::META_SOURCE_URL,
                self::META_CHANGELOG,
                self::META_LICENSE,
            ],
            PostType::HowTo => [
                self::META_DIFFICULTY,
                self::META_ESTIMATED_TIME,
                self::META_PREREQUISITES,
                self::META_TOOLS_REQUIRED,
            ],
            PostType::Troubleshoot => [
                self::META_ERROR_CONTENT,
                self::META_ERROR_CONTEXT,
            ],
            PostType::Snippet => [
                self::META_SNIPPET_NOTES,
            ],
            PostType::Comparison => [
                self::META_COMPARE_SIDE_A,
                self::META_COMPARE_SIDE_B,
                self::META_COMPARE_SIDE_A_SUB,
                self::META_COMPARE_SIDE_B_SUB,
                self::META_COMPARE_WINNER,
                self::META_COMPARE_VERDICT,
                self::META_COMPARE_CRITERIA,
            ],
            PostType::Article, PostType::Page => [],
            PostType::Note => [],
        };
    }

    public static function usesRichEditor(PostType $type): bool
    {
        return in_array($type, [
            PostType::Article,
            PostType::Page,
            PostType::Project,
            PostType::Software,
            PostType::HowTo,
            PostType::Troubleshoot,
            PostType::Comparison,
        ], true);
    }

    public static function usesCodeEditor(PostType $type): bool
    {
        return $type === PostType::Snippet;
    }

    public static function usesSnippetNotesEditor(PostType $type): bool
    {
        return $type === PostType::Snippet;
    }

    public static function isNote(PostType $type): bool
    {
        return $type === PostType::Note;
    }

    public static function hasSeo(PostType $type): bool
    {
        return in_array($type, [
            PostType::Article,
            PostType::Page,
            PostType::Project,
            PostType::Software,
            PostType::HowTo,
            PostType::Troubleshoot,
            PostType::Comparison,
        ], true);
    }

    public static function hasFeaturedImage(PostType $type): bool
    {
        return $type !== PostType::Note;
    }

    public static function hasExcerpt(PostType $type): bool
    {
        return in_array($type, [
            PostType::Article,
            PostType::Page,
            PostType::Project,
            PostType::Software,
            PostType::HowTo,
            PostType::Troubleshoot,
            PostType::Comparison,
        ], true);
    }

    public static function requiresTitle(PostType $type): bool
    {
        return $type !== PostType::Note;
    }

    public static function label(PostType $type): string
    {
        return match ($type) {
            PostType::Note => 'Not',
            PostType::Article => 'Makale',
            PostType::Project => 'Proje',
            PostType::Software => 'Yazılım / Ürün',
            PostType::Snippet => 'Kod Parçacığı',
            PostType::Page => 'Sayfa',
            PostType::HowTo => 'Nasıl Yapılır',
            PostType::Troubleshoot => 'Hata / Çözüm',
            PostType::Comparison => 'Karşılaştırma',
        };
    }

    public static function contentPanelTitle(PostType $type): string
    {
        return match ($type) {
            PostType::Article => 'Makale İçeriği',
            PostType::Page => 'Sayfa İçeriği',
            PostType::Project => 'Proje Tanıtımı',
            PostType::Software => 'Ürün Açıklaması',
            PostType::Snippet => 'Kaynak Kod',
            PostType::Note => 'Not',
            PostType::HowTo => 'Adım Adım Rehber',
            PostType::Troubleshoot => 'Çözüm Adımları',
            PostType::Comparison => 'Detaylı Analiz',
        };
    }

    public static function contentHint(PostType $type): string
    {
        return match ($type) {
            PostType::Article, PostType::Page => 'Ana metin — görselleri araç çubuğundan veya medya galerisinden ekleyin.',
            PostType::Project => 'Projenin detaylı tanıtımı, ekran görüntüleri ve açıklamalar.',
            PostType::Software => 'Ürün özellikleri, kullanım kılavuzu ve tanıtım metni.',
            PostType::Snippet => 'Ham kod — sözdizimi vurgusu için düz metin. Açıklama için aşağıdaki not alanını kullanın.',
            PostType::Note => 'Kısa not, en fazla 250 karakter.',
            PostType::HowTo => 'Numaralı adımlar kullanın (1. 2. 3.) — her adımı ayrı paragrafta yazın.',
            PostType::Troubleshoot => 'Hatayı çözmek için uygulanacak adımları buraya yazın. Hata mesajı yukarıdaki alanda.',
            PostType::Comparison => 'Her iki taraf için detaylı inceleme, artılar/eksiler ve sonuç paragrafları.',
        };
    }

    public static function difficultyLabel(?string $value): string
    {
        return match ($value) {
            'easy' => 'Kolay',
            'medium' => 'Orta',
            'hard' => 'Zor',
            default => 'Belirtilmemiş',
        };
    }

    public static function distributionLabel(?string $value): string
    {
        return match ($value) {
            'opensource' => 'Açık Kaynak',
            'commercial' => 'Ticari',
            default => '',
        };
    }

    /** @return array<string, string> */
    public static function distributionChoices(): array
    {
        return [
            'Açık Kaynak (Ücretsiz)' => 'opensource',
            'Ticari Yazılım (Ücretli)' => 'commercial',
        ];
    }

    public static function compareWinnerLabel(?string $value): string
    {
        return match ($value) {
            'a' => 'Taraf A',
            'b' => 'Taraf B',
            'tie' => 'Berabere',
            default => 'Belirsiz',
        };
    }

    /**
     * Satır formatı: Kriter | Taraf A | Taraf B | kazanan (a/b/tie/-)
     *
     * @return list<array{label: string, a: string, b: string, winner: string}>
     */
    public static function parseCompareCriteria(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 3) {
                continue;
            }

            $winner = strtolower($parts[3] ?? 'none');
            if (!in_array($winner, ['a', 'b', 'tie', 'none', '-', ''], true)) {
                $winner = 'none';
            }
            if ($winner === '-' || $winner === '') {
                $winner = 'none';
            }

            $rows[] = [
                'label' => $parts[0],
                'a' => $parts[1],
                'b' => $parts[2],
                'winner' => $winner,
            ];
        }

        return $rows;
    }

    /**
     * Admin içerik formu — tip başına görünür bloklar (JS ile senkron).
     *
     * @return array<string, list<string>>
     */
    public static function adminFormBlocks(): array
    {
        return [
            'title' => ['article', 'page', 'project', 'software', 'snippet', 'howto', 'troubleshoot', 'comparison'],
            'snippet-lang' => ['snippet'],
            'snippet-notes' => ['snippet'],
            'howto' => ['howto'],
            'troubleshoot' => ['troubleshoot'],
            'comparison' => ['comparison'],
            'content' => ['note', 'article', 'page', 'project', 'software', 'snippet', 'howto', 'troubleshoot', 'comparison'],
            'excerpt' => ['article', 'page', 'project', 'software', 'howto', 'troubleshoot', 'comparison'],
            'project' => ['project'],
            'software' => ['software'],
            'seo' => ['article', 'page', 'project', 'software', 'howto', 'troubleshoot', 'comparison'],
            'featured' => ['article', 'page', 'project', 'software', 'snippet', 'howto', 'troubleshoot', 'comparison'],
            'taxonomy' => ['article', 'project', 'software', 'snippet', 'howto', 'troubleshoot', 'comparison'],
            'publish-flags' => ['article', 'project', 'software', 'snippet', 'note', 'howto', 'troubleshoot', 'comparison'],
        ];
    }
}
