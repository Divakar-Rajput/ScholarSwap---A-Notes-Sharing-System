<?php
session_start();
require_once "config/connection.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$adminId = (int)$_SESSION['admin_id'];
$sa = $conn->prepare("SELECT first_name, last_name, role FROM admin_user WHERE admin_id = ? LIMIT 1");
$sa->execute([$adminId]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);
$adminName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: 'Admin';

// ── Reports (pending) ──────────────────────────────────────────
$repQ = $conn->prepare("
    SELECT r.report_id, r.reporter_id, r.resource_id, r.document_type,
           r.reason, r.details, r.status, r.created_at, r.reviewed_at, r.reviewed_by,
           u.username  AS reporter_username,
           u.user_id   AS reporter_user_id,
           COALESCE(
               NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
               u.username
           ) AS reporter_name,
           u.profile_image AS reporter_img,
           CASE
               WHEN r.document_type='note'
                   THEN (SELECT title FROM notes      WHERE n_code = r.resource_id)
               WHEN r.document_type='book'
                   THEN (SELECT title FROM books      WHERE b_code = r.resource_id)
               WHEN r.document_type='newspaper'
                   THEN (SELECT title FROM newspapers WHERE n_code = r.resource_id)
           END AS content_title,
           CASE
               WHEN r.document_type='note'
                   THEN (SELECT n_own.user_id FROM notes      n_own WHERE n_own.n_code = r.resource_id)
               WHEN r.document_type='book'
                   THEN (SELECT b_own.user_id FROM books      b_own WHERE b_own.b_code = r.resource_id)
               WHEN r.document_type='newspaper'
                   THEN (SELECT np_own.admin_id FROM newspapers np_own WHERE np_own.n_code = r.resource_id)
           END AS content_owner_id,
           CASE
               WHEN r.document_type='note'
                   THEN COALESCE(
                       (SELECT CONCAT(s2.first_name,' ',s2.last_name) FROM notes n2
                        JOIN students s2 ON s2.user_id=n2.user_id WHERE n2.n_code=r.resource_id),
                       (SELECT u2.username FROM notes n2 JOIN users u2 ON u2.user_id=n2.user_id WHERE n2.n_code=r.resource_id))
               WHEN r.document_type='book'
                   THEN COALESCE(
                       (SELECT CONCAT(s2.first_name,' ',s2.last_name) FROM books b2
                        JOIN students s2 ON s2.user_id=b2.user_id WHERE b2.b_code=r.resource_id),
                       (SELECT u2.username FROM books b2 JOIN users u2 ON u2.user_id=b2.user_id WHERE b2.b_code=r.resource_id))
               ELSE 'Admin'
           END AS content_owner_name,
           CASE
               WHEN r.document_type='note'
                   THEN (SELECT description FROM notes WHERE n_code = r.resource_id)
               WHEN r.document_type='book'
                   THEN (SELECT description FROM books WHERE b_code = r.resource_id)
               ELSE NULL
           END AS content_description,
           CASE
               WHEN r.document_type='note'
                   THEN (SELECT file_path FROM notes WHERE n_code = r.resource_id)
               WHEN r.document_type='book'
                   THEN (SELECT file_path FROM books WHERE b_code = r.resource_id)
               WHEN r.document_type='newspaper'
                   THEN (SELECT file_path FROM newspapers WHERE n_code = r.resource_id)
           END AS content_file,
           NULL AS content_cover,
           NULL AS content_category,
           NULL AS content_created_at,
           NULL AS content_downloads,
           NULL AS content_views
    FROM reports r
    JOIN users u ON u.user_id = r.reporter_id
    LEFT JOIN students s ON s.user_id = u.user_id
    ORDER BY
        CASE WHEN r.status='pending' THEN 0 ELSE 1 END,
        r.created_at DESC
    LIMIT 50
");
$repQ->execute();
$reports = $repQ->fetchAll(PDO::FETCH_ASSOC);
$pendingReports = array_filter($reports, fn($r) => $r['status'] === 'pending');

// ── Feedback ──────────────────────────────────────────────────
$fbQ = $conn->prepare("
    SELECT f.feedback_id, f.user_id AS f_user_id, f.category, f.subject, f.message,
           f.rating, f.page_context, f.status, f.admin_reply, f.replied_at,
           f.replied_by, f.created_at, f.updated_at,
           u.username, u.user_id AS fb_user_id, u.profile_image,
           COALESCE(
               NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
               u.username, 'Anonymous'
           ) AS display_name
    FROM feedback f
    LEFT JOIN users    u ON u.user_id = f.user_id
    LEFT JOIN students s ON s.user_id = f.user_id
    ORDER BY
        CASE WHEN f.status='new' THEN 0
             WHEN f.status='in_review' THEN 1
             ELSE 2 END,
        f.created_at DESC
    LIMIT 60
");
$fbQ->execute();
$feedbacks = $fbQ->fetchAll(PDO::FETCH_ASSOC);
$newFeedback   = count(array_filter($feedbacks, fn($f) => $f['status'] === 'new'));
$pendingRepCnt = count($pendingReports);

function ago(string $dt): string
{
    $d = time() - strtotime($dt);
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return floor($d / 60)    . 'm ago';
    if ($d < 86400)  return floor($d / 3600)  . 'h ago';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    return date('d M Y', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Reports & Feedback | ScholarSwap Admin</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --blue: #2563eb;
            --blue-s: #dbeafe;
            --blue-d: #1d4ed8;
            --teal: #0d9488;
            --teal-s: #ccfbf1;
            --amber: #d97706;
            --amber-s: #fef3c7;
            --green: #059669;
            --green-s: #d1fae5;
            --red: #dc2626;
            --red-s: #fee2e2;
            --purple: #7c3aed;
            --purple-s: #ede9fe;
            --orange: #ea580c;
            --orange-s: #ffedd5;
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
            --sh2: 0 8px 28px rgba(0, 0, 0, .1);
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            font-size: 14px;
            overflow-x: hidden
        }

        a {
            text-decoration: none;
            color: inherit
        }

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px
        }

        /* ── Page heading ── */
        .pg-head {
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px
        }

        .pg-head h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2
        }

        .pg-head p {
            font-size: .82rem;
            color: var(--text3);
            margin-top: 4px
        }

        /* ── Stat strip ── */
        .stat-strip {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px
        }

        .stat-pill {
            display: flex;
            align-items: center;
            gap: 9px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            padding: 11px 16px;
            box-shadow: var(--sh);
            min-width: 150px
        }

        .stat-pill-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            flex-shrink: 0
        }

        .stat-pill-val {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1
        }

        .stat-pill-lbl {
            font-size: .68rem;
            color: var(--text3);
            margin-top: 2px
        }

        /* ── Main tabs ── */
        .main-tabs {
            display: flex;
            gap: 4px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            padding: 5px;
            margin-bottom: 18px;
            width: fit-content;
            box-shadow: var(--sh)
        }

        .mtab {
            padding: 9px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: .82rem;
            font-weight: 600;
            background: transparent;
            color: var(--text2);
            transition: all .15s;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 7px
        }

        .mtab:hover:not(.on) {
            background: var(--bg)
        }

        .mtab.on {
            background: var(--blue);
            color: #fff;
            box-shadow: 0 3px 10px rgba(37, 99, 235, .3)
        }

        .mtab .cnt {
            background: rgba(255, 255, 255, .25);
            color: #fff;
            font-size: .6rem;
            padding: 1px 6px;
            border-radius: 99px;
            font-weight: 700
        }

        .mtab:not(.on) .cnt {
            background: var(--red-s);
            color: var(--red)
        }

        /* ── Panel ── */
        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 16px;
            width: 1060px;
        }

        .ph {
            padding: 13px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px
        }

        .pt {
            font-family: 'Space Grotesk', sans-serif;
            font-size: .87rem;
            font-weight: 700;
            color: var(--text)
        }

        .ph-sub {
            font-size: .72rem;
            color: var(--text3);
            margin-top: 2px
        }

        /* ── Filter bar ── */
        .filter-bar {
            padding: 10px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: #fafbff
        }

        .filter-select {
            padding: 6px 10px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: .78rem;
            font-family: inherit;
            color: var(--text);
            background: var(--surface);
            cursor: pointer;
            outline: none;
            transition: border-color .15s
        }

        .filter-select:focus {
            border-color: var(--blue)
        }

        .filter-search {
            flex: 1;
            min-width: 180px;
            padding: 6px 12px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: .78rem;
            font-family: inherit;
            color: var(--text);
            background: var(--surface);
            outline: none;
            transition: border-color .15s
        }

        .filter-search:focus {
            border-color: var(--blue)
        }

        .filter-label {
            font-size: .72rem;
            font-weight: 600;
            color: var(--text3);
            white-space: nowrap
        }

        /* ── Table ── */
        .panel {
            overflow: visible
        }

        .tw {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 0 0 var(--r2) var(--r2)
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap
        }

        thead th {
            padding: 9px 11px;
            background: var(--bg);
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
            text-align: left
        }

        tbody td {
            padding: 10px 11px;
            font-size: .8rem;
            color: var(--text2);
            border-bottom: 1px solid var(--border);
            vertical-align: middle
        }

        tbody tr:last-child td {
            border-bottom: none
        }

        tbody tr:hover td {
            background: #fafbff
        }

        /* truncate long text columns */
        .tc {
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .act {
            display: flex;
            gap: 4px;
            flex-wrap: nowrap
        }

        .btn {
            flex-shrink: 0
        }

        .filtered-out {
            display: none !important
        }

        /* ── Badges ── */
        .bdg {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: .63rem;
            font-weight: 700;
            white-space: nowrap
        }

        .bdg-pending {
            background: var(--amber-s);
            color: #92400e
        }

        .bdg-actioned {
            background: var(--green-s);
            color: #065f46
        }

        .bdg-dismissed {
            background: var(--bg);
            color: var(--text3)
        }

        .bdg-note {
            background: var(--blue-s);
            color: var(--blue)
        }

        .bdg-book {
            background: var(--teal-s);
            color: var(--teal)
        }

        .bdg-newspaper {
            background: var(--purple-s);
            color: var(--purple)
        }

        .bdg-new {
            background: #fef3c7;
            color: #92400e
        }

        .bdg-in_review {
            background: var(--blue-s);
            color: var(--blue)
        }

        .bdg-resolved {
            background: var(--green-s);
            color: #065f46
        }

        .bdg-closed {
            background: var(--bg);
            color: var(--text3)
        }

        /* ── Action buttons ── */
        .act {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            font-size: .71rem;
            font-weight: 700;
            transition: all .13s;
            white-space: nowrap;
            font-family: inherit
        }

        .btn:active {
            transform: scale(.96)
        }

        .btn-ban {
            background: var(--red-s);
            color: var(--red)
        }

        .btn-ban:hover {
            background: var(--red);
            color: #fff
        }

        .btn-warn {
            background: var(--amber-s);
            color: #92400e
        }

        .btn-warn:hover {
            background: var(--amber);
            color: #fff
        }

        .btn-msg {
            background: var(--blue-s);
            color: var(--blue)
        }

        .btn-msg:hover {
            background: var(--blue);
            color: #fff
        }

        .btn-dismiss {
            background: var(--green-s);
            color: var(--green)
        }

        .btn-dismiss:hover {
            background: var(--green);
            color: #fff
        }

        .btn-reply {
            background: var(--purple-s);
            color: var(--purple)
        }

        .btn-reply:hover {
            background: var(--purple);
            color: #fff
        }

        .btn-resolve {
            background: var(--green-s);
            color: var(--green)
        }

        .btn-resolve:hover {
            background: var(--green);
            color: #fff
        }

        .btn-view {
            background: var(--bg);
            color: var(--text2);
            border: 1px solid var(--border)
        }

        .btn-view:hover {
            background: var(--text);
            color: #fff;
            border-color: var(--text)
        }

        /* ── User cell ── */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 8px
        }

        .u-av {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .65rem;
            font-weight: 700;
            color: #fff;
            overflow: hidden
        }

        .u-av img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%
        }

        .u-name {
            font-size: .82rem;
            font-weight: 600;
            color: var(--text)
        }

        .u-sub {
            font-size: .68rem;
            color: var(--text3);
            margin-top: 1px
        }

        /* ── Rating stars ── */
        .stars {
            color: #f59e0b;
            letter-spacing: 1px;
            font-size: .82rem
        }

        /* ── Message thread (feedback) ── */
        .msg-thread {
            padding: 14px 18px;
            background: #f8faff;
            border-top: 1px solid var(--border)
        }

        .admin-reply-box {
            background: var(--surface);
            border: 1.5px solid var(--blue-s);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: .82rem;
            color: var(--text2);
            font-style: italic;
            line-height: 1.6
        }

        .admin-reply-box strong {
            color: var(--blue);
            font-style: normal
        }

        /* ── Empty state ── */
        .empty {
            padding: 40px;
            text-align: center;
            color: var(--text3);
            font-size: .82rem
        }

        .empty i {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
            opacity: .25
        }

        /* ══════════════════════════════════════════════════════════
           SLIDE-OVER DRAWER — Message / Reply panel
        ══════════════════════════════════════════════════════════ */
        .drawer-overlay {
            position: fixed;
            inset: 0;
            z-index: 9000;
            background: rgba(15, 23, 42, .45);
            backdrop-filter: blur(4px);
            opacity: 0;
            visibility: hidden;
            transition: opacity .24s, visibility .24s
        }

        .drawer-overlay.open {
            opacity: 1;
            visibility: visible
        }

        .drawer {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: min(500px, 96vw);
            background: var(--surface);
            box-shadow: -8px 0 40px rgba(0, 0, 0, .14);
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform .28s cubic-bezier(.34, 1.1, .64, 1);
            z-index: 9001
        }

        .drawer-overlay.open .drawer {
            transform: translateX(0)
        }

        .drawer::before {
            content: '';
            display: block;
            height: 4px;
            flex-shrink: 0;
            background: linear-gradient(90deg, #2563eb, #6366f1, #0d9488)
        }

        .drawer-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0
        }

        .drawer-icon {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            flex-shrink: 0
        }

        .drawer-head h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: var(--text);
            margin: 0
        }

        .drawer-sub {
            font-size: .72rem;
            color: var(--text3);
            margin-top: 2px
        }

        .drawer-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            background: var(--bg);
            color: var(--text2);
            font-size: .88rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            transition: all .14s;
            flex-shrink: 0
        }

        .drawer-close:hover {
            background: var(--red-s);
            color: var(--red)
        }

        .drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            scrollbar-width: thin;
            scrollbar-color: var(--border2) transparent
        }

        .drawer-body::-webkit-scrollbar {
            width: 4px
        }

        .drawer-body::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px
        }

        .drawer-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            background: #fafafa;
            flex-shrink: 0
        }

        /* Context card inside drawer */
        .ctx-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: .82rem;
            color: var(--text2);
            line-height: 1.65
        }

        .ctx-card strong {
            color: var(--text);
            display: block;
            font-size: .85rem;
            margin-bottom: 4px
        }

        .ctx-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 5px;
            font-size: .76rem;
            color: var(--text3)
        }

        .ctx-row i {
            font-size: .65rem;
            color: var(--text3)
        }

        /* Form elements */
        .form-group {
            margin-bottom: 14px
        }

        .form-label {
            font-size: .72rem;
            font-weight: 700;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 5px;
            display: block
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: .84rem;
            font-family: inherit;
            color: var(--text);
            background: var(--surface);
            outline: none;
            transition: border-color .18s
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--blue)
        }

        .form-textarea {
            resize: vertical;
            min-height: 110px;
            line-height: 1.6
        }

        /* Target selector (for reports) */
        .target-row {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
            flex-wrap: wrap
        }

        .target-chip {
            padding: 6px 14px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            background: var(--bg);
            color: var(--text2);
            font-size: .76rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 6px
        }

        .target-chip input {
            display: none
        }

        .target-chip.selected {
            border-color: var(--blue);
            background: var(--blue-s);
            color: var(--blue)
        }

        /* Notif type pills */
        .notif-types {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px
        }

        .ntype {
            padding: 4px 11px;
            border-radius: 7px;
            border: 1.5px solid var(--border);
            background: var(--bg);
            font-size: .72rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--text2);
            transition: all .14s
        }

        .ntype.sel {
            border-color: currentColor
        }

        .ntype-warning.sel {
            background: var(--red-s);
            color: var(--red);
            border-color: var(--red)
        }

        .ntype-admin_message.sel {
            background: var(--purple-s);
            color: var(--purple);
            border-color: var(--purple)
        }

        .ntype-info.sel {
            background: var(--blue-s);
            color: var(--blue);
            border-color: var(--blue)
        }

        /* Send button */
        .btn-send {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, var(--blue), var(--blue-d));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: .86rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .18s;
            box-shadow: 0 4px 14px rgba(37, 99, 235, .25)
        }

        .btn-send:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, .35)
        }

        .btn-send:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none
        }

        /* Previous reply display */
        .prev-reply {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 9px;
            padding: 11px 13px;
            margin-bottom: 14px;
            font-size: .81rem;
            color: #065f46;
            line-height: 1.6
        }

        .prev-reply strong {
            display: block;
            font-size: .72rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: #059669
        }

        /* Reason display card */
        .reason-card {
            background: var(--red-s);
            border: 1px solid #fca5a5;
            border-radius: 9px;
            padding: 10px 13px;
            margin-bottom: 14px;
            font-size: .8rem;
            color: #991b1b;
            line-height: 1.6
        }

        .reason-card strong {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 3px;
            color: var(--red)
        }

        /* ── Quick Templates ── */
        .tpl-toggle-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .72rem;
            font-weight: 700;
            color: var(--blue);
            cursor: pointer;
            background: var(--blue-s);
            border: 1.5px solid #bfdbfe;
            border-radius: 8px;
            padding: 5px 12px;
            font-family: inherit;
            transition: all .15s;
            white-space: nowrap
        }

        .tpl-toggle-btn:hover {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue)
        }

        .tpl-toggle-btn i.fa-chevron-down {
            transition: transform .22s;
            font-size: .6rem
        }

        .tpl-toggle-btn.open i.fa-chevron-down {
            transform: rotate(180deg)
        }

        .tpl-panel {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            pointer-events: none;
            transition: max-height .3s ease, opacity .25s, margin .25s;
            margin-top: 0
        }

        .tpl-panel.open {
            max-height: 700px;
            opacity: 1;
            pointer-events: all;
            margin-top: 8px
        }

        .tpl-chip {
            padding: 8px 10px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            background: var(--surface);
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            transition: all .14s;
            display: flex;
            flex-direction: column;
            gap: 3px
        }

        .tpl-chip:hover {
            border-color: var(--blue);
            background: var(--blue-s);
            transform: translateY(-1px)
        }

        .tpl-chip-head {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .72rem;
            font-weight: 700;
            color: var(--text)
        }

        .tpl-chip:hover .tpl-chip-head {
            color: var(--blue)
        }

        .tpl-chip-preview {
            font-size: .63rem;
            color: var(--text3);
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .tpl-section-head {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text3);
            grid-column: 1/-1;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid var(--border)
        }

        .tpl-section-head:first-child {
            border-top: none;
            margin-top: 0;
            padding-top: 0
        }

        /* ══════════════════════════════════════════════════════════
           CONTENT VIEW MODAL
        ══════════════════════════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9500;
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(6px);
            opacity: 0;
            visibility: hidden;
            transition: opacity .22s, visibility .22s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px
        }

        .modal-overlay.open {
            opacity: 1;
            visibility: visible
        }

        .modal-box {
            background: var(--surface);
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .2);
            width: 100%;
            max-width: 680px;
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            transform: scale(.96) translateY(12px);
            transition: transform .26s cubic-bezier(.34, 1.2, .64, 1);
            overflow: hidden
        }

        .modal-overlay.open .modal-box {
            transform: scale(1) translateY(0)
        }

        .modal-accent {
            height: 4px;
            background: linear-gradient(90deg, #2563eb, #6366f1, #0d9488);
            flex-shrink: 0
        }

        .modal-head {
            padding: 18px 22px 14px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            flex-shrink: 0
        }

        .modal-cover {
            width: 54px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border);
            flex-shrink: 0;
            background: var(--bg)
        }

        .modal-cover-placeholder {
            width: 54px;
            height: 70px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--blue-s), var(--purple-s));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0
        }

        .modal-head-info {
            flex: 1;
            min-width: 0
        }

        .modal-head-info h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.3;
            margin-bottom: 6px;
            word-break: break-word
        }

        .modal-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 6px
        }

        .modal-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            background: var(--bg);
            color: var(--text2);
            font-size: .9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .14s;
            flex-shrink: 0
        }

        .modal-close:hover {
            background: var(--red-s);
            color: var(--red)
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            scrollbar-width: thin;
            scrollbar-color: var(--border2) transparent
        }

        .modal-body::-webkit-scrollbar {
            width: 4px
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px
        }

        /* Content preview sections */
        .modal-section {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border)
        }

        .modal-section:last-child {
            border-bottom: none
        }

        .modal-section-title {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text3);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px
        }

        .modal-section-title i {
            font-size: .6rem
        }

        .modal-desc {
            font-size: .84rem;
            color: var(--text2);
            line-height: 1.7;
            background: var(--bg);
            border-radius: 9px;
            padding: 12px 14px;
            border: 1px solid var(--border)
        }

        .modal-desc.empty-desc {
            color: var(--text3);
            font-style: italic
        }

        /* Stats row */
        .modal-stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .modal-stat {
            flex: 1;
            min-width: 150px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            text-align: center
        }

        .modal-stat-val {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text)
        }

        .modal-stat-lbl {
            font-size: .62rem;
            color: var(--text3);
            margin-top: 2px
        }

        /* Report info */
        .report-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px
        }

        .report-info-item {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 10px 12px
        }

        .report-info-item-label {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text3);
            margin-bottom: 4px
        }

        .report-info-item-val {
            font-size: .82rem;
            font-weight: 600;
            color: var(--text)
        }

        /* Alert banner inside modal */
        .modal-alert {
            margin: 14px 22px 0;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: .8rem;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px
        }

        .modal-alert i {
            font-size: .85rem;
            margin-top: 1px;
            flex-shrink: 0
        }

        .modal-alert-warn {
            background: var(--amber-s);
            border: 1px solid #fcd34d;
            color: #78350f
        }

        .modal-alert-red {
            background: var(--red-s);
            border: 1px solid #fca5a5;
            color: #7f1d1d
        }

        /* File preview link */
        .file-preview-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            background: var(--blue-s);
            color: var(--blue);
            border-radius: 9px;
            font-size: .8rem;
            font-weight: 700;
            text-decoration: none;
            transition: all .15s;
            border: 1px solid #bfdbfe
        }

        .file-preview-link:hover {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue)
        }

        /* Modal footer action bar */
        .modal-footer {
            padding: 13px 22px;
            border-top: 1px solid var(--border);
            background: #fafafa;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            flex-shrink: 0
        }

        .modal-footer .btn {
            flex: 1;
            justify-content: center;
            padding: 9px 14px;
            font-size: .78rem
        }

        /* ── General Responsive ── */
        @media(max-width: 900px) {
            .pg-head {
                flex-direction: column
            }

            .stat-strip {
                gap: 8px
            }

            .stat-pill {
                min-width: 120px
            }
        }

        @media(max-width: 600px) {
            .main-tabs {
                flex-wrap: wrap;
                width: 100%
            }

            .mtab {
                flex: 1;
                justify-content: center;
                padding: 8px 10px
            }

            .stat-pill {
                flex: 1;
                min-width: 0
            }

            .report-info-grid {
                grid-template-columns: 1fr
            }

            .modal-stats {
                flex-direction: column
            }

            .modal-box {
                border-radius: 14px;
                margin: 8px
            }

            .modal-head {
                padding: 14px 16px 12px;
                gap: 10px
            }

            .modal-head-info h2 {
                font-size: .92rem
            }

            .modal-section {
                padding: 12px 16px
            }

            .modal-footer {
                padding: 10px 16px;
                gap: 5px
            }

            .modal-footer .btn {
                font-size: .7rem;
                padding: 8px 8px
            }

            .filter-bar {
                gap: 6px;
                padding: 8px 12px
            }

            .filter-select {
                font-size: .74rem
            }

            .filter-search {
                font-size: .74rem;
                min-width: 120px
            }

            .pg-head h1 {
                font-size: 1.1rem
            }

            thead th,
            tbody td {
                padding: 8px 8px;
                font-size: .75rem
            }

            .btn {
                font-size: .66rem;
                padding: 3px 7px
            }
        }
    </style>
