</main>
    </div>
</div>

<script>
(function () {
    const btnSidebar = document.getElementById('btn-sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const backdrop = document.getElementById('admin-sidebar-backdrop');
    if (!btnSidebar || !sidebar || !backdrop) return;

    function tutupSidebar() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('open');
    }

    btnSidebar.addEventListener('click', function (e) {
        e.stopPropagation();
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('open');
    });

    backdrop.addEventListener('click', tutupSidebar);
})();
</script>

<script>
(function () {
    const btn = document.getElementById('btn-notifikasi');
    const panel = document.getElementById('panel-notifikasi');
    if (!btn || !panel) return;
    let dimuat = false;

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        panel.classList.toggle('open');

        if (panel.classList.contains('open') && !dimuat) {
            fetch('aksi/ambil-notifikasi.php')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const c = document.getElementById('daftar-notifikasi');
                    if (!data.length) {
                        c.innerHTML = '<div class="notif-item text-muted">Belum ada notifikasi.</div>';
                        return;
                    }
                    c.innerHTML = data.map(function (n) {
                        return '<a href="' + (n.link_tujuan || '#') + '" class="notif-item' + (n.dibaca == 0 ? ' unread' : '') + '">'
                            + n.pesan
                            + '<div class="notif-time">' + n.waktu_relatif + '</div>'
                            + '</a>';
                    }).join('');
                    dimuat = true;
                });
        }
    });

    document.addEventListener('click', function () {
        panel.classList.remove('open');
    });
})();
</script>

</body>
</html>