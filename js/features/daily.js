import { state, isAdmin } from '../core/state.js';
import { ensureMonths } from '../core/data.js';
import {
  fmtRp, fmtShort, fmtPct, addDays, ymOf, dayName, dayShort, dateLong, relLabel, esc, colorAt,
  parseISO, daysInMonth, monthLabel, dateShort, debounce,
} from '../core/helpers.js';
import { icons } from '../ui/icons.js';
import { openSheet } from '../ui/sheet.js';
import { toast } from '../ui/toast.js';

const pct = (x) => fmtPct(x, x > 0 && x < 0.01 ? 2 : 1);

let root;
let carousel;
let strip;
let busy = false;       // scroll yang dipicu program
let navigate;

export function initDaily(el, { go }) {
  root = el;
  navigate = go;
  root.innerHTML = `
    <div class="daily">
      <div class="quick">
        <div class="seg seg-quick" id="quickSeg">
          <button data-off="0">Hari ini</button>
          <button data-off="1">Besok</button>
          <button data-off="2">Lusa</button>
        </div>
        <label class="icon-btn date-pick" title="Pilih tanggal">${icons.cal}
          <input type="date" id="datePick" aria-label="Pilih tanggal"></label>
      </div>
      <div class="datestrip" id="dateStrip" role="tablist" aria-label="Pilih hari"></div>
      <div class="carousel-wrap">
        <button class="nav-arrow left" id="prevDay" aria-label="Hari sebelumnya">${icons.left}</button>
        <div class="carousel" id="carousel"></div>
        <button class="nav-arrow right" id="nextDay" aria-label="Hari berikutnya">${icons.right}</button>
      </div>
      <p class="swipe-hint">‹ geser untuk pindah hari ›</p>
    </div>`;
  carousel = root.querySelector('#carousel');
  strip = root.querySelector('#dateStrip');

  root.querySelector('#quickSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (b) setDate(addDays(state.today, Number(b.dataset.off)));
  });
  const pick = root.querySelector('#datePick');
  pick.addEventListener('change', () => pick.value && setDate(pick.value));
  root.querySelector('#prevDay').addEventListener('click', () => slideBy(-1));
  root.querySelector('#nextDay').addEventListener('click', () => slideBy(1));
  strip.addEventListener('click', (e) => {
    const b = e.target.closest('[data-date]');
    if (b) setDate(b.dataset.date);
  });

  const settle = debounce(() => {
    if (busy) return;
    const w = carousel.clientWidth;
    const idx = Math.round(carousel.scrollLeft / w);
    if (idx !== 1) setDate(addDays(state.current, idx - 1));
  }, 90);
  carousel.addEventListener('scroll', settle, { passive: true });

  carousel.addEventListener('click', (e) => {
    const sortBtn = e.target.closest('[data-sort]');
    if (sortBtn) {
      state.sortMode = sortBtn.dataset.sort;
      render();
      return;
    }
    const row = e.target.closest('[data-div]');
    if (row) openDivision(Number(row.dataset.div), row.dataset.date);
    if (e.target.closest('[data-goto-upload]')) navigate('upload');
  });

  window.addEventListener('resize', debounce(() => centre(), 150));
  document.addEventListener('keydown', (e) => {
    if (state.view !== 'harian' || e.target.closest('input,textarea,select') || document.querySelector('.sheet-wrap')) return;
    if (e.key === 'ArrowLeft') slideBy(-1);
    if (e.key === 'ArrowRight') slideBy(1);
  });

  state.current = state.current || state.today;
}

function slideBy(n) {
  const w = carousel.clientWidth;
  carousel.scrollTo({ left: w * (1 + n), behavior: 'smooth' });
}

function centre() {
  busy = true;
  carousel.scrollLeft = carousel.clientWidth;
  requestAnimationFrame(() => requestAnimationFrame(() => { busy = false; }));
}

export async function showDaily() {
  await setDate(state.current || state.today);
}

export async function setDate(iso) {
  state.current = iso;
  const months = [addDays(iso, -8), addDays(iso, 2), iso].map(ymOf);
  const need = months.some((m) => !state.loadedMonths.has(m));
  if (need) {
    renderSkeleton();
    try {
      await ensureMonths(months);
    } catch (e) {
      toast(e.message, 'error');
    }
    if (state.current !== iso) return;
  }
  render();
}

function renderSkeleton() {
  carousel.innerHTML = [0, 1, 2].map(() => `<div class="slide"><div class="hero skeleton"></div><div class="card skeleton" style="height:420px"></div></div>`).join('');
  centre();
}

