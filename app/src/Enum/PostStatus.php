<?php

declare(strict_types=1);

namespace App\Enum;

enum PostStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Private = 'private';
    case Archived = 'archived';
}
