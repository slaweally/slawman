<?php

declare(strict_types=1);

namespace App\Newsletter;

enum NewsletterTemplateId: string
{
    case Newsletter = 'newsletter';
    case Announcement = 'announcement';
    case NewPost = 'new_post';
    case Digest = 'digest';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Newsletter => 'Bülten',
            self::Announcement => 'Duyuru',
            self::NewPost => 'Yeni İçerik',
            self::Digest => 'Özet / Digest',
            self::Custom => 'Serbest İçerik',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Newsletter => 'Genel bülten: başlık, giriş metni, içerik ve isteğe bağlı buton.',
            self::Announcement => 'Önemli duyurular için vurgulu kutu ve kısa mesaj.',
            self::NewPost => 'Yeni yazı veya proje paylaşımı bildirimi.',
            self::Digest => 'Birden fazla link/madde içeren periyodik özet.',
            self::Custom => 'Konu ve HTML içeriği tamamen sizin belirlediğiniz mail.',
        };
    }

    public function twigTemplate(): string
    {
        return 'email/' . $this->value . '.html.twig';
    }

    public function defaultSubject(string $siteTitle): string
    {
        return match ($this) {
            self::Newsletter => $siteTitle . ' — Bülten',
            self::Announcement => $siteTitle . ' — Önemli Duyuru',
            self::NewPost => $siteTitle . ' — Yeni İçerik',
            self::Digest => $siteTitle . ' — Özet',
            self::Custom => $siteTitle,
        };
    }

    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
