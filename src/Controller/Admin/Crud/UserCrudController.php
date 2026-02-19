<?php

namespace App\Controller\Admin\Crud;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email');
        yield ChoiceField::new('roles')
            ->setChoices([
                'Utilisateur copropriétaire (ROLE_USER)' => 'ROLE_USER',
                'Administrateur (ROLE_ADMIN)' => 'ROLE_ADMIN',
            ])
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setRequired(true)
            ->setHelp('Sélectionnez les rôles à attribuer. Un utilisateur peut être à la fois copropriétaire et administrateur.');
        yield TextField::new('password')->onlyOnForms();
    }
}
