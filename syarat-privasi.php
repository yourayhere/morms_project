<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Models/SettingModel.php';

use App\Core\Session;
use App\Models\SettingModel;

Session::start();

$syaratKetentuan = SettingModel::get('syarat_ketentuan');
$kebijakanPrivasi = SettingModel::get('kebijakan_privasi');

$judulHalaman = 'Syarat & Ketentuan dan Kebijakan Privasi';
$halamanAktif = '';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 32px 0 56px;">
    <div class="container" style="max-width: 720px;">

        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 24px;">Syarat & Ketentuan dan Kebijakan Privasi</h1>

        <div id="syarat" class="card" style="padding: 24px; margin-bottom: 18px; scroll-margin-top: 90px;">
            <h2 style="font-size: 16px; display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
                <svg class="icon icon-accent"><use href="assets/icons/sprite.svg#icon-note"></use></svg>
                Syarat & Ketentuan
            </h2>
            <?php if ($syaratKetentuan !== ''): ?>
                <p style="font-size: 13.5px; line-height: 1.75; color: var(--color-text);"><?= nl2br(htmlspecialchars($syaratKetentuan)) ?></p>
            <?php else: ?>
                <p style="font-size: 13.5px; color: var(--color-text-muted);">Syarat & ketentuan belum ditambahkan oleh admin.</p>
            <?php endif; ?>
        </div>

        <div id="privasi" class="card" style="padding: 24px; scroll-margin-top: 90px;">
            <h2 style="font-size: 16px; display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
                <svg class="icon icon-accent"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                Kebijakan Privasi
            </h2>
            <?php if ($kebijakanPrivasi !== ''): ?>
                <p style="font-size: 13.5px; line-height: 1.75; color: var(--color-text);"><?= nl2br(htmlspecialchars($kebijakanPrivasi)) ?></p>
            <?php else: ?>
                <p style="font-size: 13.5px; color: var(--color-text-muted);">Kebijakan privasi belum ditambahkan oleh admin.</p>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>
