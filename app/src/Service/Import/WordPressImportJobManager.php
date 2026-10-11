<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Dto\Import\WordPressImportJob;
use App\Dto\Wxr\WxrAttachment;
use App\Dto\Wxr\WxrComment;
use App\Dto\Wxr\WxrParseResult;
use App\Dto\Wxr\WxrPost;
use App\Dto\Wxr\WxrTerm;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WordPressImportJobManager
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function jobsDir(): string
    {
        $dir = $this->projectDir . '/slw-content/import-jobs';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Import job dizini oluşturulamadı.');
        }

        return $dir;
    }

    public function save(WordPressImportJob $job): void
    {
        $path = $this->jobPath($job->jobId);
        $json = json_encode($job->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException('Import job kaydedilemedi.');
        }
    }

    public function load(string $jobId): ?WordPressImportJob
    {
        $path = $this->jobPath($jobId);
        if (!is_readable($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return WordPressImportJob::fromArray($data);
    }

    public function delete(WordPressImportJob $job): void
    {
        @unlink($this->jobPath($job->jobId));
        if ($job->parseCachePath !== '' && is_file($job->parseCachePath)) {
            @unlink($job->parseCachePath);
        }
    }

    public function saveParseCache(string $jobId, WxrParseResult $parse): string
    {
        $path = $this->jobsDir() . '/' . $jobId . '_parse.json';
        $json = json_encode(WxrParseSerializer::toArray($parse), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException('Parse önbelleği kaydedilemedi.');
        }

        return $path;
    }

    public function loadParseCache(string $path): WxrParseResult
    {
        if (!is_readable($path)) {
            throw new \RuntimeException('Parse önbelleği bulunamadı.');
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return WxrParseSerializer::fromArray($data);
    }

    private function jobPath(string $jobId): string
    {
        if (!preg_match('/^[a-f0-9-]{36}$/', $jobId)) {
            throw new \InvalidArgumentException('Geçersiz job ID.');
        }

        return $this->jobsDir() . '/' . $jobId . '.json';
    }
}
