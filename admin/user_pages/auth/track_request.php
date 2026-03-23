<?php
session_start();
require_once "../../config/connection.php";
include_once('../../encryption.php');

header('Content-Type: application/json');

$trackingNumber = trim($_POST['tracking_number'] ?? '');

if (empty($trackingNumber)) {
    echo json_encode(['success' => false, 'message' => 'Tracking number is required.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            mr.request_id,
            mr.ref_code,
            mr.tracking_number,
            mr.material_type,
            mr.title,
            mr.priority,
            mr.resource_link,
            mr.status,
            mr.admin_note,
            mr.fulfilled_at,
            mr.created_at,
            mr.user_id
        FROM material_requests mr
        WHERE mr.tracking_number = ?
        LIMIT 1
    ");
    $stmt->execute([$trackingNumber]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'No request found with that tracking number.']);
        exit;
    }

    /* ── Build viewer URL ──
       resource_link is stored directly on material_requests row.
       Strip the domain prefix so it works on any domain:
       http://localhost/ScholarSwap/notes_reader?... → notes_reader?...
    ── */
    $viewerUrl = null;
    if ($req['status'] === 'Fulfilled' && !empty($req['resource_link'])) {
        $viewerUrl = preg_replace('#^https?://[^/]+/[^/]+/#i', '', trim($req['resource_link']));
    }

    echo json_encode([
        'success' => true,
        'request' => [
            'ref_code'        => $req['ref_code'],
            'tracking_number' => $req['tracking_number'],
            'material_type'   => $req['material_type'],
            'title'           => $req['title'],
            'priority'        => $req['priority'],
            'status'          => $req['status'],
            'admin_note'      => $req['admin_note'] ?? '',
            'submitted'       => date('d M Y', strtotime($req['created_at'])),
            'fulfilled_at'    => $req['fulfilled_at']
                ? date('d M Y', strtotime($req['fulfilled_at']))
                : null,
            'viewer_url'      => $viewerUrl,
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
