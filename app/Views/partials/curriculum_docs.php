<?php
/**
 * Arsip dokumen CP/TP/ATP/KKTP: formulir unggah + daftar.
 *
 * @var array $docs
 * @var array $subjects  id => nama (mapel yang boleh dipilih)
 * @var bool  $isAdmin
 */
use App\Controllers\Curriculum;

$bytes = static fn (int $b) => $b >= 1048576 ? fmt_num($b / 1048576, 1) . ' MB' : fmt_num(max(1, $b / 1024), 0) . ' KB';
?>
<div class="kur-docs">
    <form method="post" action="<?= site_url('curriculum/docs') ?>" enctype="multipart/form-data" class="kur-docform" data-progress="Mengunggah dokumen">
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="kd-subject">Mata pelajaran</label>
            <select class="input" id="kd-subject" name="subject_id" required>
                <option value="">— Pilih —</option>
                <?php foreach ($subjects as $id => $n): ?><option value="<?= $id ?>"><?= esc($n) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="kd-type">Jenis</label>
            <select class="input" id="kd-type" name="doc_type" required>
                <?php foreach (Curriculum::DOC_TYPES as $k => $n): ?><option value="<?= $k ?>"><?= esc($n) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="kd-phase">Fase</label>
            <select class="input" id="kd-phase" name="phase">
                <option value="">—</option>
                <?php foreach (phases() as $k => $n): ?><option value="<?= esc($k, 'attr') ?>"><?= esc($n) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="kd-sem">Semester</label>
            <select class="input" id="kd-sem" name="semester_id">
                <option value="">Keduanya</option>
                <?php foreach (\App\Libraries\Lookup::semesters() as $sid => $sn): ?><option value="<?= $sid ?>"><?= esc($sn) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="field kur-span2">
            <label class="label" for="kd-title">Judul (opsional)</label>
            <input class="input" id="kd-title" name="title" maxlength="150" placeholder="mis. ATP Matematika Kelas VII">
        </div>
        <div class="field kur-span2">
            <label class="label" for="kd-file">File</label>
            <input class="input py-2" type="file" id="kd-file" name="doc" accept=".pdf,.docx,.xlsx,.pptx" required aria-describedby="kd-help">
            <p class="help" id="kd-help">PDF, DOCX, XLSX, atau PPTX · maks. 5 MB.<?= $isAdmin ? ' Dokumen admin dapat diunduh semua guru pengampu mapel tersebut.' : '' ?></p>
        </div>
        <div class="kur-span2 flex items-end"><button class="btn btn-primary"><?= icon('upload', 'size-4') ?> Unggah dokumen</button></div>
    </form>

    <?php $semNames = \App\Libraries\Lookup::semesters(); $semSel = (int) service('request')->getGet('semester_id'); ?>
    <form method="get" class="kur-docfilter mt-4" aria-label="Saring dokumen per semester">
        <span class="text-xs text-muted">Semester:</span>
        <a href="?" class="cal-chip <?= $semSel ? '' : 'is-ok' ?>">Semua</a>
        <?php foreach ($semNames as $sid => $sn): ?><a href="?semester_id=<?= $sid ?>#dokumen" class="cal-chip <?= $semSel === (int) $sid ? 'is-ok' : '' ?>"><?= esc($sn) ?></a><?php endforeach ?>
    </form>
    <?php if (! $docs): ?>
        <p class="mt-4 rounded-xl border border-dashed border-line p-6 text-center text-sm text-muted">Belum ada dokumen pada tahun pelajaran ini.</p>
    <?php else: ?>
        <ul class="kur-list mt-4" aria-label="Daftar dokumen">
            <?php foreach ($docs as $d): $mine = $isAdmin || (int) $d['user_id'] === (int) ($authUser['id'] ?? 0); ?>
                <li>
                    <span class="kur-type kur-<?= esc($d['doc_type'], 'attr') ?>"><?= esc(Curriculum::DOC_TYPES[$d['doc_type']] ?? $d['doc_type']) ?></span>
                    <span class="min-w-0 flex-1">
                        <b class="block truncate"><?= esc($d['title']) ?></b>
                        <small class="block truncate text-muted"><?= esc($d['subject_name']) ?><?= $d['phase'] ? ' · Fase ' . esc($d['phase']) : '' ?> · <?= $d['semester_id'] ? esc($semNames[$d['semester_id']] ?? 'Semester') : 'Keduanya' ?> · <?= $bytes((int) $d['file_size']) ?> · <?= $d['user_id'] ? esc($d['uploader'] ?? 'Guru') : 'Sekolah' ?></small>
                    </span>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('curriculum/docs/' . $d['id']) ?>"><?= icon('download', 'size-4') ?><span class="sr-only sm:not-sr-only"> Unduh</span></a>
                    <?php if ($mine): ?>
                        <form method="post" action="<?= site_url('curriculum/docs/' . $d['id'] . '/delete') ?>" data-confirm="Hapus dokumen “<?= esc($d['title'], 'attr') ?>”?" data-confirm-label="Ya, hapus">
                            <?= csrf_field() ?><button class="btn btn-ghost btn-sm" aria-label="Hapus <?= esc($d['title'], 'attr') ?>"><?= icon('trash', 'size-4') ?></button>
                        </form>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</div>
