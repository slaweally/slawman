<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'app:sitemap-generate', description: 'Sitemap önbelleğini yeniler')]
class SitemapGenerateCommand extends Command
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly KernelInterface $appKernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $request = Request::create('/sitemap.xml');
        $response = $this->kernel->handle($request);
        $path = $this->appKernel->getProjectDir() . '/slw-content/cache/sitemap.xml';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $response->getContent());
        $this->kernel->terminate($request, $response);
        $io->success('Sitemap önbelleğe yazıldı: slw-content/cache/sitemap.xml');

        return Command::SUCCESS;
    }
}
