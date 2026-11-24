<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Bu yöntem desteklenmiyor.');
}

verify_csrf();
$companyValue = (string)($_POST['company_id'] ?? 'all');
$validCompanies = companies_for_user($user);
if ($companyValue === 'all') {
    unset($_SESSION['workspace_company_id']);
} else {
    $companyId = filter_var($companyValue, FILTER_VALIDATE_INT);
    $allowed = false;
    foreach ($validCompanies as $company) {
        if ($companyId && (int)$company['id'] === $companyId) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        http_response_code(403);
        exit('Bu şirkete erişim yetkiniz yok.');
    }
    $_SESSION['workspace_company_id'] = $companyId;
}

$returnTo = (string)($_POST['return_to'] ?? 'dashboard.php');
$allowedPages = ['dashboard.php', 'projects.php', 'tasks.php', 'team.php', 'notifications.php'];
redirect(in_array($returnTo, $allowedPages, true) ? $returnTo : 'dashboard.php');
