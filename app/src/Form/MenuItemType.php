<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MenuItem;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var MenuItem $current */
        $current = $options['data'];

        $builder
            ->add('location', ChoiceType::class, [
                'label' => 'Konum',
                'choices' => ['Üst Menü' => 'header', 'Alt Menü (Footer)' => 'footer'],
            ])
            ->add('label', TextType::class, ['label' => 'Etiket'])
            ->add('url', TextType::class, ['label' => 'URL (/blog veya https://...)'])
            ->add('icon', TextType::class, ['required' => false, 'label' => 'İkon (bi bi-*)', 'help' => 'Örn: bi bi-journal-code'])
            ->add('parent', EntityType::class, [
                'class' => MenuItem::class,
                'required' => false,
                'label' => 'Üst Menü Öğesi',
                'placeholder' => '— Kök öğe (üst menü yok) —',
                'choice_label' => 'label',
                'query_builder' => function (EntityRepository $er) use ($current) {
                    $qb = $er->createQueryBuilder('m')
                        ->andWhere('m.location = :location')
                        ->andWhere('m.parent IS NULL')
                        ->setParameter('location', $current->getLocation() ?: 'header')
                        ->orderBy('m.sortOrder', 'ASC');

                    if ($current->getId() !== null) {
                        $qb->andWhere('m.id != :selfId')->setParameter('selfId', $current->getId());
                    }

                    return $qb;
                },
                'help' => 'Seçilirse bu öğe, seçilen öğenin dropdown alt menüsünde görünür (tek seviye).',
            ])
            ->add('sortOrder', IntegerType::class, ['label' => 'Sıra'])
            ->add('isActive', CheckboxType::class, ['required' => false, 'label' => 'Aktif'])
            ->add('openInNewTab', CheckboxType::class, ['required' => false, 'label' => 'Yeni sekmede aç']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MenuItem::class]);
    }
}
