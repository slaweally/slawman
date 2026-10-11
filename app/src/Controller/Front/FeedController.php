<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FeedController extends AbstractController
{
    public function __construct(private readonly PostRepository $postRepository)
    {
    }

    #[Route('/feed.xml', name: 'front_feed')]
    public function rss(): Response
    {
        $posts = $this->postRepository->createPublishedQueryBuilder()
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $response = $this->render('front/feed/rss.xml.twig', ['posts' => $posts]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');

        return $response;
    }
}
