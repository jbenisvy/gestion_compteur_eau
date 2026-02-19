<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Compteur;
use App\Entity\Releve;
use App\Entity\ReleveItem;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class ReleveItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ReleveItem::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('releve')
            ->setFormTypeOption('query_builder', static function (EntityRepository $er) {
                return $er->createQueryBuilder('r')
                    ->leftJoin('r.lot', 'l')->addSelect('l')
                    ->orderBy('LENGTH(l.numeroLot)', 'ASC')
                    ->addOrderBy('l.numeroLot', 'ASC')
                    ->addOrderBy('r.annee', 'ASC')
                    ->addOrderBy('r.id', 'ASC');
            });
        yield TextField::new('releve.lot.numeroLot', 'Lot')->onlyOnIndex();
        yield IntegerField::new('releve.annee', 'Année')->onlyOnIndex();
        yield AssociationField::new('compteur')
            ->setFormTypeOption('query_builder', static function (EntityRepository $er) {
                return $er->createQueryBuilder('c')
                    ->leftJoin('c.lot', 'l')->addSelect('l')
                    ->orderBy('LENGTH(l.numeroLot)', 'ASC')
                    ->addOrderBy('l.numeroLot', 'ASC')
                    ->addOrderBy('c.emplacement', 'ASC')
                    ->addOrderBy('c.type', 'ASC')
                    ->addOrderBy('c.id', 'ASC');
            });
        yield IntegerField::new('indexN1');
        yield IntegerField::new('indexN');
        yield IntegerField::new('indexCompteurDemonte', 'Index ancien (démonté)')
            ->setFormTypeOption('property_path', 'indexCompteurDemonté')
            ->onlyOnForms();
        yield IntegerField::new('indexNouveauCompteur')->onlyOnForms();
        yield TextField::new('numeroCompteur', 'N° compteur')->onlyOnIndex();
        yield IntegerField::new('etatId');
        yield BooleanField::new('forfait');
        yield TextField::new('commentaire')->onlyOnForms();
        yield TextField::new('consommation')->onlyOnIndex();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields([
            'id',
            'indexN1',
            'indexN',
            'indexNouveauCompteur',
            'numeroCompteur',
            'etatId',
            'commentaire',
            'consommation',
        ]);
    }

    public function configureFilters(\EasyCorp\Bundle\EasyAdminBundle\Config\Filters $filters): \EasyCorp\Bundle\EasyAdminBundle\Config\Filters
    {
        return $filters
            ->add(
                EntityFilter::new('releve')
                    ->setFormTypeOption('value_type_options.class', Releve::class)
                    ->setFormTypeOption('value_type_options.query_builder', static function (EntityRepository $er) {
                        return $er->createQueryBuilder('r')
                            ->leftJoin('r.lot', 'l')->addSelect('l')
                            ->orderBy('LENGTH(l.numeroLot)', 'ASC')
                            ->addOrderBy('l.numeroLot', 'ASC')
                            ->addOrderBy('r.annee', 'ASC')
                            ->addOrderBy('r.id', 'ASC');
                    })
            )
            ->add(
                EntityFilter::new('compteur')
                    ->setFormTypeOption('value_type_options.class', Compteur::class)
                    ->setFormTypeOption('value_type_options.query_builder', static function (EntityRepository $er) {
                        return $er->createQueryBuilder('c')
                            ->leftJoin('c.lot', 'l')->addSelect('l')
                            ->orderBy('LENGTH(l.numeroLot)', 'ASC')
                            ->addOrderBy('l.numeroLot', 'ASC')
                            ->addOrderBy('c.emplacement', 'ASC')
                            ->addOrderBy('c.type', 'ASC')
                            ->addOrderBy('c.id', 'ASC');
                    })
            )
            ->add(NumericFilter::new('indexN'))
            ->add(NumericFilter::new('indexN1'));
    }
}
