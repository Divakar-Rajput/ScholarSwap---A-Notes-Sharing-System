<?php
session_start();
require_once "config/connection.php";
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

function q($conn, $sql)
{
    $s = $conn->prepare($sql);
    $s->execute();
    return (int)$s->fetchColumn();
}

$c = [
    'users'    => q($conn, "SELECT COUNT(*) FROM users"),
    'students' => q($conn, "SELECT COUNT(*) FROM students"),
    'tutors'   => q($conn, "SELECT COUNT(*) FROM tutors"),
    'admins'   => q($conn, "SELECT COUNT(*) FROM admin_user"),
    'notes'    => q($conn, "SELECT COUNT(*) FROM notes"),
    'books'    => q($conn, "SELECT COUNT(*) FROM books"),
    'papers'   => q($conn, "SELECT COUNT(*) FROM newspapers"),
    'n_pending' => q($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='pending'"),
    'b_pending' => q($conn, "SELECT COUNT(*) FROM books WHERE approval_status='pending'"),
    'p_pending' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'"),
];
$c['pending'] = $c['n_pending'] + $c['b_pending'] + $c['p_pending'];

$pq = $conn->prepare("SELECT * FROM admin_user WHERE admin_id=? LIMIT 1");
$pq->execute([$_SESSION['admin_id']]);
$admin = $pq->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    header("Location: admin_logout.php");
    exit;
}

// ── Handle POST ──
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ════════════════════════════════════════
    // PROFILE + IMAGE UPDATE
    // ════════════════════════════════════════
    if ($action === 'profile') {

        $fields = [
            'first_name',
            'last_name',
            'phone',
            'gender',
            'dob',
            'state',
            'district',
            'course',
            'institution',
            'subjects',
            'bio',
            'current_address',
            'permanent_address'
        ];
        $data = [];
        foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

        if (empty($data['first_name'])) $errors[] = 'First name is required.';
        if (empty($data['last_name']))  $errors[] = 'Last name is required.';

        // ── Image upload ──
        $newImage = $admin['profile_image'] ?? '';

        if (!empty($_FILES['profile_image']['name'])) {
            $file    = $_FILES['profile_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 2 * 1024 * 1024; // 2 MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Image upload failed (error code ' . $file['error'] . '). Please try again.';
            } elseif (!in_array($file['type'], $allowed)) {
                $errors[] = 'Profile image must be JPG, PNG, WEBP or GIF.';
            } elseif ($file['size'] > $maxSize) {
                $errors[] = 'Profile image must be under 2 MB.';
            } else {
                $uploadDir = '../uploads/admin_profiles/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

                // Delete old image
                if (!empty($admin['profile_image']) && file_exists('../' . $admin['profile_image'])) {
                    unlink('../' . $admin['profile_image']);
                }

                $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'admin_' . $_SESSION['admin_id'] . '_' . time() . '.' . $ext;
                $dest     = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $newImage = 'uploads/admin_profiles/' . $filename;
                } else {
                    $errors[] = 'Could not save the image. Check folder permissions.';
                }
            }
        }

        if (empty($errors)) {
            $fields[] = 'profile_image';
            $data['profile_image'] = $newImage;
            $set  = implode(',', array_map(fn($f) => "$f=:$f", $fields));
            $stmt = $conn->prepare("UPDATE admin_user SET $set WHERE admin_id=:id");
            $data['id'] = $_SESSION['admin_id'];
            if ($stmt->execute($data)) {
                $_SESSION['admin_name'] = $data['first_name'] . ' ' . $data['last_name'];
                header("Location: edit_profile.php?s=updated");
                exit;
            } else {
                $errors[] = 'Database error. Please try again.';
            }
        }
    }

    // ════════════════════════════════════════
    // PASSWORD CHANGE
    // ════════════════════════════════════════
    if ($action === 'password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password']     ?? '';
        $con = $_POST['confirm_password'] ?? '';

        if (empty($cur) || empty($new) || empty($con)) {
            $errors[] = 'All password fields are required.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $con) {
            $errors[] = 'New passwords do not match.';
        } elseif (!password_verify($cur, $admin['password'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE admin_user SET password=? WHERE admin_id=?")
                ->execute([$hash, $_SESSION['admin_id']]);
            header("Location: edit_profile.php?s=pw_updated");
            exit;
        }
    }
}

// ── Rebuild admin after possible POST ──
$pq->execute([$_SESSION['admin_id']]);
$admin = $pq->fetch(PDO::FETCH_ASSOC);

