import { state } from '../core/state.js';
import { api } from '../core/data.js';
import { esc } from '../core/helpers.js';
import { openSheet } from '../ui/sheet.js';
import { toast } from '../ui/toast.js';

let installPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); installPrompt = e; });

export function openAccount() {
  const me = state.me;
  openSheet({
    title: 'Akun',
    html: `
      <div class="acct">
        <span class="avatar lg">${esc(me.name.slice(0, 1).toUpperCase())}</span>
        <div><b>${esc(me.name)}</b><small class="muted">@${esc(me.username)} • ${me.role === 'admin' ? 'Admin' : 'Viewer'}</small></div>
      </div>
      <div class="menu">
        <button class="menu-item" data-m="pw">Ganti password</button>
        ${installPrompt ? '<button class="menu-item" data-m="install">Pasang aplikasi di HP</button>' : ''}
        <button class="menu-item danger" data-m="logout">Keluar</button>
      </div>
      <p class="muted small center">Budget Harian v${esc(window.APP_VERSION || '')}</p>`,
    onMount: ({ el, close }) => el.addEventListener('click', async (e) => {
      const m = e.target.closest('[data-m]')?.dataset.m;
      if (m === 'logout') document.getElementById('logoutForm').submit();
      if (m === 'install' && installPrompt) {
        installPrompt.prompt();
        await installPrompt.userChoice;
        installPrompt = null;
        close();
      }
      if (m === 'pw') { close(); changePassword(); }
    }),
  });
}

function changePassword() {
  openSheet({
    title: 'Ganti Password',
    html: `<form class="form" id="pwForm">
      <label class="field"><span>Password lama</span><input type="password" name="old" required autocomplete="current-password"></label>
      <label class="field"><span>Password baru</span><input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
      <label class="field"><span>Ulangi password baru</span><input type="password" name="new2" required minlength="8" autocomplete="new-password"></label>
      <p class="hint">Minimal 8 karakter, berisi huruf dan angka.</p>
      <div class="btn-row"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan</button></div>
    </form>`,
    onMount: ({ el, close }) => el.querySelector('#pwForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = new FormData(e.target);
      if (f.get('new') !== f.get('new2')) return toast('Konfirmasi password tidak sama', 'error');
      try {
        await api('account.php', { method: 'POST', body: { old_password: f.get('old'), new_password: f.get('new') } });
        close();
        toast('Password berhasil diganti', 'success');
      } catch (err) { toast(err.message, 'error'); }
    }),
  });
}
