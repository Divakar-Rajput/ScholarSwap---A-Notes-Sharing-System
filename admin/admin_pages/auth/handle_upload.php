<?php
session_start();
require_once "../../config/connection.php";
include_once('../../encryption.php');

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$adminId  = (int)$_SESSION['admin_id'];
$response = ['success' => false, 'message' => ''];

try {
    // Validate required fields
    if (empty($_POST['title']) || empty($_POST['material_type'])) {
        throw new Exception('Title and material type are required');
    }

    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('PDF file is required');
    }

    $materialType    = $_POST['material_type']; // 'note' or 'book'
    $title           = trim($_POST['title']);
    $author          = trim($_POST['author']      ?? '');
    $subject         = trim($_POST['subject']     ?? '');
    $classLevel      = trim($_POST['class_level'] ?? '');
    $course          = trim($_POST['course']      ?? '');
    $description     = trim($_POST['description'] ?? '');
    $docSubtype      = trim($_POST['doc_subtype'] ?? '');
    $requestId       = !empty($_POST['request_id'])        ? (int)$_POST['request_id']        : null;
    $requesterUserId = !empty($_POST['requester_user_id']) ? (int)$_POST['requester_user_id'] : null;

    // Generate unique code
    function generateCode($type)
    {
        $prefix = ($type === 'note') ? 'NT' : 'BK';
        return $prefix . date('YmdHis') . random_int(100, 999);
    }

    $codeId = generateCode($materialType);

    /* ══════════════════════════════════════
       VALIDATE PDF
    ══════════════════════════════════════ */
    $pdfFile         = $_FILES['pdf_file'];
    $allowedPdfTypes = ['application/pdf'];
    $maxFileSize     = 50 * 1024 * 1024; // 50 MB

    $finfo       = finfo_open(FILEINFO_MIME_TYPE);
    $pdfMimeType = finfo_file($finfo, $pdfFile['tmp_name']);
    finfo_close($finfo);

    if (!in_array($pdfMimeType, $allowedPdfTypes)) {
        throw new Exception('Invalid file type. Only PDF files are allowed');
    }

    if ($pdfFile['size'] > $maxFileSize) {
        throw new Exception('File size exceeds 50MB limit');
    }

    /* ══════════════════════════════════════
       COVER IMAGE (books only — optional)
    ══════════════════════════════════════ */
    $coverImagePath = null;
    $coverFullPath  = null; // kept for cleanup on error

    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $coverFile         = $_FILES['cover_image'];
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

        $finfo         = finfo_open(FILEINFO_MIME_TYPE);
        $coverMimeType = finfo_file($finfo, $coverFile['tmp_name']);
        finfo_close($finfo);

        if (!in_array($coverMimeType, $allowedImageTypes)) {
            throw new Exception('Invalid cover image type. Only JPG, PNG, and WebP are allowed');
        }

        // Filesystem path for the cover image
        $coverExt       = pathinfo($coverFile['name'], PATHINFO_EXTENSION);
        $coverFileName  = 'cover_' . uniqid() . '.' . $coverExt;
        $coverUploadDir = '../../user_pages/uploads/cover_img/';  // ← NOT commented out

        if (!is_dir($coverUploadDir)) {
            mkdir($coverUploadDir, 0755, true);
        }

        $coverFullPath = $coverUploadDir . $coverFileName;

        if (!move_uploaded_file($coverFile['tmp_name'], $coverFullPath)) {
            throw new Exception('Failed to upload cover image');
        }

        // DB path  →  admin/user_pages/uploads/cover_img/cover_xxx.jpg
        $coverImagePath = $coverFileName;
    }

    /* ══════════════════════════════════════
       UPLOAD PDF
    ══════════════════════════════════════ */
    $pdfExt      = pathinfo($pdfFile['name'], PATHINFO_EXTENSION);
    $pdfFileName = 'material_' . uniqid() . '.' . $pdfExt;

    // Filesystem upload directory
    if ($materialType === 'note') {
        $uploadDir = '../../user_pages/uploads/notes/';
    } else {
        $uploadDir = '../../user_pages/uploads/books/pdf/';
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $pdfFilePath = $uploadDir . $pdfFileName;

    if (!move_uploaded_file($pdfFile['tmp_name'], $pdfFilePath)) {
        throw new Exception('Failed to upload PDF file');
    }

    // DB path  →  admin/user_pages/uploads/notes/material_xxx.pdf
    //          or  admin/user_pages/uploads/books/material_xxx.pdf
    $pdfDbPath = str_replace('../../', 'admin/', $pdfFilePath);

    $fileSize = filesize($pdfFilePath);

    /* ══════════════════════════════════════
       DATABASE INSERT
    ══════════════════════════════════════ */
    $conn->beginTransaction();

    if ($materialType === 'note') {
        $insertStmt = $conn->prepare("
            INSERT INTO notes (
                n_code, user_id, admin_id, title, description,
                subject, course, notes_level, file_path,
                file_size, file_type, document_type,
                approval_status, created_at, updated_at
            ) VALUES (
                ?, NULL, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                'approved', NOW(), NOW()
            )
        ");

        $insertStmt->execute([
            $codeId,
            $adminId,
            $title,
            $description,
            $subject,
            $course,
            $classLevel,
            $pdfDbPath,
            $fileSize,
            'application/pdf',
            $docSubtype,
        ]);
    } else {
        $insertStmt = $conn->prepare("
            INSERT INTO books (
                b_code, user_id, admin_id, title, author,
                description, subject, class_level, file_path,
                cover_image, file_size, file_type, document_type,
                approval_status, created_at, updated_at
            ) VALUES (
                ?, NULL, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                'approved', NOW(), NOW()
            )
        ");

        $insertStmt->execute([
            $codeId,
            $adminId,
            $title,
            $author,
            $description,
            $subject,
            $classLevel,
            $pdfDbPath,
            $coverImagePath,   // NULL if no cover was uploaded
            $fileSize,
            'application/pdf',
            $docSubtype,
        ]);
    }

    /* ══════════════════════════════════════
       FULFIL REQUEST (if applicable)
    ══════════════════════════════════════ */
    if ($requestId && $requesterUserId) {
        // Build encrypted viewer URL
        $encryptedMaterialId = encryptId($codeId);
        $encryptedUserId     = encryptId($requesterUserId);
        $encryptedType       = encryptId($materialType);

        $viewerUrl = "http://localhost/ScholarSwap/notes_reader" .
            "?r={$encryptedMaterialId}&u={$encryptedUserId}&t={$encryptedType}";
        $updateStmt = $conn->prepare("
            UPDATE material_requests
            SET status       = 'Fulfilled',
                resource_link= ?,
                fulfilled_by = ?,
                fulfilled_at = NOW(),
                admin_note   = CONCAT(COALESCE(admin_note, ''), '\n✅ Material uploaded by admin on ', NOW())
            WHERE request_id = ?
        ");
        $updateStmt->execute([$viewerUrl, $adminId, $requestId]);

        // Get request details for notification
        $reqStmt = $conn->prepare("
            SELECT ref_code, tracking_number, title AS req_title
            FROM   material_requests
            WHERE  request_id = ?
        ");
        $reqStmt->execute([$requestId]);
        $reqData = $reqStmt->fetch(PDO::FETCH_ASSOC);


        $notifTitle   = "Your Material Request Has Been Fulfilled! 🎉";
        $notifMessage =
            "Great news! The material you requested has been uploaded and is now available.\n\n" .
            "📚 Material: {$title}\n" .
            "🔖 Your Request: {$reqData['req_title']}\n" .
            "📋 Ref: {$reqData['ref_code']}\n\n" .
            "Click the link below to view your material.\n\n" .
            "📖 View Material: {$viewerUrl}";

        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, created_at, is_read)
            VALUES (?, ?, ?, ?, NOW(), 0)
        ");
        $notifStmt->execute([$requesterUserId, 'success', $notifTitle, $notifMessage]);
    }

    $conn->commit();

    $response['success'] = true;
    $response['message'] = ($materialType === 'note')
        ? 'Notes uploaded successfully'
        : 'Book uploaded successfully';

    if ($requestId) {
        $response['message'] .= ' and request marked as fulfilled';
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Clean up any files that were already moved
    if (isset($pdfFilePath) && file_exists($pdfFilePath)) {
        unlink($pdfFilePath);
    }
    if (isset($coverFullPath) && $coverFullPath && file_exists($coverFullPath)) {
        unlink($coverFullPath);
    }

    $response['message'] = $e->getMessage();
}

echo json_encode($response);
