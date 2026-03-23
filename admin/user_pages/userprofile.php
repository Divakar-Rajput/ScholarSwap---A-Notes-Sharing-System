<?php
include_once('../config/connection.php');
require_once('../auth_check.php');
include_once('../encryption.php');

$myId   = (int)$_SESSION['user_id'];
$userId = (int)decryptId($_GET['u'] ?? 0);

if (!$userId) {
    header('Location: index.php');
    exit;
}
if ($userId === $myId) {
    header('Location: myprofile.php');
    exit;
}

$st = $conn->prepare("SELECT * FROM users WHERE user_id = :id");
$st->execute([':id' => $userId]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: index.php');
    exit;
}

$role = $user['role'];

$st = $conn->prepare($role === 'tutor'
    ? "SELECT * FROM tutors   WHERE user_id = :id"
    : "SELECT * FROM students WHERE user_id = :id");
$st->execute([':id' => $userId]);
$data = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$stF = $conn->prepare("SELECT COUNT(*) FROM follows WHERE following_id = :id");
$stF->execute([':id' => $userId]);
$followers = (int)$stF->fetchColumn();

$stFg = $conn->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = :id");
$stFg->execute([':id' => $userId]);
$following = (int)$stFg->fetchColumn();

$stN = $conn->prepare("SELECT COUNT(*) FROM notes WHERE user_id = :id AND approval_status='approved'");
$stN->execute([':id' => $userId]);
$noteCount = (int)$stN->fetchColumn();

$stB = $conn->prepare("SELECT COUNT(*) FROM books WHERE user_id = :id AND approval_status='approved'");
$stB->execute([':id' => $userId]);
$bookCount = (int)$stB->fetchColumn();

$stChk = $conn->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = :me AND following_id = :them");
$stChk->execute([':me' => $myId, ':them' => $userId]);
$isFollowing = (bool)$stChk->fetchColumn();

