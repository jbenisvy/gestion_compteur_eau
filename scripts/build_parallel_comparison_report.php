<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

const OUTPUT_FILE = __DIR__ . '/../exports/comparaison_cote_a_cote_2023_2025.xlsx';
const OUTPUT_SIGNIFICANT_FILE = __DIR__ . '/../exports/comparaison_ecarts_significatifs_2023_2025.xlsx';
const OUTPUT_OWNER_VISUAL_FILE = __DIR__ . '/../exports/comparaison_visuelle_par_coproprietaire_2023_2025.xlsx';
const OUTPUT_LOT_VISUAL_FILE = __DIR__ . '/../exports/comparaison_visuelle_par_lot_2023_2025.xlsx';

$pricingByYear = [
    2023 => [
        'forfait_ec' => 35.0,
        'forfait_ef' => 40.0,
        'price_m3_ef' => 5.05,
        'price_energy' => 21.12,
        'price_m3_ec' => 26.17,
    ],
    2024 => [
        'forfait_ec' => 75.0,
        'forfait_ef' => 150.0,
        'price_m3_ef' => 5.15,
        'price_energy' => 19.77,
        'price_m3_ec' => 24.92,
    ],
    2025 => [
        'forfait_ec' => 75.0,
        'forfait_ef' => 150.0,
        'price_m3_ef' => 5.10,
        'price_energy' => 17.96,
        'price_m3_ec' => 23.06,
    ],
];

$myWorkbookPath = '/home/johny/Documents/Analyse relevés de compteurs avec Croisement des informations/Comparaison année par année lot par lot avec mes relevés les relevés de GM GESTIMMO.xlsx';
$fallbackWorkbookPath = '/home/johny/Documents/Analyse relevés de compteurs avec Croisement des informations/Remise en forme des relevés CM GESTITIMMO.xlsx';
$syndicFiles = [
    2023 => '/home/johny/Téléchargements/ReleveCompteursXls (3).xls EF EC 2023.xls',
    2024 => '/home/johny/Téléchargements/ReleveCompteursXls (4).xls 2024.xls',
    2025 => '/home/johny/Téléchargements/ReleveCompteursXls (5).xls 2025 (1).xls',
];

function normalizeText(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    return trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', (string) $value)) ?? '');
}

function asFloat(mixed $value): float
{
    $text = normalizeText($value);
    if ($text === '') {
        return 0.0;
    }

    $text = str_replace([' ', ','], ['', '.'], $text);

    return is_numeric($text) ? (float) $text : 0.0;
}

function normalizeLot(mixed $value): string
{
    $text = normalizeText($value);
    if ($text === '') {
        return '';
    }

    if (preg_match('/(\d{3})/', $text, $match)) {
        return $match[1];
    }

    return $text;
}

function normalizeNature(mixed $value): string
{
    $text = strtoupper(normalizeText($value));
    if (str_contains($text, 'CHAUDE') || $text === 'EC') {
        return 'EC';
    }
    if (str_contains($text, 'FROIDE') || $text === 'EF') {
        return 'EF';
    }

    return $text;
}

function normalizeEmplacement(mixed $value): string
{
    $text = strtoupper(normalizeText($value));
    if ($text === '') {
        return '';
    }
    if (str_contains($text, 'CUI')) {
        return 'Cuisine';
    }
    if (str_contains($text, 'SDB') || str_contains($text, 'SALLE') || str_contains($text, 'WC')) {
        return 'Salle de bain';
    }

    return normalizeText($value);
}

function containsFlag(string $haystack, array $patterns): bool
{
    $haystack = mb_strtolower(normalizeText($haystack));
    foreach ($patterns as $pattern) {
        if (str_contains($haystack, mb_strtolower($pattern))) {
            return true;
        }
    }

    return false;
}

function amountFor(string $nature, float $volume, array $pricing): float
{
    if ($nature === 'EC') {
        return $volume * (($pricing['price_m3_ec'] ?? 0.0) + ($pricing['price_energy'] ?? 0.0));
    }

    return $volume * ($pricing['price_m3_ef'] ?? 0.0);
}

function sheetRowsByHeader(Worksheet $sheet): array
{
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $header = $sheet->rangeToArray("A1:{$highestCol}1", null, true, false)[0];
    $map = [];

    foreach ($header as $index => $name) {
        $label = normalizeText($name);
        if ($label !== '') {
            $map[$label] = Coordinate::stringFromColumnIndex($index + 1);
        }
    }

    return [$map, $highestRow];
}

function cellValue(Worksheet $sheet, array $headerMap, string $header, int $row): string
{
    if (!isset($headerMap[$header])) {
        return '';
    }

    return normalizeText($sheet->getCell($headerMap[$header] . $row)->getFormattedValue());
}