</head>

<body>

    <?php include_once('sidebar.php'); ?>
    <?php include_once('adminheader.php'); ?>

    <div class="main">
        <div class="pg">

            <!-- ── Page heading ── -->
            <div class="pg-head">
                <div>
                    <h1><i class="fas fa-flag" style="color:var(--red);margin-right:8px"></i>Reports &amp; Feedback</h1>
                    <p>Review user-submitted content reports, send messages/notifications, and reply to feedback.</p>
                </div>
            </div>

            <!-- ── Stat strip ── -->
            <div class="stat-strip">
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--red-s);color:var(--red)"><i class="fas fa-flag"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo $pendingRepCnt; ?></div>
                        <div class="stat-pill-lbl">Pending Reports</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--amber-s);color:var(--amber)"><i class="fas fa-comments"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo $newFeedback; ?></div>
                        <div class="stat-pill-lbl">New Feedback</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--blue-s);color:var(--blue)"><i class="fas fa-list"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo count($reports); ?></div>
                        <div class="stat-pill-lbl">Total Reports</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--green-s);color:var(--green)"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo count(array_filter($reports, fn($r) => $r['status'] !== 'pending')); ?></div>
                        <div class="stat-pill-lbl">Actioned Reports</div>
                    </div>
                </div>
            </div>

            <!-- ── Main tab switcher ── -->
            <div class="main-tabs">
                <button class="mtab on" onclick="switchTab('reports',this)">
                    <i class="fas fa-flag"></i> Content Reports
                    <?php if ($pendingRepCnt): ?><span class="cnt"><?php echo $pendingRepCnt; ?></span><?php endif; ?>
                </button>
                <button class="mtab" onclick="switchTab('feedback',this)">
                    <i class="fas fa-comment-dots"></i> User Feedback
                    <?php if ($newFeedback): ?><span class="cnt"><?php echo $newFeedback; ?></span><?php endif; ?>
                </button>
            </div>

            <!-- ══════════════════════════════════════════
         TAB: CONTENT REPORTS
    ══════════════════════════════════════════ -->
            <div id="tab-reports">
                <div class="panel">
                    <div class="ph">
                        <div>
                            <div class="pt"><i class="fas fa-flag" style="color:var(--red);margin-right:6px"></i>Content Reports</div>
                            <div class="ph-sub">Review flagged content — click <strong>View</strong> to preview content before taking action</div>
                        </div>
                    </div>
                    <div class="filter-bar">
                        <span class="filter-label">Filter:</span>
                        <select class="filter-select" id="repStatusFilter" onchange="filterReports()">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="actioned">Actioned</option>
                            <option value="dismissed">Dismissed</option>
                        </select>
                        <select class="filter-select" id="repTypeFilter" onchange="filterReports()">
                            <option value="">All Types</option>
                            <option value="note">Note</option>
                            <option value="book">Book</option>
                            <option value="newspaper">Newspaper</option>
                        </select>
                        <input class="filter-search" type="text" id="repSearch" placeholder="Search by content title or reporter…" oninput="filterReports()">
                    </div>

                    <?php if (empty($reports)): ?>
                        <div class="empty"><i class="fas fa-flag"></i>No reports found. Everything looks clean!</div>
                    <?php else: ?>
                        <div class="tw">
                            <table id="reportsTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Reported Content</th>
                                        <th>Type</th>
                                        <th>Reason</th>
                                        <th>Details</th>
                                        <th>Reporter</th>
                                        <th>Owner</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $i => $rep):
                                        $reasonMap = [
                                            'spam'                   => ['#92400e', 'var(--amber-s)', 'Spam'],
                                            'inappropriate_content'  => ['#991b1b', 'var(--red-s)', 'Inappropriate'],
                                            'copyright_violation'    => ['#991b1b', 'var(--red-s)', 'Copyright'],
                                            'misleading_information' => ['#92400e', 'var(--amber-s)', 'Misleading'],
                                            'wrong_category'         => ['#1e40af', 'var(--blue-s)', 'Wrong Category'],
                                            'other'                  => ['#475569', 'var(--bg)', 'Other'],
                                        ];
                                        [$rColor, $rBg, $rLabel] = $reasonMap[$rep['reason']] ?? ['#475569', 'var(--bg)', ucfirst($rep['reason'])];
                                        $typeCls = 'bdg-' . ($rep['document_type'] ?? 'note');
                                        $typeIcon = match ($rep['document_type'] ?? 'note') {
                                            'book' => 'fa-book',
                                            'newspaper' => 'fa-newspaper',
                                            default => 'fa-file-lines'
                                        };
                                        $ownerNameRaw = htmlspecialchars($rep['content_owner_name'] ?? 'Unknown');
                                        $contentTitleRaw = $rep['content_title'] ?? '—';
                                        $reporterNameRaw = $rep['reporter_name'] ?? $rep['reporter_username'] ?? 'Unknown';
                                        $detailsRaw = $rep['details'] ?? '';
                                        // encode for JS data attributes
                                        $jsData = htmlspecialchars(json_encode([
                                            'report_id'          => (int)$rep['report_id'],
                                            'resource_id'        => $rep['resource_id'],
                                            'doc_type'           => $rep['document_type'],
                                            'content_title'      => $contentTitleRaw,
                                            'reason'             => $rep['reason'],
                                            'details'            => $detailsRaw,
                                            'reporter_id'        => (int)$rep['reporter_user_id'],
                                            'reporter_name'      => $reporterNameRaw,
                                            'owner_id'           => (int)($rep['content_owner_id'] ?? 0),
                                            'owner_name'         => $rep['content_owner_name'] ?? 'Unknown',
                                            'status'             => $rep['status'],
                                            'content_description' => $rep['content_description'] ?? '',
                                            'content_file'       => 'http://localhost/ScholarSwap/' . $rep['content_file'] ?? '',
                                            'content_cover'      => $rep['content_cover'] ?? '',
                                            'content_category'   => $rep['content_category'] ?? '',
                                            'content_created_at' => $rep['content_created_at'] ?? '',
                                            'content_downloads'  => (int)($rep['content_downloads'] ?? 0),
                                            'content_views'      => (int)($rep['content_views'] ?? 0),
                                            'reporter_username'  => $rep['reporter_username'] ?? '',
                                            'reporter_img'       => $rep['reporter_img'] ?? '',
                                            'report_created_at'  => $rep['created_at'] ?? '',
                                        ]), ENT_QUOTES);
                                    ?>
                                        <tr class="rep-row"
                                            data-status="<?php echo htmlspecialchars($rep['status']); ?>"
                                            data-type="<?php echo htmlspecialchars($rep['document_type'] ?? ''); ?>"
                                            data-search="<?php echo strtolower(htmlspecialchars($contentTitleRaw . ' ' . $reporterNameRaw)); ?>">
                                            <td style="color:var(--text3);font-size:.72rem"><?php echo $i + 1; ?></td>
                                            <td class="tc" title="<?php echo htmlspecialchars($contentTitleRaw); ?>">
                                                <span style="font-weight:600;color:var(--text)"><?php echo htmlspecialchars($contentTitleRaw); ?></span>
                                            </td>
                                            <td>
                                                <span class="bdg <?php echo $typeCls; ?>">
                                                    <i class="fas <?php echo $typeIcon; ?>" style="font-size:.55rem"></i>
                                                    <?php echo ucfirst($rep['document_type'] ?? '—'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="bdg" style="background:<?php echo $rBg; ?>;color:<?php echo $rColor; ?>">
                                                    <?php echo $rLabel; ?>
                                                </span>
                                            </td>
                                            <td class="tc" title="<?php echo htmlspecialchars($detailsRaw); ?>">
                                                <?php echo $detailsRaw ? htmlspecialchars($detailsRaw) : '<span style="color:var(--text3)">—</span>'; ?>
                                            </td>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="u-av">
                                                        <?php if (!empty($rep['reporter_img'])): ?>
                                                            <img src="<?php echo htmlspecialchars($rep['reporter_img']); ?>" onerror="this.remove()">
                                                        <?php endif; ?>
                                                        <?php echo strtoupper(substr($reporterNameRaw, 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="u-name"><?php echo htmlspecialchars($reporterNameRaw); ?></div>
                                                        <div class="u-sub">@<?php echo htmlspecialchars($rep['reporter_username'] ?? ''); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-size:.78rem"><?php echo htmlspecialchars($ownerNameRaw); ?></td>
                                            <td>
                                                <span class="bdg bdg-<?php echo htmlspecialchars($rep['status']); ?>">
                                                    <?php echo ucfirst($rep['status']); ?>
                                                </span>
                                            </td>
                                            <td style="font-size:.75rem;color:var(--text3)">
                                                <?php echo ago($rep['created_at']); ?>
                                            </td>
                                            <td>
                                                <div class="act">
                                                    <!-- ★ NEW: View button — always first -->
                                                    <button class="btn btn-view" title="Preview reported content"
                                                        onclick="openContentView(<?php echo $jsData; ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-msg" title="Message Reporter or Owner"
                                                        onclick="openReportDrawer(<?php echo $jsData; ?>, 'message')">
                                                        <i class="fas fa-envelope"></i> Message
                                                    </button>
                                                    <button class="btn btn-warn" title="Send Warning Notification"
                                                        onclick="openReportDrawer(<?php echo $jsData; ?>, 'warn')">
                                                        <i class="fas fa-triangle-exclamation"></i> Warn
                                                    </button>
                                                    <?php if ($rep['status'] === 'pending'): ?>
                                                        <button class="btn btn-ban" title="Ban this content"
                                                            onclick="banContent(<?php echo (int)$rep['report_id']; ?>,'<?php echo $rep['document_type']; ?>','<?php echo htmlspecialchars(addslashes($rep['resource_id'] ?? '')); ?>',<?php echo (int)($rep['content_owner_id'] ?? 0); ?>,'<?php echo htmlspecialchars(addslashes($contentTitleRaw)); ?>')">
                                                            <i class="fas fa-ban"></i> Ban
                                                        </button>
                                                        <button class="btn btn-dismiss" title="Dismiss report"
                                                            onclick="dismissReport(<?php echo (int)$rep['report_id']; ?>)">
                                                            <i class="fas fa-check"></i> Dismiss
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
         TAB: USER FEEDBACK
    ══════════════════════════════════════════ -->
            <div id="tab-feedback" style="display:none">
                <div class="panel">
                    <div class="ph">
                        <div>
                            <div class="pt"><i class="fas fa-comment-dots" style="color:var(--blue);margin-right:6px"></i>User Feedback</div>
                            <div class="ph-sub">Read user feedback, reply with a message, and update status</div>
                        </div>
                    </div>
                    <div class="filter-bar">
                        <span class="filter-label">Filter:</span>
                        <select class="filter-select" id="fbStatusFilter" onchange="filterFeedback()">
                            <option value="">All Statuses</option>
                            <option value="new">New</option>
                            <option value="in_review">In Review</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                        <select class="filter-select" id="fbCatFilter" onchange="filterFeedback()">
                            <option value="">All Categories</option>
                            <option value="general">General</option>
                            <option value="bug_report">Bug Report</option>
                            <option value="feature_request">Feature Request</option>
                            <option value="content_quality">Content Quality</option>
                            <option value="ui_ux">UI / UX</option>
                            <option value="performance">Performance</option>
                            <option value="other">Other</option>
                        </select>
                        <input class="filter-search" type="text" id="fbSearch" placeholder="Search by subject or username…" oninput="filterFeedback()">
                    </div>

                    <?php if (empty($feedbacks)): ?>
                        <div class="empty"><i class="fas fa-comments"></i>No feedback yet.</div>
                    <?php else: ?>
                        <div class="tw">
                            <table id="feedbackTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>User</th>
                                        <th>Category</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Admin Reply</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($feedbacks as $i => $fb):
                                        $catColors = [
                                            'bug_report'      => 'bdg-rejected',
                                            'feature_request' => 'bdg-note',
                                            'content_quality' => 'bdg-book',
                                            'ui_ux'           => 'bdg-newspaper',
                                            'performance'     => 'bdg-pending',
                                            'general'         => 'bdg-student',
                                        ];
                                        $stars = (int)($fb['rating'] ?? 0);
                                        $displayName = $fb['display_name'] ?? 'Anonymous';
                                        $fbStatus = $fb['status'] ?? 'new';
                                        $hasReply = !empty($fb['admin_reply']);
                                        $fbJsData = htmlspecialchars(json_encode([
                                            'feedback_id'  => (int)$fb['feedback_id'],
                                            'user_id'      => (int)($fb['fb_user_id'] ?? 0),
                                            'username'     => $fb['username'] ?? '',
                                            'display_name' => $displayName,
                                            'subject'      => $fb['subject'] ?? '',
                                            'message'      => $fb['message'] ?? '',
                                            'category'     => $fb['category'] ?? 'general',
                                            'rating'       => $stars,
                                            'status'       => $fbStatus,
                                            'admin_reply'  => $fb['admin_reply'] ?? '',
                                            'replied_at'   => $fb['replied_at'] ?? '',
                                        ]), ENT_QUOTES);
                                    ?>
                                        <tr class="fb-row"
                                            data-status="<?php echo htmlspecialchars($fbStatus); ?>"
                                            data-cat="<?php echo htmlspecialchars($fb['category'] ?? ''); ?>"
                                            data-search="<?php echo strtolower(htmlspecialchars(($fb['subject'] ?? '') . ' ' . ($fb['username'] ?? ''))); ?>">
                                            <td style="color:var(--text3);font-size:.72rem"><?php echo $i + 1; ?></td>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="u-av">
                                                        <?php if (!empty($fb['profile_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($fb['profile_image']); ?>" onerror="this.remove()">
                                                        <?php endif; ?>
                                                        <?php echo strtoupper(substr($displayName, 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="u-name"><?php echo htmlspecialchars($displayName); ?></div>
                                                        <div class="u-sub">@<?php echo htmlspecialchars($fb['username'] ?? 'guest'); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="bdg <?php echo $catColors[$fb['category'] ?? ''] ?? 'bdg-note'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $fb['category'] ?? 'general')); ?>
                                                </span>
                                            </td>
                                            <td class="tc" title="<?php echo htmlspecialchars($fb['subject'] ?? ''); ?>" style="font-size:.8rem">
                                                <?php echo htmlspecialchars($fb['subject'] ?: '—'); ?>
                                            </td>
                                            <td class="tc" title="<?php echo htmlspecialchars($fb['message'] ?? ''); ?>" style="font-size:.77rem">
                                                <?php echo htmlspecialchars($fb['message'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <?php if ($stars > 0): ?>
                                                    <span class="stars"><?php echo str_repeat('★', $stars) . str_repeat('☆', 5 - $stars); ?></span>
                                                <?php else: ?>
                                                    <span style="color:var(--text3)">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="bdg bdg-<?php echo $fbStatus; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $fbStatus)); ?>
                                                </span>
                                            </td>
                                            <td style="font-size:.77rem">
                                                <?php if ($hasReply): ?>
                                                    <span style="color:var(--green);font-weight:600">
                                                        <i class="fas fa-check-circle" style="font-size:.65rem"></i> Replied
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:var(--text3)">No reply</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:.75rem;color:var(--text3)"><?php echo ago($fb['created_at']); ?></td>
                                            <td>
                                                <div class="act">
                                                    <button class="btn btn-reply" onclick="openFeedbackDrawer(<?php echo $fbJsData; ?>)">
                                                        <i class="fas fa-reply"></i> Reply
                                                    </button>
                                                    <?php if ($fbStatus !== 'resolved' && $fbStatus !== 'closed'): ?>
                                                        <button class="btn btn-resolve" onclick="resolveFeedback(<?php echo (int)$fb['feedback_id']; ?>)">
                                                            <i class="fas fa-check"></i> Resolve
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-msg" onclick="openFeedbackDrawer(<?php echo $fbJsData; ?>, true)">
                                                        <i class="fas fa-bell"></i> Notify
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /pg -->
    </div><!-- /main -->

    <!-- ══════════════════════════════════════════════════════════════
     MODAL: Content View / Preview
══════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="contentViewOverlay">
        <div class="modal-box" id="contentViewBox">
            <div class="modal-accent"></div>

            <!-- Header -->
            <div class="modal-head">
                <div id="cvCoverWrap">
                    <div class="modal-cover-placeholder" id="cvCoverPlaceholder"><i class="fas fa-file-lines"></i></div>
                    <img class="modal-cover" id="cvCoverImg" src="" alt="Cover" style="display:none" onerror="this.style.display='none';document.getElementById('cvCoverPlaceholder').style.display='flex'">
                </div>
                <div class="modal-head-info">
                    <h2 id="cvTitle">Content Title</h2>
                    <div class="modal-meta-row">
                        <span class="bdg" id="cvTypeBdg"><i class="fas fa-file-lines" style="font-size:.55rem"></i> Note</span>
                        <span class="bdg" id="cvStatusBdg" style="background:var(--amber-s);color:#92400e">Pending</span>
                        <span class="bdg" id="cvCatBdg" style="background:var(--bg);color:var(--text3)"></span>
                    </div>
                    <div style="font-size:.75rem;color:var(--text3);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <span><i class="fas fa-user" style="margin-right:4px"></i>Owner: <strong id="cvOwner" style="color:var(--text2)">—</strong></span>
                        <span><i class="fas fa-calendar" style="margin-right:4px"></i>Uploaded: <strong id="cvUploaded" style="color:var(--text2)">—</strong></span>
                    </div>
                </div>
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="modal-body" id="cvBody">

                <!-- Report alert banner -->
                <div class="modal-alert modal-alert-red" id="cvAlertBanner">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div>
                        <strong style="display:block;font-size:.78rem;margin-bottom:2px">Reported Content</strong>
                        <span id="cvAlertText" style="font-size:.78rem"></span>
                    </div>
                </div>

                <!-- Stats -->
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-chart-bar"></i> Content Statistics</div>
                    <div class="modal-stats">
                        <div class="modal-stat">
                            <div class="modal-stat-val" id="cvViews">—</div>
                            <div class="modal-stat-lbl">Views</div>
                        </div>
                        <div class="modal-stat">
                            <div class="modal-stat-val" id="cvDownloads">—</div>
                            <div class="modal-stat-lbl">Downloads</div>
                        </div>
                        <div class="modal-stat">
                            <div class="modal-stat-val" id="cvReportId">—</div>
                            <div class="modal-stat-lbl">Report #</div>
                        </div>
                        <div class="modal-stat">
                            <div class="modal-stat-val" id="cvResourceId">—</div>
                            <div class="modal-stat-lbl">Resource ID</div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-align-left"></i> Content Description</div>
                    <div class="modal-desc" id="cvDescription">No description available.</div>
                </div>

                <!-- Report Details -->
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-flag"></i> Report Details</div>
                    <div class="report-info-grid">
                        <div class="report-info-item">
                            <div class="report-info-item-label">Reported By</div>
                            <div class="report-info-item-val" id="cvReporter">—</div>
                        </div>
                        <div class="report-info-item">
                            <div class="report-info-item-label">Report Reason</div>
                            <div class="report-info-item-val" id="cvReason">—</div>
                        </div>
                        <div class="report-info-item" style="grid-column:1/-1" id="cvDetailsItem">
                            <div class="report-info-item-label">Reporter's Details</div>
                            <div class="report-info-item-val" id="cvDetails" style="font-weight:400;font-size:.82rem;color:var(--text2);line-height:1.6">—</div>
                        </div>
                        <div class="report-info-item">
                            <div class="report-info-item-label">Report Date</div>
                            <div class="report-info-item-val" id="cvReportDate">—</div>
                        </div>
                        <div class="report-info-item">
                            <div class="report-info-item-label">Current Status</div>
                            <div class="report-info-item-val" id="cvReportStatus">—</div>
                        </div>
                    </div>
                </div>

                <!-- File / Open Link -->
                <div class="modal-section" id="cvFileSection">
                    <div class="modal-section-title"><i class="fas fa-file-pdf"></i> Source File</div>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <a id="cvFileLink" href="#" target="_blank"
                            style="display:inline-flex;align-items:center;gap:9px;padding:11px 20px;
                                  background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;
                                  border-radius:10px;font-size:.85rem;font-weight:700;text-decoration:none;
                                  box-shadow:0 4px 14px rgba(37,99,235,.3);transition:all .18s;border:none;cursor:pointer"
                            onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 8px 20px rgba(37,99,235,.4)'"
                            onmouseout="this.style.transform='';this.style.boxShadow='0 4px 14px rgba(37,99,235,.3)'">
                            <i class="fas fa-file-arrow-up" style="font-size:1rem"></i>
                            Open File in Browser
                        </a>
                        <span id="cvFileNote" style="font-size:.72rem;color:var(--text3);line-height:1.4">
                            Opens the original uploaded file in a new tab.<br>
                            Read the full content before taking action.
                        </span>
                    </div>
                </div>

                <!-- No file fallback -->
                <div class="modal-section" id="cvNoFileSection" style="display:none">
                    <div class="modal-section-title"><i class="fas fa-file"></i> Source File</div>
                    <div style="background:var(--bg);border:1.5px dashed var(--border2);border-radius:10px;
                                padding:16px 18px;display:flex;align-items:center;gap:10px;color:var(--text3);font-size:.82rem">
                        <i class="fas fa-triangle-exclamation" style="font-size:1.1rem;color:var(--amber)"></i>
                        No file path found for this content. The file may have been deleted or the path is not stored.
                    </div>
                </div>

            </div><!-- /modal-body -->

            <!-- Footer action bar — quick actions without closing modal -->
            <div class="modal-footer" id="cvFooter">
                <button class="btn btn-msg" id="cvBtnMessage" onclick="cvQuickAction('message')">
                    <i class="fas fa-envelope"></i> Message
                </button>
                <button class="btn btn-warn" id="cvBtnWarn" onclick="cvQuickAction('warn')">
                    <i class="fas fa-triangle-exclamation"></i> Warn
                </button>
                <button class="btn btn-ban" id="cvBtnBan" onclick="cvQuickAction('ban')">
                    <i class="fas fa-ban"></i> Ban Content
                </button>
                <button class="btn btn-dismiss" id="cvBtnDismiss" onclick="cvQuickAction('dismiss')">
                    <i class="fas fa-check"></i> Dismiss Report
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
     DRAWER: Reports — Message / Warn
══════════════════════════════════════════════════════════════ -->
    <div class="drawer-overlay" id="reportDrawerOverlay">
        <div class="drawer" id="reportDrawer">
            <div class="drawer-head">
                <div class="drawer-icon" id="rdIcon" style="background:var(--blue-s);color:var(--blue)">
                    <i class="fas fa-envelope"></i>
                </div>
                <div>
                    <h3 id="rdTitle">Send Message</h3>
                    <div class="drawer-sub" id="rdSub">Communicate with report parties</div>
                </div>
                <button class="drawer-close" onclick="closeDrawer('reportDrawerOverlay')"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="drawer-body">
                <!-- Context card -->
                <div class="ctx-card" id="rdCtxCard">
                    <strong id="rdContentTitle">Content Title</strong>
                    <div class="ctx-row"><i class="fas fa-tag"></i><span id="rdDocType">—</span></div>
                    <div class="ctx-row"><i class="fas fa-triangle-exclamation"></i>Reason: <span id="rdReason">—</span></div>
                    <div class="ctx-row" id="rdDetailsRow" style="display:none"><i class="fas fa-comment"></i><span id="rdDetails"></span></div>
                </div>

                <!-- Warning reason (shown only in warn mode) -->
                <div class="reason-card" id="rdWarnInfo" style="display:none">
                    <strong><i class="fas fa-triangle-exclamation"></i> Warning Mode</strong>
                    You are sending a formal warning notification. This will appear in the user's notification bell as a <strong>Warning</strong> type.
                </div>

                <!-- Target: who to send to -->
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-users" style="margin-right:4px"></i>Send To</label>
                    <div class="target-row" id="rdTargetRow">
                        <label class="target-chip" id="rdChipReporter">
                            <input type="checkbox" value="reporter" onchange="rdToggleTarget(this)">
                            <i class="fas fa-user-magnifying-glass" style="font-size:.7rem"></i>
                            Reporter: <span id="rdReporterName">—</span>
                        </label>
                        <label class="target-chip" id="rdChipOwner">
                            <input type="checkbox" value="owner" onchange="rdToggleTarget(this)">
                            <i class="fas fa-user-pen" style="font-size:.7rem"></i>
                            Content Owner: <span id="rdOwnerName">—</span>
                        </label>
                    </div>
                </div>

                <!-- Notification type -->
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-bell" style="margin-right:4px"></i>Notification Type</label>
                    <div class="notif-types" id="rdNotifTypes">
                        <span class="ntype ntype-admin_message sel" data-val="admin_message" onclick="rdSelectType(this)">📨 Admin Message</span>
                        <span class="ntype ntype-warning" data-val="warning" onclick="rdSelectType(this)">⚠️ Warning</span>
                        <span class="ntype ntype-info" data-val="info" onclick="rdSelectType(this)">ℹ️ Info</span>
                    </div>
                </div>

                <!-- Quick Templates -->
                <div class="form-group">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px">
                        <label class="form-label" style="margin:0">Quick Templates</label>
                        <button type="button" class="tpl-toggle-btn" id="rdTplBtn" onclick="rdToggleTpl()">
                            <i class="fas fa-bolt"></i> Templates <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="tpl-panel" id="rdTplPanel">
                        <div class="tpl-section-head">⚠️ Warnings &amp; Policy</div>
                        <button type="button" class="tpl-chip" onclick="rdApplyTpl('policy_warn')">
                            <div class="tpl-chip-head"><i class="fas fa-triangle-exclamation" style="color:#d97706;font-size:.65rem"></i> Content Policy Warning</div>
                            <div class="tpl-chip-preview">Your content has been reported for violating our policy…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="rdApplyTpl('copyright')">
                            <div class="tpl-chip-head"><i class="fas fa-copyright" style="color:#dc2626;font-size:.65rem"></i> Copyright Violation</div>
                            <div class="tpl-chip-preview">Your upload may infringe on copyright of a third party…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="rdApplyTpl('misleading')">
                            <div class="tpl-chip-head"><i class="fas fa-circle-exclamation" style="color:#d97706;font-size:.65rem"></i> Misleading Content</div>
                            <div class="tpl-chip-preview">Content you uploaded contains misleading information…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="rdApplyTpl('spam')">
                            <div class="tpl-chip-head"><i class="fas fa-ban" style="color:#dc2626;font-size:.65rem"></i> Spam Notice</div>
                            <div class="tpl-chip-preview">Your content has been reported as spam or duplicate…</div>
                        </button>
                        <div class="tpl-section-head">📨 Reporter Messages</div>
                        <button type="button" class="tpl-chip" onclick="rdApplyTpl('report_received')">
                            <div class="tpl-chip-head"><i class="fas fa-check-circle" style="color:#059669;font-size:.65rem"></i> Report Received</div>
                            <div class="tpl-chip-preview">Thank you — your report has been received and reviewed…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="rdApplyTpl('report_actioned')">
                            <div class="tpl-chip-head"><i class="fas fa-shield" style="color:#059669;font-size:.65rem"></i> Report Actioned</div>
                            <div class="tpl-chip-preview">Action has been taken on the content you reported…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="rdApplyTpl('report_dismissed')">
                            <div class="tpl-chip-head"><i class="fas fa-xmark" style="color:#94a3b8;font-size:.65rem"></i> Report Dismissed</div>
                            <div class="tpl-chip-preview">After review, the content doesn't violate our guidelines…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="rdApplyTpl('more_info')">
                            <div class="tpl-chip-head"><i class="fas fa-question" style="color:#2563eb;font-size:.65rem"></i> Need More Info</div>
                            <div class="tpl-chip-preview">We need additional details about your report to proceed…</div>
                        </button>
                    </div>
                </div>

                <!-- Title -->
                <div class="form-group">
                    <label class="form-label">Notification Title</label>
                    <input type="text" class="form-input" id="rdMsgTitle" placeholder="e.g. Content Policy Warning" maxlength="150">
                </div>

                <!-- Message -->
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea class="form-textarea" id="rdMsgBody" placeholder="Write your message here…" maxlength="800"></textarea>
                </div>

                <!-- Also send in-app notification toggle -->
                <div class="form-group" style="display:flex;align-items:center;gap:10px;background:var(--bg);padding:10px 12px;border-radius:9px;border:1px solid var(--border)">
                    <input type="checkbox" id="rdSendNotif" checked style="width:15px;height:15px;cursor:pointer;accent-color:var(--blue)">
                    <label for="rdSendNotif" style="font-size:.8rem;font-weight:600;color:var(--text);cursor:pointer">
                        <i class="fas fa-bell" style="color:var(--blue);margin-right:5px"></i>
                        Also send as in-app notification
                    </label>
                </div>
            </div>

            <div class="drawer-footer">
                <button class="btn-send" id="rdSendBtn" onclick="submitReportMessage()">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
     DRAWER: Feedback — Reply / Notify
══════════════════════════════════════════════════════════════ -->
    <div class="drawer-overlay" id="feedbackDrawerOverlay">
        <div class="drawer" id="feedbackDrawer">
            <div class="drawer-head">
                <div class="drawer-icon" id="fdIcon" style="background:var(--purple-s);color:var(--purple)">
                    <i class="fas fa-reply"></i>
                </div>
                <div>
                    <h3 id="fdTitle">Reply to Feedback</h3>
                    <div class="drawer-sub" id="fdSub">Respond to user feedback</div>
                </div>
                <button class="drawer-close" onclick="closeDrawer('feedbackDrawerOverlay')"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="drawer-body">
                <!-- User + feedback context -->
                <div class="ctx-card" id="fdCtxCard">
                    <strong id="fdUserName">User</strong>
                    <div class="ctx-row"><i class="fas fa-tag"></i>Category: <span id="fdCategory">—</span></div>
                    <div class="ctx-row"><i class="fas fa-star" style="color:#f59e0b"></i>Rating: <span id="fdRating">—</span></div>
                    <div style="margin-top:8px;font-size:.8rem;color:var(--text2);line-height:1.6;background:var(--surface);border-radius:7px;padding:8px 10px;border:1px solid var(--border)" id="fdMessage"></div>
                </div>

                <!-- Previous reply (if any) -->
                <div class="prev-reply" id="fdPrevReply" style="display:none">
                    <strong><i class="fas fa-check-circle"></i> Admin Already Replied</strong>
                    <div id="fdPrevReplyText"></div>
                </div>

                <!-- Mode selector: reply vs notify -->
                <div class="form-group">
                    <label class="form-label">Action</label>
                    <div class="notif-types" id="fdModeRow">
                        <span class="ntype ntype-admin_message sel" data-val="reply" onclick="fdSelectMode(this)">💬 Reply to Feedback</span>
                        <span class="ntype ntype-warning" data-val="notify" onclick="fdSelectMode(this)">🔔 Send Notification</span>
                    </div>
                </div>

                <!-- Notification type (shown only in notify mode) -->
                <div class="form-group" id="fdNotifTypeGroup" style="display:none">
                    <label class="form-label">Notification Type</label>
                    <div class="notif-types">
                        <span class="ntype ntype-admin_message sel" data-val="admin_message" onclick="fdSelectNotifType(this)">📨 Admin Message</span>
                        <span class="ntype ntype-warning" data-val="warning" onclick="fdSelectNotifType(this)">⚠️ Warning</span>
                        <span class="ntype ntype-info" data-val="info" onclick="fdSelectNotifType(this)">ℹ️ Info</span>
                    </div>
                    <input type="text" class="form-input" id="fdNotifTitle" placeholder="Notification title…" maxlength="150" style="margin-top:8px">
                </div>

                <!-- Reply / Message text -->
                <!-- Quick Templates -->
                <div class="form-group">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px">
                        <label class="form-label" style="margin:0">Quick Templates</label>
                        <button type="button" class="tpl-toggle-btn" id="fdTplBtn" onclick="fdToggleTpl()">
                            <i class="fas fa-bolt"></i> Templates <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="tpl-panel" id="fdTplPanel">
                        <div class="tpl-section-head">💬 Reply Templates</div>
                        <button type="button" class="tpl-chip" onclick="fdApplyTpl('thanks_feedback')">
                            <div class="tpl-chip-head"><i class="fas fa-heart" style="color:#e11d48;font-size:.65rem"></i> Thank for Feedback</div>
                            <div class="tpl-chip-preview">Thank you for taking the time to share your feedback…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="fdApplyTpl('bug_acknowledged')">
                            <div class="tpl-chip-head"><i class="fas fa-bug" style="color:#dc2626;font-size:.65rem"></i> Bug Acknowledged</div>
                            <div class="tpl-chip-preview">Thank you for reporting this bug — our team is looking into it…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="fdApplyTpl('feature_noted')">
                            <div class="tpl-chip-head"><i class="fas fa-lightbulb" style="color:#d97706;font-size:.65rem"></i> Feature Noted</div>
                            <div class="tpl-chip-preview">Great suggestion! We've noted this feature request…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="fdApplyTpl('issue_resolved')">
                            <div class="tpl-chip-head"><i class="fas fa-check-circle" style="color:#059669;font-size:.65rem"></i> Issue Resolved</div>
                            <div class="tpl-chip-preview">We're pleased to inform you that the issue has been resolved…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="fdApplyTpl('under_review')">
                            <div class="tpl-chip-head"><i class="fas fa-clock" style="color:#2563eb;font-size:.65rem"></i> Under Review</div>
                            <div class="tpl-chip-preview">Your feedback is currently under review by our team…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="fdApplyTpl('not_actionable')">
                            <div class="tpl-chip-head"><i class="fas fa-info-circle" style="color:#94a3b8;font-size:.65rem"></i> Not Actionable</div>
                            <div class="tpl-chip-preview">After reviewing your feedback, we're unable to make changes…</div>
                        </button>
                        <div class="tpl-section-head">🔔 Notification Templates</div>
                        <button type="button" class="tpl-chip" onclick="fdApplyTpl('platform_update')">
                            <div class="tpl-chip-head"><i class="fas fa-sparkles" style="color:#7c3aed;font-size:.65rem"></i> Platform Update</div>
                            <div class="tpl-chip-preview">We've improved ScholarSwap based on feedback like yours…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="fdApplyTpl('follow_up')">
                            <div class="tpl-chip-head"><i class="fas fa-envelope" style="color:#2563eb;font-size:.65rem"></i> Follow-up Check</div>
                            <div class="tpl-chip-preview">We wanted to follow up on the feedback you submitted…</div>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" id="fdMsgLabel">Your Reply</label>
                    <textarea class="form-textarea" id="fdMsgBody" placeholder="Write your reply here…" maxlength="1000"></textarea>
                </div>

                <!-- Update status -->
                <div class="form-group">
                    <label class="form-label">Update Status To</label>
                    <select class="form-select" id="fdNewStatus">
                        <option value="">— Keep Current Status —</option>
                        <option value="in_review">In Review</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>

            <div class="drawer-footer">
                <button class="btn-send" id="fdSendBtn" onclick="submitFeedbackReply()">
                    <i class="fas fa-reply"></i> Send Reply
                </button>
            </div>
        </div>
    </div>

    <script>
        /* ══════════════════════════════════════════════════════════════
   STATE
══════════════════════════════════════════════════════════════ */
        var _rd = {};
        var _fd = {};
        var _cv = {}; // current content view data
        var _rdMode = 'message';
        var _rdType = 'admin_message';
        var _rdTargets = new Set();
        var _fdMode = 'reply';
        var _fdNotifType = 'admin_message';

        /* ══════════════════════════════════════════════════════════════
           CONTENT VIEW MODAL
        ══════════════════════════════════════════════════════════════ */
        function openContentView(data) {
            _cv = data;

            // Type icon mapping
            var typeIcons = {
                note: 'fa-file-lines',
                book: 'fa-book',
                newspaper: 'fa-newspaper'
            };
            var typeColors = {
                note: {
                    bg: 'var(--blue-s)',
                    color: 'var(--blue)'
                },
                book: {
                    bg: 'var(--teal-s)',
                    color: 'var(--teal)'
                },
                newspaper: {
                    bg: 'var(--purple-s)',
                    color: 'var(--purple)'
                }
            };
            var typeIcon = typeIcons[data.doc_type] || 'fa-file-lines';
            var typeColor = typeColors[data.doc_type] || typeColors.note;

            var reasonLabels = {
                spam: 'Spam',
                inappropriate_content: 'Inappropriate Content',
                copyright_violation: 'Copyright Violation',
                misleading_information: 'Misleading Information',
                wrong_category: 'Wrong Category',
                other: 'Other'
            };

            // Cover image
            var coverImg = document.getElementById('cvCoverImg');
            var coverPh = document.getElementById('cvCoverPlaceholder');
            if (data.content_cover) {
                coverImg.src = data.content_cover;
                coverImg.style.display = '';
                coverPh.style.display = 'none';
            } else {
                coverImg.style.display = 'none';
                coverPh.style.display = 'flex';
                coverPh.innerHTML = '<i class="fas ' + typeIcon + '"></i>';
            }

            // Title
            document.getElementById('cvTitle').textContent = data.content_title || '—';

            // Type badge
            var tb = document.getElementById('cvTypeBdg');
            tb.innerHTML = '<i class="fas ' + typeIcon + '" style="font-size:.55rem"></i> ' + (data.doc_type ? (data.doc_type.charAt(0).toUpperCase() + data.doc_type.slice(1)) : '—');
            tb.style.background = typeColor.bg;
            tb.style.color = typeColor.color;

            // Status badge
            var statusColors = {
                pending: {
                    bg: 'var(--amber-s)',
                    color: '#92400e'
                },
                actioned: {
                    bg: 'var(--green-s)',
                    color: '#065f46'
                },
                dismissed: {
                    bg: 'var(--bg)',
                    color: 'var(--text3)'
                }
            };
            var sc = statusColors[data.status] || statusColors.pending;
            var sb = document.getElementById('cvStatusBdg');
            sb.textContent = data.status ? (data.status.charAt(0).toUpperCase() + data.status.slice(1)) : 'Unknown';
            sb.style.background = sc.bg;
            sb.style.color = sc.color;

            // Category badge
            var catBdg = document.getElementById('cvCatBdg');
            if (data.content_category) {
                catBdg.textContent = data.content_category.replace(/_/g, ' ');
                catBdg.style.display = '';
            } else {
                catBdg.style.display = 'none';
            }

            // Owner & upload date
            document.getElementById('cvOwner').textContent = data.owner_name || '—';
            document.getElementById('cvUploaded').textContent = data.content_created_at ? new Date(data.content_created_at).toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }) : '—';

            // Alert banner
            var reasonLabel = reasonLabels[data.reason] || (data.reason || '').replace(/_/g, ' ');
            document.getElementById('cvAlertText').innerHTML =
                'Flagged for: <strong>' + reasonLabel + '</strong>' +
                (data.details ? ' — "' + data.details + '"' : '');

            // Stats
            document.getElementById('cvViews').textContent = data.content_views !== undefined ? data.content_views.toLocaleString() : '—';
            document.getElementById('cvDownloads').textContent = data.content_downloads !== undefined ? data.content_downloads.toLocaleString() : '—';
            document.getElementById('cvResourceId').textContent = data.resource_id || '—';
            document.getElementById('cvReportId').textContent = '#' + (data.report_id || '—');

            // Description
            var descEl = document.getElementById('cvDescription');
            if (data.content_description && data.content_description.trim()) {
                descEl.textContent = data.content_description;
                descEl.classList.remove('empty-desc');
            } else {
                descEl.textContent = 'No description provided for this content.';
                descEl.classList.add('empty-desc');
            }

            // Report details
            document.getElementById('cvReporter').textContent =
                (data.reporter_name || 'Unknown') + (data.reporter_username ? ' (@' + data.reporter_username + ')' : '');
            document.getElementById('cvReason').textContent = reasonLabel;
            document.getElementById('cvDetails').textContent = data.details || '— No additional details provided';
            document.getElementById('cvReportDate').textContent = data.report_created_at ?
                new Date(data.report_created_at).toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '—';
            document.getElementById('cvReportStatus').textContent = data.status ? (data.status.charAt(0).toUpperCase() + data.status.slice(1)) : '—';

            // File link
            var fileSection = document.getElementById('cvFileSection');
            var noFileSection = document.getElementById('cvNoFileSection');
            var fileLink = document.getElementById('cvFileLink');
            if (data.content_file) {
                fileLink.href = data.content_file;
                fileSection.style.display = '';
                noFileSection.style.display = 'none';
            } else {
                fileSection.style.display = 'none';
                noFileSection.style.display = '';
            }

            // Footer buttons — hide Ban/Dismiss if already actioned
            var isPending = data.status === 'pending';
            document.getElementById('cvBtnBan').style.display = isPending ? '' : 'none';
            document.getElementById('cvBtnDismiss').style.display = isPending ? '' : 'none';

            // Open modal
            document.getElementById('contentViewOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('contentViewOverlay').classList.remove('open');
            document.body.style.overflow = '';
        }

        // Click outside to close
        document.getElementById('contentViewOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Quick action buttons inside the modal — transfer to drawer / action
        function cvQuickAction(type) {
            closeModal();
            setTimeout(function() {
                if (type === 'message') {
                    openReportDrawer(_cv, 'message');
                } else if (type === 'warn') {
                    openReportDrawer(_cv, 'warn');
                } else if (type === 'ban') {
                    banContent(_cv.report_id, _cv.doc_type, _cv.resource_id, _cv.owner_id, _cv.content_title);
                } else if (type === 'dismiss') {
                    dismissReport(_cv.report_id);
                }
            }, 200);
        }

        /* ══ QUICK TEMPLATES DATA ══════════════════════════════════════ */
        var RD_TEMPLATES = {
            policy_warn: {
                type: 'warning',
                title: 'Content Policy Warning',
                message: 'Your uploaded content has been reported and reviewed by our moderation team. It appears to violate ScholarSwap\'s Content Policy. Please review our community guidelines carefully to avoid further action. Repeated violations may result in content removal or account restrictions.'
            },
            copyright: {
                type: 'warning',
                title: 'Copyright Infringement Notice',
                message: 'The content you recently uploaded may infringe on the intellectual property rights of a third party. We have temporarily hidden this content. Please ensure all materials you upload are your own original work or properly licensed. Continued violations may result in account action.'
            },
            misleading: {
                type: 'warning',
                title: 'Misleading Content Notice',
                message: 'Content you uploaded has been flagged as containing potentially misleading or inaccurate information. ScholarSwap is committed to quality academic resources. Please review the flagged content and either update it with accurate information or remove it.'
            },
            spam: {
                type: 'warning',
                title: 'Spam Content Notice',
                message: 'Your recent upload has been reported as spam or a duplicate of existing content. Please avoid uploading duplicate, low-quality, or irrelevant content. This content has been flagged and may be removed. Repeated spam uploads may result in restrictions to your account.'
            },
            report_received: {
                type: 'admin_message',
                title: 'Your Report Has Been Received',
                message: 'Thank you for helping keep ScholarSwap safe and high-quality. Your report has been received and is being reviewed by our moderation team. We take all reports seriously and will take appropriate action if a violation is confirmed. We appreciate your contribution to the community.'
            },
            report_actioned: {
                type: 'admin_message',
                title: 'Action Taken on Your Report',
                message: 'We wanted to let you know that action has been taken on the content you reported. Our moderation team reviewed the report and found it valid. The appropriate measures have been applied. Thank you for helping us maintain a safe and quality learning environment on ScholarSwap.'
            },
            report_dismissed: {
                type: 'admin_message',
                title: 'Report Review Complete',
                message: 'Our moderation team has completed a review of the content you reported. After careful evaluation, we determined that the content does not violate our community guidelines and will remain on the platform. We appreciate your vigilance — it helps keep ScholarSwap safe.'
            },
            more_info: {
                type: 'admin_message',
                title: 'Additional Information Needed',
                message: 'Thank you for submitting a report. To better investigate and take appropriate action, our moderation team needs some additional information. Could you please provide more specific details about the issue you encountered? This will help us resolve the matter more effectively.'
            }
        };

        var FD_TEMPLATES = {
            thanks_feedback: {
                message: 'Thank you for taking the time to share your feedback! We genuinely appreciate hearing from our users — your insights help us improve ScholarSwap for everyone. We have noted your feedback and our team will review it carefully. Feel free to reach out if you have any other suggestions or concerns.'
            },
            bug_acknowledged: {
                message: 'Thank you for reporting this issue! Our development team has been notified and is actively looking into the bug you described. We apologize for any inconvenience. We will aim to have this resolved as soon as possible and will keep you updated on the progress. Thank you for helping us improve ScholarSwap!'
            },
            feature_noted: {
                message: 'What a great suggestion — thank you for sharing it! We have officially noted your feature request and added it to our development backlog for consideration. While we cannot guarantee a specific timeline, we regularly review user suggestions when planning new features. Your input genuinely shapes the future of ScholarSwap!'
            },
            issue_resolved: {
                message: 'We are pleased to inform you that the issue you reported has been resolved! Our team worked on it and the fix is now live. Please try again and let us know if you experience any further problems. Thank you for your patience and for helping us identify and address this — we really appreciate it!'
            },
            under_review: {
                message: 'Thank you for your feedback. We want to let you know that it is currently being reviewed by our team. We take all feedback seriously and aim to address each submission thoughtfully. We will update you once a decision or resolution has been reached. Thank you for your patience!'
            },
            not_actionable: {
                message: 'Thank you for submitting your feedback to ScholarSwap. After carefully reviewing your submission, we are unable to make the changes requested at this time. This may be due to technical constraints, platform policy, or current development priorities. We truly value your engagement and encourage you to keep sharing your thoughts.'
            },
            platform_update: {
                message: 'We wanted to reach out to let you know that we have made improvements to ScholarSwap based on feedback from users like you! Your voice matters and directly influences how we develop the platform. We encourage you to explore the updates and share your thoughts. Thank you for being an active part of our community!'
            },
            follow_up: {
                message: 'We wanted to follow up on the feedback you recently submitted. We hope your experience has improved since then. If the issue is still ongoing or you have additional feedback, please do not hesitate to reach out again. Your satisfaction is important to us and we are always here to help!'
            }
        };

        /* ── Report Drawer template toggle ── */
        function rdToggleTpl() {
            var panel = document.getElementById('rdTplPanel');
            var btn = document.getElementById('rdTplBtn');
            panel.classList.toggle('open');
            btn.classList.toggle('open', panel.classList.contains('open'));
        }

        function rdApplyTpl(key) {
            var t = RD_TEMPLATES[key];
            if (!t) return;
            document.getElementById('rdMsgTitle').value = t.title;
            document.getElementById('rdMsgBody').value = t.message;
            document.querySelectorAll('#rdNotifTypes .ntype').forEach(function(n) {
                n.classList.remove('sel', 'ntype-warning', 'ntype-admin_message', 'ntype-info');
            });
            var typeEl = document.querySelector('#rdNotifTypes .ntype[data-val="' + t.type + '"]');
            if (typeEl) typeEl.classList.add('sel', 'ntype-' + t.type);
            _rdType = t.type;
            document.getElementById('rdTplPanel').classList.remove('open');
            document.getElementById('rdTplBtn').classList.remove('open');
            document.getElementById('rdMsgTitle').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            document.getElementById('rdMsgTitle').focus();
        }

        /* ── Feedback Drawer template toggle ── */
        function fdToggleTpl() {
            var panel = document.getElementById('fdTplPanel');
            var btn = document.getElementById('fdTplBtn');
            panel.classList.toggle('open');
            btn.classList.toggle('open', panel.classList.contains('open'));
        }

        function fdApplyTpl(key) {
            var t = FD_TEMPLATES[key];
            if (!t) return;
            document.getElementById('fdMsgBody').value = t.message;
            document.getElementById('fdTplPanel').classList.remove('open');
            document.getElementById('fdTplBtn').classList.remove('open');
            document.getElementById('fdMsgBody').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            document.getElementById('fdMsgBody').focus();
        }

        /* ══════════════════════════════════════════════════════════════
           TAB SWITCHER
        ══════════════════════════════════════════════════════════════ */
        function switchTab(name, btn) {
            document.querySelectorAll('.mtab').forEach(b => b.classList.remove('on'));
            btn.classList.add('on');
            document.getElementById('tab-reports').style.display = name === 'reports' ? '' : 'none';
            document.getElementById('tab-feedback').style.display = name === 'feedback' ? '' : 'none';
        }

        /* ══════════════════════════════════════════════════════════════
           FILTERS
        ══════════════════════════════════════════════════════════════ */
        function filterReports() {
            var status = document.getElementById('repStatusFilter').value;
            var type = document.getElementById('repTypeFilter').value;
            var q = document.getElementById('repSearch').value.toLowerCase().trim();
            document.querySelectorAll('.rep-row').forEach(row => {
                var show = true;
                if (status && row.dataset.status !== status) show = false;
                if (type && row.dataset.type !== type) show = false;
                if (q && !row.dataset.search.includes(q)) show = false;
                row.classList.toggle('filtered-out', !show);
            });
        }

        function filterFeedback() {
            var status = document.getElementById('fbStatusFilter').value;
            var cat = document.getElementById('fbCatFilter').value;
            var q = document.getElementById('fbSearch').value.toLowerCase().trim();
            document.querySelectorAll('.fb-row').forEach(row => {
                var show = true;
                if (status && row.dataset.status !== status) show = false;
                if (cat && row.dataset.cat !== cat) show = false;
                if (q && !row.dataset.search.includes(q)) show = false;
                row.classList.toggle('filtered-out', !show);
            });
        }

        /* ══════════════════════════════════════════════════════════════
           DRAWER HELPERS
        ══════════════════════════════════════════════════════════════ */
        function closeDrawer(id) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }
        ['reportDrawerOverlay', 'feedbackDrawerOverlay'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) closeDrawer(id);
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDrawer('reportDrawerOverlay');
                closeDrawer('feedbackDrawerOverlay');
                closeModal();
            }
        });

        /* ══════════════════════════════════════════════════════════════
           REPORT DRAWER
        ══════════════════════════════════════════════════════════════ */
        function openReportDrawer(data, mode) {
            _rd = data;
            _rdMode = mode;
            _rdTargets.clear();

            document.querySelectorAll('#rdTargetRow .target-chip').forEach(c => c.classList.remove('selected'));
            document.querySelectorAll('#rdTargetRow input[type=checkbox]').forEach(c => c.checked = false);

            document.getElementById('rdContentTitle').textContent = data.content_title || '—';
            document.getElementById('rdDocType').textContent = data.doc_type || '—';
            document.getElementById('rdReason').textContent = data.reason ? data.reason.replace(/_/g, ' ') : '—';
            if (data.details) {
                document.getElementById('rdDetailsRow').style.display = '';
                document.getElementById('rdDetails').textContent = data.details;
            } else {
                document.getElementById('rdDetailsRow').style.display = 'none';
            }

            document.getElementById('rdReporterName').textContent = data.reporter_name || 'Reporter';
            document.getElementById('rdOwnerName').textContent = data.owner_name || 'Owner';
            document.getElementById('rdChipOwner').style.display = data.owner_id ? '' : 'none';

            if (mode === 'warn') {
                document.getElementById('rdTitle').textContent = 'Send Warning';
                document.getElementById('rdSub').textContent = 'Issue a formal warning notification';
                document.getElementById('rdIcon').style.background = 'var(--amber-s)';
                document.getElementById('rdIcon').style.color = 'var(--amber)';
                document.getElementById('rdIcon').innerHTML = '<i class="fas fa-triangle-exclamation"></i>';
                document.getElementById('rdWarnInfo').style.display = '';
                document.getElementById('rdMsgTitle').value = 'Content Policy Warning';
                document.getElementById('rdMsgBody').value = 'Your uploaded content "' + (data.content_title || '') + '" has been reported and may violate our community guidelines. Please review our content policy to avoid further action.';
                document.querySelectorAll('#rdNotifTypes .ntype').forEach(n => {
                    n.classList.toggle('sel', n.dataset.val === 'warning');
                    n.classList.toggle('ntype-warning', n.dataset.val === 'warning');
                });
                _rdType = 'warning';
            } else {
                document.getElementById('rdTitle').textContent = 'Send Message';
                document.getElementById('rdSub').textContent = 'Communicate with report parties';
                document.getElementById('rdIcon').style.background = 'var(--blue-s)';
                document.getElementById('rdIcon').style.color = 'var(--blue)';
                document.getElementById('rdIcon').innerHTML = '<i class="fas fa-envelope"></i>';
                document.getElementById('rdWarnInfo').style.display = 'none';
                document.getElementById('rdMsgTitle').value = 'Admin Message Regarding Your Report';
                document.getElementById('rdMsgBody').value = '';
                document.querySelectorAll('#rdNotifTypes .ntype').forEach(n => {
                    n.classList.toggle('sel', n.dataset.val === 'admin_message');
                });
                _rdType = 'admin_message';
            }

            document.getElementById('rdTplPanel').classList.remove('open');
            document.getElementById('rdTplBtn').classList.remove('open');
            document.getElementById('reportDrawerOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function rdToggleTarget(cb) {
            var chip = cb.closest('.target-chip');
            if (cb.checked) {
                _rdTargets.add(cb.value);
                chip.classList.add('selected');
            } else {
                _rdTargets.delete(cb.value);
                chip.classList.remove('selected');
            }
        }

        function rdSelectType(el) {
            document.querySelectorAll('#rdNotifTypes .ntype').forEach(n => n.classList.remove('sel', 'ntype-warning', 'ntype-admin_message', 'ntype-info'));
            el.classList.add('sel', 'ntype-' + el.dataset.val);
            _rdType = el.dataset.val;
        }

        async function submitReportMessage() {
            var title = document.getElementById('rdMsgTitle').value.trim();
            var body = document.getElementById('rdMsgBody').value.trim();
            var notif = document.getElementById('rdSendNotif').checked;
            var targets = Array.from(_rdTargets);

            if (!targets.length) return sw('warning', 'No Target', 'Please select at least one recipient (Reporter or Owner).');
            if (!title) return sw('warning', 'Missing Title', 'Please enter a notification title.');
            if (!body) return sw('warning', 'Missing Message', 'Please write a message.');

            var btn = document.getElementById('rdSendBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';

            var fd = new FormData();
            fd.append('action', 'send_report_message');
            fd.append('report_id', _rd.report_id);
            fd.append('targets', JSON.stringify(targets));
            fd.append('reporter_id', _rd.reporter_id);
            fd.append('owner_id', _rd.owner_id);
            fd.append('notif_type', _rdType);
            fd.append('title', title);
            fd.append('message', body);
            fd.append('send_notif', notif ? '1' : '0');
            fd.append('resource_id', _rd.resource_id || '');
            fd.append('doc_type', _rd.doc_type || '');
            fd.append('content_title', _rd.content_title || '');

            try {
                var res = await fetch('auth/handle_report.php', {
                    method: 'POST',
                    body: fd
                });
                var data = await res.json();
                if (data.success) {
                    closeDrawer('reportDrawerOverlay');
                    sw('success', 'Sent!', data.message || 'Message delivered successfully.');
                } else {
                    sw('error', 'Failed', data.message || 'Something went wrong.');
                }
            } catch (e) {
                sw('error', 'Network Error', 'Could not reach the server.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Message';
        }

        /* ══════════════════════════════════════════════════════════════
           FEEDBACK DRAWER
        ══════════════════════════════════════════════════════════════ */
        function openFeedbackDrawer(data, notifyMode) {
            _fd = data;
            _fdMode = notifyMode ? 'notify' : 'reply';

            document.getElementById('fdUserName').textContent = data.display_name + ' (@' + data.username + ')';
            document.getElementById('fdCategory').textContent = (data.category || 'general').replace(/_/g, ' ');
            document.getElementById('fdRating').innerHTML = data.rating ?
                '<span style="color:#f59e0b">' + '★'.repeat(data.rating) + '☆'.repeat(5 - data.rating) + '</span>' :
                '— not rated';
            document.getElementById('fdMessage').textContent = data.message || '—';

            if (data.admin_reply) {
                document.getElementById('fdPrevReply').style.display = '';
                document.getElementById('fdPrevReplyText').textContent = data.admin_reply;
            } else {
                document.getElementById('fdPrevReply').style.display = 'none';
            }

            document.querySelectorAll('#fdModeRow .ntype').forEach(n => {
                n.classList.toggle('sel', n.dataset.val === _fdMode);
                n.classList.toggle('ntype-admin_message', n.dataset.val === 'reply' && n.dataset.val === _fdMode);
                n.classList.toggle('ntype-warning', n.dataset.val === 'notify' && n.dataset.val === _fdMode);
            });
            _applyFdMode(_fdMode);

            document.getElementById('fdMsgBody').value = '';
            document.getElementById('fdNewStatus').value = '';
            document.getElementById('fdNotifTitle').value = 'Feedback Response';

            if (notifyMode) {
                document.getElementById('fdTitle').textContent = 'Send Notification';
                document.getElementById('fdSub').textContent = 'Send a notification to this user';
                document.getElementById('fdIcon').style.background = 'var(--blue-s)';
                document.getElementById('fdIcon').style.color = 'var(--blue)';
                document.getElementById('fdIcon').innerHTML = '<i class="fas fa-bell"></i>';
            } else {
                document.getElementById('fdTitle').textContent = 'Reply to Feedback';
                document.getElementById('fdSub').textContent = 'Respond to user feedback';
                document.getElementById('fdIcon').style.background = 'var(--purple-s)';
                document.getElementById('fdIcon').style.color = 'var(--purple)';
                document.getElementById('fdIcon').innerHTML = '<i class="fas fa-reply"></i>';
            }

            document.getElementById('fdTplPanel').classList.remove('open');
            document.getElementById('fdTplBtn').classList.remove('open');
            document.getElementById('feedbackDrawerOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function fdSelectMode(el) {
            document.querySelectorAll('#fdModeRow .ntype').forEach(n => n.classList.remove('sel', 'ntype-admin_message', 'ntype-warning'));
            el.classList.add('sel');
            _fdMode = el.dataset.val;
            if (_fdMode === 'notify') el.classList.add('ntype-warning');
            else el.classList.add('ntype-admin_message');
            _applyFdMode(_fdMode);
        }

        function _applyFdMode(mode) {
            var isNotify = mode === 'notify';
            document.getElementById('fdNotifTypeGroup').style.display = isNotify ? '' : 'none';
            document.getElementById('fdMsgLabel').textContent = isNotify ? 'Notification Message' : 'Your Reply';
            document.getElementById('fdSendBtn').innerHTML = isNotify ?
                '<i class="fas fa-bell"></i> Send Notification' :
                '<i class="fas fa-reply"></i> Send Reply';
        }

        function fdSelectNotifType(el) {
            el.closest('.notif-types').querySelectorAll('.ntype').forEach(n => n.classList.remove('sel', 'ntype-warning', 'ntype-admin_message', 'ntype-info'));
            el.classList.add('sel', 'ntype-' + el.dataset.val);
            _fdNotifType = el.dataset.val;
        }

        async function submitFeedbackReply() {
            var msg = document.getElementById('fdMsgBody').value.trim();
            var status = document.getElementById('fdNewStatus').value;
            var btn = document.getElementById('fdSendBtn');
            var isNotify = _fdMode === 'notify';

            if (!msg) return sw('warning', 'Empty Message', isNotify ? 'Please write a notification message.' : 'Please write a reply message.');
            if (isNotify) {
                var ntitle = document.getElementById('fdNotifTitle').value.trim();
                if (!ntitle) return sw('warning', 'Missing Title', 'Please enter a notification title.');
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';

            var fd = new FormData();
            fd.append('action', isNotify ? 'send_feedback_notification' : 'reply_feedback');
            fd.append('feedback_id', _fd.feedback_id);
            fd.append('user_id', _fd.user_id);
            fd.append('message', msg);
            fd.append('new_status', status);
            if (isNotify) {
                fd.append('notif_type', _fdNotifType);
                fd.append('notif_title', document.getElementById('fdNotifTitle').value.trim());
            }

            try {
                var res = await fetch('auth/handle_report.php', {
                    method: 'POST',
                    body: fd
                });
                var data = await res.json();
                if (data.success) {
                    closeDrawer('feedbackDrawerOverlay');
                    Swal.fire({
                        icon: 'success',
                        title: isNotify ? 'Notification Sent!' : 'Reply Sent!',
                        text: data.message || 'Done.',
                        timer: 2500,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    sw('error', 'Failed', data.message || 'Something went wrong.');
                }
            } catch (e) {
                sw('error', 'Network Error', 'Could not reach the server.');
            }

            btn.disabled = false;
            btn.innerHTML = isNotify ?
                '<i class="fas fa-bell"></i> Send Notification' :
                '<i class="fas fa-reply"></i> Send Reply';
        }

        /* ══════════════════════════════════════════════════════════════
           QUICK ACTIONS (ban, dismiss, resolve)
        ══════════════════════════════════════════════════════════════ */
        function banContent(reportId, docType, resourceId, ownerId, title) {
            Swal.fire({
                title: 'Ban this content?',
                html: `<p style="color:#64748b;font-size:.88rem;margin-bottom:6px">You are about to ban:</p>
              <p style="font-weight:700;color:#0f172a;font-size:.95rem;margin-bottom:8px">"${title}"</p>
              <p style="color:#64748b;font-size:.82rem">This ${docType} will be hidden immediately and the report marked as actioned.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-ban"></i> Yes, Ban It',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#e2e8f0',
                reverseButtons: true
            }).then(r => {
                if (!r.isConfirmed) return;
                Swal.fire({
                    title: 'Banning…',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
                fetch('auth/handle_report.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=ban&report_id=${reportId}&doc_type=${docType}&resource_id=${encodeURIComponent(resourceId)}`
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) Swal.fire({
                            icon: 'success',
                            title: 'Banned!',
                            text: d.message,
                            timer: 2500,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                        else sw('error', 'Failed', d.message);
                    }).catch(() => sw('error', 'Network Error', 'Could not reach the server.'));
            });
        }

        function dismissReport(reportId) {
            Swal.fire({
                title: 'Dismiss Report?',
                text: 'The report will be marked as dismissed.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Dismiss',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#059669',
                cancelButtonColor: '#e2e8f0',
                reverseButtons: true
            }).then(r => {
                if (!r.isConfirmed) return;
                fetch('auth/handle_report.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=dismiss&report_id=${reportId}`
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) Swal.fire({
                            icon: 'success',
                            title: 'Dismissed',
                            timer: 1800,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                        else sw('error', 'Failed', d.message);
                    }).catch(() => sw('error', 'Network Error'));
            });
        }

        function resolveFeedback(feedbackId) {
            Swal.fire({
                title: 'Mark as Resolved?',
                text: 'This feedback will be moved to resolved status.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Resolve',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#059669',
                cancelButtonColor: '#e2e8f0',
                reverseButtons: true
            }).then(r => {
                if (!r.isConfirmed) return;
                fetch('auth/handle_report.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=resolve_feedback&feedback_id=${feedbackId}`
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) Swal.fire({
                            icon: 'success',
                            title: 'Resolved!',
                            timer: 1800,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                        else sw('error', 'Failed', d.message);
                    }).catch(() => sw('error', 'Network Error'));
            });
        }

        /* ══════════════════════════════════════════════════════════════
           UTIL
        ══════════════════════════════════════════════════════════════ */
        function sw(icon, title, text) {
            return Swal.fire({
                icon,
                title,
                text,
                iconColor: icon === 'error' ? '#ef4444' : icon === 'warning' ? '#f59e0b' : '#10b981'
            });
        }

        var sp = new URLSearchParams(window.location.search).get('s');
        if (sp === 'success') Swal.fire({
            icon: 'success',
            title: 'Done!',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false
        });
        else if (sp === 'failed') Swal.fire({
            icon: 'error',
            title: 'Failed',
            timer: 2000,
            showConfirmButton: false
        });
        if (sp) history.replaceState(null, '', 'reports_feedback.php');
    </script>
</body>

</html>