function render() {
  const iso = state.current;
  carousel.innerHTML = [-1, 0, 1].map((o) => `<div class="slide">${slideHtml(addDays(iso, o))}</div>`).join('');
  centre();
  renderStrip();
  root.querySelector('#datePick').value = iso;
  root.querySelectorAll('#quickSeg button').forEach((b) => {
    b.classList.toggle('active', addDays(state.today, Number(b.dataset.off)) === iso);
  });
  // preload bulan sebelah agar geser berikutnya instan
  ensureMonths([ymOf(addDays(iso, -9)), ymOf(addDays(iso, 9))]).catch(() => {});
}

function renderStrip() {
  const iso = state.current;
  let html = '';
  for (let i = -10; i <= 10; i++) {
    const d = addDays(iso, i);
    const day = state.days[d];
    const isToday = d === state.today;
    html += `<button class="chip-day ${i === 0 ? 'active' : ''} ${isToday ? 'is-today' : ''} ${[0, 6].includes(parseISO(d).getDay()) ? 'weekend' : ''}"
      data-date="${d}" role="tab" aria-selected="${i === 0}">
      <span class="cd-day">${isToday ? 'Hari ini' : dayShort(d)}</span>
      <span class="cd-num">${parseISO(d).getDate()}</span>
      <span class="cd-amt">${day ? fmtShort(day.t) : '–'}</span>
    </button>`;
  }
  strip.innerHTML = html;
  const active = strip.querySelector('.active');
  strip.scrollLeft = active.offsetLeft - strip.clientWidth / 2 + active.clientWidth / 2;
}

function monthStats(iso) {
  const ym = ymOf(iso);
  const n = daysInMonth(ym);
  let total = 0;
  let mtd = 0;
  let filled = 0;
  for (let i = 1; i <= n; i++) {
    const d = `${ym}-${String(i).padStart(2, '0')}`;
    const v = state.days[d];
    if (!v) continue;
    filled++;
    total += v.t;
    if (d <= iso) mtd += v.t;
  }
  return { total, mtd, filled, n };
}

function slideHtml(iso) {
  const day = state.days[iso];
  const rel = relLabel(iso, state.today);
  const weekend = [0, 6].includes(parseISO(iso).getDay());
  const head = `
    <div class="hero-top">
      <span class="pill ${rel === 'Hari ini' ? 'pill-live' : ''}">${esc(rel || dayName(iso))}</span>
      <span class="hero-day">${rel ? dayName(iso) : ''}${weekend ? ' <span class="badge-we">Weekend</span>' : ''}</span>
    </div>
    <div class="hero-date">${dateLong(iso)}</div>`;

  if (!day || !day.t) {
    return `
      <div class="hero hero-empty">${head}
        <div class="hero-label">Store Budget</div>
        <div class="hero-amount">–</div>
      </div>
      <div class="card empty">
        ${icons.empty}
        <h4>Belum ada budget</h4>
        <p class="muted">Data budget untuk ${esc(dateLong(iso))} belum di-upload.</p>
        ${isAdmin() ? '<button class="btn btn-primary" data-goto-upload>Upload Budget</button>' : ''}
      </div>`;
  }

  const prevIso = addDays(iso, -7);
  const prev = state.days[prevIso];
  const ms = monthStats(iso);
  let cmp = `<div class="stat"><small>vs ${dayShort(iso)} lalu</small><b>–</b><small>${dateShort(prevIso)}</small></div>`;
  if (prev?.t) {
    const g = day.t / prev.t - 1;
    cmp = `<div class="stat"><small>vs ${dayShort(iso)} lalu</small>
      <b class="${g >= 0 ? 'up' : 'down'}">${g >= 0 ? '▲' : '▼'} ${fmtPct(Math.abs(g))}</b>
      <small>${fmtShort(prev.t, true)}</small></div>`;
  }
  const share = ms.total ? day.t / ms.total : 0;
  const mtdPct = ms.total ? ms.mtd / ms.total : 0;

  const divs = state.divisions
    .map((d, i) => ({ ...d, color: colorAt(i), amt: day.d[d.code] ?? 0 }))
    .filter((d) => d.amt || day.d[d.code] !== undefined);
  // divisi yang ada di data tapi tidak aktif di master
  Object.keys(day.d).forEach((c) => {
    if (!divs.some((d) => d.code === Number(c))) divs.push({ code: Number(c), name: `Divisi ${c}`, color: '#94a3b8', amt: day.d[c] });
  });
  if (state.sortMode === 'amount') divs.sort((a, b) => b.amt - a.amt);
  const max = Math.max(...divs.map((d) => d.amt), 1);

  return `
    <div class="hero">${head}
      <div class="hero-label">Store Budget</div>
      <div class="hero-amount">${fmtRp(day.t)}</div>
      <div class="hero-stats">
        ${cmp}
        <div class="stat"><small>Porsi bulan ini</small><b>${fmtPct(share)}</b><small>dari ${fmtShort(ms.total, true)}</small></div>
      </div>
      <div class="mtd">
        <div class="mtd-row"><span>Kumulatif ${monthLabel(ymOf(iso)).split(' ')[0]} s/d tgl ${parseISO(iso).getDate()}</span><b>${fmtPct(mtdPct, 0)}</b></div>
        <div class="bar"><i style="width:${(mtdPct * 100).toFixed(1)}%"></i></div>
        <div class="mtd-row sub"><span>${fmtShort(ms.mtd, true)}</span><span>${fmtShort(ms.total, true)}</span></div>
      </div>
    </div>
    ${day.n ? `<div class="note">${icons.note}<span>${esc(day.n)}</span></div>` : ''}
    <div class="card">
      <div class="section-head">
        <h3>Divisi <span class="count">${divs.length}</span></h3>
        <div class="seg seg-sm">
          <button data-sort="order" class="${state.sortMode === 'order' ? 'active' : ''}">Urutan</button>
          <button data-sort="amount" class="${state.sortMode === 'amount' ? 'active' : ''}">Terbesar</button>
        </div>
      </div>
      <ul class="divlist">
        ${divs.map((d) => `
          <li data-div="${d.code}" data-date="${iso}" tabindex="0">
            <span class="code" style="--c:${d.color}">${d.code}</span>
            <div class="dv-main">
              <span class="dv-name">${esc(d.name)}</span>
              <div class="dv-bar"><i style="width:${((d.amt / max) * 100).toFixed(1)}%;background:${d.color}"></i></div>
            </div>
            <div class="dv-right"><span class="dv-amt">${fmtRp(d.amt)}</span><span class="dv-pct">${pct(d.amt / day.t)}</span></div>
          </li>`).join('')}
      </ul>
      <div class="divlist-foot"><span>Total Store Budget</span><b>${fmtRp(day.t)}</b></div>
    </div>`;
}

