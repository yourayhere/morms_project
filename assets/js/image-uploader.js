/**
 * Komponen upload gambar universal — dipakai di semua halaman yang punya
 * upload/preview/galeri gambar (form-barang, pengaturan, checkout, dst),
 * supaya perilaku dan tampilannya konsisten di seluruh project.
 *
 * Tugasnya cuma satu: begitu user memilih file baru lewat <input type="file">,
 * tampilkan thumbnail-nya secara langsung (tanpa reload) di dalam grid, dan
 * kasih tombol hapus bulat mengambang di pojok kanan atas tiap thumbnail yang
 * membatalkan pemilihan file itu (belum tersimpan, jadi hapus di sini = hapus
 * dari FileList sebelum submit, bukan panggil server).
 *
 * Thumbnail gambar yang SUDAH tersimpan di server tetap dirender oleh PHP
 * (server-rendered) dan tetap pakai mekanisme hapus yang sudah ada
 * masing-masing halaman (form POST ke aksi/hapus-*.php) — komponen ini hanya
 * menyediakan class CSS yang sama (.img-upload-thumb, .img-upload-delete-btn)
 * supaya keduanya terlihat identik berdampingan.
 */
(function (window, document) {
    'use strict';

    function buatElemen(tag, className, html) {
        var el = document.createElement(tag);
        if (className) el.className = className;
        if (html) el.innerHTML = html;
        return el;
    }

    var IKON_HAPUS = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="/morms/assets/icons/sprite.svg#icon-trash"></use></svg>';

    /**
     * @param {HTMLInputElement} input - elemen <input type="file">
     * @param {HTMLElement} grid - kontainer tempat thumbnail baru ditambahkan
     * @param {Object} [opsi]
     * @param {boolean} [opsi.multiple] - ikuti atribut multiple pada input kalau tidak diisi
     * @param {function(number)} [opsi.onChange] - dipanggil tiap kali jumlah file terpilih berubah, argumen = jumlah file
     */
    function pasangPreview(input, grid, opsi) {
        opsi = opsi || {};
        var multiple = opsi.multiple !== undefined ? opsi.multiple : input.multiple;
        var thumbUrls = [];

        function bersihkanThumbLama() {
            thumbUrls.forEach(function (url) { URL.revokeObjectURL(url); });
            thumbUrls = [];
            grid.querySelectorAll('.img-upload-thumb[data-pending="1"]').forEach(function (el) { el.remove(); });
        }

        function hapusFileDariInput(index) {
            var dt = new DataTransfer();
            var files = Array.prototype.slice.call(input.files);
            files.forEach(function (file, i) {
                if (i !== index) dt.items.add(file);
            });
            input.files = dt.files;
            renderSemua();
        }

        function renderSemua() {
            bersihkanThumbLama();
            var files = Array.prototype.slice.call(input.files);

            files.forEach(function (file, index) {
                if (!file.type || file.type.indexOf('image/') !== 0) return;

                var url = URL.createObjectURL(file);
                thumbUrls.push(url);

                var thumb = buatElemen('div', 'img-upload-thumb');
                thumb.setAttribute('data-pending', '1');
                thumb.innerHTML =
                    '<img src="' + url + '" alt="Pratinjau gambar">' +
                    '<button type="button" class="img-upload-delete-btn" aria-label="Batalkan gambar ini" title="Batalkan gambar ini">' + IKON_HAPUS + '</button>' +
                    '<span class="img-upload-status img-upload-status-pending">Menunggu diunggah</span>';

                thumb.querySelector('.img-upload-delete-btn').addEventListener('click', function () {
                    hapusFileDariInput(index);
                });

                grid.appendChild(thumb);
            });

            if (typeof opsi.onChange === 'function') {
                opsi.onChange(files.length);
            }
        }

        input.addEventListener('change', renderSemua);

        return { render: renderSemua };
    }

    window.MormsImageUploader = { pasangPreview: pasangPreview };
})(window, document);
