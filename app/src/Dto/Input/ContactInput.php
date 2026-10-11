<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ContactInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public string $subject = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 5000)]
    public string $message = '';
}
