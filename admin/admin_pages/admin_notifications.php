<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login");
    exit;
}

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/auth/notifications.php';

$adminStmt = $conn->prepare("SELECT first_name, last_name, profile_image, role FROM admin_user WHERE admin_id = ? LIMIT 1");
$adminStmt->execute([$_SESSION['admin_id']]);
$admin     = $adminStmt->fetch(PDO::FETCH_ASSOC);
$initials  = strtoupper(($admin['first_name'][0] ?? '') . ($admin['last_name'][0] ?? ''));
$avatarSrc = !empty($admin['profile_image']) ? htmlspecialchars($admin['profile_image']) : '';

$studentsStmt = $conn->prepare("
    SELECT u.user_id, u.email,
           COALESCE(CONCAT(s.first_name,' ',s.last_name), u.username) AS full_name
    FROM users u LEFT JOIN students s ON s.user_id = u.user_id
    WHERE u.role = 'student' AND u.is_active = 1 ORDER BY full_name ASC");
$studentsStmt->execute();
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$tutorsStmt = $conn->prepare("
    SELECT u.user_id, u.email,
           COALESCE(CONCAT(t.first_name,' ',t.last_name), u.username) AS full_name
    FROM users u LEFT JOIN tutors t ON t.user_id = u.user_id
    WHERE u.role = 'tutor' AND u.is_active = 1 ORDER BY full_name ASC");
$tutorsStmt->execute();
$tutors = $tutorsStmt->fetchAll(PDO::FETCH_ASSOC);

$adminsStmt = $conn->prepare("
    SELECT admin_id AS user_id, email, CONCAT(first_name,' ',last_name) AS full_name
    FROM admin_user WHERE status = 'approved' ORDER BY full_name ASC");
$adminsStmt->execute();
$admins = $adminsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalNotifs   = (int)$conn->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$unreadNotifs  = (int)$conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
$warningNotifs = (int)$conn->query("SELECT COUNT(*) FROM notifications WHERE type = 'warning'")->fetchColumn();
$todayNotifs   = (int)$conn->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// ── KEY FIX: JOIN admin_user so admin recipient names resolve correctly ──
// notifications.user_id  = users.user_id   for students/tutors
// notifications.admin_id = admin_user.admin_id for admins
$notifsStmt = $conn->prepare("
    SELECT n.*,
           COALESCE(
               NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
               u.username,
               NULLIF(TRIM(CONCAT(a.first_name,' ',a.last_name)),''),
               'Unknown'
           ) AS recipient_name,
           COALESCE(u.email,  a.email)   AS recipient_email,
           u.profile_image               AS recipient_image
    FROM notifications n
    LEFT JOIN users      u ON u.user_id  = n.user_id
    LEFT JOIN students   s ON s.user_id  = n.user_id
    LEFT JOIN admin_user a ON a.admin_id = n.admin_id
    ORDER BY n.created_at DESC
    LIMIT 200
");
$notifsStmt->execute();
$notifications = $notifsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSwap Admin | Notifications</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --sh2: 0 8px 28px rgba(0, 0, 0, .1)
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
            font-size: 14px
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

        .pg-head {
            margin-bottom: 22px
        }

        .pg-head h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
            display: flex;
            align-items: center;
            gap: 10px
        }

        .pg-head h1 i {
            color: var(--blue);
            font-size: 1.1rem
        }

        .pg-head p {
            font-size: .82rem;
            color: var(--text3);
            margin-top: 4px
        }

        .ph-actions {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
            flex-wrap: wrap
        }

        .sg {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
            gap: 14px;
            margin-bottom: 22px
        }

        .sc {
            background: var(--surface);
            border-radius: var(--r2);
            padding: 17px 18px;
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 13px;
            transition: transform .2s, box-shadow .2s
        }

        .sc:hover {
            transform: translateY(-2px);
            box-shadow: var(--sh2)
        }

        .si {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            flex-shrink: 0
        }

        .si.b {
            background: var(--blue-s);
            color: var(--blue)
        }

        .si.g {
            background: var(--green-s);
            color: var(--green)
        }

        .si.a {
            background: var(--amber-s);
            color: var(--amber)
        }

        .si.p {
            background: var(--purple-s);
            color: var(--purple)
        }

        .sv {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1
        }

        .sl {
            font-size: .7rem;
            color: var(--text3);
            margin-top: 3px
        }

        .btn-compose {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--blue);
            color: #fff;
            padding: 9px 18px;
            border-radius: var(--r);
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-family: inherit;
            transition: background .18s, transform .15s;
            white-space: nowrap;
            flex-shrink: 0
        }

        .btn-compose:hover {
            background: var(--blue-d);
            transform: translateY(-1px)
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap
        }

        .search-wrap {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 0 12px;
            height: 36px;
            box-shadow: var(--sh)
        }

        .search-wrap i {
            color: var(--text3);
            font-size: .78rem
        }

        .search-wrap input {
            background: none;
            border: none;
            outline: none;
            color: var(--text);
            font-size: .82rem;
            font-family: inherit;
            width: 100%
        }

        .filter-sel {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text2);
            padding: 0 10px;
            height: 36px;
            border-radius: var(--r);
            font-size: .8rem;
            font-family: inherit;
            cursor: pointer;
            outline: none;
            box-shadow: var(--sh)
        }

        .type-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 14px;
            flex-wrap: wrap
        }

        .type-tab {
            padding: 5px 13px;
            border-radius: var(--r);
            font-size: .72rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text2);
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 5px
        }

        .type-tab:hover {
            background: var(--blue-s);
            color: var(--blue);
            border-color: #bfdbfe
        }

        .type-tab.active {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue)
        }

        .type-tab .tc {
            background: var(--bg);
            color: var(--text3);
            padding: 1px 6px;
            border-radius: 99px;
            font-size: .62rem
        }

        .type-tab.active .tc {
            background: rgba(255, 255, 255, .22);
            color: #fff
        }

        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 18px
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

        .tw {
            overflow-x: auto
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        thead th {
            padding: 9px 13px;
            background: var(--bg);
            font-size: .63rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            text-align: left
        }

        tbody td {
            padding: 11px 13px;
            font-size: .82rem;
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

        .recip {
            display: flex;
            align-items: center;
            gap: 9px
        }

        .recip-av {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .68rem;
            font-weight: 700;
            color: #fff;
            overflow: hidden;
            border: 1.5px solid var(--border2);
            position: relative
        }

        .recip-av img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%
        }

        .recip-name {
            font-weight: 600;
            font-size: .82rem;
            color: var(--text)
        }

        .recip-email {
            font-size: .68rem;
            color: var(--text3)
        }

        .recip-type {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: .58rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 4px;
            margin-top: 2px
        }

        .rt-admin {
            background: var(--purple-s);
            color: var(--purple)
        }

        .rt-user {
            background: var(--blue-s);
            color: var(--blue)
        }

        .rt-unknown {
            background: var(--bg);
            color: var(--text3)
        }

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

        .bdg-warning {
            background: var(--red-s);
            color: #991b1b
        }

        .bdg-admin_message {
            background: var(--blue-s);
            color: var(--blue)
        }

        .bdg-upload_approved {
            background: var(--green-s);
            color: #065f46
        }

        .bdg-upload_rejected {
            background: var(--amber-s);
            color: #92400e
        }

        .bdg-new_upload {
            background: var(--teal-s);
            color: var(--teal)
        }

        .bdg-banned_content {
            background: var(--red-s);
            color: #991b1b
        }

        .bdg-other {
            background: var(--purple-s);
            color: var(--purple)
        }

        .read-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            flex-shrink: 0
        }

        .dot-unread {
            background: var(--blue);
            box-shadow: 0 0 5px rgba(37, 99, 235, .4)
        }

        .dot-read {
            background: var(--border2)
        }

        .notif-title {
            font-weight: 600;
            font-size: .82rem;
            color: var(--text);
            margin-bottom: 2px;
            display: flex;
            align-items: center
        }

        .notif-msg {
            font-size: .72rem;
            color: var(--text3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 260px
        }

        .res-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--bg);
            border: 1px solid var(--border);
            padding: 2px 7px;
            border-radius: 6px;
            font-size: .68rem;
            color: var(--text3)
        }

        .act-btn {
            background: none;
            border: none;
            color: var(--text3);
            cursor: pointer;
            padding: 5px 7px;
            border-radius: 6px;
            font-size: .78rem;
            transition: all .15s
        }

        .act-btn:hover {
            background: var(--bg);
            color: var(--text)
        }

        .act-btn.del:hover {
            background: var(--red-s);
            color: var(--red)
        }

        .empty {
            padding: 40px;
            text-align: center;
            color: var(--text3);
            font-size: .8rem
        }

        .empty i {
            font-size: 1.8rem;
            display: block;
            margin-bottom: 10px;
            opacity: .25
        }

        .pag {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 16px;
            flex-wrap: wrap
        }

        .pag-btn {
            min-width: 30px;
            height: 30px;
            border-radius: 7px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text2);
            font-size: .78rem;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 7px
        }

        .pag-btn:hover,
        .pag-btn.active {
            background: var(--blue);
            border-color: var(--blue);
            color: #fff
        }

        .pag-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
            pointer-events: none
        }

        .pag-ellipsis {
            color: var(--text3);
            font-size: .8rem;
            padding: 0 3px
        }

        .row-check {
            width: 15px;
            height: 15px;
            accent-color: var(--blue);
            cursor: pointer;
            flex-shrink: 0
        }

        thead th:first-child,
        tbody td:first-child {
            width: 36px;
            padding-right: 0
        }

        .bulk-bar {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 9px 18px;
            background: var(--blue-s);
            border-bottom: 1px solid #bfdbfe;
            flex-wrap: wrap;
            animation: slideDown .18s ease
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-6px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .bulk-bar.show {
            display: flex
        }

        .bulk-count {
            font-size: .78rem;
            font-weight: 700;
            color: var(--blue);
            display: flex;
            align-items: center;
            gap: 6px
        }

        .bulk-sep {
            flex: 1
        }

        .btn-bulk-del {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: var(--r);
            border: none;
            background: var(--red);
            color: #fff;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background .15s, transform .12s
        }

        .btn-bulk-del:hover {
            background: #b91c1c;
            transform: translateY(-1px)
        }

        .btn-bulk-cancel {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--r);
            border: 1px solid #93c5fd;
            background: #fff;
            color: var(--blue);
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s
        }

        .btn-bulk-cancel:hover {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue)
        }

        tbody tr.selected td {
            background: #eff6ff !important
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(6px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity .22s
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: all
        }

        .modal {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: var(--r2);
            width: 100%;
            max-width: 560px;
            margin: 20px;
            transform: scale(.96) translateY(14px);
            transition: transform .26s cubic-bezier(.34, 1.56, .64, 1), opacity .22s;
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: var(--sh2);
            opacity: 0
        }

        .modal-overlay.open .modal {
            transform: scale(1) translateY(0);
            opacity: 1
        }

        .modal::before {
            content: '';
            display: block;
            height: 4px;
            background: linear-gradient(90deg, var(--blue), var(--purple), var(--teal))
        }

        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            background: var(--surface);
            z-index: 1
        }

        .modal-title {
            font-size: .92rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px
        }

        .modal-title i {
            color: var(--blue)
        }

        .modal-close {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: var(--bg);
            border: none;
            color: var(--text2);
            cursor: pointer;
            font-size: .85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .14s
        }

        .modal-close:hover {
            background: var(--red-s);
            color: var(--red)
        }

        .modal-body {
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 16px
        }

        .modal-foot {
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            position: sticky;
            bottom: 0;
            background: var(--bg)
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px
        }

        .form-label {
            font-size: .63rem;
            font-weight: 700;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: .07em
        }

        .form-input,
        .form-select,
        .form-textarea {
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 9px 13px;
            border-radius: var(--r);
            font-size: .83rem;
            font-family: inherit;
            outline: none;
            transition: border-color .18s, box-shadow .18s;
            width: 100%
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .08)
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px
        }

        .toggle-row {
            display: flex;
            gap: 7px;
            flex-wrap: wrap
        }

        .rtog-btn {
            flex: 1;
            min-width: 80px;
            padding: 8px 10px;
            border-radius: var(--r);
            font-size: .75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text2);
            font-family: inherit;
            transition: all .15s;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px
        }

        .rtog-btn.active {
            background: var(--blue-s);
            border-color: #93c5fd;
            color: var(--blue)
        }

        .rtog-btn:hover:not(.active) {
            border-color: var(--border2);
            color: var(--text)
        }

        .char-count {
            font-size: .68rem;
            color: var(--text3);
            text-align: right
        }

        .btn-cancel {
            padding: 8px 16px;
            border-radius: var(--r);
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text2);
            font-size: .82rem;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s
        }

        .btn-cancel:hover {
            background: var(--bg);
            color: var(--text)
        }

        .btn-send {
            background: var(--blue);
            border: none;
            color: #fff;
            padding: 8px 20px;
            border-radius: var(--r);
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: background .15s
        }

        .btn-send:hover {
            background: var(--blue-d)
        }

        .btn-send:disabled {
            opacity: .5;
            cursor: not-allowed
        }

        .msg-type-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px
        }

        .msg-type-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 11px 8px 9px;
            border-radius: var(--r);
            border: 1.5px solid var(--border);
            background: var(--bg);
            cursor: pointer;
            transition: all .15s;
            text-align: center
        }

        .msg-type-btn:hover,
        .msg-type-btn.active {
            border-color: var(--blue);
            background: var(--blue-s)
        }

        .msg-type-btn.active {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10)
        }

        .mt-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .88rem;
            flex-shrink: 0;
            transition: transform .15s
        }

        .msg-type-btn:hover .mt-icon,
        .msg-type-btn.active .mt-icon {
            transform: scale(1.08)
        }

        .mt-label {
            font-size: .74rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1
        }

        .msg-type-btn.active .mt-label {
            color: var(--blue)
        }

        .mt-desc {
            font-size: .62rem;
            color: var(--text3);
            line-height: 1.3
        }

        .mt-preview {
            display: flex;
            align-items: center;
            margin-top: 8px;
            padding: 7px 11px;
            background: var(--bg);
            border-radius: var(--r);
            border: 1px solid var(--border)
        }

        .view-modal {
            max-width: 460px
        }

        .view-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text);
            margin: 10px 0 7px
        }

        .view-msg {
            font-size: .84rem;
            color: var(--text2);
            line-height: 1.65
        }

        .view-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border)
        }

        .view-meta-row {
            display: flex;
            gap: 10px;
            font-size: .75rem
        }

        .view-meta-row span:first-child {
            color: var(--text3);
            min-width: 88px;
            flex-shrink: 0
        }

        .view-meta-row span:last-child {
            color: var(--text)
        }

        /* ── Quick Templates ── */
        .tpl-section {
            display: flex;
            flex-direction: column;
            gap: 7px
        }

        .tpl-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px
        }

        .tpl-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .72rem;
            font-weight: 600;
            color: var(--blue);
            cursor: pointer;
            background: none;
            border: none;
            font-family: inherit;
            padding: 0;
            transition: color .15s
        }

        .tpl-toggle:hover {
            color: var(--blue-d)
        }

        .tpl-toggle i.fa-chevron-down {
            transition: transform .22s
        }

        .tpl-toggle.open i.fa-chevron-down {
            transform: rotate(180deg)
        }

        .tpl-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 7px;
            overflow: hidden;
            max-height: 0;
            transition: max-height .3s ease, opacity .25s;
            opacity: 0;
            pointer-events: none
        }

        .tpl-grid.open {
            max-height: 600px;
            opacity: 1;
            pointer-events: all
        }

        .tpl-card {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 9px 11px;
            border-radius: var(--r);
            border: 1.5px solid var(--border);
            background: var(--bg);
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            transition: all .15s
        }

        .tpl-card:hover {
            border-color: var(--blue);
            background: var(--blue-s);
            transform: translateY(-1px)
        }

        .tpl-card-head {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .73rem;
            font-weight: 700;
            color: var(--text)
        }

        .tpl-card-head i {
            font-size: .65rem;
            width: 16px;
            text-align: center
        }

        .tpl-card-preview {
            font-size: .65rem;
            color: var(--text3);
            line-height: 1.35;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .tpl-card:hover .tpl-card-head {
            color: var(--blue)
        }

        .tpl-divider {
            height: 1px;
            background: var(--border);
            margin: 2px 0
        }

        @media(max-width:900px) {
            .sg {
                grid-template-columns: repeat(2, 1fr)
            }

            .notif-msg {
                max-width: 140px
            }
        }

        @media(max-width:600px) {
            .msg-type-grid {
                grid-template-columns: repeat(2, 1fr)
            }

            .tpl-grid {
                grid-template-columns: 1fr
            }

            th:nth-child(5),
            td:nth-child(5),
            th:nth-child(6),
            td:nth-child(6) {
                display: none
            }
        }
    </style>
</head>

<body>
    <?php include_once('../admin_pages/sidebar.php'); ?>
    <?php include_once('adminheader.php'); ?>

    <div class="main">
        <div class="pg">

            <div class="ph-actions">
                <div class="pg-head" style="margin-bottom:0">
                    <h1><i class="fas fa-bell"></i>Notifications</h1>
                    <p>Manage all user &amp; admin notifications and send custom messages</p>
                </div>
                <button class="btn-compose" onclick="openCompose()"><i class="fas fa-paper-plane"></i> Send Message</button>
            </div>

            <div class="sg">
                <div class="sc">
                    <div class="si b"><i class="fas fa-bell"></i></div>
                    <div>
                        <div class="sv"><?php echo number_format($totalNotifs); ?></div>
                        <div class="sl">Total Sent</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si p"><i class="fas fa-envelope"></i></div>
                    <div>
                        <div class="sv"><?php echo number_format($unreadNotifs); ?></div>
                        <div class="sl">Unread</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si a"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="sv"><?php echo number_format($warningNotifs); ?></div>
                        <div class="sl">Warnings Sent</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si g"><i class="fas fa-calendar-day"></i></div>
                    <div>
                        <div class="sv"><?php echo number_format($todayNotifs); ?></div>
                        <div class="sl">Sent Today</div>
                    </div>
                </div>
            </div>

            <div class="toolbar">
                <div class="search-wrap"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search by recipient, title, message…"></div>
                <select class="filter-sel" id="readFilter">
                    <option value="all">All Status</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>
                <select class="filter-sel" id="recipTypeFilter">
                    <option value="all">All Recipients</option>
                    <option value="user">Users</option>
                    <option value="admin">Admins</option>
                </select>
                <select class="filter-sel" id="sortFilter">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>

            <div class="type-tabs">
                <div class="type-tab active" data-type="all">All <span class="tc" id="tc-all">0</span></div>
                <div class="type-tab" data-type="warning"><i class="fas fa-triangle-exclamation"></i> Warning <span class="tc" id="tc-warning">0</span></div>
                <div class="type-tab" data-type="admin_message"><i class="fas fa-envelope"></i> Message <span class="tc" id="tc-admin_message">0</span></div>
                <div class="type-tab" data-type="upload_approved"><i class="fas fa-check"></i> Approved <span class="tc" id="tc-upload_approved">0</span></div>
                <div class="type-tab" data-type="upload_rejected"><i class="fas fa-xmark"></i> Rejected <span class="tc" id="tc-upload_rejected">0</span></div>
                <div class="type-tab" data-type="new_upload"><i class="fas fa-cloud-arrow-up"></i> Upload <span class="tc" id="tc-new_upload">0</span></div>
                <div class="type-tab" data-type="banned_content"><i class="fas fa-ban"></i> Banned <span class="tc" id="tc-banned_content">0</span></div>
            </div>

            <div class="panel">
                <div class="ph">
                    <span class="pt">All Notifications</span>
                    <span style="font-size:.75rem;color:var(--text3)" id="countLabel"></span>
                </div>
                <div class="bulk-bar" id="bulkBar">
                    <span class="bulk-count"><i class="fas fa-check-square"></i><span id="bulkCountLabel">0 selected</span></span>
                    <div class="bulk-sep"></div>
                    <button class="btn-bulk-cancel" onclick="clearSelection()"><i class="fas fa-xmark"></i> Deselect All</button>
                    <button class="btn-bulk-del" onclick="bulkDelete()"><i class="fas fa-trash-can"></i> Delete Selected</button>
                </div>
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="row-check" id="checkAll" onchange="toggleAll(this.checked)"></th>
                                <th>Recipient</th>
                                <th>Notification</th>
                                <th>Type</th>
                                <th>Resource</th>
                                <th>Status</th>
                                <th>Sent</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="notifBody">
                            <?php
                            $typeLabels = [
                                'warning' => ['Warning', 'fa-triangle-exclamation'],
                                'admin_message' => ['Message', 'fa-envelope'],
                                'upload_approved' => ['Approved', 'fa-check'],
                                'upload_rejected' => ['Rejected', 'fa-xmark'],
                                'new_upload' => ['Upload', 'fa-cloud-arrow-up'],
                                'banned_content' => ['Banned', 'fa-ban'],
                            ];
                            foreach ($notifications as $n):
                                $rName    = htmlspecialchars($n['recipient_name'] ?? 'Unknown');
                                $rEmail   = htmlspecialchars($n['recipient_email'] ?? '');
                                $rImg     = !empty($n['recipient_image']) ? htmlspecialchars($n['recipient_image']) : '';
                                $rInit    = strtoupper(substr($n['recipient_name'] ?? '?', 0, 1));
                                [$tLabel, $tIcon] = $typeLabels[$n['type']] ?? ['Other', 'fa-bell'];
                                $typeClass = 'bdg-' . $n['type'];
                                $isRead   = (int)$n['is_read'];
                                $created  = date('d M Y, h:i A', strtotime($n['created_at']));
                                $createdR = date('d M Y', strtotime($n['created_at']));
                                // Determine recipient type
                                $recipType = 'unknown';
                                if (!empty($n['user_id']))  $recipType = 'user';
                                elseif (!empty($n['admin_id'])) $recipType = 'admin';
                                $rtMap = ['admin' => ['Admin', 'fa-shield-halved', 'rt-admin'], 'user' => ['User', 'fa-user', 'rt-user'], 'unknown' => ['Unknown', 'fa-circle-question', 'rt-unknown']];
                                [$rtLabel, $rtIcon, $rtCls] = $rtMap[$recipType];
                                $vd = htmlspecialchars(json_encode([
                                    'recipient' => $n['recipient_name'] ?? 'Unknown',
                                    'email' => $n['recipient_email'] ?? '',
                                    'recipType' => $recipType,
                                    'type' => $n['type'],
                                    'typeLabel' => $tLabel,
                                    'typeIcon' => $tIcon,
                                    'typeClass' => $typeClass,
                                    'title' => $n['title'],
                                    'message' => $n['message'],
                                    'resource' => $n['resource_type'] ?? '',
                                    'resTitle' => $n['resource_title'] ?? '',
                                    'read' => $isRead,
                                    'created' => $created,
                                    'fromName' => $n['from_name'] ?? '',
                                ]), ENT_QUOTES);
                            ?>
                                <tr class="notif-row"
                                    data-type="<?php echo $n['type']; ?>"
                                    data-read="<?php echo $isRead; ?>"
                                    data-recip-type="<?php echo $recipType; ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower(($n['recipient_name'] ?? '') . ' ' . ($n['recipient_email'] ?? '') . ' ' . $n['title'] . ' ' . $n['message'])); ?>"
                                    data-created="<?php echo strtotime($n['created_at']); ?>"
                                    data-id="<?php echo $n['notif_id']; ?>">
                                    <td><input type="checkbox" class="row-check notif-check" value="<?php echo $n['notif_id']; ?>" onchange="onRowCheck()"></td>
                                    <td>
                                        <div class="recip">
                                            <div class="recip-av" id="av-<?php echo $n['notif_id']; ?>">
                                                <?php if ($rImg): ?><img src="<?php echo $rImg; ?>" alt="<?php echo $rInit; ?>" onerror="this.remove();document.getElementById('av-<?php echo $n['notif_id']; ?>').textContent='<?php echo $rInit; ?>'">
                                                <?php else: echo $rInit;
                                                endif; ?>
                                            </div>
                                            <div>
                                                <div class="recip-name"><?php echo $rName; ?></div>
                                                <div class="recip-email"><?php echo $rEmail; ?></div>
                                                <span class="recip-type <?php echo $rtCls; ?>"><i class="fas <?php echo $rtIcon; ?>" style="font-size:.5rem"></i> <?php echo $rtLabel; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="notif-title"><span class="read-dot <?php echo $isRead ? 'dot-read' : 'dot-unread'; ?>"></span><?php echo htmlspecialchars($n['title']); ?></div>
                                        <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                                    </td>
                                    <td><span class="bdg <?php echo $typeClass; ?>"><i class="fas <?php echo $tIcon; ?>"></i> <?php echo $tLabel; ?></span></td>
                                    <td><?php if (!empty($n['resource_type'])): ?><span class="res-chip"><i class="fas fa-file-lines" style="font-size:.6rem"></i> <?php echo htmlspecialchars(ucfirst($n['resource_type'])); ?></span><?php else: ?><span style="color:var(--border2)">—</span><?php endif; ?></td>
                                    <td><?php if ($isRead): ?><span class="bdg" style="background:var(--green-s);color:#065f46"><i class="fas fa-check" style="font-size:.58rem"></i> Read</span><?php else: ?><span class="bdg" style="background:var(--blue-s);color:var(--blue)"><i class="fas fa-circle" style="font-size:.4rem"></i> Unread</span><?php endif; ?></td>
                                    <td style="font-size:.75rem;color:var(--text3);white-space:nowrap"><?php echo $createdR; ?></td>
                                    <td style="white-space:nowrap">
                                        <button class="act-btn" title="View" onclick='viewNotif(<?php echo $n["notif_id"]; ?>,<?php echo $vd; ?>)'><i class="fas fa-eye"></i></button>
                                        <button class="act-btn del" title="Delete" onclick="deleteNotif(<?php echo $n['notif_id']; ?>,this)"><i class="fas fa-trash-can"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="empty" style="display:none"><i class="fas fa-bell-slash"></i>No notifications found. Try adjusting your filters.</div>
                <div class="pag" id="pagination"></div>
            </div>

        </div>
    </div>

    <!-- COMPOSE MODAL -->
    <div class="modal-overlay" id="composeOverlay">
        <div class="modal">
            <div class="modal-head">
                <div class="modal-title"><i class="fas fa-paper-plane"></i>Send Custom Message</div><button class="modal-close" onclick="closeCompose()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="form-label">Recipient Mode</div>
                    <div class="toggle-row">
                        <button class="rtog-btn active" id="togSingle" onclick="setRecipMode('single')"><i class="fas fa-user"></i> Single User</button>
                        <button class="rtog-btn" id="togAll" onclick="setRecipMode('all')"><i class="fas fa-users"></i> Broadcast</button>
                    </div>
                </div>
                <div id="singleWrap">
                    <div class="form-group" style="margin-bottom:14px">
                        <div class="form-label">User Type</div>
                        <div class="toggle-row">
                            <button class="rtog-btn active" id="utype-student" onclick="setUserType('student')"><i class="fas fa-user-graduate"></i> Student</button>
                            <button class="rtog-btn" id="utype-tutor" onclick="setUserType('tutor')"><i class="fas fa-chalkboard-teacher"></i> Tutor</button>
                            <button class="rtog-btn" id="utype-admin" onclick="setUserType('admin')"><i class="fas fa-shield-halved"></i> Admin</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-label">Select Recipient</div>
                        <select class="form-select" id="recipStudent">
                            <option value="">— Choose a student —</option><?php foreach ($students as $u): ?><option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?> — <?php echo htmlspecialchars($u['email']); ?></option><?php endforeach; ?>
                        </select>
                        <select class="form-select" id="recipTutor" style="display:none">
                            <option value="">— Choose a tutor —</option><?php foreach ($tutors as $u): ?><option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?> — <?php echo htmlspecialchars($u['email']); ?></option><?php endforeach; ?>
                        </select>
                        <select class="form-select" id="recipAdmin" style="display:none">
                            <option value="">— Choose an admin —</option><?php foreach ($admins as $u): ?><option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?> — <?php echo htmlspecialchars($u['email']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="broadcastWrap" style="display:none">
                    <div class="form-group">
                        <div class="form-label">Send To</div>
                        <select class="form-select" id="broadcastTarget">
                            <option value="all_students">All Students</option>
                            <option value="all_tutors">All Tutors</option>
                            <option value="all_admins">All Admins</option>
                            <option value="everyone">Everyone</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-label">Message Type</div>
                    <div class="msg-type-grid">
                        <button type="button" class="msg-type-btn active" data-type="admin_message" onclick="setMsgType('admin_message',this)"><span class="mt-icon" style="background:rgba(37,99,235,.12);color:#2563eb"><i class="fas fa-envelope"></i></span><span class="mt-label">Message</span><span class="mt-desc">General info or announcement</span></button>
                        <button type="button" class="msg-type-btn" data-type="warning" onclick="setMsgType('warning',this)"><span class="mt-icon" style="background:rgba(220,38,38,.12);color:#dc2626"><i class="fas fa-triangle-exclamation"></i></span><span class="mt-label">Warning</span><span class="mt-desc">Policy violation or caution</span></button>
                        <button type="button" class="msg-type-btn" data-type="upload_approved" onclick="setMsgType('upload_approved',this)"><span class="mt-icon" style="background:rgba(5,150,105,.12);color:#059669"><i class="fas fa-check-circle"></i></span><span class="mt-label">Approved</span><span class="mt-desc">Content approved notice</span></button>
                        <button type="button" class="msg-type-btn" data-type="upload_rejected" onclick="setMsgType('upload_rejected',this)"><span class="mt-icon" style="background:rgba(217,119,6,.12);color:#d97706"><i class="fas fa-times-circle"></i></span><span class="mt-label">Rejected</span><span class="mt-desc">Content rejected notice</span></button>
                        <button type="button" class="msg-type-btn" data-type="banned_content" onclick="setMsgType('banned_content',this)"><span class="mt-icon" style="background:rgba(220,38,38,.12);color:#dc2626"><i class="fas fa-ban"></i></span><span class="mt-label">Banned</span><span class="mt-desc">Content removed / banned</span></button>
                        <button type="button" class="msg-type-btn" data-type="new_upload" onclick="setMsgType('new_upload',this)"><span class="mt-icon" style="background:rgba(13,148,136,.12);color:#0d9488"><i class="fas fa-cloud-arrow-up"></i></span><span class="mt-label">Upload</span><span class="mt-desc">New upload notification</span></button>
                    </div>
                    <div class="mt-preview" id="mtPreview"><span id="mtPreviewPill" class="bdg bdg-admin_message"><i class="fas fa-envelope"></i> Message</span><span style="font-size:.72rem;color:var(--text3);margin-left:6px">will be sent as this type</span></div>
                </div>
                <!-- Quick Templates -->
                <div class="form-group tpl-section">
                    <div class="tpl-label-row">
                        <div class="form-label" style="margin:0">Quick Templates</div>
                        <button type="button" class="tpl-toggle" id="tplToggle" onclick="toggleTemplates()">
                            <i class="fas fa-bolt"></i> Use a template <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="tpl-grid" id="tplGrid">
                        <button type="button" class="tpl-card" onclick="applyTemplate('welcome')">
                            <div class="tpl-card-head"><i class="fas fa-hand-wave" style="color:#059669"></i> Welcome</div>
                            <div class="tpl-card-preview">Welcome to ScholarSwap! We're glad you joined…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('policy_warning')">
                            <div class="tpl-card-head"><i class="fas fa-triangle-exclamation" style="color:#d97706"></i> Policy Warning</div>
                            <div class="tpl-card-preview">Your recent activity has violated our content policy…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('upload_approved')">
                            <div class="tpl-card-head"><i class="fas fa-check-circle" style="color:#059669"></i> Upload Approved</div>
                            <div class="tpl-card-preview">Your uploaded content has been reviewed and approved…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('upload_rejected')">
                            <div class="tpl-card-head"><i class="fas fa-times-circle" style="color:#dc2626"></i> Upload Rejected</div>
                            <div class="tpl-card-preview">Your upload could not be approved at this time…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('account_suspended')">
                            <div class="tpl-card-head"><i class="fas fa-ban" style="color:#dc2626"></i> Account Warning</div>
                            <div class="tpl-card-preview">We have placed a temporary restriction on your account…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('maintenance')">
                            <div class="tpl-card-head"><i class="fas fa-wrench" style="color:#2563eb"></i> Maintenance</div>
                            <div class="tpl-card-preview">ScholarSwap will undergo scheduled maintenance…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('new_feature')">
                            <div class="tpl-card-head"><i class="fas fa-sparkles" style="color:#7c3aed"></i> New Feature</div>
                            <div class="tpl-card-preview">We've launched an exciting new feature on ScholarSwap…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('copyright')">
                            <div class="tpl-card-head"><i class="fas fa-copyright" style="color:#d97706"></i> Copyright Notice</div>
                            <div class="tpl-card-preview">Content you uploaded may infringe on copyright…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('complete_profile')">
                            <div class="tpl-card-head"><i class="fas fa-user-pen" style="color:#2563eb"></i> Complete Profile</div>
                            <div class="tpl-card-preview">Your profile is incomplete. Adding details helps others…</div>
                        </button>
                        <button type="button" class="tpl-card" onclick="applyTemplate('thank_you')">
                            <div class="tpl-card-head"><i class="fas fa-heart" style="color:#e11d48"></i> Thank You</div>
                            <div class="tpl-card-preview">Thank you for your valuable contribution to ScholarSwap…</div>
                        </button>
                    </div>
                    <div class="tpl-divider" id="tplDivider" style="display:none"></div>
                </div>

                <div class="form-group">
                    <div class="form-label">Title</div><input type="text" class="form-input" id="msgTitle" placeholder="e.g. Important Update" maxlength="200">
                </div>
                <div class="form-group">
                    <div class="form-label">Message</div><textarea class="form-textarea" id="msgBody" placeholder="Write your message here…" maxlength="1000" oninput="updateChar()"></textarea>
                    <div class="char-count"><span id="charCount">0</span> / 1000</div>
                </div>
            </div>
            <div class="modal-foot"><button class="btn-cancel" onclick="closeCompose()">Cancel</button><button class="btn-send" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i> Send</button></div>
        </div>
    </div>

    <!-- VIEW MODAL -->
    <div class="modal-overlay" id="viewOverlay">
        <div class="modal view-modal">
            <div class="modal-head">
                <div class="modal-title"><i class="fas fa-eye"></i>Notification Detail</div><button class="modal-close" onclick="document.getElementById('viewOverlay').classList.remove('open')"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="viewBody"></div>
        </div>
    </div>

    <script>
        const allRows = Array.from(document.querySelectorAll('.notif-row'));
        const PER_PAGE = 15;
        let page = 1,
            filtered = [...allRows],
            activeType = 'all',
            recipMode = 'single',
            userType = 'student',
            msgType = 'admin_message';
        const MT_META = {
            admin_message: {
                label: 'Message',
                icon: 'fa-envelope',
                cls: 'bdg-admin_message'
            },
            warning: {
                label: 'Warning',
                icon: 'fa-triangle-exclamation',
                cls: 'bdg-warning'
            },
            upload_approved: {
                label: 'Approved',
                icon: 'fa-check-circle',
                cls: 'bdg-upload_approved'
            },
            upload_rejected: {
                label: 'Rejected',
                icon: 'fa-times-circle',
                cls: 'bdg-upload_rejected'
            },
            banned_content: {
                label: 'Banned',
                icon: 'fa-ban',
                cls: 'bdg-banned_content'
            },
            new_upload: {
                label: 'Upload',
                icon: 'fa-cloud-arrow-up',
                cls: 'bdg-new_upload'
            }
        };
        const tc = {};
        allRows.forEach(r => {
            const t = r.dataset.type;
            tc[t] = (tc[t] || 0) + 1;
        });
        document.getElementById('tc-all').textContent = allRows.length;
        Object.entries(tc).forEach(([t, c]) => {
            const e = document.getElementById('tc-' + t);
            if (e) e.textContent = c;
        });

        function applyFilters() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            const rd = document.getElementById('readFilter').value;
            const rt = document.getElementById('recipTypeFilter').value;
            const so = document.getElementById('sortFilter').value;
            filtered = allRows.filter(r => {
                if (activeType !== 'all' && r.dataset.type !== activeType) return false;
                if (q && !r.dataset.search.includes(q)) return false;
                if (rd === 'read' && r.dataset.read !== '1') return false;
                if (rd === 'unread' && r.dataset.read !== '0') return false;
                if (rt !== 'all' && r.dataset.recipType !== rt) return false;
                return true;
            });
            filtered.sort((a, b) => so === 'oldest' ? a.dataset.created - b.dataset.created : b.dataset.created - a.dataset.created);
            page = 1;
            render();
        }

        function render() {
            const tbody = document.getElementById('notifBody'),
                empty = document.getElementById('emptyState'),
                label = document.getElementById('countLabel');
            tbody.innerHTML = '';
            const s = (page - 1) * PER_PAGE,
                slice = filtered.slice(s, s + PER_PAGE);
            label.textContent = filtered.length + ' notification' + (filtered.length !== 1 ? 's' : '');
            empty.style.display = slice.length ? 'none' : '';
            slice.forEach(r => tbody.appendChild(r));
            buildPagination();
        }

        function buildPagination() {
            const pag = document.getElementById('pagination');
            pag.innerHTML = '';
            const total = Math.ceil(filtered.length / PER_PAGE);
            if (total <= 1) return;
            const prev = mkBtn('<i class="fas fa-chevron-left"></i>');
            prev.disabled = page === 1;
            prev.onclick = () => {
                page--;
                render();
            };
            pag.appendChild(prev);
            const range = 2,
                rs = Math.max(1, page - range),
                re = Math.min(total, page + range);
            if (rs > 1) {
                pag.appendChild(mkPageBtn(1));
                if (rs > 2) addEllipsis();
            }
            for (let i = rs; i <= re; i++) pag.appendChild(mkPageBtn(i));
            if (re < total) {
                if (re < total - 1) addEllipsis();
                pag.appendChild(mkPageBtn(total));
            }
            const next = mkBtn('<i class="fas fa-chevron-right"></i>');
            next.disabled = page === total;
            next.onclick = () => {
                page++;
                render();
            };
            pag.appendChild(next);
        }

        function mkPageBtn(i) {
            const b = mkBtn(i);
            if (i === page) b.classList.add('active');
            b.onclick = () => {
                page = i;
                render();
            };
            return b;
        }

        function mkBtn(l) {
            const b = document.createElement('button');
            b.className = 'pag-btn';
            b.innerHTML = l;
            return b;
        }

        function addEllipsis() {
            const s = document.createElement('span');
            s.className = 'pag-ellipsis';
            s.textContent = '…';
            document.getElementById('pagination').appendChild(s);
        }
        document.querySelectorAll('.type-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.type-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                activeType = tab.dataset.type;
                applyFilters();
            });
        });
        ['searchInput', 'readFilter', 'recipTypeFilter', 'sortFilter'].forEach(id => document.getElementById(id).addEventListener(id === 'searchInput' ? 'input' : 'change', applyFilters));
        applyFilters();

        // ── Quick Templates ──
        const TEMPLATES = {
            welcome: {
                type: 'admin_message',
                title: 'Welcome to ScholarSwap!',
                message: "Welcome to ScholarSwap! 🎉 We're thrilled to have you on board. Start exploring thousands of free notes, books, and newspapers shared by students like you. Don't forget to complete your profile and upload your own study materials to help the community grow!"
            },
            policy_warning: {
                type: 'warning',
                title: 'Important: Policy Violation Notice',
                message: "Your recent activity on ScholarSwap has been flagged as a violation of our Content Policy. Please review our guidelines carefully. Repeated violations may result in further restrictions to your account. If you believe this is a mistake, please contact our support team."
            },
            upload_approved: {
                type: 'upload_approved',
                title: 'Your Upload Has Been Approved ✓',
                message: "Great news! Your uploaded content has been reviewed by our moderation team and is now live on ScholarSwap. Students across the platform can now access and benefit from your contribution. Thank you for helping build a better learning community!"
            },
            upload_rejected: {
                type: 'upload_rejected',
                title: 'Upload Could Not Be Approved',
                message: "Unfortunately, your recent upload could not be approved at this time. It may not meet our content quality standards, or it may contain copyrighted material. Please review our upload guidelines and resubmit with the necessary corrections. Feel free to reach out if you need assistance."
            },
            account_suspended: {
                type: 'warning',
                title: 'Account Activity Restriction',
                message: "We have placed a temporary restriction on your account due to activity that violates our community guidelines. During this time, some features may be limited. Please review our Terms of Service. This restriction will be reviewed shortly. Contact support if you have questions."
            },
            maintenance: {
                type: 'admin_message',
                title: 'Scheduled Maintenance Notice',
                message: "ScholarSwap will undergo scheduled maintenance to improve performance and add new features. During this period, the platform may experience brief downtime or reduced functionality. We apologize for any inconvenience and thank you for your patience. Normal service will resume shortly."
            },
            new_feature: {
                type: 'admin_message',
                title: 'Exciting New Feature on ScholarSwap! 🚀',
                message: "We've just launched an exciting new feature on ScholarSwap designed to improve your learning experience. Log in today to explore it and let us know what you think through our feedback form. We're always working to make ScholarSwap better for you!"
            },
            copyright: {
                type: 'warning',
                title: 'Copyright Infringement Notice',
                message: "Content you recently uploaded to ScholarSwap may infringe on the intellectual property rights of a third party. We have temporarily hidden this content pending your review. Please ensure all materials you upload are either your own original work or properly licensed for sharing. Repeated copyright violations may result in account action."
            },
            complete_profile: {
                type: 'admin_message',
                title: 'Complete Your Profile',
                message: "Your ScholarSwap profile is incomplete! Adding your institution, subjects, and a profile photo helps other students find and connect with you. A complete profile also builds trust and increases engagement with your uploaded resources. It only takes a minute — log in and update your profile today!"
            },
            thank_you: {
                type: 'admin_message',
                title: 'Thank You for Your Contribution! 🙏',
                message: "Thank you for your valuable contribution to ScholarSwap! Your uploads and participation help thousands of students access quality study materials for free. The ScholarSwap community is stronger because of members like you. Keep sharing and keep learning!"
            },
        };

        function toggleTemplates() {
            const grid = document.getElementById('tplGrid');
            const btn = document.getElementById('tplToggle');
            const div = document.getElementById('tplDivider');
            const isOpen = grid.classList.toggle('open');
            btn.classList.toggle('open', isOpen);
            if (div) div.style.display = isOpen ? '' : 'none';
        }

        function applyTemplate(key) {
            const t = TEMPLATES[key];
            if (!t) return;
            // Fill title & message
            document.getElementById('msgTitle').value = t.title;
            document.getElementById('msgBody').value = t.message;
            updateChar();
            // Switch message type
            const btn = document.querySelector('.msg-type-btn[data-type="' + t.type + '"]');
            setMsgType(t.type, btn);
            // Close the templates panel
            document.getElementById('tplGrid').classList.remove('open');
            document.getElementById('tplToggle').classList.remove('open');
            const div = document.getElementById('tplDivider');
            if (div) div.style.display = 'none';
            // Scroll to title field
            document.getElementById('msgTitle').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            document.getElementById('msgTitle').focus();
        }

        function openCompose() {
            document.getElementById('composeOverlay').classList.add('open');
        }

        function closeCompose() {
            document.getElementById('composeOverlay').classList.remove('open');
            document.getElementById('msgTitle').value = '';
            document.getElementById('msgBody').value = '';
            document.getElementById('charCount').textContent = '0';
            setMsgType('admin_message', document.querySelector('.msg-type-btn[data-type="admin_message"]'));
            // Reset templates panel
            document.getElementById('tplGrid').classList.remove('open');
            document.getElementById('tplToggle').classList.remove('open');
            const div = document.getElementById('tplDivider');
            if (div) div.style.display = 'none';
        }

        function setMsgType(type, btn) {
            msgType = type;
            document.querySelectorAll('.msg-type-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            const m = MT_META[type] || MT_META['admin_message'];
            const p = document.getElementById('mtPreviewPill');
            if (p) {
                p.className = 'bdg ' + m.cls;
                p.innerHTML = '<i class="fas ' + m.icon + '"></i> ' + m.label;
            }
        }

        function setRecipMode(m) {
            recipMode = m;
            document.getElementById('togSingle').classList.toggle('active', m === 'single');
            document.getElementById('togAll').classList.toggle('active', m === 'all');
            document.getElementById('singleWrap').style.display = m === 'single' ? '' : 'none';
            document.getElementById('broadcastWrap').style.display = m === 'all' ? '' : 'none';
        }

        function setUserType(t) {
            userType = t;
            ['student', 'tutor', 'admin'].forEach(x => {
                document.getElementById('utype-' + x).classList.toggle('active', x === t);
                const c = x.charAt(0).toUpperCase() + x.slice(1);
                document.getElementById('recip' + c).style.display = x === t ? '' : 'none';
            });
        }

        function updateChar() {
            document.getElementById('charCount').textContent = document.getElementById('msgBody').value.length;
        }
        async function sendMessage() {
            const title = document.getElementById('msgTitle').value.trim(),
                message = document.getElementById('msgBody').value.trim();
            if (!title) return sw('warning', 'Title required', 'Please enter a title.');
            if (!message) return sw('warning', 'Message required', 'Please write a message.');
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('title', title);
            fd.append('message', message);
            fd.append('recip_mode', recipMode);
            fd.append('msg_type', msgType);
            if (recipMode === 'single') {
                const c = userType.charAt(0).toUpperCase() + userType.slice(1);
                const s = document.getElementById('recip' + c).value;
                if (!s) return sw('warning', 'Select a user', 'Please choose a recipient.');
                fd.append('user_id', s);
                fd.append('user_type', userType);
            } else fd.append('user_type', document.getElementById('broadcastTarget').value);
            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
            try {
                const r = await fetch('auth/handler_notification.php', {
                    method: 'POST',
                    body: fd
                });
                const d = await r.json();
                if (d.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sent!',
                        text: d.message,
                        timer: 2500,
                        showConfirmButton: false
                    });
                    closeCompose();
                    setTimeout(() => location.reload(), 2600);
                } else sw('error', 'Failed', d.message);
            } catch {
                sw('error', 'Error', 'Could not connect.');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        }

        function viewNotif(id, n) {
            const rl = n.recipType === 'admin' ? '<span style="display:inline-flex;align-items:center;gap:3px;font-size:.62rem;font-weight:700;padding:1px 6px;border-radius:4px;background:var(--purple-s);color:var(--purple)"><i class="fas fa-shield-halved" style="font-size:.5rem"></i> Admin</span>' : '<span style="display:inline-flex;align-items:center;gap:3px;font-size:.62rem;font-weight:700;padding:1px 6px;border-radius:4px;background:var(--blue-s);color:var(--blue)"><i class="fas fa-user" style="font-size:.5rem"></i> User</span>';
            document.getElementById('viewBody').innerHTML = `<div style="margin-bottom:10px"><span class="bdg ${esc(n.typeClass)}"><i class="fas ${esc(n.typeIcon)}"></i> ${esc(n.typeLabel)}</span></div><div class="view-title">${esc(n.title)}</div><div class="view-msg">${esc(n.message)}</div><div class="view-meta"><div class="view-meta-row"><span>Recipient</span><span>${esc(n.recipient)} ${rl}${n.email?' <span style="color:var(--text3)">&lt;'+esc(n.email)+'&gt;</span>':''}</span></div>${n.resource?'<div class="view-meta-row"><span>Resource</span><span>'+esc(n.resource)+(n.resTitle?' — '+esc(n.resTitle):'')+' </span></div>':''}${n.fromName?'<div class="view-meta-row"><span>Sent by</span><span>'+esc(n.fromName)+'</span></div>':''}<div class="view-meta-row"><span>Status</span><span>${n.read?'✓ Read':'● Unread'}</span></div><div class="view-meta-row"><span>Sent at</span><span>${esc(n.created)}</span></div></div>`;
            document.getElementById('viewOverlay').classList.add('open');
        }
        async function deleteNotif(id, btn) {
            const r = await Swal.fire({
                title: 'Delete notification?',
                text: 'This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626'
            });
            if (!r.isConfirmed) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('notif_id', id);
            try {
                const res = await fetch('/ScholarSwap/admin/admin_pages/auth/handler_notification.php', {
                    method: 'POST',
                    body: fd
                });
                const d = await res.json();
                if (d.success) {
                    const row = btn.closest('tr');
                    row.style.cssText = 'opacity:0;transition:opacity .3s';
                    setTimeout(() => {
                        row.remove();
                        applyFilters();
                    }, 300);
                } else sw('error', 'Failed', d.message);
            } catch {
                sw('error', 'Error', 'Could not connect.');
            }
        }

        function getChecked() {
            return Array.from(document.querySelectorAll('#notifBody .notif-check:checked'));
        }

        function onRowCheck() {
            const c = getChecked(),
                cnt = c.length;
            document.querySelectorAll('#notifBody .notif-check').forEach(cb => cb.closest('tr').classList.toggle('selected', cb.checked));
            document.getElementById('bulkBar').classList.toggle('show', cnt > 0);
            document.getElementById('bulkCountLabel').textContent = cnt + ' selected';
            const total = document.querySelectorAll('#notifBody .notif-check').length;
            document.getElementById('checkAll').indeterminate = cnt > 0 && cnt < total;
            document.getElementById('checkAll').checked = cnt > 0 && cnt === total;
        }

        function toggleAll(checked) {
            document.querySelectorAll('#notifBody .notif-check').forEach(cb => {
                cb.checked = checked;
            });
            onRowCheck();
        }

        function clearSelection() {
            document.querySelectorAll('.notif-check').forEach(cb => {
                cb.checked = false;
            });
            document.getElementById('checkAll').checked = false;
            document.getElementById('checkAll').indeterminate = false;
            document.getElementById('bulkBar').classList.remove('show');
            document.querySelectorAll('#notifBody tr').forEach(r => r.classList.remove('selected'));
        }
        async function bulkDelete() {
            const c = getChecked();
            if (!c.length) return;
            const cnt = c.length,
                r = await Swal.fire({
                    title: `Delete ${cnt} notification${cnt>1?'s':''}?`,
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: `<i class="fas fa-trash-can"></i> Yes, delete ${cnt}`,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc2626'
                });
            if (!r.isConfirmed) return;
            const db = document.querySelector('.btn-bulk-del'),
                oh = db.innerHTML;
            db.disabled = true;
            db.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting…';
            let del = 0,
                fail = 0;
            for (const cb of c) {
                const id = cb.value,
                    row = cb.closest('tr');
                try {
                    const fd = new FormData();
                    fd.append('action', 'delete');
                    fd.append('notif_id', id);
                    const res = await fetch('/ScholarSwap/admin/admin_pages/auth/handler_notification.php', {
                        method: 'POST',
                        body: fd
                    });
                    const d = await res.json();
                    if (d.success) {
                        row.style.cssText = 'opacity:0;transition:opacity .25s';
                        setTimeout(() => row.remove(), 260);
                        del++;
                    } else fail++;
                } catch {
                    fail++;
                }
            }
            await new Promise(r => setTimeout(r, 300));
            clearSelection();
            applyFilters();
            db.disabled = false;
            db.innerHTML = oh;
            if (del > 0) Swal.fire({
                icon: 'success',
                title: `${del} deleted`,
                text: fail > 0 ? `${fail} could not be deleted.` : 'All selected notifications removed.',
                timer: 2500,
                showConfirmButton: false
            });
            else sw('error', 'Delete failed', 'None of the selected notifications could be deleted.');
        }
        const _or = render;
        window.render = function() {
            _or();
            clearSelection();
        };
        document.getElementById('composeOverlay').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeCompose();
        });
        document.getElementById('viewOverlay').addEventListener('click', e => {
            if (e.target === e.currentTarget) document.getElementById('viewOverlay').classList.remove('open');
        });

        function esc(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function sw(i, t, x) {
            return Swal.fire({
                icon: i,
                title: t,
                text: x
            });
        }
    </script>
</body>

</html>