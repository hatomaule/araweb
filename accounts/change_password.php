<section class="page-heading">
    <div>
        <span class="eyebrow">KEAMANAN AKUN</span>
        <h1>Ganti password</h1>
        <p>Gunakan password yang mudah Anda ingat dan tidak digunakan di tempat lain.</p>
    </div>
</section>
<section class="panel form-panel">
    <form method="post" action="index.php?page=change_password" class="stack-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <label class="field"><span>Password saat ini</span><input type="password" name="current_password" autocomplete="current-password" required></label>
        <label class="field"><span>Password baru</span><input type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
        <label class="field"><span>Ulangi password baru</span><input type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
        <button class="button primary">Simpan password</button>
    </form>
</section>