$initials = strtoupper(($admin['first_name'][0] ?? '') . ($admin['last_name'][0] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Edit Profile | ScholarSwap Admin</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --blue: #2563eb;
            --blue-s: #dbeafe;
            --green: #059669;
            --green-s: #d1fae5;
            --red: #dc2626;
            --red-s: #fee2e2;
            --amber: #d97706;
            --amber-s: #fef3c7;
            --purple: #7c3aed;
            --purple-s: #ede9fe;
            --teal: #0d9488;
            --teal-s: #ccfbf1;
            --indigo: #4f46e5;
            --indigo-s: #e0e7ff;
            --bg: #f1f5f9;
            --surface: #fff;
            --border: #e2e8f0;
            --border2: #cbd5e1;
            --text: #0f172a;
            --text2: #475569;
            --text3: #94a3b8;
            --r: 10px;
            --r2: 16px;
            --sh: 0 1px 3px rgba(0, 0, 0, .06), 0 4px 14px rgba(0, 0, 0, .05);
            --sh2: 0 8px 28px rgba(0, 0, 0, .10);
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            font-size: 14px;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px;
        }

        /* ── Page heading ── */
        .pg-head {
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pg-head h1 {
            font-size: 1.3rem;
            font-weight: 800;
        }

        .pg-head p {
            font-size: .8rem;
            color: var(--text3);
            margin-top: 3px;
        }

        /* ── Layout ── */
        .edit-grid {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 20px;
            align-items: start;
        }

        /* ── Mini card ── */
        .mini-card {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden;
            position: sticky;
            top: 80px;
        }

        .mini-cover {
            height: 72px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed, #0d9488);
        }

        .mini-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 18px 20px;
            margin-top: -38px;
        }

        /* ── Avatar upload widget ── */
        .av-wrap {
            position: relative;
            cursor: pointer;
            display: inline-block;
        }

        .av-wrap:hover .av-overlay {
            opacity: 1;
        }

        .av-img {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            object-fit: cover;
            border: 4px solid var(--surface);
            box-shadow: 0 4px 16px rgba(79, 70, 229, .3);
            display: block;
        }

        .av-fallback {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            border: 4px solid var(--surface);
            box-shadow: 0 4px 16px rgba(79, 70, 229, .3);
        }

        .av-overlay {
            position: absolute;
            inset: 4px;
            border-radius: 14px;
            background: rgba(15, 23, 42, .55);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            opacity: 0;
            transition: opacity .18s;
        }

        .av-overlay i {
            color: #fff;
            font-size: .78rem;
        }

        .av-overlay span {
            color: #fff;
            font-size: .55rem;
            font-weight: 700;
            letter-spacing: .03em;
        }

        .av-badge {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 24px;
            height: 24px;
            background: var(--indigo);
            border-radius: 50%;
            border: 2.5px solid var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .av-badge i {
            font-size: .52rem;
            color: #fff;
        }

        .mini-name {
            font-size: .95rem;
            font-weight: 800;
            margin-top: 12px;
            text-align: center;
        }

        .mini-sub {
            font-size: .72rem;
            color: var(--text3);
            margin-top: 2px;
            text-align: center;
        }

        .bdg {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 9px;
            border-radius: 99px;
            font-size: .6rem;
            font-weight: 700;
        }

        .bdg-super {
            background: var(--indigo-s);
            color: var(--indigo);
        }

        .bdg-admin {
            background: var(--purple-s);
            color: var(--purple);
        }

        .img-hint {
            font-size: .62rem;
            color: var(--text3);
            margin-top: 7px;
            text-align: center;
            line-height: 1.5;
            min-height: 28px;
        }

        /* ── Sidebar nav ── */
        .nav-list {
            padding: 12px 10px;
            border-top: 1px solid var(--border);
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 11px;
            border-radius: 9px;
            font-size: .82rem;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 2px;
            transition: all .13s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .nav-link:hover,
        .nav-link.on {
            background: var(--indigo-s);
            color: var(--indigo);
        }

        .nav-link i {
            font-size: .78rem;
            width: 16px;
            text-align: center;
        }

        /* ── Forms column ── */
        .forms-col {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .form-panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
        }

        .fp-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .fp-ico {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            flex-shrink: 0;
        }

        .fp-ico.ind {
            background: var(--indigo-s);
            color: var(--indigo);
        }

        .fp-ico.amb {
            background: var(--amber-s);
            color: var(--amber);
        }

        .fp-head h2 {
            font-size: .95rem;
            font-weight: 800;
        }

        .fp-head p {
            font-size: .73rem;
            color: var(--text3);
            margin-top: 1px;
        }

        .fp-body {
            padding: 20px;
        }

        .fg {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 18px;
        }

        .fgrp {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .fgrp.full {
            grid-column: 1/-1;
        }

        label {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--text3);
        }

        input[type=text],
        input[type=email],
        input[type=tel],
        input[type=date],
        input[type=password],
        select,
        textarea {
            width: 100%;
            padding: 9px 13px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: .85rem;
            color: var(--text);
            background: var(--surface);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--indigo);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, .07);
        }

        input::placeholder,
        textarea::placeholder {
            color: var(--text3);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
            line-height: 1.5;
        }

        /* Password toggle */
        .pw-wrap {
            position: relative;
        }

        .pw-wrap input {
            padding-right: 38px;
        }

        .pw-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            cursor: pointer;
            color: var(--text3);
            font-size: .85rem;
            padding: 0;
        }

        .pw-eye:hover {
            color: var(--text2);
        }

        /* Alerts */
        .err-box {
            background: var(--red-s);
            border: 1px solid rgba(220, 38, 38, .2);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: .82rem;
            color: var(--red);
        }

        .err-box ul {
            padding-left: 16px;
        }

        .err-box li {
            margin-top: 3px;
        }

        /* Info box */
        .info-box {
            background: var(--bg);
            border-radius: 9px;
            padding: 11px 14px;
            font-size: .79rem;
            color: var(--text3);
            border: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .info-box i {
            margin-top: 2px;
            color: var(--indigo);
            flex-shrink: 0;
        }

        /* Footer */
        .fp-foot {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            font-size: .8rem;
            font-weight: 600;
            transition: all .14s;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:active {
            transform: scale(.97);
        }

        .btn-primary {
            background: var(--indigo);
            color: #fff;
            box-shadow: 0 2px 8px rgba(79, 70, 229, .25);
        }

        .btn-primary:hover {
            background: #3730a3;
        }

        .btn-outline {
            background: var(--surface);
            color: var(--text2);
            border: 1.5px solid var(--border);
        }

        .btn-outline:hover {
            border-color: var(--indigo);
            color: var(--indigo);
        }

        /* Password strength */
        .pw-strength {
            margin-top: 6px;
        }

        .pw-strength-bar {
            height: 4px;
            border-radius: 99px;
            background: var(--border);
            overflow: hidden;
            margin-bottom: 4px;
        }

        .pw-strength-fill {
            height: 100%;
            border-radius: 99px;
            width: 0;
            transition: width .3s, background .3s;
        }

        .pw-strength-label {
            font-size: .65rem;
            color: var(--text3);
        }

        /* Responsive */
        @media(max-width:860px) {
            .edit-grid {
                grid-template-columns: 1fr;
            }

            .mini-card {
                position: static;
            }

            .fg {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:500px) {
            .fp-foot {
                flex-direction: column;
            }

            .fp-foot .btn {
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <?php include_once('sidebar.php'); ?>

    <!-- ══ TOPBAR ══ -->
    <?php include_once('adminheader.php'); ?>

    <!-- ══ MAIN ══ -->
    <div class="main">
        <div class="pg">

            <div class="pg-head">
                <div>
                    <h1>Edit Profile</h1>
                    <p>Update your personal information, academic details and security settings</p>
                </div>
                <a class="btn btn-outline" href="profile.php"><i class="fas fa-arrow-left"></i> Back to Profile</a>
            </div>

            <div class="edit-grid">

                <!-- ════ LEFT — Mini card ════ -->
                <div>
                    <div class="mini-card">
                        <div class="mini-cover"></div>
                        <div class="mini-body">

                            <!-- Avatar upload widget -->
                            <div class="av-wrap" onclick="document.getElementById('imgInput').click()" title="Click to change photo">
                                <?php if (!empty($admin['profile_image'])): ?>
                                    <img id="avatarPreview"
                                        class="av-img"
                                        src="../<?php echo htmlspecialchars($admin['profile_image']); ?>"
                                        onerror="this.style.display='none';document.getElementById('avatarFallback').style.display='flex'">
                                    <div id="avatarFallback" class="av-fallback" style="display:none"><?php echo $initials; ?></div>
                                <?php else: ?>
                                    <img id="avatarPreview" class="av-img" style="display:none">
                                    <div id="avatarFallback" class="av-fallback"><?php echo $initials; ?></div>
                                <?php endif; ?>

                                <div class="av-overlay">
                                    <i class="fas fa-camera"></i>
                                    <span>CHANGE</span>
                                </div>
                                <div class="av-badge"><i class="fas fa-camera"></i></div>
                            </div>

                            <div class="img-hint" id="imgHint">
                                JPG, PNG, WEBP · Max 2 MB
                            </div>

                            <div class="mini-name"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                            <div class="mini-sub">@<?php echo htmlspecialchars($admin['username']); ?></div>
                            <div style="margin-top:8px">
                                <span class="bdg <?php echo strtolower($admin['role'] ?? '') === 'superadmin' ? 'bdg-super' : 'bdg-admin'; ?>">
                                    <?php echo $admin['role'] === 'superadmin' ? '⭐ Super Admin' : '🛡️ Admin'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="nav-list">
                            <button class="nav-link on" onclick="showSection('personal',this)">
                                <i class="fas fa-user"></i> Personal Info
                            </button>
                            <button class="nav-link" onclick="showSection('academic',this)">
                                <i class="fas fa-graduation-cap"></i> Academic Info
                            </button>
                            <button class="nav-link" onclick="showSection('address',this)">
                                <i class="fas fa-location-dot"></i> Address
                            </button>
                            <button class="nav-link" onclick="showSection('password',this)" id="pwNavBtn">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ════ RIGHT — Forms ════ -->
                <div class="forms-col">

                    <?php if (!empty($errors)): ?>
                        <div class="err-box">
                            <strong><i class="fas fa-triangle-exclamation"></i> Please fix the following:</strong>
                            <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
                        </div>
                    <?php endif; ?>

                    <!-- ── Profile form (Personal + Academic + Address share this form) ── -->
                    <form method="POST" id="profileForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="profile">
                        <!-- hidden file input sits inside the form -->
                        <input type="file" id="imgInput" name="profile_image" accept="image/*" style="display:none">

                        <!-- ── PERSONAL ── -->
                        <div class="form-panel" id="sec-personal">
                            <div class="fp-head">
                                <div class="fp-ico ind"><i class="fas fa-user"></i></div>
                                <div>
                                    <h2>Personal Information</h2>
                                    <p>Basic details about yourself</p>
                                </div>
                            </div>
                            <div class="fp-body">
                                <div class="fg">
                                    <div class="fgrp">
                                        <label>First Name *</label>
                                        <input type="text" name="first_name"
                                            value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>"
                                            placeholder="First name" required>
                                    </div>
                                    <div class="fgrp">
                                        <label>Last Name *</label>
                                        <input type="text" name="last_name"
                                            value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>"
                                            placeholder="Last name" required>
                                    </div>
                                    <div class="fgrp">
                                        <label>Phone</label>
                                        <input type="tel" name="phone"
                                            value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>"
                                            placeholder="+91 XXXXX XXXXX">
                                    </div>
                                    <div class="fgrp">
                                        <label>Gender</label>
                                        <select name="gender">
                                            <option value="">Select gender</option>
                                            <?php foreach (['male', 'female', 'other'] as $g): ?>
                                                <option value="<?php echo $g; ?>" <?php echo strtolower($admin['gender'] ?? '') === $g ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($g); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fgrp">
                                        <label>Date of Birth</label>
                                        <input type="date" name="dob"
                                            value="<?php echo htmlspecialchars($admin['dob'] ?? ''); ?>">
                                    </div>
                                    <div class="fgrp">
                                        <label>State</label>
                                        <input type="text" name="state"
                                            value="<?php echo htmlspecialchars($admin['state'] ?? ''); ?>"
                                            placeholder="e.g. Maharashtra">
                                    </div>
                                    <div class="fgrp">
                                        <label>District</label>
                                        <input type="text" name="district"
                                            value="<?php echo htmlspecialchars($admin['district'] ?? ''); ?>"
                                            placeholder="e.g. Pune">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── ACADEMIC ── -->
                        <div class="form-panel" id="sec-academic" style="display:none">
                            <div class="fp-head">
                                <div class="fp-ico ind"><i class="fas fa-graduation-cap"></i></div>
                                <div>
                                    <h2>Academic Information</h2>
                                    <p>Your course and institution details</p>
                                </div>
                            </div>
                            <div class="fp-body">
                                <div class="fg">
                                    <div class="fgrp">
                                        <label>Course</label>
                                        <input type="text" name="course"
                                            value="<?php echo htmlspecialchars($admin['course'] ?? ''); ?>"
                                            placeholder="e.g. B.Tech, MBA">
                                    </div>
                                    <div class="fgrp">
                                        <label>Institution</label>
                                        <input type="text" name="institution"
                                            value="<?php echo htmlspecialchars($admin['institution'] ?? ''); ?>"
                                            placeholder="University / College name">
                                    </div>
                                    <div class="fgrp full">
                                        <label>Subjects</label>
                                        <input type="text" name="subjects"
                                            value="<?php echo htmlspecialchars($admin['subjects'] ?? ''); ?>"
                                            placeholder="e.g. Mathematics, Physics, Chemistry">
                                    </div>
                                    <div class="fgrp full">
                                        <label>Bio</label>
                                        <textarea name="bio" placeholder="Write a short bio about yourself…"><?php echo htmlspecialchars($admin['bio'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── ADDRESS ── -->
                        <div class="form-panel" id="sec-address" style="display:none">
                            <div class="fp-head">
                                <div class="fp-ico ind"><i class="fas fa-location-dot"></i></div>
                                <div>
                                    <h2>Address</h2>
                                    <p>Your residential address details</p>
                                </div>
                            </div>
                            <div class="fp-body">
                                <div class="fg">
                                    <div class="fgrp full">
                                        <label>Current Address</label>
                                        <textarea name="current_address"
                                            placeholder="Flat / House no., Street, City, State, PIN"><?php echo htmlspecialchars($admin['current_address'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="fgrp full">
                                        <label>Permanent Address</label>
                                        <textarea name="permanent_address"
                                            placeholder="Flat / House no., Street, City, State, PIN"><?php echo htmlspecialchars($admin['permanent_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shared save footer -->
                        <div class="fp-foot" id="profileFoot">
                            <a class="btn btn-outline" href="profile.php"><i class="fas fa-xmark"></i> Cancel</a>
                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="fas fa-floppy-disk"></i> Save Changes
                            </button>
                        </div>
                    </form>

                    <!-- ── PASSWORD FORM ── -->
                    <form method="POST" id="pwForm" style="display:none">
                        <input type="hidden" name="action" value="password">
                        <div class="form-panel">
                            <div class="fp-head">
                                <div class="fp-ico amb"><i class="fas fa-key"></i></div>
                                <div>
                                    <h2>Change Password</h2>
                                    <p>Set a new secure password for your account</p>
                                </div>
                            </div>
                            <div class="fp-body">
                                <div class="fg">
                                    <div class="fgrp full">
                                        <label>Current Password</label>
                                        <div class="pw-wrap">
                                            <input type="password" name="current_password" id="cp"
                                                placeholder="Enter your current password">
                                            <button type="button" class="pw-eye" onclick="togglePw('cp',this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="fgrp">
                                        <label>New Password</label>
                                        <div class="pw-wrap">
                                            <input type="password" name="new_password" id="np"
                                                placeholder="Min. 8 characters"
                                                oninput="checkStrength(this.value)">
                                            <button type="button" class="pw-eye" onclick="togglePw('np',this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="pw-strength">
                                            <div class="pw-strength-bar">
                                                <div class="pw-strength-fill" id="strengthFill"></div>
                                            </div>
                                            <div class="pw-strength-label" id="strengthLabel"></div>
                                        </div>
                                    </div>
                                    <div class="fgrp">
                                        <label>Confirm New Password</label>
                                        <div class="pw-wrap">
                                            <input type="password" name="confirm_password" id="cnp"
                                                placeholder="Repeat new password">
                                            <button type="button" class="pw-eye" onclick="togglePw('cnp',this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="fgrp full">
                                        <div class="info-box">
                                            <i class="fas fa-circle-info"></i>
                                            <span>Use at least 8 characters with a mix of uppercase, lowercase, numbers and symbols for a strong password.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="fp-foot">
                                <a class="btn btn-outline" href="profile.php"><i class="fas fa-xmark"></i> Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Update Password
                                </button>
                            </div>
                        </div>
                    </form>

                </div><!-- /forms-col -->
            </div><!-- /edit-grid -->
        </div>
    </div>

    <script>
        /* ═══════════════════════════════════
           SECTION SWITCHING
        ═══════════════════════════════════ */
        const profileSections = ['personal', 'academic', 'address'];

        function showSection(s, btn) {
            // Hide all profile sections + footer
            profileSections.forEach(sec => {
                const el = document.getElementById('sec-' + sec);
                if (el) el.style.display = 'none';
            });
            document.getElementById('profileFoot').style.display = 'none';
            document.getElementById('pwForm').style.display = 'none';

            // Toggle nav highlight
            document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('on'));
            btn.classList.add('on');

            if (s === 'password') {
                document.getElementById('pwForm').style.display = 'block';
            } else {
                const el = document.getElementById('sec-' + s);
                if (el) el.style.display = 'block';
                document.getElementById('profileFoot').style.display = 'flex';
            }
        }

        // Auto-show password section if PHP returned password errors
        <?php if (!empty($errors) && ($_POST['action'] ?? '') === 'password'): ?>
            document.getElementById('pwNavBtn').click();
        <?php endif; ?>

        // Hash-based jump
        if (location.hash === '#password') {
            document.getElementById('pwNavBtn').click();
        }

        /* ═══════════════════════════════════
           PROFILE IMAGE UPLOAD + PREVIEW
        ═══════════════════════════════════ */
        document.getElementById('imgInput').addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;

            // Client-side validation
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Type',
                    text: 'Please select a JPG, PNG, WEBP or GIF image.',
                    confirmButtonColor: '#4f46e5'
                });
                this.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'Please choose an image smaller than 2 MB.',
                    confirmButtonColor: '#4f46e5'
                });
                this.value = '';
                return;
            }

            // Show live preview
            const reader = new FileReader();
            reader.onload = e => {
                const preview = document.getElementById('avatarPreview');
                const fallback = document.getElementById('avatarFallback');
                preview.src = e.target.result;
                preview.style.display = 'block';
                if (fallback) fallback.style.display = 'none';

                const kb = (file.size / 1024).toFixed(0);
                document.getElementById('imgHint').innerHTML =
                    `<span style="color:#4f46e5;font-weight:700"><i class="fas fa-check-circle"></i> ${file.name}</span><br>
                     <span style="color:#94a3b8">${kb} KB — will upload on save</span>`;
            };
            reader.readAsDataURL(file);
        });

        /* ═══════════════════════════════════
           PASSWORD VISIBILITY TOGGLE
        ═══════════════════════════════════ */
        function togglePw(id, btn) {
            const inp = document.getElementById(id);
            inp.type = inp.type === 'password' ? 'text' : 'password';
            btn.querySelector('i').className = inp.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        /* ═══════════════════════════════════
           PASSWORD STRENGTH METER
        ═══════════════════════════════════ */
        function checkStrength(val) {
            const fill = document.getElementById('strengthFill');
            const label = document.getElementById('strengthLabel');
            if (!val) {
                fill.style.width = '0';
                label.textContent = '';
                return;
            }

            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            if (val.length >= 12) score++;

            const levels = [{
                    w: '20%',
                    color: '#ef4444',
                    text: 'Very Weak'
                },
                {
                    w: '40%',
                    color: '#f97316',
                    text: 'Weak'
                },
                {
                    w: '60%',
                    color: '#eab308',
                    text: 'Fair'
                },
                {
                    w: '80%',
                    color: '#22c55e',
                    text: 'Strong'
                },
                {
                    w: '100%',
                    color: '#059669',
                    text: 'Very Strong'
                },
            ];
            const lvl = levels[Math.min(score - 1, 4)] || levels[0];
            fill.style.width = lvl.w;
            fill.style.background = lvl.color;
            label.textContent = lvl.text;
            label.style.color = lvl.color;
        }

        /* ═══════════════════════════════════
           SWEETALERT FEEDBACK (URL params)
        ═══════════════════════════════════ */
        const sp = new URLSearchParams(window.location.search);

        if (sp.get('s') === 'updated') {
            Swal.fire({
                icon: 'success',
                title: 'Profile Updated!',
                text: 'Your profile information has been saved successfully.',
                timer: 2800,
                timerProgressBar: true,
                showConfirmButton: false,
                iconColor: '#059669'
            });
            history.replaceState(null, '', 'edit_profile.php');
        }

        if (sp.get('s') === 'pw_updated') {
            Swal.fire({
                icon: 'success',
                title: 'Password Changed!',
                text: 'Your password has been updated. Use it next time you log in.',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                iconColor: '#059669'
            });
            history.replaceState(null, '', 'edit_profile.php');
        }
    </script>
</body>

</html>