function openDivision(code, iso) {
  const idx = state.divisions.findIndex((d) => d.code === code);
  const div = state.divisions[idx] || { code, name: `Divisi ${code}` };
  const color = idx >= 0 ? colorAt(idx) : '#94a3b8';
  const ym = ymOf(iso);
  const n = daysInMonth(ym);
  const series = [];
  for (let i = 1; i <= n; i++) {
    const d = `${ym}-${String(i).padStart(2, '0')}`;
    series.push({ d, v: state.days[d]?.d[code] ?? 0, t: state.days[d]?.t ?? 0 });
  }
  const total = series.reduce((a, s) => a + s.v, 0);
  const storeTotal = series.reduce((a, s) => a + s.t, 0);
  const filled = series.filter((s) => s.v);
  const max = Math.max(...series.map((s) => s.v), 1);
  const day = series.find((s) => s.d === iso);

  openSheet({
    title: `${div.code} • ${div.name} — ${monthLabel(ym)}`,
    html: `
      <div class="kpis kpis-3">
        <div class="kpi"><small>${dateShort(iso)}</small><b>${fmtShort(day?.v, true)}</b></div>
        <div class="kpi"><small>Total bulan</small><b>${fmtShort(total, true)}</b></div>
        <div class="kpi"><small>Porsi bulan</small><b>${pct(storeTotal ? total / storeTotal : 0)}</b></div>
      </div>
      <div class="minichart" style="--c:${color}">
        ${series.map((s) => `<div class="mc-col ${s.d === iso ? 'on' : ''} ${[0, 6].includes(parseISO(s.d).getDay()) ? 'we' : ''}" title="${dateShort(s.d)}: ${fmtRp(s.v)}">
          <i style="height:${((s.v / max) * 100).toFixed(1)}%"></i><span>${[1, 5, 10, 15, 20, 25, 30].includes(parseISO(s.d).getDate()) || s.d === iso ? parseISO(s.d).getDate() : ''}</span></div>`).join('')}
      </div>
      <p class="muted small">Rata-rata ${fmtRp(filled.length ? total / filled.length : 0)} per hari (${filled.length} hari terisi).</p>`,
  });
}
