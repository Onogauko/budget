export const state = {
  me: null,
  settings: {},
  divisions: [],
  today: null,
  range: { min: null, max: null },
  days: {},                 // 'YYYY-MM-DD' => { t, n, d: {code: amount} }
  loadedMonths: new Set(),  // 'YYYY-MM'
  current: null,            // tanggal di tampilan harian
  month: null,              // bulan di tampilan bulanan
  sortMode: 'order',
  view: 'harian',
};

export const isAdmin = () => state.me?.role === 'admin';

export function divColor(code) {
  const i = state.divisions.findIndex((d) => d.code === Number(code));
  return i;
}

export function resetBudgetCache() {
  state.days = {};
  state.loadedMonths = new Set();
}
