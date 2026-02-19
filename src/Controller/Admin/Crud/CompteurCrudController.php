<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Compteur;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CompteurCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Compteur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['id' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('lot');
        yield TextField::new('type');
        yield TextField::new('emplacement');
        yield TextField::new('numeroSerie');
        yield BooleanField::new('actif');
        yield TextField::new('photo')->onlyOnIndex();
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $alias = $qb->getRootAliases()[0] ?? 'entity';

        $qb->leftJoin(sprintf('%s.lot', $alias), 'l')
            ->addSelect('l')
            ->resetDQLPart('orderBy')
            ->addOrderBy('LENGTH(l.numeroLot)', 'ASC')
            ->addOrderBy('l.numeroLot', 'ASC')
            ->addOrderBy(sprintf('%s.emplacement', $alias), 'ASC')
            ->addOrderBy(sprintf('%s.type', $alias), 'ASC')
            ->addOrderBy(sprintf('%s.id', $alias), 'ASC');

        return $qb;
    }
}
