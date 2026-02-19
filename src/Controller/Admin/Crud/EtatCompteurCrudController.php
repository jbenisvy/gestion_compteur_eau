<?php

namespace App\Controller\Admin\Crud;

use App\Entity\EtatCompteur;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EtatCompteurCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EtatCompteur::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code');
        yield TextField::new('libelle');
    }
}
