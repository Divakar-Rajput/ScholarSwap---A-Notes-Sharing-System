<?php

include_once "admin/config/connection.php";
include_once('admin/encryption.php');
require_once('admin/auth_check.php');

$enoteId = $_GET['r'];
$euserId = $_GET['u'];
$etype   = $_GET['t'];

$noteID = decryptId($_GET['r']);
$userID = decryptId($_GET['u']);
$type   = decryptId($_GET['t']);
$selfId = (int)$_SESSION['user_id'];

/* ── Check if logged-in user is banned ── */
$banCheck = $conn->prepare('SELECT is_active FROM users WHERE user_id = :id');
$banCheck->execute([':id' => $selfId]);
$banRow = $banCheck->fetch(PDO::FETCH_ASSOC);
if ($banRow && (int)$banRow['is_active'] === 0) {
    session_destroy();
    header('location: login.html?s=banned');
    exit();
}

/* ════════════════════════════════════════════════════════
   RESOURCE FETCH — handles admin-uploaded (user_id NULL)
   and user-uploaded (student / tutor) resources
════════════════════════════════════════════════════════ */

$note         = null;
$uploaderRole = 'admin'; // default — overridden below for user uploads

if ($type === 'newspaper') {

    /* ── Newspapers are always admin-owned ── */
    $stmt = $conn->prepare('
        SELECT newspapers.*, admin_user.*,
               admin_user.profile_image AS uploader_img
        FROM   newspapers
        JOIN   admin_user ON newspapers.admin_id = admin_user.admin_id
        WHERE  newspapers.n_code = :id
    ');
    $stmt->execute([':id' => $noteID]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note || $note['approval_status'] === 'banned') {
        header('location: index.php?s=content_banned');
        exit();
    }

    $conn->prepare("UPDATE newspapers SET view_count = view_count + 1 WHERE n_code = :id")
         ->execute([':id' => $noteID]);

} else {

    /* ── Decide which table to query ── */
    $codeCol  = ($type === 'note') ? 'n_code' : 'b_code';
    $table    = ($type === 'note') ? 'notes'  : 'books';

    /* ── Try to load the resource first, then decide uploader type ── */
    $stmt = $conn->prepare("SELECT * FROM {$table} WHERE {$codeCol} = :id");
    $stmt->execute([':id' => $noteID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['approval_status'] === 'banned') {
        header('location: index.php?s=content_banned');
        exit();
    }

    /* ── Was this uploaded by an admin (user_id is NULL)? ── */
    if (empty($row['user_id']) && !empty($row['admin_id'])) {

        /* Admin-uploaded — join with admin_user for uploader name/avatar */
        $stmt2 = $conn->prepare("
            SELECT {$table}.*, admin_user.*,
                   admin_user.profile_image AS uploader_img
            FROM   {$table}
            JOIN   admin_user ON {$table}.admin_id = admin_user.admin_id
            WHERE  {$table}.{$codeCol} = :id
        ");
        $stmt2->execute([':id' => $noteID]);
        $note         = $stmt2->fetch(PDO::FETCH_ASSOC);
        $uploaderRole = 'admin';

    } else {

        /* User-uploaded — figure out student vs tutor */
        $roleStmt = $conn->prepare('SELECT role FROM users WHERE user_id = :id');
        $roleStmt->execute([':id' => $row['user_id']]);
        $roleRow      = $roleStmt->fetch(PDO::FETCH_ASSOC);
        $uploaderRole = $roleRow['role'] ?? 'student';

        if ($uploaderRole === 'tutor') {
            $joinTable = 'tutors';
        } else {
            $joinTable = 'students';
        }

        $stmt2 = $conn->prepare("
            SELECT {$table}.*,
                   {$joinTable}.first_name,
                   {$joinTable}.last_name,
                   users.profile_image AS uploader_img
            FROM   {$table}
            JOIN   {$joinTable} ON {$table}.user_id = {$joinTable}.user_id
            JOIN   users        ON users.user_id    = {$table}.user_id
            WHERE  {$table}.{$codeCol} = :id
        ");
        $stmt2->execute([':id' => $noteID]);
        $note = $stmt2->fetch(PDO::FETCH_ASSOC);
    }

    /* ── Fallback: if joined query returned nothing, use the raw row ── */
    if (!$note) {
        $note = $row;
    }

    /* ── Increment view count ── */
    $conn->prepare("UPDATE {$table} SET view_count = view_count + 1 WHERE {$codeCol} = :id")
         ->execute([':id' => $noteID]);
}

/* ── Logged-in user info ── */
$meQ = $conn->prepare("
    SELECT u.username, u.profile_image, u.role,
           COALESCE(
               NULLIF(CONCAT(s.first_name,' ',s.last_name),' '),
               NULLIF(CONCAT(t.first_name,' ',t.last_name),' '),
               u.username
           ) AS display_name
    FROM   users u
    LEFT JOIN students s ON s.user_id = u.user_id
    LEFT JOIN tutors   t ON t.user_id = u.user_id
    WHERE  u.user_id = ?
    LIMIT  1
");
$meQ->execute([$selfId]);
$me = $meQ->fetch(PDO::FETCH_ASSOC);

/* ── Ratings ── */
$rq = $conn->prepare("SELECT ROUND(AVG(rating),1) AS avg_r, COUNT(*) AS total FROM ratings WHERE resource_id=?");
$rq->execute([$noteID]);
$ratingRow    = $rq->fetch(PDO::FETCH_ASSOC);
$avgRating    = (float)($ratingRow['avg_r']  ?? 0);
$totalRatings = (int)($ratingRow['total']    ?? 0);

$myRQ = $conn->prepare("SELECT rating FROM ratings WHERE resource_id=? AND user_id=? LIMIT 1");
$myRQ->execute([$noteID, $selfId]);
$myRating = (int)($myRQ->fetchColumn() ?: 0);

$barQ = $conn->prepare("SELECT rating, COUNT(*) AS cnt FROM ratings WHERE resource_id=? GROUP BY rating ORDER BY rating DESC");
$barQ->execute([$noteID]);
$barData = $barQ->fetchAll(PDO::FETCH_ASSOC);
$barMap  = array_column($barData, 'cnt', 'rating');

/* ── Check if user already reported this resource ── */
$alreadyReported = false;
try {
    $repChk = $conn->prepare("SELECT report_id FROM reports WHERE reporter_id=? AND resource_id=? AND document_type=? LIMIT 1");
    $repChk->execute([$selfId, $noteID, $type]);
    $alreadyReported = (bool)$repChk->fetchColumn();
} catch (PDOException $e) { /* Table may not exist yet — silently skip */ }

/* ── Check if user already bookmarked this resource ── */
$alreadyBookmarked = false;
try {
    $bmChk = $conn->prepare("SELECT bookmark_id FROM bookmarks WHERE user_id=? AND document_type=? AND document_id=? LIMIT 1");
    $bmChk->execute([$selfId, $type, $noteID]);
    $alreadyBookmarked = (bool)$bmChk->fetchColumn();
} catch (PDOException $e) { /* Table may not exist yet — silently skip */ }

/* ── Comments ── */
$cq = $conn->prepare("
    SELECT c.*,
           u.profile_image AS u_img,
           u.role AS user_role,
           COALESCE(
               NULLIF(CONCAT(s.first_name,' ',s.last_name),' '),
               NULLIF(CONCAT(t.first_name,' ',t.last_name),' '),
               u.username
           ) AS display_name
    FROM   comments c
    JOIN   users    u ON u.user_id = c.user_id
    LEFT JOIN students s ON s.user_id = c.user_id
    LEFT JOIN tutors   t ON t.user_id = c.user_id
    WHERE  c.resource_id=? AND c.document_type=? AND c.is_deleted=0
    ORDER  BY c.created_at ASC
");
$cq->execute([$noteID, $type]);
$allComments = $cq->fetchAll(PDO::FETCH_ASSOC);

$topComments = [];
$repliesMap  = [];
foreach ($allComments as $cm) {
    if ($cm['parent_id']) $repliesMap[$cm['parent_id']][] = $cm;
    else $topComments[] = $cm;
}
$SHOW_INIT = 8;

/* ── Helpers ── */
function timeAgo(string $dt): string
{
    $d = time() - strtotime($dt);
    if ($d < 60)     return 'Just now';
    if ($d < 3600)   return floor($d / 60) . 'm ago';
    if ($d < 86400)  return floor($d / 3600) . 'h ago';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    return date('M j, Y', strtotime($dt));
}

function avatarHtml(string $img, string $name, string $role = 'student', string $size = '40px'): string
{
    $init = strtoupper(mb_substr(trim($name), 0, 1) ?: '?');
    $grad = match (true) {
        str_contains($role, 'tutor') => 'linear-gradient(135deg,#0d9488,#0284c7)',
        str_contains($role, 'admin') => 'linear-gradient(135deg,#7A0C0C,#b91c1c)',
        default                      => 'linear-gradient(135deg,#7A0C0C,#F2B400)',
    };
    $safeGrad = htmlspecialchars($grad, ENT_QUOTES);
    if ($img) {
        $fallback = "<div class='cm-av-init' style='width:{$size};height:{$size};background:{$safeGrad};flex-shrink:0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;color:#fff'>{$init}</div>";
        return "<img src=\"" . htmlspecialchars($img) . "\" class=\"cm-av\" style=\"width:{$size};height:{$size}\" alt=\"" . htmlspecialchars($name) . "\" onerror=\"this.outerHTML='" . addslashes($fallback) . "'\">";
    }
    return "<div class=\"cm-av-init\" style=\"width:{$size};height:{$size};background:{$grad};flex-shrink:0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;color:#fff\">{$init}</div>";
}

/* ── Build uploader display name ── */
if ($uploaderRole === 'admin') {
    // admin_user table uses first_name / last_name directly
    $uploaderName = trim(($note['first_name'] ?? '') . ' ' . ($note['last_name'] ?? '')) ?: 'ScholarSwap Admin';
} else {
    $uploaderName = trim(($note['first_name'] ?? '') . ' ' . ($note['last_name'] ?? '')) ?: 'Unknown';
}
$uploaderImg = $note['uploader_img'] ?? '';

/* ── For the uploader profile link we need their encrypted user_id.
   For admin uploads use the admin_id instead (profile button is hidden anyway). ── */
$eUploaderId = encryptId(!empty($note['user_id']) ? $note['user_id'] : ($note['admin_id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta -->
    <title>ScholarSwap — Share, Swap &amp; Succeed Together</title>
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

    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="manifest" href="assets/site.webmanifest">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        /* ══════════════════════════════════════════
           DESIGN TOKENS — Maroon & Gold Theme
        ══════════════════════════════════════════ */
        :root {
            --primary: #7A0C0C;
            --primary-dark: #5a0909;
            --primary-g: #5a0909;
            --primary-light: #fde8e8;
            --primary-xlight: #fff1f1;
            --accent: #F2B400;
            --accent-d: #d49c00;
            --accent-soft: #fff4cc;
            --accent-xsoft: #fff9e6;
            --gold: #b45309;
            --amber: #f59e0b;
            --amber-xlight: #fef3c7;
            --red: #dc2626;
            --red-s: #fee2e2;
            --green: #059669;
            --green-s: #d1fae5;
            --teal: #0d9488;
            --purple: #7c3aed;
            --purple-s: #ede9fe;
            --surface: #ffffff;
            --paper: #fffaf5;
            --paper2: #fff4e6;
            --ink: #000000;
            --ink-mid: #3d3d3c;
            --ink-light: #767472;
            --border: rgba(122, 12, 12, 0.15);
            --border-dark: rgba(122, 12, 12, 0.28);
            --shadow-xs: 0 1px 4px rgba(122, 12, 12, 0.08);
            --shadow-sm: 0 1px 4px rgba(122, 12, 12, 0.08);
            --shadow-md: 0 4px 16px rgba(122, 12, 12, 0.10);
            --shadow-lg: 0 12px 36px rgba(122, 12, 12, 0.15);
            --r: 12px;
            --r-lg: 18px;
            --r-xl: 24px;
            --hdr-h: 64px;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: var(--paper); color: var(--ink); min-height: 100vh; }
        a { text-decoration: none; color: inherit; }

        .page-wrapper {
            max-width: 1520px;
            margin: 0 auto;
            padding: 20px 20px 48px;
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start;
        }

        .doc-header {
            margin: 16px 20px 0;
            padding: 18px 24px;
            background: var(--surface);
            border-radius: var(--r-lg);
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
            box-shadow: var(--shadow-xs);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .doc-header-icon { width: 44px; height: 44px; background: var(--primary-light); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.2rem; flex-shrink: 0; }
        .doc-header-text h1 { font-size: 1.15rem; font-weight: 700; color: var(--ink); line-height: 1.3; text-transform: capitalize; }
        .doc-header-text p { font-size: 0.82rem; color: var(--ink-light); margin-top: 3px; }
        .doc-type-pill { margin-left: auto; flex-shrink: 0; font-size: 0.7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 4px 12px; border-radius: 99px; background: var(--primary-light); color: var(--primary); border: 1px solid rgba(122,12,12,0.2); }

        .viewer-card { background: var(--surface); border-radius: var(--r-xl); box-shadow: var(--shadow-md); overflow: hidden; border: 2px solid var(--primary); }

        .pdf-toolbar { background: var(--primary); padding: 9px 12px; display: flex; align-items: center; gap: 2px; flex-wrap: wrap; min-height: 52px; }
        .tb-group { display: flex; align-items: center; gap: 1px; padding: 0 5px; border-right: 1px solid rgba(255,255,255,.12); }
        .tb-group:last-child { border-right: none; }
        .tb-btn { background: transparent; border: none; color: #fff; width: 34px; height: 34px; border-radius: 7px; cursor: pointer; font-size: .88rem; display: flex; align-items: center; justify-content: center; transition: all .15s; position: relative; box-shadow: none; }
        .tb-btn:hover { background: rgba(242,180,0,.18); color: var(--accent); }
        .tb-btn:active { transform: scale(.94); }
        .tb-btn.active { background: var(--accent); color: var(--primary-dark); }
        .tb-btn:disabled { opacity: .28; cursor: not-allowed; }
        .tb-btn[data-tip]::after { content: attr(data-tip); position: absolute; bottom: -32px; left: 50%; transform: translateX(-50%); background: rgba(15,17,23,.9); color: #fff; font-size: .68rem; padding: 3px 8px; border-radius: 5px; white-space: nowrap; pointer-events: none; opacity: 0; transition: opacity .18s; z-index: 100; }
        .tb-btn[data-tip]:hover::after { opacity: 1; }
        .page-control { display: flex; align-items: center; gap: 5px; color: rgba(255,255,255,.65); font-size: .82rem; padding: 0 6px; }
        .page-input { width: 42px; height: 30px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); color: #fff; border-radius: 5px; text-align: center; font-size: .82rem; outline: none; }
        .page-input:focus { border-color: var(--accent); }
        .zoom-select { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); color: #fff; border-radius: 5px; padding: 3px 7px; font-size: .78rem; cursor: pointer; outline: none; height: 30px; }
        .zoom-select option { background: var(--primary-dark); color: #fff; }
        .tb-spacer { flex: 1; }

        .canvas-container { background: #ede8e0; min-height: 680px; display: flex; flex-direction: column; align-items: center; overflow: auto; padding: 28px; position: relative; }
        #pdfCanvas { background: #fff; box-shadow: 0 6px 28px rgba(122,12,12,.14); display: block; margin: 0 auto; max-width: 100%; border-radius: 2px; }
        .pdf-loader { position: absolute; inset: 0; background: #ede8e0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; z-index: 10; }
        .loader-ring { width: 42px; height: 42px; border: 3px solid rgba(122,12,12,.18); border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loader-text { color: var(--ink-light); font-size: .82rem; }
        .viewer-card:fullscreen, .viewer-card:-webkit-full-screen { border-radius: 0; height: 100vh; display: flex; flex-direction: column; }
        .viewer-card:fullscreen .canvas-container, .viewer-card:-webkit-full-screen .canvas-container { flex: 1; min-height: 0; }

        .reading-progress { background: var(--paper2); padding: 12px 20px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
        .prog-bar { flex: 1; height: 4px; background: var(--border); border-radius: 99px; overflow: hidden; }
        .prog-fill { height: 100%; background: var(--primary); border-radius: 99px; transition: width .4s ease; }
        .prog-txt { font-size: .75rem; color: var(--ink-light); white-space: nowrap; }

        .sidebar { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 80px; }
        .card { background: var(--surface); border-radius: var(--r); padding: 20px; box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
        .card-title { font-size: .95rem; color: var(--ink); margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-title i { color: var(--primary); margin-right: 6px; }

        .action-grid { display: flex; flex-direction: column; gap: 9px; }
        .btn-primary { background: var(--primary); color: #fff; border: none; padding: 12px 16px; border-radius: 10px; font-size: .875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 9px; text-decoration: none; transition: background .18s, transform .1s; box-shadow: none; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-primary:active { transform: scale(.98); }
        .btn-secondary { background: transparent; color: var(--primary); border: 1.5px solid var(--primary); padding: 10px 16px; border-radius: 10px; font-size: .875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 9px; text-decoration: none; transition: all .18s; box-shadow: none; }
        .btn-secondary:hover { background: var(--primary-light); }
        .btn-ghost { background: transparent; color: var(--ink-mid); border: 1.5px solid var(--border); padding: 10px 16px; border-radius: 10px; font-size: .875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 9px; text-decoration: none; transition: all .18s; width: 100%; box-shadow: none; }
        .btn-ghost:hover { background: var(--paper2); border-color: var(--primary); color: var(--primary); }
        .btn-report { background: transparent; color: var(--ink-light); border: none; padding: 9px 12px; border-radius: 8px; font-size: .82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all .18s; width: 100%; border: 1.5px solid transparent; box-shadow: none; }
        .btn-report:hover { color: #ffffff; background: var(--primary); border-color: var(--primary-dark); }
        .btn-report.reported { color: var(--ink-light); cursor: default; opacity: .55; pointer-events: none; }

        .author-row { display: flex; align-items: center; gap: 12px; }
        .author-av { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border); flex-shrink: 0; }
        .author-av-init { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; font-weight: 700; color: #fff; flex-shrink: 0; }
        .author-name { font-weight: 700; font-size: .95rem; text-transform: uppercase; }
        .author-role-badge { font-size: .65rem; font-weight: 700; padding: 2px 8px; border-radius: 99px; text-transform: uppercase; letter-spacing: .05em; display: inline-block; margin-top: 4px; }
        .author-role-badge.student { background: var(--primary-light); color: var(--primary); }
        .author-role-badge.tutor { background: var(--green-s); color: var(--green); }
        .author-role-badge.admin { background: var(--purple-s); color: var(--purple); }
        .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 6px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); }
        .stat { text-align: center; }
        .stat-val { font-size: 1.25rem; font-weight: 700; color: var(--primary); }
        .stat-lbl { font-size: .68rem; color: var(--ink-light); margin-top: 2px; }
        .profile-btn { width: 100%; margin-top: 12px; padding: 9px; background: var(--paper); border: 1px solid var(--primary); border-radius: 8px; color: var(--primary); font-weight: 600; font-size: .82rem; cursor: pointer; transition: all .18s; box-shadow: none; }
        .profile-btn:hover { background: var(--primary); color: #ffffff; }

        .detail-list { display: flex; flex-direction: column; gap: 9px; }
        .detail-item { display: flex; justify-content: space-between; align-items: center; font-size: .855rem; }
        .detail-item .lbl { color: var(--ink-light); }
        .detail-item .val { font-weight: 600; color: var(--ink); }
        .stars { color: var(--accent); font-size: .78rem; }

        .rating-card { background: linear-gradient(135deg,#fffbf0,var(--amber-xlight)); border-color: rgba(242,180,0,0.35); }
        .rating-card .card-title { border-bottom-color: rgba(242,180,0,0.35); }
        .rating-display { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(242,180,0,0.35); }
        .rating-number { font-size: 2rem; font-weight: 700; color: var(--primary); line-height: 1; }
        .rating-stars { color: var(--accent); font-size: 1rem; letter-spacing: 1px; }
        .rating-text { font-size: .7rem; color: var(--ink-light); margin-top: 2px; }
        .rating-bars { display: flex; flex-direction: column; gap: 4px; margin-bottom: 14px; }
        .rb-row { display: flex; align-items: center; gap: 6px; font-size: .7rem; color: var(--ink-light); }
        .rb-lbl { width: 8px; text-align: right; font-weight: 600; }
        .rb-track { flex: 1; height: 5px; background: rgba(122,12,12,0.1); border-radius: 99px; overflow: hidden; }
        .rb-fill { height: 100%; background: var(--accent); border-radius: 99px; transition: width .4s ease; }
        .rb-pct { width: 24px; text-align: right; font-size: .62rem; }
        .rate-label { font-size: .7rem; font-weight: 700; color: var(--ink-mid); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
        .star-picker { display: flex; gap: 4px; }
        .star-picker .star { font-size: 1.6rem; cursor: pointer; color: #d1d5db; transition: color .1s, transform .1s; line-height: 1; user-select: none; }
        .star-picker .star:hover { transform: scale(1.15); }
        .star-picker .star.hover, .star-picker .star.sel { color: var(--accent); }
        .rating-msg { font-size: .74rem; color: var(--green); font-weight: 600; margin-top: 6px; display: none; align-items: center; gap: 5px; }
        .rating-msg.show { display: flex; }

        .thumb-panel { position: fixed; top: 64px; left: -230px; width: 178px; height: calc(100vh - 70px); background: var(--surface); box-shadow: var(--shadow-lg); z-index: 999; transition: left .28s ease; display: flex; flex-direction: column; border-right: 1px solid var(--border); }
        .thumb-panel.open { left: 0; }
        .thumb-header { padding: 11px 13px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .thumb-header h3 { font-size: .85rem; font-weight: 700; color: var(--ink); }
        .thumb-close { background: none; border: none; cursor: pointer; color: var(--ink-light); font-size: .9rem; width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: all .15s; box-shadow: none; }
        .thumb-close:hover { background: var(--primary-light); color: var(--primary); }
        .thumb-list { flex: 1; overflow-y: auto; padding: 9px 7px; display: flex; flex-direction: column; gap: 7px; }
        .thumb-list::-webkit-scrollbar { width: 3px; }
        .thumb-list::-webkit-scrollbar-thumb { background: rgba(122,12,12,.2); border-radius: 3px; }
        .thumb-item { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 7px 5px; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all .14s; }
        .thumb-item:hover { background: var(--paper2); }
        .thumb-item.active { border-color: var(--primary); background: var(--primary-xlight); }
        .thumb-canvas-wrap { width: 100%; display: flex; justify-content: center; }
        .thumb-canvas-wrap canvas { width: 130px !important; height: auto !important; border: 1px solid var(--border); border-radius: 3px; display: block; box-shadow: var(--shadow-xs); }
        .thumb-item.active .thumb-canvas-wrap canvas { border-color: var(--primary); }
        .thumb-label { font-size: .68rem; font-weight: 700; color: var(--ink-light); text-align: center; background: var(--paper2); border: 1px solid var(--border); border-radius: 99px; padding: 2px 10px; line-height: 1.4; transition: all .14s; }
        .thumb-item.active .thumb-label { background: var(--primary); color: #fff; border-color: var(--primary); }
        .thumb-item:hover:not(.active) .thumb-label { background: rgba(122,12,12,.08); color: var(--primary); }

        .comments-wrap { margin-top: 20px; background: var(--surface); border-radius: var(--r-xl); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        .comments-head { padding: 16px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 9px; background: var(--paper2); }
        .comments-head h3 { font-size: .95rem; color: var(--ink); }
        .cm-badge { font-size: .65rem; background: var(--primary-light); color: var(--primary); padding: 2px 8px; border-radius: 99px; font-weight: 700; }
        .comments-body { padding: 18px 22px; }
        .write-box { display: flex; gap: 11px; margin-bottom: 22px; }
        .cm-av { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border); flex-shrink: 0; display: block; }
        .cm-av-init { border: 2px solid var(--border); }
        .write-inner { flex: 1; }
        .write-ta { width: 100%; border: 1.5px solid var(--border); border-radius: 10px; padding: 10px 12px; font-size: .86rem; color: var(--ink); background: var(--paper); resize: vertical; min-height: 78px; outline: none; transition: border-color .15s; line-height: 1.5; }
        .write-ta:focus { border-color: var(--primary); background: var(--surface); }
        .write-ta::placeholder { color: var(--ink-light); }
        .write-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 7px; }
        .char-count { font-size: .7rem; color: var(--ink-light); }
        .char-count.warn { color: var(--red); }
        .btn-post { background: linear-gradient(135deg,var(--primary),var(--primary-dark)); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-size: .8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background .14s; box-shadow: none; }
        .btn-post:disabled { opacity: .45; cursor: not-allowed; }
        .cm-list { display: flex; flex-direction: column; gap: 0; }
        .cm-item { padding: 13px 0; border-top: 1px solid var(--border); }
        .cm-item:first-child { border-top: none; }
        .cm-top { display: flex; gap: 10px; align-items: flex-start; }
        .cm-body { flex: 1; min-width: 0; }
        .cm-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 5px; margin-bottom: 4px; }
        .cm-name { font-weight: 700; font-size: .83rem; color: var(--ink); }
        .cm-role { font-size: .58rem; padding: 1px 6px; border-radius: 99px; font-weight: 700; text-transform: uppercase; }
        .cm-role.student { background: var(--primary-light); color: var(--primary); }
        .cm-role.tutor { background: var(--green-s); color: var(--green); }
        .cm-role.admin { background: var(--purple-s); color: var(--purple); }
        .cm-time { font-size: .68rem; color: var(--ink-light); }
        .cm-text { font-size: .86rem; color: var(--ink-mid); line-height: 1.6; word-break: break-word; }
        .cm-actions { display: flex; gap: 9px; margin-top: 5px; }
        .cm-act { background: none; border: none; font-size: .73rem; color: var(--ink-light); cursor: pointer; padding: 2px 0; display: flex; align-items: center; gap: 4px; transition: color .12s; }
        .cm-act:hover { color: var(--primary); }
        .cm-act.del:hover { color: var(--red); }
        .cm-replies { padding-left: 24px; margin-top: 10px; border-left: 2px solid rgba(122,12,12,.18); }
        .reply-form { display: flex; gap: 9px; margin-top: 9px; padding-top: 9px; border-top: 1px dashed var(--border); }
        .reply-form .write-ta { min-height: 52px; font-size: .8rem; }
        .load-more-btn { width: 100%; padding: 11px; background: var(--paper2); border: none; border-top: 1px solid var(--border); font-size: .82rem; font-weight: 600; color: var(--ink-mid); cursor: pointer; transition: all .18s; box-shadow: none; }
        .load-more-btn:hover { background: var(--primary-light); color: var(--primary); }
        .no-comments { text-align: center; padding: 32px; color: var(--ink-light); font-size: .86rem; }
        .no-comments i { font-size: 1.8rem; display: block; margin-bottom: 8px; opacity: .2; }

        .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(10px); background: var(--ink); color: #fff; padding: 10px 20px; border-radius: 99px; font-size: .83rem; opacity: 0; pointer-events: none; transition: all .28s; z-index: 9999; box-shadow: var(--shadow-md); }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .panel-overlay { display: none; position: fixed; inset: 0; background: rgba(90,9,9,.2); z-index: 998; }
        .panel-overlay.show { display: block; }

        .modal-backdrop { position: fixed; inset: 0; background: rgba(90,9,9,.38); backdrop-filter: blur(3px); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-backdrop.show { display: flex; }
        .report-modal { background: var(--surface); border-radius: var(--r-xl); width: 100%; max-width: 480px; box-shadow: var(--shadow-lg); overflow: hidden; animation: modalIn .25s cubic-bezier(.34,1.56,.64,1) both; }
        @keyframes modalIn { from { opacity:0; transform:scale(.92) translateY(16px); } to { opacity:1; transform:scale(1) translateY(0); } }
        .modal-header { padding: 20px 24px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; gap: 12px; }
        .modal-icon { width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .modal-header-text h3 { font-size: 1.05rem; font-weight: 700; color: var(--ink); }
        .modal-header-text p { font-size: .8rem; color: var(--ink-light); margin-top: 3px; line-height: 1.5; }
        .modal-close { margin-left: auto; background: none; border: none; color: var(--ink-light); cursor: pointer; font-size: .95rem; width: 30px; height: 30px; border-radius: 7px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: all .15s; box-shadow: none; }
        .modal-close:hover { background: var(--primary-light); color: var(--primary); }
        .modal-body { padding: 20px 24px; }
        .report-reasons { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px; }
        .reason-option { position: relative; }
        .reason-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .reason-label { display: flex; align-items: center; gap: 8px; padding: 9px 12px; border-radius: 9px; border: 1.5px solid var(--border); cursor: pointer; font-size: .8rem; color: var(--ink-mid); transition: all .15s; user-select: none; font-weight: 500; }
        .reason-label i { font-size: .82rem; color: var(--ink-light); flex-shrink: 0; }
        .reason-option input:checked+.reason-label { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
        .reason-option input:checked+.reason-label i { color: var(--primary); }
        .reason-label:hover { border-color: var(--primary); background: var(--primary-xlight); }
        .report-details-wrap { margin-bottom: 16px; }
        .report-details-wrap label { display: block; font-size: .72rem; font-weight: 700; color: var(--ink-light); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
        .report-ta { width: 100%; border: 1.5px solid var(--border); border-radius: 10px; padding: 10px 12px; font-size: .84rem; color: var(--ink); background: var(--paper); resize: none; height: 90px; outline: none; transition: border-color .15s; line-height: 1.5; }
        .report-ta:focus { border-color: var(--primary); background: var(--surface); }
        .report-ta::placeholder { color: var(--ink-light); }
        .report-notice { display: flex; gap: 8px; padding: 10px 12px; background: var(--amber-xlight); border: 1px solid rgba(242,180,0,0.4); border-radius: 8px; margin-bottom: 18px; }
        .report-notice i { color: var(--amber); font-size: .85rem; flex-shrink: 0; margin-top: 1px; }
        .report-notice p { font-size: .75rem; color: #78350f; line-height: 1.5; }
        .modal-footer { display: flex; gap: 9px; justify-content: flex-end; }
        .btn-cancel { background: var(--paper); color: var(--ink-mid); border: 1px solid var(--border); padding: 10px 20px; border-radius: 9px; font-size: .85rem; font-weight: 600; cursor: pointer; transition: all .15s; box-shadow: none; }
        .btn-cancel:hover { background: var(--paper2); }
        .btn-submit-report { background: var(--primary); color: #fff; border: none; padding: 10px 22px; border-radius: 9px; font-size: .85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 7px; transition: background .15s; box-shadow: none; }
        .btn-submit-report:hover { background: var(--primary-dark); }
        .btn-submit-report:disabled { opacity: .5; cursor: not-allowed; }

        @media(max-width:1100px) {
            .page-wrapper { grid-template-columns: 1fr; }
            .sidebar { display: grid; grid-template-columns: repeat(2,1fr); position: static; }
        }
        @media(max-width:640px) {
            .sidebar { grid-template-columns: 1fr; }
            .doc-header { margin: 0 12px; flex-wrap: wrap; }
            .doc-type-pill { margin-left: 0; }
            .page-wrapper { padding: 14px 12px 40px; }
            #pdfCanvas { width: 100% !important; height: auto !important; }
            .cm-replies { padding-left: 14px; }
            .comments-body { padding: 14px 16px; }
            .report-reasons { grid-template-columns: 1fr; }
            .modal-body { padding: 16px 18px; }
        }
        .swal-toast-popup { border-radius: 99px !important; padding: 10px 20px !important; font-size: .83rem !important; font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important; }
    </style>
</head>

<body>

    <div class="panel-overlay" id="panelOverlay" onclick="closeThumbs()"></div>

    <!-- Thumbnail Panel -->
    <div class="thumb-panel" id="thumbPanel">
        <div class="thumb-header">
            <h3>Pages</h3>
            <button class="thumb-close" onclick="closeThumbs()"><i class="fas fa-times"></i></button>
        </div>
        <div class="thumb-list" id="thumbList"></div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- REPORT MODAL -->
    <div class="modal-backdrop" id="reportModal" onclick="handleBackdropClick(event)">
        <div class="report-modal" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
            <div class="modal-header">
                <div class="modal-icon"><i class="fas fa-flag"></i></div>
                <div class="modal-header-text">
                    <h3 id="reportModalTitle">Report this Content</h3>
                    <p>Help us keep ScholarSwap safe. Reports are reviewed by our moderation team.</p>
                </div>
                <button class="modal-close" onclick="closeReportModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="report-reasons" id="reportReasons">
                    <div class="reason-option">
                        <input type="radio" name="reportReason" id="rr-spam" value="spam">
                        <label class="reason-label" for="rr-spam"><i class="fas fa-ban"></i> Spam</label>
                    </div>
                    <div class="reason-option">
                        <input type="radio" name="reportReason" id="rr-inappropriate" value="inappropriate_content">
                        <label class="reason-label" for="rr-inappropriate"><i class="fas fa-exclamation-triangle"></i> Inappropriate</label>
                    </div>
                    <div class="reason-option">
                        <input type="radio" name="reportReason" id="rr-copyright" value="copyright_violation">
                        <label class="reason-label" for="rr-copyright"><i class="fas fa-copyright"></i> Copyright</label>
                    </div>
                    <div class="reason-option">
                        <input type="radio" name="reportReason" id="rr-misleading" value="misleading_information">
                        <label class="reason-label" for="rr-misleading"><i class="fas fa-times-circle"></i> Misleading</label>
                    </div>
                    <div class="reason-option">
                        <input type="radio" name="reportReason" id="rr-category" value="wrong_category">
                        <label class="reason-label" for="rr-category"><i class="fas fa-folder-open"></i> Wrong Category</label>
                    </div>
                    <div class="reason-option">
                        <input type="radio" name="reportReason" id="rr-other" value="other">
                        <label class="reason-label" for="rr-other"><i class="fas fa-ellipsis-h"></i> Other</label>
                    </div>
                </div>
                <div class="report-details-wrap">
                    <label for="reportDetails">Additional details <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
                    <textarea id="reportDetails" class="report-ta" placeholder="Describe the issue in more detail…" maxlength="1000"></textarea>
                </div>
                <div class="report-notice">
                    <i class="fas fa-info-circle"></i>
                    <p>False reports may result in your account being restricted. Only report content that genuinely violates our community guidelines.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeReportModal()">Cancel</button>
                    <button class="btn-submit-report" id="submitReportBtn" onclick="submitReport()">
                        <i class="fas fa-flag"></i> Submit Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include_once('admin/files/header.php') ?>

    <!-- Document Header -->
    <div class="doc-header">
        <div class="doc-header-icon"><i class="fas fa-file-pdf"></i></div>
        <div class="doc-header-text">
            <h1><?php echo htmlspecialchars($note['title']) ?>
                <?php if ($type === 'newspaper') echo ' — (' . date("d M Y", strtotime($note['publication_date'])) . ')'; ?>
            </h1>
            <p><?php echo $type !== 'newspaper' ? htmlspecialchars($note['description'] ?? '') : 'Newspaper Edition'; ?></p>
        </div>
        <span class="doc-type-pill"><?php echo ucfirst($type); ?></span>
    </div>

    <!-- Main Layout -->
    <div class="page-wrapper">

        <!-- LEFT: PDF + Comments -->
        <div>
            <!-- PDF VIEWER -->
            <div class="viewer-card" id="viewerCard">
                <div class="pdf-toolbar">
                    <div class="tb-group">
                        <button class="tb-btn" id="btnThumbs" onclick="toggleThumbs()" data-tip="Page Thumbnails"><i class="fas fa-th-large"></i></button>
                    </div>
                    <div class="tb-group">
                        <button class="tb-btn" id="btnFirst" onclick="goToPage(1)" data-tip="First Page" disabled><i class="fas fa-step-backward"></i></button>
                        <button class="tb-btn" id="btnPrev" onclick="changePage(-1)" data-tip="Previous Page" disabled><i class="fas fa-chevron-left"></i></button>
                        <div class="page-control">
                            <input type="number" id="pageInput" class="page-input" value="1" min="1">
                            <span id="totalPages" style="color:rgba(255,255,255,.45);font-size:.78rem">/ –</span>
                        </div>
                        <button class="tb-btn" id="btnNext" onclick="changePage(1)" data-tip="Next Page"><i class="fas fa-chevron-right"></i></button>
                        <button class="tb-btn" id="btnLast" onclick="goToLast()" data-tip="Last Page"><i class="fas fa-step-forward"></i></button>
                    </div>
                    <div class="tb-group">
                        <button class="tb-btn" onclick="adjustZoom(-.25)" data-tip="Zoom Out"><i class="fas fa-search-minus"></i></button>
                        <select class="zoom-select" id="zoomSelect" onchange="setZoomPreset(this.value)">
                            <option value="0.5">50%</option>
                            <option value="0.75">75%</option>
                            <option value="1.0" selected>100%</option>
                            <option value="1.25">125%</option>
                            <option value="1.5">150%</option>
                            <option value="1.75">175%</option>
                            <option value="2.0">200%</option>
                            <option value="fit-width">Fit Width</option>
                            <option value="fit-page">Fit Page</option>
                        </select>
                        <button class="tb-btn" onclick="adjustZoom(.25)" data-tip="Zoom In"><i class="fas fa-search-plus"></i></button>
                    </div>
                    <div class="tb-group">
                        <button class="tb-btn" onclick="rotate(-90)" data-tip="Rotate Left"><i class="fas fa-undo"></i></button>
                        <button class="tb-btn" onclick="rotate(90)" data-tip="Rotate Right"><i class="fas fa-redo"></i></button>
                    </div>
                    <div class="tb-spacer"></div>
                    <div class="tb-group">
                        <button class="tb-btn" id="btnFullscreen" onclick="toggleFullscreen()" data-tip="Fullscreen"><i class="fas fa-expand"></i></button>
                    </div>
                </div>

                <span style="display:none" id="pdfUrl"><?php echo htmlspecialchars($note['file_path']); ?></span>

                <div class="canvas-container" id="canvasContainer">
                    <div class="pdf-loader" id="pdfLoader">
                        <div class="loader-ring"></div>
                        <span class="loader-text">Loading document…</span>
                    </div>
                    <canvas id="pdfCanvas"></canvas>
                </div>

                <div class="reading-progress">
                    <span class="prog-txt" id="progressLabel">Page 1</span>
                    <div class="prog-bar"><div class="prog-fill" id="progressFill" style="width:0%"></div></div>
                    <span class="prog-txt" id="progressPercent">0%</span>
                </div>
            </div>

            <!-- COMMENTS -->
            <div class="comments-wrap">
                <div class="comments-head">
                    <i class="fas fa-comments" style="color:var(--primary)"></i>
                    <h3>Discussion</h3>
                    <span class="cm-badge" id="cmBadge"><?php echo count($topComments); ?></span>
                </div>
                <div class="comments-body">
                    <div class="write-box">
                        <?php echo avatarHtml($me['profile_image'] ?? '', $me['display_name'] ?? 'Me', $me['role'] ?? 'student', '38px'); ?>
                        <div class="write-inner">
                            <textarea id="mainTa" class="write-ta" placeholder="Share your thoughts on this resource…" maxlength="2000" oninput="countChars(this,'mainCount')"></textarea>
                            <div class="write-footer">
                                <span class="char-count" id="mainCount">0 / 2000</span>
                                <button class="btn-post" id="postBtn" onclick="postComment()">
                                    <i class="fas fa-paper-plane"></i> Post
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="cm-list" id="cmList">
                        <?php if (empty($topComments)): ?>
                            <div class="no-comments" id="emptyState">
                                <i class="fas fa-comment-slash"></i>
                                No comments yet. Be the first!
                            </div>
                        <?php else: ?>
                            <?php foreach ($topComments as $idx => $cm):
                                $isOwn    = ((int)$cm['user_id'] === $selfId);
                                $isHidden = ($idx >= $SHOW_INIT);
                                $roleKey  = htmlspecialchars($cm['user_role'] ?? 'student');
                            ?>
                                <div class="cm-item<?php echo $isHidden ? ' cm-extra' : ''; ?>" id="cm-<?php echo $cm['comment_id']; ?>" <?php echo $isHidden ? 'style="display:none"' : ''; ?>>
                                    <div class="cm-top">
                                        <?php echo avatarHtml($cm['u_img'] ?? '', $cm['display_name'] ?? 'User', $cm['user_role'] ?? 'student', '38px'); ?>
                                        <div class="cm-body">
                                            <div class="cm-meta">
                                                <span class="cm-name"><?php echo htmlspecialchars($cm['display_name']); ?></span>
                                                <span class="cm-role <?php echo $roleKey; ?>"><?php echo $roleKey; ?></span>
                                                <span class="cm-time"><?php echo timeAgo($cm['created_at']); ?></span>
                                            </div>
                                            <div class="cm-text"><?php echo nl2br(htmlspecialchars($cm['body'])); ?></div>
                                            <div class="cm-actions">
                                                <button class="cm-act" onclick="toggleReply(<?php echo $cm['comment_id']; ?>)"><i class="fas fa-reply"></i> Reply</button>
                                                <?php if ($isOwn): ?>
                                                    <button class="cm-act del" onclick="deleteComment(<?php echo $cm['comment_id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="reply-form" id="rf-<?php echo $cm['comment_id']; ?>" style="display:none">
                                                <?php echo avatarHtml($me['profile_image'] ?? '', $me['display_name'] ?? 'Me', $me['role'] ?? 'student', '30px'); ?>
                                                <div class="write-inner" style="flex:1">
                                                    <textarea class="write-ta" id="rta-<?php echo $cm['comment_id']; ?>" placeholder="Write a reply…" maxlength="2000" oninput="countChars(this,'rc-<?php echo $cm['comment_id']; ?>')"></textarea>
                                                    <div class="write-footer">
                                                        <span class="char-count" id="rc-<?php echo $cm['comment_id']; ?>">0 / 2000</span>
                                                        <div style="display:flex;gap:6px">
                                                            <button class="btn-post" style="background:#6b7280" onclick="toggleReply(<?php echo $cm['comment_id']; ?>)">Cancel</button>
                                                            <button class="btn-post" onclick="postReply(<?php echo $cm['comment_id']; ?>)"><i class="fas fa-paper-plane"></i> Reply</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if (!empty($repliesMap[$cm['comment_id']])): ?>
                                                <div class="cm-replies">
                                                    <?php foreach ($repliesMap[$cm['comment_id']] as $rep):
                                                        $repOwn  = ((int)$rep['user_id'] === $selfId);
                                                        $repRole = htmlspecialchars($rep['user_role'] ?? 'student');
                                                    ?>
                                                        <div class="cm-item" id="cm-<?php echo $rep['comment_id']; ?>">
                                                            <div class="cm-top">
                                                                <?php echo avatarHtml($rep['u_img'] ?? '', $rep['display_name'] ?? 'User', $rep['user_role'] ?? 'student', '30px'); ?>
                                                                <div class="cm-body">
                                                                    <div class="cm-meta">
                                                                        <span class="cm-name"><?php echo htmlspecialchars($rep['display_name']); ?></span>
                                                                        <span class="cm-role <?php echo $repRole; ?>"><?php echo $repRole; ?></span>
                                                                        <span class="cm-time"><?php echo timeAgo($rep['created_at']); ?></span>
                                                                    </div>
                                                                    <div class="cm-text"><?php echo nl2br(htmlspecialchars($rep['body'])); ?></div>
                                                                    <?php if ($repOwn): ?>
                                                                        <div class="cm-actions">
                                                                            <button class="cm-act del" onclick="deleteComment(<?php echo $rep['comment_id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="cm-replies" id="replies-<?php echo $cm['comment_id']; ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (count($topComments) > $SHOW_INIT): ?>
                    <button class="load-more-btn" id="loadMoreBtn" onclick="loadMore()">
                        <i class="fas fa-chevron-down"></i>
                        Show <?php echo count($topComments) - $SHOW_INIT; ?> more comments
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <!-- Actions -->
            <div class="card">
                <h4 class="card-title"><span><i class="fas fa-bolt"></i>Actions</span></h4>
                <div class="action-grid">
                    <button class="btn-primary" id="downloadBtn" onclick="handleDownload()">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                    <button class="btn-secondary<?php echo $alreadyBookmarked ? ' bookmarked' : ''; ?>"
                        id="bookmarkBtn" onclick="handleBookmark()"
                        <?php if ($alreadyBookmarked): ?>style="background:var(--primary);color:#fff;border-color:var(--primary)"<?php endif; ?>>
                        <i class="<?php echo $alreadyBookmarked ? 'fas' : 'far'; ?> fa-bookmark"></i>
                        <?php echo $alreadyBookmarked ? 'Saved' : 'Save for Later'; ?>
                    </button>
                    <button class="btn-ghost" onclick="shareResource()">
                        <i class="fas fa-share-alt"></i> Share this resource
                    </button>
                    <button class="btn-report<?php echo $alreadyReported ? ' reported' : ''; ?>"
                        id="reportBtn"
                        <?php echo $alreadyReported ? 'disabled' : 'onclick="openReportModal()"'; ?>>
                        <i class="fas fa-flag"></i>
                        <?php echo $alreadyReported ? 'Already Reported' : 'Report this Content'; ?>
                    </button>
                </div>
            </div>

            <!-- Author -->
            <div class="card">
                <h4 class="card-title"><span><i class="fas fa-user-circle"></i>Uploaded By</span></h4>
                <div class="author-row">
                    <?php
                    $avGrad = match ($uploaderRole) {
                        'tutor' => 'linear-gradient(135deg,#0d9488,#0284c7)',
                        'admin' => 'linear-gradient(135deg,#7A0C0C,#b91c1c)',
                        default => 'linear-gradient(135deg,#7A0C0C,#F2B400)',
                    };
                    $avInit = strtoupper($uploaderName[0] ?? '?');
                    if ($uploaderImg): ?>
                        <img src="<?php echo htmlspecialchars($uploaderImg); ?>"
                            alt="<?php echo htmlspecialchars($uploaderName); ?>"
                            class="author-av"
                            onerror="this.outerHTML='<div class=\'author-av-init\' style=\'background:<?php echo $avGrad; ?>\'><?php echo $avInit; ?></div>'">
                    <?php else: ?>
                        <div class="author-av-init" style="background:<?php echo $avGrad; ?>"><?php echo $avInit; ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="author-name"><?php echo htmlspecialchars($uploaderName); ?></div>
                        <span class="author-role-badge <?php echo $uploaderRole; ?>">
                            <?php echo $type === 'newspaper' || $uploaderRole === 'admin' ? 'Admin' : ucfirst($uploaderRole); ?>
                        </span>
                    </div>
                </div>
                <div class="stats-row">
                    <div class="stat">
                        <div class="stat-val"><?php
                            if ($uploaderRole === 'admin') {
                                $adminUploadId = $note['admin_id'] ?? 0;
                                $s = $conn->prepare("SELECT (SELECT COUNT(*) FROM notes WHERE admin_id=:a1)+(SELECT COUNT(*) FROM books WHERE admin_id=:a2) AS c");
                                $s->execute([':a1' => $adminUploadId, ':a2' => $adminUploadId]);
                            } elseif ($type === 'newspaper') {
                                $s = $conn->prepare("SELECT COUNT(n_code) AS c FROM newspapers WHERE admin_id = :a");
                                $s->execute(['a' => $note['admin_id']]);
                            } else {
                                $s = $conn->prepare("SELECT (SELECT COUNT(n_code) FROM notes WHERE user_id=:n)+(SELECT COUNT(b_code) FROM books WHERE user_id=:b) AS c");
                                $s->execute(['n' => $note['user_id'], 'b' => $note['user_id']]);
                            }
                            echo $s->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
                        ?></div>
                        <div class="stat-lbl">Uploads</div>
                    </div>
                    <div class="stat">
                        <div class="stat-val"><?php
                            if ($uploaderRole === 'admin') {
                                $adminUploadId = $note['admin_id'] ?? 0;
                                $s = $conn->prepare("SELECT (SELECT COALESCE(SUM(download_count),0) FROM notes WHERE admin_id=:a1)+(SELECT COALESCE(SUM(download_count),0) FROM books WHERE admin_id=:a2) AS c");
                                $s->execute([':a1' => $adminUploadId, ':a2' => $adminUploadId]);
                            } elseif ($type === 'newspaper') {
                                $s = $conn->prepare("SELECT COALESCE(SUM(download_count),0) AS c FROM newspapers WHERE admin_id = :u");
                                $s->execute(['u' => $note['admin_id']]);
                            } else {
                                $s = $conn->prepare("SELECT (SELECT COALESCE(SUM(download_count),0) FROM notes WHERE user_id=:u1)+(SELECT COALESCE(SUM(download_count),0) FROM books WHERE user_id=:u2) AS c");
                                $s->execute([':u1' => $note['user_id'], ':u2' => $note['user_id']]);
                            }
                            echo $s->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
                        ?></div>
                        <div class="stat-lbl">Downloads</div>
                    </div>
                    <div class="stat">
                        <div class="stat-val"><?php echo $avgRating ?: '–'; ?></div>
                        <div class="stat-lbl">Rating</div>
                    </div>
                </div>
                <!-- Hide "View Profile" for admin-uploaded content -->
                <?php if ($type !== 'newspaper' && $uploaderRole !== 'admin'): ?>
                    <button class="profile-btn"
                        onclick="window.location.href='admin/user_pages/userprofile.php?u=<?php echo $eUploaderId; ?>'">
                        <i class="fas fa-user" style="margin-right:6px"></i>View Profile
                    </button>
                <?php endif; ?>
            </div>

            <!-- File Details -->
            <div class="card">
                <h4 class="card-title"><span><i class="fas fa-info-circle"></i>File Details</span></h4>
                <div class="detail-list">
                    <div class="detail-item"><span class="lbl">Format</span><span class="val">PDF</span></div>
                    <div class="detail-item"><span class="lbl">Pages</span><span class="val" id="sidebarPages">–</span></div>
                    <div class="detail-item">
                        <span class="lbl">Size</span>
                        <span class="val"><?php echo round($note['file_size'] / 1024 / 1024, 2) . ' MB'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="lbl">Rating</span>
                        <span class="val">
                            <?php
                            $stars = round($avgRating);
                            echo '<span class="stars">' . str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) . '</span> (' . $totalRatings . ')';
                            ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="lbl">Uploaded</span>
                        <span class="val"><?php echo date("j M Y", strtotime($note['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Rating Card -->
            <div class="card rating-card">
                <h4 class="card-title">
                    <span><i class="fas fa-star" style="color:var(--accent);margin-right:4px"></i>Rate this</span>
                    <span style="font-size:.7rem;color:var(--ink-light)"><?php echo $totalRatings; ?> rating<?php echo $totalRatings !== 1 ? 's' : ''; ?></span>
                </h4>
                <div class="rating-display">
                    <div class="rating-number" id="avgNum"><?php echo $avgRating ?: '–'; ?></div>
                    <div>
                        <div class="rating-stars" id="avgStars"><?php
                            $f = round($avgRating);
                            echo str_repeat('★', $f) . str_repeat('☆', 5 - $f);
                        ?></div>
                        <div class="rating-text" id="totalTxt">
                            <?php echo $totalRatings ? 'Based on ' . $totalRatings . ' rating' . ($totalRatings !== 1 ? 's' : '') : 'No ratings yet'; ?>
                        </div>
                    </div>
                </div>
                <div class="rating-bars">
                    <?php for ($star = 5; $star >= 1; $star--):
                        $cnt = (int)($barMap[$star] ?? 0);
                        $pct = $totalRatings ? round($cnt / $totalRatings * 100) : 0;
                    ?>
                        <div class="rb-row">
                            <span class="rb-lbl"><?php echo $star; ?></span>
                            <i class="fas fa-star" style="color:var(--accent);font-size:.55rem"></i>
                            <div class="rb-track"><div class="rb-fill" style="width:<?php echo $pct; ?>%"></div></div>
                            <span class="rb-pct"><?php echo $pct; ?>%</span>
                        </div>
                    <?php endfor; ?>
                </div>
                <div style="padding-top:12px;border-top:1px solid rgba(242,180,0,0.35)">
                    <div class="rate-label"><?php echo $myRating ? 'Your rating' : 'Rate this resource'; ?></div>
                    <div class="star-picker" id="starPicker">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star<?php echo $i <= $myRating ? ' sel' : ''; ?>" data-val="<?php echo $i; ?>" onclick="submitRating(<?php echo $i; ?>)">★</span>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-msg" id="ratingMsg"><i class="fas fa-check-circle"></i> <span id="ratingMsgTxt">Saved!</span></div>
                </div>
            </div>

        </aside>
    </div>

    <?php include_once('admin/files/footer.php') ?>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

        const pdfUrl = document.getElementById("pdfUrl").innerText.trim();
        let pdfDoc = null, pageNum = 1, scale = 1.2, rotation = 0, rendering = false, pendingPage = null, fitMode = null;
        const canvas = document.getElementById("pdfCanvas"), ctx = canvas.getContext("2d");
        const loader = document.getElementById("pdfLoader"), container = document.getElementById("canvasContainer");

        pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
            pdfDoc = pdf;
            document.getElementById("totalPages").textContent = `/ ${pdf.numPages}`;
            document.getElementById("sidebarPages").textContent = pdf.numPages;
            document.getElementById("pageInput").max = pdf.numPages;
            updateNavButtons();
            renderPage(pageNum);
            buildThumbnails();
        }).catch(() => {
            loader.innerHTML = '<i class="fas fa-exclamation-circle" style="color:var(--primary);font-size:2rem"></i><span class="loader-text">Failed to load PDF</span>';
        });

        function renderPage(num) {
            if (rendering) { pendingPage = num; return; }
            rendering = true;
            pageNum = num;
            pdfDoc.getPage(num).then(page => {
                let vp;
                if (fitMode === 'fit-width') {
                    const us = page.getViewport({ scale: 1, rotation });
                    vp = page.getViewport({ scale: (container.clientWidth - 56) / us.width, rotation });
                } else if (fitMode === 'fit-page') {
                    const us = page.getViewport({ scale: 1, rotation });
                    const s = Math.min((container.clientWidth - 56) / us.width, (container.clientHeight - 56) / us.height);
                    vp = page.getViewport({ scale: s, rotation });
                } else {
                    vp = page.getViewport({ scale, rotation });
                }
                canvas.width = vp.width; canvas.height = vp.height;
                page.render({ canvasContext: ctx, viewport: vp }).promise.then(() => {
                    rendering = false;
                    loader.style.display = 'none';
                    updateUI();
                    if (pendingPage !== null) { const p = pendingPage; pendingPage = null; renderPage(p); }
                });
            });
        }

        function updateUI() {
            document.getElementById("pageInput").value = pageNum;
            const pct = pdfDoc ? Math.round((pageNum / pdfDoc.numPages) * 100) : 0;
            document.getElementById("progressFill").style.width = pct + '%';
            document.getElementById("progressPercent").textContent = pct + '%';
            document.getElementById("progressLabel").textContent = `Page ${pageNum} of ${pdfDoc?.numPages||1}`;
            updateNavButtons();
            document.querySelectorAll('.thumb-item').forEach((el, i) => el.classList.toggle('active', i + 1 === pageNum));
        }

        function updateNavButtons() {
            const n = pdfDoc?.numPages || 1;
            document.getElementById("btnFirst").disabled = pageNum <= 1;
            document.getElementById("btnPrev").disabled  = pageNum <= 1;
            document.getElementById("btnNext").disabled  = pageNum >= n;
            document.getElementById("btnLast").disabled  = pageNum >= n;
        }

        function changePage(d) { const p = pageNum + d; if (p >= 1 && p <= pdfDoc.numPages) renderPage(p); }
        function goToPage(n)   { if (n >= 1 && n <= pdfDoc?.numPages) renderPage(n); }
        function goToLast()    { goToPage(pdfDoc?.numPages); }

        document.getElementById("pageInput").addEventListener("keydown", e => {
            if (e.key === "Enter") { const v = parseInt(e.target.value); if (v && pdfDoc && v >= 1 && v <= pdfDoc.numPages) renderPage(v); }
        });

        function adjustZoom(d) { fitMode = null; scale = Math.max(.25, Math.min(4, scale + d)); syncZoomSelect(); renderPage(pageNum); }
        function setZoomPreset(val) {
            if (val === 'fit-width' || val === 'fit-page') fitMode = val;
            else { fitMode = null; scale = parseFloat(val); }
            renderPage(pageNum);
        }
        function syncZoomSelect() {
            const sel = document.getElementById("zoomSelect");
            const m = [...sel.options].find(o => parseFloat(o.value) === scale);
            if (m) sel.value = m.value;
        }
        container.addEventListener("wheel", e => {
            if (e.ctrlKey || e.metaKey) { e.preventDefault(); adjustZoom(e.deltaY < 0 ? .15 : -.15); }
        }, { passive: false });

        function rotate(deg) { rotation = (rotation + deg + 360) % 360; renderPage(pageNum); }
        function toggleFullscreen() {
            const el = document.getElementById("viewerCard");
            if (!document.fullscreenElement)(el.requestFullscreen || el.webkitRequestFullscreen).call(el);
            else (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        }
        document.addEventListener("fullscreenchange", () => {
            document.getElementById("btnFullscreen").innerHTML = document.fullscreenElement ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>';
            setTimeout(() => renderPage(pageNum), 200);
        });

        async function buildThumbnails() {
            if (!pdfDoc) return;
            const list = document.getElementById("thumbList");
            list.innerHTML = '';
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                const item = document.createElement('div');
                item.className = 'thumb-item' + (i === pageNum ? ' active' : '');
                item.onclick = () => { renderPage(i); closeThumbs(); };
                const wrap = document.createElement('div'); wrap.className = 'thumb-canvas-wrap';
                const c = document.createElement('canvas'); wrap.appendChild(c); item.appendChild(wrap);
                const lbl = document.createElement('div'); lbl.className = 'thumb-label'; lbl.textContent = `Page ${i}`; item.appendChild(lbl);
                list.appendChild(item);
                pdfDoc.getPage(i).then(page => {
                    const vp = page.getViewport({ scale: .18 });
                    c.width = vp.width; c.height = vp.height;
                    page.render({ canvasContext: c.getContext('2d'), viewport: vp });
                });
            }
        }

        function toggleThumbs() {
            const p = document.getElementById("thumbPanel"), o = document.getElementById("panelOverlay");
            const open = p.classList.toggle("open"); o.classList.toggle("show", open);
            document.getElementById("btnThumbs").classList.toggle("active", open);
        }
        function closeThumbs() {
            document.getElementById("thumbPanel").classList.remove("open");
            document.getElementById("panelOverlay").classList.remove("show");
            document.getElementById("btnThumbs").classList.remove("active");
        }

        document.addEventListener("keydown", e => {
            if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA") return;
            if (e.key === "ArrowRight" || e.key === "ArrowDown") changePage(1);
            if (e.key === "ArrowLeft"  || e.key === "ArrowUp")   changePage(-1);
            if (e.key === "Home") goToPage(1);
            if (e.key === "End")  goToLast();
            if (e.key === "+" || e.key === "=") adjustZoom(.25);
            if (e.key === "-") adjustZoom(-.25);
            if (e.key === "f" || e.key === "F") toggleFullscreen();
        });

        function shareResource() {
            const d = { title: document.title, text: "Check out this resource on ScholarSwap", url: window.location.href };
            if (navigator.share) navigator.share(d).catch(() => {});
            else { navigator.clipboard.writeText(window.location.href); showToast("Link copied to clipboard!", "success"); }
        }

        function showToast(msg, icon = 'info') {
            Swal.fire({
                toast: true, position: 'bottom', icon: icon, title: msg,
                showConfirmButton: false, timer: 3000, timerProgressBar: true,
                background: '#1a0303', color: '#fff',
                iconColor: icon === 'error' ? '#f87171' : icon === 'success' ? '#34d399' : '#F2B400',
                customClass: { popup: 'swal-toast-popup' }
            });
        }

        /* STAR RATING */
        const RESOURCE_ID = '<?php echo $enoteId; ?>';
        const DOC_TYPE    = '<?php echo $etype; ?>';
        const starEls = document.querySelectorAll('.star-picker .star');
        starEls.forEach(s => {
            s.addEventListener('mouseenter', () => { const v = parseInt(s.dataset.val); starEls.forEach(el => el.classList.toggle('hover', parseInt(el.dataset.val) <= v)); });
            s.addEventListener('mouseleave', () => starEls.forEach(el => el.classList.remove('hover')));
        });
        function submitRating(val) {
            starEls.forEach(el => el.classList.toggle('sel', parseInt(el.dataset.val) <= val));
            fetch('admin/user_pages/auth/submit_rating.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ resource_id: RESOURCE_ID, document_type: DOC_TYPE, rating: val })
            }).then(r => r.text()).then(raw => {
                let d; try { d = JSON.parse(raw); } catch (e) { showToast('Server said: ' + raw.substring(0, 150)); return; }
                if (d.success) {
                    const avg = parseFloat(d.avg_rating).toFixed(1);
                    document.getElementById('avgNum').textContent = avg;
                    document.getElementById('totalTxt').textContent = 'Based on ' + d.total_ratings + ' rating' + (d.total_ratings !== 1 ? 's' : '');
                    const f = Math.round(d.avg_rating);
                    document.getElementById('avgStars').textContent = '★'.repeat(f) + '☆'.repeat(5 - f);
                    showToast(d.message, 'success');
                } else { showToast(d.message, 'error'); }
            }).catch(() => showToast('Network error.', 'error'));
        }

        /* REPORT MODAL */
        function openReportModal()  { document.getElementById('reportModal').classList.add('show'); document.body.style.overflow = 'hidden'; }
        function closeReportModal() { document.getElementById('reportModal').classList.remove('show'); document.body.style.overflow = ''; }
        function handleBackdropClick(e) { if (e.target === document.getElementById('reportModal')) closeReportModal(); }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeReportModal(); });

        function submitReport() {
            const reason = document.querySelector('input[name="reportReason"]:checked');
            if (!reason) {
                const rr = document.getElementById('reportReasons');
                rr.style.outline = '2px solid var(--primary)'; rr.style.borderRadius = '9px';
                showToast('Please select a reason for your report.');
                setTimeout(() => { rr.style.outline = 'none'; }, 2500);
                return;
            }
            const details = document.getElementById('reportDetails').value.trim();
            const btn = document.getElementById('submitReportBtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
            fetch('admin/user_pages/auth/submit_report.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ resource_id: RESOURCE_ID, document_type: DOC_TYPE, reason: reason.value, details: details })
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    closeReportModal();
                    const rb = document.getElementById('reportBtn');
                    rb.classList.add('reported'); rb.innerHTML = '<i class="fas fa-flag"></i> Already Reported'; rb.disabled = true;
                    Swal.fire({ icon: 'success', title: 'Report Submitted', text: 'Thank you. Our moderation team will review this content.', timer: 3500, timerProgressBar: true, showConfirmButton: false, background: '#fff', color: '#0f1117', iconColor: '#059669' });
                } else {
                    btn.disabled = false; btn.innerHTML = '<i class="fas fa-flag"></i> Submit Report';
                    showToast(d.message || 'Could not submit report. Try again.');
                }
            }).catch(() => {
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-flag"></i> Submit Report';
                showToast('Connection error. Please try again.');
            });
        }

        /* COMMENTS */
        const SELF_ID = <?php echo $selfId; ?>;
        const ME_INIT = '<?php echo strtoupper(mb_substr($me['display_name'] ?? 'U', 0, 1)); ?>';
        const ME_ROLE = '<?php echo $me['role'] ?? 'student'; ?>';

        function countChars(ta, countId) {
            const el = document.getElementById(countId); if (!el) return;
            el.textContent = ta.value.length + ' / 2000';
            el.className = 'char-count' + (ta.value.length > 1800 ? ' warn' : '');
        }

        function postComment() {
            const ta = document.getElementById('mainTa'), text = ta.value.trim();
            if (!text) { ta.focus(); ta.style.borderColor = 'var(--primary)'; return; }
            const btn = document.getElementById('postBtn'); btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            fetch('admin/user_pages/auth/submit_comment.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'post', resource_id: RESOURCE_ID, document_type: DOC_TYPE, body: text })
            }).then(r => r.json()).then(d => {
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Post';
                if (d.success) {
                    ta.value = ''; document.getElementById('mainCount').textContent = '0 / 2000'; ta.style.borderColor = '';
                    document.getElementById('emptyState')?.remove();
                    prependComment(d.comment); updateBadge(1); showToast('Comment posted!', 'success');
                } else { showToast(d.message || 'Could not post comment.', 'error'); }
            }).catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Post'; showToast('Connection error.', 'error'); });
        }

        function postReply(parentId) {
            const ta = document.getElementById('rta-' + parentId), text = ta.value.trim();
            if (!text) { ta.focus(); return; }
            fetch('admin/user_pages/auth/submit_comment.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reply', resource_id: RESOURCE_ID, document_type: DOC_TYPE, parent_id: parentId, body: text })
            }).then(r => r.json()).then(d => {
                if (d.success) { ta.value = ''; toggleReply(parentId); appendReply(d.comment, parentId); updateBadge(1); showToast('Reply posted!', 'success'); }
                else { showToast(d.message || 'Could not post reply.', 'error'); }
            }).catch(() => showToast('Connection error.', 'error'));
        }

        function deleteComment(id) {
            Swal.fire({ title: 'Delete comment?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#7A0C0C', cancelButtonColor: '#6b7280', confirmButtonText: '<i class="fas fa-trash"></i> Delete', cancelButtonText: 'Cancel', background: '#fff', color: '#0f1117', reverseButtons: true })
            .then(result => {
                if (!result.isConfirmed) return;
                fetch('admin/user_pages/auth/submit_comment.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', comment_id: id })
                }).then(r => r.json()).then(d => {
                    if (d.success) {
                        const el = document.getElementById('cm-' + id);
                        if (el) { const txt = el.querySelector('.cm-text'); txt.textContent = '[deleted]'; txt.style.cssText = 'font-style:italic;color:var(--ink-light)'; el.querySelector('.cm-actions')?.remove(); }
                        updateBadge(-1); showToast('Comment deleted.', 'success');
                    } else { showToast(d.message || 'Could not delete.', 'error'); }
                }).catch(() => showToast('Connection error.', 'error'));
            });
        }

        function toggleReply(id) {
            const rf = document.getElementById('rf-' + id); if (!rf) return;
            const show = rf.style.display === 'none'; rf.style.display = show ? 'flex' : 'none';
            if (show) document.getElementById('rta-' + id)?.focus();
        }

        function buildCommentHTML(cm, isReply = false) {
            const sz = isReply ? '30px' : '38px';
            const gradMap = { tutor: 'linear-gradient(135deg,#0d9488,#0284c7)', admin: 'linear-gradient(135deg,#7A0C0C,#b91c1c)' };
            const grad = gradMap[cm.user_role] || 'linear-gradient(135deg,#7A0C0C,#F2B400)';
            const init = (cm.display_name || 'U').charAt(0).toUpperCase();
            const avHtml = cm.profile_image ?
                `<img src="${cm.profile_image}" class="cm-av" style="width:${sz};height:${sz}" alt="${cm.display_name}" onerror="this.outerHTML='<div class=cm-av-init style=width:${sz};height:${sz};background:${grad};border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;color:#fff;flex-shrink:0>${init}</div>'">` :
                `<div class="cm-av-init" style="width:${sz};height:${sz};background:${grad};border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;color:#fff;flex-shrink:0">${init}</div>`;
            const delBtn   = cm.is_own ? `<button class="cm-act del" onclick="deleteComment(${cm.comment_id})"><i class="fas fa-trash"></i> Delete</button>` : '';
            const replyBtn = !isReply ? `<button class="cm-act" onclick="toggleReply(${cm.comment_id})"><i class="fas fa-reply"></i> Reply</button>` : '';
            const meAv     = `<div class="cm-av-init" style="width:30px;height:30px;background:linear-gradient(135deg,#7A0C0C,#F2B400);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;color:#fff;flex-shrink:0">${ME_INIT}</div>`;
            const replyForm = !isReply ? `<div class="reply-form" id="rf-${cm.comment_id}" style="display:none">${meAv}<div class="write-inner" style="flex:1"><textarea class="write-ta" id="rta-${cm.comment_id}" placeholder="Write a reply…" maxlength="2000" oninput="countChars(this,'rc-${cm.comment_id}')"></textarea><div class="write-footer"><span class="char-count" id="rc-${cm.comment_id}">0 / 2000</span><div style="display:flex;gap:6px"><button class="btn-post" style="background:#6b7280" onclick="toggleReply(${cm.comment_id})">Cancel</button><button class="btn-post" onclick="postReply(${cm.comment_id})"><i class="fas fa-paper-plane"></i> Reply</button></div></div></div></div><div class="cm-replies" id="replies-${cm.comment_id}"></div>` : '';
            return `<div class="cm-item" id="cm-${cm.comment_id}"><div class="cm-top">${avHtml}<div class="cm-body"><div class="cm-meta"><span class="cm-name">${cm.display_name}</span><span class="cm-role ${cm.user_role}">${cm.user_role}</span><span class="cm-time">${cm.time_ago}</span></div><div class="cm-text">${cm.body}</div><div class="cm-actions">${replyBtn}${delBtn}</div>${replyForm}</div></div></div>`;
        }

        function prependComment(cm) { document.getElementById('cmList').insertAdjacentHTML('afterbegin', buildCommentHTML(cm, false)); }
        function appendReply(cm, parentId) { document.getElementById('replies-' + parentId)?.insertAdjacentHTML('beforeend', buildCommentHTML(cm, true)); }
        function updateBadge(delta) { const el = document.getElementById('cmBadge'); if (el) el.textContent = Math.max(0, parseInt(el.textContent||'0') + delta); }
        function loadMore() { document.querySelectorAll('.cm-extra').forEach(el => el.style.display = ''); document.getElementById('loadMoreBtn')?.remove(); }

        /* DOWNLOAD */
        function handleDownload() {
            const btn = document.getElementById('downloadBtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing…';
            Swal.fire({ title: 'Preparing your download…', html: '<p style="color:#6b7280;font-size:.9rem">Please wait while we fetch your file.</p>', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, background: '#fff', color: '#0f1117', didOpen: () => Swal.showLoading() });
            fetch('admin/user_pages/auth/download?u=<?php echo $euserId; ?>&t=<?php echo $etype; ?>&r=<?php echo $enoteId; ?>', { method: 'GET' })
            .then(async res => {
                Swal.close(); btn.disabled = false; btn.innerHTML = '<i class="fas fa-download"></i> Download PDF';
                if (!res.ok) {
                    let msg = 'Download failed. Please try again.';
                    const ct = res.headers.get('Content-Type') || '';
                    if (ct.includes('application/json')) { try { const j = await res.json(); msg = j.message || msg; } catch {} }
                    Swal.fire({ icon: 'error', title: 'Download Failed', text: msg, confirmButtonColor: '#7A0C0C', background: '#fff', color: '#0f1117' });
                    return;
                }
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                const cd = res.headers.get('Content-Disposition') || '';
                const match = cd.match(/filename[^;=]*=['"]?([^'"]+)/i);
                a.href = url; a.download = match ? match[1] : 'download.pdf';
                document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                Swal.fire({ toast: true, position: 'bottom', icon: 'success', title: 'Download started!', showConfirmButton: false, timer: 3000, timerProgressBar: true, background: '#1a0303', color: '#fff', iconColor: '#34d399', customClass: { popup: 'swal-toast-popup' } });
            }).catch(() => {
                Swal.close(); btn.disabled = false; btn.innerHTML = '<i class="fas fa-download"></i> Download PDF';
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not reach the server. Please check your connection and try again.', confirmButtonColor: '#7A0C0C', background: '#fff', color: '#0f1117' });
            });
        }

        /* BOOKMARK */
        let isBookmarked = <?php echo $alreadyBookmarked ? 'true' : 'false'; ?>;
        function handleBookmark() {
            const btn = document.getElementById('bookmarkBtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            fetch('admin/user_pages/auth/bookmark?u=<?php echo $euserId; ?>&t=<?php echo $etype; ?>&r=<?php echo $enoteId; ?>', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(async res => {
                btn.disabled = false;
                let d; try { d = await res.json(); } catch { d = { success: false, message: 'Unexpected server response.' }; }
                if (d.success) {
                    isBookmarked = (d.action === 'saved');
                    if (isBookmarked) { btn.innerHTML = '<i class="fas fa-bookmark"></i> Saved'; btn.style.cssText = 'background:var(--primary);color:#fff;border-color:var(--primary)'; }
                    else { btn.innerHTML = '<i class="far fa-bookmark"></i> Save for Later'; btn.style.cssText = ''; }
                    showToast(d.message || (isBookmarked ? 'Saved to your bookmarks!' : 'Removed from bookmarks.'), 'success');
                } else {
                    btn.innerHTML = isBookmarked ? '<i class="fas fa-bookmark"></i> Saved' : '<i class="far fa-bookmark"></i> Save for Later';
                    Swal.fire({ icon: 'error', title: 'Could Not Save', text: d.message || 'Something went wrong.', confirmButtonColor: '#7A0C0C', background: '#fff', color: '#0f1117' });
                }
            }).catch(() => {
                btn.disabled = false;
                btn.innerHTML = isBookmarked ? '<i class="fas fa-bookmark"></i> Saved' : '<i class="far fa-bookmark"></i> Save for Later';
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not reach the server.', confirmButtonColor: '#7A0C0C', background: '#fff', color: '#0f1117' });
            });
        }

        /* URL param feedback */
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('s') === 'content_banned') {
            Swal.fire({ icon: 'error', title: 'Content Unavailable', html: '<p style="color:#6b7280;font-size:.9rem">This resource has been <strong style="color:#7A0C0C">banned</strong> by an administrator and is no longer accessible.</p>', confirmButtonColor: '#7A0C0C', confirmButtonText: 'Go Back', background: '#fff', color: '#0f1117' }).then(() => { history.back(); });
        }
    </script>
</body>

</html>