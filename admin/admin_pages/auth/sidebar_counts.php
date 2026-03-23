<?php

/**
 * sidebar_counts.php
 * Lightweight JSON endpoint polled every 30 s by the sidebar.
 * Place at:  admin/admin_pages/auth/sidebar_counts.php
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

require_once '../../config/connection.php';

$out = [
    /* Approvals */
    'notes_pending'  => 0,
    'books_pending'  => 0,
    'news_pending'   => 0,

    /* Users — total counts */
    'students'       => 0,
    'tutors'         => 0,

    /* Actions */
    'admins_pending' => 0,   // admin_user pending registration
    'admins'         => 0,   // total active admins
    'rf'             => 0,   // reports pending + feedback new
    'requests'       => 0,   // material requests Pending + In Progress
];

try {
    $q = $out['notes_pending'] = (int)$conn->query("SELECT COUNT(*) FROM notes  WHERE approval_status='pending'")->fetchColumn();
    $out['notes_pending'] = $q;
} catch (Exception $e) {
}

try {
    $out['books_pending'] = (int)$conn->query("SELECT COUNT(*) FROM books  WHERE approval_status='pending'")->fetchColumn();
} catch (Exception $e) {
}

try {
    $out['news_pending']  = (int)$conn->query("SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'")->fetchColumn();
} catch (Exception $e) {
}

try {
    $out['students'] = (int)$conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
} catch (Exception $e) {
}

try {
    $out['tutors'] = (int)$conn->query("SELECT COUNT(*) FROM tutors")->fetchColumn();
} catch (Exception $e) {
}

try {
    $out['admins_pending'] = (int)$conn->query("SELECT COUNT(*) FROM admin_user WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {
}

try {
    $out['admins'] = (int)$conn->query("SELECT COUNT(*) FROM admin_user WHERE status='active'")->fetchColumn();
} catch (Exception $e) {
}

try {
    $out['rf'] = (int)$conn->query("
        SELECT (SELECT COUNT(*) FROM reports  WHERE status='pending') +
               (SELECT COUNT(*) FROM feedback WHERE status='new') AS c
    ")->fetchColumn();
} catch (Exception $e) {
}

try {
    $out['requests'] = (int)$conn->query("
        SELECT COUNT(*) FROM material_requests WHERE status IN ('Pending','In Progress')
    ")->fetchColumn();
} catch (Exception $e) {
}

echo json_encode($out);
