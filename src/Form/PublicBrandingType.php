<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class PublicBrandingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $brandAssetConstraint = new File([
            'maxSize' => '2M',
            'mimeTypes' => [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/svg+xml',
                'image/x-icon',
            ],
            'mimeTypesMessage' => 'Por favor sube una imagen válida (JPG, PNG, WEBP, SVG, ICO)',
        ]);
        $socialAssetConstraint = new File([
            'maxSize' => '5M',
            'mimeTypes' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'mimeTypesMessage' => 'Sube una imagen social válida (JPG, PNG o WEBP).',
        ]);

        $builder
            // General Branding
            ->add('app_name', TextType::class, [
                'label' => 'Nombre de la aplicación',
                'constraints' => [new NotBlank(), new Length(max: 80)],
            ])
            ->add('theme_color', ColorType::class, [
                'label' => 'Color principal',
                'constraints' => [new Regex('/^#[0-9a-fA-F]{6}$/')],
            ])
            ->add('default_meta_title', TextType::class, [
                'label' => 'Título SEO por defecto',
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ])
            ->add('default_meta_description', TextareaType::class, [
                'label' => 'Descripción SEO por defecto',
                'constraints' => [new NotBlank(), new Length(max: 220)],
            ])

            // Logos & Icons
            ->add('logo_horizontal', FileType::class, [
                'label' => 'Logo Horizontal (Preferible SVG o WebP)',
                'mapped' => false,
                'required' => false,
                'constraints' => [$brandAssetConstraint],
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp,.svg'],
            ])
            ->add('logo_square', FileType::class, [
                'label' => 'Logo Cuadrado',
                'mapped' => false,
                'required' => false,
                'constraints' => [$brandAssetConstraint],
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp,.svg'],
            ])
            ->add('favicon', FileType::class, [
                'label' => 'Favicon (.ico, .png, .svg)',
                'mapped' => false,
                'required' => false,
                'constraints' => [$brandAssetConstraint],
                'attr' => ['accept' => '.ico,.png,.svg'],
            ])

            // Default OG
            ->add('default_og_title', TextType::class, ['label' => 'Título Open Graph', 'required' => false, 'constraints' => [new Length(max: 120)]])
            ->add('default_og_description', TextareaType::class, ['label' => 'Descripción Open Graph', 'required' => false, 'constraints' => [new Length(max: 220)]])
            ->add('default_og_image', FileType::class, [
                'label' => 'Default OG Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$socialAssetConstraint],
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp'],
            ])

            // Twitter
            ->add('twitter_card_type', ChoiceType::class, [
                'label' => 'Tipo de tarjeta X/Twitter',
                'choices'  => [
                    'Summary Large Image' => 'summary_large_image',
                    'Summary' => 'summary',
                ],
            ])
            ->add('twitter_title', TextType::class, ['label' => 'Título X/Twitter', 'required' => false, 'constraints' => [new Length(max: 120)]])
            ->add('twitter_description', TextareaType::class, ['label' => 'Descripción X/Twitter', 'required' => false, 'constraints' => [new Length(max: 220)]])
            ->add('twitter_image', FileType::class, [
                'label' => 'Twitter Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$socialAssetConstraint],
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp'],
            ])

            // Facebook
            ->add('facebook_title', TextType::class, ['label' => 'Título Facebook', 'required' => false, 'constraints' => [new Length(max: 120)]])
            ->add('facebook_description', TextareaType::class, ['label' => 'Descripción Facebook', 'required' => false, 'constraints' => [new Length(max: 220)]])
            ->add('facebook_image', FileType::class, [
                'label' => 'Facebook Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$socialAssetConstraint],
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp'],
            ])

            // Threads
            ->add('threads_title', TextType::class, ['label' => 'Título Threads', 'required' => false, 'constraints' => [new Length(max: 120)]])
            ->add('threads_description', TextareaType::class, ['label' => 'Descripción Threads', 'required' => false, 'constraints' => [new Length(max: 220)]])
            ->add('threads_image', FileType::class, [
                'label' => 'Threads Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$socialAssetConstraint],
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp'],
            ])

            // Google
            ->add('google_title', TextType::class, ['label' => 'Título Google', 'required' => false, 'constraints' => [new Length(max: 120)]])
            ->add('google_description', TextareaType::class, ['label' => 'Descripción Google', 'required' => false, 'constraints' => [new Length(max: 220)]])
            ->add('google_image', FileType::class, [
                'label' => 'Google Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$socialAssetConstraint],
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Esto no mapea contra una Entidad de Doctrine porque es un JSON en SystemPlugin
            'data_class' => null,
        ]);
    }
}
