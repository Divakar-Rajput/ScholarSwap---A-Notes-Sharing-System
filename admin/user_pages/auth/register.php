<?php
require_once('../../files/config/connection.php');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request");
}

/* =====================
   1. Collect & Sanitize
===================== */

$first_name   = trim($_POST['first_name']);
$last_name    = trim($_POST['last_name']);
$username     = trim($_POST['username']);
$email        = trim($_POST['email']);
$phone        = trim($_POST['phone']);
$password     = $_POST['password'];
$role         = $_POST['role']; // student | tutor

$dob          = $_POST['dob'];
$gender       = $_POST['gender'];
$state        = $_POST['state'];
$district     = $_POST['district'];

$course       = $_POST['course'] ?? null;
$institution  = $_POST['institution'];
$subjects     = $_POST['subjects'] ?? null;
$bio          = $_POST['bio'] ?? null;

$current_addr   = $_POST['current_address'];
$permanent_addr = $_POST['permanent_address'];

/* =====================
   2. Basic Validation
===================== */

if (
    empty($username) || empty($email) || empty($password) ||
    empty($first_name) || empty($role)
) {
    exit("Required fields missing");
}

if (!in_array($role, ['student', 'tutor'])) {
    exit("Invalid role");
}

/* =====================
   3. Check Existing User
===================== */

$check = $conn->prepare(
    "SELECT user_id FROM users WHERE email = :email OR username = :username"
);
$check->execute([
    ':email' => $email,
    ':username' => $username
]);

if ($check->rowCount() > 0) {
    exit("Username or email already exists");
}

/* =====================
   4. Insert into users
===================== */

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$conn->beginTransaction();

try {
    $userSql = "INSERT INTO users 
        (username, email, phone, password_hash, role) 
        VALUES 
        (:username, :email, :phone, :password, :role)";

    $stmt = $conn->prepare($userSql);
    $stmt->execute([
        ':username' => $username,
        ':email'    => $email,
        ':phone'    => $phone,
        ':password' => $passwordHash,
        ':role'     => $role
    ]);

    $user_id = $conn->lastInsertId();

    /* =====================
       5. Insert Role Data
    ===================== */

    if ($role === "student") {

        $studentSql = "INSERT INTO students
            (user_id, first_name, last_name, dob, gender, state, district,
             course, institution, subjects_of_interest, bio,
             current_address, permanent_address)
            VALUES
            (:user_id, :first_name, :last_name, :dob, :gender, :state, :district,
             :course, :institution, :subjects, :bio,
             :current_addr, :permanent_addr)";

        $stmt = $conn->prepare($studentSql);
        $stmt->execute([
            ':user_id'        => $user_id,
            ':first_name'     => $first_name,
            ':last_name'      => $last_name,
            ':dob'            => $dob,
            ':gender'         => $gender,
            ':state'          => $state,
            ':district'       => $district,
            ':course'         => $course,
            ':institution'    => $institution,
            ':subjects'       => $subjects,
            ':bio'            => $bio,
            ':current_addr'   => $current_addr,
            ':permanent_addr' => $permanent_addr
        ]);

    } else {

        $tutorSql = "INSERT INTO tutors
            (user_id, first_name, last_name, dob, gender, state, district,
             qualification, institution, subjects_taught, bio,
             current_address, permanent_address)
            VALUES
            (:user_id, :first_name, :last_name, :dob, :gender, :state, :district,
             :qualification, :institution, :subjects, :bio,
             :current_addr, :permanent_addr)";

        $stmt = $conn->prepare($tutorSql);
        $stmt->execute([
            ':user_id'        => $user_id,
            ':first_name'     => $first_name,
            ':last_name'      => $last_name,
            ':dob'            => $dob,
            ':gender'         => $gender,
            ':state'          => $state,
            ':district'       => $district,
            ':qualification'  => $course, // reuse field
            ':institution'    => $institution,
            ':subjects'       => $subjects,
            ':bio'            => $bio,
            ':current_addr'   => $current_addr,
            ':permanent_addr' => $permanent_addr
        ]);
    }

    $conn->commit();

    header("Location: http://localhost/ScholarSwap/signup.html?s=success"); exit;

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: http://localhost/ScholarSwap/signup.html?s=failed&msg=db_error");exit;
}