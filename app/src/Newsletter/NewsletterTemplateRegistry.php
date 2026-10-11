<?php

declare(strict_types=1);

namespace App\Newsletter;

/**
 * Admin arayüzü için şablon meta verileri.
 */
class NewsletterTemplateRegistry
{
    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     fields: list<string>
     * }>
     */
    public function all(): array
    {
        $templates = [];

        foreach (NewsletterTemplateId::cases() as $case) {
            $templates[] = [
                'id' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
                'fields' => $this->fieldsFor($case),
            ];
        }

        return $templates;
    }

    public function get(NewsletterTemplateId $id): ?array
    {
        foreach ($this->all() as $template) {
            if ($template['id'] === $id->value) {
                return $template;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function fieldsFor(NewsletterTemplateId $id): array
    {
        return match ($id) {
            NewsletterTemplateId::Newsletter => ['title', 'intro', 'body', 'cta_text', 'cta_url'],
            NewsletterTemplateId::Announcement => ['title', 'body', 'highlight'],
            NewsletterTemplateId::NewPost => ['post_title', 'post_excerpt', 'post_url', 'post_type'],
            NewsletterTemplateId::Digest => ['title', 'intro', 'digest_items'],
            NewsletterTemplateId::Custom => ['body_html'],
        };
    }
}
