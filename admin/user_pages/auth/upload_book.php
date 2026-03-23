<?php
session_start();
require_once "../../config/connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../../login.html");
    exit;
}
function generateBookCode(): string
{
    return 'BK' . date('YmdHis') . random_int(100, 999);
}
$contentID = generateBookCode();

$user_id = $_SESSION['user_id'];

/* ---------- Collect & sanitize ---------- */
$title            = trim($_POST['title']);
$subject          = trim($_POST['subject']);
$class_level          = trim($_POST['class_level']);
$author           = trim($_POST['author']);
$description      = trim($_POST['description']);
$published_year   = $_POST['publish_year'];
$publication_name = trim($_POST['publication_name']);

/* ---------- Basic validation ---------- */
if (empty($title) || empty($subject) || empty($author)) {
    header("Location: ../../upload_book.php?s=failed");
    exit;
}

/* ---------- Directories ---------- */
$pdfDir   = "../uploads/books/pdf/";
$coverDir = "../uploads/cover_img/";

if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0777, true);
}
if (!is_dir($coverDir)) {
    mkdir($coverDir, 0777, true);
}

/* ---------- Upload PDF ---------- */
$pdfName = null;
$fileSize = 0;
$fileType = null;

if (!empty($_FILES['pdf']['name'])) {

    $fileTmp  = $_FILES['pdf']['tmp_name'];
    $fileSize = $_FILES['pdf']['size'];
    $fileType = $_FILES['pdf']['type'];
    $ext      = strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION));

    if ($ext !== 'pdf') {
        header("Location: ../../upload_book.php?s=invalid_pdf");
        exit;
    }

    $pdfName = uniqid("book_") . ".pdf";
    move_uploaded_file($fileTmp, $pdfDir . $pdfName);
}
$pdfpath  = "admin/user_pages/uploads/books/pdf/" . $pdfName;
/* ---------- Upload cover image ---------- */
$coverName = null;

if (!empty($_FILES['cover_image']['name'])) {

    $imgExt = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($imgExt, $allowed)) {
        header("Location: ../../upload_book.php?s=invalid_image");
        exit;
    }

    $coverName = uniqid("cover_") . "." . $imgExt;
    move_uploaded_file(
        $_FILES['cover_image']['tmp_name'],
        $coverDir . $coverName
    );
}

/* ---------- Insert into books table ---------- */
$stmt = $conn->prepare("
    INSERT INTO books (
        b_code,
        user_id,
        title,
        author,
        description,
        subject,
        class_level,
        file_path,
        cover_image,
        file_size,
        file_type,
        approval_status,
        is_featured,
        view_count,
        download_count,
        publication_name,
        published_year,
        created_at,
        updated_at
    ) VALUES (
        :book_id,
        :user_id,
        :title,
        :author,
        :description,
        :subject,
        :level,
        :file_path,
        :cover_image,
        :file_size,
        :file_type,
        'pending',
        0,
        0,
        0,
        :publication_name,
        :published_year,
        NOW(),
        NOW()
    )
");

$success = $stmt->execute([
    ':book_id'          => $contentID,
    ':user_id'          => $user_id,
    ':title'            => $title,
    ':author'           => $author,
    ':description'      => $description,
    ':subject'          => $subject,
    ':level'          => $class_level,
    ':file_path'        => $pdfpath,
    ':cover_image'      => $coverName,
    ':file_size'        => $fileSize,
    ':file_type'        => $fileType,
    ':publication_name' => $publication_name,
    ':published_year'   => $published_year
]);

/* ---------- Redirect ---------- */
if ($success) {
    header("Location: ../../../../../ScholarSwap/books.php?s=success");
} else {
    header("Location: ../../../../../ScholarSwap/books.php?s=failed");
}
exit;
