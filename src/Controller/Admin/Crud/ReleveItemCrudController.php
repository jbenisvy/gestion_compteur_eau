<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Compteur;
use App\Entity\EtatCompteur;
use App\Entity\Releve;
use App\Entity\ReleveItem;
use App\Domain\Consommation\ForfaitConsommationResolver;
use App\Repository\ParametreRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(
        private readonly ParametreRepository $parametreRepository,
        private readonly ForfaitConsommationResolver $forfaitResolver,
    ) {
    }

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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof ReleveItem) {
            $this->refreshComputedFields($entityManager, $entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof ReleveItem) {
            $this->refreshComputedFields($entityManager, $entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function refreshComputedFields(EntityManagerInterface $entityManager, ReleveItem $item): void
    {
        $item->setForfait(false);

        $etatCode = $this->resolveEtatCode($entityManager, $item->getEtatId());
        $compteur = $item->getCompteur();
        if (!$compteur instanceof Compteur) {
            $item->setConsommation(null);
            $item->setUpdatedAt(new \DateTimeImmutable());

            return;
        }

        $annee = $item->getReleve()?->getAnnee();
        $forfaits = is_int($annee) ? $this->parametreRepository->getForfaitsForYear($annee) : ['ef' => 0.0, 'ec' => 0.0];
        $lotCompteurs = [];
        if ($compteur->getLot() !== null) {
            $lotCompteurs = $entityManager->getRepository(Compteur::class)->findBy(['lot' => $compteur->getLot()]);
        }

        $prev = (int)($item->getIndexN1() ?? 0);
        $indexN = $item->getIndexN();
        $indexDemonte = $item->getIndexCompteurDemonté();
        $indexNouveau = $item->getIndexNouveauCompteur();

        $isForfaitLike = $etatCode !== null && (
            str_contains($etatCode, 'forfait')
            || str_contains($etatCode, 'bloqu')
            || str_contains($etatCode, 'non communiqu')
            || str_contains($etatCode, 'index compteur non')
        );
        $isNouveauCompteur = $etatCode !== null && str_contains($etatCode, 'nouveau');
        $isRemplacement = $etatCode !== null && (
            str_contains($etatCode, 'remplac')
            || str_contains($etatCode, 'démont')
            || str_contains($etatCode, 'demonte')
        );

        if ($etatCode !== null && str_contains($etatCode, 'suppr')) {
            $cons = 0;
        } elseif ($isForfaitLike) {
            $cons = (int) round($this->forfaitResolver->resolveForCompteur($compteur, $forfaits, $lotCompteurs));
            $item->setForfait(true);
        } elseif ($isNouveauCompteur) {
            $ancienActif = (int)($indexDemonte ?? 0) > $prev;
            $cons = $ancienActif
                ? max(0, (int)($indexDemonte ?? 0) - $prev) + max(0, (int)($indexNouveau ?? $indexN ?? 0))
                : max(0, (int)($indexNouveau ?? $indexN ?? 0));
        } elseif ($isRemplacement) {
            $cons = max(0, (int)($indexDemonte ?? 0) - $prev) + max(0, (int)($indexNouveau ?? $indexN ?? 0));
        } else {
            $curr = (int)($indexN ?? 0);
            $cons = $curr < $prev ? max(0, $curr) : max(0, $curr - $prev);
        }

        $item->setConsommation(number_format($cons, 3, '.', ''));
        $item->setUpdatedAt(new \DateTimeImmutable());
    }

    private function resolveEtatCode(EntityManagerInterface $entityManager, ?int $etatId): ?string
    {
        if ($etatId === null) {
            return null;
        }

        $etat = $entityManager->getRepository(EtatCompteur::class)->find($etatId);
        if (!$etat instanceof EtatCompteur) {
            return null;
        }

        $merged = trim($etat->getCode() . ' ' . $etat->getLibelle());

        return $merged !== '' ? mb_strtolower($merged) : null;
    }
}
