import { state, isAdmin } from './core/state.js';
import { loadMeta } from './core/data.js';
import { icons } from './ui/icons.js';
import { toast } from './ui/toast.js';
import { initDaily, showDaily } from './features/daily.js';
import { initMonthly, showMonthly } from './features/monthly.js';
import { initUpload, showUpload } from './features/upload.js';
import { initAdmin, showAdmin } from './features/admin.js';
import { openAccount } from './features/account.js';

const VIEWS = [
  { id: 'harian', label: 'Harian', icon: icons.today, show: showDaily },
  { id: 'bulanan', label: 'Bulanan', icon: icons.month, show: showMonthly },
  { id: 'upload', label: 'Upload', icon: icons.upload, show: showUpload, admin: true },
  { id: 'admin', label: 'Admin', icon: icons.admin, show: showAdmin, admin: true },
];

function allowed() {
  return VIEWS.filter((v) => !v.admin || isAdmin());
}

function go(id) {
  if (location.hash !== '#' + id) location.hash = id;
  else route();
}

function route() {
  const list = allowed();
  const id = location.hash.slice(1);
  const view = list.find((v) => v.id === id) || list[0];
  state.view = view.id;
  document.querySelectorAll('.view').forEach((s) => { s.hidden = s.dataset.view !== view.id; });
  document.querySelectorAll('[data-nav]').forEach((b) => b.classList.toggle('active', b.dataset.nav === view.id));
  window.scrollTo(0, 0);
  view.show();
}

function renderNav() {
  const html = allowed().map((v) => `<a href="#${v.id}" data-nav="${v.id}">${v.icon}<span>${v.label}</span></a>`).join('');
  document.getElementById('tabBar').innerHTML = html;
  document.getElementById('sideNav').innerHTML = html;
  document.body.classList.toggle('one-tab', allowed().length < 3);
}

function renderTitle() {
  document.getElementById('storeTitle').textContent = state.settings.store_title || '';
}

async function boot() {
  try {
    await loadMeta();
  } catch (e) {
    toast(e.message, 'error', 6000);
    return;
  }
  window.APP_VERSION = '1.0.0';
  renderTitle();
  renderNav();
  initDaily(document.getElementById('view-harian'), { go });
  initMonthly(document.getElementById('view-bulanan'), { go });
  if (isAdmin()) {
    initUpload(document.getElementById('view-upload'));
    initAdmin(document.getElementById('view-admin'), { onSettings: renderTitle });
  }
  document.getElementById('btnAccount').addEventListener('click', openAccount);
  window.addEventListener('hashchange', route);
  route();

  // tanggal berganti saat aplikasi dibiarkan terbuka semalaman
  document.addEventListener('visibilitychange', async () => {
    if (document.visibilityState !== 'visible') return;
    const before = state.today;
    try { await loadMeta(); } catch { return; }
    if (state.today !== before && state.current === before) { state.current = state.today; if (state.view === 'harian') showDaily(); }
  });

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
}

boot();
