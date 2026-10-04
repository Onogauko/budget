const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const HARI_S = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const BULAN_S = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const nf = new Intl.NumberFormat('id-ID');

export const fmtNum = (n) => nf.format(Math.round(n || 0));
export const fmtRp = (n) => 'Rp ' + fmtNum(n);

export function fmtShort(n, withRp = false) {
  const a = Math.abs(n || 0);
  let s;
  const d = (x, p) => x.toLocaleString('id-ID', { maximumFractionDigits: p, minimumFractionDigits: 0 });
  if (a >= 1e12) s = d(n / 1e12, 2) + ' T';
  else if (a >= 1e9) s = d(n / 1e9, 2) + ' M';
  else if (a >= 1e6) s = d(n / 1e6, a >= 1e8 ? 0 : 1) + ' jt';
  else if (a >= 1e3) s = d(n / 1e3, 0) + ' rb';
  else s = d(n || 0, 0);
  return (withRp ? 'Rp ' : '') + s;
}

export function fmtPct(x, digits = 1) {
  if (!isFinite(x)) return '–';
  return (x * 100).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits }) + '%';
}

export function parseISO(s) {
  const [y, m, d] = s.split('-').map(Number);
  return new Date(y, m - 1, d);
}
export function toISO(dt) {
  const p = (n) => String(n).padStart(2, '0');
  return `${dt.getFullYear()}-${p(dt.getMonth() + 1)}-${p(dt.getDate())}`;
}
export function addDays(iso, n) {
  const dt = parseISO(iso);
  dt.setDate(dt.getDate() + n);
  return toISO(dt);
}
export const diffDays = (a, b) => Math.round((parseISO(b) - parseISO(a)) / 86400000);
export const ymOf = (iso) => iso.slice(0, 7);
export function addMonths(ym, n) {
  const [y, m] = ym.split('-').map(Number);
  const dt = new Date(y, m - 1 + n, 1);
  return toISO(dt).slice(0, 7);
}
export const daysInMonth = (ym) => { const [y, m] = ym.split('-').map(Number); return new Date(y, m, 0).getDate(); };
export const dayName = (iso) => HARI[parseISO(iso).getDay()];
export const dayShort = (iso) => HARI_S[parseISO(iso).getDay()];
export const dayIdx = (iso) => parseISO(iso).getDay();
export const dateLong = (iso) => { const d = parseISO(iso); return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`; };
export const dateShort = (iso) => { const d = parseISO(iso); return `${d.getDate()} ${BULAN_S[d.getMonth()]}`; };
export const dateMed = (iso) => { const d = parseISO(iso); return `${HARI_S[d.getDay()]}, ${d.getDate()} ${BULAN_S[d.getMonth()]} ${d.getFullYear()}`; };
export const monthLabel = (ym) => { const [y, m] = ym.split('-').map(Number); return `${BULAN[m - 1]} ${y}`; };
export const HARI_SHORT = HARI_S;

export function relLabel(iso, today) {
  const n = diffDays(today, iso);
  return { 0: 'Hari ini', 1: 'Besok', 2: 'Lusa', [-1]: 'Kemarin' }[n] ?? null;
}

export function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export function fmtDateTime(s) {
  if (!s) return '–';
  const [d, t] = s.split(' ');
  return `${dateShort(d)} ${d.slice(0, 4)} ${t ? t.slice(0, 5) : ''}`;
}

const PALETTE = ['#7c3aed', '#db2777', '#0891b2', '#16a34a', '#ea580c', '#2563eb', '#ca8a04', '#9333ea',
  '#0d9488', '#dc2626', '#4f46e5', '#65a30d', '#c026d3', '#0284c7', '#d97706', '#e11d48'];
export const colorAt = (i) => PALETTE[i % PALETTE.length];

export function debounce(fn, ms) {
  let t;
  return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}
