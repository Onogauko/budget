import { state } from '../core/state.js';
import { ensureMonths } from '../core/data.js';
import {
  fmtRp, fmtShort, fmtPct, ymOf, addMonths, daysInMonth, monthLabel, parseISO, dateShort, dateMed,
  esc, colorAt, HARI_SHORT,
} from '../core/helpers.js';
import { icons } from '../ui/icons.js';
import { toast } from '../ui/toast.js';

const pct = (x) => fmtPct(x, x > 0 && x < 0.01 ? 2 : 1);

let root;
let navigate;

export function initMonthly(el, { go }) {
  root = el;
  navigate = go;
  root.addEventListener('click', (e) => {
    const t = e.target.closest('[data-act]');
    if (!t) return;
    const act = t.dataset.act;
    if (act === 'prev') setMonth(addMonths(state.month, -1));
    if (act === 'next') setMonth(addMonths(state.month, 1));
    if (act === 'this') setMonth(ymOf(state.today));
    if (act === 'day') { state.current = t.dataset.date; navigate('harian'); }
    if (act === 'xlsx' || act === 'csv') location.href = `api/export.php?ym=${state.month}&format=${act}`;
    if (act === 'pdf') window.print();
  });
}

export async function showMonthly() {
  await setMonth(state.month || ymOf(state.current || state.today));
}

async function setMonth(ym) {
  state.month = ym;
  if (!state.loadedMonths.has(ym)) {
    root.innerHTML = `${navHtml(ym, null)}<div class="kpis"><div class="kpi skeleton"></div><div class="kpi skeleton"></div></div><div class="card skeleton" style="height:360px"></div>`;
    try { await ensureMonths([ym]); } catch (e) { toast(e.message, 'error'); return; }
    if (state.month !== ym) return;
  }
  render(ym);
}

function navHtml(ym, info) {
  return `
    <div class="month-nav">
      <button class="icon-btn" data-act="prev" aria-label="Bulan sebelumnya">${icons.left}</button>
      <button class="month-title" data-act="this" title="Kembali ke bulan ini">
        <b>${monthLabel(ym)}</b><small>${info ?? '&nbsp;'}</small>
      </button>
      <button class="icon-btn" data-act="next" aria-label="Bulan berikutnya">${icons.right}</button>
    </div>`;
}

