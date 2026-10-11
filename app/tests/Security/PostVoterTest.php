<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Post;
use App\Entity\User;
use App\Enum\PostStatus;
use App\Security\PostVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class PostVoterTest extends TestCase
{
    public function testAuthorCannotPublish(): void
    {
        $post = (new Post())->setStatus(PostStatus::Draft);
        $author = (new User())->setEmail('author@example.com')->setUsername('author')->setPassword('x')->setRoles(['ROLE_AUTHOR']);
        $post->setAuthor($author);

        $voter = new PostVoter();
        $token = new UsernamePasswordToken($author, 'main', $author->getRoles());

        self::assertFalse($voter->vote($token, $post, [PostVoter::PUBLISH]) > 0);
    }
}
