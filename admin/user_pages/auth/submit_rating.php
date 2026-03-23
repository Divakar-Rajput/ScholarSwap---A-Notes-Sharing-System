<?php
ob_start();
session_start();
require_once "../../../admin/config/connection.php";
require_once "../../../admin/encryption.php"; // FIX 1: need decryptId()
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to rate.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

/* FIX 2: decrypt both values — JS sends encrypted $enoteId and $etype */
$resourceId   = trim(decryptId($body['resource_id']   ?? ''));
$documentType = trim(decryptId($body['document_type'] ?? ''));
$rating       = (int)($body['rating'] ?? 0);
$userId       = (int)$_SESSION['user_id'];

if (empty($resourceId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid resource.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit;
}

/* FIX 3: allowed types whitelist */
$allowedTypes = ['note', 'book', 'newspaper'];
if (!in_array($documentType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid document type.']);
    exit;
}

try {

    /* ── Check if this user already rated this resource ── */
    $check = $conn->prepare(
        "SELECT rating_id FROM ratings
         WHERE resource_id = :rid AND user_id = :uid AND document_type = :dt
         LIMIT 1"
    );
    $check->execute([':rid' => $resourceId, ':uid' => $userId, ':dt' => $documentType]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        /* UPDATE existing row */
        $stmt = $conn->prepare(
            "UPDATE ratings SET rating = :rating
             WHERE rating_id = :id"
        );
        $stmt->execute([':rating' => $rating, ':id' => $existing['rating_id']]);
        $msg = 'Rating updated!';
    } else {
        /* INSERT new row */
        $stmt = $conn->prepare(
            "INSERT INTO ratings (resource_id, document_type, user_id, rating, created_at)
             VALUES (:rid, :dt, :uid, :rating, NOW())"
        );
        $stmt->execute([
            ':rid'    => $resourceId,
            ':dt'     => $documentType,
            ':uid'    => $userId,
            ':rating' => $rating,
        ]);
        $msg = 'Rating saved!';
    }

    /* ── Fetch updated avg + count ── */
    $stats = $conn->prepare(
        "SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total
         FROM ratings
         WHERE resource_id = :rid AND document_type = :dt"
    );
    $stats->execute([':rid' => $resourceId, ':dt' => $documentType]);
    $row   = $stats->fetch(PDO::FETCH_ASSOC);
    $avg   = (float)($row['avg_rating'] ?? 0);
    $total = (int)($row['total']        ?? 0);

    /* FIX 4: correct table names and primary key columns to match your schema
       notes      → n_code  (not note_id)
       books      → b_code  (not book_id)
       newspapers → n_code  (not newspaper_id)
    */
    $tableMap = [
        'note'      => ['notes',      'n_code'],
        'book'      => ['books',      'b_code'],
        'newspaper' => ['newspapers', 'n_code'],
    ];
    if (isset($tableMap[$documentType])) {
        [$tbl, $pk] = $tableMap[$documentType];
        $upd = $conn->prepare("UPDATE `{$tbl}` SET rating = :r WHERE `{$pk}` = :id");
        $upd->execute([':r' => $avg, ':id' => $resourceId]);
    }

    echo json_encode([
        'success'       => true,
        'avg_rating'    => $avg,
        'total_ratings' => $total,
        'user_rating'   => $rating,
        'message'       => $msg,
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}