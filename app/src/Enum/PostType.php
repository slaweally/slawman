<?php

declare(strict_types=1);

namespace App\Enum;

enum PostType: string
{
    case Note = 'note';
    case Article = 'article';
    case Project = 'project';
    case Software = 'software';
    case Snippet = 'snippet';
    case Page = 'page';
    case HowTo = 'howto';
    case Troubleshoot = 'troubleshoot';
    case Comparison = 'comparison';
}
