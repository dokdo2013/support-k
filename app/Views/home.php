<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<section class="hero">
    <p class="eyebrow">CUSTOMER SUPPORT</p>
    <h1>무엇을 도와드릴까요?</h1>
    <p class="lead">궁금한 점을 남겨 주세요.<br>문의 번호와 비밀번호로 답변을 확인할 수 있습니다.</p>
</section>
<div class="card-grid">
    <a class="action-card" href="<?= esc(site_url('tickets/new')) ?>"><span class="card-icon" aria-hidden="true">＋</span><h2>새 문의 남기기</h2><p>회원가입 없이 문의를 접수하세요.</p><span class="card-link">문의 작성하기 →</span></a>
    <a class="action-card" href="<?= esc(site_url('tickets/lookup')) ?>"><span class="card-icon" aria-hidden="true">↗</span><h2>답변 확인하기</h2><p>진행 상황을 확인하고 대화를 이어가세요.</p><span class="card-link">내 문의 조회 →</span></a>
</div>
<?= $this->endSection() ?>
