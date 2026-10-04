import { esc } from '../core/helpers.js';

/** Bottom-sheet (HP) / dialog (laptop). */
export function openSheet({ title, html, onMount, wide = false }) {
  const root = document.getElementById('sheetRoot');
  const wrap = document.createElement('div');
  wrap.className = 'sheet-wrap';
  wrap.innerHTML = `
    <div class="sheet-backdrop" data-close></div>
    <div class="sheet ${wide ? 'sheet-wide' : ''}" role="dialog" aria-modal="true" aria-label="${esc(title)}">
      <div class="sheet-grip"></div>
      <div class="sheet-head"><h3>${esc(title)}</h3><button class="icon-btn" data-close aria-label="Tutup">✕</button></div>
      <div class="sheet-body">${html}</div>
    </div>`;
  root.appendChild(wrap);
  document.body.classList.add('no-scroll');
  requestAnimationFrame(() => wrap.classList.add('open'));

  const onKey = (e) => e.key === 'Escape' && close();
  const close = () => {
    wrap.classList.remove('open');
    document.removeEventListener('keydown', onKey);
    setTimeout(() => {
      wrap.remove();
      if (!root.children.length) document.body.classList.remove('no-scroll');
    }, 250);
  };
  wrap.addEventListener('click', (e) => { if (e.target.closest('[data-close]')) close(); });
  document.addEventListener('keydown', onKey);

  // geser ke bawah untuk menutup
  const sheet = wrap.querySelector('.sheet');
  const grip = wrap.querySelector('.sheet-grip');
  let startY = null;
  grip.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; }, { passive: true });
  grip.addEventListener('touchmove', (e) => {
    if (startY === null) return;
    const dy = Math.max(0, e.touches[0].clientY - startY);
    sheet.style.transform = `translateY(${dy}px)`;
  }, { passive: true });
  grip.addEventListener('touchend', (e) => {
    const dy = e.changedTouches[0].clientY - startY;
    sheet.style.transform = '';
    startY = null;
    if (dy > 80) close();
  });

  const api = { el: wrap.querySelector('.sheet-body'), close };
  onMount?.(api);
  return api;
}

export function confirmSheet({ title, message, okText = 'Ya', danger = false }) {
  return new Promise((resolve) => {
    let done = false;
    const s = openSheet({
      title,
      html: `<p class="muted">${esc(message)}</p>
        <div class="btn-row"><button class="btn btn-ghost" data-act="no">Batal</button>
        <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-act="yes">${esc(okText)}</button></div>`,
      onMount: ({ el, close }) => {
        el.addEventListener('click', (e) => {
          const act = e.target.closest('[data-act]')?.dataset.act;
          if (!act) return;
          done = true;
          resolve(act === 'yes');
          close();
        });
      },
    });
    const obs = new MutationObserver(() => {
      if (!s.el.isConnected) { obs.disconnect(); if (!done) resolve(false); }
    });
    obs.observe(document.getElementById('sheetRoot'), { childList: true });
  });
}
