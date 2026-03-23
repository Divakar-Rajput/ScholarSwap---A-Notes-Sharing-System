<?php
require_once "../config/connection.php";
require_once('../auth_check.php');

$user_id = $_SESSION['user_id'];

$roleStmt = $conn->prepare('SELECT role FROM users WHERE user_id = :id');
$roleStmt->execute([':id' => $user_id]);
$userRow  = $roleStmt->fetch(PDO::FETCH_ASSOC);
$role     = $userRow['role'] ?? 'student';

if ($role === 'tutor') {
    $stmt = $conn->prepare("SELECT users.*, tutors.* FROM users LEFT JOIN tutors ON users.user_id = tutors.user_id WHERE users.user_id = :id");
} else {
    $stmt = $conn->prepare("SELECT users.*, students.* FROM users LEFT JOIN students ON users.user_id = students.user_id WHERE users.user_id = :id");
}
$stmt->execute([':id' => $user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

function v($data, $key, $fallback = '')
{
    return htmlspecialchars($data[$key] ?? $fallback);
}

$initials = strtoupper(substr($data['first_name'] ?? 'U', 0, 1) . substr($data['last_name'] ?? '', 0, 1));

/* ── Profile image fix ── */
$rawImg = $data['profile_image'] ?? '';
$profileImg = '';
if (!empty($rawImg)) {
    $profileImg = (str_starts_with($rawImg, 'http') || str_starts_with($rawImg, '/'))
        ? htmlspecialchars($rawImg)
        : htmlspecialchars('http://localhost/ScholarSwap/' . ltrim($rawImg, '/'));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta -->
    <meta name="description" content="ScholarSwap is a collaborative academic platform where students exchange notes, textbooks, and study resources. Save money, study smarter, and succeed together.">
    <meta name="keywords" content="student notes sharing, swap textbooks, academic resources, study materials exchange, college notes, university books, free study resources, peer learning, student community, notes marketplace, textbook swap, academic collaboration, study guides, exam preparation, student platform, educational resource sharing, buy sell books, second hand textbooks, course notes, lecture notes">
    <meta name="author" content="ScholarSwap">
    <meta name="robots" content="index, follow">
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="theme-color" content="#4F46E5">

    <!-- Canonical -->
    <link rel="canonical" href="https://www.scholarswap.com/">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.scholarswap.com/">
    <meta property="og:title" content="ScholarSwap — Share, Swap &amp; Succeed Together">
    <meta property="og:description" content="Join thousands of students sharing notes, swapping textbooks, and exchanging academic resources. Study smarter, spend less.">
    <meta property="og:image" content="https://www.scholarswap.com/assets/img/og-banner.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="ScholarSwap">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://www.scholarswap.com/">
    <meta name="twitter:title" content="ScholarSwap — Share, Swap &amp; Succeed Together">
    <meta name="twitter:description" content="A student-powered platform to share notes, swap textbooks, and ace your academics together.">
    <meta name="twitter:image" content="https://www.scholarswap.com/assets/img/twitter-banner.png">
    <meta name="twitter:site" content="@ScholarSwap">
    <meta name="twitter:creator" content="@ScholarSwap">

    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": "ScholarSwap",
            "url": "https://www.scholarswap.com",
            "description": "A collaborative academic platform for students to exchange notes, books and resources.",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "https://www.scholarswap.com/search?q={search_term_string}",
                "query-input": "required name=search_term_string"
            },
            "sameAs": [
                "https://twitter.com/ScholarSwap",
                "https://www.instagram.com/scholarswap",
                "https://www.facebook.com/scholarswap",
                "https://www.linkedin.com/company/scholarswap"
            ]
        }
    </script>
    <title>Edit Profile | ScholarSwap</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --blue: #2563eb;
            --blue-g: #3b82f6;
            --blue-light: #eff6ff;
            --blue-xlight: #dbeafe;
            --indigo: #4f46e5;
            --indigo-g: #6366f1;
            --indigo-light: #eef2ff;
            --indigo-xlight: #e0e7ff;
            --red: #dc2626;
            --green: #059669;
            --teal: #0d9488;
            --teal-light: #f0fdfa;
            --surface: #ffffff;
            --page-bg: #f4f7fe;
            --text: #0f172a;
            --text2: #475569;
            --text3: #94a3b8;
            --border: rgba(15, 23, 42, .10);
            --input-border: rgba(15, 23, 42, .14);
            --shadow-sm: 0 1px 4px rgba(15, 23, 42, .06);
            --shadow-md: 0 4px 16px rgba(15, 23, 42, .08);
            --hdr-h: 64px;
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        html {
            scroll-behavior: smooth;
            color-scheme: light
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--page-bg);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased
        }

        a {
            text-decoration: none;
            color: inherit
        }

        ::-webkit-scrollbar {
            width: 6px
        }

        ::-webkit-scrollbar-track {
            background: #f1f5fd
        }

        ::-webkit-scrollbar-thumb {
            background: #c7d2e8;
            border-radius: 6px
        }

        .page-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: linear-gradient(rgba(15, 23, 42, .022) 1px, transparent 1px), linear-gradient(90deg, rgba(15, 23, 42, .022) 1px, transparent 1px);
            background-size: 52px 52px
        }

        .page-grid::before,
        .page-grid::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(72px);
            pointer-events: none
        }

        .page-grid::before {
            width: 480px;
            height: 380px;
            top: -80px;
            left: -80px;
            background: radial-gradient(circle, rgba(37, 99, 235, .07) 0%, transparent 70%)
        }

        .page-grid::after {
            width: 360px;
            height: 320px;
            bottom: -60px;
            right: -60px;
            background: radial-gradient(circle, rgba(79, 70, 229, .05) 0%, transparent 70%)
        }

        .wrap {
            position: relative;
            z-index: 1;
            padding: calc(var(--hdr-h) + 28px) 20px 60px;
            max-width: 1060px;
            margin: 0 auto
        }

        /* Page header */
        .page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 26px;
            flex-wrap: wrap;
            gap: 12px
        }

        .page-head-left {
            display: flex;
            align-items: center;
            gap: 14px
        }

        .page-icon {
            width: 48px;
            height: 48px;
            border-radius: 13px;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--indigo-xlight), var(--blue-xlight));
            border: 1px solid rgba(79, 70, 229, .2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--indigo);
            box-shadow: var(--shadow-md)
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.1
        }

        .page-sub {
            font-size: .82rem;
            color: var(--text2);
            margin-top: 3px
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: 10px;
            background: #fff;
            border: 1.5px solid var(--border);
            color: var(--text2);
            font-size: .82rem;
            font-weight: 500;
            transition: all .18s;
            cursor: pointer;
            text-decoration: none;
            box-shadow: var(--shadow-sm)
        }

        .btn-back:hover {
            background: var(--blue-light);
            border-color: rgba(37, 99, 235, .25);
            color: var(--blue)
        }

        /* Profile hero */
        .profile-hero {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px 28px 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap
        }

        .avatar-wrap {
            position: relative;
            flex-shrink: 0
        }

        .avatar {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--indigo), var(--blue));
            color: #fff;
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #fff;
            box-shadow: var(--shadow-md);
            overflow: hidden
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover
        }

        .avatar-change {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--blue);
            border: 2px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .6rem;
            color: #fff;
            cursor: pointer;
            transition: background .15s;
            box-shadow: var(--shadow-sm)
        }

        .avatar-change:hover {
            background: var(--indigo)
        }

        .profile-info {
            flex: 1
        }

        .profile-name {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .profile-email {
            font-size: .82rem;
            color: var(--text2);
            margin-bottom: 8px
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 99px
        }

        .role-student {
            background: var(--blue-xlight);
            color: var(--blue);
            border: 1px solid rgba(37, 99, 235, .2)
        }

        .role-tutor {
            background: var(--teal-light);
            color: var(--teal);
            border: 1px solid rgba(13, 148, 136, .2)
        }

        /* Cards */
        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 26px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 18px
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: .88rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border)
        }

        .card-title i {
            color: var(--indigo-g);
            font-size: .82rem
        }

        .g2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px
        }

        .g3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px
        }

        .g1 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px
        }

        @media(max-width:640px) {

            .g2,
            .g3 {
                grid-template-columns: 1fr
            }
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px
        }

        label {
            font-size: .79rem;
            font-weight: 600;
            color: var(--text2);
            display: flex;
            align-items: center;
            gap: 5px
        }

        .req {
            color: var(--red);
            font-size: .85rem;
            line-height: 1
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 11px 14px;
            background: #f8fafc;
            border: 1.5px solid var(--input-border);
            border-radius: 10px;
            color: var(--text);
            font-size: .88rem;
            outline: none;
            transition: border-color .18s, background .18s, box-shadow .18s;
            -webkit-appearance: none
        }

        .hdr-search input {
            border: none;
            background: none;
            outline: none;
            font-size: .855rem;
            color: var(--text);
            width: 100%;
            padding: 5px;
        }

        input::placeholder,
        textarea::placeholder {
            color: var(--text3)
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--indigo);
            background: var(--indigo-light);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, .10)
        }

        select option {
            background: #fff;
            color: var(--text)
        }

        textarea {
            resize: vertical;
            min-height: 90px
        }

        /* Radio / gender */
        .radio-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .radio-opt {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 10px;
            cursor: pointer;
            background: #f8fafc;
            border: 1.5px solid var(--input-border);
            font-size: .84rem;
            color: var(--text2);
            transition: all .18s;
            user-select: none
        }

        .radio-opt input {
            display: none
        }

        .radio-opt:has(input:checked) {
            background: var(--indigo-xlight);
            border-color: rgba(79, 70, 229, .35);
            color: var(--indigo)
        }

        .radio-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid var(--input-border);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .18s
        }

        .radio-opt:has(input:checked) .radio-dot {
            border-color: var(--indigo);
            background: var(--indigo)
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            flex-wrap: wrap
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 11px;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, var(--indigo), var(--blue));
            color: #fff;
            font-size: .9rem;
            font-weight: 700;
            box-shadow: 0 6px 20px rgba(79, 70, 229, .28);
            transition: all .2s
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(79, 70, 229, .4)
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 11px;
            background: #f4f7fe;
            border: 1.5px solid var(--border);
            color: var(--text2);
            font-size: .9rem;
            font-weight: 500;
            transition: all .18s;
            cursor: pointer;
            text-decoration: none
        }

        .btn-cancel:hover {
            background: var(--blue-light);
            border-color: rgba(37, 99, 235, .25);
            color: var(--blue)
        }

        #avatarInput {
            display: none
        }
    </style>
