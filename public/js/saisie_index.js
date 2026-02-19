document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-saisie-index');

  function parse(v){ const n = parseFloat(v); return isNaN(n) ? 0 : n; }

  function calcBloc(bloc){
    // champs
    const idxNMinus1 = parse(bloc.querySelector('input[name$="[indexNmoins1]"]').value);
    const idxNInput  = bloc.querySelector('.indexN');
    const consoInput = bloc.querySelector('.conso');
    const forfaitRow = bloc.querySelector('.forfait-row');
    const forfaitInp = bloc.querySelector('.forfait');
    const etatSel    = bloc.querySelector('.etat-select');

    let conso = 0;

    if (etatSel && etatSel.value) {
      const opt = etatSel.selectedOptions[0];
      const code = opt.dataset.code || '';
      const requiresIndex = opt.dataset.requiresIndex === '1';
      const requiresForfait = opt.dataset.requiresForfait === '1';

      // visibilités
      if (forfaitRow) forfaitRow.classList.toggle('hidden', !requiresForfait);

      const idxN = parse(idxNInput?.value);

      if (code === 'SUPPRIME' || code === 'INOCCUPE') {
        conso = 0;
      } else if (code === 'BLOQUE') {
        conso = Math.max(0, parse(forfaitInp?.value));
      } else if (code === 'REMPLACE') {
        // version simple sans index démonté/nouveau -> diff
        conso = Math.max(0, idxN - idxNMinus1);
      } else {
        // fonctionnement
        conso = Math.max(0, idxN - idxNMinus1);
      }
    } else {
      // pas d'état choisi -> diff simple
      const idxN = parse(idxNInput?.value);
      conso = Math.max(0, idxN - idxNMinus1);
      if (forfaitRow) forfaitRow.classList.add('hidden');
    }

    if (consoInput) consoInput.value = conso.toFixed(2);
    return conso;
  }

  function refreshTotals(){
    let ef=0, ec=0, cuisine=0, sdb=0;

    document.querySelectorAll('.bloc-compteur').forEach(bloc => {
      const slot = bloc.dataset.slot || '';
      const conso = calcBloc(bloc);

      if (slot.includes('ef')) ef += conso;
      if (slot.includes('ec')) ec += conso;
      if (slot.includes('cuisine')) cuisine += conso;
      if (slot.includes('sdb')) sdb += conso;
    });

    document.getElementById('total-ef').textContent = ef.toFixed(2);
    document.getElementById('total-ec').textContent = ec.toFixed(2);
    document.getElementById('total-cuisine').textContent = cuisine.toFixed(2);
    document.getElementById('total-sdb').textContent = sdb.toFixed(2);
  }

  // listeners
  form.addEventListener('input', e => {
    if (e.target.matches('.indexN, .forfait, .etat-select')) {
      refreshTotals();
    }
  });

  // init
  refreshTotals();
});