$stU = $conn->prepare("
    SELECT 'note' AS type, n_code AS item_id, title, subject, document_type, created_at, user_id
    FROM notes WHERE user_id = :n AND approval_status = 'approved'
    UNION ALL
    SELECT 'book' AS type, b_code AS item_id, title, subject, document_type, created_at, user_id
    FROM books WHERE user_id = :b AND approval_status = 'approved'
    ORDER BY created_at DESC
");
$stU->execute([':n' => $userId, ':b' => $userId]);
$uploads     = $stU->fetchAll(PDO::FETCH_ASSOC);
$uploadCount = count($uploads);

$subjectRaw = $role === 'tutor' ? ($data['subjects_taught'] ?? '') : ($data['subjects_of_interest'] ?? '');
$subjectArr = array_filter(array_map('trim', explode(',', $subjectRaw)));
$related    = [];
if ($subjectArr) {
    $likeArr  = array_map(fn($s) => "%$s%", $subjectArr);
    $whereStr = implode(' OR ', array_fill(0, count($subjectArr), 'subject LIKE ?'));
    $stR = $conn->prepare("
        SELECT n_code, title, subject, view_count, download_count, user_id
        FROM notes
        WHERE user_id != ? AND approval_status = 'approved' AND ($whereStr)
        ORDER BY download_count DESC LIMIT 6
    ");
    $stR->execute(array_merge([$userId], $likeArr));
    $related = $stR->fetchAll(PDO::FETCH_ASSOC);
}

function v($arr, $key, $fallback = '—')
{
    return htmlspecialchars($arr[$key] ?? $fallback);
}

// Safe encrypt — never throws on NULL
function safeEncrypt($value): string
{
    if ($value === null || $value === '') return '';
    return encryptId((string)$value);
}

$firstName = $data['first_name'] ?? 'U';
$lastName  = $data['last_name']  ?? '';
$uinitials  = strtoupper(($firstName[0] ?? '') . ($lastName[0] ?? ''));
$fullName  = trim($firstName . ' ' . $lastName);
$subArr    = array_filter(array_map('trim', explode(
    ',',
    $role === 'tutor' ? ($data['subjects_taught'] ?? '') : ($data['subjects_of_interest'] ?? '')
)));

$rawImg = $user['profile_image'] ?? '';
$Avatar = '';
if (!empty($rawImg)) {
    $Avatar = (str_starts_with($rawImg, 'http') || str_starts_with($rawImg, '/'))
        ? htmlspecialchars($rawImg)
        : htmlspecialchars('http://localhost/ScholarSwap/' . ltrim($rawImg, '/'));
}

$BASE = 'http://localhost/ScholarSwap/';
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
    <meta name="theme-color" content="#7A0C0C">

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
    <title><?php echo htmlspecialchars($fullName); ?> | ScholarSwap</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* 🔴 Primary (Logo Maroon) */
            --primary: #7A0C0C;
            --primary-dark: #5a0909;
            --primary-light: #fde8e8;
            --primary-xlight: #fff1f1;

            /* 🟡 Accent (Golden Yellow) */
            --accent: #F2B400;
            --accent-dark: #d49c00;
            --accent-light: #fff4cc;
            --accent-xlight: #fff9e6;

            /* 🟠 Supporting Colors */
            --gold: #b45309;
            --amber: #f59e0b;
            --amber-xlight: #fef3c7;

            /* 🔵 Status Colors (keep for UI usability) */
            --red: #dc2626;
            --green: #059669;
            --green-xlight: #dcfce7;
            --teal: #0d9488;
            --teal-light: #f0fdfa;

            /* ⚪ Backgrounds */
            --surface: #ffffff;
            --page-bg: #fffaf5;
            --section-alt: #fff4e6;

            /* 📝 Text Colors */
            --text: #000000;
            --text2: #767472;
            --text3: #3d3d3c;

            /* 🧱 Borders & Shadows */
            --border: rgba(122, 12, 12, 0.15);
            --shadow-sm: 0 1px 4px rgba(122, 12, 12, 0.08);
            --shadow-md: 0 4px 16px rgba(122, 12, 12, 0.10);
            --shadow-lg: 0 12px 36px rgba(122, 12, 12, 0.15);

            /* 📏 Layout */
            --hdr-h: 64px;
            --page-px: 20px;
            --max-w: 1100px;
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
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        a {
            text-decoration: none;
            color: inherit
        }

        ::-webkit-scrollbar {
            width: 6px
        }

        ::-webkit-scrollbar-track {
            background: #fff4f4
        }

        ::-webkit-scrollbar-thumb {
            background: #e8c0c0;
            border-radius: 6px
        }

        /* ── Background grid ── */
        .page-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(122, 12, 12, .022) 1px, transparent 1px),
                linear-gradient(90deg, rgba(122, 12, 12, .022) 1px, transparent 1px);
            background-size: 52px 52px;
        }

        .page-grid::before,
        .page-grid::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(72px);
            pointer-events: none;
        }

        .page-grid::before {
            width: 480px;
            height: 380px;
            top: -80px;
            left: -80px;
            background: radial-gradient(circle, rgba(122, 12, 12, .07) 0%, transparent 70%);
        }

        .page-grid::after {
            width: 360px;
            height: 320px;
            bottom: -60px;
            right: -60px;
            background: radial-gradient(circle, rgba(242, 180, 0, .07) 0%, transparent 70%);
        }

        /* ── Page wrapper ── */
        .wrap {
            position: relative;
            z-index: 1;
            padding-bottom: 60px;
            overflow-x: hidden;
        }

        /* ── Cover banner ── */
        .cover {
            height: 160px;
            background: linear-gradient(135deg, #fde8e8 0%, #fff4cc 50%, #fff9e6 100%);
            position: relative;
            overflow: hidden;
        }

        .cover::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(122, 12, 12, .04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(122, 12, 12, .04) 1px, transparent 1px);
            background-size: 32px 32px;
        }

        .cover-orb1 {
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            filter: blur(80px);
            background: rgba(122, 12, 12, .12);
            top: -120px;
            right: 8%;
            pointer-events: none;
        }

        .cover-orb2 {
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            filter: blur(60px);
            background: rgba(242, 180, 0, .15);
            bottom: -90px;
            left: 12%;
            pointer-events: none;
        }

        /* ── Profile card ── */
        .profile-card-wrap {
            max-width: var(--max-w);
            margin: 0 auto;
            padding: 0 var(--page-px);
        }

        .profile-top {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 20px 24px 20px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* Avatar */
        .avatar-wrap {
            position: relative;
            flex-shrink: 0;
            margin-top: -46px;
        }

        .avatar {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: var(--shadow-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            overflow: hidden;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover
        }

        .verified-badge {
            position: absolute;
            bottom: 4px;
            right: 2px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--green);
            border: 2px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .52rem;
            color: #fff;
        }

        /* Name block */
        .profile-name-block {
            flex: 1;
            min-width: 0;
            padding-top: 2px;
        }

        .profile-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-username {
            font-size: .8rem;
            color: var(--text3);
            margin-top: 2px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 7px;
            padding: 3px 11px;
            border-radius: 99px;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .rb-student {
            background: var(--primary-xlight);
            color: var(--primary);
            border: 1px solid rgba(122, 12, 12, 0.2);
        }

        .rb-tutor {
            background: var(--teal-light);
            color: var(--teal);
            border: 1px solid rgba(13, 148, 136, .2);
        }

        /* Stats row */
        .profile-stats {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            flex-shrink: 0;
        }

        .stat-box {
            text-align: center;
            min-width: 52px;
        }

        .stat-num {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .stat-lbl {
            font-size: .65rem;
            color: var(--text3);
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .stat-divider {
            width: 1px;
            height: 32px;
            background: var(--border);
            flex-shrink: 0;
        }

        /* Follow button */
        .btn-follow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: .83rem;
            font-weight: 700;
            transition: all .2s;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .btn-follow.follow {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 14px rgba(122, 12, 12, .28);
        }

        .btn-follow.follow:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(122, 12, 12, .4);
        }

        .btn-follow.following {
            background: var(--page-bg);
            color: var(--text2);
            border: 1.5px solid var(--border);
        }

        .btn-follow.following:hover {
            background: rgba(220, 38, 38, .07);
            color: var(--red);
            border-color: rgba(220, 38, 38, .2);
        }

        /* ── Body grid ── */
        .body-grid {
            max-width: var(--max-w);
            margin: 18px auto 0;
            padding: 0 var(--page-px);
            display: grid;
            grid-template-columns: minmax(0, 1fr) 270px;
            gap: 18px;
            align-items: start;
        }

        @media(max-width:940px) {
            .body-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── Info cards ── */
        .info-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }

        .info-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 7px 0;
            border-bottom: 1px solid rgba(122, 12, 12, .04);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: .76rem;
            color: var(--text3);
            flex-shrink: 0;
            margin-right: 12px;
        }

        .info-val {
            font-size: .82rem;
            color: var(--text2);
            text-align: right;
            word-break: break-all;
        }

        /* Subject tags */
        .tag-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .tag {
            font-size: .7rem;
            padding: 3px 10px;
            border-radius: 99px;
            background: var(--accent-xlight);
            border: 1px solid rgba(242, 180, 0, .35);
            color: var(--accent-dark);
        }

        /* ── Uploads card ── */
        .uploads-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .uploads-head {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .uploads-head h3 {
            font-size: .92rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .count-badge {
            font-size: .65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 99px;
            background: var(--primary-xlight);
            color: var(--primary);
            border: 1px solid rgba(122, 12, 12, .2);
        }

        .upload-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            border-bottom: 1px solid rgba(122, 12, 12, .04);
            cursor: pointer;
            transition: background .18s;
            min-width: 0;
        }

        .upload-item:last-child {
            border-bottom: none;
        }

        .upload-item:hover {
            background: var(--primary-xlight);
        }

        .ui-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
        }

        .ui-note {
            background: var(--primary-xlight);
            color: var(--primary);
        }

        .ui-book {
            background: var(--green-xlight);
            color: var(--green);
        }

        .ui-info {
            flex: 1;
            min-width: 0;
        }

        .ui-title {
            font-size: .85rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ui-meta {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 3px;
            flex-wrap: wrap;
        }

        .ui-meta span {
            font-size: .68rem;
            color: var(--text3);
        }

        .ui-type {
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 2px 6px;
            border-radius: 99px;
            background: var(--accent-xlight);
            color: var(--accent-dark);
            border: 1px solid rgba(242, 180, 0, .3);
        }

        .btn-view-sm {
            flex-shrink: 0;
            padding: 5px 12px;
            border-radius: 8px;
            background: var(--primary-xlight);
            color: var(--primary);
            border: 1px solid rgba(122, 12, 12, .2);
            font-size: .7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .18s;
            white-space: nowrap;
        }

        .btn-view-sm:hover {
            background: var(--primary);
            color: #fff;
        }

        .empty-uploads {
            padding: 36px 18px;
            text-align: center;
            color: var(--text3);
            font-size: .82rem;
        }

        .empty-uploads i {
            font-size: 1.4rem;
            margin-bottom: 10px;
            display: block;
            color: var(--primary-light);
        }

        /* ── Right sidebar: Related ── */
        .related-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: calc(var(--hdr-h) + 14px);
            min-width: 0;
            width: 100%;
        }

        .related-head {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border);
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text3);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .related-head i {
            color: var(--amber);
        }

        .rel-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 14px;
            border-bottom: 1px solid rgba(122, 12, 12, .04);
            cursor: pointer;
            transition: background .18s;
            text-decoration: none;
            min-width: 0;
        }

        .rel-item:last-child {
            border-bottom: none;
        }

        .rel-item:hover {
            background: var(--primary-xlight);
        }

        .rel-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            flex-shrink: 0;
            background: var(--primary-xlight);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
        }

        .rel-info {
            flex: 1;
            min-width: 0;
        }

        .rel-title {
            font-size: .78rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rel-sub {
            font-size: .66rem;
            color: var(--text3);
            margin-top: 2px;
        }

        .rel-empty {
            padding: 22px 14px;
            text-align: center;
            font-size: .76rem;
            color: var(--text3);
        }

        /* ── Responsive tweaks ── */
        @media(max-width:640px) {
            .cover {
                height: 120px;
            }

            .avatar-wrap {
                margin-top: -36px;
            }

            .avatar {
                width: 76px;
                height: 76px;
                font-size: 1.4rem;
            }

            .profile-top {
                padding: 14px 16px 16px;
                gap: 14px;
            }

            .profile-name {
                font-size: 1.1rem;
            }

            .profile-stats {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
            }

            .btn-follow {
                width: 100%;
                justify-content: center;
                margin-top: 4px;
            }

            .stat-divider {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="page-grid"></div>
    <div class="wrap">
        <?php include_once('../files/header.php'); ?>

        <div class="cover">
            <div class="cover-orb1"></div>
            <div class="cover-orb2"></div>
        </div>

        <!-- Profile card -->
        <div class="profile-card-wrap">
            <div class="profile-top">

                <div class="avatar-wrap">
                    <div class="avatar">
                        <?php if ($Avatar): ?>
                            <img src="<?php echo $Avatar; ?>" alt="<?php echo htmlspecialchars($fullName); ?>"
                                onerror="this.parentElement.innerHTML='<?php echo $uinitials; ?>'">
                        <?php else: ?>
                            <?php echo $uinitials; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($user['is_verified'] ?? 0): ?>
                        <div class="verified-badge" title="Verified"><i class="fas fa-check"></i></div>
                    <?php endif; ?>
                </div>

                <div class="profile-name-block">
                    <div class="profile-name"><?php echo htmlspecialchars($fullName); ?></div>
                    <div class="profile-username">@<?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                    <span class="role-badge <?php echo $role === 'tutor' ? 'rb-tutor' : 'rb-student'; ?>">
                        <i class="fas <?php echo $role === 'tutor' ? 'fa-chalkboard-user' : 'fa-graduation-cap'; ?>"></i>
                        <?php echo ucfirst($role); ?>
                    </span>
                </div>

                <div class="profile-stats">
                    <div class="stat-box">
                        <div class="stat-num" id="followers-count"><?php echo $followers; ?></div>
                        <div class="stat-lbl">Followers</div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $following; ?></div>
                        <div class="stat-lbl">Following</div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $noteCount; ?></div>
                        <div class="stat-lbl">Notes</div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $bookCount; ?></div>
                        <div class="stat-lbl">Books</div>
                    </div>
                    <?php if ($userId !== $myId): ?>
                        <button class="btn-follow <?php echo $isFollowing ? 'following' : 'follow'; ?>" id="followBtn">
                            <?php if ($isFollowing): ?>
                                <i class="fas fa-check"></i> Following
                            <?php else: ?>
                                <i class="fas fa-plus"></i> Follow
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                </div>

            </div>
        </div><!-- /profile-card-wrap -->

        <!-- Body -->
        <div class="body-grid">

            <!-- Left column -->
            <div>
                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-user"></i> Personal Information</div>
                    <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?php echo v($user, 'email'); ?></span></div>
                    <div class="info-row"><span class="info-label">Phone</span><span class="info-val"><?php echo v($user, 'phone'); ?></span></div>
                    <div class="info-row"><span class="info-label">Gender</span><span class="info-val"><?php echo ucfirst(v($data, 'gender')); ?></span></div>
                    <div class="info-row"><span class="info-label">State</span><span class="info-val"><?php echo v($data, 'state'); ?></span></div>
                    <div class="info-row"><span class="info-label">District</span><span class="info-val"><?php echo v($data, 'district'); ?></span></div>
                </div>

                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-graduation-cap"></i> Academic Details</div>
                    <div class="info-row"><span class="info-label">Institution</span><span class="info-val"><?php echo v($data, 'institution'); ?></span></div>
                    <?php if ($role === 'student'): ?>
                        <div class="info-row"><span class="info-label">Course</span><span class="info-val"><?php echo v($data, 'course'); ?></span></div>
                    <?php else: ?>
                        <div class="info-row"><span class="info-label">Qualification</span><span class="info-val"><?php echo v($data, 'qualification'); ?></span></div>
                        <div class="info-row"><span class="info-label">Experience</span><span class="info-val"><?php echo v($data, 'experience_years', '0'); ?> years</span></div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <div class="info-card-title">
                        <i class="fas fa-book-open"></i>
                        <?php echo $role === 'tutor' ? 'Subjects Taught' : 'Subjects of Interest'; ?>
                    </div>
                    <?php if ($subArr): ?>
                        <div class="tag-wrap">
                            <?php foreach ($subArr as $s): ?>
                                <span class="tag"><?php echo htmlspecialchars($s); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:.8rem;color:var(--text3)">No subjects listed.</p>
                    <?php endif; ?>
                    <?php if (!empty($data['bio'])): ?>
                        <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
                            <div style="font-size:.68rem;color:var(--text3);margin-bottom:5px;text-transform:uppercase;letter-spacing:.08em">Bio</div>
                            <p style="font-size:.82rem;color:var(--text2);line-height:1.65"><?php echo htmlspecialchars($data['bio']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Uploads -->
                <div class="uploads-card">
                    <div class="uploads-head">
                        <h3>
                            <i class="fas fa-cloud-arrow-up" style="color:var(--primary)"></i>
                            Uploads
                            <span class="count-badge"><?php echo $uploadCount; ?></span>
                        </h3>
                    </div>
                    <?php if (empty($uploads)): ?>
                        <div class="empty-uploads"><i class="fas fa-cloud-arrow-up"></i>
                            <p>No uploads yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($uploads as $u):
                            if (empty($u['item_id']) || empty($u['user_id'])) continue;
                            $isBook  = $u['type'] === 'book';
                            $iconCls = $isBook ? 'ui-book' : 'ui-note';
                            $icon    = $isBook ? 'fa-book' : 'fa-file-lines';
                            $eItemId  = safeEncrypt($u['item_id']);
                            $eOwnerId = safeEncrypt($u['user_id']);
                            $eType    = safeEncrypt($u['type']);
                            if (!$eItemId || !$eOwnerId || !$eType) continue;
                            $readUrl  = $BASE . 'notes_reader.php?r=' . urlencode($eItemId)
                                . '&u=' . urlencode($eOwnerId)
                                . '&t=' . urlencode($eType);
                        ?>
                            <div class="upload-item" onclick="location.href='<?php echo $readUrl; ?>'">
                                <div class="ui-icon <?php echo $iconCls; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                                <div class="ui-info">
                                    <div class="ui-title"><?php echo htmlspecialchars($u['title']); ?></div>
                                    <div class="ui-meta">
                                        <?php if (!empty($u['subject'])): ?><span><?php echo htmlspecialchars($u['subject']); ?></span><?php endif; ?>
                                        <span><?php echo date('d M Y', strtotime($u['created_at'])); ?></span>
                                        <span class="ui-type"><?php echo htmlspecialchars(str_replace('_', ' ', $u['document_type'] ?? $u['type'])); ?></span>
                                    </div>
                                </div>
                                <button class="btn-view-sm" onclick="event.stopPropagation();location.href='<?php echo $readUrl; ?>'">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right column: Related -->
            <div>
                <div class="related-card">
                    <div class="related-head"><i class="fas fa-star"></i> Related Resources</div>
                    <?php if (empty($related)): ?>
                        <div class="rel-empty">No related resources found.</div>
                    <?php else: ?>
                        <?php foreach ($related as $rel):
                            if (empty($rel['n_code']) || empty($rel['user_id'])) continue;
                            $eRelId    = safeEncrypt($rel['n_code']);
                            $eRelOwner = safeEncrypt($rel['user_id']);
                            $eRelType  = safeEncrypt('note');
                            if (!$eRelId || !$eRelOwner) continue;
                            $relUrl = $BASE . 'notes_reader.php?r=' . urlencode($eRelId)
                                . '&u=' . urlencode($eRelOwner)
                                . '&t=' . urlencode($eRelType);
                        ?>
                            <a class="rel-item" href="<?php echo $relUrl; ?>">
                                <div class="rel-icon"><i class="fas fa-file-lines"></i></div>
                                <div class="rel-info">
                                    <div class="rel-title"><?php echo htmlspecialchars($rel['title']); ?></div>
                                    <div class="rel-sub">
                                        <?php echo htmlspecialchars($rel['subject']); ?>
                                        &nbsp;·&nbsp;
                                        <i class="fas fa-download" style="font-size:.6rem"></i>
                                        <?php echo number_format($rel['download_count']); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /body-grid -->
    </div><!-- /wrap -->

    <?php include_once('../files/footer.php'); ?>

    <script>
        const followBtn = document.getElementById('followBtn');
        const targetId = <?php echo (int)$userId; ?>;

        if (followBtn) {
            followBtn.addEventListener('click', () => {
                followBtn.disabled = true;
                fetch('auth/follow_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'following_id=' + targetId
                    })
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('followers-count').textContent = data.followers;
                        if (data.status === 'followed') {
                            followBtn.className = 'btn-follow following';
                            followBtn.innerHTML = '<i class="fas fa-check"></i> Following';
                        } else {
                            followBtn.className = 'btn-follow follow';
                            followBtn.innerHTML = '<i class="fas fa-plus"></i> Follow';
                        }
                    })
                    .catch(() => {})
                    .finally(() => {
                        followBtn.disabled = false;
                    });
            });
        }
    </script>
</body>

</html>