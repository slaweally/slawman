<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Adınız', 'constraints' => [new Assert\NotBlank()]])
            ->add('email', EmailType::class, ['label' => 'E-posta', 'constraints' => [new Assert\NotBlank(), new Assert\Email()]])
            ->add('subject', TextType::class, ['label' => 'Konu', 'constraints' => [new Assert\NotBlank()]])
            ->add('message', TextareaType::class, ['label' => 'Mesaj', 'constraints' => [new Assert\NotBlank()]])
            ->add('cf_turnstile_response', HiddenType::class, ['mapped' => false, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'contact_form']);
    }
}