function parseMyRows(Worksheet $sheet, int $expectedYear, array $pricing, string $sourceLabel): array
{
    [$headerMap, $highestRow] = sheetRowsByHeader($sheet);
    $rows = [];

    $yearHeader = isset($headerMap['Annee']) ? 'Annee' : 'C';

    for ($row = 2; $row <= $highestRow; $row++) {
        $lot = normalizeLot(cellValue($sheet, $headerMap, 'Lot', $row));
        if ($lot === '') {
            continue;
        }

        $rawYear = cellValue($sheet, $headerMap, 'Annee', $row);
        $year = (int) ($rawYear !== '' ? $rawYear : $expectedYear);
        if ($year === 0) {
            $year = $expectedYear;
        }

        $nature = normalizeNature(cellValue($sheet, $headerMap, 'Nature', $row));
        $emplacement = normalizeEmplacement(cellValue($sheet, $headerMap, 'Emplacement', $row));
        $typeConso = normalizeText(cellValue($sheet, $headerMap, 'Type conso', $row));
        $forfaitValue = asFloat(cellValue($sheet, $headerMap, 'Valeur forfait', $row));
        $consumption = asFloat(cellValue($sheet, $headerMap, 'Consommation', $row));

        if ($forfaitValue <= 0.0 && $typeConso !== '' && containsFlag($typeConso, ['forfait'])) {
            $forfaitValue = $nature === 'EC'
                ? (float) ($pricing['forfait_ec'] ?? 0.0)
                : (float) ($pricing['forfait_ef'] ?? 0.0);
        }

        $retainedVolume = $forfaitValue > 0.0 ? $forfaitValue : $consumption;
        $status = normalizeText(cellValue($sheet, $headerMap, 'Statut', $row));
        $motifForfait = normalizeText(cellValue($sheet, $headerMap, 'Motif forfait', $row));
        $comment = normalizeText(cellValue($sheet, $headerMap, 'Commentaire', $row));
        $lotInoccupe = normalizeText(cellValue($sheet, $headerMap, 'Lot inoccupe', $row));
        $motifInoccupe = normalizeText(cellValue($sheet, $headerMap, 'Motif inoccupe', $row));

        $notes = [];
        if (containsFlag($status, ['supprime'])) {
            $notes[] = 'compteur supprimé';
        }
        if ($lotInoccupe !== '' || $motifInoccupe !== '') {
            $notes[] = 'appartement inoccupé';
        }
        if (containsFlag($motifForfait . ' ' . $comment, ['non communiqué'])) {
            $notes[] = 'compteur non communiqué';
        }
        if (containsFlag($motifForfait . ' ' . $comment, ['bloque', 'bloqué'])) {
            $notes[] = 'compteur bloqué';
        }
        if (containsFlag($motifForfait . ' ' . $comment, ['changé', 'remplac', 'new compteur'])) {
            $notes[] = 'remplacement de compteur';
        }
        if (containsFlag($comment, ['plus de compteur', 'plus de compteurs'])) {
            $notes[] = 'compteur supprimé';
        }

        $rows[] = [
            'year' => $year,
            'lot' => $lot,
            'description' => normalizeText(cellValue($sheet, $headerMap, 'Description lot', $row)),
            'owner' => normalizeText(cellValue($sheet, $headerMap, 'Proprietaire', $row)),
            'tenant' => normalizeText(cellValue($sheet, $headerMap, 'Locataire', $row)),
            'reference' => normalizeText(cellValue($sheet, $headerMap, 'Reference', $row)),
            'meter_id' => normalizeText(cellValue($sheet, $headerMap, 'Compteur ID', $row)),
            'nature' => $nature,
            'emplacement' => $emplacement,
            'status' => $status,
            'index_n_1' => asFloat(cellValue($sheet, $headerMap, 'Index N-1', $row)),
            'index_n' => asFloat(cellValue($sheet, $headerMap, 'Index N', $row)),
            'consumption' => $consumption,
            'type_conso' => $typeConso,
            'forfait_volume' => $forfaitValue,
            'retained_volume' => $retainedVolume,
            'amount' => amountFor($nature, $retainedVolume, $pricing),
            'motif_forfait' => $motifForfait,
            'comment' => $comment,
            'lot_inoccupe' => $lotInoccupe,
            'motif_inoccupe' => $motifInoccupe,
            'notes' => array_values(array_unique($notes)),
            'source' => $sourceLabel,
        ];
    }

    return $rows;
}

function parseSyndicRows(Worksheet $sheet, int $year, array $pricing): array
{
    $highestRow = $sheet->getHighestRow();
    $rows = [];
    $sectionNature = '';

    for ($row = 2; $row <= $highestRow; $row++) {
        $owner = normalizeText($sheet->getCell("A{$row}")->getFormattedValue());
        $label = normalizeText($sheet->getCell("B{$row}")->getFormattedValue());
        $lotRaw = normalizeText($sheet->getCell("C{$row}")->getFormattedValue());
        $estimated = normalizeText($sheet->getCell("H{$row}")->getFormattedValue());
        $consumption = asFloat($sheet->getCell("I{$row}")->getCalculatedValue());

        if (str_starts_with(mb_strtolower($owner), 'compteurs :')) {
            $sectionNature = normalizeNature($owner);
            continue;
        }

        if ($owner === '' && $label === '' && $lotRaw === '') {
            continue;
        }

        $lot = normalizeLot($lotRaw);
        if ($lot === '') {
            $lot = normalizeLot($label);
        }
        if ($lot === '') {
            $lot = normalizeLot($owner);
        }
        if ($lot === '') {
            continue;
        }

        $nature = $sectionNature;
        if ($nature === '') {
            $nature = normalizeNature($label . ' ' . $owner);
        }

        $emplacement = normalizeEmplacement($label);
        $forfaitVolume = 0.0;
        $retainedVolume = $consumption;
        $notes = [];
        if ($estimated !== '') {
            $notes[] = 'relevé syndic estimé';
            $forfaitVolume = $nature === 'EC'
                ? (float) ($pricing['forfait_ec'] ?? 0.0)
                : (float) ($pricing['forfait_ef'] ?? 0.0);
            $retainedVolume = $forfaitVolume;
            $notes[] = 'forfait appliqué côté syndic';
        }
        if (containsFlag($label . ' ' . $owner, ['new compteur', 'nouveau compteur'])) {
            $notes[] = 'remplacement de compteur côté syndic';
        }
        if ($consumption < 0) {
            $notes[] = 'consommation négative côté syndic';
        }

        $rows[] = [
            'year' => $year,
            'lot' => $lot,
            'owner' => $owner,
            'reference' => $label,
            'nature' => $nature,
            'emplacement' => $emplacement,
            'old_index' => asFloat($sheet->getCell("E{$row}")->getCalculatedValue()),
            'new_index' => asFloat($sheet->getCell("G{$row}")->getCalculatedValue()),
            'consumption' => $consumption,
            'forfait_volume' => $forfaitVolume,
            'retained_volume' => $retainedVolume,
            'amount' => amountFor($nature, $retainedVolume, $pricing),
            'estimated' => $estimated !== '',
            'notes' => array_values(array_unique($notes)),
            'raw_label' => $label,
        ];
    }

    return $rows;
}

function composeKey(array $row): string
{
    return implode('|', [
        $row['year'],
        $row['lot'],
        $row['nature'],
        $row['emplacement'],
    ]);
}

function sortDetailRows(array &$rows): void
{
    $emplacementOrder = ['Cuisine' => 1, 'Salle de bain' => 2];
    $natureOrder = ['EC' => 1, 'EF' => 2];

    usort($rows, static function (array $left, array $right) use ($emplacementOrder, $natureOrder): int {
        return [$left['year'], (int) $left['lot'], $natureOrder[$left['nature']] ?? 9, $emplacementOrder[$left['emplacement']] ?? 9]
            <=>
            [$right['year'], (int) $right['lot'], $natureOrder[$right['nature']] ?? 9, $emplacementOrder[$right['emplacement']] ?? 9];
    });
}

