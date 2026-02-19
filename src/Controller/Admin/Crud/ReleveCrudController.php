<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Releve;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class ReleveCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Releve::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('lot')
            ->setFormTypeOption('query_builder', static function (EntityRepository $er) {
                return $er->createQueryBuilder('l')
                    ->orderBy('LENGTH(l.numeroLot)', 'ASC')
                    ->addOrderBy('l.numeroLot', 'ASC')
                    ->addOrderBy('l.id', 'ASC');
            });
        yield IntegerField::new('annee');
        yield BooleanField::new('verrouille');
    }
}
