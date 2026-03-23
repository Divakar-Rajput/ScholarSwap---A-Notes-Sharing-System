<?php
require_once "../config/connection.php";
require_once('../auth_check.php');
require_once "../encryption.php";

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header("Location: login.html");
    exit;
}

$role = $user['role'];

if ($role === 'tutor') {
    $stmt = $conn->prepare("SELECT users.*, tutors.* FROM users LEFT JOIN tutors ON users.user_id = tutors.user_id WHERE users.user_id = :id");
} else {
    $stmt = $conn->prepare("SELECT users.*, students.* FROM users LEFT JOIN students ON users.user_id = students.user_id WHERE users.user_id = :id");
}
$stmt->execute([':id' => $user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$cNotes = $conn->prepare("SELECT COUNT(*) FROM notes WHERE user_id = :id");
$cNotes->execute([':id' => $user_id]);
$noteCount = (int)$cNotes->fetchColumn();

$cBooks = $conn->prepare("SELECT COUNT(*) FROM books WHERE user_id = :id");
$cBooks->execute([':id' => $user_id]);
$bookCount = (int)$cBooks->fetchColumn();
$totalUploads = $noteCount + $bookCount;

$stFollowers = $conn->prepare("SELECT COUNT(*) FROM follows WHERE following_id = :id");
$stFollowers->execute([':id' => $user_id]);
$followers = (int)$stFollowers->fetchColumn();

$stFollowing = $conn->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = :id");
$stFollowing->execute([':id' => $user_id]);
$following = (int)$stFollowing->fetchColumn();

$stUploads = $conn->prepare("
    SELECT 'note' AS type, n_code AS item_id, title, subject,
           approval_status, created_at, user_id, document_type
    FROM notes WHERE user_id = :n
    UNION ALL
    SELECT 'book' AS type, b_code AS item_id, title, subject,
           approval_status, created_at, user_id, document_type
    FROM books WHERE user_id = :b
    ORDER BY created_at DESC
");
$stUploads->execute([':n' => $user_id, ':b' => $user_id]);
$uploads = $stUploads->fetchAll(PDO::FETCH_ASSOC);

/* ── Bookmarks — fetch user_id AND admin_id so admin-uploaded items work ── */
$stBookmarks = $conn->prepare("
    SELECT bm.bookmark_id, bm.user_id, bm.document_type, bm.document_id, bm.created_at,
           COALESCE(n.title,   b.title)   AS title,
           COALESCE(n.subject, b.subject) AS subject,
           COALESCE(n.user_id, b.user_id) AS owner_user_id,
           COALESCE(n.admin_id, b.admin_id) AS owner_admin_id,
           COALESCE(n.n_code, b.b_code)   AS item_code
    FROM bookmarks bm
    LEFT JOIN notes n ON bm.document_type IN ('note','notes') AND n.n_code = bm.document_id
    LEFT JOIN books b ON bm.document_type IN ('book','books') AND b.b_code = bm.document_id
    WHERE bm.user_id = :id
    ORDER BY bm.created_at DESC
");
$stBookmarks->execute([':id' => $user_id]);
$bookmarks = $stBookmarks->fetchAll(PDO::FETCH_ASSOC);
$bookmarkCount = count($bookmarks);

/* ── Downloads — same fix ── */
$stDownloads = $conn->prepare("
    SELECT dl.download_id, dl.user_id, dl.document_type, dl.document_id, dl.created_at,
           COALESCE(n.title,   b.title)   AS title,
           COALESCE(n.subject, b.subject) AS subject,
           COALESCE(n.user_id, b.user_id) AS owner_user_id,
           COALESCE(n.admin_id, b.admin_id) AS owner_admin_id,
           COALESCE(n.n_code, b.b_code)   AS item_code
    FROM downloads dl
    LEFT JOIN notes n ON dl.document_type IN ('note','notes') AND n.n_code = dl.document_id
    LEFT JOIN books b ON dl.document_type IN ('book','books') AND b.b_code = dl.document_id
    WHERE dl.user_id = :id
    ORDER BY dl.created_at DESC
");
$stDownloads->execute([':id' => $user_id]);
$downloads = $stDownloads->fetchAll(PDO::FETCH_ASSOC);
$downloadCount = count($downloads);

/* ── Material Requests ── */
$stRequests = $conn->prepare("
    SELECT request_id, ref_code, tracking_number, material_type,
           title, priority, status, admin_note, created_at, resource_link
    FROM material_requests
    WHERE user_id = :id
    ORDER BY created_at DESC
");
$stRequests->execute([':id' => $user_id]);
$myRequests   = $stRequests->fetchAll(PDO::FETCH_ASSOC);
$requestCount = count($myRequests);

// ── Helpers ──────────────────────────────────────────────────────

function v($d, $k, $fb = '—')
{
    return htmlspecialchars($d[$k] ?? $fb);
}

function normaliseDocType(string $raw): string
{
    $t = strtolower(trim($raw));
    if (str_starts_with($t, 'book'))      return 'book';
    if (str_starts_with($t, 'note'))      return 'note';
    if (str_starts_with($t, 'newspaper')) return 'newspaper';
    return $t;
}

function safeEncrypt($value): string
{
    if ($value === null || $value === '') return '';
    return encryptId((string)$value);
}

/**
 * Build reader URL.
 * For admin-uploaded items user_id is NULL — use admin_id as the owner token instead.
 * The notes_reader page already handles this case.
 */
function buildReaderUrl(string $baseUrl, $itemId, $ownerUserId, $ownerAdminId, string $docType): string
{
    if ($itemId === null || $itemId === '') return '';

    /* Determine which ID to use as the owner token:
       - Regular upload: user_id is set → encrypt user_id
       - Admin upload:   user_id is NULL, admin_id is set → encrypt admin_id */
    $ownerId = (!empty($ownerUserId)) ? $ownerUserId : $ownerAdminId;

    if (empty($ownerId)) return '';

    $eItem  = safeEncrypt($itemId);
    $eOwner = safeEncrypt($ownerId);
    $eType  = safeEncrypt(normaliseDocType($docType));

    if ($eItem === '' || $eOwner === '' || $eType === '') return '';

    return $baseUrl . 'notes_reader'
        . '?r=' . urlencode($eItem)
        . '&u=' . urlencode($eOwner)
        . '&t=' . urlencode($eType);
}

$initials = strtoupper(substr($data['first_name'] ?? 'U', 0, 1) . substr($data['last_name'] ?? '', 0, 1));

$rawImg = $data['profile_image'] ?? '';
$profileImg = '';
if (!empty($rawImg)) {
    $profileImg = (str_starts_with($rawImg, 'http') || str_starts_with($rawImg, '/'))
        ? htmlspecialchars($rawImg)
        : htmlspecialchars('http://localhost/ScholarSwap/' . ltrim($rawImg, '/'));
}

$BASE_URL = 'http://localhost/ScholarSwap/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ScholarSwap is a collaborative academic platform where students exchange notes, textbooks, and study resources.">
    <meta name="theme-color" content="#7A0C0C">
    <title>My Profile | ScholarSwap</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../assets/css/index.css">
    <style>
        :root {
            --primary: #7A0C0C;
            --primary-dark: #5a0909;
            --primary-light: #fde8e8;
            --primary-xlight: #fff1f1;
            --accent: #F2B400;
            --accent-dark: #d49c00;
            --accent-light: #fff4cc;
            --accent-xlight: #fff9e6;
            --gold: #b45309;
            --amber: #f59e0b;
            --amber-xlight: #fef3c7;
            --red: #dc2626;
            --green: #059669;
            --green-xlight: #dcfce7;
            --teal: #0d9488;
            --teal-light: #f0fdfa;
            --surface: #ffffff;
            --page-bg: #fffaf5;
            --section-alt: #fff4e6;
            --text: #000000;
            --text2: #767472;
            --text3: #3d3d3c;
            --border: rgba(122, 12, 12, 0.15);
            --shadow-sm: 0 1px 4px rgba(122, 12, 12, 0.08);
            --shadow-md: 0 4px 16px rgba(122, 12, 12, 0.10);
            --shadow-lg: 0 12px 36px rgba(122, 12, 12, 0.15);
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
            background: #fff4f4
        }

        ::-webkit-scrollbar-thumb {
            background: #e8c0c0;
            border-radius: 6px
        }

        .page-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: linear-gradient(rgba(122, 12, 12, .022) 1px, transparent 1px), linear-gradient(90deg, rgba(122, 12, 12, .022) 1px, transparent 1px);
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
            background: radial-gradient(circle, rgba(122, 12, 12, .07) 0%, transparent 70%)
        }

        .page-grid::after {
            width: 360px;
            height: 320px;
            bottom: -60px;
            right: -60px;
            background: radial-gradient(circle, rgba(242, 180, 0, .07) 0%, transparent 70%)
        }

        .content {
            position: relative;
            z-index: 1
        }

        .wrap {
            max-width: 1120px;
            margin: 0 auto;
            padding: calc(var(--hdr-h) + 20px) 20px 60px
        }

        /* ── Cover & Profile card ── */
        .cover-banner {
            height: 190px;
            border-radius: 20px 20px 0 0;
            background: linear-gradient(135deg, #fde8e8 0%, #fff4cc 50%, #fff9e6 100%);
            position: relative;
            overflow: hidden
        }

        .cover-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(122, 12, 12, .04) 1px, transparent 1px), linear-gradient(90deg, rgba(122, 12, 12, .04) 1px, transparent 1px);
            background-size: 32px 32px
        }

        .cover-banner::after {
            content: '';
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: rgba(122, 12, 12, .10);
            filter: blur(80px);
            top: -100px;
            right: 8%;
            pointer-events: none
        }

        .profile-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 0 0 20px 20px;
            padding: 0 32px 28px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px
        }

        .profile-top {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px
        }

        .avatar-wrap {
            margin-top: -52px;
            position: relative;
            flex-shrink: 0
        }

        .avatar {
            width: 104px;
            height: 104px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            color: #fff;
            font-size: 2rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
            box-shadow: var(--shadow-md);
            overflow: hidden
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
            font-size: .55rem;
            color: #fff
        }

        .profile-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            padding-top: 16px
        }

        .btn-edit {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            color: #fff;
            font-size: .84rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all .2s;
            box-shadow: 0 4px 14px rgba(122, 12, 12, .25);
            text-decoration: none
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(122, 12, 12, .35)
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 18px;
            border-radius: 10px;
            background: rgba(220, 38, 38, .07);
            border: 1.5px solid rgba(220, 38, 38, .18);
            color: var(--red);
            font-size: .84rem;
            font-weight: 500;
            cursor: pointer;
            transition: all .18s;
            text-decoration: none
        }

        .btn-logout:hover {
            background: rgba(220, 38, 38, .14)
        }

        .profile-details {
            margin-top: 6px
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.1;
            margin-bottom: 5px;
            text-transform: uppercase
        }

        .profile-email {
            font-size: .83rem;
            color: var(--text2);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px
        }

        .profile-location {
            font-size: .81rem;
            color: var(--text3);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
            text-transform: capitalize
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 99px
        }

        .role-student {
            background: var(--accent-light);
            color: var(--primary);
            border: 1px solid rgba(122, 12, 12, .25)
        }

        .role-tutor {
            background: var(--teal-light);
            color: var(--teal);
            border: 1px solid rgba(13, 148, 136, .2)
        }

        .stats-row {
            display: flex;
            gap: 0;
            margin-top: 18px;
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            background: var(--page-bg)
        }

        .stat-box {
            flex: 1;
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid var(--border)
        }

        .stat-box:last-child {
            border-right: none
        }

        .stat-num {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1
        }

        .stat-lbl {
            font-size: .7rem;
            color: var(--text3);
            margin-top: 3px
        }

        /* ── Layout ── */
        .body-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px
        }

        @media(max-width:900px) {
            .body-grid {
                grid-template-columns: 1fr
            }
        }

        /* ── Info cards ── */
        .info-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px
        }

        .info-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .84rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border)
        }

        .info-card-title i {
            color: var(--primary);
            font-size: .78rem
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: .82rem;
            margin-bottom: 11px;
            padding-bottom: 11px;
            border-bottom: 1px solid rgba(122, 12, 12, .04)
        }

        .info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none
        }

        .info-label {
            color: var(--text3);
            min-width: 90px;
            flex-shrink: 0;
            font-size: .77rem
        }

        .info-val {
            color: var(--text);
            font-weight: 500;
            flex: 1;
            text-transform: capitalize;
            word-break: break-word
        }

        .tag-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px
        }

        .tag {
            font-size: .7rem;
            font-weight: 600;
            padding: 4px 11px;
            border-radius: 99px;
            background: var(--accent-xlight);
            color: var(--accent-dark);
            border: 1px solid rgba(242, 180, 0, .35)
        }

        /* ── Tabs card ── */
        .tabs-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-sm)
        }

        .tabs-nav {
            display: flex;
            border-bottom: 1px solid var(--border);
            background: var(--page-bg);
            overflow-x: auto
        }

        .tab-btn {
            padding: 14px 18px;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--text2);
            font-size: .82rem;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all .18s
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: #fff
        }

        .tab-btn:hover {
            color: var(--primary);
            background: #fff
        }

        .tab-count {
            font-size: .65rem;
            padding: 2px 7px;
            border-radius: 99px;
            margin-left: 5px;
            background: rgba(122, 12, 12, .06);
            color: var(--text3)
        }

        .tab-btn.active .tab-count {
            background: var(--primary-xlight);
            color: var(--primary)
        }

        .tab-body {
            display: none;
            padding: 20px
        }

        .tab-body.active {
            display: block
        }

        /* ── Upload item ── */
        .upload-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            border-radius: 12px;
            background: var(--page-bg);
            border: 1px solid var(--border);
            margin-bottom: 10px;
            transition: all .18s;
            cursor: pointer
        }

        .upload-item:last-child {
            margin-bottom: 0
        }

        .upload-item:hover {
            background: var(--primary-xlight);
            border-color: rgba(122, 12, 12, .2);
            transform: translateX(3px)
        }

        .upload-item.no-link {
            cursor: default;
            opacity: .7
        }

        .upload-item.no-link:hover {
            transform: none;
            background: var(--page-bg);
            border-color: var(--border)
        }

        .upload-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem
        }

        .ui-note {
            background: var(--primary-xlight);
            color: var(--primary)
        }

        .ui-book {
            background: var(--teal-light);
            color: var(--teal)
        }

        .ui-req {
            background: var(--amber-xlight);
            color: var(--gold)
        }

        .upload-info {
            flex: 1;
            min-width: 0
        }

        .upload-title {
            font-size: .88rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px
        }

        .upload-meta {
            font-size: .73rem;
            color: var(--text3);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap
        }

        .upload-badge {
            font-size: .62rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 99px
        }

        .badge-approved {
            background: var(--green-xlight);
            color: var(--green);
            border: 1px solid rgba(5, 150, 105, .2)
        }

        .badge-pending {
            background: var(--amber-xlight);
            color: var(--gold);
            border: 1px solid rgba(180, 83, 9, .2)
        }

        .badge-rejected {
            background: rgba(220, 38, 38, .08);
            color: var(--red);
            border: 1px solid rgba(220, 38, 38, .2)
        }

        .badge-progress {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(122, 12, 12, .2)
        }

        .badge-fulfilled {
            background: var(--green-xlight);
            color: var(--green);
            border: 1px solid rgba(5, 150, 105, .2)
        }

        .badge-cancelled {
            background: rgba(100, 100, 100, .07);
            color: #666;
            border: 1px solid rgba(0, 0, 0, .1)
        }

        .upload-actions {
            display: flex;
            gap: 7px;
            flex-shrink: 0
        }

        .btn-view {
            padding: 7px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            background: var(--primary-xlight);
            color: var(--primary);
            font-size: .76rem;
            font-weight: 600;
            transition: all .15s;
            box-shadow: none
        }

        .btn-view:hover {
            background: var(--primary);
            color: #fff
        }

        .btn-cancel-req {
            padding: 7px 12px;
            border-radius: 8px;
            border: 1px solid rgba(220, 38, 38, .2);
            cursor: pointer;
            background: rgba(220, 38, 38, .06);
            color: var(--red);
            font-size: .76rem;
            font-weight: 600;
            transition: all .15s;
            box-shadow: none
        }

        .btn-cancel-req:hover {
            background: rgba(220, 38, 38, .14);
            border-color: rgba(220, 38, 38, .4)
        }

        .empty-tab {
            text-align: center;
            padding: 40px 20px
        }

        .empty-tab i {
            font-size: 2rem;
            color: var(--primary-light);
            margin-bottom: 12px;
            display: block
        }

        .empty-tab p {
            font-size: .84rem;
            color: var(--text3)
        }

        .empty-tab a {
            color: var(--primary);
            font-weight: 600
        }

        /* ── Tracking code chip ── */
        .trk-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: .06em;
            padding: 2px 9px;
            border-radius: 5px;
            background: var(--page-bg);
            border: 1px solid var(--border);
            color: var(--text2);
            font-variant-numeric: tabular-nums;
            cursor: pointer;
            transition: all .15s;
        }

        .trk-chip:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-xlight)
        }

        /* ── Request detail expand ── */
        .req-detail {
            display: none;
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 9px;
            background: var(--surface);
            border: 1px solid var(--border);
            font-size: .78rem;
            color: var(--text2);
            line-height: 1.6;
        }

        .req-detail.open {
            display: block;
            animation: fadeIn .2s ease
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-4px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .req-detail-row {
            display: flex;
            gap: 8px;
            margin-bottom: 5px
        }

        .req-detail-row:last-child {
            margin-bottom: 0
        }

        .req-detail-lbl {
            color: var(--text3);
            min-width: 80px;
            flex-shrink: 0;
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em
        }

        .req-detail-val {
            color: var(--text);
            font-weight: 500
        }

        .admin-note-box {
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 7px;
            background: var(--amber-xlight);
            border: 1px solid rgba(180, 83, 9, .18);
            font-size: .76rem;
            color: #78350f;
            display: flex;
            gap: 7px
        }
    </style>
</head>

<body>
    <div class="page-grid"></div>
    <div class="content">
        <?php include_once "../files/header.php"; ?>
        <div class="wrap">

            <div class="cover-banner"></div>
            <div class="profile-card">
                <div class="profile-top">
                    <div class="avatar-wrap">
                        <div class="avatar">
                            <?php if ($profileImg): ?>
                                <img src="<?php echo $profileImg; ?>" alt="Profile"
                                    onerror="this.parentElement.innerHTML='<?php echo $initials; ?>'">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($user['is_verified'] ?? 0): ?>
                            <div class="verified-badge" title="Verified"><i class="fas fa-check"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-actions">
                        <a href="edit-profile.php" class="btn-edit"><i class="fas fa-user-pen"></i> Edit Profile</a>
                        <a href="auth/logout.php" class="btn-logout"><i class="fas fa-arrow-right-from-bracket"></i> Log Out</a>
                    </div>
                </div>

                <div class="profile-details">
                    <div class="profile-name"><?php echo v($data, 'first_name') . ' ' . v($data, 'last_name', ''); ?></div>
                    <div class="profile-email"><i class="fas fa-envelope" style="font-size:.7rem;color:var(--text3)"></i><?php echo v($data, 'email'); ?></div>
                    <?php if (!empty($data['current_address'])): ?>
                        <div class="profile-location"><i class="fas fa-location-dot" style="font-size:.7rem"></i><?php echo v($data, 'current_address'); ?></div>
                    <?php endif; ?>
                    <span class="role-badge role-<?php echo $role; ?>">
                        <i class="fas fa-<?php echo $role === 'tutor' ? 'chalkboard-user' : 'graduation-cap'; ?>"></i>
                        <?php echo ucfirst($role); ?>
                    </span>
                </div>

                <div class="stats-row">
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $followers; ?></div>
                        <div class="stat-lbl">Followers</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $following; ?></div>
                        <div class="stat-lbl">Following</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $noteCount; ?></div>
                        <div class="stat-lbl">Notes</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $bookCount; ?></div>
                        <div class="stat-lbl">Books</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $totalUploads; ?></div>
                        <div class="stat-lbl">Uploads</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num"><?php echo $requestCount; ?></div>
                        <div class="stat-lbl">Requests</div>
                    </div>
                </div>
            </div>

            <div class="body-grid">
                <!-- Left: Info cards -->
                <div>
                    <div class="info-card">
                        <div class="info-card-title"><i class="fas fa-user"></i> Personal Information</div>
                        <div class="info-row"><span class="info-label">Username</span><span class="info-val"><?php echo v($data, 'username'); ?></span></div>
                        <div class="info-row"><span class="info-label">Phone</span><span class="info-val"><?php echo v($data, 'phone'); ?></span></div>
                        <div class="info-row"><span class="info-label">Gender</span><span class="info-val"><?php echo ucfirst(v($data, 'gender')); ?></span></div>
                        <div class="info-row"><span class="info-label">Date of Birth</span><span class="info-val"><?php echo v($data, 'dob'); ?></span></div>
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
                        <div class="info-card-title"><i class="fas fa-book-open"></i> <?php echo $role === 'tutor' ? 'Subjects Taught' : 'Subjects of Interest'; ?></div>
                        <?php
                        $subjectsRaw = $role === 'tutor' ? ($data['subjects_taught'] ?? '') : ($data['subjects_of_interest'] ?? '');
                        $subArr = array_filter(array_map('trim', explode(',', $subjectsRaw)));
                        if ($subArr): ?>
                            <div class="tag-wrap"><?php foreach ($subArr as $s): ?><span class="tag"><?php echo htmlspecialchars($s); ?></span><?php endforeach; ?></div>
                        <?php else: echo '<p style="font-size:.82rem;color:var(--text3)">No subjects added yet.</p>';
                        endif; ?>
                        <?php if (!empty($data['bio'])): ?>
                            <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
                                <div style="font-size:.72rem;color:var(--text3);margin-bottom:5px;text-transform:uppercase;letter-spacing:.08em">Bio</div>
                                <p style="font-size:.84rem;color:var(--text2);line-height:1.65"><?php echo htmlspecialchars($data['bio']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Tabs -->
                <div class="tabs-card">
                    <div class="tabs-nav">
                        <button class="tab-btn active" onclick="openTab('uploads',this)">
                            My Uploads <span class="tab-count"><?php echo $totalUploads; ?></span>
                        </button>
                        <button class="tab-btn" onclick="openTab('bookmarks',this)">
                            Bookmarks <span class="tab-count"><?php echo $bookmarkCount; ?></span>
                        </button>
                        <button class="tab-btn" onclick="openTab('downloads',this)">
                            Downloads <span class="tab-count"><?php echo $downloadCount; ?></span>
                        </button>
                        <button class="tab-btn" onclick="openTab('requests',this)">
                            <i class="fas fa-inbox" style="font-size:.72rem;margin-right:4px"></i>Requests
                            <span class="tab-count"><?php echo $requestCount; ?></span>
                        </button>
                    </div>

                    <!-- ── Uploads tab ── -->
                    <div id="uploads" class="tab-body active">
                        <?php if (empty($uploads)): ?>
                            <div class="empty-tab"><i class="fas fa-cloud-arrow-up"></i>
                                <p>You haven't uploaded anything yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($uploads as $u):
                                $isBook  = $u['type'] === 'book';
                                $iconCls = $isBook ? 'ui-book' : 'ui-note';
                                $icon    = $isBook ? 'fa-book' : 'fa-file-lines';
                                $status  = strtolower($u['approval_status'] ?? 'pending');
                                $badgeCls = $status === 'approved' ? 'badge-approved' : ($status === 'rejected' ? 'badge-rejected' : 'badge-pending');
                                $docType = normaliseDocType($u['document_type'] ?? $u['type']);
                                /* Uploads always belong to this user — no admin_id needed */
                                $readUrl = buildReaderUrl($BASE_URL, $u['item_id'], $u['user_id'], null, $docType);
                                $noLink  = $readUrl === '';
                            ?>
                                <div class="upload-item<?php echo $noLink ? ' no-link' : ''; ?>"
                                    <?php if (!$noLink): ?>onclick="window.location.href='<?php echo $readUrl; ?>'" <?php endif; ?>>
                                    <div class="upload-icon <?php echo $iconCls; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                                    <div class="upload-info">
                                        <div class="upload-title"><?php echo htmlspecialchars($u['title']); ?></div>
                                        <div class="upload-meta">
                                            <?php if (!empty($u['subject'])): ?><span><?php echo htmlspecialchars($u['subject']); ?></span><?php endif; ?>
                                            <span><?php echo date('d M Y', strtotime($u['created_at'])); ?></span>
                                            <span class="upload-badge <?php echo $badgeCls; ?>"><?php echo ucfirst($status); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!$noLink): ?>
                                        <div class="upload-actions">
                                            <button class="btn-view" onclick="event.stopPropagation();window.location.href='<?php echo $readUrl; ?>'"><i class="fas fa-eye"></i> View</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- ── Bookmarks tab ── -->
                    <div id="bookmarks" class="tab-body">
                        <?php if (empty($bookmarks)): ?>
                            <div class="empty-tab"><i class="fas fa-bookmark"></i>
                                <p>No bookmarks yet — save resources to find them here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($bookmarks as $bm):
                                $normDt  = normaliseDocType($bm['document_type'] ?? 'note');
                                $isBook  = $normDt === 'book';
                                $iconCls = $isBook ? 'ui-book' : 'ui-note';
                                $icon    = $isBook ? 'fa-book' : 'fa-file-lines';
                                /* Pass both owner_user_id and owner_admin_id — function picks whichever is set */
                                $readUrl = buildReaderUrl($BASE_URL, $bm['document_id'], $bm['owner_user_id'], $bm['owner_admin_id'], $normDt);
                                $noLink  = $readUrl === '';
                            ?>
                                <div class="upload-item<?php echo $noLink ? ' no-link' : ''; ?>"
                                    <?php if (!$noLink): ?>onclick="window.location.href='<?php echo $readUrl; ?>'" <?php endif; ?>>
                                    <div class="upload-icon <?php echo $iconCls; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                                    <div class="upload-info">
                                        <div class="upload-title"><?php echo htmlspecialchars($bm['title'] ?? 'Untitled'); ?></div>
                                        <div class="upload-meta">
                                            <?php if (!empty($bm['subject'])): ?><span><?php echo htmlspecialchars($bm['subject']); ?></span><?php endif; ?>
                                            <span><i class="fas fa-bookmark" style="font-size:.6rem;color:var(--amber)"></i> Saved <?php echo date('d M Y', strtotime($bm['created_at'])); ?></span>
                                            <span class="upload-badge" style="background:var(--amber-xlight);color:var(--gold);border:1px solid rgba(180,83,9,.2)"><?php echo ucfirst($normDt); ?></span>
                                            <?php if ($noLink): ?><span style="color:var(--red);font-size:.66rem"><i class="fas fa-circle-exclamation"></i> Content unavailable</span><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!$noLink): ?>
                                        <div class="upload-actions">
                                            <button class="btn-view" onclick="event.stopPropagation();window.location.href='<?php echo $readUrl; ?>'"><i class="fas fa-eye"></i> View</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- ── Downloads tab ── -->
                    <div id="downloads" class="tab-body">
                        <?php if (empty($downloads)): ?>
                            <div class="empty-tab"><i class="fas fa-download"></i>
                                <p>Your download history will appear here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($downloads as $dl):
                                $normDt  = normaliseDocType($dl['document_type'] ?? 'note');
                                $isBook  = $normDt === 'book';
                                $iconCls = $isBook ? 'ui-book' : 'ui-note';
                                $icon    = $isBook ? 'fa-book' : 'fa-file-lines';
                                /* Pass both owner_user_id and owner_admin_id */
                                $readUrl = buildReaderUrl($BASE_URL, $dl['document_id'], $dl['owner_user_id'], $dl['owner_admin_id'], $normDt);
                                $noLink  = $readUrl === '';
                            ?>
                                <div class="upload-item<?php echo $noLink ? ' no-link' : ''; ?>"
                                    <?php if (!$noLink): ?>onclick="window.location.href='<?php echo $readUrl; ?>'" <?php endif; ?>>
                                    <div class="upload-icon <?php echo $iconCls; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                                    <div class="upload-info">
                                        <div class="upload-title"><?php echo htmlspecialchars($dl['title'] ?? 'Untitled'); ?></div>
                                        <div class="upload-meta">
                                            <?php if (!empty($dl['subject'])): ?><span><?php echo htmlspecialchars($dl['subject']); ?></span><?php endif; ?>
                                            <span><i class="fas fa-download" style="font-size:.6rem;color:var(--green)"></i> Downloaded <?php echo date('d M Y', strtotime($dl['created_at'])); ?></span>
                                            <span class="upload-badge" style="background:var(--green-xlight);color:var(--green);border:1px solid rgba(5,150,105,.2)"><?php echo ucfirst($normDt); ?></span>
                                            <?php if ($noLink): ?><span style="color:var(--red);font-size:.66rem"><i class="fas fa-circle-exclamation"></i> Content unavailable</span><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!$noLink): ?>
                                        <div class="upload-actions">
                                            <button class="btn-view" onclick="event.stopPropagation();window.location.href='<?php echo $readUrl; ?>'"><i class="fas fa-eye"></i> View</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- ── Requests tab ── -->
                    <div id="requests" class="tab-body">
                        <?php if (empty($myRequests)): ?>
                            <div class="empty-tab">
                                <i class="fas fa-inbox"></i>
                                <p>No material requests yet.</p>
                                <p style="margin-top:8px"><a href="<?php echo $BASE_URL; ?>request.php">Submit your first request →</a></p>
                            </div>
                        <?php else: ?>

                            <?php foreach ($myRequests as $idx => $req):
                                $status    = $req['status'] ?? 'Pending';
                                $badgeCls  = match ($status) {
                                    'Fulfilled'      => 'badge-fulfilled',
                                    'In Progress'    => 'badge-progress',
                                    'Cancelled',
                                    'Cannot Fulfil'  => 'badge-cancelled',
                                    default          => 'badge-pending',
                                };
                                $canCancel = in_array($status, ['Pending', 'In Progress']);
                                $priority  = $req['priority'] ?? 'Low';
                                $priColor  = match ($priority) {
                                    'High'   => 'var(--red)',
                                    'Medium' => 'var(--gold)',
                                    default  => 'var(--green)',
                                };
                                $rid = (int)$req['request_id'];
                            ?>
                                <div class="upload-item no-link" id="req-item-<?php echo $rid; ?>">
                                    <div class="upload-icon ui-req"><i class="fas fa-inbox"></i></div>
                                    <div class="upload-info">
                                        <div class="upload-title"><?php echo htmlspecialchars($req['title']); ?></div>
                                        <div class="upload-meta">
                                            <span><?php echo htmlspecialchars($req['material_type']); ?></span>
                                            <span><?php echo date('d M Y', strtotime($req['created_at'])); ?></span>
                                            <span class="upload-badge <?php echo $badgeCls; ?>"><?php echo htmlspecialchars($status); ?></span>
                                            <span style="color:<?php echo $priColor; ?>;font-size:.68rem;font-weight:700">
                                                <i class="fas fa-circle" style="font-size:.42rem;vertical-align:middle"></i>
                                                <?php echo $priority; ?>
                                            </span>
                                        </div>

                                        <!-- Tracking number chip -->
                                        <?php if (!empty($req['tracking_number'])): ?>
                                            <div style="margin-top:5px">
                                                <span class="trk-chip"
                                                    onclick="event.stopPropagation();copyTrk('<?php echo htmlspecialchars($req['tracking_number']); ?>',this)"
                                                    title="Click to copy tracking number">
                                                    <i class="fas fa-radar" style="font-size:.58rem"></i>
                                                    <?php echo htmlspecialchars($req['tracking_number']); ?>
                                                    <i class="fas fa-copy" style="font-size:.56rem;opacity:.6"></i>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Expandable detail -->
                                        <div class="req-detail" id="req-detail-<?php echo $rid; ?>">
                                            <div class="req-detail-row">
                                                <span class="req-detail-lbl">Ref Code</span>
                                                <span class="req-detail-val"><?php echo htmlspecialchars($req['ref_code'] ?? '—'); ?></span>
                                            </div>
                                            <div class="req-detail-row">
                                                <span class="req-detail-lbl">Tracking</span>
                                                <span class="req-detail-val"><?php echo htmlspecialchars($req['tracking_number'] ?? '—'); ?></span>
                                            </div>
                                            <div class="req-detail-row">
                                                <span class="req-detail-lbl">Type</span>
                                                <span class="req-detail-val"><?php echo htmlspecialchars($req['material_type']); ?></span>
                                            </div>
                                            <div class="req-detail-row">
                                                <span class="req-detail-lbl">Priority</span>
                                                <span class="req-detail-val" style="color:<?php echo $priColor; ?>;font-weight:700"><?php echo $priority; ?></span>
                                            </div>
                                            <div class="req-detail-row">
                                                <span class="req-detail-lbl">Submitted</span>
                                                <span class="req-detail-val"><?php echo date('d M Y, h:i A', strtotime($req['created_at'])); ?></span>
                                            </div>
                                            <?php if (!empty($req['admin_note'])): ?>
                                                <div class="admin-note-box">
                                                    <i class="fas fa-comment-dots" style="flex-shrink:0;margin-top:1px"></i>
                                                    <span><strong>Admin note:</strong> <?php echo htmlspecialchars($req['admin_note']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="upload-actions" style="flex-direction:column;gap:5px">
                                        <?php if ($status === 'Fulfilled' && !empty($req['resource_link'])): ?>
                                            <?php
                                            $viewerUrl = $req['resource_link'];
                                            ?>
                                            <a href="<?php echo htmlspecialchars($viewerUrl); ?>"
                                                class="btn-view"
                                                style="text-align:center;text-decoration:none;display:flex;align-items:center;gap:5px;justify-content:center;"
                                                onclick="event.stopPropagation()">
                                                <i class="fas fa-book-open"></i> View Material
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn-view"
                                            onclick="event.stopPropagation();toggleReqDetail(<?php echo $rid; ?>)"
                                            id="req-toggle-<?php echo $rid; ?>">
                                            <i class="fas fa-chevron-down" id="req-ico-<?php echo $rid; ?>"></i> Details
                                        </button>
                                        <?php if ($canCancel): ?>
                                            <button class="btn-cancel-req"
                                                onclick="event.stopPropagation();cancelRequest(<?php echo $rid; ?>)">
                                                <i class="fas fa-xmark"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div style="margin-top:14px;text-align:right">
                                <a href="<?php echo $BASE_URL; ?>request.php"
                                    style="font-size:.78rem;font-weight:600;color:var(--primary)">
                                    <i class="fas fa-plus" style="font-size:.68rem"></i> New request
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                </div><!-- /tabs-card -->
            </div>
        </div>
    </div>

    <?php include_once "../files/footer.php"; ?>

    <script>
        /* ── Tab switcher ── */
        function openTab(name, btn) {
            document.querySelectorAll('.tab-body').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(name).classList.add('active');
            btn.classList.add('active');
        }

        /* ── Request detail expand/collapse ── */
        function toggleReqDetail(rid) {
            var det = document.getElementById('req-detail-' + rid);
            var ico = document.getElementById('req-ico-' + rid);
            var btn = document.getElementById('req-toggle-' + rid);
            var open = det.classList.toggle('open');
            ico.style.transform = open ? 'rotate(180deg)' : '';
            btn.innerHTML = open ?
                '<i class="fas fa-chevron-up" id="req-ico-' + rid + '" style="transform:rotate(180deg)"></i> Details' :
                '<i class="fas fa-chevron-down" id="req-ico-' + rid + '"></i> Details';
        }

        /* ── Copy tracking number ── */
        function copyTrk(code, el) {
            navigator.clipboard.writeText(code).then(function() {
                var orig = el.innerHTML;
                el.innerHTML = '<i class="fas fa-check" style="font-size:.58rem"></i> Copied!';
                el.style.borderColor = 'var(--green)';
                el.style.color = 'var(--green)';
                setTimeout(function() {
                    el.innerHTML = orig;
                    el.style.borderColor = '';
                    el.style.color = '';
                }, 2000);
            }).catch(function() {
                var ta = document.createElement('textarea');
                ta.value = code;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            });
        }

        /* ── Cancel request ── */
        function cancelRequest(rid) {
            Swal.fire({
                title: 'Cancel this request?',
                text: 'This cannot be undone. The request will be marked as Cancelled.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#767472',
                confirmButtonText: '<i class="fas fa-xmark"></i> Yes, cancel it',
                cancelButtonText: 'Keep it',
                reverseButtons: true,
                background: '#fff',
                color: '#000',
            }).then(function(result) {
                if (!result.isConfirmed) return;

                var fd = new FormData();
                fd.append('request_id', rid);

                fetch('auth/cancel_material_request.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(d) {
                        if (d.success) {
                            var item = document.getElementById('req-item-' + rid);
                            if (item) {
                                var badge = item.querySelector('.upload-badge');
                                if (badge) {
                                    badge.className = 'upload-badge badge-cancelled';
                                    badge.textContent = 'Cancelled';
                                }
                                var cancelBtn = item.querySelector('.btn-cancel-req');
                                if (cancelBtn) cancelBtn.remove();
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Request cancelled',
                                timer: 2200,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                background: '#fff',
                                color: '#000',
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Could not cancel',
                                text: d.message || 'Please try again.',
                                confirmButtonColor: '#7A0C0C',
                                background: '#fff',
                                color: '#000',
                            });
                        }
                    })
                    .catch(function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection error',
                            text: 'Please check your connection and try again.',
                            confirmButtonColor: '#7A0C0C',
                            background: '#fff',
                            color: '#000',
                        });
                    });
            });
        }

        /* ── Profile update alerts ── */
        const s = new URLSearchParams(location.search).get('s');
        if (s === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: 'Your profile has been saved successfully.',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                background: '#ffffff',
                color: '#0f172a',
                iconColor: '#059669'
            });
            setTimeout(() => history.replaceState(null, '', 'myprofile.php'), 500);
        } else if (s === 'failed') {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: 'Something went wrong. Please try again.',
                timer: 3000,
                showConfirmButton: false,
                background: '#ffffff',
                color: '#0f172a'
            });
            setTimeout(() => history.replaceState(null, '', 'myprofile.php'), 500);
        }

        /* ── Open requests tab directly if hash is #requests ── */
        if (location.hash === '#requests') {
            var btn = document.querySelector('[onclick*="requests"]');
            if (btn) btn.click();
        }
    </script>
</body>

</html>