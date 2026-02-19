<?php

namespace App\Form;

use App\Dto\SaisieIndexItem;
use App\Entity\EtatCompteur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class SaisieIndexItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 🛡️ SAFE-GUARD: flag piloté par le contrôleur via SaisieIndexType (année active = éditable)
        $isEditable = (bool)($options['is_editable'] ?? true);

        /** @var SaisieIndexItem|null $dto */
        $dto = $options['data'] ?? null;

        // --- Numéro de compteur (lecture seule, non mappé) ---
        // ⬅️ IMPORTANT : on injecte la valeur depuis le DTO avec 'data'
        $builder->add('compteurNumero', TextType::class, [
            'required' => false,
            'disabled' => !$isEditable,
            'label'    => 'N° compteur (optionnel)',
            'data'     => $dto?->compteurNumero ?? '',
            'attr'     => ['maxlength' => 255],
        ]);

        // --- ID compteur (caché, non mappé) ---
        // ⬅️ On injecte aussi l'ID, utile pour le data-compteur-id côté twig/JS
        $builder->add('compteurId', HiddenType::class, [
            'mapped' => false,
            'data'   => $dto?->compteurId ?? null,
        ]);

        // --- Index N-1 (lecture seule) ---
        $builder->add('indexPrevious', IntegerType::class, [
            'label'    => 'Index N-1',
            'required' => false,
            'disabled' => true,
        ]);

        // --- Index N ---
        $builder->add('indexN', IntegerType::class, [
            'label'    => 'Index N',
            'required' => false,
            'attr'     => ['readonly' => !$isEditable],
        ]);

        // --- État ---
        $builder->add('etat', EntityType::class, [
            'class'        => EtatCompteur::class,
            'choice_label' => 'libelle',
            'label'        => 'État',
            'placeholder'  => 'Sélectionner…',
            'disabled'     => !$isEditable, // figé si non éditable
        ]);

        // --- Forfait (utilisé par ton template) ---
        // 🛡️ SAFE-GUARD: présent pour compat avec le template; sert si l’état choisi est un forfait.
        $builder->add('forfait', IntegerType::class, [
            'label'    => 'Forfait',
            'required' => false,
            'attr'     => [
                'readonly' => !$isEditable,
                'min'      => 0,
                'step'     => 1,
            ],
        ]);

        // --- Zone remplacement: date d'installation ---
        $builder->add('dateInstallation', DateType::class, [
            'label'    => "Date d'installation",
            'required' => false,
            'widget'   => 'single_text', // HTML5
            'attr'     => ['readonly' => !$isEditable],
        ]);

        $builder->add('ancienFonctionnaitEncore', CheckboxType::class, [
            'label'    => 'Ancien compteur encore en service',
            'required' => false,
            'disabled' => !$isEditable,
        ]);

        // --- Index ancien démonté / index nouveau (si remplacement) ---
        $builder->add('indexDemonte', IntegerType::class, [
            'label'    => 'Index ancien (démonté)',
            'required' => false,
            'attr'     => ['readonly' => !$isEditable],
        ]);

        $builder->add('indexNouveau', IntegerType::class, [
            'label'    => 'Index nouveau',
            'required' => false,
            'attr'     => ['readonly' => !$isEditable],
        ]);

        // --- Commentaire ---
        $builder->add('commentaire', TextareaType::class, [
            'label'    => 'Commentaire',
            'required' => false,
            'attr'     => ['rows' => 2, 'readonly' => !$isEditable],
        ]);

        // --- Photo du compteur (non mappé => pas d'impact DB) ---
        $builder->add('photoFile', FileType::class, [
            'label'       => 'Photo du compteur',
            'mapped'      => false,
            'required'    => false,
            'constraints' => [
                // 🛡️ SAFE-GUARD: contraintes larges pour compat mobile
                new Image([
                    'maxSize'        => '10M',
                    'maxSizeMessage' => 'La photo ne doit pas dépasser 10 Mo',
                ]),
            ],
            'attr' => [
                'accept' => 'image/*',
                'class'  => 'photo-input hidden', // déclenché par clic sur le numéro
            ],
        ]);

        $builder->add('removePhoto', CheckboxType::class, [
            'label'    => 'Supprimer la photo',
            'required' => false,
            'disabled' => !$isEditable,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // 🛡️ SAFE-GUARD:
        // - binding sur DTO (pas d'entité Doctrine)
        // - option custom 'is_editable' pour propager l'état lecture/édition
        $resolver->setDefaults([
            'data_class'  => SaisieIndexItem::class,
            'is_editable' => true,
        ]);

        $resolver->setAllowedTypes('is_editable', 'bool');
    }
}
