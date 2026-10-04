import { state, resetBudgetCache } from '../core/state.js';
import { api, uploadFile, loadMeta } from '../core/data.js';
import { fmtRp, fmtShort, fmtNum, dateMed, dateShort, dayShort, esc, fmtDateTime, ymOf, addMonths } from '../core/helpers.js';
import { icons } from '../ui/icons.js';
import { toast } from '../ui/toast.js';
import { confirmSheet } from '../ui/sheet.js';

let root;
let preview = null;

export function initUpload(el) {
  root = el;
  root.innerHTML = `
    <div class="card">
      <div class="section-head"><h3>Upload Budget</h3></div>
      <p class="muted small">Gunakan format sheet <b>DATA</b> seperti MASTER_SHARE_BUDGET: baris nama divisi, baris header
      <code>Date · Day · Note · total · kode divisi</code>, lalu satu baris per tanggal. File .xlsx atau .csv.
      Tanggal yang sudah ada akan <b>diganti</b> dengan data baru.</p>
      <label class="dropzone" id="dropzone">
        <input type="file" id="fileInput" accept=".xlsx,.csv" hidden>
        <span class="dz-icon">${icons.upload}</span>
        <b>Pilih file atau tarik ke sini</b>
        <small class="muted">.xlsx / .csv • maks 10 MB</small>
      </label>
      <div class="progress" id="progress" hidden><i></i><span>0%</span></div>
      <div id="previewBox"></div>
      <details class="template">
        <summary>${icons.file} Unduh template kosong</summary>
        <div class="template-row">
          <input type="month" id="tplMonth" aria-label="Bulan template">
          <button class="btn btn-soft" id="tplBtn">${icons.download}<span>Template</span></button>
        </div>
      </details>
    </div>
    <div class="card">
      <div class="section-head"><h3>Riwayat Upload</h3><button class="link" id="reloadHist">Muat ulang</button></div>
      <div id="history" class="history"><div class="skeleton" style="height:80px"></div></div>
    </div>`;

  const input = root.querySelector('#fileInput');
  const dz = root.querySelector('#dropzone');
  input.addEventListener('change', () => input.files[0] && doPreview(input.files[0]));
  ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('drag'); }));
  ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('drag'); }));
  dz.addEventListener('drop', (e) => e.dataTransfer.files[0] && doPreview(e.dataTransfer.files[0]));

  const tpl = root.querySelector('#tplMonth');
  tpl.value = addMonths(ymOf(state.today), 1);
  root.querySelector('#tplBtn').addEventListener('click', () => {
    if (tpl.value) location.href = `api/template.php?ym=${tpl.value}`;
  });
  root.querySelector('#reloadHist').addEventListener('click', loadHistory);
  root.querySelector('#previewBox').addEventListener('click', onPreviewClick);
}

export function showUpload() {
  loadHistory();
}

async function doPreview(file) {
  if (preview) await cancel(true);
  const prog = root.querySelector('#progress');
  const bar = prog.querySelector('i');
  const label = prog.querySelector('span');
  prog.hidden = false;
  bar.style.width = '0%';
  label.textContent = 'Mengunggah…';
  root.querySelector('#previewBox').innerHTML = '';
  try {
    const res = await uploadFile('upload.php?action=preview', file, (p) => {
      bar.style.width = `${Math.round(p * 100)}%`;
      label.textContent = p < 1 ? `${Math.round(p * 100)}%` : 'Membaca file…';
    });
    preview = res;
    renderPreview();
  } catch (e) {
    toast(e.message, 'error', 5000);
    root.querySelector('#previewBox').innerHTML = `<div class="alert alert-err">${esc(e.message)}</div>`;
  } finally {
    prog.hidden = true;
    root.querySelector('#fileInput').value = '';
  }
}

