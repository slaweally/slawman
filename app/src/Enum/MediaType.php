<?php

declare(strict_types=1);

namespace App\Enum;

enum MediaType: string
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';
    case Audio = 'audio';
    case Archive = 'archive';
    case Other = 'other';
}
