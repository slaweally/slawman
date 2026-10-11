<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SocialLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('icon', TextType::class, [
                'label' => 'İkon Sınıfı',
                'required' => false,
                'attr' => ['placeholder' => 'fa-brands fa-github'],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Bağlantı',
                'required' => false,
                'attr' => ['placeholder' => 'https://github.com/kullanici'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
