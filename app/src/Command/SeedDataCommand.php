<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use App\Service\SettingsRegistry;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-data', description: 'Varsayılan site ayarları ve kategorileri oluşturur')]
class SeedDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsService $settingsService,
        private readonly SettingsRegistry $settingsRegistry,
        private readonly MenuItemRepository $menuRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->settingsRegistry->all() as $key => $meta) {
            $existing = $this->entityManager->getRepository(\App\Entity\SiteSettings::class)
                ->findOneBy(['settingKey' => $key]);
            if ($existing === null) {
                $this->settingsService->set($key, $meta['default'] ?? '', $meta['group']);
            }
        }

        $categories = [
            ['Symfony', 'symfony', 'sf', '#000'],
            ['Laravel', 'laravel', 'lv', '#FF2D20'],
            ['WordPress', 'wordpress', 'wp', '#21759B'],
            ['WHMCS', 'whmcs', 'wh', '#F47921'],
            ['Linux/Sysadmin', 'linux-sysadmin', 'tux', '#333'],
            ['Genel', 'genel', '*', '#666'],
        ];

        foreach ($categories as $i => [$name, $slug, $icon, $color]) {
            $existing = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $slug]);
            if ($existing !== null) {
                continue;
            }
            $cat = new Category();
            $cat->setName($name);
            $cat->setSlug($slug);
            $cat->setIcon($icon);
            $cat->setColor($color);
            $cat->setSortOrder($i);
            $this->entityManager->persist($cat);
        }

        if ($this->menuRepository->count([]) === 0) {
            $headerMenus = [
                ['Blog', '/blog', 'bi bi-journal-code', 0],
                ['Notlar', '/notes', 'bi bi-lightning', 1],
                ['Projeler', '/projects', 'bi bi-box-seam', 2],
                ['Yazılım', '/software', 'bi bi-cpu', 3],
                ['Snippetler', '/snippets', 'bi bi-braces', 4],
                ['İletişim', '/iletisim', 'bi bi-envelope', 5],
            ];
            foreach ($headerMenus as [$label, $url, $icon, $order]) {
                $item = new MenuItem();
                $item->setLocation('header');
                $item->setLabel($label);
                $item->setUrl($url);
                $item->setIcon($icon);
                $item->setSortOrder($order);
                $this->entityManager->persist($item);
            }
        }

        $this->entityManager->flush();
        $io->success('Varsayılan veriler yüklendi.');

        return Command::SUCCESS;
    }
}
