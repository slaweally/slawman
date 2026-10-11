<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Post;
use App\Entity\User;
use App\Enum\PostStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PostVoter extends Voter
{
    public const EDIT = 'POST_EDIT';
    public const PUBLISH = 'POST_PUBLISH';
    public const DELETE = 'POST_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::PUBLISH, self::DELETE], true) && $subject instanceof Post;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $post = $subject;
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            return true;
        }

        return match ($attribute) {
            self::EDIT => $this->canEdit($post, $user),
            self::PUBLISH => in_array('ROLE_EDITOR', $roles, true),
            self::DELETE => $this->canDelete($post, $user),
            default => false,
        };
    }

    private function canEdit(Post $post, User $user): bool
    {
        $roles = $user->getRoles();
        if (in_array('ROLE_EDITOR', $roles, true)) {
            return true;
        }

        if (in_array('ROLE_AUTHOR', $roles, true) || in_array('ROLE_CONTRIBUTOR', $roles, true)) {
            return $post->getAuthor()?->getId() === $user->getId()
                && $post->getStatus() !== PostStatus::Published;
        }

        return false;
    }

    private function canDelete(Post $post, User $user): bool
    {
        if (in_array('ROLE_EDITOR', $user->getRoles(), true)) {
            return true;
        }

        return $post->getAuthor()?->getId() === $user->getId() && $post->getStatus() === PostStatus::Draft;
    }
}