function render(ym) {
  const n = daysInMonth(ym);
  const list = [];
  for (let i = 1; i <= n; i++) {
    const d = `${ym}-${String(i).padStart(2, '0')}`;
    list.push({ d, day: state.days[d] });
  }
  const filled = list.filter((x) => x.day?.t);
  const info = `${filled.length} dari ${n} hari terisi`;

  if (!filled.length) {
    root.innerHTML = `${navHtml(ym, info)}
      <div class="card empty">${icons.empty}<h4>Belum ada budget</h4>
      <p class="muted">Belum ada data budget untuk ${monthLabel(ym)}.</p></div>`;
    return;
  }

  const total = filled.reduce((a, x) => a + x.day.t, 0);
  const avg = total / filled.length;
  const hi = filled.reduce((a, x) => (x.day.t > a.day.t ? x : a));
  const lo = filled.reduce((a, x) => (x.day.t < a.day.t ? x : a));
  const max = hi.day.t;

  // rata-rata per hari dalam minggu (Sen..Min)
  const wd = Array.from({ length: 7 }, () => ({ s: 0, c: 0 }));
  filled.forEach((x) => { const k = parseISO(x.d).getDay(); wd[k].s += x.day.t; wd[k].c++; });
  const order = [1, 2, 3, 4, 5, 6, 0];
  const wdAvg = order.map((k) => ({ k, v: wd[k].c ? wd[k].s / wd[k].c : 0 }));
  const wdMax = Math.max(...wdAvg.map((w) => w.v), 1);

  // total per divisi
  const divTot = {};
  filled.forEach((x) => Object.entries(x.day.d).forEach(([c, v]) => { divTot[c] = (divTot[c] || 0) + v; }));
  const divs = state.divisions.map((d, i) => ({ ...d, color: colorAt(i), amt: divTot[d.code] || 0 })).filter((d) => d.amt);
  Object.keys(divTot).forEach((c) => {
    if (!divs.some((d) => d.code === Number(c))) divs.push({ code: Number(c), name: `Divisi ${c}`, color: '#94a3b8', amt: divTot[c] });
  });
  divs.sort((a, b) => b.amt - a.amt);
  const dMax = divs[0]?.amt || 1;

  // kalender (minggu mulai Senin)
  const firstDow = (parseISO(`${ym}-01`).getDay() + 6) % 7;
  let cal = order.map((k) => `<div class="cal-h ${k === 0 || k === 6 ? 'we' : ''}">${HARI_SHORT[k]}</div>`).join('');
  cal += '<div class="cal-c blank"></div>'.repeat(firstDow);
  list.forEach((x) => {
    const v = x.day?.t || 0;
    const lvl = v ? 0.12 + 0.88 * (v / max) : 0;
    cal += `<button class="cal-c ${v ? '' : 'none'} ${lvl > 0.55 ? 'hot' : ''} ${x.d === state.today ? 'today' : ''}" data-act="day" data-date="${x.d}" style="--l:${lvl.toFixed(3)}"
      title="${dateMed(x.d)}: ${fmtRp(v)}">
      <span class="cal-n">${parseISO(x.d).getDate()}</span><span class="cal-v">${v ? fmtShort(v) : ''}</span>${x.day?.n ? '<i class="cal-dot"></i>' : ''}</button>`;
  });

  const title = esc(state.settings.store_title || '');
  root.innerHTML = `
    ${navHtml(ym, info)}
    <div class="print-title"><h2>Daily Budget ${title}</h2><p>${monthLabel(ym)}</p></div>
    <div class="kpi-hero">
      <small>Total Budget ${monthLabel(ym)}</small>
      <b>${fmtRp(total)}</b>
      <span>${title}</span>
    </div>
    <div class="kpis">
      <div class="kpi"><small>Rata-rata / hari</small><b>${fmtShort(avg, true)}</b></div>
      <div class="kpi"><small>Hari terisi</small><b>${filled.length}<span class="muted"> / ${n}</span></b></div>
      <div class="kpi"><small>Tertinggi • ${dateShort(hi.d)}</small><b>${fmtShort(hi.day.t, true)}</b></div>
      <div class="kpi"><small>Terendah • ${dateShort(lo.d)}</small><b>${fmtShort(lo.day.t, true)}</b></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="section-head"><h3>Kalender Budget</h3><span class="muted small">ketuk tanggal</span></div>
        <div class="calendar">${cal}</div>
      </div>
      <div class="card">
        <div class="section-head"><h3>Rata-rata per Hari</h3></div>
        <div class="wdchart">
          ${wdAvg.map((w) => `<div class="wd-col ${w.k === 0 || w.k === 6 ? 'we' : ''}">
            <span class="wd-v">${w.v ? fmtShort(w.v) : '–'}</span>
            <div class="wd-bar"><i style="height:${((w.v / wdMax) * 100).toFixed(1)}%"></i></div>
            <span class="wd-l">${HARI_SHORT[w.k]}</span></div>`).join('')}
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-head"><h3>Total per Divisi</h3><span class="count">${divs.length}</span></div>
      <ul class="divlist">
        ${divs.map((d) => `
          <li>
            <span class="code" style="--c:${d.color}">${d.code}</span>
            <div class="dv-main">
              <span class="dv-name">${esc(d.name)}</span>
              <div class="dv-bar"><i style="width:${((d.amt / dMax) * 100).toFixed(1)}%;background:${d.color}"></i></div>
            </div>
            <div class="dv-right"><span class="dv-amt">${fmtRp(d.amt)}</span><span class="dv-pct">${pct(d.amt / total)}</span></div>
          </li>`).join('')}
      </ul>
      <div class="divlist-foot"><span>Total</span><b>${fmtRp(total)}</b></div>
    </div>

    <div class="card no-print">
      <div class="section-head"><h3>Unduh Laporan</h3><span class="muted small">${monthLabel(ym)}</span></div>
      <div class="export-row">
        <button class="btn btn-soft" data-act="xlsx">${icons.download}<span>Excel</span></button>
        <button class="btn btn-soft" data-act="pdf">${icons.printer}<span>PDF</span></button>
        <button class="btn btn-soft" data-act="csv">${icons.file}<span>CSV</span></button>
      </div>
    </div>`;
}
