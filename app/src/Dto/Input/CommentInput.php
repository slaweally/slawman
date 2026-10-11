<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

class CommentInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $authorName = '';

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $authorEmail = '';

    #[Assert\Length(max: 500)]
    #[Assert\Url(requireTld: false)]
    public ?string $authorWebsite = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 3000)]
    public string $content = '';

    #[Assert\PositiveOrZero]
    public int $parentId = 0;

    #[Assert\Positive]
    public int $postId = 0;
}
