<?php
session_start();
require_once "../../config/connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.html");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ── POST fields ──
$first_name        = trim($_POST['first_name']        ?? '');
$last_name         = trim($_POST['last_name']         ?? '');
$phone             = trim($_POST['phone']             ?? '');
$gender            = trim($_POST['gender']            ?? '');
$dob               = trim($_POST['dob']               ?? '');   // name="dob" in the form
$institution       = trim($_POST['institution']       ?? '');
$course            = trim($_POST['course']            ?? '');
$qualification     = trim($_POST['qualification']     ?? '');
$experience_years  = trim($_POST['experience_years']  ?? '');
$subjects          = trim($_POST['subjects']          ?? '');
$bio               = trim($_POST['bio']               ?? '');
$current_address   = trim($_POST['current_address']   ?? '');
$permanent_address = trim($_POST['permanent_address'] ?? '');
$state             = trim($_POST['state']             ?? '');
$district          = trim($_POST['district']          ?? '');

// ── Fix date: empty string → NULL (prevents 0000-00-00 in DB) ──
$dobValue = (!empty($dob) && $dob !== '0000-00-00') ? $dob : null;

// ── Fetch role ──
$stmt = $conn->prepare('SELECT role FROM users WHERE user_id = :id');
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../../login.html");
    exit;
}
$role = $user['role'];

// ── Handle profile image upload ──
$profileImageSql  = '';
$profileImageBind = [];

if (!empty($_FILES['profile_image']['tmp_name'])) {
    $file      = $_FILES['profile_image'];
    $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mimeType  = mime_content_type($file['tmp_name']);

    if (in_array($mimeType, $allowed) && $file['size'] <= 3 * 1024 * 1024) {
        $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename  = 'profile_' . $user_id . '_' . time() . '.' . strtolower($ext);
        $uploadDir = __DIR__ . '/../../../admin/user_pages/uploads/profile_images/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Delete old profile image if it exists
            $oldStmt = $conn->prepare('SELECT profile_image FROM users WHERE user_id = :id');
            $oldStmt->execute([':id' => $user_id]);
            $oldImg = $oldStmt->fetchColumn();
            if ($oldImg && file_exists(__DIR__ . '/../../../' . $oldImg)) {
                @unlink(__DIR__ . '/../../../' . $oldImg);
            }

            $storedPath = 'admin/user_pages/uploads/profile_images/' . $filename;
            $profileImageSql  = ', profile_image = :profile_image';
            $profileImageBind = [':profile_image' => $storedPath];
        }
    }
}

// ── Update users table (phone + optional profile_image) ──
$userSql  = "UPDATE users SET phone = :phone" . $profileImageSql . " WHERE user_id = :id";
$userBind = array_merge([':phone' => $phone, ':id' => $user_id], $profileImageBind);
$userResult = $conn->prepare($userSql)->execute($userBind);

// ── Update role table ──
$result = false;

if ($role === 'student') {
    $stmt = $conn->prepare("
        UPDATE students SET
            first_name           = :first_name,
            last_name            = :last_name,
            dob                  = :dob,
            gender               = :gender,
            course               = :course,
            institution          = :institution,
            subjects_of_interest = :subjects,
            bio                  = :bio,
            state                = :state,
            district             = :district,
            current_address      = :current_address,
            permanent_address    = :permanent_address
        WHERE user_id = :id
    ");
    $result = $stmt->execute([
        ':first_name'        => $first_name,
        ':last_name'         => $last_name,
        ':dob'               => $dobValue,
        ':gender'            => $gender,
        ':course'            => $course,
        ':institution'       => $institution,
        ':subjects'          => $subjects,
        ':bio'               => $bio,
        ':state'             => $state,
        ':district'          => $district,
        ':current_address'   => $current_address,
        ':permanent_address' => $permanent_address,
        ':id'                => $user_id,
    ]);

} elseif ($role === 'tutor') {
    $stmt = $conn->prepare("
        UPDATE tutors SET
            first_name      = :first_name,
            last_name       = :last_name,
            dob             = :dob,
            gender          = :gender,
            qualification   = :qualification,
            institution     = :institution,
            subjects_taught = :subjects,
            experience_years= :experience_years,
            bio             = :bio,
            state           = :state,
            district        = :district,
            current_address      = :current_address,
            permanent_address    = :permanent_address
        WHERE user_id = :id
    ");
    $result = $stmt->execute([
        ':first_name'        => $first_name,
        ':last_name'         => $last_name,
        ':dob'               => $dobValue,
        ':gender'            => $gender,
        ':qualification'     => $qualification,
        ':institution'       => $institution,
        ':subjects'          => $subjects,
        ':experience_years'  => $experience_years !== '' ? (int)$experience_years : 0,
        ':bio'               => $bio,
        ':state'             => $state,
        ':district'          => $district,
        ':current_address'   => $current_address,
        ':permanent_address' => $permanent_address,
        ':id'                => $user_id,
    ]);
}

// ── Redirect ──
if ($result && $userResult) {
    header('Location: ../myprofile.php?s=success');
} else {
    header('Location: ../myprofile.php?s=failed');
}
exit;