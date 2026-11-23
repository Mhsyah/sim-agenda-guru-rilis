<?php
/** Halaman singkat setelah agenda disimpan dari popup Kalender: memberi tahu kalender lalu menutup popup. */
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Agenda tersimpan</title></head>
<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:90vh;color:#16a34a">
<p>✓ Agenda tersimpan. Memperbarui kalender…</p>
<script>
    try { window.parent.postMessage({ type: 'agenda-saved', msg: <?= json_encode($msg ?? 'Agenda tersimpan.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, action: <?= json_encode($action ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> }, window.location.origin); } catch (e) {}
    setTimeout(function () { if (window.parent === window) window.location.href = <?= json_encode(site_url('calendar')) ?>; }, 800);
</script>
</body></html>