function autosizeSheet(Worksheet $sheet, int $columnCount): void
{
    for ($index = 1; $index <= $columnCount; $index++) {
        $column = Coordinate::stringFromColumnIndex($index);
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
}

function styleTable(Worksheet $sheet, string $range): void
{
    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $sheet->getStyle($range)->getAlignment()->setWrapText(true);
}

function buildWorkbook(
    array $pricingByYear,
    array $sourceNotes,
    array $summaryRows,
    array $detailsByYear,
    string $titleLine,
    string $subtitleLine
): Spreadsheet {
    $spreadsheet = new Spreadsheet();
    $summarySheet = $spreadsheet->getActiveSheet();
    $summarySheet->setTitle('Synthese');

    $summarySheet->fromArray([
        [$titleLine],
        [$subtitleLine],
        ['Règle de calcul retenue pour la facturation: EF = m3 x prix m3 EF ; EC = m3 x (prix m3 EC + prix Energy).'],
        [],
        ['Barème utilisé'],
        ['Année', 'Forfait EC', 'Forfait EF', 'Prix m3 EF', 'Prix Energy', 'Prix m3 EC'],
    ], null, 'A1');

    $barRow = 7;
    foreach ($pricingByYear as $year => $pricing) {
        $summarySheet->fromArray([[
            $year,
            $pricing['forfait_ec'],
            $pricing['forfait_ef'],
            $pricing['price_m3_ef'],
            $pricing['price_energy'],
            $pricing['price_m3_ec'],
        ]], null, "A{$barRow}");
        $barRow++;
    }

    $tableStart = $barRow + 2;
    $summaryHeaders = [
        'Année',
        'Lot',
        'Description lot',
        'Copropriétaire',
        'Volume retenu moi (m3)',
        'Volume retenu syndic (m3)',
        'Écart volume (m3)',
        'Montant moi (€)',
        'Montant syndic (€)',
        'Écart montant (€)',
        'Nb lignes avec forfait',
        'Commentaires / spécificités',
        'Source utilisée',
    ];
    $summarySheet->fromArray([$summaryHeaders], null, "A{$tableStart}");
    $summarySheet->fromArray($summaryRows, null, 'A' . ($tableStart + 1));

    $summaryEndRow = $tableStart + max(count($summaryRows), 1);
    $summarySheet->getStyle("A{$tableStart}:M{$tableStart}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1F4E78']],
    ]);
    styleTable($summarySheet, "A{$tableStart}:M{$summaryEndRow}");
    $summarySheet->freezePane("A{$tableStart}");

    foreach (['E', 'F', 'G', 'H', 'I', 'J'] as $column) {
        $summarySheet->getStyle("{$column}" . ($tableStart + 1) . ":{$column}{$summaryEndRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');
    }

    $detailHeaders = [
        'Lot',
        'Description lot',
        'Copropriétaire',
        'Type eau',
        'Emplacement',
        'Référence / compteur moi',
        'Statut moi',
        'Type conso moi',
        'Volume retenu moi (m3)',
        'Montant moi (€)',
        'Forfait appliqué moi (m3)',
        'Index N-1 moi',
        'Index N moi',
        'Référence / libellé syndic',
        'Volume retenu syndic (m3)',
        'Montant syndic (€)',
        'Relevé syndic estimé',
        'Ancien index syndic',
        'Nouvel index syndic',
        'Écart volume (m3)',
        'Écart montant (€)',
        'Commentaires / spécificités',
    ];

    $sheetIndex = 1;
    foreach ([2023, 2024, 2025] as $year) {
        $sheet = $spreadsheet->createSheet($sheetIndex++);
        $sheet->setTitle((string) $year);
        $sheet->fromArray([
            ["Comparatif détaillé {$year}"],
            [$sourceNotes[$year] ?? ''],
            [],
            $detailHeaders,
        ], null, 'A1');

        $rows = [];
        foreach ($detailsByYear[$year] as $row) {
            $rows[] = [
                $row['lot'],
                $row['description'],
                $row['owner'],
                $row['nature'],
                $row['emplacement'],
                $row['my_reference'],
                $row['my_status'],
                $row['my_type_conso'],
                $row['my_volume'],
                $row['my_amount'],
                $row['my_forfait'],
                $row['my_index_prev'],
                $row['my_index_new'],
                $row['syndic_reference'],
                $row['syndic_volume'],
                $row['syndic_amount'],
                $row['syndic_estimated'],
                $row['syndic_index_prev'],
                $row['syndic_index_new'],
                $row['delta_volume'],
                $row['delta_amount'],
                $row['notes'],
            ];
        }

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A5');
        }

        $lastRow = 4 + max(count($rows), 1);

        $sheet->getStyle('A4:V4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4F81BD']],
        ]);

        styleTable($sheet, "A4:V{$lastRow}");
        $sheet->freezePane('A5');

        foreach (['I', 'J', 'K', 'L', 'M', 'O', 'P', 'R', 'S', 'T', 'U'] as $column) {
            $sheet->getStyle("{$column}5:{$column}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        autosizeSheet($sheet, 22);
        $sheet->getStyle('A1:A2')->getFont()->setBold(true);
    }

    autosizeSheet($summarySheet, 13);
    $summarySheet->getStyle('A1:A5')->getFont()->setBold(true);

    return $spreadsheet;
}

function sanitizeSheetTitle(string $title): string
{
    $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $title) ?? $title;
    $title = trim($title);

    return $title === '' ? 'Sans nom' : $title;
}

function uniqueSheetTitle(string $title, array &$usedTitles): string
{
    $base = mb_substr(sanitizeSheetTitle($title), 0, 31);
    $candidate = $base;
    $index = 2;

    while (isset($usedTitles[$candidate])) {
        $suffix = ' ' . $index;
        $candidate = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
        $index++;
    }

    $usedTitles[$candidate] = true;

    return $candidate;
}

