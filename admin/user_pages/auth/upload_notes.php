<?php
session_start();
require_once "../../config/connection.php";
function generateBookCode(): string
{
    return 'NT' . date('YmdHis') . random_int(100, 999);
}
$contentID = generateBookCode();
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/ScholarSwap/login.html/login.html");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT role FROM users WHERE user_id = :id');
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$role = $user['role'];
/* =======================
   1. Collect & validate
======================= */
$title       = trim($_POST['title']);
$subject     = trim($_POST['subject']);
$level     = trim($_POST['document_type']);
$course      = trim($_POST['course']);
$description = trim($_POST['description']);

if ($title === "" || $subject === "" || $description === "") {
    header("Location: http://localhost/ScholarSwap/admin/user_pages/notes_upload.php?s=failed");
    exit;
}

/* =======================
   2. File validation
======================= */
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== 0) {
    header("Location: http://localhost/ScholarSwap/admin/user_pages/notes_upload.php?s=failed");
    exit;
}

$file = $_FILES['pdf'];

if ($file['type'] !== "application/pdf") {
    header("Location: http://localhost/ScholarSwap/admin/user_pages/notes_upload.php?s=failed");
    exit;
}

/* =======================
   3. Create directory
======================= */
$uploadDir = __DIR__ . "/../uploads/notes/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* =======================
   4. File info
======================= */
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$newName = uniqid("note_") . "." . $ext;
$targetPath = $uploadDir . $newName;

/* =======================
   5. Move file
======================= */
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    header("Location: http://localhost/ScholarSwap/admin/user_pages/notes_upload.php?s=failed");
    exit;
}

/* =======================
   6. Save to DB
======================= */
$stmt = $conn->prepare("
    INSERT INTO notes (
        n_code,
        user_id,
        title,
        description,
        subject,
        course,
        notes_level,
        uploaded_by,
        file_path,
        file_size,
        file_type,
        approval_status,
        approved_by,
        approved_at,
        is_featured,
        download_count,
        view_count,
        created_at,
        updated_at
    ) VALUES (
        :n_code,
        :user_id,
        :title,
        :description,
        :subject,
        :course,
        :level,
        :upload_by,
        :file_path,
        :file_size,
        :file_type,
        :approval_status,
        NULL,
        NULL,
        0,
        0,
        0,
        NOW(),
        NOW()
    )
");

$stmt->execute([
    ':n_code'         => $contentID,
    ':user_id'         => $user_id,
    ':title'           => $title,
    ':description'     => $description,
    ':subject'         => $subject,
    ':course'          => $course,
    ':level'          => $level,
    ':upload_by'      => $role,
    ':file_path'       => "admin/user_pages/uploads/notes/" . $newName,
    ':file_size'       => $file['size'],               // can calculate later
    ':file_type'       => $file['type'],
    ':approval_status' => 'pending'
]);

/* =======================
   7. Redirect success
======================= */
header("Location: ../../../../../ScholarSwap/notes?s=success");
exit;