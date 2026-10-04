import { state } from '../core/state.js';
import { api, loadMeta } from '../core/data.js';
import { esc, fmtDateTime, colorAt } from '../core/helpers.js';
import { icons } from '../ui/icons.js';
import { toast } from '../ui/toast.js';
import { openSheet, confirmSheet } from '../ui/sheet.js';

let root;
let tab = 'users';
let users = [];
let divisions = [];
let onSettingsSaved;

export function initAdmin(el, { onSettings }) {
  root = el;
  onSettingsSaved = onSettings;
  root.innerHTML = `
    <div class="seg seg-full" id="adminSeg">
      <button data-tab="users">Pengguna</button>
      <button data-tab="divisions">Divisi</button>
      <button data-tab="settings">Pengaturan</button>
    </div>
    <div id="adminBody"></div>`;
  root.querySelector('#adminSeg').addEventListener('click', (e) => {
    const b = e.target.closest('[data-tab]');
    if (b) { tab = b.dataset.tab; showAdmin(); }
  });
  root.querySelector('#adminBody').addEventListener('click', onClick);
}

export function showAdmin() {
  root.querySelectorAll('#adminSeg button').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
  if (tab === 'users') loadUsers();
  if (tab === 'divisions') loadDivisions();
  if (tab === 'settings') renderSettings();
}

const body = () => root.querySelector('#adminBody');

/* ---------------- Pengguna ---------------- */
async function loadUsers() {
  body().innerHTML = '<div class="card skeleton" style="height:240px"></div>';
  try {
    users = (await api('users.php')).users;
    renderUsers();
  } catch (e) { body().innerHTML = `<div class="alert alert-err">${esc(e.message)}</div>`; }
}

function renderUsers() {
  body().innerHTML = `
    <div class="card">
      <div class="section-head"><h3>Pengguna <span class="count">${users.length}</span></h3>
        <button class="btn btn-primary btn-sm" data-a="add-user">${icons.plus}<span>Tambah</span></button></div>
      <div class="ulist">
        ${users.map((u) => `
          <div class="urow ${u.active ? '' : 'off'}">
            <span class="avatar sm">${esc(u.name.slice(0, 1).toUpperCase())}</span>
            <div class="u-main">
              <b>${esc(u.name)}</b>
              <small class="muted">@${esc(u.username)} • login: ${u.last_login_at ? fmtDateTime(u.last_login_at) : 'belum pernah'}</small>
              <div class="hist-tags">
                <span class="tag ${u.role === 'admin' ? 'tag-pri' : ''}">${u.role === 'admin' ? 'Admin' : 'Viewer'}</span>
                ${u.active ? '' : '<span class="tag tag-warn">Nonaktif</span>'}
                ${u.must_change_pw ? '<span class="tag">Wajib ganti password</span>' : ''}
              </div>
            </div>
            <div class="u-act">
              <button class="btn btn-ghost btn-sm" data-a="edit-user" data-id="${u.id}">Ubah</button>
              <button class="btn btn-ghost btn-sm" data-a="reset-user" data-id="${u.id}">Reset</button>
            </div>
          </div>`).join('')}
      </div>
    </div>`;
}

function showTempPassword(username, pw) {
  openSheet({
    title: 'Password sementara',
    html: `<p class="muted">Berikan ke <b>${esc(username)}</b>. Pengguna wajib mengganti password saat login pertama.</p>
      <div class="temp-pw"><code>${esc(pw)}</code><button class="btn btn-soft btn-sm" id="copyPw">Salin</button></div>`,
    onMount: ({ el }) => el.querySelector('#copyPw').addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(pw); toast('Disalin', 'success'); } catch { toast('Salin manual ya', 'info'); }
    }),
  });
}

function userForm(u) {
  const isNew = !u;
  openSheet({
    title: isNew ? 'Tambah Pengguna' : `Ubah ${u.name}`,
    html: `
      <form class="form" id="uForm">
        ${isNew ? '<label class="field"><span>Username</span><input name="username" autocapitalize="none" required pattern="[a-z0-9._\\-]{3,50}" placeholder="mis. budi"></label>' : ''}
        <label class="field"><span>Nama</span><input name="name" required value="${esc(u?.name || '')}"></label>
        <div class="field"><span>Peran</span>
          <div class="seg seg-full" id="roleSeg">
            <button type="button" data-role="viewer">Viewer (lihat saja)</button>
            <button type="button" data-role="admin">Admin</button>
          </div></div>
        ${isNew ? '' : `<label class="check"><input type="checkbox" name="active" ${u.active ? 'checked' : ''}> Akun aktif</label>`}
        <div class="btn-row"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan</button></div>
      </form>`,
    onMount: ({ el, close }) => {
      let role = u?.role || 'viewer';
      const seg = el.querySelector('#roleSeg');
      const paint = () => seg.querySelectorAll('button').forEach((b) => b.classList.toggle('active', b.dataset.role === role));
      paint();
      seg.addEventListener('click', (e) => { const b = e.target.closest('[data-role]'); if (b) { role = b.dataset.role; paint(); } });
      el.querySelector('#uForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = new FormData(e.target);
        try {
          if (isNew) {
            const r = await api('users.php', { method: 'POST', body: { action: 'create', username: f.get('username'), name: f.get('name'), role } });
            close();
            showTempPassword(f.get('username'), r.temp_password);
          } else {
            await api('users.php', { method: 'POST', body: { action: 'update', id: u.id, name: f.get('name'), role, active: f.get('active') ? 1 : 0 } });
            close();
            toast('Pengguna diperbarui', 'success');
          }
          loadUsers();
        } catch (err) { toast(err.message, 'error'); }
      });
    },
  });
}

