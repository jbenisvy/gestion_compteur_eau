<?php

namespace App\Form;

use App\Dto\LotTransferData;
use App\Entity\Coproprietaire;
use App\Entity\Lot;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotTransferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lot', EntityType::class, [
                'class' => Lot::class,
                'label' => 'Lot',
                'placeholder' => 'Sélectionner un lot',
                'choice_label' => static function (Lot $lot): string {
                    return sprintf('Lot %s — %s', $lot->getNumeroLot(), $lot->getEmplacement());
                },
                'query_builder' => static function (\Doctrine\ORM\EntityRepository $er) {
                    return $er->createQueryBuilder('l')
                        ->orderBy('LENGTH(l.numeroLot)', 'ASC')
                        ->addOrderBy('l.numeroLot', 'ASC')
                        ->addOrderBy('l.id', 'ASC');
                },
            ])
            ->add('newCoproprietaire', EntityType::class, [
                'class' => Coproprietaire::class,
                'label' => 'Nouveau copropriétaire',
                'placeholder' => 'Sélectionner un copropriétaire',
                'choice_label' => static function (Coproprietaire $copro): string {
                    return sprintf('%s (%s)', $copro->getNomComplet(), $copro->getEmail());
                },
                'query_builder' => static function (\Doctrine\ORM\EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.nom', 'ASC')
                        ->addOrderBy('c.prenom', 'ASC')
                        ->addOrderBy('c.id', 'ASC');
                },
            ])
            ->add('effectiveDate', DateType::class, [
                'label' => 'Date d’effet',
                'widget' => 'single_text',
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire (optionnel)',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'rows' => 3,
                    'maxlength' => 255,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotTransferData::class,
        ]);
    }
}
