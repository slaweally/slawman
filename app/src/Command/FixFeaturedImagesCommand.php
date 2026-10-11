<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PostFeaturedImageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:fix-featured-images', description: 'Kapak görseli olmayan içeriklere içerik/meta görsellerini atar')]
class FixFeaturedImagesCommand extends Command
{
    public function __construct(private readonly PostFeaturedImageService $featuredImageService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->featuredImageService->fixMissingFeaturedImages();
        $io->success(sprintf('%d içerik güncellendi, %d içerikte görsel bulunamadı.', $result['updated'], $result['skipped']));

        return Command::SUCCESS;
    }
}
