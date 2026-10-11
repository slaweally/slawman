<?php

declare(strict_types=1);

namespace App\Input;

use Symfony\Component\Validator\Validator\ValidatorInterface;

class InputValidator
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    public function isValid(object $dto): bool
    {
        return $this->validator->validate($dto)->count() === 0;
    }
}
