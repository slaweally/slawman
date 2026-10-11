<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:security:smoke', description: 'Run baseline security smoke checks')]
class SecuritySmokeCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $checks = [];

        $securityYaml = $this->read('config/packages/security.yaml');
        $rateLimiterYaml = $this->read('config/packages/rate_limiter.yaml');
        $headersListener = $this->read('src/EventListener/SecurityHeadersListener.php');
        $adminTopbar = $this->read('templates/admin/partials/topbar.html.twig');

        $checks[] = $this->check('Security has sodium hasher', str_contains($securityYaml, 'algorithm: sodium'));
        $checks[] = $this->check('Admin access control exists', str_contains($securityYaml, "roles: ROLE_ADMIN"));
        $checks[] = $this->check('Admin login limiter exists', str_contains($rateLimiterYaml, 'admin_login:'));
        $checks[] = $this->check('Admin action limiter exists', str_contains($rateLimiterYaml, 'admin_actions:'));
        $checks[] = $this->check('CSP header is set', str_contains($headersListener, 'Content-Security-Policy'));
        $checks[] = $this->check('CSP blocks framing', str_contains($headersListener, "frame-ancestors 'none'"));
        $checks[] = $this->check('No inline onclick in topbar', !str_contains($adminTopbar, 'onclick='));
        $checks[] = $this->check('Topbar uses data-confirm', str_contains($adminTopbar, 'data-confirm-message='));

        $io->table(['Check', 'Status'], array_map(
            static fn (array $item): array => [$item['name'], $item['ok'] ? 'OK' : 'FAIL'],
            $checks
        ));

        $failed = array_filter($checks, static fn (array $item): bool => !$item['ok']);
        if ($failed !== []) {
            $io->error(sprintf('Security smoke failed (%d checks).', count($failed)));
            return Command::FAILURE;
        }

        $io->success('Security smoke checks passed.');
        return Command::SUCCESS;
    }

    private function read(string $relativePath): string
    {
        $path = $this->projectDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $content = @file_get_contents($path);

        return is_string($content) ? $content : '';
    }

    /**
     * @return array{name:string,ok:bool}
     */
    private function check(string $name, bool $ok): array
    {
        return ['name' => $name, 'ok' => $ok];
    }
}
