<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<section class="narrow panel">
<p class="eyebrow">READY TO HELP</p><h1>설치를 마쳤습니다</h1>
<p>관리자 계정으로 로그인하고 첫 문의를 확인해 보세요.</p>
<?php if ($recoveryCode): ?>
<div class="recovery"><h2>복구 코드를 보관해 주세요</h2><p>관리자 비밀번호를 잊었을 때 사용할 수 있습니다. 이 코드는 지금 한 번만 표시하며, 한 번 사용하면 만료됩니다.</p><code id="recovery-code"><?= esc($recoveryCode) ?></code></div>
<?php else: ?><p>중단된 설치를 복구했습니다. 이전에 만든 관리자 계정으로 로그인하세요.</p><?php endif ?>
<p><a class="button" href="<?= esc(site_url('admin/login')) ?>">운영자 로그인</a> <a href="<?= esc(site_url()) ?>">고객센터 열기</a></p>
</section>
<?= $this->endSection() ?>