function renderPreview() {
  const p = preview;
  const hasErr = p.errors.length > 0;
  const days = Object.entries(p.day_totals);
  root.querySelector('#previewBox').innerHTML = `
    <div class="preview">
      <div class="preview-head">${icons.file}<div><b>${esc(p.file)}</b><small class="muted">Sheet: ${esc(p.sheet)}</small></div></div>
      <div class="kpis">
        <div class="kpi"><small>Periode</small><b class="sm">${p.date_from ? `${dateShort(p.date_from)} – ${dateShort(p.date_to)} ${p.date_to.slice(0, 4)}` : '–'}</b></div>
        <div class="kpi"><small>Jumlah hari</small><b>${p.days_count}</b></div>
        <div class="kpi"><small>Total budget</small><b class="sm">${fmtShort(p.total, true)}</b></div>
        <div class="kpi ${p.replaced_days ? 'kpi-warn' : ''}"><small>Data lama diganti</small><b>${p.replaced_days} hari</b></div>
      </div>
      ${hasErr ? `<div class="alert alert-err"><b>${p.errors.length} error – perbaiki file lalu upload ulang:</b><ul>${p.errors.map((e) => `<li>${esc(e)}</li>`).join('')}</ul></div>` : ''}
      ${p.warnings.length ? `<details class="alert alert-warn" ${hasErr ? '' : 'open'}><summary><b>${p.warnings.length} peringatan</b></summary><ul>${p.warnings.map((e) => `<li>${esc(e)}</li>`).join('')}</ul></details>` : ''}
      <details class="pv-detail">
        <summary>Total per divisi (${p.divisions.length})</summary>
        <table class="tbl"><tbody>${p.divisions.map((d) => `<tr><td><span class="code sm">${d.code}</span> ${esc(d.name)}${d.new ? ' <span class="badge">baru</span>' : ''}</td><td class="num">${fmtNum(d.total)}</td></tr>`).join('')}</tbody></table>
      </details>
      <details class="pv-detail">
        <summary>Total per tanggal (${days.length})</summary>
        <table class="tbl"><tbody>${days.map(([d, v]) => `<tr><td>${dayShort(d)}, ${dateShort(d)}${v.n ? ` <span class="muted small">• ${esc(v.n)}</span>` : ''}</td><td class="num">${fmtNum(v.t)}</td></tr>`).join('')}</tbody></table>
      </details>
      <div class="btn-row sticky-actions">
        <button class="btn btn-ghost" data-pv="cancel">Batal</button>
        <button class="btn btn-primary" data-pv="commit" ${hasErr ? 'disabled' : ''}>Simpan</button>
      </div>
    </div>`;
}

async function onPreviewClick(e) {
  const act = e.target.closest('[data-pv]')?.dataset.pv;
  if (act === 'cancel') cancel();
  if (act === 'commit') commit(e.target.closest('button'));
}

async function cancel(silent = false) {
  if (!preview) return;
  const token = preview.token;
  preview = null;
  root.querySelector('#previewBox').innerHTML = '';
  try { await api('upload.php?action=cancel', { method: 'POST', body: { token } }); } catch { /* abaikan */ }
  if (!silent) toast('Upload dibatalkan');
}

async function commit(btn) {
  if (!preview) return;
  if (preview.replaced_days) {
    const ok = await confirmSheet({
      title: 'Ganti data lama?',
      message: `${preview.replaced_days} hari yang sudah ada akan diganti dengan data dari file ini.`,
      okText: 'Ya, simpan',
    });
    if (!ok) return;
  }
  btn.disabled = true;
  btn.textContent = 'Menyimpan…';
  try {
    const r = await api('upload.php?action=commit', { method: 'POST', body: { token: preview.token } });
    preview = null;
    root.querySelector('#previewBox').innerHTML = `<div class="alert alert-ok">Berhasil menyimpan ${r.days} hari (${dateMed(r.date_from)} – ${dateMed(r.date_to)}).</div>`;
    toast('Budget berhasil disimpan', 'success');
    resetBudgetCache();
    await loadMeta();
    loadHistory();
  } catch (e) {
    toast(e.message, 'error', 5000);
    btn.disabled = false;
    btn.textContent = 'Coba simpan lagi';
  }
}

async function loadHistory() {
  const box = root.querySelector('#history');
  try {
    const { uploads } = await api('uploads.php');
    if (!uploads.length) { box.innerHTML = '<p class="muted small">Belum ada upload.</p>'; return; }
    box.innerHTML = uploads.map((u) => `
      <div class="hist">
        <div class="hist-main">
          <b>${esc(u.original_name)}</b>
          <small class="muted">${fmtDateTime(u.created_at)} • ${esc(u.user_name || '-')}</small>
          <div class="hist-tags">
            <span class="tag">${dateShort(u.date_from)} – ${dateShort(u.date_to)} ${u.date_to.slice(0, 4)}</span>
            <span class="tag">${u.days} hari</span>
            <span class="tag">${fmtShort(u.total, true)}</span>
            ${u.replaced_days ? `<span class="tag tag-warn">${u.replaced_days} diganti</span>` : ''}
          </div>
        </div>
        ${u.has_file ? `<a class="icon-btn" href="api/uploads.php?download=${u.id}" title="Unduh file asli" aria-label="Unduh file asli">${icons.download}</a>` : ''}
      </div>`).join('');
  } catch (e) {
    box.innerHTML = `<div class="alert alert-err">${esc(e.message)}</div>`;
  }
}
