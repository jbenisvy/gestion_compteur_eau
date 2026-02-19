<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Crud\CompteurCrudController;
use App\Controller\Admin\Crud\CoproprietaireCrudController;
use App\Controller\Admin\Crud\EtatCompteurCrudController;
use App\Controller\Admin\Crud\LotCrudController;
use App\Controller\Admin\Crud\ReleveCrudController;
use App\Controller\Admin\Crud\ReleveItemCrudController;
use App\Controller\Admin\Crud\UserCrudController;
use App\Entity\Compteur;
use App\Entity\Coproprietaire;
use App\Entity\EtatCompteur;
use App\Entity\Lot;
use App\Entity\Releve;
use App\Entity\ReleveItem;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin/easy', routeName: 'admin_easy')]
#[IsGranted('ROLE_ADMIN')]
class EasyAdminDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/easy_dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Administration copropriété');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Retour admin', 'fa fa-home', 'admin_dashboard');
        yield MenuItem::section('Données');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-user', User::class)->setController(UserCrudController::class);
        yield MenuItem::linkToCrud('Copropriétaires', 'fa fa-users', Coproprietaire::class)->setController(CoproprietaireCrudController::class);
        yield MenuItem::linkToCrud('Lots', 'fa fa-building', Lot::class)->setController(LotCrudController::class);
        yield MenuItem::linkToCrud('Compteurs', 'fa fa-tachometer', Compteur::class)->setController(CompteurCrudController::class);
        yield MenuItem::linkToCrud('États compteur', 'fa fa-tag', EtatCompteur::class)->setController(EtatCompteurCrudController::class);
        yield MenuItem::section('Relevés');
        yield MenuItem::linkToCrud('Relevés (maître)', 'fa fa-list', Releve::class)->setController(ReleveCrudController::class);
        yield MenuItem::linkToCrud('Relevés (détails)', 'fa fa-list-alt', ReleveItem::class)->setController(ReleveItemCrudController::class);
        yield MenuItem::section('Rapports');
        yield MenuItem::linkToRoute('Tableau global', 'fa fa-table', 'admin_tableau');
        yield MenuItem::linkToRoute('Référentiel compteurs', 'fa fa-hashtag', 'admin_compteurs_reference');
        yield MenuItem::linkToRoute('Historique global', 'fa fa-chart-line', 'admin_historique');
        yield MenuItem::linkToRoute('Paramètres forfaits', 'fa fa-sliders', 'admin_parametres');
        yield MenuItem::linkToRoute('Transférer un lot', 'fa fa-exchange-alt', 'admin_lot_transfer');
        yield MenuItem::section('Aide');
        yield MenuItem::linkToRoute('Didacticiels', 'fa fa-book', 'guides_index');
        yield MenuItem::linkToRoute('Guide Admin', 'fa fa-graduation-cap', 'guide_admin');
    }
}
