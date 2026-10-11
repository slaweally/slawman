<?php

declare(strict_types=1);

namespace App\Service;

use HTMLPurifier;
use HTMLPurifier_Config;

class ContentSanitizerService
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,u,s,ul,ol,li,h1,h2,h3,h4,blockquote,pre,code[class],a[href|title],img[src|alt|width|height],figure[class],figcaption,table,thead,tbody,tr,td[colspan|rowspan],th[colspan|rowspan],hr,span[class]');
        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitize(string $html): string
    {
        return $this->purifier->purify($html);
    }
}
