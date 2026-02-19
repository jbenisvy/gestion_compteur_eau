<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Compteur;
use App\Entity\Coproprietaire;
use App\Entity\EtatCompteur;
use App\Entity\Lot;
use App\Entity\LotCoproprietaire;
use App\Entity\Releve;
use App\Entity\ReleveItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:import:access-export',
    description: 'Importe un export Access (.xlsx) vers user/coproprietaire/lot/lot_coproprietaire/compteur/releve_new/releve_item',
)]
class ImportAccessExportCommand extends Command
{
    private const REQUIRED_HEADERS = [
        'DateFinReleve',
        'NumGroupementLots',
        'NomDuCoproprietaire',
        'Emplacement',
        'EtatCompteurCuisineECN',
        'Compteurs_1.IndexCompteurCuisineECN',
        'TblReleveDesCompteurs.IndexCompteurCuisineECN',
        'ForfaitECCuisine',
        'ConsoECSdbWc',
        'EtatCompteurSdbWcECN',
        'Compteurs_1.IndexCompteurSdbWcECN',
        'TblReleveDesCompteurs.IndexCompteurSdbWcECN',
        'ForfaitECSdbWc',
        'CommentairesCompteursEauChaudeN',
        'AjoutInfoCompteurEauChaude',
        'EtatCompteurCuisineEFN',
        'ConsoEFCuisine',
        'Compteurs_1.IndexCompteurCuisineEFN',
        'TblReleveDesCompteurs.IndexCompteurCuisineEFN',
        'ForfaitEFCuisine',
        'EtatCompteurSdbWcEFN',
        'Compteurs_1.IndexCompteurSdbWcEFN',
        'TblReleveDesCompteurs.IndexCompteurSdbWcEFN',
        'ForfaitEFSdbWc',
        'CommentairesCompteursEauFroideN',
        'AjoutInfoCompteurEauFroide',
        'NomDuLocataire',
    ];

    /** @var array<string,string> */
    private array $generatedEmails = [];

    /** @var array<string, bool> */
    private array $reservedEmails = [];

    /** @var array<string, User> */
    private array $usersByEmail = [];

    /** @var array<string, Coproprietaire> */
    private array $coprosByEmail = [];

    /** @var array<string, EtatCompteur> */
    private array $etatByCode = [];

    /** @var array<string, int> */
    private array $stats = [
        'rows_total' => 0,
        'rows_skipped' => 0,
        'users_created' => 0,
        'copros_created' => 0,
        'lots_created' => 0,
        'lots_updated' => 0,
        'lot_links_created' => 0,
        'compteurs_created' => 0,
        'releves_created' => 0,
        'releves_updated' => 0,
        'items_created' => 0,
        'items_updated' => 0,
        'etats_created' => 0,
    ];

    /**
     * @var array<string, array<int, Coproprietaire>>
     * key = lotNumero
     */
    private array $lotOwnerByYear = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier .xlsx')
            ->addOption('sheet', null, InputOption::VALUE_REQUIRED, 'Nom de l\'onglet (par défaut: actif)', null)
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Applique l\'import en base (sinon dry-run)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        $sheetName = $input->getOption('sheet');
        $apply = (bool) $input->getOption('apply');

        if (!is_file($file)) {
            $output->writeln("<error>Fichier introuvable: {$file}</error>");
            return Command::FAILURE;
        }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $sheetName ? $spreadsheet->getSheetByName((string) $sheetName) : $spreadsheet->getActiveSheet();

            if ($sheet === null) {
                throw new \RuntimeException(sprintf('Onglet "%s" introuvable.', (string) $sheetName));
            }

            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();
            $headerRow = $sheet->rangeToArray("A1:{$highestCol}1", null, true, true, true)[1] ?? [];

            $headers = [];
            foreach ($headerRow as $col => $value) {
                $headers[trim((string) $value)] = $col;
            }

