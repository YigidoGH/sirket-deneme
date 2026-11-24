<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $companyStatement = $pdo->prepare('INSERT INTO companies (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $userStatement = $pdo->prepare(
        'INSERT INTO users (company_id, display_name, email, password_hash, role)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $passwordHash = password_hash('demo123', PASSWORD_DEFAULT);

    foreach ([
        ['Şirket 1', [
            ['İsim 1', 'admin@deliverdesk.test', 'admin', null],
            ['İsim 2', 'pm1@deliverdesk.test', 'manager', null],
            ['İsim 3', 'uzman1@deliverdesk.test', 'developer', null],
            ['İsim 4', 'musteri1@deliverdesk.test', 'client', 'Şirket 1'],
        ]],
        ['Şirket 2', [
            ['İsim 5', 'uzman2@deliverdesk.test', 'developer', null],
            ['İsim 6', 'musteri2@deliverdesk.test', 'client', 'Şirket 2'],
        ]],
    ] as [$companyName, $members]) {
        $companyStatement->execute([$companyName]);
        $companyId = (int)$pdo->lastInsertId();

        foreach ($members as [$name, $email, $role, $memberCompany]) {
            $memberCompanyId = null;
            if ($memberCompany !== null) {
                $companyLookup = $pdo->prepare('SELECT id FROM companies WHERE name = ?');
                $companyLookup->execute([$memberCompany]);
                $memberCompanyId = (int)$companyLookup->fetchColumn();
            }
            $userStatement->execute([$memberCompanyId, $name, $email, $passwordHash, $role]);
        }
    }

    $findCompany = $pdo->prepare('SELECT id FROM companies WHERE name = ?');
    $findUser = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $findProject = $pdo->prepare('SELECT id FROM projects WHERE company_id = ? AND name = ?');
    $projectInsert = $pdo->prepare(
        'INSERT INTO projects (company_id, manager_id, name, description, status, client_visible)
         VALUES (?, ?, ?, ?, "active", 1)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $memberInsert = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)');

    $projectRows = [
        ['Şirket 1', 'Proje 1', 'Şirket 1 için örnek teslim ve revizyon projesi.', 'pm1@deliverdesk.test', ['uzman1@deliverdesk.test']],
        ['Şirket 2', 'Proje 2', 'Şirket 2 için örnek web projesi.', 'pm1@deliverdesk.test', ['uzman2@deliverdesk.test']],
        ['Şirket 1', 'Proje 3', 'Şirket 1 için ikinci örnek proje.', 'pm1@deliverdesk.test', ['uzman1@deliverdesk.test']],
    ];
    $projectIds = [];

    foreach ($projectRows as [$companyName, $projectName, $description, $managerEmail, $memberEmails]) {
        $findCompany->execute([$companyName]);
        $companyId = (int)$findCompany->fetchColumn();
        $findUser->execute([$managerEmail]);
        $managerId = (int)$findUser->fetchColumn();
        $projectInsert->execute([$companyId, $managerId, $projectName, $description]);
        $findProject->execute([$companyId, $projectName]);
        $projectId = (int)$findProject->fetchColumn();
        $projectIds[$projectName] = $projectId;
        $memberInsert->execute([$projectId, $managerId]);

        foreach ($memberEmails as $memberEmail) {
            $findUser->execute([$memberEmail]);
            $memberInsert->execute([$projectId, (int)$findUser->fetchColumn()]);
        }
    }

    $taskInsert = $pdo->prepare(
        'INSERT INTO tasks (project_id, assignee_id, created_by, title, description, status, priority, due_date, client_visible, internal_note)
         SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
         WHERE NOT EXISTS (SELECT 1 FROM tasks WHERE project_id = ? AND title = ?)'
    );
    $taskRows = [
        ['Proje 1', 'uzman1@deliverdesk.test', 'İş kalemi 1', 'İlk örnek teslim kalemi.', 'in_progress', 'high', '2026-12-03', 1, 'Ekip içi geliştirme notu 1.'],
        ['Proje 1', 'pm1@deliverdesk.test', 'İş kalemi 2', 'İkinci örnek teslim kalemi.', 'review', 'medium', '2026-12-08', 0, 'Ekip içi geliştirme notu 2.'],
        ['Proje 1', 'uzman1@deliverdesk.test', 'İş kalemi 3', 'Üçüncü örnek teslim kalemi.', 'waiting', 'medium', '2026-12-12', 1, ''],
        ['Proje 2', 'uzman2@deliverdesk.test', 'İş kalemi 4', 'Dördüncü örnek teslim kalemi.', 'completed', 'low', '2026-12-01', 1, ''],
        ['Proje 2', 'pm1@deliverdesk.test', 'İş kalemi 5', 'Beşinci örnek teslim kalemi.', 'in_progress', 'high', '2026-12-06', 0, 'Ekip içi geliştirme notu 5.'],
        ['Proje 3', 'uzman1@deliverdesk.test', 'İş kalemi 6', 'Altıncı örnek teslim kalemi.', 'in_progress', 'high', '2026-12-10', 1, ''],
    ];

    foreach ($taskRows as [$projectName, $assigneeEmail, $title, $description, $status, $priority, $due, $visible, $note]) {
        $findUser->execute([$assigneeEmail]);
        $assigneeId = (int)$findUser->fetchColumn();
        $creatorId = $managerId;
        $projectId = (int)$projectIds[$projectName];
        $taskInsert->execute([
            $projectId, $assigneeId, $creatorId, $title, $description, $status, $priority, $due, $visible, $note,
            $projectId, $title,
        ]);
    }

    $pdo->commit();
    echo "Demo verileri hazır. Tüm hesapların şifresi: demo123\n";
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}
