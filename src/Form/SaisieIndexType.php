<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SaisieIndexType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // ⚠️ SAFE-GUARD: ce flag est piloté par le contrôleur (année active ou pas).
        // Ne jamais déverrouiller sans vérifier les règles métier.
        $isEditable = (bool)($options['is_editable'] ?? true);

        $builder->add('items', CollectionType::class, [
            'entry_type'    => SaisieIndexItemType::class,
            'entry_options' => [
                'label'       => false,
                // ⬇️ on propage le flag à chaque sous-formulaire (SaisieIndexItemType)
                'is_editable' => $isEditable,
            ],
            'allow_add'     => false,  // ⚠️ SAFE-GUARD: pas de création dynamique
            'allow_delete'  => false,  // ⚠️ SAFE-GUARD: pas de suppression dynamique
            'by_reference'  => false,
            'label'         => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // ⚠️ SAFE-GUARD: on bind un tableau ['items' => …] (pas d'entité Doctrine directe)
        $resolver->setDefaults([
            'data_class'  => null,
            // ⬇️ option custom utilisée pour geler les champs si année non active
            'is_editable' => true,
        ]);

        // On documente l’option custom pour éviter les notices PHP
        $resolver->setAllowedTypes('is_editable', 'bool');
    }
}
