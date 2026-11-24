<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
$activePage = 'projects';
$pageTitle = 'Projeler';
$errors = [];
$companies = companies_for_user($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role($user, ['admin', 'manager']);
    $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $internalNote = trim((string)($_POST['internal_note'] ?? ''));
    if (!$companyId || !in_array($companyId, array_map(static fn(array $company): int => (int)$company['id'], $companies), true)) {
        $errors[] = 'Bir şirket seçin.';
    }
    if ($name === '' || mb_strlen($name) > 160) {
        $errors[] = 'Proje adı 1–160 karakter arasında olmalıdır.';
    }
    if ($description === '' || mb_strlen($description) > 4000) {
        $errors[] = 'Proje açıklaması 1–4000 karakter arasında olmalıdır.';
    }
    if (mb_strlen($internalNote) > 2000) {
        $errors[] = 'Ekip içi not en fazla 2000 karakter olabilir.';
    }
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT INTO projects (company_id, manager_id, name, description, internal_note) VALUES (?, ?, ?, ?, ?)'
            );
            $insert->execute([$companyId, $user['id'], $name, $description, $internalNote ?: null]);
            $projectId = (int)$pdo->lastInsertId();
            $member = $pdo->prepare('INSERT INTO project_members (project_id, user_id) VALUES (?, ?)');
            $member->execute([$projectId, $user['id']]);
            $pdo->commit();
            set_flash('Proje oluşturuldu.');
            redirect('project.php?id=' . $projectId);
        } catch (PDOException $exception) {
            $pdo->rollBack();
            if ($exception->getCode() === '23000') {
                $errors[] = 'Bu şirket için aynı isimde bir proje zaten var.';
            } else {
                throw $exception;
            }
        }
    }
}

$projects = visible_projects($user);
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">ÇALIŞMA ALANINIZ</span><h1>Projeler</h1><p>Projeleri, müşteri şirketlerini ve teslim ilerlemesini takip et.</p></div>
    <?php if (in_array($user['role'], ['admin', 'manager'], true)): ?><a class="button button-primary" href="#new-project">+ Proje oluştur</a><?php endif; ?>
</section>
<?php if (in_array($user['role'], ['admin', 'manager'], true)): ?>
<section class="section-block create-panel" id="new-project">
    <div class="section-heading"><div><span class="eyebrow">YENİ ÇALIŞMA</span><h2>Proje oluştur</h2></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label>Şirket<select name="company_id" required><option value="">Şirket seçin</option><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>"><?= e($company['name']) ?></option><?php endforeach; ?></select></label>
        <label>Proje adı<input name="name" maxlength="160" placeholder="Proje 4" required></label>
        <label class="form-full">Açıklama<textarea name="description" rows="3" maxlength="4000" placeholder="Proje kapsamını kısaca yazın." required></textarea></label>
        <label class="form-full">Ekip içi proje notu<textarea name="internal_note" rows="2" maxlength="2000" placeholder="Yalnızca iç ekip görür."></textarea></label>
        <div class="form-full"><button class="button button-primary" type="submit">Projeyi oluştur</button></div>
    </form>
</section>
<?php endif; ?>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">PROJE LİSTESİ</span><h2><?= count($projects) ?> proje</h2></div></div>
    <?php if (!$projects): ?>
        <div class="empty-state"><strong>Proje bulunamadı</strong><p>Aradığınız filtrede gösterilecek bir proje yok.</p></div>
    <?php else: ?>
        <div class="project-grid project-grid-large">
            <?php foreach ($projects as $project): ?>
                <?php $progress = (int)$project['task_count'] > 0 ? (int)round((int)$project['completed_count'] / (int)$project['task_count'] * 100) : 0; ?>
                <article class="project-card">
                    <a class="project-art project-art-violet" href="project.php?id=<?= (int)$project['id'] ?>" aria-label="<?= e($project['name']) ?> ayrıntıları"><span class="art-orbit orbit-a"></span><span class="art-orbit orbit-b"></span><span class="art-symbol">✦</span></a>
                    <div class="project-body">
                        <div class="project-title-line"><h3><a href="project.php?id=<?= (int)$project['id'] ?>"><?= e($project['name']) ?></a></h3><span class="project-status"><?= e(status_label($project['status'])) ?></span></div>
                        <p class="project-company"><?= e($project['company_name']) ?> · <?= (int)$project['task_count'] ?> teslim</p>
                        <p class="project-description"><?= e($project['description']) ?></p>
                        <div class="progress-label"><span>İlerleme</span><strong><?= $progress ?>%</strong></div><div class="progress-track"><span style="width: <?= $progress ?>%"></span></div>
                        <a class="button project-open-button" href="project.php?id=<?= (int)$project['id'] ?>">Projeyi aç <span>→</span></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
