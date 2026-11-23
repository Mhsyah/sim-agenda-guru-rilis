<?php

namespace App\Controllers;

use App\Models\PrincipalReviewModel;

/**
 * "Kesan-kesan Kepala Sekolah": sekali sebulan Buku Agenda Guru diserahkan kepada
 * kepala sekolah; tanggapannya dicatat di sini dan ikut tercetak di Buku Jurnal.
 */
class PrincipalReviews extends CrudController
{
    protected string $modelClass = PrincipalReviewModel::class;
    protected string $table      = 'principal_reviews';
    protected string $base       = 'principal-reviews';
    protected string $title      = 'Kesan-kesan Kepala Sekolah';
    protected string $singular   = 'Kesan kepala sekolah';
    protected string $entity     = 'principal_review';
    protected ?string $intro     = 'Buku Agenda Guru diserahkan kepada kepala sekolah sekali sebulan untuk diberi tanggapan. Catat tanggapan dan parafnya di sini.';
    protected array $searchFields = ['principal_reviews.notes'];
    protected array $order       = ['review_date', 'DESC'];
    protected bool $yearScoped   = true;
    protected bool $archive      = false;
    protected bool $audit        = true;
    protected bool $ownerScoped  = true;

    protected function fields(?array $row): array
    {
        return [
            'review_date'   => ['label' => 'Tanggal', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]', 'default' => date('Y-m-d')],
            'reviewer_name' => ['label' => 'Kepala sekolah', 'rules' => 'permit_empty|max_length[150]', 'default' => safe_profile()['principal_name'] ?? ''],
            'notes'         => ['label' => 'Kesan-kesan kepala sekolah', 'type' => 'textarea', 'rules' => 'required|max_length[3000]', 'span' => 'full'],
            'is_signed'     => ['label' => 'Sudah diparaf kepala sekolah', 'type' => 'checkbox'],
        ];
    }

    protected function columns(): array
    {
        return [
            'review_date'   => ['label' => 'Tanggal', 'fn' => static fn ($r) => '<span class="whitespace-nowrap">' . fmt_date($r['review_date']) . '</span>'],
            'notes'         => ['label' => 'Kesan-kesan', 'class' => 'col-main', 'fn' => static fn ($r) => nl2br(esc($r['notes']))],
            'reviewer_name' => 'Kepala sekolah',
            'is_signed'     => ['label' => 'Paraf', 'fn' => static fn ($r) => (int) $r['is_signed'] ? badge('Sudah diparaf', 'success') : badge('Belum', 'warning')],
        ];
    }

    protected function headerLinks(): array
    {
        return [['label' => 'Cetak Buku Jurnal', 'url' => site_url('reports/journal'), 'icon' => 'printer']];
    }
}
