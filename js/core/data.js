import { state } from './state.js';
import { addMonths, daysInMonth } from './helpers.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

export class ApiError extends Error {}

function handleAuth(status, body) {
  if (status === 401) { location.href = 'login.php'; throw new ApiError('Sesi berakhir'); }
  if (status === 403 && body?.code === 'must_change') { location.href = 'change_password.php'; throw new ApiError('Ganti password'); }
}

export async function api(path, { method = 'GET', body } = {}) {
  let res;
  try {
    res = await fetch('api/' + path, {
      method,
      credentials: 'same-origin',
      headers: method === 'GET' ? {} : { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError('Tidak ada koneksi internet.');
  }
  let data = null;
  try { data = await res.json(); } catch { /* bukan JSON */ }
  handleAuth(res.status, data);
  if (!res.ok || !data?.ok) throw new ApiError(data?.error || `Terjadi kesalahan server (${res.status}).`);
  return data;
}

export function uploadFile(path, file, onProgress) {
  return new Promise((resolve, reject) => {
    const fd = new FormData();
    fd.append('file', file);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/' + path);
    xhr.setRequestHeader('X-CSRF-Token', csrf());
    xhr.upload.onprogress = (e) => e.lengthComputable && onProgress?.(e.loaded / e.total);
    xhr.onload = () => {
      let data = null;
      try { data = JSON.parse(xhr.responseText); } catch { /* */ }
      try { handleAuth(xhr.status, data); } catch (e) { return reject(e); }
      if (xhr.status >= 200 && xhr.status < 300 && data?.ok) resolve(data);
      else reject(new ApiError(data?.error || `Upload gagal (${xhr.status}).`));
    };
    xhr.onerror = () => reject(new ApiError('Upload gagal, periksa koneksi internet.'));
    xhr.send(fd);
  });
}

export async function loadMeta() {
  const m = await api('meta.php');
  state.me = m.user;
  state.settings = m.settings;
  state.divisions = m.divisions;
  state.today = m.today;
  state.range = m.range;
  return m;
}

const pending = new Map();

/** Pastikan data bulan-bulan ini sudah ada di cache. */
export async function ensureMonths(yms) {
  const need = [...new Set(yms)].filter((ym) => !state.loadedMonths.has(ym)).sort();
  if (!need.length) return;
  const key = need.join(',');
  if (!pending.has(key)) {
    const from = need[0] + '-01';
    const last = need[need.length - 1];
    const to = `${last}-${String(daysInMonth(last)).padStart(2, '0')}`;
    pending.set(key, api(`days.php?from=${from}&to=${to}`).then((r) => {
      Object.assign(state.days, r.days);
      // tandai semua bulan dalam rentang sebagai sudah dimuat
      for (let ym = need[0]; ym <= last; ym = addMonths(ym, 1)) state.loadedMonths.add(ym);
    }).finally(() => pending.delete(key)));
  }
  return pending.get(key);
}
