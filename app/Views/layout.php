<?php $siteName = \App\Services\Installation\Runtime::read()['site_name'] ?? 'Support K'; ?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? '고객센터') ?> · <?= esc($siteName) ?></title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/' . (defined('SUPPORT_K_RELEASE') ? SUPPORT_K_RELEASE . '/' : '') . 'app.css')) ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= esc(site_url()) ?>"><span class="brand-mark" aria-hidden="true">K</span><?= esc($siteName) ?></a>
    <nav aria-label="주 메뉴">
        <?php if (\App\Services\Installation\Runtime::installed()): ?>
        <a href="<?= esc(site_url('knowledge')) ?>">도움말</a>
        <a href="<?= esc(site_url('tickets/new')) ?>">문의하기</a>
        <a href="<?= esc(site_url('tickets/lookup')) ?>">내 문의</a>
        <?php if (session('staff_id')): ?>
            <a href="<?= esc(site_url('admin/tickets')) ?>">문의함</a>
            <a href="<?= esc(site_url('admin/knowledge')) ?>">지식 관리</a>
            <form class="inline" method="post" action="<?= esc(site_url('admin/logout')) ?>"><?= csrf_field() ?><button class="text-button">로그아웃</button></form>
        <?php else: ?>
            <a href="<?= esc(site_url('admin/login')) ?>">운영자</a>
        <?php endif ?>
        <?php else: ?><span class="badge">처음 시작하기</span><?php endif ?>
    </nav>
</header>
<main id="main" class="container">
    <?php if ($error = session()->getFlashdata('error')): ?><div class="alert error" role="alert"><?= esc($error) ?></div><?php endif ?>
    <?php if ($message = session()->getFlashdata('message')): ?><div class="alert success" role="status"><?= esc($message) ?></div><?php endif ?>
    <?= $this->renderSection('content') ?>
</main>
<footer class="site-footer">Support K <span aria-hidden="true">·</span> 내 공간에서 운영하는 고객센터</footer>
</body>
</html>
