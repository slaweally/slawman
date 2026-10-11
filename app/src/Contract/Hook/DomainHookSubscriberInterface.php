<?php

declare(strict_types=1);

namespace App\Contract\Hook;

interface DomainHookSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getHookSubscriptions(): array;
}
