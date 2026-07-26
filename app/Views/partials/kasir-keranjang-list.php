<?php
// Partial ini dipakai DUA tempat: render awal kasir.php, dan hasil AJAX
// (aksi/tambah-kasir.php, hapus-kasir.php, ubah-jumlah-kasir.php) lewat
// output buffering - supaya markup keranjang selalu identik di kedua jalur,
// tidak ada logic tampilan yang dobel ditulis ulang di JS.
// Variabel yang harus sudah ada di scope pemanggil: $keranjangKasir (array).
?>
<?php if (empty($keranjangKasir)): ?>
    <p style="font-size: 12.5px; color: var(--color-text-muted); text-align: center; padding: 16px 0;">Belum ada barang dipilih.</p>
<?php else: ?>
    <?php foreach ($keranjangKasir as $index => $barang): ?>
        <div class="baris-keranjang-kasir" data-index="<?= $index ?>" style="padding: 10px 0; border-bottom: 1px solid var(--color-border); font-size: 12.5px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                <div>
                    <strong><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran'])): ?> <span style="font-weight: 400; color: var(--color-text-muted);">(Ukuran <?= htmlspecialchars($barang['ukuran']) ?>)</span><?php endif; ?></strong>
                    <p class="teks-jumlah-kasir" style="color: var(--color-text-muted); margin: 2px 0 2px;"><?= (int) $barang['jumlah'] ?> unit &times; <?= (int) $barang['durasi'] ?> malam</p>
                    <p style="color: var(--color-text-muted); font-size: 11.5px; margin: 0 0 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span class="periode-tampil-kasir"><?= htmlspecialchars(format_periode_sewa($barang['tanggal_ambil'], $barang['tanggal_kembali'], $barang['jam_ambil'] ?? null)) ?></span>
                        <button type="button" class="btn-toggle-tanggal-kasir" data-index="<?= $index ?>" style="background: none; border: none; color: var(--color-accent); cursor: pointer; font-weight: 600; font-size: 11px; padding: 0;">Ubah Tanggal</button>
                    </p>
                    <div class="qty-selector-kasir" style="display: inline-flex; align-items: center; gap: 0; border: 1px solid var(--color-border); border-radius: 6px; overflow: hidden;">
                        <button type="button" class="btn-qty-kurang" data-index="<?= $index ?>" aria-label="Kurangi jumlah" style="width: 26px; height: 26px; border: none; background: var(--color-bg); cursor: pointer; font-size: 14px; font-weight: 700; color: var(--color-text);">&minus;</button>
                        <span class="qty-value-kasir" style="min-width: 28px; text-align: center; font-weight: 600;"><?= (int) $barang['jumlah'] ?></span>
                        <button type="button" class="btn-qty-tambah" data-index="<?= $index ?>" aria-label="Tambah jumlah" style="width: 26px; height: 26px; border: none; background: var(--color-bg); cursor: pointer; font-size: 14px; font-weight: 700; color: var(--color-text);">&plus;</button>
                    </div>
                </div>
                <div style="text-align: right;">
                    <p class="subtotal-baris-kasir" style="font-weight: 600;"><?= format_rupiah((float) $barang['subtotal']) ?></p>
                    <button type="button" class="btn-hapus-kasir" data-index="<?= $index ?>" style="background: none; border: none; color: var(--color-danger); font-size: 11px; cursor: pointer; margin-top: 6px;">Hapus</button>
                </div>
            </div>

            <div class="panel-ubah-tanggal-kasir" data-index="<?= $index ?>" style="display: none; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--color-border);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 8px; margin-bottom: 8px;">
                    <div>
                        <label style="display: block; font-size: 10.5px; font-weight: 600; margin-bottom: 3px;">Tanggal Ambil</label>
                        <input type="date" class="input-ambil-kasir" value="<?= htmlspecialchars($barang['tanggal_ambil']) ?>" min="<?= date('Y-m-d') ?>"
                            style="width: 100%; padding: 6px 7px; border: 1px solid var(--color-border); border-radius: 5px; font-size: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 10.5px; font-weight: 600; margin-bottom: 3px;">Tanggal Kembali</label>
                        <input type="date" class="input-kembali-kasir" value="<?= htmlspecialchars($barang['tanggal_kembali']) ?>" min="<?= date('Y-m-d') ?>"
                            style="width: 100%; padding: 6px 7px; border: 1px solid var(--color-border); border-radius: 5px; font-size: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 10.5px; font-weight: 600; margin-bottom: 3px;">Jam Ambil</label>
                        <input type="time" class="input-jam-kasir" value="<?= htmlspecialchars($barang['jam_ambil'] ?? '09:00') ?>"
                            style="width: 100%; padding: 6px 7px; border: 1px solid var(--color-border); border-radius: 5px; font-size: 12px;">
                    </div>
                </div>
                <p class="pesan-error-tanggal-kasir" style="display: none; font-size: 11px; color: var(--color-danger); margin-bottom: 8px;"></p>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn-simpan-tanggal-kasir" data-index="<?= $index ?>" style="padding: 5px 12px; font-size: 11.5px; font-weight: 600; border: none; border-radius: 5px; background: var(--color-accent); color: #fff; cursor: pointer;">Simpan</button>
                    <button type="button" class="btn-batal-tanggal-kasir" data-index="<?= $index ?>" style="padding: 5px 12px; font-size: 11.5px; font-weight: 600; border: 1px solid var(--color-border); border-radius: 5px; background: none; cursor: pointer;">Batal</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