</head>

<body>
    <div class="page-grid"></div>
    <?php include_once "../files/header.php"; ?>

    <div class="wrap">
        <div class="page-head">
            <div class="page-head-left">
                <div class="page-icon"><i class="fas fa-user-pen"></i></div>
                <div>
                    <div class="page-title">Edit Profile</div>
                    <div class="page-sub">Update your personal, academic and contact information</div>
                </div>
            </div>
            <a href="myprofile.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Profile</a>
        </div>

        <form method="POST" action="auth/update_profile.php" enctype="multipart/form-data">

            <!-- Profile hero -->
            <div class="profile-hero">
                <div class="avatar-wrap">
                    <div class="avatar" id="avatarDisplay">
                        <?php if ($profileImg): ?>
                            <img src="<?php echo $profileImg; ?>" id="avatarImg" alt="Profile"
                                onerror="this.parentElement.innerHTML='<span><?php echo $initials; ?></span>'">
                        <?php else: ?>
                            <span id="avatarInitials"><?php echo $initials; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-change" onclick="document.getElementById('avatarInput').click()" title="Change photo">
                        <i class="fas fa-camera"></i>
                    </div>
                    <input type="file" name="profile_image" id="avatarInput" accept="image/*">
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo v($data, 'first_name') . ' ' . v($data, 'last_name'); ?></div>
                    <div class="profile-email"><?php echo v($data, 'email'); ?></div>
                    <span class="role-badge role-<?php echo $role; ?>">
                        <i class="fas fa-<?php echo $role === 'tutor' ? 'chalkboard-user' : 'graduation-cap'; ?>"></i>
                        <?php echo ucfirst($role); ?>
                    </span>
                </div>
            </div>

            <!-- Personal -->
            <div class="card">
                <div class="card-title"><i class="fas fa-user"></i> Personal Information</div>
                <div class="g2" style="margin-bottom:16px">
                    <div class="field"><label>First Name <span class="req">*</span></label><input type="text" name="first_name" placeholder="First name" value="<?php echo v($data, 'first_name'); ?>" required></div>
                    <div class="field"><label>Last Name <span class="req">*</span></label><input type="text" name="last_name" placeholder="Last name" value="<?php echo v($data, 'last_name'); ?>" required></div>
                </div>
                <div class="g3" style="margin-bottom:16px">
                    <div class="field"><label>Date of Birth</label><input type="date" name="dob" value="<?php echo v($data, 'dob'); ?>"></div>
                    <div class="field"><label>Phone</label><input type="tel" name="phone" placeholder="e.g. 9876543210" value="<?php echo v($data, 'phone'); ?>"></div>
                    <div class="field">
                        <label>Gender</label>
                        <div class="radio-group">
                            <label class="radio-opt"><input type="radio" name="gender" value="male" <?php echo ($data['gender'] ?? '') === 'male' ? 'checked' : ''; ?>><span class="radio-dot"></span> Male</label>
                            <label class="radio-opt"><input type="radio" name="gender" value="female" <?php echo ($data['gender'] ?? '') === 'female' ? 'checked' : ''; ?>><span class="radio-dot"></span> Female</label>
                            <label class="radio-opt"><input type="radio" name="gender" value="other" <?php echo ($data['gender'] ?? '') === 'other' ? 'checked' : ''; ?>><span class="radio-dot"></span> Other</label>
                        </div>
                    </div>
                </div>
                <div class="g1">
                    <div class="field"><label>Bio</label><textarea name="bio" placeholder="Write a short bio about yourself…"><?php echo v($data, 'bio'); ?></textarea></div>
                </div>
            </div>

            <!-- Academic -->
            <div class="card">
                <div class="card-title"><i class="fas fa-graduation-cap"></i> Academic Details</div>
                <div class="g2" style="margin-bottom:16px">
                    <div class="field"><label>Institution / College</label><input type="text" name="institution" placeholder="e.g. Delhi University" value="<?php echo v($data, 'institution'); ?>"></div>
                    <?php if ($role === 'student'): ?>
                        <div class="field"><label>Course</label><input type="text" name="course" placeholder="e.g. B.Sc Physics" value="<?php echo v($data, 'course'); ?>"></div>
                    <?php else: ?>
                        <div class="field"><label>Qualification</label><input type="text" name="qualification" placeholder="e.g. M.Sc Mathematics" value="<?php echo v($data, 'qualification'); ?>"></div>
                    <?php endif; ?>
                </div>
                <div class="g2">
                    <div class="field"><label>State</label><input type="text" name="state" placeholder="e.g. Delhi" value="<?php echo v($data, 'state'); ?>"></div>
                    <div class="field"><label>District</label><input type="text" name="district" placeholder="e.g. New Delhi" value="<?php echo v($data, 'district'); ?>"></div>
                </div>
                <?php if ($role === 'tutor'): ?>
                    <div class="g1" style="margin-top:16px">
                        <div class="field"><label>Experience (years)</label><input type="number" name="experience_years" min="0" max="50" placeholder="e.g. 3" value="<?php echo v($data, 'experience_years'); ?>"></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Subjects -->
            <div class="card">
                <div class="card-title"><i class="fas fa-book-open"></i> Subjects & Interests</div>
                <div class="g1">
                    <div class="field">
                        <?php if ($role === 'student'): ?>
                            <label>Subjects of Interest</label>
                            <textarea name="subjects" placeholder="e.g. Mathematics, Physics, Chemistry (comma-separated)"><?php echo v($data, 'subjects_of_interest'); ?></textarea>
                        <?php else: ?>
                            <label>Subjects Taught</label>
                            <textarea name="subjects" placeholder="e.g. Mathematics, Physics, Chemistry (comma-separated)"><?php echo v($data, 'subjects_taught'); ?></textarea>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="card">
                <div class="card-title"><i class="fas fa-location-dot"></i> Address</div>
                <div class="g2">
                    <div class="field"><label>Current Address</label><textarea name="current_address" placeholder="House no., Street, Area…"><?php echo v($data, 'current_address'); ?></textarea></div>
                    <div class="field"><label>Permanent Address</label><textarea name="permanent_address" placeholder="House no., Street, Area…" id="permanentAddr"><?php echo v($data, 'permanent_address'); ?></textarea></div>
                </div>
                <div style="margin-top:10px">
                    <label class="radio-opt" style="width:fit-content;gap:10px" id="sameAddrLabel">
                        <input type="checkbox" id="sameAddr" style="display:none">
                        <span class="radio-dot" id="sameAddrDot"></span>
                        <span style="font-size:.82rem">Same as current address</span>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions">
                <button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> Save Changes</button>
                <a href="myprofile.php" class="btn-cancel"><i class="fas fa-xmark"></i> Cancel</a>
            </div>
        </form>
    </div>

    <?php include_once "../files/footer.php"; ?>
    <script>
        document.getElementById('avatarInput').addEventListener('change', function() {
            if (!this.files[0]) return;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('avatarDisplay').innerHTML =
                    `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
            };
            reader.readAsDataURL(this.files[0]);
        });

        const sameAddr = document.getElementById('sameAddr');
        const currentAddr = document.querySelector('textarea[name="current_address"]');
        const permAddr = document.getElementById('permanentAddr');
        const dot = document.getElementById('sameAddrDot');

        function syncAddr() {
            if (sameAddr.checked) {
                permAddr.value = currentAddr.value;
                permAddr.disabled = true;
                permAddr.style.opacity = '.5';
                dot.style.background = 'var(--indigo)';
                dot.style.borderColor = 'var(--indigo)';
            } else {
                permAddr.disabled = false;
                permAddr.style.opacity = '1';
                dot.style.background = '';
                dot.style.borderColor = '';
            }
        }
        document.getElementById('sameAddrLabel').addEventListener('click', () => {
            sameAddr.checked = !sameAddr.checked;
            syncAddr();
        });
        currentAddr.addEventListener('input', () => {
            if (sameAddr.checked) permAddr.value = currentAddr.value;
        });
    </script>
</body>

</html>