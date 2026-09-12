<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<section class="auth panel"><p class="eyebrow">SUPPORT DESK</p><h1>운영자 로그인</h1>
<form method="post" action="<?= esc(site_url('admin/login')) ?>"><?= csrf_field() ?>
<label>이메일<input name="email" type="email" required maxlength="254" autocomplete="username"></label>
<label>비밀번호<input name="password" type="password" required maxlength="72" autocomplete="current-password"></label>
<button>로그인</button></form>
<p><a href="<?= esc(site_url('admin/recovery')) ?>">복구 코드로 비밀번호 변경</a></p></section>
<?= $this->endSection() ?>
