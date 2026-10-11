<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\AuditLogService;
use App\Service\MediaUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile', name: 'admin_user_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AuditLogService $auditLog,
        private readonly MediaUploadService $mediaUploadService,
    ) {
    }

    #[Route('', name: 'profile')]
    public function profile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Geçersiz token.');

                return $this->redirectToRoute('admin_user_profile');
            }

            $user->setDisplayName($request->request->get('display_name') ?: null);
            $user->setBio((string) $request->request->get('bio', ''));
            $user->setWebsite($request->request->get('website') ?: null);
            $user->setGithub($request->request->get('github') ?: null);
            $user->setTwitter($request->request->get('twitter') ?: null);
            $user->setLinkedin($request->request->get('linkedin') ?: null);
            $user->setStackTags($request->request->get('stack_tags') ?: null);

            $avatarUrl = $request->request->get('avatar');
            $user->setAvatar($avatarUrl !== null && $avatarUrl !== '' ? (string) $avatarUrl : null);

            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $request->files->get('avatar_file');
            if ($avatarFile instanceof UploadedFile) {
                $media = $this->mediaUploadService->upload($avatarFile, $user);
                $user->setAvatar($this->mediaUploadService->getPublicUrl($media));
            }

            $newPassword = (string) $request->request->get('new_password', '');
            if ($newPassword !== '') {
                $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
            }

            $this->entityManager->flush();
            $this->auditLog->log($user, 'update', User::class, (int) $user->getId());
            $this->addFlash('success', 'Profil güncellendi.');

            return $this->redirectToRoute('admin_user_profile');
        }

        return $this->render('admin/user/profile.html.twig', ['user' => $user]);
    }
}
