<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SidebarWidget;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class SidebarWidgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Başlık'])
            ->add('type', ChoiceType::class, [
                'label' => 'Widget Türü',
                'choices' => [
                    'Özel HTML / Metin' => 'html',
                    'Profil Kartı' => 'profile',
                    'Son İçerikler' => 'recent',
                    'Kategoriler' => 'categories',
                    'Son Yorumlar' => 'comments',
                    'Arşiv Bağlantısı' => 'archive',
                ],
            ])
            ->add('content', TextareaType::class, ['required' => false, 'label' => 'İçerik / Profil Metni (HTML destekler)', 'attr' => ['rows' => 6]])
            ->add('archiveIcon', TextType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'Arşiv İkonu (FontAwesome sınıfı)',
                'attr' => ['placeholder' => 'fas fa-archive'],
            ])
            ->add('socialLinks', CollectionType::class, [
                'label' => 'Sosyal Medya İkonları',
                'entry_type' => SocialLinkType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'mapped' => false,
                'prototype' => true,
            ])
            ->add('sortOrder', IntegerType::class, ['label' => 'Sıra'])
            ->add('isActive', CheckboxType::class, ['required' => false, 'label' => 'Aktif']);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $widget = $event->getData();
            if (!$widget instanceof SidebarWidget) {
                return;
            }

            $event->getForm()->get('socialLinks')->setData($widget->getSocialLinks());
            $event->getForm()->get('archiveIcon')->setData($widget->getSettings()['archive_icon'] ?? 'fas fa-archive');
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $widget = $event->getData();
            if (!$widget instanceof SidebarWidget) {
                return;
            }

            $links = array_values(array_filter(
                (array) $event->getForm()->get('socialLinks')->getData(),
                static fn (array $link): bool => trim((string) ($link['url'] ?? '')) !== ''
            ));

            $settings = $widget->getSettings();
            $settings['social_links'] = $links;

            $icon = trim((string) $event->getForm()->get('archiveIcon')->getData());
            $settings['archive_icon'] = $icon !== '' ? $icon : 'fas fa-archive';

            $widget->setSettings($settings);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SidebarWidget::class]);
    }
}