function sortMeterRows(array &$rows): void
{
    $natureOrder = ['EC' => 1, 'EF' => 2];
    $emplacementOrder = ['Cuisine' => 1, 'Salle de bain' => 2];

    usort($rows, static function (array $left, array $right) use ($natureOrder, $emplacementOrder): int {
        return [
            (int) ($left['lot'] ?? 0),
            $natureOrder[$left['nature'] ?? ''] ?? 9,
            $emplacementOrder[$left['emplacement'] ?? ''] ?? 9,
            normalizeText(($left['reference'] ?? '') . ' ' . ($left['meter_id'] ?? '')),
        ] <=> [
            (int) ($right['lot'] ?? 0),
            $natureOrder[$right['nature'] ?? ''] ?? 9,
            $emplacementOrder[$right['emplacement'] ?? ''] ?? 9,
            normalizeText(($right['reference'] ?? '') . ' ' . ($right['meter_id'] ?? '')),
        ];
    });
}

function moneyFormat(Worksheet $sheet, string $range): void
{
    $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
}

function applySectionBox(Worksheet $sheet, int $startRow, int $endRow): void
{
    $sheet->getStyle("A{$startRow}:J{$endRow}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
}

function writeVisualPartySection(
    Worksheet $sheet,
    int $startRow,
    string $title,
    array $rows,
    string $party,
    string $owner,
    string $lot,
    string $description
): int {
    $sheet->setCellValue("A{$startRow}", $title);
    $sheet->mergeCells("A{$startRow}:J{$startRow}");
    $sheet->getStyle("A{$startRow}:J{$startRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $party === 'my' ? '4472C4' : '70AD47']],
    ]);
    $sheet->getRowDimension($startRow)->setRowHeight(22);

    $row = $startRow + 1;
    $headers = ['Compteur', 'Référence', 'Nature', 'Emplacement', 'Index N-1', 'Index N', 'Consommation', 'Forfait', 'Montant facturé', 'Commentaires'];
    $sheet->fromArray([$headers], null, "A{$row}");
    $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $party === 'my' ? 'D9E2F3' : 'E2F0D9']],
    ]);
    $headerRow = $row;
    $row++;

    $dataStart = $row;
    $volumeTotal = 0.0;
    $amountTotal = 0.0;
    $natureTotals = ['EC' => 0.0, 'EF' => 0.0];
    $locationTotals = ['Cuisine' => 0.0, 'Salle de bain' => 0.0];

    if ($rows === []) {
        $message = $party === 'my' ? 'Aucun relevé dans ma version pour ce lot / cette année.' : 'Aucun relevé dans la version syndic pour ce lot / cette année.';
        $sheet->fromArray([['', '', '', '', '', '', '', '', '', $message]], null, "A{$row}");
        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFF2CC']],
            'font' => ['italic' => true],
        ]);
        $row++;
    } else {
        sortMeterRows($rows);

        foreach ($rows as $item) {
            if ($party === 'my') {
                $comments = $item['notes'] ?? [];
                foreach ([$item['motif_forfait'] ?? '', $item['comment'] ?? '', $item['motif_inoccupe'] ?? ''] as $text) {
                    $text = normalizeText($text);
                    if ($text !== '' && !in_array($text, $comments, true)) {
                        $comments[] = $text;
                    }
                }

                $forfaitLabel = ($item['forfait_volume'] ?? 0.0) > 0 ? 'Oui (' . $item['forfait_volume'] . ' m3)' : 'Non';
                $sheet->fromArray([[
                    $item['meter_id'] ?? '',
                    $item['reference'] ?? '',
                    $item['nature'] ?? '',
                    $item['emplacement'] ?? '',
                    (float) ($item['index_n_1'] ?? 0.0),
                    (float) ($item['index_n'] ?? 0.0),
                    (float) ($item['retained_volume'] ?? 0.0),
                    $forfaitLabel,
                    (float) ($item['amount'] ?? 0.0),
                    implode(' | ', array_values(array_unique(array_filter(array_map('normalizeText', $comments))))),
                ]], null, "A{$row}");

                $volume = (float) ($item['retained_volume'] ?? 0.0);
                $amount = (float) ($item['amount'] ?? 0.0);
                $nature = $item['nature'] ?? '';
                $emplacement = $item['emplacement'] ?? '';
            } else {
                $comments = $item['notes'] ?? [];
                $sheet->fromArray([[
                    '',
                    $item['reference'] ?? '',
                    $item['nature'] ?? '',
                    $item['emplacement'] ?? '',
                    (float) ($item['old_index'] ?? 0.0),
                    (float) ($item['new_index'] ?? 0.0),
                    (float) ($item['retained_volume'] ?? 0.0),
                    ($item['forfait_volume'] ?? 0.0) > 0 ? 'Oui (' . $item['forfait_volume'] . ' m3)' : 'Non',
                    (float) ($item['amount'] ?? 0.0),
                    implode(' | ', array_values(array_unique(array_filter(array_map('normalizeText', $comments))))),
                ]], null, "A{$row}");

                $volume = (float) ($item['retained_volume'] ?? 0.0);
                $amount = (float) ($item['amount'] ?? 0.0);
                $nature = $item['nature'] ?? '';
                $emplacement = $item['emplacement'] ?? '';
            }

            $commentText = mb_strtolower((string) $sheet->getCell("J{$row}")->getValue());
            if (str_contains($commentText, 'absent') || str_contains($commentText, 'présent seulement')) {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
            } elseif (str_contains($commentText, 'supprim') || str_contains($commentText, 'non communiqué') || str_contains($commentText, 'bloqué') || str_contains($commentText, 'inoccupé') || str_contains($commentText, 'estimé') || str_contains($commentText, 'remplacement')) {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
            }

            $volumeTotal += $volume;
            $amountTotal += $amount;
            if (isset($natureTotals[$nature])) {
                $natureTotals[$nature] += $volume;
            }
            if (isset($locationTotals[$emplacement])) {
                $locationTotals[$emplacement] += $volume;
            }

            $row++;
        }
    }

    $lastDataRow = $row - 1;
    styleTable($sheet, "A{$headerRow}:J{$lastDataRow}");
    moneyFormat($sheet, "E{$dataStart}:G{$lastDataRow}");
    moneyFormat($sheet, "I{$dataStart}:I{$lastDataRow}");

    $sheet->setCellValue("A{$row}", "Total lot");
    $sheet->setCellValue("G{$row}", $volumeTotal);
    $sheet->setCellValue("I{$row}", $amountTotal);
    $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F2F2F2']],
    ]);
    styleTable($sheet, "A{$row}:J{$row}");
    moneyFormat($sheet, "G{$row}:I{$row}");
    $sheet->getStyle("A{$row}:J{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
    $row++;

    $sheet->fromArray([[
        'Total EC',
        $natureTotals['EC'],
        'Total EF',
        $natureTotals['EF'],
        'Total Cuisine',
        $locationTotals['Cuisine'],
        'Total SDB',
        $locationTotals['Salle de bain'],
        '',
        '',
    ]], null, "A{$row}");
    $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F4F1E8']],
    ]);
    styleTable($sheet, "A{$row}:J{$row}");
    moneyFormat($sheet, "B{$row}:H{$row}");
    applySectionBox($sheet, $startRow, $row);

    return $row + 2;
}

