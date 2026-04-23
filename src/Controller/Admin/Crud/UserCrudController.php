<?php

namespace App\Controller\Admin\Crud;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['email' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email');
        yield BooleanField::new('isVerified', 'Email vérifié')
            ->setHelp('Cochez cette case pour permettre directement la connexion par lien sans étape de vérification.');
        yield ChoiceField::new('roles')
            ->setChoices([
                'Utilisateur copropriétaire (ROLE_USER)' => 'ROLE_USER',
                'Administrateur (ROLE_ADMIN)' => 'ROLE_ADMIN',
            ])
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setRequired(true)
            ->setHelp('Sélectionnez les rôles à attribuer. Un utilisateur peut être à la fois copropriétaire et administrateur.');
        yield TextField::new('plainPassword', 'Mot de passe')
            ->onlyOnForms()
            ->setFormType(PasswordType::class)
            ->setRequired(false)
            ->setHelp('Optionnel pour la connexion par lien. Si renseigné, il sera hashé automatiquement.');
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::persistEntity($entityManager, $entityInstance);
            return;
        }

        $this->hashPlainPassword($entityInstance, true);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        $this->hashPlainPassword($entityInstance, false);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPlainPassword(User $user, bool $isNew): void
    {
        $plainPassword = trim((string) $user->getPlainPassword());
        if ($plainPassword !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $user->eraseCredentials();
            return;
        }

        if ($isNew && $user->getPassword() === '') {
            $randomPassword = bin2hex(random_bytes(24));
            $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));
        }

        $user->eraseCredentials();
    }
}
