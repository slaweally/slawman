<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CommentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('authorName', TextType::class, [
                'label' => 'Adınız',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])
            ->add('authorEmail', EmailType::class, [
                'label' => 'E-posta (gizli)',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('authorWebsite', UrlType::class, [
                'required' => false,
                'default_protocol' => 'https',
                'label' => 'Web sitesi (isteğe bağlı)',
                'attr' => ['placeholder' => 'https://'],
                'constraints' => [new Assert\Length(max: 500), new Assert\Url(requireTld: false)],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Yorumunuz',
                'attr' => ['rows' => 4],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 3, max: 5000)],
            ])
            ->add('parentId', HiddenType::class, ['required' => false, 'mapped' => false])
            ->add('cf_turnstile_response', HiddenType::class, ['mapped' => false, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'comment_form']);
    }
}
