<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<section class="auth panel"><h1>관리자 계정 복구</h1><p>설치할 때 보관한 일회용 복구 코드를 입력해 주세요.</p>
<form method="post" action="<?= esc(site_url('admin/recovery')) ?>"><?= csrf_field() ?>
<label>관리자 이메일<input name="email" type="email" required maxlength="254" autocomplete="username"></label>
<label>복구 코드<input name="code" required maxlength="64" autocomplete="off"></label>
<label>새 비밀번호<input name="password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
<button>비밀번호 변경</button></form></section>
<?= $this->endSection() ?>
