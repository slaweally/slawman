<?php

declare(strict_types=1);

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\MediaUploadService;

class UploadController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'upload_dir')]
        private readonly string $uploadDir,
        private readonly MediaUploadService $mediaUploadService,
    ) {
    }

    #[Route('/uploads/{path}', name: 'front_uploads', requirements: ['path' => '.+'])]
    public function serve(string $path): Response
    {
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            throw $this->createNotFoundException();
        }

        $uploadRoot = realpath($this->uploadDir);
        $fullPath = realpath($this->uploadDir . '/' . $path);
        if ($uploadRoot === false || $fullPath === false || !str_starts_with($fullPath, $uploadRoot . DIRECTORY_SEPARATOR)) {
            throw $this->createNotFoundException();
        }

        if (!is_file($fullPath)) {
            throw $this->createNotFoundException();
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        $response = new BinaryFileResponse($fullPath);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Type', $mime !== '' ? $mime : 'application/octet-stream');
        $response->setContentDisposition(
            $this->mediaUploadService->isInlineMime($mime) ? 'inline' : 'attachment',
            basename($fullPath)
        );

        return $response;
    }
}
