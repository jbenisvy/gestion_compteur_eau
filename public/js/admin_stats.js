(function () {
  "use strict";

  var table = null;
  var lastData = [];
  var lastPivotData = [];
  var currentGlobalFilter = null;
  var pivotState = null;
  var activeSavedPivot = "";
  var pivotStorageKey = "admin_stats_pivot_presets_v1";
  var savedPivotCache = {};
  var hasRemoteStorage = false;
  var isInitialized = false;
  var pendingInitTimer = null;
  var MAX_ROWS_FOR_PIVOT = 1200;
  var MAX_ROWS_FOR_DETAIL = 800;

  function qs(id) {
    return document.getElementById(id);
  }

  function getStandardFilters() {
    var year = qs("statsYear").value;
    var params = {};
    if (year) {
      params.annee = year;
    }

    params.include_supprime = qs("includeSupprime").checked ? "1" : "0";
    params.include_inactif = qs("includeInactif").checked ? "1" : "0";
    params.include_forfait = qs("includeForfait").checked ? "1" : "0";
    params.include_grise = qs("includeGrise").checked ? "1" : "0";

    return params;
  }

  function getPivotFilters() {
    var years = getSelectedPivotYears();
    var params = {};
    if (years.length === 1) {
      params.annee = years[0];
    } else if (years.length > 1) {
      params.annees = years.join(",");
    }

    params.include_supprime = qs("includeSupprime").checked ? "1" : "0";
    params.include_inactif = qs("includeInactif").checked ? "1" : "0";
    params.include_forfait = qs("includeForfait").checked ? "1" : "0";
    params.include_grise = qs("includeGrise").checked ? "1" : "0";

    return params;
  }

  function buildUrl(base, params) {
    var url = new URL(base, window.location.origin);
    Object.keys(params).forEach(function (k) {
      url.searchParams.set(k, params[k]);
    });
    return url.toString();
  }

  function formatFiltersText(params) {
    var parts = [];
    if (params.annees) {
      parts.push("Annees: " + params.annees);
    } else if (params.annee) {
      parts.push("Annee: " + params.annee);
    } else {
      parts.push("Annees: toutes");
    }
    parts.push("Supprimes: " + (params.include_supprime === "1" ? "oui" : "non"));
    parts.push("Inactifs: " + (params.include_inactif === "1" ? "oui" : "non"));
    parts.push("Forfaits: " + (params.include_forfait === "1" ? "oui" : "non"));
    parts.push("Grises: " + (params.include_grise === "1" ? "oui" : "non"));
    return parts.join(" | ");
  }

  function setPrintMeta(params) {
    var meta = qs("statsPrintMeta");
    if (!meta) return;
    var now = new Date();
    meta.textContent = "Genere le " + now.toLocaleString() + " | " + formatFiltersText(params);
  }

  function fetchData() {
    var params = getStandardFilters();
    setPrintMeta(params);
    var url = buildUrl("/admin/stats/data", params);
    return fetch(url, { headers: { "Accept": "application/json" } })
      .then(function (r) {
        if (!r.ok) throw new Error("Erreur chargement stats");
        return r.json();
      });
  }

  function fetchPivotData() {
    var params = getPivotFilters();
    var url = buildUrl("/admin/stats/data", params);
    return fetch(url, { headers: { "Accept": "application/json" } })
      .then(function (r) {
        if (!r.ok) throw new Error("Erreur chargement pivot");
        return r.json();
      });
  }

  function refreshPivotOnly() {
    var currentPivotConfig = getCurrentPivotState() || buildPivotConfig(getPivotPreset());
    var pivot = qs("statsPivot");
    if (pivot) {
      pivot.innerHTML = "<div class=\"muted\">Chargement de l'analyse croisee…</div>";
    }

    fetchPivotData()
      .then(function (payload) {
        lastPivotData = payload.rows || [];
        renderPivot(lastPivotData, currentPivotConfig);
      })
      .catch(function () {
        alert("Impossible de charger l'analyse croisee.");
      });
  }

  function buildColumns() {
    return [
      { title: "Annee", field: "annee", hozAlign: "left", sorter: "number", headerFilter: "input" },
      { title: "Lot", field: "lot_numero", headerFilter: "input" },
      { title: "Description lot", field: "lot_description", headerFilter: "input" },
      { title: "Lot inoccupe", field: "lot_inoccupe", formatter: "tickCross", hozAlign: "center" },
      { title: "Motif inoccupe", field: "lot_inoccupe_motif", headerFilter: "input" },
      { title: "Proprietaire", field: "proprietaire_nom", headerFilter: "input" },
      { title: "Locataire", field: "locataire_nom", headerFilter: "input" },
      { title: "Compteur ID", field: "compteur_id", sorter: "number", headerFilter: "input" },
      { title: "Reference", field: "compteur_reference", headerFilter: "input" },
      { title: "Nature", field: "compteur_nature", headerFilter: "input" },
      { title: "Emplacement", field: "compteur_emplacement", headerFilter: "input" },
      { title: "Statut", field: "compteur_statut", headerFilter: "input" },
      { title: "Compteur supprime", field: "compteur_supprime", formatter: "tickCross", hozAlign: "center" },
      { title: "Index masque", field: "index_masque", formatter: "tickCross", hozAlign: "center" },
      { title: "Index N-1", field: "index_n_1", sorter: "number", headerFilter: "input" },
      { title: "Index N", field: "index_n", sorter: "number", headerFilter: "input" },
      { title: "Consommation", field: "consommation", sorter: "number", headerFilter: "input", formatter: function (cell) { return formatNumber(cell.getValue()); } },
      { title: "Prix m3 applicable", field: "prix_m3_applicable", sorter: "number", headerFilter: "input", formatter: function (cell) { return formatMoney(cell.getValue()); } },
      { title: "Valorisation EUR", field: "valorisation_eur", sorter: "number", headerFilter: "input", formatter: function (cell) { return formatMoney(cell.getValue()); } },
      { title: "Type conso", field: "consommation_type", headerFilter: "input" },
      { title: "Forfait", field: "forfait_applique", formatter: "tickCross", hozAlign: "center" },
      { title: "Valeur forfait", field: "forfait_valeur", sorter: "number", headerFilter: "input", formatter: function (cell) { return formatNumber(cell.getValue()); } },
      { title: "Motif forfait", field: "forfait_motif", headerFilter: "input" },
      { title: "Commentaire", field: "commentaire", headerFilter: "input" },
      { title: "Emplacement norm", field: "compteur_emplacement_norm", visible: false },
      { title: "Ligne grisee", field: "ligne_grisee", visible: false },
    ];
  }

  function buildColumnToggles(columns) {
    var holder = qs("statsColumns");
    if (!holder) return;
    holder.innerHTML = "";

    var label = document.createElement("div");
    label.className = "muted";
    label.textContent = "Colonnes visibles";
    holder.appendChild(label);

    columns.forEach(function (colDef) {
      if (!colDef.field) return;
      var wrapper = document.createElement("label");
      wrapper.className = "filter-check";

      var input = document.createElement("input");
      input.type = "checkbox";
      input.checked = colDef.visible !== false;
      input.addEventListener("change", function () {
        if (!table) return;
        var col = table.getColumn(colDef.field);
        if (!col) return;
        if (input.checked) {
          col.show();
        } else {
          col.hide();
        }
      });

      var span = document.createElement("span");
      span.textContent = colDef.title || colDef.field;

      wrapper.appendChild(input);
      wrapper.appendChild(span);
      holder.appendChild(wrapper);
    });
  }

  function applyGlobalSearch(value) {
    if (!table) return;
    if (currentGlobalFilter) {
      table.removeFilter(globalFilterFn);
      currentGlobalFilter = null;
    }
    var v = (value || "").trim().toLowerCase();
    if (!v) return;

    currentGlobalFilter = v;
    table.addFilter(globalFilterFn, { value: v });
  }

  function globalFilterFn(data, params) {
    var value = (params && params.value) || "";
    if (!value) return true;
    var needle = value.toLowerCase();
    return Object.keys(data).some(function (key) {
      var v = data[key];
      if (v === null || v === undefined) return false;
      return String(v).toLowerCase().indexOf(needle) !== -1;
    });
  }

  function renderTable(rows) {
    var columns = buildColumns();
    if (!table) {
      table = new Tabulator("#statsTable", {
        data: rows,
        layout: "fitDataStretch",
        movableColumns: true,
        clipboard: true,
        clipboardCopyStyled: false,
        clipboardCopyRowRange: "active",
        pagination: "local",
        paginationSize: parseInt(qs("statsPageSize").value, 10) || 150,
        paginationSizeSelector: [10, 25, 50, 100, 150],
        columns: columns,
        rowFormatter: function (row) {
          var data = row.getData();
          if (data.ligne_grisee) {
            row.getElement().classList.add("row-grise");
          }
          if (data.compteur_supprime) {
            row.getElement().classList.add("row-supprime");
          }
          if (data.lot_inoccupe) {
            row.getElement().classList.add("row-inoccupe");
          }
        },
        persistence: {
          sort: true,
          filter: true,
          columns: true,
        },
        persistenceMode: "local",
        persistenceID: "admin_stats_table",
      });

      buildColumnToggles(columns);
    } else {
      table.setData(rows);
    }
  }

  function copyDynamicTableToClipboard() {
    if (!table || !table.modules || !table.modules.clipboard) {
      alert("Tableau indisponible.");
      return;
    }
    table.modules.clipboard.copy("active", true);
    alert("Le tableau visible a ete copie. Tu peux maintenant le coller dans Excel.");
  }

  function getPivotPreset() {
    var select = qs("statsPivotPreset");
    return select ? select.value : "conso_copro_annee";
  }

  function buildPivotConfig(preset) {
    var utils = $.pivotUtilities;
    var sum = utils.aggregatorTemplates.sum;
    var count = utils.aggregatorTemplates.count;
    var numberFormatM3 = utils.numberFormat({ digitsAfterDecimal: 0 });
    var numberFormatMoney = utils.numberFormat({ digitsAfterDecimal: 2 });

    if (preset === "conso_lot_annee") {
      return {
        rows: ["lot_numero"],
        cols: ["annee"],
        vals: ["consommation"],
        aggregatorName: "Sum",
        aggregator: sum(numberFormatM3)(["consommation"]),
        rendererName: "Table",
      };
    }
    if (preset === "conso_nature_annee") {
      return {
        rows: ["compteur_nature"],
        cols: ["annee"],
        vals: ["consommation"],
        aggregatorName: "Sum",
        aggregator: sum(numberFormatM3)(["consommation"]),
        rendererName: "Table",
      };
    }
    if (preset === "valorisation_copro_annee") {
      return {
        rows: ["proprietaire_nom"],
        cols: ["annee"],
        vals: ["valorisation_eur"],
        aggregatorName: "Sum",
        aggregator: sum(numberFormatMoney)(["valorisation_eur"]),
        rendererName: "Table",
      };
    }
    if (preset === "forfaits_annee") {
      return {
        rows: ["annee"],
        cols: ["compteur_nature"],
        vals: ["forfait_valeur"],
        aggregatorName: "Sum",
        aggregator: sum(numberFormatM3)(["forfait_valeur"]),
        rendererName: "Table",
      };
    }

    // Par defaut: consommation par coproprietaire et annee.
    return {
      rows: ["proprietaire_nom"],
      cols: ["annee"],
      vals: ["consommation"],
      aggregatorName: "Sum",
      aggregator: sum(numberFormatM3)(["consommation"]),
      rendererName: "Table",
    };
  }

  function filterRowsForPreset(rows, preset) {
    if (preset === "forfaits_annee") {
      return rows.filter(function (r) {
        return r && r.forfait_applique;
      });
    }
    return rows;
  }

  function extendPivotConfig(base, extra) {
    var merged = {};
    Object.keys(base || {}).forEach(function (k) {
      merged[k] = base[k];
    });
    Object.keys(extra || {}).forEach(function (k) {
      merged[k] = extra[k];
    });
    return merged;
  }

  function sanitizePivotState(state) {
    if (!state) return null;
    var allowedKeys = [
      "rows",
      "cols",
      "vals",
      "rowOrder",
      "colOrder",
      "aggregatorName",
      "rendererName",
      "exclusions",
      "inclusions",
    ];
    var clean = {};
    allowedKeys.forEach(function (k) {
      if (state[k] !== undefined) {
        clean[k] = state[k];
      }
    });
    return clean;
  }

  function normalizePivotConfigForExternalYearFilter(config) {
    var normalized = extendPivotConfig({}, config || {});

    if (normalized.inclusions && normalized.inclusions.annee) {
      delete normalized.inclusions.annee;
      if (Object.keys(normalized.inclusions).length === 0) {
        delete normalized.inclusions;
      }
    }

    if (normalized.exclusions && normalized.exclusions.annee) {
      delete normalized.exclusions.annee;
      if (Object.keys(normalized.exclusions).length === 0) {
        delete normalized.exclusions;
      }
    }

    return normalized;
  }

  function renderPivot(rows, overrideConfig) {
    var target = document.getElementById("statsPivot");
    if (!target || !window.$ || !$.pivotUtilities) return;

    var preset = getPivotPreset();
    var config = normalizePivotConfigForExternalYearFilter(overrideConfig || buildPivotConfig(preset));
    var filteredRows = overrideConfig ? rows : filterRowsForPreset(rows, preset);

    if (filteredRows.length > MAX_ROWS_FOR_PIVOT) {
      target.innerHTML = "<div class=\"muted\">Analyse croisee desactivee au-dela de " + MAX_ROWS_FOR_PIVOT + " lignes. Choisis une annee ou reduis le filtre, puis clique sur Actualiser.</div>";
      pivotState = null;
      syncPivotSaveControls();
      return;
    }

    var finalConfig = extendPivotConfig(config, {
      onRefresh: function (state) {
        pivotState = normalizePivotConfigForExternalYearFilter(sanitizePivotState(state));
        syncPivotSaveControls();
      },
    });

    $(target).pivotUI(filteredRows, finalConfig, true);
  }

  function renderDetailTable(rows) {
    var container = qs("statsDetailTable");
    var notice = qs("statsDetailNotice");
    var detailYearBadge = qs("statsDetailYearBadge");
    if (!container) return;

    var year = qs("statsYear") ? qs("statsYear").value : "";
    if (detailYearBadge) {
      detailYearBadge.textContent = year ? "Annee: " + year : "Annees: toutes";
    }

    var filtered = year
      ? rows.filter(function (r) {
          return String(r.annee) === String(year);
        })
      : rows.slice();

    if (!filtered.length) {
      if (notice) notice.hidden = false;
      container.innerHTML = "<div class=\"muted\">Aucune donnee pour l'annee selectionnee.</div>";
      return;
    }
    if (notice) notice.hidden = true;

    if (filtered.length > MAX_ROWS_FOR_DETAIL) {
      container.innerHTML = "<div class=\"muted\">Rapport detaille masque au-dela de " + MAX_ROWS_FOR_DETAIL + " lignes pour garder la page reactive. Reduis le filtre puis clique sur Actualiser.</div>";
      return;
    }

    var grouped = {};
    filtered.forEach(function (r) {
      var lot = r.lot_numero || "Lot ?";
      var key = String(lot);
      if (!grouped[key]) {
        grouped[key] = {
          lot_numero: lot,
          lot_description: r.lot_description || "",
          proprietaire_nom: r.proprietaire_nom || "",
          lot_inoccupe: !!r.lot_inoccupe,
          lot_inoccupe_motif: r.lot_inoccupe_motif || "",
          rows: [],
          totals: {
            totalCons: 0,
            totalEC: 0,
            totalEF: 0,
            totalCuisine: 0,
            totalSdb: 0,
            totalValorisation: 0,
            totalValorisationEC: 0,
            totalValorisationEF: 0,
          },
        };
      }
      var cons = parseFloat(r.consommation);
      cons = isNaN(cons) ? 0 : cons;
      var valorisation = parseFloat(r.valorisation_eur);
      valorisation = isNaN(valorisation) ? 0 : valorisation;
      grouped[key].rows.push(r);
      grouped[key].totals.totalCons += cons;
      grouped[key].totals.totalValorisation += valorisation;

      var natureKey = getNatureKey(r);
      if (natureKey === "ec") grouped[key].totals.totalEC += cons;
      if (natureKey === "ef") grouped[key].totals.totalEF += cons;
      if (natureKey === "ec") grouped[key].totals.totalValorisationEC += valorisation;
      if (natureKey === "ef") grouped[key].totals.totalValorisationEF += valorisation;

      var emplKey = getEmplacementKey(r);
      if (emplKey === "cuisine") grouped[key].totals.totalCuisine += cons;
      if (emplKey === "sdb") grouped[key].totals.totalSdb += cons;
    });

    var lotKeys = Object.keys(grouped).sort(function (a, b) {
      return a.localeCompare(b, undefined, { numeric: true, sensitivity: "base" });
    });

    var html = "";
    lotKeys.forEach(function (key) {
      var group = grouped[key];
      var title = "Lot " + group.lot_numero;
      if (group.lot_description) title += " - " + escapeHtml(group.lot_description);
      if (group.proprietaire_nom) title += " | " + escapeHtml(group.proprietaire_nom);
      if (group.lot_inoccupe) title += " | APPARTEMENT INOCCUPE";

      html += "<section class=\"stats-detail-group\">";
      html += "<div class=\"stats-detail-group-title\">" + title + "</div>";
      html += "<table class=\"stats-detail-table\">";
      html += "<thead>";
      html += "<tr>";
      html += "<th>Annee</th>";
      html += "<th>Compteur</th>";
      html += "<th>Reference</th>";
      html += "<th>Nature (EC/EF)</th>";
      html += "<th>Emplacement</th>";
      html += "<th>Index N-1</th>";
      html += "<th>Index N</th>";
      html += "<th>Consommation</th>";
      html += "<th>Forfait</th>";
      html += "<th>Montant forfait</th>";
      html += "<th>Valorisation EUR</th>";
      html += "</tr>";
      html += "</thead>";
      html += "<tbody>";

      group.rows.forEach(function (r) {
        html += "<tr" + (r.compteur_supprime ? " class=\"row-supprime\"" : "") + ">";
        html += "<td>" + escapeHtml(r.annee) + "</td>";
        html += "<td>" + escapeHtml(r.compteur_id) + "</td>";
        html += "<td>" + escapeHtml(r.compteur_reference) + "</td>";
        html += "<td>" + escapeHtml(r.compteur_nature) + "</td>";
        html += "<td>" + escapeHtml(r.compteur_emplacement) + "</td>";
        html += "<td>" + (r.index_masque ? "Index masque" : escapeHtml(r.index_n_1)) + "</td>";
        html += "<td>" + (r.index_masque ? "Index masque" : escapeHtml(r.index_n)) + "</td>";
        html += "<td>" + (r.compteur_supprime ? "Compteur supprime" : formatNumber(r.consommation)) + "</td>";
        html += "<td>" + (r.forfait_applique ? "Oui" : "Non") + "</td>";
        html += "<td>" + (r.forfait_applique ? formatNumber(r.forfait_valeur) : "-") + "</td>";
        html += "<td>" + formatMoney(r.valorisation_eur) + "</td>";
        html += "</tr>";
      });

      html += "</tbody>";
      html += "<tfoot>";
      html += "<tr>";
      html += "<td colspan=\"7\" class=\"label\">Totaux lot</td>";
      html += "<td>" + formatNumber(group.totals.totalCons) + "</td>";
      html += "<td colspan=\"2\"></td>";
      html += "<td>" + formatMoney(group.totals.totalValorisation) + "</td>";
      html += "</tr>";
      html += "<tr>";
      html += "<td colspan=\"4\" class=\"label\">Total EC (eau chaude)</td>";
      html += "<td>" + formatNumber(group.totals.totalEC) + "</td>";
      html += "<td colspan=\"2\" class=\"label\">Total EF (eau froide)</td>";
      html += "<td>" + formatNumber(group.totals.totalEF) + "</td>";
      html += "<td colspan=\"2\"></td>";
      html += "<td>" + formatMoney(group.totals.totalValorisationEC + group.totals.totalValorisationEF) + "</td>";
      html += "</tr>";
      html += "<tr>";
      html += "<td colspan=\"4\" class=\"label\">Total cuisine</td>";
      html += "<td>" + formatNumber(group.totals.totalCuisine) + "</td>";
      html += "<td colspan=\"2\" class=\"label\">Total SDB</td>";
      html += "<td>" + formatNumber(group.totals.totalSdb) + "</td>";
      html += "<td colspan=\"2\"></td>";
      html += "<td></td>";
      html += "</tr>";
      html += "<tr>";
      html += "<td colspan=\"4\" class=\"label\">Valorisation EC</td>";
      html += "<td>" + formatMoney(group.totals.totalValorisationEC) + "</td>";
      html += "<td colspan=\"2\" class=\"label\">Valorisation EF</td>";
      html += "<td>" + formatMoney(group.totals.totalValorisationEF) + "</td>";
      html += "<td colspan=\"3\"></td>";
      html += "</tr>";
      html += "</tfoot>";
      html += "</table>";
      html += "</section>";
    });

    container.innerHTML = html;
  }

  function sanitizeSheetName(value) {
    var name = (value || "Rapport detaille").replace(/[\\/*?:\[\]]/g, " ").trim();
    if (!name) name = "Rapport detaille";
    return name.slice(0, 31);
  }

  function exportDetailTablesToExcel() {
    if (typeof XLSX === "undefined" || !XLSX.utils) {
      alert("Export Excel indisponible.");
      return;
    }

    var year = qs("statsYear") ? qs("statsYear").value : "";
    var groups = Array.prototype.slice.call(document.querySelectorAll(".stats-detail-group"));
    if (!groups.length) {
      alert("Aucun tableau detaille a exporter.");
      return;
    }

    var aoa = [];
    groups.forEach(function (group, groupIndex) {
      var titleEl = group.querySelector(".stats-detail-group-title");
      var tableEl = group.querySelector(".stats-detail-table");
      if (!tableEl) return;

      aoa.push([titleEl ? titleEl.textContent.trim() : "Lot"]);

      var rows = Array.prototype.slice.call(tableEl.querySelectorAll("tr"));
      rows.forEach(function (row) {
        var cells = Array.prototype.slice.call(row.querySelectorAll("th, td")).map(function (cell) {
          return (cell.textContent || "").trim();
        });
        aoa.push(cells);
      });

      if (groupIndex < groups.length - 1) {
        aoa.push([]);
      }
    });

    var worksheet = XLSX.utils.aoa_to_sheet(aoa);
    worksheet["!cols"] = [
      { wch: 10 },
      { wch: 12 },
      { wch: 20 },
      { wch: 16 },
      { wch: 18 },
      { wch: 12 },
      { wch: 12 },
      { wch: 14 },
      { wch: 10 },
      { wch: 16 },
      { wch: 16 },
    ];

    var workbook = XLSX.utils.book_new();
    var yearLabel = year || "toutes";
    XLSX.utils.book_append_sheet(workbook, worksheet, sanitizeSheetName("Detail " + yearLabel));
    XLSX.writeFile(workbook, "statistiques-detail-" + yearLabel + ".xlsx");
  }

  function exportCurrentPivotToExcel() {
    if (typeof XLSX === "undefined" || !XLSX.utils) {
      alert("Export Excel indisponible.");
      return;
    }

    var pivotRoot = qs("statsPivot");
    if (!pivotRoot) {
      alert("Tableau croise indisponible.");
      return;
    }

    var pivotTable = pivotRoot.querySelector("table.pvtTable");
    if (!pivotTable) {
      alert("Aucun tableau croise affichable a exporter.");
      return;
    }

    var preset = getPivotPreset() || "pivot";
    var workbook = XLSX.utils.table_to_book(pivotTable, { sheet: sanitizeSheetName("Pivot " + preset) });
    XLSX.writeFile(workbook, "statistiques-pivot-" + preset + ".xlsx");
  }

  function getNatureKey(row) {
    var value = (row.compteur_nature || row.consommation_type || "").toString().toLowerCase();
    if (value === "ec" || value.indexOf("chaud") !== -1 || value.indexOf("eau chaude") !== -1) {
      return "ec";
    }
    if (value === "ef" || value.indexOf("froid") !== -1 || value.indexOf("eau froide") !== -1) {
      return "ef";
    }
    return "autre";
  }

  function getEmplacementKey(row) {
    var value = (row.compteur_emplacement_norm || row.compteur_emplacement || "").toString().toLowerCase();
    if (value.indexOf("cuisine") !== -1) return "cuisine";
    if (value.indexOf("sdb") !== -1 || value.indexOf("salle de bain") !== -1 || value.indexOf("salle d") !== -1) {
      return "sdb";
    }
    return "autre";
  }

  function formatNumber(value) {
    var num = parseFloat(value);
    if (isNaN(num)) return "-";
    return num.toLocaleString(undefined, { maximumFractionDigits: 0 });
  }

  function formatMoney(value) {
    var num = parseFloat(value);
    if (isNaN(num)) return "-";
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " EUR";
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) return "";
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function isTabPaneActive(id) {
    var pane = qs(id);
    return !!(pane && pane.classList.contains("active") && pane.classList.contains("show"));
  }

  function renderVisibleStandardSection() {
    if (isTabPaneActive("stats-table-pane")) {
      renderTable(lastData);
      return;
    }

    renderDetailTable(lastData);
  }

  function refreshAll() {
    var currentPivotConfig = getCurrentPivotState() || buildPivotConfig(getPivotPreset());
    var pivot = qs("statsPivot");
    var detail = qs("statsDetailTable");
    var statsTable = qs("statsTable");
    if (statsTable) {
      statsTable.innerHTML = "<div class=\"muted\">Chargement du tableau…</div>";
    }
    if (detail) {
      detail.innerHTML = "";
    }
    if (pivot) {
      pivot.innerHTML = "<div class=\"muted\">Chargement de l'analyse croisee…</div>";
    }

    fetchData()
      .then(function (payload) {
        lastData = payload.rows || [];
        renderVisibleStandardSection();
      })
      .catch(function () {
        alert("Impossible de charger les statistiques.");
      });

    fetchPivotData()
      .then(function (pivotPayload) {
        lastPivotData = (pivotPayload && pivotPayload.rows) || [];
        window.setTimeout(function () {
          renderPivot(lastPivotData, currentPivotConfig);
        }, 0);
      })
      .catch(function () {
        if (pivot) {
          pivot.innerHTML = "<div class=\"muted\">Impossible de charger l'analyse croisee.</div>";
        }
      });
  }

  function getSelectedPivotYears() {
    var select = qs("statsPivotYears");
    if (!select) return [];
    return Array.prototype.slice.call(select.options)
      .filter(function (option) { return option.selected; })
      .map(function (option) { return option.value; })
      .filter(function (value) { return value !== ""; });
  }

  function setSelectedPivotYears(values) {
    var select = qs("statsPivotYears");
    if (!select) return;
    var wanted = {};
    (values || []).forEach(function (value) {
      wanted[String(value)] = true;
    });
    Array.prototype.slice.call(select.options).forEach(function (option) {
      option.selected = !!wanted[String(option.value)];
    });
  }

  function canUseStorage() {
    try {
      return typeof window !== "undefined" && "localStorage" in window && window.localStorage;
    } catch (e) {
      return false;
    }
  }

  function loadLocalSavedPivots() {
    if (!canUseStorage()) {
      return {};
    }
    var raw = window.localStorage.getItem(pivotStorageKey);
    if (!raw) {
      return {};
    }
    try {
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function readSavedPivots() {
    return savedPivotCache || {};
  }

  function writeSavedPivots(map) {
    savedPivotCache = map || {};
    if (!canUseStorage()) return;
    window.localStorage.setItem(pivotStorageKey, JSON.stringify(savedPivotCache));
  }

  function fetchSavedPivots() {
    savedPivotCache = loadLocalSavedPivots();
    refreshSavedPivotSelect();
    syncPivotSaveControls();

    return fetch("/admin/stats/pivots", { headers: { "Accept": "application/json" } })
      .then(function (r) {
        if (!r.ok) throw new Error("load_failed");
        return r.json();
      })
      .then(function (payload) {
        hasRemoteStorage = true;
        savedPivotCache = extendPivotConfig(savedPivotCache, payload.items || {});
        writeSavedPivots(savedPivotCache);
        refreshSavedPivotSelect();
        syncPivotSaveControls();
      })
      .catch(function () {
        hasRemoteStorage = false;
        savedPivotCache = loadLocalSavedPivots();
        refreshSavedPivotSelect();
        syncPivotSaveControls();
      });
  }

  function parseJsonSafely(response) {
    return response.text().then(function (text) {
      if (!text) return {};
      try {
        return JSON.parse(text);
      } catch (e) {
        return { message: text };
      }
    });
  }

  function refreshSavedPivotSelect() {
    var select = qs("statsPivotSaved");
    if (!select) return;
    var saved = readSavedPivots();
    var names = Object.keys(saved).sort(function (a, b) {
      return a.localeCompare(b);
    });

    select.innerHTML = "";
    var empty = document.createElement("option");
    empty.value = "";
    empty.textContent = "Aucun";
    select.appendChild(empty);

    names.forEach(function (name) {
      var opt = document.createElement("option");
      opt.value = name;
      opt.textContent = name;
      select.appendChild(opt);
    });

    if (activeSavedPivot && saved[activeSavedPivot]) {
      select.value = activeSavedPivot;
    }
  }

  function syncPivotSaveControls() {
    var deleteBtn = qs("statsPivotDeleteBtn");
    if (deleteBtn) {
      deleteBtn.disabled = !activeSavedPivot;
    }
  }

  function getCurrentPivotState() {
    if (pivotState) return pivotState;
    if (!window.$) return null;
    var target = $("#statsPivot");
    if (!target.length) return null;
    return sanitizePivotState(target.data("pivotUIOptions"));
  }

  function saveCurrentPivot(name) {
    if (!name) return;
    var state = getCurrentPivotState();
    if (!state) {
      alert("Aucun tableau croise a memoriser.");
      return;
    }
    var saved = readSavedPivots();
    if (saved[name] && !confirm("Ce nom existe deja. Ecraser le tableau memorise ?")) {
      return;
    }

    if (hasRemoteStorage) {
      fetch("/admin/stats/pivots", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({ name: name, config: state })
      })
        .then(function (r) {
          return parseJsonSafely(r).then(function (payload) {
            if (!r.ok) {
              var message = payload && payload.message ? payload.message : "save_failed";
              throw new Error(message);
            }
            return payload;
          });
        })
        .then(function (payload) {
          savedPivotCache = extendPivotConfig(loadLocalSavedPivots(), payload.items || {});
          writeSavedPivots(savedPivotCache);
          activeSavedPivot = name;
          refreshSavedPivotSelect();
          syncPivotSaveControls();
        })
        .catch(function (e) {
          saved[name] = {
            config: state,
            savedAt: new Date().toISOString(),
          };
          writeSavedPivots(saved);
          activeSavedPivot = name;
          refreshSavedPivotSelect();
          syncPivotSaveControls();
          alert("Enregistrement serveur indisponible. Le tableau a ete memorise sur ce navigateur. Detail: " + (e && e.message ? e.message : "Erreur inconnue."));
        });
      return;
    }

    saved[name] = {
      config: state,
      savedAt: new Date().toISOString(),
    };
    writeSavedPivots(saved);
    activeSavedPivot = name;
    refreshSavedPivotSelect();
    syncPivotSaveControls();
  }

  function loadSavedPivot(name) {
    if (!name) return;
    var saved = readSavedPivots();
    var entry = saved[name];
    if (!entry || !entry.config) return;
    activeSavedPivot = name;
    renderPivot(lastPivotData, entry.config);
  }

  function deleteSavedPivot(name) {
    if (!name) return;
    var saved = readSavedPivots();
    if (!saved[name]) return;
    if (!confirm("Supprimer le tableau memorise \"" + name + "\" ?")) return;

    if (hasRemoteStorage) {
      fetch("/admin/stats/pivots/" + encodeURIComponent(name), { method: "DELETE" })
        .then(function (r) {
          return parseJsonSafely(r).then(function (payload) {
            if (!r.ok) {
              var message = payload && payload.message ? payload.message : "delete_failed";
              throw new Error(message);
            }
            return payload;
          });
        })
        .then(function (payload) {
          var localSaved = loadLocalSavedPivots();
          delete localSaved[name];
          savedPivotCache = extendPivotConfig(localSaved, payload.items || {});
          writeSavedPivots(savedPivotCache);
          activeSavedPivot = "";
          refreshSavedPivotSelect();
          syncPivotSaveControls();
        })
        .catch(function (e) {
          delete saved[name];
          writeSavedPivots(saved);
          activeSavedPivot = "";
          refreshSavedPivotSelect();
          syncPivotSaveControls();
          alert("Suppression serveur indisponible. Le tableau a ete retire de ce navigateur. Detail: " + (e && e.message ? e.message : "Erreur inconnue."));
        });
      return;
    }

    delete saved[name];
    writeSavedPivots(saved);
    activeSavedPivot = "";
    refreshSavedPivotSelect();
    syncPivotSaveControls();
  }

  function bindEvents() {
    qs("statsRefreshBtn").addEventListener("click", function () {
      refreshAll();
    });
    var yearsAllBtn = qs("statsPivotYearsAllBtn");
    if (yearsAllBtn) {
      yearsAllBtn.addEventListener("click", function () {
        var select = qs("statsPivotYears");
        if (!select) return;
        setSelectedPivotYears(Array.prototype.slice.call(select.options).map(function (option) { return option.value; }));
      });
    }

    var yearsClearBtn = qs("statsPivotYearsClearBtn");
    if (yearsClearBtn) {
      yearsClearBtn.addEventListener("click", function () {
        setSelectedPivotYears([]);
      });
    }

    var pivotRefreshBtn = qs("statsPivotRefreshBtn");
    if (pivotRefreshBtn) {
      pivotRefreshBtn.addEventListener("click", refreshPivotOnly);
    }

    qs("statsSearch").addEventListener("input", function (e) {
      applyGlobalSearch(e.target.value);
    });

    qs("statsPageSize").addEventListener("change", function () {
      if (!table) return;
      table.setPageSize(parseInt(this.value, 10) || 25);
    });

    var tableTab = qs("stats-table-tab");
    if (tableTab) {
      tableTab.addEventListener("shown.bs.tab", function () {
        if (lastData.length) {
          renderTable(lastData);
        }
      });
    }

    var detailTab = qs("stats-detail-tab");
    if (detailTab) {
      detailTab.addEventListener("shown.bs.tab", function () {
        if (lastData.length) {
          renderDetailTable(lastData);
        }
      });
    }

    var pivotPreset = qs("statsPivotPreset");
    if (pivotPreset) {
      pivotPreset.addEventListener("change", function () {
        activeSavedPivot = "";
        var savedSelect = qs("statsPivotSaved");
        if (savedSelect) savedSelect.value = "";
        renderPivot(lastPivotData);
        syncPivotSaveControls();
      });
    }

    var pivotYears = qs("statsPivotYears");
    if (pivotYears) {
      pivotYears.addEventListener("change", function () {
        activeSavedPivot = "";
        var savedSelect = qs("statsPivotSaved");
        if (savedSelect) savedSelect.value = "";
      });
    }

    var savedSelect = qs("statsPivotSaved");
    if (savedSelect) {
      savedSelect.addEventListener("change", function () {
        var name = (this.value || "").trim();
        var nameInput = qs("statsPivotName");
        if (nameInput) nameInput.value = name;
        if (!name) {
          activeSavedPivot = "";
          syncPivotSaveControls();
          return;
        }
        loadSavedPivot(name);
      });
    }

    var saveBtn = qs("statsPivotSaveBtn");
    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        var input = qs("statsPivotName");
        var name = input ? input.value.trim() : "";
        if (!name) {
          alert("Donne un nom pour memoriser le tableau.");
          return;
        }
        saveCurrentPivot(name);
      });
    }

    var deleteBtn = qs("statsPivotDeleteBtn");
    if (deleteBtn) {
      deleteBtn.addEventListener("click", function () {
        if (!activeSavedPivot) return;
        deleteSavedPivot(activeSavedPivot);
      });
    }

    var exportBtn = qs("statsPivotExportBtn");
    if (exportBtn) {
      exportBtn.addEventListener("click", function () {
        window.open("/admin/stats/pivots/export", "_blank");
      });
    }

    var pivotExcelBtn = qs("statsPivotExcelBtn");
    if (pivotExcelBtn) {
      pivotExcelBtn.addEventListener("click", exportCurrentPivotToExcel);
    }

      qs("statsXlsxBtn").addEventListener("click", function () {
        if (!table || typeof table.download !== "function") {
          alert("Export Excel indisponible.");
          return;
        }
        table.download("xlsx", "statistiques.xlsx", { sheetName: "Statistiques" });
    });

    var copyTableBtn = qs("statsCopyTableBtn");
    if (copyTableBtn) {
      copyTableBtn.addEventListener("click", copyDynamicTableToClipboard);
    }

    var detailExportBtn = qs("statsDetailXlsxBtn");
    if (detailExportBtn) {
      detailExportBtn.addEventListener("click", exportDetailTablesToExcel);
    }

    qs("statsPdfBtn").addEventListener("click", function () {
      var params = getStandardFilters();
      var url = buildUrl("/admin/stats/pdf", params);
      window.open(url, "_blank");
    });

    qs("statsPrintBtn").addEventListener("click", function () {
      window.print();
    });
  }

  function cleanup() {
    if (pendingInitTimer) {
      window.clearTimeout(pendingInitTimer);
      pendingInitTimer = null;
    }
    if (table && typeof table.destroy === "function") {
      table.destroy();
    }
    table = null;
    lastData = [];
    lastPivotData = [];
    currentGlobalFilter = null;
    pivotState = null;
    activeSavedPivot = "";
    isInitialized = false;
    var pivot = qs("statsPivot");
    if (pivot) {
      pivot.innerHTML = "";
    }
    var detail = qs("statsDetailTable");
    if (detail) {
      detail.innerHTML = "";
    }
    var statsTable = qs("statsTable");
    if (statsTable) {
      statsTable.innerHTML = "";
    }
  }

  function dependenciesReady() {
    return typeof window.Tabulator !== "undefined"
      && typeof window.fetch === "function"
      && typeof window.$ !== "undefined"
      && typeof $.pivotUtilities !== "undefined";
  }

  function init() {
    if (!qs("statsTable")) return;
    if (isInitialized) return;
    if (!dependenciesReady()) {
      if (pendingInitTimer) {
        window.clearTimeout(pendingInitTimer);
      }
      pendingInitTimer = window.setTimeout(init, 50);
      return;
    }
    pendingInitTimer = null;
    isInitialized = true;
    fetchSavedPivots();
    bindEvents();
    refreshAll();
  }

  document.addEventListener("DOMContentLoaded", init);
  document.addEventListener("turbo:load", init);
  document.addEventListener("turbo:before-cache", cleanup);
})();
