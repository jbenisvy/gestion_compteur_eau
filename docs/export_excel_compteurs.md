# Export JSON/CSV Excel - Compteurs d'eau

## Objectif
Expose un endpoint JSON stable pour Excel (Power Query) et un endpoint CSV pour LibreOffice Calc, sans toucher aux autres onglets.

## Endpoints
JSON (GET):
- `/api/export/excel-compteurs`

CSV (GET):
- `/api/export/excel-compteurs.csv`

Parametres optionnels (JSON et CSV):
- `annee`: filtre une annee precise
- `from`: annee de debut (incluse)
- `to`: annee de fin (incluse)
- `lot_id`: filtre un lot
- `compteur_id`: filtre un compteur
- `token`: cle d'acces si `EXPORT_EXCEL_TOKEN` est configuree

Exemple JSON:
```text
https://votre-domaine.tld/api/export/excel-compteurs?from=2022&to=2025&token=VOTRE_TOKEN
```

Exemple CSV (LibreOffice):
```text
https://votre-domaine.tld/api/export/excel-compteurs.csv?from=2022&to=2025&token=VOTRE_TOKEN
```

## Configuration (optionnelle)
Variables d'environnement:
- `EXPORT_EXCEL_ENABLED` = `1` (actif) ou `0` (desactive)
- `EXPORT_EXCEL_TOKEN` = valeur du token partage (si non defini, l'endpoint est public)

## Structure JSON (resume)
Racine:
- `generated_at` (ISO 8601)
- `source`
- `version`
- `meta`
- `rows`

Chaque element de `rows` contient une ligne exploitable par Excel (flat, sans imbrication), avec:
- donnees lot, coproprietaire, locataire
- donnees compteur
- index N-1, index N, consommation
- forfait applique, motif, valeur
- etats et commentaires

## Power Query (Excel)
1. Ouvrir le fichier Excel existant.
2. Onglet `Donnees` > `Recuperer des donnees` > `A partir d'autres sources` > `A partir du Web`.
3. Coller l'URL JSON (avec `token` si besoin).
4. Dans Power Query, choisir `Convertir en table` sur le champ `rows`.
5. Charger la requete dans l'onglet 1 (ne pas remplacer les autres onglets).
6. Pour actualiser: `Donnees` > `Actualiser tout`.

## LibreOffice Calc (CSV)
1. Ouvrir le fichier Calc existant (ou en creer un nouveau).
2. Menu `Feuille` > `Lier a des donnees externes...`.
3. Coller l'URL CSV (avec `token` si besoin).
4. Cliquer sur `Mettre a jour` pour charger les donnees.
5. Dans `Options`, definir l'intervalle de rafraichissement (ex: 10 minutes) si souhaite.
6. Les autres onglets ne sont pas impactes, seule la plage liee est actualisee.

## Notes
- L'export est en lecture seule.
- Aucun changement n'est applique aux traitements metier existants.