            foreach (self::REQUIRED_HEADERS as $required) {
                if (!isset($headers[$required])) {
                    throw new \RuntimeException(sprintf('Colonne manquante: %s', $required));
                }
            }

            for ($row = 2; $row <= $highestRow; $row++) {
                $this->stats['rows_total']++;

                $annee = $this->asInt($this->getCellValue($sheet, $headers['DateFinReleve'], $row));
                $lotNumero = $this->normalizeKey($this->getCellValue($sheet, $headers['NumGroupementLots'], $row));

                if ($annee === null || $lotNumero === '') {
                    $this->stats['rows_skipped']++;
                    continue;
                }

                $coproNomRaw = trim($this->getCellValue($sheet, $headers['NomDuCoproprietaire'], $row));
                $lotEmplacement = trim($this->getCellValue($sheet, $headers['Emplacement'], $row));
                $occupant = trim($this->getCellValue($sheet, $headers['NomDuLocataire'], $row));

                $copro = $this->findOrCreateCoproprietaire($coproNomRaw);
                $lot = $this->findOrCreateLot($lotNumero, $lotEmplacement, $occupant, $copro);
                $this->lotOwnerByYear[$lotNumero][$annee] = $copro;

                $createdAt = new \DateTimeImmutable(sprintf('%d-09-15 10:00:00', $annee));
                $releve = $this->em->getRepository(Releve::class)->findOneBy([
                    'lot' => $lot,
                    'annee' => $annee,
                ]);

                if (!$releve) {
                    $releve = (new Releve())
                        ->setLot($lot)
                        ->setAnnee($annee)
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($createdAt)
                        ->setVerrouille(false);
                    $this->em->persist($releve);
                    $this->stats['releves_created']++;
                } else {
                    $releve->setUpdatedAt(new \DateTimeImmutable());
                    $this->stats['releves_updated']++;
                }

                $ecComment = $this->joinComment(
                    $this->getCellValue($sheet, $headers['CommentairesCompteursEauChaudeN'], $row),
                    $this->getCellValue($sheet, $headers['AjoutInfoCompteurEauChaude'], $row),
                );

                $efComment = $this->joinComment(
                    $this->getCellValue($sheet, $headers['CommentairesCompteursEauFroideN'], $row),
                    $this->getCellValue($sheet, $headers['AjoutInfoCompteurEauFroide'], $row),
                );

                $slots = [
                    [
                        'type' => 'EC',
                        'emplacement' => 'Cuisine',
                        'etat' => $this->getCellValue($sheet, $headers['EtatCompteurCuisineECN'], $row),
                        'index_n1' => $this->asInt($this->getCellValue($sheet, $headers['Compteurs_1.IndexCompteurCuisineECN'], $row)),
                        'index_n' => $this->asInt($this->getCellValue($sheet, $headers['TblReleveDesCompteurs.IndexCompteurCuisineECN'], $row)),
                        'forfait' => $this->asFloat($this->getCellValue($sheet, $headers['ForfaitECCuisine'], $row)),
                        'consommation' => null,
                        'commentaire' => $ecComment,
                    ],
                    [
                        'type' => 'EC',
                        'emplacement' => 'Salle de bain',
                        'etat' => $this->getCellValue($sheet, $headers['EtatCompteurSdbWcECN'], $row),
                        'index_n1' => $this->asInt($this->getCellValue($sheet, $headers['Compteurs_1.IndexCompteurSdbWcECN'], $row)),
                        'index_n' => $this->asInt($this->getCellValue($sheet, $headers['TblReleveDesCompteurs.IndexCompteurSdbWcECN'], $row)),
                        'forfait' => $this->asFloat($this->getCellValue($sheet, $headers['ForfaitECSdbWc'], $row)),
                        'consommation' => $this->asFloat($this->getCellValue($sheet, $headers['ConsoECSdbWc'], $row)),
                        'commentaire' => $ecComment,
                    ],
                    [
                        'type' => 'EF',
                        'emplacement' => 'Cuisine',
                        'etat' => $this->getCellValue($sheet, $headers['EtatCompteurCuisineEFN'], $row),
                        'index_n1' => $this->asInt($this->getCellValue($sheet, $headers['Compteurs_1.IndexCompteurCuisineEFN'], $row)),
                        'index_n' => $this->asInt($this->getCellValue($sheet, $headers['TblReleveDesCompteurs.IndexCompteurCuisineEFN'], $row)),
                        'forfait' => $this->asFloat($this->getCellValue($sheet, $headers['ForfaitEFCuisine'], $row)),
                        'consommation' => $this->asFloat($this->getCellValue($sheet, $headers['ConsoEFCuisine'], $row)),
                        'commentaire' => $efComment,
                    ],
                    [
                        'type' => 'EF',
                        'emplacement' => 'Salle de bain',
                        'etat' => $this->getCellValue($sheet, $headers['EtatCompteurSdbWcEFN'], $row),
                        'index_n1' => $this->asInt($this->getCellValue($sheet, $headers['Compteurs_1.IndexCompteurSdbWcEFN'], $row)),
                        'index_n' => $this->asInt($this->getCellValue($sheet, $headers['TblReleveDesCompteurs.IndexCompteurSdbWcEFN'], $row)),
                        'forfait' => $this->asFloat($this->getCellValue($sheet, $headers['ForfaitEFSdbWc'], $row)),
                        'consommation' => null,
                        'commentaire' => $efComment,
                    ],
                ];

                foreach ($slots as $slot) {
                    $this->upsertReleveItem($lot, $releve, $annee, $slot);
                }

                if ($row % 25 === 0) {
                    $this->em->flush();
                }
            }