/* ---------------- Divisi ---------------- */
async function loadDivisions() {
  body().innerHTML = '<div class="card skeleton" style="height:240px"></div>';
  try {
    divisions = (await api('divisions.php')).divisions;
    renderDivisions();
  } catch (e) { body().innerHTML = `<div class="alert alert-err">${esc(e.message)}</div>`; }
}

function renderDivisions() {
  body().innerHTML = `
    <div class="card">
      <div class="section-head"><h3>Divisi <span class="count">${divisions.length}</span></h3>
        <button class="btn btn-primary btn-sm" data-a="add-div">${icons.plus}<span>Tambah</span></button></div>
      <p class="muted small">Atur nama, urutan tampil, dan status aktif. Kode divisi harus sama dengan header di file upload.</p>
      <div class="dlist">
        ${divisions.map((d, i) => `
          <div class="drow ${d.active ? '' : 'off'}" data-i="${i}">
            <span class="code" style="--c:${colorAt(i)}">${d.code}</span>
            <input class="dname" value="${esc(d.name)}" aria-label="Nama divisi ${d.code}">
            <label class="switch" title="Aktif"><input type="checkbox" class="dactive" ${d.active ? 'checked' : ''}><span></span></label>
            <div class="dmove">
              <button class="icon-btn sm" data-a="up" data-i="${i}" aria-label="Naik" ${i === 0 ? 'disabled' : ''}>${icons.up}</button>
              <button class="icon-btn sm" data-a="down" data-i="${i}" aria-label="Turun" ${i === divisions.length - 1 ? 'disabled' : ''}>${icons.down}</button>
            </div>
            <button class="icon-btn sm danger" data-a="del-div" data-code="${d.code}" aria-label="Hapus">✕</button>
          </div>`).join('')}
      </div>
      <div class="btn-row"><button class="btn btn-primary" data-a="save-div">Simpan Perubahan</button></div>
    </div>`;
}

function collectDivisions() {
  body().querySelectorAll('.drow').forEach((row) => {
    const d = divisions[Number(row.dataset.i)];
    d.name = row.querySelector('.dname').value;
    d.active = row.querySelector('.dactive').checked ? 1 : 0;
  });
}

/* ---------------- Pengaturan ---------------- */
function renderSettings() {
  body().innerHTML = `
    <div class="card">
      <div class="section-head"><h3>Pengaturan Toko</h3></div>
      <form class="form" id="setForm">
        <label class="field"><span>Nama toko (judul laporan)</span>
          <input name="store_title" required maxlength="100" value="${esc(state.settings.store_title || '')}"></label>
        <div class="btn-row"><button class="btn btn-primary">Simpan</button></div>
      </form>
    </div>`;
  body().querySelector('#setForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await api('settings.php', { method: 'POST', body: { store_title: new FormData(e.target).get('store_title') } });
      await loadMeta();
      onSettingsSaved?.();
      toast('Pengaturan disimpan', 'success');
    } catch (err) { toast(err.message, 'error'); }
  });
}

async function onClick(e) {
  const b = e.target.closest('[data-a]');
  if (!b) return;
  const a = b.dataset.a;
  if (a === 'add-user') userForm(null);
  if (a === 'edit-user') userForm(users.find((u) => u.id === Number(b.dataset.id)));
  if (a === 'reset-user') {
    const u = users.find((x) => x.id === Number(b.dataset.id));
    if (!(await confirmSheet({ title: 'Reset password?', message: `Password ${u.name} akan diganti dengan password sementara.`, okText: 'Reset', danger: true }))) return;
    try {
      const r = await api('users.php', { method: 'POST', body: { action: 'reset', id: u.id } });
      showTempPassword(u.username, r.temp_password);
      loadUsers();
    } catch (err) { toast(err.message, 'error'); }
  }
  if (a === 'up' || a === 'down') {
    collectDivisions();
    const i = Number(b.dataset.i);
    const j = a === 'up' ? i - 1 : i + 1;
    [divisions[i], divisions[j]] = [divisions[j], divisions[i]];
    renderDivisions();
  }
  if (a === 'save-div') {
    collectDivisions();
    try {
      await api('divisions.php', { method: 'POST', body: { action: 'save', divisions } });
      await loadMeta();
      toast('Divisi disimpan', 'success');
      loadDivisions();
    } catch (err) { toast(err.message, 'error'); }
  }
  if (a === 'del-div') {
    const code = Number(b.dataset.code);
    if (!(await confirmSheet({ title: 'Hapus divisi?', message: `Divisi ${code} akan dihapus. Hanya bisa jika belum ada data budget.`, okText: 'Hapus', danger: true }))) return;
    try {
      await api('divisions.php', { method: 'POST', body: { action: 'delete', code } });
      await loadMeta();
      toast('Divisi dihapus', 'success');
      loadDivisions();
    } catch (err) { toast(err.message, 'error'); }
  }
  if (a === 'add-div') {
    openSheet({
      title: 'Tambah Divisi',
      html: `<form class="form" id="dForm">
        <label class="field"><span>Kode</span><input name="code" type="number" min="1" max="9999" required inputmode="numeric"></label>
        <label class="field"><span>Nama</span><input name="name" required maxlength="100"></label>
        <div class="btn-row"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Tambah</button></div></form>`,
      onMount: ({ el, close }) => el.querySelector('#dForm').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const f = new FormData(ev.target);
        try {
          await api('divisions.php', { method: 'POST', body: { action: 'create', code: Number(f.get('code')), name: f.get('name') } });
          close();
          await loadMeta();
          toast('Divisi ditambahkan', 'success');
          loadDivisions();
        } catch (err) { toast(err.message, 'error'); }
      }),
    });
  }
}
