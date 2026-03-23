<?php
session_start();
include_once('../../config/connection.php');
include_once('../../encryption.php');

/* ── Ensure DB returns UTF-8 so Hindi titles aren't garbled ── */
if (isset($conn)) {
    $conn->exec("SET NAMES 'utf8mb4'");
}

$type   = decryptId($_GET['t'] ?? '') ?? '';
$id     = decryptId($_GET['r'] ?? '') ?? '';
$userID = (int)$_SESSION['user_id'];   // logged-in user — NOT from URL (security)

$eId     = $_GET['r'] ?? '';
$euserId = $_GET['u'] ?? '';
$etype   = $_GET['t'] ?? '';

$file     = '';
$filename = '';
$docType  = '';   // normalised type for downloads table

if ($type === 'note') {
    $stmt = $conn->prepare("SELECT file_path, title FROM notes WHERE n_code = :id");
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data) {
        $filename = $data['title'];
        $file     = '../../../' . $data['file_path'];
        $docType  = 'note';
        $conn->prepare("UPDATE notes SET download_count = download_count + 1 WHERE n_code = :id")
            ->execute([':id' => $id]);
    }
} elseif ($type === 'book') {
    $stmt = $conn->prepare("SELECT file_path, title FROM books WHERE b_code = :id");
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data) {
        $filename = $data['title'];
        $file     = '../../../' . $data['file_path'];
        $docType  = 'book';
        $conn->prepare("UPDATE books SET download_count = download_count + 1 WHERE b_code = :id")
            ->execute([':id' => $id]);
    }
} elseif ($type === 'newspaper') {
    $stmt = $conn->prepare("SELECT file_path, title FROM newspapers WHERE n_code = :id");
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data) {
        $filename = $data['title'];
        $file     = '../../../' . $data['file_path'];
        $docType  = 'newspaper';
        $conn->prepare("UPDATE newspapers SET download_count = download_count + 1 WHERE n_code = :id")
            ->execute([':id' => $id]);
    }
}

/* ── Record download in downloads table ── */
if ($file && file_exists($file) && $userID && $docType) {
    try {
        $conn->prepare("
            INSERT INTO downloads (user_id, document_type, document_id, downloaded_at, created_at)
            VALUES (:uid, :dt, :did, NOW(), NOW())
        ")->execute([
            ':uid' => $userID,
            ':dt'  => $docType,
            ':did' => $id,
        ]);
    } catch (PDOException $e) {
        error_log('[ScholarSwap] download insert error: ' . $e->getMessage());
    }
}

/* ── Serve the file ── */
if ($file && file_exists($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);

    /* Build a safe download filename that supports Unicode (Hindi, etc.)
       RFC 5987 / RFC 6266: use filename* parameter with UTF-8 percent-encoding.
       Modern browsers (Chrome, Firefox, Edge, Safari) all support this.
       The plain filename= fallback uses ASCII only for very old clients. */

    // Ensure the title is valid UTF-8.
    // If the DB returned a non-UTF-8 string (latin1 mis-read),
    // detect and convert so Hindi/Devanagari characters are preserved.
    if (!mb_check_encoding($filename, 'UTF-8')) {
        $titleUtf8 = mb_convert_encoding($filename, 'UTF-8', 'ISO-8859-1');
    } else {
        $titleUtf8 = $filename;
    }
    // Strip any remaining invalid bytes
    $titleUtf8 = mb_convert_encoding($titleUtf8, 'UTF-8', 'UTF-8');

    // ASCII fallback: keep only printable ASCII chars for old browsers
    $asciiFallback = preg_replace('/[^\x20-\x7E]/', '', $titleUtf8);
    $asciiFallback = trim(preg_replace('/\s+/', ' ', $asciiFallback));
    if ($asciiFallback === '') {
        $asciiFallback = 'document';
    }
    $asciiFallback .= '.' . $ext;

    // RFC 5987: percent-encode the full UTF-8 filename for modern browsers
    $rfc5987Name = rawurlencode($titleUtf8 . '.' . $ext);

    ob_end_clean();

    header('Content-Type: application/octet-stream');
    // Both parameters: filename= for old clients, filename*= for modern ones
    header('Content-Disposition: attachment; filename="' . $asciiFallback . '"; filename*=UTF-8\'\'' . $rfc5987Name);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: no-cache');

    readfile($file);
    exit;
}

/* ── File not found ── */
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

if ($isAjax) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'File not found on server.']);
} else {
    header('Location: http://localhost/ScholarSwap/notes_reader.php?r=' . urlencode($eId) . '&u=' . urlencode($euserId) . '&t=' . urlencode($etype));
}
exit;