function buildOwnerVisualWorkbook(
    array $pricingByYear,
    array $sourceNotes,
    array $myRowsByYear,
    array $syndicRowsByYear
): Spreadsheet {
    $spreadsheet = new Spreadsheet();
    $indexSheet = $spreadsheet->getActiveSheet();
    $indexSheet->setTitle('Index');
    $indexSheet->fromArray([
        ['Comparatif visuel par copropriétaire'],
        ['Un onglet par copropriétaire, avec pour chaque année : Ma version puis Version syndic.'],
        ['Périmètre: travail parallèle hors application, uniquement à partir des fichiers Excel fournis.'],
        [],
        ['Onglet', 'Copropriétaire', 'Lots concernés'],
    ], null, 'A1');
    $indexSheet->getStyle('A1:A3')->getFont()->setBold(true);
    $indexSheet->getStyle('A5:C5')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1F4E78']],
    ]);

    $lotMeta = [];
    foreach ($myRowsByYear as $yearRows) {
        foreach ($yearRows as $row) {
            $lot = $row['lot'];
            if (!isset($lotMeta[$lot])) {
                $lotMeta[$lot] = [
                    'owner' => $row['owner'] ?: '',
                    'description' => $row['description'] ?: '',
                ];
            } else {
                if ($lotMeta[$lot]['owner'] === '' && ($row['owner'] ?? '') !== '') {
                    $lotMeta[$lot]['owner'] = $row['owner'];
                }
                if ($lotMeta[$lot]['description'] === '' && ($row['description'] ?? '') !== '') {
                    $lotMeta[$lot]['description'] = $row['description'];
                }
            }
        }
    }

    $owners = [];
    foreach ([2023, 2024, 2025] as $year) {
        foreach ($myRowsByYear[$year] as $row) {
            $owner = $row['owner'] !== '' ? $row['owner'] : ('Lot ' . $row['lot']);
            $owners[$owner]['display_name'] = $owner;
            $owners[$owner]['lots'][$row['lot']] = $lotMeta[$row['lot']]['description'] ?? ($row['description'] ?? '');
            $owners[$owner]['years'][$year]['my'][$row['lot']][] = $row;
        }

        foreach ($syndicRowsByYear[$year] as $row) {
            $owner = $lotMeta[$row['lot']]['owner'] ?? ($row['owner'] !== '' ? $row['owner'] : ('Lot ' . $row['lot']));
            $owners[$owner]['display_name'] = $owner;
            $owners[$owner]['lots'][$row['lot']] = $lotMeta[$row['lot']]['description'] ?? '';
            $owners[$owner]['years'][$year]['syndic'][$row['lot']][] = $row;
        }
    }

    foreach ($owners as $ownerKey => &$ownerData) {
        foreach ([2023, 2024, 2025] as $year) {
            $myLots = $ownerData['years'][$year]['my'] ?? [];
            $syndicLots = $ownerData['years'][$year]['syndic'] ?? [];
            $lots = array_values(array_unique(array_merge(array_keys($myLots), array_keys($syndicLots))));

            foreach ($lots as $lot) {
                $myAmount = 0.0;
                foreach ($myLots[$lot] ?? [] as $row) {
                    $myAmount += (float) ($row['amount'] ?? 0.0);
                }

                $syndicAmount = 0.0;
                foreach ($syndicLots[$lot] ?? [] as $row) {
                    $syndicAmount += (float) ($row['amount'] ?? 0.0);
                }

                if (abs($myAmount - $syndicAmount) < 0.0001) {
                    unset($ownerData['years'][$year]['my'][$lot], $ownerData['years'][$year]['syndic'][$lot]);
                }
            }

            if (($ownerData['years'][$year]['my'] ?? []) === [] && ($ownerData['years'][$year]['syndic'] ?? []) === []) {
                unset($ownerData['years'][$year]);
            }
        }

        if (($ownerData['years'] ?? []) === []) {
            unset($owners[$ownerKey]);
        }
    }
    unset($ownerData);

    ksort($owners, SORT_NATURAL | SORT_FLAG_CASE);
    $usedTitles = ['Index' => true];
    $indexRow = 6;

    foreach ($owners as $ownerName => $ownerData) {
        $sheetTitle = uniqueSheetTitle($ownerName, $usedTitles);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->setCellValue('A1', $ownerData['display_name']);
        $sheet->setCellValue('A2', 'Comparatif visuel de mes relevés puis de la version syndic, sans tableau d’écarts.');
        $sheet->setCellValue('A3', 'Bleu = ma version, vert = version syndic, jaune = point d’attention, saumon = absence d’un côté.');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getStyle('A3')->getFont()->setItalic(true);

        $row = 5;
        foreach ([2023, 2024, 2025] as $year) {
            $sheet->setCellValue("A{$row}", "Année {$year}");
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'C9DAF8']],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(24);
            $row++;

            $sheet->setCellValue("A{$row}", $sourceNotes[$year] ?? '');
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $row += 2;

            $lots = array_values(array_unique(array_merge(
                array_keys($ownerData['years'][$year]['my'] ?? []),
                array_keys($ownerData['years'][$year]['syndic'] ?? [])
            )));
            sort($lots, SORT_NATURAL);

            if ($lots === []) {
                $sheet->setCellValue("A{$row}", "Aucune donnée pour {$year}");
                $sheet->mergeCells("A{$row}:J{$row}");
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                $row += 3;
                continue;
            }

            foreach ($lots as $lot) {
                $description = $ownerData['lots'][$lot] ?? ($lotMeta[$lot]['description'] ?? '');
                $sheet->setCellValue("A{$row}", "Lot {$lot}" . ($description !== '' ? " - {$description}" : '') . " | {$ownerData['display_name']}");
                $sheet->mergeCells("A{$row}:J{$row}");
                $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'EDEDED']],
                ]);
                $sheet->getStyle("A{$row}:J{$row}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
                $sheet->getRowDimension($row)->setRowHeight(22);
                $row++;

                $row = writeVisualPartySection(
                    $sheet,
                    $row,
                    'Ma version',
                    $ownerData['years'][$year]['my'][$lot] ?? [],
                    'my',
                    $ownerData['display_name'],
                    (string) $lot,
                    $description
                );

                $row = writeVisualPartySection(
                    $sheet,
                    $row,
                    'Version syndic',
                    $ownerData['years'][$year]['syndic'][$lot] ?? [],
                    'syndic',
                    $ownerData['display_name'],
                    (string) $lot,
                    $description
                );
            }
        }

        autosizeSheet($sheet, 10);
        foreach ([
            'A' => 14,
            'B' => 18,
            'C' => 10,
            'D' => 16,
            'E' => 11,
            'F' => 11,
            'G' => 13,
            'H' => 16,
            'I' => 16,
            'J' => 55,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A5');

        $lotsLabel = implode(', ', array_map(static fn($lot): string => (string) $lot, array_keys($ownerData['lots'])));
        $indexSheet->fromArray([[$sheetTitle, $ownerData['display_name'], $lotsLabel]], null, "A{$indexRow}");
        $indexRow++;
    }

    autosizeSheet($indexSheet, 3);
    styleTable($indexSheet, 'A5:C' . max($indexRow - 1, 5));

    return $spreadsheet;
}

