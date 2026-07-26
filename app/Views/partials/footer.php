</main>

<?php
require_once __DIR__ . '/../../Models/SettingModel.php';
require_once __DIR__ . '/../../Helpers/format.php';
$pengaturanFooter = \App\Models\SettingModel::getAll();

$jamOperasionalFooter = format_jam_operasional($pengaturanFooter['jam_buka'] ?? '', $pengaturanFooter['jam_tutup'] ?? '');

$noHpFooter = $pengaturanFooter['whatsapp'] ?? ($pengaturanFooter['no_hp'] ?? '');
$waLinkFooter = null;
if ($noHpFooter !== '') {
    $noHpBersih = preg_replace('/[^0-9]/', '', $noHpFooter);
    $waLinkFooter = 'https://wa.me/' . (str_starts_with($noHpBersih, '0') ? '62' . substr($noHpBersih, 1) : $noHpBersih);
}

$igHandleFooter = trim($pengaturanFooter['instagram'] ?? '', "@ \t\n\r\0\x0B");
$igLinkFooter = $igHandleFooter !== '' ? 'https://instagram.com/' . $igHandleFooter : null;

$alamatFooter = $pengaturanFooter['alamat'] ?? "Jl. Sorowajan Baru, Tegal Tanda,\nBanguntapan, Bantul, DIY 55198";
$mapsUrlFooter = $pengaturanFooter['maps_url'] ?? '';
$deskripsiFooter = $pengaturanFooter['deskripsi_usaha'] ?? 'Partner setia petualanganmu. Sewa peralatan camping dan hiking dengan mudah dan terpercaya di Yogyakarta.';
?>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <p class="footer-brand-name">Merimba Outdoor</p>
                <p class="footer-brand-desc"><?= nl2br(htmlspecialchars($deskripsiFooter)) ?></p>
            </div>
            <div>
                <p class="footer-col-title">Informasi Booking</p>
                <div class="footer-col-text footer-info-list">
                    <?php if ($noHpFooter !== ''): ?>
                        <div class="footer-info-row">
                            <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-whatsapp"></use></svg>
                            <?php if ($waLinkFooter): ?>
                                <a href="<?= htmlspecialchars($waLinkFooter) ?>" target="_blank" rel="noopener" style="color: inherit;"><?= htmlspecialchars($noHpFooter) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($noHpFooter) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($igLinkFooter): ?>
                        <div class="footer-info-row">
                            <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-instagram"></use></svg>
                            <a href="<?= htmlspecialchars($igLinkFooter) ?>" target="_blank" rel="noopener" style="color: inherit;">@<?= htmlspecialchars($igHandleFooter) ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if ($jamOperasionalFooter !== ''): ?>
                        <div class="footer-info-row">
                            <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-clock"></use></svg>
                            <?= htmlspecialchars($jamOperasionalFooter) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <p class="footer-col-title">Lokasi</p>
                <?php if ($mapsUrlFooter !== ''): ?>
                    <a href="<?= htmlspecialchars($mapsUrlFooter) ?>" target="_blank" rel="noopener" class="footer-col-text" style="color: inherit; display: block;"><?= nl2br(htmlspecialchars($alamatFooter)) ?></a>
                <?php else: ?>
                    <p class="footer-col-text"><?= nl2br(htmlspecialchars($alamatFooter)) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Merimba Outdoor. Hak cipta dilindungi.</span>
            <div class="footer-bottom-links">
                <a href="syarat-privasi.php#syarat">Syarat & Ketentuan</a>
                <a href="syarat-privasi.php#privasi">Kebijakan Privasi</a>
            </div>
        </div>
    </div>
</footer>

<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <a href="index.php" class="bottom-nav-item <?= ($halamanAktif ?? '') === 'home' ? 'active' : '' ?>">
            <svg class="icon"><use href="assets/icons/sprite.svg#icon-home"></use></svg>
            Beranda
        </a>
        <a href="katalog.php" class="bottom-nav-item <?= ($halamanAktif ?? '') === 'katalog' ? 'active' : '' ?>">
            <svg class="icon"><use href="assets/icons/sprite.svg#icon-catalog"></use></svg>
            Katalog
        </a>
        <a href="keranjang.php" class="bottom-nav-item <?= ($halamanAktif ?? '') === 'keranjang' ? 'active' : '' ?>">
            <svg class="icon"><use href="assets/icons/sprite.svg#icon-cart"></use></svg>
            Booking
        </a>
        <a href="tracking.php" class="bottom-nav-item <?= ($halamanAktif ?? '') === 'tracking' ? 'active' : '' ?>">
            <svg class="icon"><use href="assets/icons/sprite.svg#icon-tracking"></use></svg>
            Tracking
        </a>
        <a href="akun.php" class="bottom-nav-item <?= ($halamanAktif ?? '') === 'akun' ? 'active' : '' ?>">
            <svg class="icon"><use href="assets/icons/sprite.svg#icon-account"></use></svg>
            Akun
        </a>
    </div>
</nav>

</body>
</html>