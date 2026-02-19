<?php
// compteurTest.php
use App\Entity\Compteur;
use App\Entity\EtatCompteur;
use Doctrine\ORM\EntityManagerInterface;

require __DIR__ . '/vendor/autoload.php';

// On récupère le Kernel Symfony pour avoir l'EntityManager
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();

// Création d'un état compteur
$etat = new EtatCompteur();
$etat->setLibelle('Actif');
$em->persist($etat);

// Création d'un compteur
$compteur = new Compteur();
$compteur->setNumeroCompteur('TEST-001');
$compteur->setType('EF'); // Eau froide
$compteur->setEmplacement('Cuisine');
$compteur->setDateInstallation(new \DateTime('2025-08-06'));
$compteur->setEtatCompteur($etat);

$em->persist($compteur);

// Envoi en base
$em->flush();

echo "✅ Compteur inséré avec succès : ID = " . $compteur->getId() . PHP_EOL;