function buildLotVisualWorkbook(
    array $sourceNotes,
    array $myRowsByYear,
    array $syndicRowsByYear
): Spreadsheet {
    $spreadsheet = new Spreadsheet();
    $indexSheet = $spreadsheet->getActiveSheet();
    $indexSheet->setTitle('Index');
    $indexSheet->fromArray([
        ['Comparatif visuel par lot'],
        ['Un onglet par lot, avec pour chaque année : Ma version puis Version syndic.'],
        ['Le filtrage conserve uniquement les cas avec écart de facturation entre ma version et celle du syndic.'],
        [],
        ['Onglet', 'Lot', 'Copropriétaire', 'Description'],
    ], null, 'A1');
    $indexSheet->getStyle('A1:A3')->getFont()->setBold(true);
    $indexSheet->getStyle('A5:D5')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1F4E78']],
    ]);

    $lots = [];
    foreach ([2023, 2024, 2025] as $year) {
        foreach ($myRowsByYear[$year] as $row) {
            $lot = $row['lot'];
            if (!isset($lots[$lot])) {
                $lots[$lot] = [
                    'lot' => $lot,
                    'owner' => $row['owner'] ?? '',
                    'description' => $row['description'] ?? '',
                    'years' => [],
                ];
            }
            if (($lots[$lot]['owner'] ?? '') === '' && ($row['owner'] ?? '') !== '') {
                $lots[$lot]['owner'] = $row['owner'];
            }
            if (($lots[$lot]['description'] ?? '') === '' && ($row['description'] ?? '') !== '') {
                $lots[$lot]['description'] = $row['description'];
            }
            $lots[$lot]['years'][$year]['my'][] = $row;
        }

        foreach ($syndicRowsByYear[$year] as $row) {
            $lot = $row['lot'];
            if (!isset($lots[$lot])) {
                $lots[$lot] = [
                    'lot' => $lot,
                    'owner' => $row['owner'] ?? '',
                    'description' => '',
                    'years' => [],
                ];
            }
            if (($lots[$lot]['owner'] ?? '') === '' && ($row['owner'] ?? '') !== '') {
                $lots[$lot]['owner'] = $row['owner'];
            }
            $lots[$lot]['years'][$year]['syndic'][] = $row;
        }
    }

    foreach ($lots as $lotKey => &$lotData) {
        foreach ([2023, 2024, 2025] as $year) {
            $myRows = $lotData['years'][$year]['my'] ?? [];
            $syndicRows = $lotData['years'][$year]['syndic'] ?? [];
            $myAmount = array_sum(array_map(static fn(array $row): float => (float) ($row['amount'] ?? 0.0), $myRows));
            $syndicAmount = array_sum(array_map(static fn(array $row): float => (float) ($row['amount'] ?? 0.0), $syndicRows));

            if (abs($myAmount - $syndicAmount) < 0.0001) {
                unset($lotData['years'][$year]);
            }
        }

        if (($lotData['years'] ?? []) === []) {
            unset($lots[$lotKey]);
        }
    }
    unset($lotData);

    ksort($lots, SORT_NATURAL);
    $usedTitles = ['Index' => true];
    $indexRow = 6;

    foreach ($lots as $lot => $lotData) {
        $sheetTitle = uniqueSheetTitle('Lot ' . $lot, $usedTitles);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->setCellValue('A1', 'Lot ' . $lot . ($lotData['description'] !== '' ? ' - ' . $lotData['description'] : ''));
        $sheet->setCellValue('A2', 'Copropriétaire: ' . ($lotData['owner'] !== '' ? $lotData['owner'] : 'Non renseigné'));
        $sheet->setCellValue('A3', 'Comparatif visuel sur la base du numéro de lot. Bleu = ma version, vert = version syndic.');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->setItalic(true);

        $row = 5;
        foreach ([2023, 2024, 2025] as $year) {
            if (!isset($lotData['years'][$year])) {
                continue;
            }

            $sheet->setCellValue("A{$row}", "Année {$year}");
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'C9DAF8']],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(24);
            $row++;

            $sheet->setCellValue("A{$row}", $sourceNotes[$year] ?? '');
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $row += 2;

            $row = writeVisualPartySection(
                $sheet,
                $row,
                'Ma version',
                $lotData['years'][$year]['my'] ?? [],
                'my',
                $lotData['owner'],
                (string) $lot,
                $lotData['description']
            );

            $row = writeVisualPartySection(
                $sheet,
                $row,
                'Version syndic',
                $lotData['years'][$year]['syndic'] ?? [],
                'syndic',
                $lotData['owner'],
                (string) $lot,
                $lotData['description']
            );
        }

        foreach ([
            'A' => 14,
            'B' => 18,
            'C' => 10,
            'D' => 16,
            'E' => 11,
            'F' => 11,
            'G' => 13,
            'H' => 16,
            'I' => 16,
            'J' => 55,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A5');

        $indexSheet->fromArray([[
            $sheetTitle,
            $lot,
            $lotData['owner'],
            $lotData['description'],
        ]], null, "A{$indexRow}");
        $indexRow++;
    }

    autosizeSheet($indexSheet, 4);
    styleTable($indexSheet, 'A5:D' . max($indexRow - 1, 5));

    return $spreadsheet;
}

