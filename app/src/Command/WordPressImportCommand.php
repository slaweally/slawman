<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\WordPressImporterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:wordpress-import',
    description: 'WordPress WXR dosyasını önizle, içe aktar veya geri al',
)]
class WordPressImportCommand extends Command
{
    public function __construct(
        private readonly WordPressImporterService $importer,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'WXR XML dosya yolu')
            ->addOption('preview', null, InputOption::VALUE_NONE, 'Yalnızca doğrulama ve önizleme')
            ->addOption('no-media', null, InputOption::VALUE_NONE, 'Medya indirmeyi atla')
            ->addOption('rollback', null, InputOption::VALUE_REQUIRED, 'Batch ID ile geri al');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rollback = $input->getOption('rollback');
        if (is_string($rollback) && $rollback !== '') {
            $result = $this->importer->rollback($rollback);
            if ($result['success']) {
                $io->success($result['message']);
            } else {
                $io->error($result['message']);
            }

            return $result['success'] ? Command::SUCCESS : Command::FAILURE;
        }

        $file = $input->getOption('file');
        if (!is_string($file) || $file === '' || !is_readable($file)) {
            $io->error('Geçerli bir --file yolu belirtin.');

            return Command::FAILURE;
        }

        if ($input->getOption('preview')) {
            $preview = $this->importer->preview($file);
            $io->title('WordPress WXR Önizleme');
            $io->writeln('Geçerli: ' . ($preview['valid'] ? 'Evet' : 'Hayır'));
            $io->table(['Alan', 'Değer'], [
                ['Site', (string) ($preview['preview']['site_title'] ?? '-')],
                ['URL', (string) ($preview['preview']['base_url'] ?? '-')],
                ['Makale', (string) ($preview['preview']['counts']['posts'] ?? 0)],
                ['Sayfa', (string) ($preview['preview']['counts']['pages'] ?? 0)],
                ['Kategori', (string) ($preview['preview']['counts']['categories'] ?? 0)],
                ['Etiket', (string) ($preview['preview']['counts']['tags'] ?? 0)],
                ['Yorum', (string) ($preview['preview']['counts']['comments'] ?? 0)],
                ['Medya URL', (string) ($preview['preview']['media_urls'] ?? 0)],
                ['Yeni içerik', (string) ($preview['preview']['new_items'] ?? 0)],
                ['Atlanacak', (string) ($preview['preview']['duplicates'] ?? 0)],
            ]);

            foreach ($preview['warnings'] as $warning) {
                $io->warning($warning);
            }
            foreach ($preview['errors'] as $error) {
                $io->error($error);
            }

            return $preview['valid'] ? Command::SUCCESS : Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy([]);
        if ($user === null) {
            $io->error('Sistemde kullanıcı bulunamadı.');

            return Command::FAILURE;
        }

        $downloadMedia = !$input->getOption('no-media');
        $io->writeln('İçe aktarma başlıyor...');
        $result = $this->importer->importFromWxr($file, $user, $downloadMedia);

        $io->success(sprintf('Batch: %s', $result['batch_id']));
        $io->table(['Metrik', 'Adet'], [
            ['Makale', (string) $result['posts']],
            ['Sayfa', (string) $result['pages']],
            ['Kategori', (string) $result['categories']],
            ['Etiket', (string) $result['tags']],
            ['Yorum', (string) $result['comments']],
            ['Medya', (string) $result['media']],
            ['Medya hatası', (string) $result['media_failed']],
            ['Atlandı', (string) $result['skipped']],
            ['Hata', (string) count($result['errors'])],
        ]);

        foreach (array_slice($result['errors'], 0, 10) as $error) {
            $io->writeln('<comment>' . $error . '</comment>');
        }

        return $result['errors'] === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
