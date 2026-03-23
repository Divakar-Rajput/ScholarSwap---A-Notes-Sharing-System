<?php

function generateNoteID($prefix)
{
    $datetime = date("YmdHis");
    $rand = random_int(1000, 9999); // stronger than rand()
    return $prefix . $datetime . $rand;
}
$contentID = generateNoteID('NP');

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "../config/connection.php";
require_once('../auth/notifications.php');

/* ── Auth ── */
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin_login.php");
    exit;
}

$adminId = (int)$_SESSION['admin_id'];

/* ── Redirect helpers ── */
function fail(string $msg): never
{
    header("Location: ../newspapers.php?s=error&msg=" . urlencode($msg));
    exit;
}
function ok(): never
{
    header("Location: ../newspapers.php?s=success");
    exit;
}

/* ═══════════════════════════════════════════
   1. TEXT FIELD VALIDATION
   ═══════════════════════════════════════════ */
$title            = trim($_POST['title']            ?? '');
$publisher        = trim($_POST['publisher']        ?? '');
$language         = trim($_POST['language']         ?? '');
$region           = trim($_POST['region']           ?? '');
$publication_date = trim($_POST['publication_date'] ?? '');
$approval_status  = in_array($_POST['approval_status'] ?? '', ['approved', 'pending'], true)
    ? $_POST['approval_status']
    : 'pending';
$is_featured      = (($_POST['is_featured'] ?? '0') === '1') ? 1 : 0;

if ($title === '')            fail('Newspaper title is required.');
if ($publisher === '')        fail('Publisher name is required.');
if ($language === '')         fail('Language is required.');
if ($region === '')           fail('Region is required.');
if ($publication_date === '') fail('Publication date is required.');

/* Date format + not-in-future (today is allowed) */
$dateObj = DateTime::createFromFormat('Y-m-d', $publication_date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $publication_date) {
    fail('Invalid publication date. Use YYYY-MM-DD format.');
}
if ($publication_date > date('Y-m-d')) {
    fail('Publication date cannot be in the future.');
}

/* Length limits */
if (mb_strlen($title)     > 200) fail('Title must be under 200 characters.');
if (mb_strlen($publisher) > 120) fail('Publisher must be under 120 characters.');
if (mb_strlen($language)  > 60)  fail('Language must be under 60 characters.');
if (mb_strlen($region)    > 100) fail('Region must be under 100 characters.');

/* ═══════════════════════════════════════════
   2. FILE VALIDATION
   ═══════════════════════════════════════════ */
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds the server\'s upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form\'s MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded. Please select a PDF file.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server error: failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Server error: upload blocked by a PHP extension.',
    ];
    $code = $_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE;
    fail($errMap[$code] ?? 'File upload error (code ' . (int)$code . ').');
}

$file     = $_FILES['pdf'];
$origName = basename($file['name']);
$tmpPath  = $file['tmp_name'];
$fileSize = (int)$file['size'];

/* MIME type check via finfo */
if (function_exists('finfo_open')) {
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);
    $allowed  = ['application/pdf', 'application/x-pdf', 'application/acrobat', 'application/vnd.pdf'];
    if (!in_array($mimeType, $allowed, true)) {
        fail('Only PDF files are allowed. Detected MIME type: ' . htmlspecialchars($mimeType));
    }
}

/* Extension check */
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    fail('Only .pdf files are accepted. Uploaded file has extension: .' . htmlspecialchars($ext));
}

/* ═══════════════════════════════════════════
   3. PREPARE UPLOAD DIRECTORY & FILENAME
   ═══════════════════════════════════════════ */
$uploadDir = rtrim(__DIR__ . '/../../uploads/newspapers', '/') . '/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        fail('Could not create the upload directory. Check server folder permissions.');
    }
}
if (!is_writable($uploadDir)) {
    fail('Upload directory is not writable. Check server folder permissions.');
}

$baseName = pathinfo($origName, PATHINFO_FILENAME);
$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
$safeName = trim($safeName, '_');
$safeName = substr($safeName ?: 'newspaper', 0, 50);
$unique   = time() . '_' . bin2hex(random_bytes(5)) . '_' . $safeName . '.pdf';
$destPath = $uploadDir . $unique;
$dbPath   = 'admin/uploads/newspapers/' . $unique;

/* ═══════════════════════════════════════════
   4. MOVE FILE TO FINAL DESTINATION
   ═══════════════════════════════════════════ */
if (!move_uploaded_file($tmpPath, $destPath)) {
    fail('Failed to move the uploaded file. Check server write permissions on uploads/newspapers/.');
}

/* ═══════════════════════════════════════════
   5. INSERT INTO newspapers TABLE
   ═══════════════════════════════════════════ */
try {
    $sql = "
        INSERT INTO newspapers
            (n_code,
             admin_id,
             title,
             publisher,
             language,
             region,
             publication_date,
             file_path,
             file_size,
             file_type,
             document_type,
             approval_status,
             is_featured,
             view_count,
             download_count,
             created_at)
        VALUES
            (:n_code,
             :admin_id,
             :title,
             :publisher,
             :language,
             :region,
             :publication_date,
             :file_path,
             :file_size,
             'application/pdf',
             'newspaper',
             :approval_status,
             :is_featured,
             0,
             0,
             NOW())
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':n_code',           $contentID,        PDO::PARAM_STR);
    $stmt->bindValue(':admin_id',         $adminId,          PDO::PARAM_INT);
    $stmt->bindValue(':title',            $title,            PDO::PARAM_STR);
    $stmt->bindValue(':publisher',        $publisher,        PDO::PARAM_STR);
    $stmt->bindValue(':language',         $language,         PDO::PARAM_STR);
    $stmt->bindValue(':region',           $region,           PDO::PARAM_STR);
    $stmt->bindValue(':publication_date', $publication_date, PDO::PARAM_STR);
    $stmt->bindValue(':file_path',        $dbPath,           PDO::PARAM_STR);
    $stmt->bindValue(':file_size',        $fileSize,         PDO::PARAM_INT);
    $stmt->bindValue(':approval_status',  $approval_status,  PDO::PARAM_STR);
    $stmt->bindValue(':is_featured',      $is_featured,      PDO::PARAM_INT);
    $stmt->execute();

    $newId = (int)$conn->lastInsertId();

    if ($newId === 0) {
        @unlink($destPath);
        fail('The newspaper record could not be created (no insert ID returned). Please try again.');
    }
} catch (PDOException $e) {
    @unlink($destPath);
    error_log('[ScholarSwap] upload_newspaper DB error: ' . $e->getMessage());
    fail('A database error occurred. Please check server error logs or try again. (' . $e->getMessage() . ')');
}
ok();
