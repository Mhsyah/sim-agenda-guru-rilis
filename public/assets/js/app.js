/* Agenda Guru v0.2 — progressive enhancement; semua fitur tetap bisa dipakai tanpa JS. */
(function () {
  'use strict';
  var d = document, root = d.documentElement;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var csrfName = root.getAttribute('data-csrf-name');
  var csrfHash = root.getAttribute('data-csrf-hash');

  /* ---------------------------------------------------------------- Tema */
  var mq = window.matchMedia ? matchMedia('(prefers-color-scheme: dark)') : null;
  function applyTheme(t) {
    var dark = t === 'dark' || (t === 'system' && mq && mq.matches);
    root.classList.toggle('dark', dark);
    root.setAttribute('data-theme', t);
    $$('[data-theme-set]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-theme-set') === t)); });
    var label = { light: 'Terang', dark: 'Gelap', system: 'Ikuti sistem' }[t];
    $$('[data-theme-label]').forEach(function (el) { el.textContent = label; });
    $$('[data-theme-icon]').forEach(function (el) { el.hidden = el.getAttribute('data-theme-icon') !== t; });
    $$('[data-theme-field]').forEach(function (el) { el.value = t; }); // login: kirim tema pilihan ke akun
  }
  function saveTheme(t) {
    try { localStorage.setItem('ag-theme', t); } catch (e) {}
    root.setAttribute('data-pref', t);
    var url = root.getAttribute('data-theme-url');
    if (!url || !csrfName) return;
    var body = new URLSearchParams(); body.append('theme', t); body.append(csrfName, csrfHash);
    fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) { if (j && j.csrf) { csrfHash = j.csrf; $$('input[name="' + csrfName + '"]').forEach(function (i) { i.value = j.csrf; }); } })
      .catch(function () {});
  }
  if (mq && mq.addEventListener) mq.addEventListener('change', function () { if (root.getAttribute('data-theme') === 'system') applyTheme('system'); });
  applyTheme(root.getAttribute('data-theme') || 'system');
  d.addEventListener('click', function (e) {
    var set = e.target.closest('[data-theme-set]');
    var tog = e.target.closest('[data-theme-toggle]');
    if (set) { e.preventDefault(); var t = set.getAttribute('data-theme-set'); applyTheme(t); saveTheme(t); }
    if (tog) {
      var order = ['system', 'light', 'dark'];
      var next = order[(order.indexOf(root.getAttribute('data-theme')) + 1) % 3];
      applyTheme(next); saveTheme(next);
      announce('Tema: ' + { light: 'terang', dark: 'gelap', system: 'mengikuti sistem' }[next]);
    }
  });

  /* Preferensi gerak lokal */
  $$('[data-motion-toggle]').forEach(function (cb) {
    cb.checked = root.getAttribute('data-motion') === 'reduce';
    cb.addEventListener('change', function () {
      if (cb.checked) { root.setAttribute('data-motion', 'reduce'); try { localStorage.setItem('ag-motion', 'reduce'); } catch (e) {} }
      else { root.removeAttribute('data-motion'); try { localStorage.removeItem('ag-motion'); } catch (e) {} }
    });
  });

  /* ---------------------------------------------------------------- Live region */
  var live = $('#live-region');
  function announce(msg) { if (!live) return; live.textContent = ''; setTimeout(function () { live.textContent = msg; }, 30); }

  /* ---------------------------------------------------------------- Laci navigasi (mobile) */
  /* Sembunyikan/tampilkan sidebar (desktop), diingat di browser */
  var sideBtns = $$('[data-side-toggle]');
  function syncSide() {
    var hidden = d.documentElement.classList.contains('side-hidden');
    sideBtns.forEach(function (b) {
      b.setAttribute('aria-expanded', String(!hidden));
      b.title = (hidden ? 'Tampilkan' : 'Sembunyikan') + ' sidebar (Ctrl+B)';
      var l = $('[data-side-label]', b); if (l) l.textContent = hidden ? 'Tampilkan sidebar' : 'Sembunyikan sidebar';
    });
  }
  function toggleSide() {
    var hidden = d.documentElement.classList.toggle('side-hidden');
    try { localStorage.setItem('ag-side', hidden ? 'hidden' : 'shown'); } catch (e) {}
    syncSide(); announce(hidden ? 'Sidebar disembunyikan' : 'Sidebar ditampilkan');
  }
  sideBtns.forEach(function (b) { b.addEventListener('click', toggleSide); });
  d.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'b' && window.matchMedia('(min-width: 1024px)').matches) { e.preventDefault(); toggleSide(); }
  });
  syncSide();

  var drawer = $('#nav-drawer');
  if (drawer && drawer.showModal) {
    $$('[data-drawer-open]').forEach(function (b) { b.addEventListener('click', function () { drawer.showModal(); }); });
    $$('[data-drawer-close]', drawer).forEach(function (b) { b.addEventListener('click', function () { drawer.close(); }); });
    drawer.addEventListener('click', function (e) { if (e.target === drawer) drawer.close(); });
  }

  /* ---------------------------------------------------------------- Dialog konfirmasi */
  var dlg = $('#confirm-dialog'), pendingForm = null, lastFocus = null;
  d.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.hasAttribute('data-confirm') || f.__confirmed || !dlg || !dlg.showModal) return;
    e.preventDefault();
    pendingForm = f; lastFocus = d.activeElement;
    $('#confirm-message', dlg).textContent = f.getAttribute('data-confirm');
    $('#confirm-yes', dlg).textContent = f.getAttribute('data-confirm-label') || 'Ya, lanjutkan';
    dlg.showModal();
    $('#confirm-no', dlg).focus();
  }, true);
  if (dlg) {
    $('#confirm-yes', dlg).addEventListener('click', function () {
      dlg.close();
      if (pendingForm) { pendingForm.__confirmed = true; pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit(); }
    });
    $('#confirm-no', dlg).addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('close', function () { if (lastFocus && !(pendingForm && pendingForm.__confirmed)) lastFocus.focus(); pendingForm = pendingForm && pendingForm.__confirmed ? pendingForm : null; });
  }

  /* ---------------------------------------------------------------- Pencarian langsung (tanpa tekan tombol)
     Hasil dimuat di latar dan hanya area daftar yang diganti; permintaan lama dibatalkan agar tetap cepat. */
  $$('form[data-live-search]').forEach(function (f) {
    var q = $('input[type="search"], input[name="q"]', f), region = $('[data-live-region]');
    if (!q || !region) return;
    var timer = null, ctrl = null, last = q.value;
    var fullReload = !!$('[data-bulk-bar]'); // halaman dengan ceklis massal: muat ulang biasa agar fitur tetap utuh
    function run() {
      if (q.value === last) return; last = q.value;
      var params = new URLSearchParams(new FormData(f)); params.delete('page');
      Array.from(params.keys()).forEach(function (k) { if (params.get(k) === '') params.delete(k); });
      var url = (f.getAttribute('action') || location.pathname) + (params.toString() ? '?' + params.toString() : '');
      if (fullReload) { location.assign(url); return; }
      if (ctrl) ctrl.abort();
      ctrl = window.AbortController ? new AbortController() : null;
      region.classList.add('is-loading'); region.setAttribute('aria-busy', 'true');
      fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html'), fresh = doc.querySelector('[data-live-region]');
          if (!fresh) { location.assign(url); return; }
          region.innerHTML = fresh.innerHTML;
          history.replaceState(null, '', url);
          d.dispatchEvent(new Event('ag:live-updated'));
          var n = region.querySelectorAll('tbody tr, .u-card, .class-card').length;
          announce(n ? n + ' hasil ditemukan' : 'Tidak ada hasil');
        })
        .catch(function (err) { if (!err || err.name !== 'AbortError') location.assign(url); })
        .finally(function () { region.classList.remove('is-loading'); region.removeAttribute('aria-busy'); });
    }
    q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 280); });
    q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); last = null; run(); } });
  });

  /* ---------------------------------------------------------------- Kelas/Rombel: jenjang dilipat, pilihan diingat */
  function initGrades() {
    var searching = /[?&]q=[^&]/.test(location.search);
    var saved = {}; try { saved = JSON.parse(localStorage.getItem('ag-grades') || '{}'); } catch (e) {}
    $$('details.grade-group').forEach(function (g) {
      var k = g.getAttribute('data-grade');
      if (!searching && Object.prototype.hasOwnProperty.call(saved, k)) g.open = !!saved[k];
      g.addEventListener('toggle', function () {
        if (/[?&]q=[^&]/.test(location.search)) return;
        saved[k] = g.open; try { localStorage.setItem('ag-grades', JSON.stringify(saved)); } catch (e) {}
      });
    });
  }
  initGrades();
  d.addEventListener('ag:live-updated', initGrades);

  /* ---------------------------------------------------------------- Kelas/Rombel: daftar siswa dalam pop-up */
  var cdlg = d.getElementById('class-dialog');
  if (cdlg) {
    var cbody = $('[data-class-body]', cdlg);
    d.addEventListener('click', function (e) {
      var b = e.target.closest('[data-class-students]');
      if (b) {
        cbody.innerHTML = '<p class="p-6 text-sm text-muted">Memuat daftar siswa…</p>';
        cdlg.showModal();
        fetch(b.getAttribute('data-class-students'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
          .then(function (html) { cbody.innerHTML = html; var fi = $('[data-class-filter]', cbody); if (fi) fi.focus(); })
          .catch(function () { cbody.innerHTML = '<p class="p-6 text-sm">Gagal memuat daftar siswa.</p>'; });
        return;
      }
      if (e.target.closest('[data-class-close]') || e.target === cdlg) cdlg.close();
    });
    cdlg.addEventListener('input', function (e) {
      if (!e.target.matches('[data-class-filter]')) return;
      var v = e.target.value.trim().toLowerCase();
      $$('.cd-list li', cdlg).forEach(function (li) { li.hidden = v !== '' && li.getAttribute('data-name').indexOf(v) === -1; });
    });
  }

  /* ---------------------------------------------------------------- Popup progres impor / restore
     Formulir dikirim biasa (pesan hasil tetap tampil); popup menahan klik ganda & menunjukkan tahap proses. */
  d.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || !f.hasAttribute('data-progress')) return;
    var inp = $('input[type="file"]', f), file = inp && inp.files ? inp.files[0] : null;
    var pd = d.getElementById('progress-dialog');
    if (!pd) {
      pd = d.createElement('dialog'); pd.id = 'progress-dialog'; pd.className = 'progress-dialog';
      pd.setAttribute('aria-labelledby', 'pd-title'); pd.setAttribute('aria-describedby', 'pd-step');
      pd.innerHTML = '<p id="pd-title" class="text-base font-bold"></p><p class="pd-file mt-1 text-xs text-muted"></p>'
        + '<ol class="pd-steps"><li data-s="1">Mengunggah file</li><li data-s="2">Memeriksa &amp; menyimpan data</li><li data-s="3">Menyiapkan ringkasan hasil</li></ol>'
        + '<div class="pd-bar" role="progressbar" aria-label="Sedang diproses"><span></span></div>'
        + '<p id="pd-step" class="mt-2 flex justify-between text-xs text-muted"><span class="pd-msg">Mohon tunggu, jangan tutup halaman ini.</span><span class="pd-time">0 dtk</span></p>';
      d.body.appendChild(pd);
      pd.addEventListener('cancel', function (ev) { ev.preventDefault(); }); // tidak bisa ditutup dengan Esc saat proses
    }
    $('#pd-title', pd).textContent = f.getAttribute('data-progress') + '…';
    var checking = /^Memeriksa/i.test(f.getAttribute('data-progress'));
    $('.pd-steps li[data-s="2"]', pd).textContent = checking ? 'Memeriksa format & data ganda' : 'Menyimpan data';
    $('.pd-steps li[data-s="3"]', pd).textContent = checking ? 'Menyiapkan pratinjau' : 'Menyiapkan ringkasan hasil';
    var info = $('.pd-file', pd);
    info.textContent = file ? file.name + ' · ' + (file.size >= 1048576 ? (file.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(file.size / 1024)) + ' KB') : '';
    if (file && /\.csv$/i.test(file.name) && file.size < 5 * 1048576 && file.text) {
      file.text().then(function (t) { var n = t.split(/\r\n|\n|\r/).filter(function (l) { return l.trim(); }).length - 1; if (n > 0) info.textContent += ' · ± ' + n + ' baris'; }).catch(function () {});
    }
    var steps = $$('.pd-steps li', pd); steps.forEach(function (li) { li.className = ''; }); steps[0].className = 'is-now';
    $$('button[type="submit"], button:not([type])', f).forEach(function (b) { b.disabled = true; });
    var t0 = Date.now(), timeEl = $('.pd-time', pd);
    var tick = setInterval(function () {
      var s = Math.round((Date.now() - t0) / 1000); timeEl.textContent = s + ' dtk';
      if (s >= 1 && steps[0].className === 'is-now') { steps[0].className = 'is-done'; steps[1].className = 'is-now'; }
      if (s >= 20) $('.pd-msg', pd).textContent = 'File besar sedang diproses — tetap tunggu.';
    }, 500);
    window.addEventListener('pagehide', function () { clearInterval(tick); }, { once: true });
    if (pd.showModal && !pd.open) pd.showModal();
  });
  // Kembali via tombol Back (bfcache): tutup popup & aktifkan tombol lagi
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    var pd = d.getElementById('progress-dialog'); if (pd && pd.open) pd.close();
    $$('form[data-progress] button').forEach(function (b) { b.disabled = false; });
  });

  /* Pratinjau impor: saring baris */
  var impTabs = $('[data-imp-tabs]');
  if (impTabs) {
    impTabs.addEventListener('click', function (e) {
      var b = e.target.closest('[data-imp-filter]'); if (!b) return;
      var want = b.getAttribute('data-imp-filter').split(',');
      $$('[data-imp-filter]', impTabs).forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
      $$('[data-imp-rows] tr').forEach(function (tr) { tr.hidden = want[0] !== 'all' && want.indexOf(tr.getAttribute('data-status')) === -1; });
    });
  }

  // Input nilai: tingkat ketercapaian KKTP diperbarui saat mengetik
  (function () {
    var grid = $('#score-grid'); if (!grid || !grid.getAttribute('data-kktp')) return;
    var bands = JSON.parse(grid.getAttribute('data-kktp')), max = parseFloat(grid.getAttribute('data-max')) || 100;
    var tone = { danger: 'badge-danger', warning: 'badge-warning', info: 'badge-info', success: 'badge-success' };
    grid.addEventListener('input', function (e) {
      var inp = e.target.closest('.score-input'); if (!inp) return;
      var cell = $('[data-kktp-cell="' + inp.id + '"]', grid); if (!cell) return;
      var v = parseFloat(String(inp.value).replace(',', '.'));
      if (isNaN(v)) { cell.innerHTML = '<span class="text-xs text-muted">—</span>'; return; }
      var pct = v / max * 100, b = bands[bands.length - 1];
      for (var i = 0; i < bands.length; i++) { if (pct <= bands[i].max + 0.4999) { b = bands[i]; break; } }
      cell.innerHTML = '<span class="badge ' + (tone[b.tone] || '') + '">' + b.label + '</span>';
    });
  })();

  // Pratinjau Dapodik: centang/lepas semua baris yang perlu disamakan
  d.addEventListener('change', function (e) {
    if (!e.target.matches('[data-dp-all]')) return;
    $$('[data-dp-row]').forEach(function (c) { c.checked = e.target.checked; });
  });

  // Kartu impor: tampilkan nama file yang dipilih
  d.addEventListener('change', function (e) {
    if (!e.target.matches('[data-imp-file]')) return;
    var lbl = e.target.closest('.imp-drop'), f = e.target.files[0], n = lbl && lbl.querySelector('[data-imp-fname]');
    if (n) n.textContent = f ? f.name : 'Pilih file CSV';
    if (lbl) lbl.classList.toggle('has-file', !!f);
  });

  /* ---------------------------------------------------------------- Menu radial (pintasan opsional) */
  var radial = $('.radial');
  if (radial) {
    var rt = $('.radial-toggle', radial), ri = $('.radial-items', radial);
    var items = $$('a', ri);
    var n = items.length;
    items.forEach(function (a, i) {
      // Kipas melengkung ke atas-kiri dari tombol utama.
      var t = (i + 1) / (n + 1) * Math.PI;
      a.style.setProperty('--x', (-Math.sin(t) * 34) + 'px');
      a.style.setProperty('--y', (-(i + 1) * 56 - 8) + 'px');
      a.style.transitionDelay = (i * 35) + 'ms';
    });
    function setOpen(open) {
      radial.setAttribute('data-open', String(open));
      rt.setAttribute('aria-expanded', String(open));
      if (open) { ri.removeAttribute('inert'); } else { ri.setAttribute('inert', ''); }
    }
    setOpen(false);
    rt.addEventListener('click', function () { var o = radial.getAttribute('data-open') !== 'true'; setOpen(o); if (o && items[0]) items[0].focus(); });
    radial.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { setOpen(false); rt.focus(); }
      if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && radial.getAttribute('data-open') === 'true') {
        e.preventDefault();
        var idx = items.indexOf(d.activeElement);
        var nxt = e.key === 'ArrowDown' ? (idx + 1) % n : (idx - 1 + n) % n;
        items[nxt].focus();
      }
    });
    d.addEventListener('click', function (e) { if (!radial.contains(e.target)) setOpen(false); });
  }

  /* ---------------------------------------------------------------- Opsi bergantung mapel */
  $$('[data-filter-by]').forEach(function (target) {
    var src = d.getElementById('f-' + target.getAttribute('data-filter-by'));
    if (!src) return;
    function sync() {
      var v = src.value;
      if (target.tagName === 'SELECT') {
        $$('optgroup', target).forEach(function (g) {
          var show = !v || g.getAttribute('data-group') === v;
          g.hidden = !show; g.disabled = !show;
        });
        var sel = target.options[target.selectedIndex];
        if (sel && sel.parentNode.tagName === 'OPTGROUP' && sel.parentNode.disabled) target.value = '';
      } else {
        var any = false;
        $$('[data-group]', target).forEach(function (g) {
          var show = !!v && g.getAttribute('data-group') === v;
          g.hidden = !show; if (show) any = true;
        });
        var empty = $('[data-group-empty]', target);
        if (empty) empty.hidden = any;
      }
    }
    src.addEventListener('change', sync); sync();
  });

  /* ---------------------------------------------------------------- Pemilih periode */
  var pf = $('#period-form');
  if (pf) {
    var py = $('[name="academic_year_id"]', pf), ps = $('[name="semester_id"]', pf);
    if (py) py.addEventListener('change', function () { if (ps) ps.value = ''; pf.submit(); });
    if (ps) ps.addEventListener('change', function () { pf.submit(); });
    var pb = $('[data-period-submit]', pf); if (pb) pb.classList.add('sr-only');
  }

  /* ---------------------------------------------------------------- Input nilai */
  var grid = $('#score-grid');
  if (grid) {
    var inputs = $$('.score-input', grid), max = parseFloat(grid.getAttribute('data-max'));
    var counter = $('#score-count');
    function check(inp) {
      var raw = inp.value.trim().replace(',', '.');
      var msg = $('#' + inp.getAttribute('data-msg'));
      var bad = raw !== '' && (isNaN(raw) || +raw < 0 || +raw > max);
      inp.setAttribute('aria-invalid', String(bad));
      if (msg) msg.textContent = bad ? 'Nilai harus 0–' + String(max).replace('.', ',') : (raw === '' ? 'Belum diinput' : '');
      var row = inp.closest('tr'); if (row) row.classList.toggle('opacity-100', true);
    }
    function count() {
      if (!counter) return;
      var f = inputs.filter(function (i) { return i.value.trim() !== ''; }).length;
      counter.textContent = f + ' dari ' + inputs.length + ' siswa sudah dinilai';
    }
    inputs.forEach(function (inp, i) {
      inp.addEventListener('input', function () { check(inp); count(); });
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === 'ArrowDown') { e.preventDefault(); if (inputs[i + 1]) { inputs[i + 1].focus(); inputs[i + 1].select(); } }
        if (e.key === 'ArrowUp') { e.preventDefault(); if (inputs[i - 1]) { inputs[i - 1].focus(); inputs[i - 1].select(); } }
      });
      check(inp);
    });
    count();
    var form = grid.closest('form'), dirty = false;
    inputs.forEach(function (i) { i.addEventListener('change', function () { dirty = true; }); });
    if (form) form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }

  /* ---------------------------------------------------------------- Total bobot */
  var wsum = $('#weight-sum');
  if (wsum) {
    var ws = $$('[data-weight]');
    function recalc() {
      var s = 0;
      ws.forEach(function (w) {
        var en = d.getElementById('en-' + w.getAttribute('data-weight'));
        if (en && en.checked) { var v = parseFloat((w.value || '0').replace(',', '.')); if (!isNaN(v)) s += v; }
        w.disabled = en && !en.checked;
      });
      s = Math.round(s * 100) / 100;
      var ok = Math.abs(s - 100) < 0.001;
      wsum.textContent = String(s).replace('.', ',') + '%';
      var st = $('#weight-status');
      st.textContent = ok ? 'Total tepat 100% — rekap final dapat dihitung.' : 'Total harus tepat 100% agar rekap final dapat dihitung.';
      st.className = 'badge ' + (ok ? 'badge-success' : 'badge-warning');
    }
    ws.forEach(function (w) { w.addEventListener('input', recalc); });
    $$('[data-weight-enabled]').forEach(function (c) { c.addEventListener('change', recalc); });
    recalc();
    var gform = wsum.closest('form');
    if (gform) gform.addEventListener('submit', function () { ws.forEach(function (w) { w.disabled = false; }); });
  }

  /* ---------------------------------------------------------------- Daftar hadir agenda */
  var att = $('#attendance');
  if (att) {
    var list = $('#att-list', att), summary = $('#att-summary', att);
    var fClass = d.getElementById('f-class_group_id'), fDate = d.getElementById('f-agenda_date');
    var attDirty = false;
    var names = { H: 'hadir', S: 'sakit', I: 'izin', A: 'alpa' };
    var search = $('[data-att-search]', att), tools = $('[data-att-tools]', att), mode = 'absent';
    function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim(); }
    function tally() {
      var c = { H: 0, S: 0, I: 0, A: 0 }, n = 0;
      $$('[data-att-rows] > li', list).forEach(function (li) {
        var r = $('input[data-att-radio]:checked', li);
        var v = r ? r.value : 'H';
        li.setAttribute('data-status', v); c[v]++; n++;
      });
      Object.keys(c).forEach(function (k) { var el = $('[data-att-count="' + k + '"]', att); if (el) el.textContent = c[k]; });
      var ta = $('[data-att-tab="absent"]', att), tl = $('[data-att-tab="all"]', att);
      if (ta) ta.textContent = n - c.H; if (tl) tl.textContent = n;
      if (tools) tools.hidden = !n;
      if (!n) { summary.textContent = ''; return; }
      var parts = ['S', 'I', 'A'].filter(function (k) { return c[k]; }).map(function (k) { return c[k] + ' ' + names[k]; });
      summary.textContent = c.H + '/' + n + ' hadir' + (parts.length ? ' · ' + parts.join(', ') : '');
    }
    // Tampilkan hanya siswa yang dicari; tanpa kata kunci → sesuai tab (tidak hadir / semua).
    function applyFilter() {
      var q = norm(search && search.value), shown = 0, rows = $$('[data-att-rows] > li', list);
      rows.forEach(function (li) {
        var ok = q ? norm(li.getAttribute('data-name')).indexOf(q) !== -1 : (mode === 'all' || li.getAttribute('data-status') !== 'H');
        li.hidden = !ok; if (ok) shown++;
      });
      var none = $('[data-att-none]', list);
      if (none) {
        none.hidden = shown > 0 || !rows.length;
        none.textContent = q ? 'Tidak ada siswa bernama “' + search.value.trim() + '”.' : 'Semua siswa hadir. Cari nama siswa di atas untuk menandai sakit, izin, atau alpa.';
      }
    }
    $$('[data-att-filter]', att).forEach(function (b) {
      b.addEventListener('click', function () {
        mode = b.getAttribute('data-att-filter');
        $$('[data-att-filter]', att).forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
        if (search) search.value = '';
        applyFilter();
      });
    });
    if (search) {
      search.addEventListener('input', applyFilter);
      search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { // jangan kirim formulir; lompat ke siswa pertama yang cocok
          e.preventDefault();
          var first = $$('[data-att-rows] > li', list).filter(function (li) { return !li.hidden; })[0];
          var r = first && $('input[data-att-radio]:checked', first); if (r) r.focus();
        } else if (e.key === 'Escape' && search.value) { e.preventDefault(); search.value = ''; applyFilter(); }
      });
    }
    // Tombol keterangan per siswa (siswa hadir): buka kolom keterangan.
    list.addEventListener('click', function (e) {
      var b = e.target.closest('[data-att-note-toggle]'); if (!b) return;
      var li = b.closest('.att-row'); li.classList.toggle('note-open');
      var inp = $('input[name^="att_note"]', li); if (li.classList.contains('note-open') && inp) inp.focus();
    });
    list.addEventListener('change', function (e) { if (e.target.matches('[data-att-radio]')) { attDirty = true; tally(); } });
    list.addEventListener('input', function (e) { if (e.target.name && e.target.name.indexOf('att_note') === 0) attDirty = true; });
    var allBtn = $('[data-att-all-present]', att);
    if (allBtn) allBtn.addEventListener('click', function () {
      $$('input[data-att-radio][value="H"]', list).forEach(function (r) { r.checked = true; });
      attDirty = true; tally(); applyFilter(); announce('Semua siswa ditandai hadir');
    });
    function reload(reason) {
      if (search) search.value = '';
      if (!fClass || !fClass.value) { list.innerHTML = '<p class="rounded-xl border border-dashed border-line p-4 text-sm text-muted">Pilih kelas untuk menampilkan daftar siswa.</p>'; tally(); return; }
      if (attDirty && reason === 'date' && !confirm('Muat ulang daftar hadir sesuai tanggal baru? Perubahan yang belum disimpan akan diganti.')) return;
      var q = new URLSearchParams({ class_group_id: fClass.value, agenda_date: fDate ? fDate.value : '', agenda_id: att.getAttribute('data-agenda-id') || '' });
      list.setAttribute('aria-busy', 'true');
      fetch(att.getAttribute('data-att-url') + '?' + q.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
        .then(function (html) { list.innerHTML = html; attDirty = false; tally(); applyFilter(); announce('Daftar hadir dimuat'); })
        .catch(function () { announce('Gagal memuat daftar hadir'); })
        .finally(function () { list.removeAttribute('aria-busy'); });
    }
    if (fClass) fClass.addEventListener('change', function () { attDirty = false; reload('class'); });
    if (fDate) fDate.addEventListener('change', function () { if (!att.getAttribute('data-agenda-id')) reload('date'); });
    tally(); applyFilter();
  }

  /* ---------------------------------------------------------------- Lampiran materi: pilih/seret, pratinjau */
  var mat = $('[data-material]');
  if (mat) {
    var mIn = $('[data-mat-input]', mat), mNew = $('[data-mat-new]', mat), mDrop = $('[data-mat-drop]', mat);
    var dlg = d.getElementById('mat-dialog'), body = dlg && $('[data-mat-body]', dlg), dl = dlg && $('[data-mat-dl]', dlg);
    var objUrl = null, LIMIT = 1048576;
    var okExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'pdf', 'docx', 'xlsx', 'pptx'];
    function human(b) { return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function extOf(n) { var m = /\.([a-z0-9]+)$/i.exec(n || ''); return m ? m[1].toLowerCase() : ''; }
    function kindOf(ext) { return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'].indexOf(ext) !== -1 ? 'image' : (ext === 'pdf' ? 'pdf' : 'office'); }
    function openPreview(url, kind, name, dlUrl) {
      if (!dlg) return;
      $('#mat-dialog-title', dlg).textContent = name || 'Pratinjau materi';
      body.innerHTML = '';
      if (kind === 'image' || kind === 'webp') { var im = d.createElement('img'); im.src = url; im.alt = name || ''; body.appendChild(im); }
      else if (kind === 'pdf') { var fr = d.createElement('iframe'); fr.src = url; fr.title = 'Pratinjau ' + (name || 'PDF'); body.appendChild(fr); }
      else { body.innerHTML = '<div class="p-8 text-center text-sm text-muted"><p class="font-semibold text-ink">Pratinjau langsung belum tersedia untuk file Office.</p><p class="mt-1">Unduh untuk membuka di Word/Excel/PowerPoint.</p></div>'; }
      if (dl) { dl.hidden = !dlUrl; if (dlUrl) dl.href = dlUrl; }
      dlg.showModal();
    }
    if (dlg) {
      $('[data-mat-close]', dlg).addEventListener('click', function () { dlg.close(); });
      dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
      dlg.addEventListener('close', function () { body.innerHTML = ''; });
    }
    $$('[data-mat-preview]', mat).forEach(function (b) {
      b.addEventListener('click', function () {
        var u = b.getAttribute('data-mat-preview');
        openPreview(u, b.getAttribute('data-mat-kind'), b.getAttribute('data-mat-name'), u + '?download=1');
      });
    });
    function showFile(f) {
      if (objUrl) { URL.revokeObjectURL(objUrl); objUrl = null; }
      if (!f) { mNew.hidden = true; return; }
      var ext = extOf(f.name), kind = kindOf(ext), info = $('[data-mat-info]', mNew), th = $('[data-mat-thumb]', mNew);
      if (okExt.indexOf(ext) === -1) { mIn.value = ''; mNew.hidden = true; announce('Jenis file tidak didukung'); alert('Jenis file tidak didukung. Gunakan JPG, PNG, WebP, PDF, DOCX, XLSX, atau PPTX.'); return; }
      if (f.size > 20 * 1048576) { mIn.value = ''; mNew.hidden = true; alert('File terlalu besar (maks. 20 MB sebelum dikompres).'); return; }
      objUrl = URL.createObjectURL(f);
      $('[data-mat-name]', mNew).textContent = f.name;
      info.className = 'text-xs ' + (kind === 'image' || f.size > LIMIT ? 'text-warn' : 'text-muted');
      info.textContent = human(f.size) + (kind === 'image' ? ' · akan dikonversi ke WebP' : (f.size > LIMIT ? ' · lebih dari 1 MB, akan dikompres otomatis' : ' · siap diunggah'));
      th.className = 'mat-thumb mat-ext-' + ext; th.innerHTML = '';
      if (kind === 'image') { var im = d.createElement('img'); im.src = objUrl; im.alt = ''; th.appendChild(im); } else th.textContent = ext.toUpperCase();
      $('[data-mat-preview-new]', mNew).onclick = function () { openPreview(objUrl, kind, f.name, null); };
      mNew.hidden = false;
    }
    mIn.addEventListener('change', function () { showFile(mIn.files[0]); });
    $('[data-mat-clear]', mNew).addEventListener('click', function () { mIn.value = ''; showFile(null); });
    ['dragenter', 'dragover'].forEach(function (t) { mDrop.addEventListener(t, function (e) { e.preventDefault(); mDrop.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { mDrop.addEventListener(t, function () { mDrop.classList.remove('is-over'); }); });
    mDrop.addEventListener('drop', function (e) {
      e.preventDefault();
      if (e.dataTransfer && e.dataTransfer.files.length) { mIn.files = e.dataTransfer.files; showFile(mIn.files[0]); }
    });
  }

  /* ---------------------------------------------------------------- Foto profil: kamera/galeri, unggah otomatis */
  var pf = $('[data-photo-form]');
  if (pf) {
    var pins = $$('[data-photo-input]', pf), pst = $('[data-photo-status]', pf), prev = $('[data-photo-preview]');
    pins.forEach(function (inp) {
      inp.addEventListener('change', function () {
        var f = inp.files[0]; if (!f) return;
        if (!/^image\//.test(f.type)) { alert('Pilih file gambar (JPG, PNG, atau WebP).'); inp.value = ''; return; }
        pins.forEach(function (o) { if (o !== inp) { o.removeAttribute('name'); o.value = ''; } });
        inp.setAttribute('name', 'photo');
        if (prev) { var u = URL.createObjectURL(f); prev.innerHTML = '<img src="' + u + '" alt="" class="size-28 rounded-full object-cover ring-4 ring-surface-2 opacity-70">'; }
        pst.classList.remove('hidden'); pst.textContent = 'Mengunggah foto…';
        pf.submit();
      });
    });
  }

  /* Tombol kirim yang perlu konfirmasi (nilai tombol tetap ikut terkirim) */
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm-btn]');
    if (b && !confirm(b.getAttribute('data-confirm-btn'))) e.preventDefault();
  });

  /* Warna kegiatan kalender mengikuti jenis, selama belum diganti manual */
  var evType = d.getElementById('f-event_type'), evColor = $('[data-event-color]');
  if (evType && evColor) {
    var palette = { libur: '#ef4444', kegiatan: '#3b82f6', ujian: '#f59e0b', rapat: '#8b5cf6', lainnya: '#14b8a6' };
    var touched = Object.keys(palette).every(function (k) { return palette[k] !== evColor.value.toLowerCase(); });
    evColor.addEventListener('input', function () { touched = true; });
    evType.addEventListener('change', function () { if (!touched && palette[evType.value]) evColor.value = palette[evType.value]; });
  }

  /* Pembagian mengajar: centang kelas → mapel ikut tercentang; lepas mapel → kelasnya dilepas */
  $$('[data-teach]').forEach(function (box) {
    function count(row) {
      var n = $$('[data-teach-class]:checked', row).length, el = $('[data-teach-count]', row);
      if (el) el.textContent = n ? n + ' kelas' : ($('[data-teach-subject]', row).checked ? 'pilih kelas' : '');
    }
    box.addEventListener('change', function (e) {
      var row = e.target.closest('[data-teach-row]'); if (!row) return;
      var subj = $('[data-teach-subject]', row);
      if (e.target.matches('[data-teach-class]') && e.target.checked) subj.checked = true;
      if (e.target === subj && !subj.checked) $$('[data-teach-class]', row).forEach(function (c) { c.checked = false; });
      row.classList.toggle('is-on', subj.checked); count(row);
    });
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-teach-all]'); if (!b) return;
      var grade = b.closest('.teach-grade'), row = b.closest('[data-teach-row]'), cs = $$('[data-teach-class]', grade);
      var all = cs.every(function (c) { return c.checked; });
      cs.forEach(function (c) { c.checked = !all; });
      if (!all) { $('[data-teach-subject]', row).checked = true; row.classList.add('is-on'); }
      count(row);
    });
    $$('[data-teach-row]', box).forEach(count);
    // Admin: tambah mapel baru langsung dari formulir
    var nw = $('[data-teach-new]', box), tpl = $('[data-teach-template]', box), list = $('[data-teach-list]', box);
    if (!nw || !tpl || !list) return;
    var nName = $('[data-teach-new-name]', nw), nCode = $('[data-teach-new-code]', nw), msg = $('[data-teach-new-msg]', nw), add = $('[data-teach-new-add]', nw);
    function say(t, bad) { msg.textContent = t; msg.className = 'mt-1.5 text-xs ' + (bad ? 'teach-msg-bad' : 'teach-msg-ok'); }
    function create() {
      var name = nName.value.trim(); if (!name) { say('Isi nama mata pelajaran.', true); nName.focus(); return; }
      var body = new URLSearchParams(); body.append('name', name); body.append('code', nCode.value.trim()); body.append(csrfName, csrfHash);
      add.disabled = true;
      fetch(nw.getAttribute('data-url'), { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.csrf) { csrfHash = j.csrf; $$('input[name="' + csrfName + '"]').forEach(function (i) { i.value = j.csrf; }); }
          if (!j.ok) { say(j.error || 'Gagal menambah mapel.', true); return; }
          var exist = $('[data-teach-subject][value="' + j.id + '"]', list);
          if (exist) { exist.checked = true; exist.closest('[data-teach-row]').classList.add('is-on'); }
          else {
            var html = tpl.innerHTML.replace(/teach\[0\]/g, 'teach[' + j.id + ']').replace('value="0"', 'value="' + j.id + '"');
            var wrap = d.createElement('div'); wrap.innerHTML = html.trim();
            var row = wrap.firstElementChild; $('.teach-subject span', row).textContent = j.label;
            var empty = $('[data-teach-empty]', list); if (empty) empty.remove();
            list.appendChild(row);
          }
          say((j.created ? 'Mapel baru “' : 'Mapel “') + j.label + '” ditambahkan dan dicentang. Pilih kelasnya, lalu Simpan.', false);
          nName.value = ''; nCode.value = '';
          announce('Mata pelajaran ditambahkan');
        })
        .catch(function () { say('Gagal menghubungi server.', true); })
        .finally(function () { add.disabled = false; });
    }
    add.addEventListener('click', create);
    [nName, nCode].forEach(function (i) { i.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); create(); } }); });
  });

  /* Select yang langsung menerapkan filter */
  $$('select[data-autosubmit]').forEach(function (sel) { sel.addEventListener('change', function () { if (sel.form) sel.form.submit(); }); });

  /* Log audit: centang + konfirmasi hapus */
  var adel = $('[data-audit-del]');
  if (adel) {
    var aItems = $$('[data-audit-item]'), aAll = $('[data-audit-all]'), aMode = $('[data-audit-mode]', adel);
    var aCount = function () { return aItems.filter(function (c) { return c.checked; }).length; };
    var aSync = function () { var o = aMode.querySelector('option[value="selected"]'); if (o) o.textContent = 'Log yang dicentang (' + aCount() + ')'; };
    aItems.forEach(function (c) { c.addEventListener('change', aSync); });
    if (aAll) aAll.addEventListener('change', function () { aItems.forEach(function (c) { c.checked = aAll.checked; }); aSync(); });
    adel.addEventListener('submit', function (e) {
      if (aMode.value === 'selected' && !aCount()) { e.preventDefault(); alert('Centang log yang akan dihapus, atau pilih rentang waktu.'); return; }
      var label = aMode.options[aMode.selectedIndex].textContent;
      if (!confirm('Hapus ' + label.toLowerCase() + '? Tindakan ini tidak dapat dibatalkan.')) e.preventDefault();
    });
    aSync();
  }

  /* ---------------------------------------------------------------- Ceklis baris & aksi massal */
  var bulk = $('[data-bulk-bar]');
  if (bulk) {
    var items = $$('[data-bulk-item]'), all = $('[data-bulk-all]'), act = $('[data-bulk-action]', bulk);
    function syncBulk() {
      var n = items.filter(function (c) { return c.checked; }).length;
      bulk.hidden = n === 0;
      $('[data-bulk-count]', bulk).textContent = n;
      if (all) { all.checked = n > 0 && n === items.length; all.indeterminate = n > 0 && n < items.length; }
      items.forEach(function (c) { var tr = c.closest('tr'); if (tr) tr.classList.toggle('is-selected', c.checked); });
    }
    items.forEach(function (c) { c.addEventListener('change', syncBulk); });
    if (all) all.addEventListener('change', function () { items.forEach(function (c) { c.checked = all.checked; }); syncBulk(); });
    act.addEventListener('change', function () {
      $$('[data-bulk-for]', bulk).forEach(function (el) { el.hidden = el.getAttribute('data-bulk-for') !== act.value; });
    });
    $('[data-bulk-clear]', bulk).addEventListener('click', function () { items.forEach(function (c) { c.checked = false; }); syncBulk(); });
    bulk.addEventListener('submit', function (e) {
      var n = $('[data-bulk-count]', bulk).textContent;
      if (!act.value) { e.preventDefault(); act.focus(); announce('Pilih aksi terlebih dahulu'); return; }
      if (act.value === 'delete' && !confirm('Hapus ' + n + ' siswa terpilih dari daftar? Riwayat nilai & kehadiran tetap tersimpan (arsip).')) e.preventDefault();
    });
    syncBulk();
  }

  /* ---------------------------------------------------------------- Kop surat: pratinjau langsung */
  var ked = $('[data-kop-editor]');
  if (ked) {
    var form = ked.closest('form'), fonts = JSON.parse(ked.getAttribute('data-fonts') || '{}');
    var defs = JSON.parse(ked.getAttribute('data-defaults') || '{}');
    var roots = $$('[data-kop-root]', form); // pratinjau kop + kop di pratinjau sampul
    function val(n) { var el = $('[data-kop-in="' + n + '"]', form); return el ? (el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value) : ''; }
    function swapClass(root, prefix, v) {
      root.className = root.className.split(' ').filter(function (c) { return c.indexOf(prefix) !== 0; }).join(' ') + ' ' + prefix + v;
    }
    function render() {
      var px = function (n, min, max, def) { var v = parseInt(val(n), 10); return (isNaN(v) ? def : Math.max(min, Math.min(max, v))) + 'px'; };
      roots.forEach(function (root) {
        ['line1', 'line2', 'line3', 'addr1', 'addr2', 'city', 'postal'].forEach(function (n) {
          var el = $('[data-kop="' + n + '"]', root); if (!el) return;
          var t = val(n).trim(); el.textContent = t; el.hidden = t === '';
        });
        root.style.setProperty('--kop-font', fonts[val('font')] || 'Arial, sans-serif');
        root.style.setProperty('--kop-font-addr', fonts[val('font_addr')] || fonts[val('font')] || 'Arial, sans-serif');
        root.style.setProperty('--kop-s1', px('size1', 8, 28, 12));
        root.style.setProperty('--kop-s2', px('size2', 8, 28, 12));
        root.style.setProperty('--kop-s3', px('size3', 8, 32, 14));
        root.style.setProperty('--kop-sa', px('size_addr', 7, 20, 10));
        swapClass(root, 'kop-rule-', val('rule')); swapClass(root, 'kop-case-', val('case')); swapClass(root, 'kop-w-', val('weight'));
        root.classList.toggle('kop-one', val('show_logo') !== '1');
      });
      $$('[data-cover-photo]', form).forEach(function (el) { el.hidden = val('show_cover_photo') !== '1'; });
    }
    // Lembar mini diskalakan agar selalu pas lebar kotak pratinjau.
    var boxes = $$('[data-kop-preview]', form);
    function fit() {
      boxes.forEach(function (box) {
        var sheet = $('[data-kop-sheet]', box); if (!sheet || !box.clientWidth) return;
        var cs = getComputedStyle(box), pad = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight), padY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
        var k = Math.min(1, (box.clientWidth - pad) / sheet.offsetWidth);
        sheet.style.setProperty('--kop-scale', k.toFixed(4));
        box.style.height = Math.ceil(sheet.offsetHeight * k + padY) + 'px';
      });
    }
    if (window.ResizeObserver) { var ro = new ResizeObserver(fit); boxes.forEach(function (b) { ro.observe(b); }); }
    window.addEventListener('load', fit); fit();
    form.addEventListener('input', function (e) { if (e.target.closest('[data-kop-in]')) { render(); fit(); } });
    form.addEventListener('change', function (e) {
      if (e.target.closest('[data-kop-in]')) { render(); fit(); }
      if (e.target.id === 'k-date_mode') { var b = $('[data-show-date]', form); if (b) b.hidden = e.target.value !== 'manual'; }
    });
    var reset = $('[data-kop-reset]', ked);
    if (reset) reset.addEventListener('click', function () {
      if (!confirm('Kembalikan isi & gaya kop ke bawaan dari data sekolah? Perubahan baru tersimpan setelah klik Simpan.')) return;
      Object.keys(defs).forEach(function (n) { var el = $('[data-kop-in="' + n + '"]', form); if (el) el.value = defs[n]; });
      render(); fit(); announce('Kop dikembalikan ke bawaan');
    });
  }

  /* ---------------------------------------------------------------- Panel "Pengaturan Cetak" di halaman cetak */
  $$('[data-print-opts]').forEach(function (po) {
    var f = po.closest('form'), dateBox = $('[data-print-date]', po);
    po.addEventListener('change', function (e) {
      if (e.target.matches('[data-print-date-manual]')) { if (dateBox) { dateBox.hidden = false; $('input', dateBox).focus(); } }
      else if (e.target.name === 'print[date_mode]' && dateBox) dateBox.hidden = true;
      if (e.target.matches('[data-print-auto],[data-print-date-manual]') && f) f.requestSubmit ? f.requestSubmit() : f.submit();
    });
  });

  /* ---------------------------------------------------------------- Form agenda ringkas */
  var af = $('#agenda-form');
  if (af) {
    // Tampilkan/sembunyikan bagian sesuai pilihan (mis. KET hanya saat guru terlambat/berhalangan).
    function cond() {
      $$('[data-show-when],[data-hide-when]', af).forEach(function (el) {
        var rule = el.getAttribute('data-show-when') || el.getAttribute('data-hide-when');
        var parts = rule.split(':'), cur = $('input[name="' + parts[0] + '"]:checked', af);
        var hit = parts[1].split(',').indexOf(cur ? cur.value : '') !== -1;
        el.hidden = el.hasAttribute('data-show-when') ? !hit : hit;
      });
    }
    af.addEventListener('change', function (e) { if (e.target.type === 'radio' && !e.target.hasAttribute('data-att-radio')) cond(); });
    cond();

    var selClass = d.getElementById('f-class_group_id'), selSub = d.getElementById('f-subject_id'), inHours = d.getElementById('f-lesson_hours');
    var sumClass = $('[data-sum-class]', af), sumSub = $('[data-sum-subject]', af), sumAtt = $('[data-sum-att]', af);
    function sum() {
      if (sumClass && selClass) sumClass.textContent = selClass.value ? selClass.options[selClass.selectedIndex].text : 'Pilih kelas';
      if (sumSub && selSub) sumSub.textContent = selSub.value ? '· ' + selSub.options[selSub.selectedIndex].text : '';
      var s = $('#att-summary'); if (sumAtt) sumAtt.textContent = s && s.textContent ? '· ' + s.textContent : '';
      $$('[data-slot]', af).forEach(function (b) {
        b.setAttribute('aria-pressed', String(b.getAttribute('data-class') === (selClass && selClass.value) && b.getAttribute('data-subject') === (selSub && selSub.value)));
      });
    }
    [selClass, selSub].forEach(function (s) { if (s) s.addEventListener('change', sum); });
    var attSum = $('#att-summary');
    if (attSum && window.MutationObserver) new MutationObserver(sum).observe(attSum, { childList: true, characterData: true, subtree: true });

    $$('[data-slot]', af).forEach(function (b) {
      b.addEventListener('click', function () {
        if (selSub) { selSub.value = b.getAttribute('data-subject'); selSub.dispatchEvent(new Event('change', { bubbles: true })); }
        if (inHours && b.getAttribute('data-hours')) inHours.value = b.getAttribute('data-hours');
        if (selClass) { selClass.value = b.getAttribute('data-class'); selClass.dispatchEvent(new Event('change', { bubbles: true })); }
        var topic = d.getElementById('f-topic'); if (topic) topic.focus();
        announce('Jadwal dipilih: ' + b.textContent.replace(/\s+/g, ' ').trim());
      });
    });
    sum();

    // Ctrl/Cmd+S untuk menyimpan cepat
    d.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); af.requestSubmit ? af.requestSubmit() : af.submit(); } });
  }

  /* ---------------------------------------------------------------- Drop-down (<details>) */
  d.addEventListener('click', function (e) {
    $$('details[data-dropdown][open]').forEach(function (dd) { if (!dd.contains(e.target)) dd.removeAttribute('open'); });
  });
  d.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    $$('details[data-dropdown][open]').forEach(function (dd) { dd.removeAttribute('open'); var s = $('summary', dd); if (s) s.focus(); });
  });

  /* ---------------------------------------------------------------- Ingat email di perangkat ini (login) */
  var rem = $('[data-remember]'), em = d.getElementById('email');
  if (rem && em) {
    try { var saved = localStorage.getItem('ag-email'); if (saved && !em.value) { em.value = saved; rem.checked = true; var pw = d.getElementById('password'); if (pw) pw.focus(); } } catch (e) {}
    var lf = rem.closest('form');
    if (lf) lf.addEventListener('submit', function () { try { rem.checked ? localStorage.setItem('ag-email', em.value.trim()) : localStorage.removeItem('ag-email'); } catch (e) {} });
  }

  /* ---------------------------------------------------------------- Cetak */
  d.addEventListener('click', function (e) { if (e.target.closest('[data-print]')) { e.preventDefault(); window.print(); } });

  /* ---------------------------------------------------------------- Pintasan keyboard */
  d.addEventListener('keydown', function (e) {
    if (e.target.matches('input, textarea, select, [contenteditable]')) return;
    if (e.key === '/' ) { var s = $('input[type="search"]'); if (s) { e.preventDefault(); s.focus(); } }
  });
  // ---------------------------------------------------------------- Admin: Jadwal keseluruhan (sorot guru + jadwal mini)
  (function () {
    var box = $('[data-ov]'), src = $('#ov-data');
    if (!box || !src) return;
    var data = {}; try { data = JSON.parse(src.textContent); } catch (e) { return; }
    var mini = $('[data-ov-mini]', box), cur = null;
    function esc(t) { var x = d.createElement('span'); x.textContent = t == null ? '' : String(t); return x.innerHTML; }
    function pick(id) {
      cur = cur === id ? null : id;
      $$('.ov-table .is-hl', box).forEach(function (el) { el.classList.remove('is-hl'); });
      $$('[data-ov-guru]', box).forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-ov-guru') === cur)); });
      box.classList.toggle('has-hl', !!cur);
      if (!cur) { mini.hidden = true; mini.innerHTML = ''; announce('Sorotan dihapus'); return; }
      $$('.ov-table td[data-u="' + cur + '"], .ov-piket li[data-u="' + cur + '"]', box).forEach(function (el) { el.classList.add('is-hl'); });
      var g = data[cur] || {}, items = g.items || [], last = '';
      var html = '<div class="ov-mini-head"><span class="ov-code">' + esc(g.kd || '–') + '</span><div class="min-w-0 flex-1"><b>' + esc(g.name) + '</b><small>' + esc(g.mapel || '—') + ' · ' + esc(g.jp) + ' JP/minggu</small></div>'
        + '<button type="button" class="btn btn-ghost btn-sm btn-icon size-8" data-ov-clear aria-label="Tutup sorotan">×</button></div>';
      if (!items.length) html += '<p class="ov-mini-empty">Belum ada jadwal.</p>';
      else {
        html += '<ol class="ov-mini-list">';
        items.forEach(function (it) {
          html += '<li>' + (it.d !== last ? '<span class="ov-mini-day">' + esc(it.d) + '</span>' : '<span class="ov-mini-day"></span>')
            + '<span class="ov-mini-t">' + esc(it.t) + (it.j ? ' <small>(' + esc(it.j) + ')</small>' : '') + '</span><b>' + esc(it.c) + '</b><span class="ov-mini-m">' + esc(it.m) + '</span></li>';
          last = it.d;
        });
        html += '</ol>';
      }
      mini.innerHTML = html; mini.hidden = false;
      var first = $('.ov-table td.is-hl', box);
      if (first && window.matchMedia('(min-width: 1024px)').matches) first.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
      announce((g.name || 'Guru') + ': ' + items.length + ' blok jadwal disorot');
    }
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ov-guru]'); if (b) { pick(b.getAttribute('data-ov-guru')); return; }
      if (e.target.closest('[data-ov-clear]')) { pick(cur); return; }
      var td = e.target.closest('.ov-table td[data-u]'); if (td) pick(td.getAttribute('data-u'));
    });
    var find = $('[data-ov-find]', box);
    if (find) find.addEventListener('input', function () {
      var q = find.value.trim().toLowerCase();
      $$('[data-ov-guru]', box).forEach(function (b) { b.parentNode.hidden = q !== '' && b.getAttribute('data-ov-text').indexOf(q) === -1; });
    });
  })();
  // ---------------------------------------------------------------- Cetak jadwal: muat 1 halaman (ukuran kertas dari Pengaturan Cetak)
  (function () {
    var MM = 96 / 25.4;
    function fit() {
      $$('[data-fit-w][data-fit-h]').forEach(function (el) {
        el.style.zoom = '';
        var aw = parseFloat(el.getAttribute('data-fit-w')) * MM, ah = parseFloat(el.getAttribute('data-fit-h')) * MM;
        el.classList.add('is-fitting');            // tata letak cetak (tanpa nama singkat, tanpa gulir)
        var w = Math.max(el.scrollWidth, el.offsetWidth), h = el.scrollHeight;
        $$('.table-wrap', el).forEach(function (t) { w = Math.max(w, t.scrollWidth + 24); });
        // data-fit-only="w": cukup selebar kertas, tinggi berlanjut ke halaman berikut (tulisan tetap besar)
        var z = (el.getAttribute('data-fit-only') === 'w' ? Math.min(1, aw / w) : Math.min(1, aw / w, ah / h)) * 0.97;
        if (z < 1) el.style.zoom = z.toFixed(3);
      });
    }
    function unfit() { $$('[data-fit-w]').forEach(function (el) { el.style.zoom = ''; el.classList.remove('is-fitting'); }); }
    window.addEventListener('beforeprint', fit);
    window.addEventListener('afterprint', unfit);
  })();
  // ---------------------------------------------------------------- Sampul Buku Agenda: pratinjau langsung blanko & posisi isian
  (function () {
    var form = $('[data-cvr-form]'), prev = $('[data-cvr-preview]'); if (!form || !prev) return;
    var sheet = $('.cover-sheet.is-blank', prev), img = $('.cv-blank-img', prev), dl = $('[data-cv-fields]', prev), empty = $('.cv-blank-empty', prev);
    var builtin = $('[data-cvr-builtin]', prev), wrap = $('[data-cvr-blankwrap]', prev);
    function sync() {
      var y = $('[data-cvr="y"]', form), f = $('[data-cvr="fields"]', form), b = $('[data-cvr="box"]', form), yv = $('[data-cvr-yv]', form);
      if (dl) { dl.style.top = y.value + '%'; dl.hidden = !f.checked; dl.classList.toggle('has-box', b.checked); }
      if (yv) yv.textContent = y.value + '%';
    }
    form.addEventListener('input', function (e) { if (e.target.matches('[data-cvr]')) sync(); });
    form.addEventListener('change', function (e) {
      if (!e.target.matches('[data-cvr-file]') || !e.target.files[0] || !img) return;
      img.src = URL.createObjectURL(e.target.files[0]); img.hidden = false;
      if (builtin) builtin.hidden = true; if (wrap) wrap.hidden = false;
      if (empty) empty.remove(); if (sheet) sheet.classList.remove('is-empty');
    });
  })();
  // ---------------------------------------------------------------- Struktur jam: tambah baris & ganti jenis
  (function () {
    function sync(tr) {
      var k = $('[data-sb-kind]', tr); if (!k) return;
      var act = k.value === 'kegiatan';
      tr.classList.toggle('is-act', act);
      var no = $('.sb-no', tr), lb = $('.sb-label', tr);
      if (no) no.hidden = act; if (lb) lb.hidden = !act;
    }
    $$('tr.sb-slot').forEach(sync);
    d.addEventListener('change', function (e) { if (e.target.matches('[data-sb-kind]')) sync(e.target.closest('tr')); });
    var seq = 10000;
    d.addEventListener('click', function (e) {
      var b = e.target.closest('[data-sb-add]'); if (!b) return;
      var body = $('[data-sb-rows="' + b.getAttribute('data-sb-add') + '"]'); if (!body) return;
      var last = body.querySelector('tr:last-child'); if (!last) return;
      var tr = last.cloneNode(true), i = seq++;
      $$('input, select', tr).forEach(function (el) {
        el.name = el.name.replace(/slot\[\d+\]/, 'slot[' + i + ']');
        if (el.type === 'checkbox') el.checked = false; else if (el.type !== 'hidden') el.value = el.tagName === 'SELECT' ? 'pelajaran' : '';
      });
      body.appendChild(tr); sync(tr); var f = $('input[type="time"]', tr); if (f) f.focus();
    });
  })();
  // ---------------------------------------------------------------- Kalender guru: klik tanggal → jadwal hari itu & tulis agenda
  (function () {
    var src = $('#cal-teach'), dlg = $('#cal-day-dlg');
    if (!src || !dlg) return;
    var data = {}; try { data = JSON.parse(src.textContent); } catch (e) { return; }
    var esc = function (t) { var x = d.createElement('span'); x.textContent = t == null ? '' : String(t); return x.innerHTML; };
    var today = new Date(); today = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
    var hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'], bln = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    function open(date) {
      var t = (data.days || {})[date] || { slots: [], extra: [], acts: [], holiday: null }, ev = (data.events || {})[date] || [];
      var dt = new Date(date + 'T00:00:00'), past = date < today, acts = t.acts || [];
      var done = t.slots.filter(function (s) { return s.done; }).length, total = t.slots.length;
      $('#cal-dlg-title', dlg).textContent = 'Agenda ' + dt.getDate() + ' ' + bln[dt.getMonth()].slice(0, 3) + ' ' + dt.getFullYear() + ' · ' + hari[dt.getDay()];
      var st = $('[data-cal-state]', dlg);
      if (st) {
        var lbl = !total ? (t.extra.length ? t.extra.length + ' agenda' : (acts.length ? acts.length + ' kegiatan' : 'Tanpa jadwal')) : (done >= total ? 'Lengkap ' + done + '/' + total : (past || date === today ? 'Belum lengkap ' + done + '/' + total : (done ? 'Terisi ' + done + '/' + total : total + ' jadwal')));
        st.textContent = lbl; st.className = 'cal-dlg-state ' + (!total ? 'is-none' : (done >= total ? 'is-ok' : (past ? 'is-miss' : 'is-plan')));
      }
      var csrfN = d.documentElement.getAttribute('data-csrf-name'), csrfH = d.documentElement.getAttribute('data-csrf-hash');
      function row(s, kind) {
        var has = !!s.id, state = has ? 'is-done' : (past ? 'is-miss' : 'is-plan');
        var chips = '';
        if (s.jam) chips += '<span class="cal-chip">Jam ' + esc(s.jam) + '</span>';
        if (has && s.att) chips += '<span class="cal-chip is-info">Hadir ' + esc(s.att) + '</span>';
        if (has && s.statusLabel) chips += '<span class="cal-chip ' + (s.status === 'batal' ? 'is-bad' : (s.status === 'draf' ? 'is-warn' : 'is-ok')) + '">' + esc(s.statusLabel) + '</span>';
        if (has && s.complete === 'belum') chips += '<span class="cal-chip is-warn">Materi belum selesai</span>';
        var act = has
          ? '<a class="btn btn-ghost btn-sm" href="' + esc(s.url) + '" data-cal-form>Ubah</a>'
            + '<form method="post" action="' + esc(s.del) + '" data-confirm="Hapus agenda ' + esc(s['class'] + ' · ' + s.subject) + '?" data-confirm-label="Ya, hapus">'
            + '<input type="hidden" name="' + esc(csrfN) + '" value="' + esc(csrfH) + '"><input type="hidden" name="from" value="calendar:' + esc(date.slice(0, 7)) + '"><button class="btn btn-danger btn-sm">Hapus</button></form>'
          : '<a class="btn btn-primary btn-sm" href="' + esc(s.url) + '" data-cal-form>Isi agenda</a>';
        return '<tr class="' + state + '">'
          + '<td class="cal-t-time" data-label="Jam"><b>' + esc(s.time || '—') + '</b><span class="cal-kind">' + (kind === 'extra' ? 'Di luar jadwal' : 'Jadwal') + '</span></td>'
          + '<td class="cal-t-dot"><i class="cal-st ' + state + '" title="' + (has ? 'Sudah diisi' : (past ? 'Belum diisi' : 'Akan datang')) + '"></i></td>'
          + '<td data-label="Kelas · Mapel"><b>' + esc(s['class']) + '</b><span class="cal-mp">' + (s.code ? '<span class="mp-chip" style="--mp:' + esc(s.clr || '#64748b') + '">' + esc(s.code) + '</span> ' : '') + esc(s.subject) + '</span></td>'
          + '<td data-label="TP">' + (has && s.tp ? '<span class="cal-clamp">' + esc(s.tp) + '</span>' : '<span class="text-muted">—</span>') + '</td>'
          + '<td data-label="Pokok bahasan">' + (has ? '<span class="cal-clamp">' + esc(s.topic) + '</span>' : '<span class="text-muted">' + (past ? 'Belum ditulis' : 'Belum diisi') + '</span>') + '</td>'
          + '<td class="cal-t-chips" data-label="Info">' + (chips || '<span class="text-muted">—</span>') + '</td>'
          + '<td class="cal-t-act">' + act + '</td></tr>';
      }
      var h = '';
      if (ev.length) h += '<ul class="cal-dlg-ev">' + ev.map(function (e) { return '<li class="k-' + esc(e.k) + '">' + esc(e.t) + '</li>'; }).join('') + '</ul>';
      if (t.holiday) h += '<p class="alert alert-warning">Tanggal ini tercatat sebagai hari libur. Isi agenda hanya bila tetap ada kegiatan belajar.</p>';
      if (!t.slots.length && !t.extra.length) {
        h += '<p class="cal-dlg-empty">Tidak ada jadwal mengajar pada tanggal ini. Klik <b>+ Tambah</b> untuk agenda di luar jadwal atau kegiatan lain.</p>';
      } else {
        h += '<div class="cal-table-wrap"><table class="cal-table"><thead><tr><th>Jam</th><th><span class="sr-only">Status</span></th><th>Kelas · Mapel</th><th>TP</th><th>Pokok bahasan</th><th>Info</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody>'
          + t.slots.map(function (s) { return row(s, 'slot'); }).join('') + t.extra.map(function (s) { return row(s, 'extra'); }).join('')
          + '</tbody></table></div>';
      }
      if (acts.length) {
        h += '<p class="cal-sec-title">Kegiatan di luar KBM</p><div class="cal-table-wrap"><table class="cal-table"><thead><tr><th>Jam</th><th><span class="sr-only">Status</span></th><th>Kegiatan</th><th>Jenis</th><th>Uraian</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody>'
          + acts.map(function (k) {
            return '<tr class="is-act"><td class="cal-t-time" data-label="Jam"><b>' + esc(k.time || '—') + '</b><span class="cal-kind">Kegiatan</span></td>'
              + '<td class="cal-t-dot"><i class="cal-st is-act" title="Kegiatan di luar KBM"></i></td>'
              + '<td data-label="Kegiatan"><b>' + esc(k.title) + '</b>' + (k.place ? '<span class="cal-mp">' + esc(k.place) + '</span>' : '') + '</td>'
              + '<td data-label="Jenis"><span class="cal-chip is-info">' + esc(k.type) + '</span></td>'
              + '<td data-label="Uraian">' + (k.desc ? '<span class="cal-clamp">' + esc(k.desc) + '</span>' : '<span class="text-muted">—</span>')
              + (k.file ? ' <a class="cal-chip" href="' + esc(k.file) + '" target="_blank" rel="noopener">Lampiran</a>' : '') + '</td>'
              + '<td class="cal-t-act"><a class="btn btn-ghost btn-sm" href="' + esc(k.url) + '" data-cal-form>Ubah</a>'
              + '<form method="post" action="' + esc(k.del) + '" data-confirm="Hapus kegiatan ' + esc(k.title) + '?" data-confirm-label="Ya, hapus">'
              + '<input type="hidden" name="' + esc(csrfN) + '" value="' + esc(csrfH) + '"><input type="hidden" name="from" value="calendar:' + esc(date.slice(0, 7)) + '"><button class="btn btn-danger btn-sm">Hapus</button></form></td></tr>';
          }).join('') + '</tbody></table></div>';
      }
      $('[data-cal-body]', dlg).innerHTML = h;
      var addA = $('[data-cal-add="agenda"]', dlg), addK = $('[data-cal-add="activity"]', dlg);
      if (addA) addA.href = data.newUrl + '?agenda_date=' + date;
      if (addK) addK.href = (data.actUrl || '') + '?activity_date=' + date;
      showList();
      if (!dlg.open) { if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', ''); }
    }
    var frame = $('[data-cal-frame]', dlg), wrap = $('[data-cal-framewrap]', dlg), back = $('[data-cal-back]', dlg);
    // Pesan "agenda tersimpan" dari popup sebelumnya (setelah kalender dimuat ulang)
    try {
      var saved = JSON.parse(sessionStorage.getItem('cal-saved') || 'null');
      if (saved) {
        sessionStorage.removeItem('cal-saved');
        var box = d.createElement('div');
        box.className = 'alert alert-success mb-4 no-print'; box.setAttribute('role', 'status');
        box.innerHTML = '<div class="flex-1">' + esc(saved.msg) + '</div>' + (saved.action && saved.action.url && /^https?:\/\//.test(saved.action.url) && saved.action.url.indexOf(location.origin) === 0
          ? ' <a class="btn btn-primary btn-sm" href="' + esc(saved.action.url) + '">' + esc(saved.action.label) + '</a>' : '');
        var card = $('.cal') && $('.cal').closest('.card'), grid = card && card.parentNode; if (grid && grid.parentNode) grid.parentNode.insertBefore(box, grid);
      }
    } catch (er) {}
    function showList() {
      dlg.classList.remove('is-form'); if (wrap) wrap.hidden = true; if (frame) frame.src = 'about:blank';
      $('[data-cal-body]', dlg).hidden = false; $('[data-cal-foot]', dlg).hidden = false; if (back) back.hidden = true;
      var oth = $('[data-cal-other]', dlg), sta = $('[data-cal-state]', dlg); if (oth) oth.hidden = false; if (sta) sta.hidden = false;
      $('[data-cal-kicker]', dlg).textContent = 'Agenda & Jurnal Mengajar';
    }
    function showForm(url) {
      if (!frame) { location.href = url; return; }
      frame.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'embed=1';
      dlg.classList.add('is-form'); wrap.hidden = false; $('[data-cal-body]', dlg).hidden = true; $('[data-cal-foot]', dlg).hidden = true;
      if (back) back.hidden = false; $('[data-cal-kicker]', dlg).textContent = /\/activities\//.test(url) ? 'Kegiatan di luar KBM' : 'Pengisian Agenda & Jurnal Mengajar';
      var menu = $('details[data-cal-other]', dlg); if (menu) menu.removeAttribute('open');
      var oth = $('[data-cal-other]', dlg), sta = $('[data-cal-state]', dlg); if (oth) oth.hidden = true; if (sta) sta.hidden = true;
    }
    dlg.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-cal-form]');
      if (a) { e.preventDefault(); showForm(a.href); return; }
      if (e.target.closest('[data-cal-back]')) showList();
    });
    // Formulir di popup memberi tahu kalender: tersimpan → muat ulang; "Kembali" → daftar jadwal
    window.addEventListener('message', function (e) {
      if (e.origin !== location.origin || !e.data) return;
      if (e.data.type === 'agenda-saved') {
        try { sessionStorage.setItem('cal-saved', JSON.stringify({ msg: e.data.msg || 'Agenda tersimpan.', action: e.data.action || null })); } catch (er) {}
        dlg.close(); location.reload();
      }
      if (e.data.type === 'agenda-cancel') showList();
    });
    d.addEventListener('click', function (e) {
      var t = e.target.closest('[data-cal-day]');
      if (t && !e.target.closest('dialog')) { e.preventDefault(); open(t.getAttribute('data-cal-day')); return; }
      if (e.target.closest('[data-cal-close]') || e.target === dlg) dlg.close();
    });
  })();
  // Formulir di dalam popup: tombol "Kembali" memberi tahu halaman induk
  d.addEventListener('click', function (e) {
    if (!e.target.closest('[data-embed-cancel]')) return;
    try { window.parent.postMessage({ type: 'agenda-cancel' }, location.origin); } catch (er) {}
  });
  // ---------------------------------------------------------------- Dashboard admin: tampilkan guru lainnya
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ad-more]'); if (!b) return;
    var t = d.getElementById(b.getAttribute('aria-controls')); if (!t) return;
    var open = t.hidden; t.hidden = !open; b.setAttribute('aria-expanded', String(open));
    $('span', b).textContent = open ? 'Sembunyikan' : 'Tampilkan ' + t.rows.length + ' guru lainnya';
  });
  // ---------------------------------------------------------------- Tugas harian: buka isian & "tidak kumpul"
  d.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches('[data-toggle-target]')) { var box = $(t.getAttribute('data-toggle-target')); if (box) box.hidden = !t.checked; }
    if (t.matches('[data-sc-miss]')) { var inp = d.getElementById(t.getAttribute('data-sc-miss')); if (inp) { inp.disabled = t.checked; if (t.checked) inp.value = ''; inp.placeholder = t.checked ? 'Tidak kumpul' : ''; var mg = d.getElementById(inp.getAttribute('data-msg')); if (mg) mg.textContent = t.checked ? 'Tidak kumpul' : (inp.value === '' ? 'Belum diinput' : ''); } }
  });
  $$('[data-sc-miss]:checked').forEach(function (t) { var inp = d.getElementById(t.getAttribute('data-sc-miss')); if (inp) { inp.disabled = true; inp.placeholder = 'Tidak kumpul'; } });

  // ---------------------------------------------------------------- Lihat/sembunyikan kata sandi (ikon mata) + salin
  var EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.9 10.9 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a10 10 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  $$('input[type="password"]:not([data-no-eye])').forEach(function (inp) {
    if (inp.parentNode.classList.contains('pw-wrap')) return;
    var wrap = d.createElement('span'); wrap.className = 'pw-wrap';
    inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp);
    var b = d.createElement('button'); b.type = 'button'; b.className = 'pw-eye';
    b.setAttribute('aria-label', 'Tampilkan kata sandi'); b.setAttribute('aria-pressed', 'false'); b.title = 'Tampilkan kata sandi'; b.innerHTML = EYE;
    b.addEventListener('click', function () {
      var show = inp.type === 'password'; inp.type = show ? 'text' : 'password';
      b.innerHTML = show ? EYE_OFF : EYE; b.setAttribute('aria-pressed', String(show));
      b.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'); b.title = b.getAttribute('aria-label');
      inp.focus();
    });
    wrap.appendChild(b);
  });
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    var src = d.getElementById(b.getAttribute('data-copy')); if (!src) return;
    var done = function () { var t = b.getAttribute('data-copied') || 'Tersalin'; var o = b.innerHTML; b.textContent = t; setTimeout(function () { b.innerHTML = o; }, 1600); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(src.value).then(done); }
    else { var ty = src.type; src.type = 'text'; src.select(); try { d.execCommand('copy'); done(); } catch (x) {} src.type = ty; }
  });
})();
