<?php

/**
 * submit_material_request.php
 * ─────────────────────────────────────────────────────────────
 * POST endpoint — validates, saves, returns JSON with:
 *   ref_code        (short display code    e.g. SS-A3F9C1)
 *   tracking_number (full trackable code   e.g. TRK-20241218-A3F9C1)
 *
 * Place at:  admin/user_pages/auth/submit_material_request.php
 * ─────────────────────────────────────────────────────────────
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a request.']);
    exit;
}

$base = dirname(__DIR__, 3);
include_once $base . '/admin/config/connection.php';

$uid = (int)$_SESSION['user_id'];

/* ── Auto-fetch user profile from session ── */
$uq = $conn->prepare("
    SELECT u.email,
           COALESCE(NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
                    NULLIF(TRIM(CONCAT(t.first_name,' ',t.last_name)),''),
                    u.username) AS full_name,
           COALESCE(s.student_id,'') AS roll
    FROM users u
    LEFT JOIN students s ON s.user_id = u.user_id
    LEFT JOIN tutors   t ON t.user_id = u.user_id
    WHERE u.user_id = ? LIMIT 1
");
$uq->execute([$uid]);
$uRow = $uq->fetch(PDO::FETCH_ASSOC);

if (!$uRow) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User account not found.']);
    exit;
}

/* ── Sanitise ── */
function clean(string $key, int $max = 300): string
{
    return mb_substr(trim(strip_tags($_POST[$key] ?? '')), 0, $max);
}
function opt(string $key, int $max = 300): ?string
{
    $v = mb_substr(trim(strip_tags($_POST[$key] ?? '')), 0, $max);
    return $v !== '' ? $v : null;
}

/* ── Validate required fields ── */
$errors        = [];
$material_type = clean('mat_type', 40);
$title         = clean('title', 300);
$priority      = clean('priority', 10);

$allowed_types = ['Textbook', 'Lecture Notes', 'Past Papers', 'Reference Book', 'Other'];
if (!in_array($material_type, $allowed_types, true))
    $errors[] = 'Invalid material type.';
if (strlen($title) < 2)
    $errors[] = 'Please enter the material title.';
if ($material_type === 'Past Papers' && strlen(clean('university', 160)) < 2)
    $errors[] = 'University / Board is required for past papers.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$allowed_priority = ['Low', 'Medium', 'High'];
if (!in_array($priority, $allowed_priority, true)) $priority = 'Low';

/* ── Optional fields ── */
$author       = opt('author',    200);
$subject_code = opt('subject',   100);
$edition      = opt('edition',    80);
$exam_year    = opt('exam_year',  80);
$lecturer     = opt('lecturer',  160);
$unit_needed  = opt('unit',      160);
$note_type    = opt('note_type',  80);
$university   = opt('university', 160);
$description  = opt('desc',     2000);
$extra_notes  = opt('extra',    1000);

/* ── Generate unique ref_code + tracking_number ── */
do {
    $rand            = strtoupper(bin2hex(random_bytes(3)));   // 6 hex chars
    $ref_code        = 'SS-' . $rand;                          // SS-A3F9C1
    $tracking_number = 'TRK-' . date('Ymd') . '-' . $rand;   // TRK-20241218-A3F9C1
    $chk = $conn->prepare('SELECT request_id FROM material_requests WHERE ref_code=? OR tracking_number=? LIMIT 1');
    $chk->execute([$ref_code, $tracking_number]);
} while ($chk->fetchColumn());

/* ── Insert ── */
try {
    $stmt = $conn->prepare("
        INSERT INTO material_requests
            (user_id, full_name, email, student_roll, department,
             material_type, title, author, subject_code, edition,
             exam_year, lecturer, unit_needed, note_type, university,
             description, priority, extra_notes,
             status, ref_code, tracking_number)
        VALUES
            (:uid, :full_name, :email, :roll, :dept,
             :mat, :title, :author, :subject, :edition,
             :exam_year, :lecturer, :unit, :note_type, :university,
             :desc, :priority, :extra,
             'Pending', :ref_code, :tracking)
    ");
    $stmt->execute([
        ':uid'          => $uid,
        ':full_name'    => $uRow['full_name'],
        ':email'        => $uRow['email'],
        ':roll'         => $uRow['roll'],
        ':dept'         => 'Not specified',
        ':mat'          => $material_type,
        ':title'        => $title,
        ':author'       => $author,
        ':subject'      => $subject_code,
        ':edition'      => $edition,
        ':exam_year'    => $exam_year,
        ':lecturer'     => $lecturer,
        ':unit'         => $unit_needed,
        ':note_type'    => $note_type,
        ':university'   => $university,
        ':desc'         => $description,
        ':priority'     => $priority,
        ':extra'        => $extra_notes,
        ':ref_code'     => $ref_code,
        ':tracking'     => $tracking_number,
    ]);

    echo json_encode([
        'success'          => true,
        'ref_code'         => $ref_code,
        'tracking_number'  => $tracking_number,
        'message'          => 'Request submitted successfully.',
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
