<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MenuItem;
use App\Enum\PostType;
use App\Repository\MenuItemRepository;
use App\Repository\PostRepository;
use App\Service\SiteOwnerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(name: 'app:menu:sync-footer', description: 'Frontend footer fallback linklerini admin menüsüne senkronlar')]
class SyncFooterMenuCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MenuItemRepository $menuRepository,
        private readonly PostRepository $postRepository,
        private readonly SiteOwnerService $siteOwnerService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $order = 0;
        $created = 0;
        $updated = 0;

        $targets = [];
        foreach ($this->postRepository->findPublishedByType(PostType::Page, 50) as $page) {
            $targets[] = [
                'label' => (string) ($page->getTitle() ?? ucfirst((string) $page->getSlug())),
                'url' => $this->urlGenerator->generate('front_page', ['slug' => $page->getSlug()]),
                'icon' => null,
                'newTab' => false,
            ];
        }

        $targets[] = [
            'label' => 'RSS',
            'url' => $this->urlGenerator->generate('front_feed'),
            'icon' => 'bi bi-rss',
            'newTab' => false,
        ];
        $targets[] = [
            'label' => 'Sitemap',
            'url' => $this->urlGenerator->generate('front_sitemap'),
            'icon' => 'bi bi-diagram-3',
            'newTab' => false,
        ];

        $socialMap = [
            'github' => ['label' => 'GitHub', 'icon' => 'fa-brands fa-github', 'url' => $this->siteOwnerService->getGithub()],
            'twitter' => ['label' => 'Twitter', 'icon' => 'fa-brands fa-x-twitter', 'url' => $this->siteOwnerService->getTwitter()],
            'linkedin' => ['label' => 'LinkedIn', 'icon' => 'fa-brands fa-linkedin', 'url' => $this->siteOwnerService->getLinkedin()],
        ];
        foreach ($socialMap as $meta) {
            $url = trim((string) ($meta['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $targets[] = [
                'label' => $meta['label'],
                'url' => $url,
                'icon' => $meta['icon'],
                'newTab' => true,
            ];
        }

        foreach ($targets as $target) {
            $item = $this->menuRepository->findOneBy([
                'location' => 'footer',
                'label' => $target['label'],
            ]);

            if (!$item instanceof MenuItem) {
                $item = new MenuItem();
                $item->setLocation('footer');
                $this->entityManager->persist($item);
                $created++;
            } else {
                $updated++;
            }

            $item->setLabel($target['label']);
            $item->setUrl($target['url']);
            $item->setIcon($target['icon']);
            $item->setOpenInNewTab($target['newTab']);
            $item->setIsActive(true);
            $item->setSortOrder($order++);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Footer menüsü senkronlandı. %d yeni, %d güncellendi, toplam hedef: %d',
            $created,
            $updated,
            count($targets)
        ));

        return Command::SUCCESS;
    }
}

