<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Ad'])
            ->add('slug', TextType::class, ['required' => false, 'label' => 'Slug'])
            ->add('description', TextareaType::class, ['required' => false, 'label' => 'Açıklama'])
            ->add('icon', TextType::class, ['required' => false, 'label' => 'İkon'])
            ->add('color', TextType::class, ['required' => false, 'label' => 'Renk'])
            ->add('sortOrder', IntegerType::class, ['label' => 'Sıra'])
            ->add('isActive', CheckboxType::class, ['required' => false, 'label' => 'Aktif'])
            ->add('parent', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Üst Kategori',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Category::class]);
    }
}
