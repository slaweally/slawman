<?php

declare(strict_types=1);

namespace App\Tests\Input;

use App\Input\InputSanitizer;
use PHPUnit\Framework\TestCase;

final class InputSanitizerTest extends TestCase
{
    public function testSanitizesText(): void
    {
        $sanitizer = new InputSanitizer();
        $value = $sanitizer->text('<script>alert(1)</script>Hello', 20);

        self::assertSame('alert(1)Hello', $value);
    }
}
