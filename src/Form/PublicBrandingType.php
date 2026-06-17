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

class PublicBrandingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $imageConstraint = new File([
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

        $builder
            // General Branding
            ->add('app_name', TextType::class, ['label' => 'Nombre de la aplicación', 'required' => true])
            ->add('theme_color', ColorType::class, ['label' => 'Color Principal del Tema', 'required' => true])
            ->add('default_meta_title', TextType::class, ['label' => 'Meta Título por defecto', 'required' => true])
            ->add('default_meta_description', TextareaType::class, ['label' => 'Meta Descripción por defecto', 'required' => true])

            // Logos & Icons
            ->add('logo_horizontal', FileType::class, [
                'label' => 'Logo Horizontal (Preferible SVG o WebP)',
                'mapped' => false,
                'required' => false,
                'constraints' => [$imageConstraint],
            ])
            ->add('logo_square', FileType::class, [
                'label' => 'Logo Cuadrado',
                'mapped' => false,
                'required' => false,
                'constraints' => [$imageConstraint],
            ])
            ->add('favicon', FileType::class, [
                'label' => 'Favicon (.ico, .png, .svg)',
                'mapped' => false,
                'required' => false,
                'constraints' => [$imageConstraint],
            ])

            // Default OG
            ->add('default_og_title', TextType::class, ['label' => 'Default OG Title', 'required' => false])
            ->add('default_og_description', TextareaType::class, ['label' => 'Default OG Description', 'required' => false])
            ->add('default_og_image', FileType::class, [
                'label' => 'Default OG Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$imageConstraint],
            ])

            // Twitter
            ->add('twitter_card_type', ChoiceType::class, [
                'label' => 'Twitter Card Type',
                'choices'  => [
                    'Summary Large Image' => 'summary_large_image',
                    'Summary' => 'summary',
                ],
            ])
            ->add('twitter_title', TextType::class, ['label' => 'Twitter Title', 'required' => false])
            ->add('twitter_description', TextareaType::class, ['label' => 'Twitter Description', 'required' => false])
            ->add('twitter_image', FileType::class, [
                'label' => 'Twitter Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$imageConstraint],
            ])

            // Facebook
            ->add('facebook_title', TextType::class, ['label' => 'Facebook Title', 'required' => false])
            ->add('facebook_description', TextareaType::class, ['label' => 'Facebook Description', 'required' => false])
            ->add('facebook_image', FileType::class, [
                'label' => 'Facebook Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$imageConstraint],
            ])

            // Threads
            ->add('threads_title', TextType::class, ['label' => 'Threads Title', 'required' => false])
            ->add('threads_description', TextareaType::class, ['label' => 'Threads Description', 'required' => false])
            ->add('threads_image', FileType::class, [
                'label' => 'Threads Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$imageConstraint],
            ])

            // Google
            ->add('google_title', TextType::class, ['label' => 'Google Title', 'required' => false])
            ->add('google_description', TextareaType::class, ['label' => 'Google Description', 'required' => false])
            ->add('google_image', FileType::class, [
                'label' => 'Google Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [$imageConstraint],
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