            $this->syncLotHistory();
            $this->em->flush();

            if (!$apply) {
                $conn->rollBack();
                $output->writeln('<info>Dry-run terminé: aucune donnée écrite.</info>');
            } else {
                $conn->commit();
                $output->writeln('<info>Import appliqué avec succès.</info>');
            }

            $this->printSummary($output);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }

            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function upsertReleveItem(Lot $lot, Releve $releve, int $annee, array $slot): void
    {
        $compteur = $this->findOrCreateCompteur(
            $lot,
            $slot['type'],
            $slot['emplacement'],
            $slot['etat'],
            $annee,
        );

        $item = $this->em->getRepository(ReleveItem::class)->findOneBy([
            'releve' => $releve,
            'compteur' => $compteur,
        ]);

        $isNew = false;
        if (!$item) {
            $item = (new ReleveItem())
                ->setReleve($releve)
                ->setCompteur($compteur);
            $this->em->persist($item);
            $isNew = true;
        }

        [$etatCode, $etatLabel] = $this->mapEtat($slot['etat']);
        $etat = $this->findOrCreateEtat($etatCode, $etatLabel);

        $indexN1 = $slot['index_n1'];
        $indexN = $slot['index_n'];

        $forfait = ($slot['forfait'] ?? 0.0) > 0.0 || $etatCode === 'forfait';

        $consommation = $slot['consommation'];
        if ($consommation === null && $indexN !== null && $indexN1 !== null) {
            $consommation = max(0, $indexN - $indexN1);
        }

        if (in_array($etatCode, ['remplace', 'remplace_sans_date'], true) && $indexN1 !== null && $indexN !== null) {
            $item->setIndexCompteurDemonté($indexN1);
            $item->setIndexNouveauCompteur($indexN);
        }

        $item
            ->setIndexN1($indexN1)
            ->setIndexN($indexN)
            ->setEtatId($etat->getId())
            ->setForfait($forfait)
            ->setCommentaire($slot['commentaire'] !== '' ? $slot['commentaire'] : null)
            ->setConsommation($consommation !== null ? number_format($consommation, 3, '.', '') : null)
            ->setUpdatedAt(new \DateTimeImmutable());

        if ($isNew) {
            $this->stats['items_created']++;
        } else {
            $this->stats['items_updated']++;
        }
    }

    private function findOrCreateCompteur(Lot $lot, string $type, string $emplacement, string $etatRaw, int $annee): Compteur
    {
        $compteur = $this->em->getRepository(Compteur::class)->findOneBy([
            'lot' => $lot,
            'type' => strtoupper($type),
            'emplacement' => $emplacement,
        ]);

        [$etatCode, $etatLabel] = $this->mapEtat($etatRaw);
        $etat = $this->findOrCreateEtat($etatCode, $etatLabel);

        if (!$compteur) {
            $emplacementCode = strtoupper(substr($this->slugify($emplacement), 0, 3));
            $compteur = (new Compteur())
                ->setLot($lot)
                ->setType($type)
                ->setEmplacement($emplacement)
                ->setEtatCompteur($etat)
                ->setActif($etatCode !== 'supprime')
                ->setDateInstallation(new \DateTimeImmutable(sprintf('%d-01-01', $annee)))
                ->setNumeroSerie(sprintf('IMP-%s-%s-%s', $lot->getNumeroLot(), $emplacementCode ?: 'GEN', strtoupper($type)));
            $this->em->persist($compteur);
            $this->stats['compteurs_created']++;
        } else {
            $compteur
                ->setEtatCompteur($etat)
                ->setActif($etatCode !== 'supprime');
        }

        return $compteur;
    }

    private function findOrCreateLot(string $numeroLot, string $emplacement, string $occupant, Coproprietaire $copro): Lot
    {
        $lot = $this->em->getRepository(Lot::class)->findOneBy(['numeroLot' => $numeroLot]);
        $resolvedType = $this->resolveTypeAppartement($emplacement);

        if (!$lot) {
            $lot = (new Lot())
                ->setNumeroLot($numeroLot)
                ->setEmplacement($emplacement !== '' ? $emplacement : 'Non spécifié')
                ->setTypeAppartement($resolvedType)
                ->setTantieme(0)
                ->setOccupant($occupant !== '' ? $occupant : 'Non précisé')
                ->setCoproprietaire($copro);
            $this->em->persist($lot);
            $this->stats['lots_created']++;
        } else {
            $updated = false;
            if ($emplacement !== '' && $lot->getEmplacement() !== $emplacement) {
                $lot->setEmplacement($emplacement);
                $updated = true;
            }
            if ($lot->getTypeAppartement() !== $resolvedType) {
                $lot->setTypeAppartement($resolvedType);
                $updated = true;
            }
            if ($occupant !== '' && $lot->getOccupant() !== $occupant) {
                $lot->setOccupant($occupant);
                $updated = true;
            }
            if ($lot->getCoproprietaire()?->getId() !== $copro->getId()) {
                $lot->setCoproprietaire($copro);
                $updated = true;
            }
            if ($updated) {
                $this->stats['lots_updated']++;
            }
        }

        return $lot;
    }

    private function resolveTypeAppartement(string $emplacement): string
    {
        $value = mb_strtolower($emplacement);

        if (str_contains($value, 'couloir face')) {
            return '5 pièces';
        }
        if (str_contains($value, 'couloir gauche')) {
            return '3 pièces';
        }
        if (str_contains($value, 'coursive 1')) {
            return '2 pièces ou studio';
        }
        if (str_contains($value, 'coursive 2')) {
            return '4 pièces';
        }
        if (preg_match('/(^|\\s)rdc(\\s|$)/u', $value) === 1) {
            return 'Bureaux';
        }

        return 'Non précisé';
    }

    private function findOrCreateCoproprietaire(string $nomRaw): Coproprietaire
    {
        $nom = $nomRaw !== '' ? mb_strtoupper($nomRaw) : 'NOM INCONNU';
        $email = $this->emailForName($nom);

        if (isset($this->coprosByEmail[$email])) {
            return $this->coprosByEmail[$email];
        }

        $copro = $this->em->getRepository(Coproprietaire::class)->findOneBy(['email' => $email]);
        if ($copro) {
            $this->coprosByEmail[$email] = $copro;
            return $copro;
        }

        if (isset($this->usersByEmail[$email])) {
            $user = $this->usersByEmail[$email];
        } else {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = (new User())
                ->setEmail($email)
                ->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'ChangeMe123!'));
            $this->em->persist($user);
            $this->stats['users_created']++;
        }
        $this->usersByEmail[$email] = $user;
        }

        $parts = preg_split('/\s+/', trim($nom)) ?: [];
        $prenom = count($parts) > 1 ? $parts[0] : 'N/A';
        $nomFamille = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $nom;

        $copro = (new Coproprietaire())
            ->setNom($nomFamille)
            ->setPrenom($prenom)
            ->setEmail($email)
            ->setTelephone('Non communiqué')
            ->setUser($user);

        $this->em->persist($copro);
        $this->stats['copros_created']++;
        $this->coprosByEmail[$email] = $copro;

        return $copro;
    }

    private function syncLotHistory(): void
    {
        $lotRepo = $this->em->getRepository(Lot::class);
        $linkRepo = $this->em->getRepository(LotCoproprietaire::class);

        foreach ($this->lotOwnerByYear as $lotNumero => $ownersByYear) {
            ksort($ownersByYear);
            $lot = $lotRepo->findOneBy(['numeroLot' => $lotNumero]);
            if (!$lot) {
                continue;
            }

            $segments = [];
            $currentStart = null;
            $currentOwner = null;
            $lastYear = null;

            foreach ($ownersByYear as $year => $owner) {
                if ($currentOwner === null) {
                    $currentOwner = $owner;
                    $currentStart = (int) $year;
                    $lastYear = (int) $year;
                    continue;
                }

                if ($owner->getId() === $currentOwner->getId()) {
                    $lastYear = (int) $year;
                    continue;
                }

                $segments[] = [$currentStart, $lastYear, $currentOwner];
                $currentStart = (int) $year;
                $lastYear = (int) $year;
                $currentOwner = $owner;
            }

            if ($currentOwner !== null && $currentStart !== null && $lastYear !== null) {
                $segments[] = [$currentStart, $lastYear, $currentOwner];
            }

            foreach ($segments as [$startYear, $endYear, $owner]) {
                $startDate = new \DateTimeImmutable(sprintf('%d-01-01', $startYear));
                $endDate = $endYear !== null ? new \DateTimeImmutable(sprintf('%d-12-31', $endYear)) : null;

                $existing = $linkRepo->findOneBy([
                    'lot' => $lot,
                    'coproprietaire' => $owner,
                    'dateDebut' => $startDate,
                ]);

                if ($existing) {
                    $existing->setDateFin($endDate)->setIsPrincipal(true);
                    continue;
                }

                $link = (new LotCoproprietaire())
                    ->setLot($lot)
                    ->setCoproprietaire($owner)
                    ->setDateDebut($startDate)
                    ->setDateFin($endDate)
                    ->setIsPrincipal(true)
                    ->setCommentaire('Import Access');

                $this->em->persist($link);
                $this->stats['lot_links_created']++;
            }
        }
    }

    private function findOrCreateEtat(string $code, string $libelle): EtatCompteur
    {
        if (isset($this->etatByCode[$code])) {
            return $this->etatByCode[$code];
        }

        $etat = $this->em->getRepository(EtatCompteur::class)->findOneBy(['code' => $code]);
        if (!$etat) {
            $etat = (new EtatCompteur())
                ->setCode($code)
                ->setLibelle($libelle);
            $this->em->persist($etat);
            $this->stats['etats_created']++;
        } elseif ($libelle !== '' && $etat->getLibelle() !== $libelle) {
            $etat->setLibelle($libelle);
        }

        $this->em->flush(); // garantit un ID disponible pour releve_item.etat_id
        $this->etatByCode[$code] = $etat;

        return $etat;
    }

    private function mapEtat(string $raw): array
    {
        $value = trim($raw);
        if ($value === '') {
            return ['actif', 'En fonctionnement'];
        }

        if (preg_match('/^(\d+)\s*(.*)$/u', $value, $m)) {
            $num = (int) $m[1];
            $txt = trim($m[2]);

            return match ($num) {
                1 => ['actif', 'En fonctionnement'],
                2 => ['remplace', $txt !== '' ? $txt : 'Nouveau compteur'],
                3 => ['remplace_sans_date', $txt !== '' ? $txt : 'Nouveau compteur sans date'],
                4 => ['bloque', $txt !== '' ? $txt : 'Compteur bloqué'],
                5 => ['inoccupe', $txt !== '' ? $txt : 'Appartement inoccupé'],
                6 => ['supprime', $txt !== '' ? $txt : 'Suppression définitive'],
                7 => ['forfait', $txt !== '' ? $txt : 'Index non communiqué'],
                10 => ['aucune_conso', $txt !== '' ? $txt : 'Aucune consommation'],
                default => ['legacy_' . $num, $value],
            };
        }

        $slug = $this->slugify($value);
        return [$slug !== '' ? $slug : 'actif', $value];
    }

    private function emailForName(string $nom): string
    {
        if (isset($this->generatedEmails[$nom])) {
            return $this->generatedEmails[$nom];
        }

        $base = $this->slugify($nom);
        if ($base === '') {
            $base = 'copro';
        }

        $email = $base . '@import.local';
        $i = 1;
        while (
            isset($this->reservedEmails[$email]) ||
            $this->em->getRepository(User::class)->findOneBy(['email' => $email]) !== null
        ) {
            $i++;
            $email = sprintf('%s%d@import.local', $base, $i);
        }

        $this->generatedEmails[$nom] = $email;
        $this->reservedEmails[$email] = true;

        return $email;
    }

    private function getCellValue($sheet, string $col, int $row): string
    {
        return trim((string) $sheet->getCell("{$col}{$row}")->getFormattedValue());
    }

    private function asInt(string $value): ?int
    {
        $v = $this->normalizeNumeric($value);
        if ($v === null) {
            return null;
        }

        return (int) round($v);
    }

    private function asFloat(string $value): ?float
    {
        return $this->normalizeNumeric($value);
    }

    private function normalizeNumeric(string $value): ?float
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        $v = str_replace([' ', ','], ['', '.'], $v);
        if (!is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    private function normalizeKey(string $value): string
    {
        $intVal = $this->asInt($value);
        if ($intVal !== null) {
            return (string) $intVal;
        }

        return trim($value);
    }

    private function joinComment(string ...$parts): string
    {
        $clean = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $clean[] = $p;
            }
        }

        return implode(' | ', array_unique($clean));
    }

    private function slugify(string $value): string
    {
        $v = strtolower(trim($value));
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
        $v = preg_replace('/[^a-z0-9]+/', '.', $v) ?? '';
        $v = trim($v, '.');

        return $v;
    }

    private function printSummary(OutputInterface $output): void
    {
        $output->writeln(sprintf('Lignes traitées: %d (ignorées: %d)', $this->stats['rows_total'], $this->stats['rows_skipped']));
        $output->writeln(sprintf('Etats créés: %d', $this->stats['etats_created']));
        $output->writeln(sprintf('Users créés: %d | Copropriétaires créés: %d', $this->stats['users_created'], $this->stats['copros_created']));
        $output->writeln(sprintf('Lots créés: %d | Lots mis à jour: %d | Liens lot/copro créés: %d', $this->stats['lots_created'], $this->stats['lots_updated'], $this->stats['lot_links_created']));
        $output->writeln(sprintf('Compteurs créés: %d', $this->stats['compteurs_created']));
        $output->writeln(sprintf('Relevés créés: %d | Relevés mis à jour: %d', $this->stats['releves_created'], $this->stats['releves_updated']));
        $output->writeln(sprintf('Items créés: %d | Items mis à jour: %d', $this->stats['items_created'], $this->stats['items_updated']));
    }
}
