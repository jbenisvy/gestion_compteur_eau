<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Coproprietaire;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CoproprietaireCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Coproprietaire::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Copropriétaire')
            ->setEntityLabelInPlural('Copropriétaires')
            ->setDefaultSort(['nom' => 'ASC', 'prenom' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('nom');
        yield TextField::new('prenom');
        yield EmailField::new('email');
        yield TextField::new('telephone');
        yield AssociationField::new('user', 'Compte utilisateur')
            ->setHelp('Associez ici le compte User qui permettra au copropriétaire de se connecter.');
    }
}