$myReader = IOFactory::createReaderForFile($myWorkbookPath);
$myReader->setReadDataOnly(true);
$mySpreadsheet = $myReader->load($myWorkbookPath);

$fallbackReader = IOFactory::createReaderForFile($fallbackWorkbookPath);
$fallbackReader->setReadDataOnly(true);
$fallbackSpreadsheet = $fallbackReader->load($fallbackWorkbookPath);

$myRowsByYear = [];
$sourceNotes = [
    2023 => 'Mes relevés 2023 pris depuis "Remise en forme des relevés CM GESTITIMMO.xlsx" car l’onglet "2023" du fichier de comparaison contient des données 2025.',
    2024 => 'Mes relevés 2024 pris depuis le fichier "Comparaison année par année...".',
    2025 => 'Mes relevés 2025 pris depuis le fichier "Comparaison année par année...".',
];

$myRowsByYear[2023] = parseMyRows(
    $fallbackSpreadsheet->getSheetByName('Mes Relevés 2023'),
    2023,
    $pricingByYear[2023],
    'Mes Relevés 2023 (fallback fiable)'
);
$myRowsByYear[2024] = parseMyRows(
    $mySpreadsheet->getSheetByName('2024'),
    2024,
    $pricingByYear[2024],
    'Comparaison année par année... / onglet 2024'
);
$myRowsByYear[2025] = parseMyRows(
    $mySpreadsheet->getSheetByName('2025'),
    2025,
    $pricingByYear[2025],
    'Comparaison année par année... / onglet 2025'
);

$syndicRowsByYear = [];
foreach ($syndicFiles as $year => $file) {
    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($file);
    $syndicRowsByYear[$year] = parseSyndicRows($spreadsheet->getSheet(0), $year, $pricingByYear[$year]);
}

$detailsByYear = [];
$summaryRows = [];

foreach ([2023, 2024, 2025] as $year) {
    $myRows = $myRowsByYear[$year];
    $syndicRows = $syndicRowsByYear[$year];

    $myByKey = [];
    foreach ($myRows as $row) {
        $myByKey[composeKey($row)][] = $row;
    }

    $syndicByKey = [];
    foreach ($syndicRows as $row) {
        $syndicByKey[composeKey($row)][] = $row;
    }

    $allKeys = array_values(array_unique(array_merge(array_keys($myByKey), array_keys($syndicByKey))));
    $detailRows = [];
    $totalsByLot = [];

    foreach ($allKeys as $key) {
        $myGroup = $myByKey[$key] ?? [];
        $syndicGroup = $syndicByKey[$key] ?? [];
        $sample = $myGroup[0] ?? $syndicGroup[0];
        if ($sample === null) {
            continue;
        }

        $lot = $sample['lot'];
        $owner = $myGroup[0]['owner'] ?? $syndicGroup[0]['owner'] ?? '';
        $description = $myGroup[0]['description'] ?? '';
        $nature = $sample['nature'];
        $emplacement = $sample['emplacement'];

        $myRetained = array_sum(array_column($myGroup, 'retained_volume'));
        $myAmount = array_sum(array_column($myGroup, 'amount'));
        $syndicRetained = array_sum(array_column($syndicGroup, 'retained_volume'));
        $syndicAmount = array_sum(array_column($syndicGroup, 'amount'));

        $myForfait = array_sum(array_column($myGroup, 'forfait_volume'));
        $myIndexPrev = $myGroup[0]['index_n_1'] ?? 0.0;
        $myIndexNew = $myGroup[0]['index_n'] ?? 0.0;
        $syndicIndexPrev = $syndicGroup[0]['old_index'] ?? 0.0;
        $syndicIndexNew = $syndicGroup[0]['new_index'] ?? 0.0;

        $notes = [];
        foreach ($myGroup as $row) {
            $notes = array_merge($notes, $row['notes']);
            foreach ([$row['motif_forfait'], $row['comment'], $row['motif_inoccupe']] as $text) {
                $text = normalizeText($text);
                if ($text !== '' && !in_array($text, $notes, true)) {
                    $notes[] = $text;
                }
            }
        }
        foreach ($syndicGroup as $row) {
            $notes = array_merge($notes, $row['notes']);
        }
        if ($myGroup === []) {
            $notes[] = 'présent seulement côté syndic';
        }
        if ($syndicGroup === []) {
            $notes[] = 'absent du relevé syndic';
        }
        $notes = array_values(array_unique(array_filter(array_map('normalizeText', $notes))));

        $detailRows[] = [
            'year' => $year,
            'lot' => $lot,
            'description' => $description,
            'owner' => $owner,
            'nature' => $nature,
            'emplacement' => $emplacement,
            'my_reference' => implode(' | ', array_values(array_filter(array_map(
                static fn(array $row): string => normalizeText(($row['reference'] ?: $row['meter_id']) ?? ''),
                $myGroup
            )))),
            'my_status' => implode(' | ', array_values(array_unique(array_filter(array_map(
                static fn(array $row): string => normalizeText($row['status'] ?? ''),
                $myGroup
            ))))),
            'my_type_conso' => implode(' | ', array_values(array_unique(array_filter(array_map(
                static fn(array $row): string => normalizeText($row['type_conso'] ?? ''),
                $myGroup
            ))))),
            'my_volume' => $myRetained,
            'my_amount' => $myAmount,
            'my_forfait' => $myForfait,
            'my_index_prev' => $myIndexPrev,
            'my_index_new' => $myIndexNew,
            'syndic_reference' => implode(' | ', array_values(array_filter(array_map(
                static fn(array $row): string => normalizeText($row['reference'] ?? ''),
                $syndicGroup
            )))),
            'syndic_volume' => $syndicRetained,
            'syndic_amount' => $syndicAmount,
            'syndic_estimated' => in_array(true, array_column($syndicGroup, 'estimated'), true) ? 'Oui' : '',
            'syndic_index_prev' => $syndicIndexPrev,
            'syndic_index_new' => $syndicIndexNew,
            'delta_volume' => $syndicRetained - $myRetained,
            'delta_amount' => $syndicAmount - $myAmount,
            'notes' => implode(' | ', $notes),
        ];

        if (!isset($totalsByLot[$lot])) {
            $totalsByLot[$lot] = [
                'year' => $year,
                'lot' => $lot,
                'description' => $description,
                'owner' => $owner,
                'my_volume' => 0.0,
                'syndic_volume' => 0.0,
                'my_amount' => 0.0,
                'syndic_amount' => 0.0,
                'forfait_count' => 0,
                'note_count' => 0,
                'notes' => [],
            ];
        }

        $totalsByLot[$lot]['my_volume'] += $myRetained;
        $totalsByLot[$lot]['syndic_volume'] += $syndicRetained;
        $totalsByLot[$lot]['my_amount'] += $myAmount;
        $totalsByLot[$lot]['syndic_amount'] += $syndicAmount;
        $totalsByLot[$lot]['forfait_count'] += $myForfait > 0.0 ? 1 : 0;
        $totalsByLot[$lot]['notes'] = array_values(array_unique(array_merge(
            $totalsByLot[$lot]['notes'],
            $notes
        )));
    }

    sortDetailRows($detailRows);
    $detailsByYear[$year] = $detailRows;

    usort($totalsByLot, static fn(array $left, array $right): int => (int) $left['lot'] <=> (int) $right['lot']);
    foreach ($totalsByLot as $row) {
        $summaryRows[] = [
            $row['year'],
            $row['lot'],
            $row['description'],
            $row['owner'],
            $row['my_volume'],
            $row['syndic_volume'],
            $row['syndic_volume'] - $row['my_volume'],
            $row['my_amount'],
            $row['syndic_amount'],
            $row['syndic_amount'] - $row['my_amount'],
            $row['forfait_count'],
            implode(' | ', $row['notes']),
            $sourceNotes[$year] ?? '',
        ];
    }
}

$spreadsheet = buildWorkbook(
    $pricingByYear,
    $sourceNotes,
    $summaryRows,
    $detailsByYear,
    'Comparatif année par année de mes relevés vs relevés du syndic',
    'Périmètre: travail parallèle hors application. Sources: fichiers Excel transmis uniquement, sans utilisation de la base locale.'
);

$writer = new Xlsx($spreadsheet);
$writer->save(OUTPUT_FILE);

$significantDetailsByYear = [];
$significantSummaryRows = [];
foreach ([2023, 2024, 2025] as $year) {
    $significantDetailsByYear[$year] = array_values(array_filter(
        $detailsByYear[$year],
        static function (array $row): bool {
            $notes = mb_strtolower($row['notes']);
            return abs((float) $row['delta_volume']) >= 5.0
                || abs((float) $row['delta_amount']) >= 10.0
                || str_contains($notes, 'absent du relevé syndic')
                || str_contains($notes, 'présent seulement côté syndic');
        }
    ));

    $lots = [];
    foreach ($significantDetailsByYear[$year] as $row) {
        $lot = $row['lot'];
        if (!isset($lots[$lot])) {
            $lots[$lot] = [
                'year' => $year,
                'lot' => $row['lot'],
                'description' => $row['description'],
                'owner' => $row['owner'],
                'my_volume' => 0.0,
                'syndic_volume' => 0.0,
                'my_amount' => 0.0,
                'syndic_amount' => 0.0,
                'forfait_count' => 0,
                'notes' => [],
            ];
        }

        $lots[$lot]['my_volume'] += (float) $row['my_volume'];
        $lots[$lot]['syndic_volume'] += (float) $row['syndic_volume'];
        $lots[$lot]['my_amount'] += (float) $row['my_amount'];
        $lots[$lot]['syndic_amount'] += (float) $row['syndic_amount'];
        $lots[$lot]['forfait_count'] += (float) $row['my_forfait'] > 0 ? 1 : 0;
        $lots[$lot]['notes'][] = $row['notes'];
    }

    foreach ($lots as $lotData) {
        $significantSummaryRows[] = [
            $lotData['year'],
            $lotData['lot'],
            $lotData['description'],
            $lotData['owner'],
            $lotData['my_volume'],
            $lotData['syndic_volume'],
            $lotData['syndic_volume'] - $lotData['my_volume'],
            $lotData['my_amount'],
            $lotData['syndic_amount'],
            $lotData['syndic_amount'] - $lotData['my_amount'],
            $lotData['forfait_count'],
            implode(' | ', array_values(array_unique(array_filter(array_map('normalizeText', $lotData['notes']))))),
            ($sourceNotes[$year] ?? '') . ' Filtre: |écart volume| >= 5 m3, ou |écart montant| >= 10 €, ou anomalie structurelle.',
        ];
    }
}

$significantWorkbook = buildWorkbook(
    $pricingByYear,
    $sourceNotes,
    $significantSummaryRows,
    $significantDetailsByYear,
    'Comparatif des écarts significatifs entre mes relevés et ceux du syndic',
    'Filtre retenu: |écart volume| >= 5 m3, ou |écart montant| >= 10 €, ou anomalie structurelle (absence d’un côté).'
);

(new Xlsx($significantWorkbook))->save(OUTPUT_SIGNIFICANT_FILE);

$ownerVisualWorkbook = buildOwnerVisualWorkbook(
    $pricingByYear,
    $sourceNotes,
    $myRowsByYear,
    $syndicRowsByYear
);

(new Xlsx($ownerVisualWorkbook))->save(OUTPUT_OWNER_VISUAL_FILE);

$lotVisualWorkbook = buildLotVisualWorkbook(
    $sourceNotes,
    $myRowsByYear,
    $syndicRowsByYear
);

(new Xlsx($lotVisualWorkbook))->save(OUTPUT_LOT_VISUAL_FILE);

echo OUTPUT_FILE . PHP_EOL;
echo OUTPUT_SIGNIFICANT_FILE . PHP_EOL;
echo OUTPUT_OWNER_VISUAL_FILE . PHP_EOL;
echo OUTPUT_LOT_VISUAL_FILE . PHP_EOL;
