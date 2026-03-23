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
    'users'     => q($conn, "SELECT COUNT(*) FROM users"),
    'students'  => q($conn, "SELECT COUNT(*) FROM students"),
    'tutors'    => q($conn, "SELECT COUNT(*) FROM tutors"),
    'admins'    => q($conn, "SELECT COUNT(*) FROM admin_user"),
    'notes'     => q($conn, "SELECT COUNT(*) FROM notes"),
    'books'     => q($conn, "SELECT COUNT(*) FROM books"),
    'papers'    => q($conn, "SELECT COUNT(*) FROM newspapers"),
    'n_pending' => q($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='pending'"),
    'b_pending' => q($conn, "SELECT COUNT(*) FROM books WHERE approval_status='pending'"),
    'p_pending' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'"),
];
$c['pending'] = $c['n_pending'] + $c['b_pending'] + $c['p_pending'];

// Admin info
$sa = $conn->prepare("SELECT first_name, last_name, role FROM admin_user WHERE admin_id=? LIMIT 1");
$sa->execute([$_SESSION['admin_id']]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);

// Admin avatar for topbar
$adminImgQ = $conn->prepare("SELECT profile_image FROM admin_user WHERE admin_id=? LIMIT 1");
$adminImgQ->execute([$_SESSION['admin_id']]);
$adminImg  = $adminImgQ->fetchColumn() ?: '';
$avatarSrc = !empty($adminImg) ? htmlspecialchars('../' . $adminImg) : '';
$initials  = strtoupper(($admin['first_name'][0] ?? '') . ($admin['last_name'][0] ?? ''));

// Students query
$sq = $conn->prepare("
    SELECT s.*,
        u.email, u.username, u.is_verified, u.is_active,
        u.created_at AS joined, u.last_login, u.profile_image, u.phone,
        (SELECT COUNT(*) FROM notes     WHERE user_id=s.user_id AND approval_status='approved') AS note_count,
        (SELECT COUNT(*) FROM books     WHERE user_id=s.user_id AND approval_status='approved') AS book_count,
        (SELECT COUNT(*) FROM downloads WHERE user_id=s.user_id) AS dl_count
    FROM students s
    JOIN users u ON s.user_id=u.user_id
    ORDER BY s.created_at DESC
");
$sq->execute();
$allStudents = $sq->fetchAll(PDO::FETCH_ASSOC);

$ss = [
    'total'      => count($allStudents),
    'verified'   => q($conn, "SELECT COUNT(*) FROM users u JOIN students s ON s.user_id=u.user_id WHERE u.is_verified=1"),
    'active'     => q($conn, "SELECT COUNT(*) FROM users u JOIN students s ON s.user_id=u.user_id WHERE u.is_active=1"),
    'inactive'   => q($conn, "SELECT COUNT(*) FROM users u JOIN students s ON s.user_id=u.user_id WHERE u.is_active=0"),
    'unverified' => q($conn, "SELECT COUNT(*) FROM users u JOIN students s ON s.user_id=u.user_id WHERE u.is_verified=0"),
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Students | ScholarSwap Admin</title>
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
            --sky: #0284c7;
            --sky-s: #e0f2fe;
            --rose: #e11d48;
            --rose-s: #ffe4e6;
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
            --sh2: 0 8px 32px rgba(0, 0, 0, .12);
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
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px;
        }

        .pg-head {
            margin-bottom: 20px;
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

        /* Stat strip */
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .sc {
            background: var(--surface);
            border-radius: var(--r2);
            padding: 14px 16px;
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 11px;
            transition: transform .18s, box-shadow .18s;
        }

        .sc:hover {
            transform: translateY(-2px);
            box-shadow: var(--sh2);
        }

        .si {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .88rem;
            flex-shrink: 0;
        }

        .si.b {
            background: var(--blue-s);
            color: var(--blue);
        }

        .si.g {
            background: var(--green-s);
            color: var(--green);
        }

        .si.a {
            background: var(--amber-s);
            color: var(--amber);
        }

        .si.r {
            background: var(--red-s);
            color: var(--red);
        }

        .si.sk {
            background: var(--sky-s);
            color: var(--sky);
        }

        .sv {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .sl {
            font-size: .67rem;
            color: var(--text3);
            margin-top: 2px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .t-search {
            display: flex;
            align-items: center;
            gap: 7px;
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 8px 14px;
            min-width: 200px;
            flex: 1;
            max-width: 340px;
            transition: border-color .18s;
        }

        .t-search:focus-within {
            border-color: var(--blue);
        }

        .t-search i {
            color: var(--text3);
            font-size: .8rem;
        }

        .t-search input {
            border: none;
            outline: none;
            font-size: .84rem;
            color: var(--text);
            width: 100%;
            background: none;
        }

        .t-search input::placeholder {
            color: var(--text3);
        }

        .fsel {
            padding: 8px 12px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: .82rem;
            color: var(--text2);
            background: var(--surface);
            cursor: pointer;
            outline: none;
            transition: border-color .18s;
        }

        .fsel:focus {
            border-color: var(--blue);
        }

        .ml {
            margin-left: auto;
        }

        .ftabs {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .ft {
            padding: 6px 13px;
            border-radius: 99px;
            border: 1.5px solid var(--border);
            font-size: .76rem;
            font-weight: 600;
            cursor: pointer;
            background: var(--surface);
            color: var(--text3);
            transition: all .14s;
        }

        .ft:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .ft.on {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue);
        }

        /* Bulk bar */
        .bulk-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            background: var(--blue-s);
            border: 1.5px solid rgba(37, 99, 235, .22);
            border-radius: 12px;
            padding: 11px 16px;
            margin-bottom: 12px;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-6px);
            transition: opacity .2s, transform .2s;
        }

        .bulk-bar.vis {
            opacity: 1;
            pointer-events: all;
            transform: translateY(0);
        }

        .bulk-info {
            font-size: .83rem;
            font-weight: 600;
            color: var(--blue);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .bulk-acts {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 13px;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            font-size: .78rem;
            font-weight: 600;
            transition: all .14s;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:active {
            transform: scale(.96);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: .72rem;
            border-radius: 7px;
        }

        .btn-view {
            background: var(--blue-s);
            color: var(--blue);
        }

        .btn-view:hover {
            background: var(--blue);
            color: #fff;
        }

        .btn-ok {
            background: var(--green-s);
            color: var(--green);
        }

        .btn-ok:hover {
            background: var(--green);
            color: #fff;
        }

        .btn-warn {
            background: var(--amber-s);
            color: var(--amber);
        }

        .btn-warn:hover {
            background: var(--amber);
            color: #fff;
        }

        .btn-sky {
            background: var(--sky-s);
            color: var(--sky);
        }

        .btn-sky:hover {
            background: var(--sky);
            color: #fff;
        }

        .btn-ban {
            background: var(--rose-s);
            color: var(--rose);
        }

        .btn-ban:hover {
            background: var(--rose);
            color: #fff;
        }

        .btn-unban {
            background: var(--green-s);
            color: var(--green);
        }

        .btn-unban:hover {
            background: var(--green);
            color: #fff;
        }

        .btn-outline {
            background: var(--surface);
            color: var(--text2);
            border: 1.5px solid var(--border);
        }

        .btn-outline:hover {
            background: var(--bg);
        }

        /* Panel + table */
        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .tw {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            padding: 9px 12px;
            background: var(--bg);
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            text-align: left;
        }

        thead th:first-child {
            width: 36px;
            text-align: center;
        }

        tbody td {
            padding: 10px 12px;
            font-size: .82rem;
            color: var(--text2);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover td {
            background: #fafbff;
        }

        tbody tr.sel td {
            background: #eff6ff;
        }

        tbody tr.banned td {
            background: #fff5f5;
        }

        tbody tr.banned:hover td {
            background: #fee2e2;
        }

        .cb-td {
            text-align: center;
        }

        input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--blue);
            cursor: pointer;
        }

        /* Badges */
        .bdg {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: .62rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .bdg-active {
            background: var(--green-s);
            color: #065f46;
        }

        .bdg-inactive {
            background: var(--rose-s);
            color: var(--rose);
        }

        .bdg-banned {
            background: #1e293b;
            color: #f1f5f9;
        }

        .bdg-verified {
            background: var(--sky-s);
            color: var(--sky);
        }

        .bdg-unverified {
            background: var(--amber-s);
            color: #92400e;
        }

        .bdg-course {
            background: var(--blue-s);
            color: var(--blue);
        }

        .bdg-gender-m {
            background: var(--sky-s);
            color: var(--sky);
        }

        .bdg-gender-f {
            background: #fce7f3;
            color: #9d174d;
        }

        .bdg-gender-o {
            background: var(--purple-s);
            color: var(--purple);
        }

        /* Avatar */
        .av {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
        }

        .av img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }

        .av-init-fallback {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 800;
            color: #fff;
        }

        .av.banned-av {
            background: linear-gradient(135deg, #9f1239, #e11d48);
        }

        .name-cell {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .u-meta {
            font-size: .67rem;
            color: var(--text3);
            margin-top: 1px;
        }

        /* Status dot */
        .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .dot-on {
            background: #059669;
        }

        .dot-off {
            background: #e11d48;
        }

        .dot-ban {
            background: #1e293b;
        }

        /* Pagination */
        .pgbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 8px;
        }

        .pgi {
            font-size: .75rem;
            color: var(--text3);
        }

        .pgbtns {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .pgb {
            min-width: 30px;
            height: 30px;
            padding: 0 6px;
            border-radius: 7px;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--text2);
            font-size: .74rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .13s;
        }

        .pgb:hover:not([disabled]) {
            border-color: var(--blue);
            color: var(--blue);
        }

        .pgb.on {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue);
        }

        .pgb[disabled] {
            opacity: .3;
            cursor: not-allowed;
        }

        .pg-ellipsis {
            color: var(--text3);
            padding: 0 4px;
            font-size: .8rem;
            align-self: center;
        }

        .empty {
            padding: 52px;
            text-align: center;
            color: var(--text3);
            font-size: .84rem;
        }

        .empty i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 12px;
            opacity: .18;
        }

        .empty strong {
            display: block;
            font-size: .95rem;
            color: var(--text2);
            margin-bottom: 4px;
        }

        /* ═══ MODAL ═══ */
        .mov {
            position: fixed;
            inset: 0;
            z-index: 9000;
            background: rgba(4, 8, 20, .82);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            visibility: hidden;
            transition: opacity .22s, visibility .22s;
        }

        .mov.show {
            opacity: 1;
            visibility: visible;
        }

        .mob {
            background: var(--surface);
            border-radius: 20px;
            width: min(700px, 96vw);
            max-height: 92vh;
            box-shadow: var(--sh2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: scale(.93) translateY(20px);
            opacity: 0;
            transition: transform .28s cubic-bezier(.34, 1.56, .64, 1), opacity .22s;
            border: 1px solid var(--border);
        }

        .mov.show .mob {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .mob-accent {
            height: 4px;
            flex-shrink: 0;
            background: linear-gradient(90deg, #2563eb, #6366f1, #0d9488);
        }

        .mob-profile {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #f8faff, #eff6ff);
        }

        .mob-av-lg {
            width: 62px;
            height: 62px;
            border-radius: 16px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #2563eb, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(37, 99, 235, .3);
        }

        .mob-av-lg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mob-av-lg.banned {
            background: linear-gradient(135deg, #9f1239, #e11d48);
            box-shadow: 0 4px 14px rgba(225, 29, 72, .3);
        }

        .mob-pinfo {
            flex: 1;
            min-width: 0;
        }

        .mob-pinfo h3 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text);
            margin: 0;
            line-height: 1.2;
        }

        .mob-pinfo p {
            font-size: .75rem;
            color: var(--text3);
            margin-top: 3px;
        }

        .mob-badges {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 7px;
        }

        .mob-x {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            background: rgba(0, 0, 0, .06);
            color: var(--text2);
            font-size: .9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .13s;
            flex-shrink: 0;
            align-self: flex-start;
        }

        .mob-x:hover {
            background: var(--red-s);
            color: var(--red);
        }

        .mob-metrics {
            display: flex;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .mob-met {
            flex: 1;
            padding: 13px 16px;
            text-align: center;
            border-right: 1px solid var(--border);
        }

        .mob-met:last-child {
            border-right: none;
        }

        .mob-met-v {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .mob-met-l {
            font-size: .63rem;
            color: var(--text3);
            margin-top: 3px;
        }

        .mob-body {
            overflow-y: auto;
            flex: 1;
        }

        .mob-body::-webkit-scrollbar {
            width: 4px;
        }

        .mob-section {
            padding: 15px 22px;
        }

        .mob-section+.mob-section {
            border-top: 1px solid var(--border);
        }

        .mob-sec-title {
            font-size: .61rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--text3);
            margin-bottom: 11px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .dgrid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 11px 20px;
        }

        .dg {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .dg.full {
            grid-column: 1/-1;
        }

        .dg-l {
            font-size: .61rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--text3);
        }

        .dg-v {
            font-size: .85rem;
            color: var(--text);
            font-weight: 500;
            word-break: break-word;
            line-height: 1.5;
        }

        .dg-v.muted {
            color: var(--text3);
            font-weight: 400;
        }

        .ban-notice {
            margin: 14px 22px;
            padding: 11px 14px;
            background: #fff5f5;
            border: 1px solid rgba(225, 29, 72, .2);
            border-radius: 10px;
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: .8rem;
            color: #9f1239;
        }

        .ban-notice i {
            font-size: .9rem;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .act-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .act-chip {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 6px 11px;
            font-size: .75rem;
            font-weight: 600;
            color: var(--text2);
        }

        .act-chip i {
            font-size: .7rem;
            color: var(--text3);
        }

        .act-chip strong {
            color: var(--text);
        }

        .mob-foot {
            padding: 13px 22px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 7px;
            justify-content: flex-end;
            flex-shrink: 0;
            background: var(--bg);
            flex-wrap: wrap;
        }

        @media(max-width:640px) {
            .stat-strip {
                grid-template-columns: repeat(2, 1fr);
            }

            .mob-metrics {
                flex-wrap: wrap;
            }

            .mob-met {
                min-width: 50%;
            }

            .dgrid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php include_once('sidebar.php'); ?>

    <!-- ══ TOPBAR ══ -->
    <?php include_once('adminheader.php'); ?>

    <div class="main">
        <div class="pg">

            <div class="pg-head">
                <div>
                    <h1>Students Management</h1>
                    <p>View, search, filter and manage all registered students</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="stat-strip">
                <div class="sc">
                    <div class="si b"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <div class="sv"><?php echo $ss['total']; ?></div>
                        <div class="sl">Total Students</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si g"><i class="fas fa-wifi"></i></div>
                    <div>
                        <div class="sv"><?php echo $ss['active']; ?></div>
                        <div class="sl">Active</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si r"><i class="fas fa-ban"></i></div>
                    <div>
                        <div class="sv"><?php echo $ss['inactive']; ?></div>
                        <div class="sl">Banned / Inactive</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si sk"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="sv"><?php echo $ss['verified']; ?></div>
                        <div class="sl">Verified</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si a"><i class="fas fa-circle-question"></i></div>
                    <div>
                        <div class="sv"><?php echo $ss['unverified']; ?></div>
                        <div class="sl">Unverified</div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="t-search"><i class="fas fa-search"></i><input type="text" id="searchQ" placeholder="Search name, email, username, course, institution…"></div>
                <select class="fsel" id="fCourse" onchange="go()">
                    <option value="">All Courses</option>
                    <?php
                    $courses = array_values(array_unique(array_filter(array_column($allStudents, 'course'))));
                    sort($courses);
                    foreach ($courses as $cr): ?>
                        <option value="<?php echo strtolower(htmlspecialchars($cr)); ?>"><?php echo htmlspecialchars($cr); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="fsel" id="fState" onchange="go()">
                    <option value="">All States</option>
                    <?php
                    $states = array_values(array_unique(array_filter(array_column($allStudents, 'state'))));
                    sort($states);
                    foreach ($states as $st): ?>
                        <option value="<?php echo strtolower(htmlspecialchars($st)); ?>"><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="fsel" id="fGender" onchange="go()">
                    <option value="">All Genders</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
                <div class="ftabs ml">
                    <button class="ft on" onclick="setSt('all',this)">All <span style="background:var(--bg);border-radius:99px;padding:1px 6px;font-size:.58rem;margin-left:2px"><?php echo $ss['total']; ?></span></button>
                    <button class="ft" onclick="setSt('active',this)">Active</button>
                    <button class="ft" onclick="setSt('inactive',this)">Banned</button>
                    <button class="ft" onclick="setSt('verified',this)">Verified</button>
                    <button class="ft" onclick="setSt('unverified',this)">Unverified</button>
                </div>
            </div>

            <!-- Bulk bar -->
            <div class="bulk-bar" id="bulkBar">
                <div class="bulk-info"><i class="fas fa-check-square"></i><span id="bCount">0</span> student(s) selected</div>
                <div class="bulk-acts">
                    <button class="btn btn-outline btn-sm" onclick="clearSel()"><i class="fas fa-xmark"></i> Clear</button>
                    <button class="btn btn-ok    btn-sm" onclick="bAction('activate')"><i class="fas fa-check"></i> Activate</button>
                    <button class="btn btn-warn  btn-sm" onclick="bAction('deactivate')"><i class="fas fa-ban"></i> Deactivate</button>
                    <button class="btn btn-sky   btn-sm" onclick="bAction('verify')"><i class="fas fa-badge-check"></i> Verify</button>
                    <button class="btn btn-ban   btn-sm" onclick="bAction('ban')"><i class="fas fa-gavel"></i> Ban</button>
                </div>
            </div>

            <!-- Table -->
            <div class="panel">
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selAll" onchange="tAll(this.checked)"></th>
                                <th>#</th>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Institution</th>
                                <th>State</th>
                                <th>Gender</th>
                                <th>Notes</th>
                                <th>Books</th>
                                <th>DL</th>
                                <th>Verified</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allStudents as $i => $s):
                                $fullName = trim($s['first_name'] . ' ' . $s['last_name']);
                                $sInit    = strtoupper(($s['first_name'][0] ?? '') . ($s['last_name'][0] ?? ''));
                                // DB stores: "admin/user_pages/uploads/profile_images/file.jpg"
                                // students.php lives at admin/students.php
                                // Strip leading "admin/" since we are already inside admin/
                                $pp = !empty($s["profile_image"])
                                    ? htmlspecialchars(preg_replace("#^admin/#", "", $s["profile_image"]))
                                    : "";
                                $pp = 'http://localhost/ScholarSwap/admin/' . $pp;
                                $isBanned = !$s['is_active'];
                                $sj = json_encode([
                                    'id'             => $s['user_id'],
                                    'name'           => $fullName,
                                    'username'       => $s['username']             ?? '',
                                    'email'          => $s['email']                ?? '',
                                    'phone'          => $s['phone']                ?? '',
                                    'gender'         => ucfirst($s['gender']       ?? ''),
                                    'dob'            => $s['dob']                  ?? '',
                                    'state'          => $s['state']                ?? '',
                                    'district'       => $s['district']             ?? '',
                                    'course'         => $s['course']               ?? '',
                                    'institution'    => $s['institution']          ?? '',
                                    'subjects'       => $s['subjects_of_interest'] ?? '',
                                    'bio'            => $s['bio']                  ?? '',
                                    'current_addr'   => $s['current_address']      ?? '',
                                    'permanent_addr' => $s['permanent_address']    ?? '',
                                    'verified'       => (bool)$s['is_verified'],
                                    'active'         => (bool)$s['is_active'],
                                    'joined'         => date('d M Y', strtotime($s['joined'])),
                                    'last_login'     => $s['last_login'] ? date('d M Y, H:i', strtotime($s['last_login'])) : 'Never',
                                    'notes'          => (int)$s['note_count'],
                                    'books'          => (int)$s['book_count'],
                                    'downloads'      => (int)$s['dl_count'],
                                    'avatar'         => $pp,
                                    'initials'       => $sInit,
                                ]);
                            ?>
                                <tr class="dr <?php echo $isBanned ? 'banned' : ''; ?>"
                                    data-active="<?php echo $s['is_active']   ? 'active'    : 'inactive'; ?>"
                                    data-verified="<?php echo $s['is_verified'] ? 'verified' : 'unverified'; ?>"
                                    data-course="<?php echo strtolower($s['course'] ?? ''); ?>"
                                    data-state="<?php echo strtolower($s['state']  ?? ''); ?>"
                                    data-gender="<?php echo strtolower($s['gender'] ?? ''); ?>"
                                    data-s="<?php echo strtolower(htmlspecialchars($fullName . ' ' . ($s['username'] ?? '') . ' ' . ($s['email'] ?? '') . ' ' . ($s['course'] ?? '') . ' ' . ($s['institution'] ?? '') . ' ' . ($s['state'] ?? '') . ' ' . ($s['district'] ?? ''))); ?>">
                                    <td class="cb-td"><input type="checkbox" class="rcb" data-id="<?php echo $s['user_id']; ?>" onchange="onCk()"></td>
                                    <td style="color:var(--text3);font-size:.7rem"><?php echo $i + 1; ?></td>
                                    <td>
                                        <div class="name-cell">
                                            <!-- Avatar with image + initials fallback -->
                                            <div class="av <?php echo $isBanned ? 'banned-av' : ''; ?>">
                                                <?php if ($pp): ?>
                                                    <img src="<?php echo $pp; ?>"
                                                        alt="<?php echo htmlspecialchars($sInit); ?>"
                                                        style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block"
                                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                                    <span class="av-init-fallback"><?php echo $sInit; ?></span>
                                                <?php else: ?>
                                                    <?php echo $sInit; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;color:var(--text)"><?php echo htmlspecialchars($fullName); ?></div>
                                                <div class="u-meta">@<?php echo htmlspecialchars($s['username'] ?? ''); ?> · <?php echo htmlspecialchars($s['email'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="bdg bdg-course"><?php echo htmlspecialchars($s['course'] ?? '—'); ?></span></td>
                                    <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.8rem"><?php echo htmlspecialchars($s['institution'] ?? '—'); ?></td>
                                    <td style="font-size:.8rem"><?php echo htmlspecialchars($s['state'] ?? '—'); ?></td>
                                    <td>
                                        <span class="bdg <?php echo match (strtolower($s['gender'] ?? '')) {
                                                                'male'   => 'bdg-gender-m',
                                                                'female' => 'bdg-gender-f',
                                                                default  => 'bdg-gender-o'
                                                            }; ?>"><?php echo ucfirst($s['gender'] ?? '—'); ?></span>
                                    </td>
                                    <td style="font-weight:700;text-align:center"><?php echo $s['note_count']; ?></td>
                                    <td style="font-weight:700;text-align:center"><?php echo $s['book_count']; ?></td>
                                    <td style="font-weight:700;text-align:center"><?php echo $s['dl_count']; ?></td>
                                    <td>
                                        <?php if ($s['is_verified']): ?>
                                            <span class="bdg bdg-verified"><i class="fas fa-check" style="font-size:.5rem"></i> Verified</span>
                                        <?php else: ?>
                                            <span class="bdg bdg-unverified"><i class="fas fa-clock" style="font-size:.5rem"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="bdg <?php echo $s['is_active'] ? 'bdg-active' : 'bdg-inactive'; ?>">
                                            <span class="dot <?php echo $s['is_active'] ? 'dot-on' : 'dot-ban'; ?>"></span>
                                            <?php echo $s['is_active'] ? 'Active' : 'Banned'; ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;color:var(--text3);font-size:.75rem"><?php echo date('M j, Y', strtotime($s['joined'])); ?></td>
                                    <td style="white-space:nowrap;color:var(--text3);font-size:.75rem"><?php echo $s['last_login'] ? date('M j', strtotime($s['last_login'])) : '—'; ?></td>
                                    <td style="white-space:nowrap">
                                        <button class="btn btn-view btn-sm" onclick='openMod(<?php echo htmlspecialchars($sj, ENT_QUOTES); ?>)'><i class="fas fa-eye"></i> View</button>
                                        <?php if ($s['is_active']): ?>
                                            <button class="btn btn-ban   btn-sm" title="Ban" onclick="doAction(<?php echo $s['user_id']; ?>,'ban','<?php echo addslashes($fullName); ?>')"><i class="fas fa-gavel"></i></button>
                                        <?php else: ?>
                                            <button class="btn btn-unban btn-sm" title="Activate" onclick="doAction(<?php echo $s['user_id']; ?>,'activate','<?php echo addslashes($fullName); ?>')"><i class="fas fa-circle-check"></i></button>
                                        <?php endif; ?>
                                        <?php if (!$s['is_verified']): ?>
                                            <button class="btn btn-sky  btn-sm" title="Verify" onclick="doAction(<?php echo $s['user_id']; ?>,'verify','<?php echo addslashes($fullName); ?>')"><i class="fas fa-badge-check"></i></button>
                                        <?php endif; ?>
                                        <button class="btn btn-warn btn-sm" title="Warn" onclick="openWarn(<?php echo $s['user_id']; ?>,'<?php echo addslashes($fullName); ?>')"><i class="fas fa-triangle-exclamation"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="empty" id="emptyMsg" style="display:none">
                    <i class="fas fa-user-slash"></i>
                    <strong>No students found</strong>
                    Try adjusting your filters or search term.
                </div>
                <div class="pgbar">
                    <div class="pgi" id="pgi"></div>
                    <div class="pgbtns" id="pgbtns"></div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══════ VIEW MODAL ═══════ -->
    <div class="mov" id="vModal">
        <div class="mob">
            <div class="mob-accent"></div>
            <div class="mob-profile">
                <div class="mob-av-lg" id="mAvatar">?</div>
                <div class="mob-pinfo">
                    <h3 id="mName">Student</h3>
                    <p id="mMeta">—</p>
                    <div class="mob-badges" id="mBadges"></div>
                </div>
                <button class="mob-x" onclick="closeMod()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="mob-metrics">
                <div class="mob-met">
                    <div class="mob-met-v" id="mNotes">0</div>
                    <div class="mob-met-l">Notes</div>
                </div>
                <div class="mob-met">
                    <div class="mob-met-v" id="mBooks">0</div>
                    <div class="mob-met-l">Books</div>
                </div>
                <div class="mob-met">
                    <div class="mob-met-v" id="mDL">0</div>
                    <div class="mob-met-l">Downloads</div>
                </div>
                <div class="mob-met">
                    <div class="mob-met-v" id="mJoined">—</div>
                    <div class="mob-met-l">Joined</div>
                </div>
            </div>
            <div class="mob-body">
                <div class="ban-notice" id="mBanNotice" style="display:none">
                    <i class="fas fa-gavel"></i>
                    <div><strong>This account is currently banned.</strong><br>The student cannot log in or access the platform.</div>
                </div>
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-user"></i> Personal Information</div>
                    <div class="dgrid">
                        <div class="dg">
                            <div class="dg-l">Username</div>
                            <div class="dg-v" id="mUsername">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">Email</div>
                            <div class="dg-v" id="mEmail">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">Phone</div>
                            <div class="dg-v" id="mPhone">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">Gender</div>
                            <div class="dg-v" id="mGender">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">Date of Birth</div>
                            <div class="dg-v" id="mDob">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">State / District</div>
                            <div class="dg-v" id="mLocation">—</div>
                        </div>
                    </div>
                </div>
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-graduation-cap"></i> Academic Information</div>
                    <div class="dgrid">
                        <div class="dg">
                            <div class="dg-l">Course</div>
                            <div class="dg-v" id="mCourse">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">Institution</div>
                            <div class="dg-v" id="mInstitution">—</div>
                        </div>
                        <div class="dg full">
                            <div class="dg-l">Subjects of Interest</div>
                            <div class="dg-v" id="mSubjects">—</div>
                        </div>
                    </div>
                </div>
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-align-left"></i> Bio</div>
                    <div class="dg-v" id="mBio" style="font-size:.84rem;line-height:1.6;color:var(--text2)">—</div>
                </div>
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-location-dot"></i> Address</div>
                    <div class="dgrid">
                        <div class="dg full">
                            <div class="dg-l">Current Address</div>
                            <div class="dg-v" id="mCurrAddr">—</div>
                        </div>
                        <div class="dg full">
                            <div class="dg-l">Permanent Address</div>
                            <div class="dg-v" id="mPermAddr">—</div>
                        </div>
                    </div>
                </div>
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-clock-rotate-left"></i> Activity</div>
                    <div class="act-row" id="mActivity"></div>
                </div>
            </div>
            <div class="mob-foot">
                <button class="btn btn-outline" onclick="closeMod()">Close</button>
                <a style="display: none;" class="btn btn-sky btn-sm" id="mProfileLink" href="#" target="_blank"><i class="fas fa-arrow-up-right-from-square"></i> Full Profile</a>
                <button class="btn btn-sky  btn-sm" id="mVerifyBtn" onclick="mAction('verify')" style="display:none"><i class="fas fa-badge-check"></i> Verify</button>
                <button class="btn btn-ok   btn-sm" id="mActivateBtn" onclick="mAction('activate')" style="display:none"><i class="fas fa-circle-check"></i> Activate</button>
                <button class="btn btn-warn btn-sm" onclick="mWarn()"><i class="fas fa-triangle-exclamation"></i> Warn</button>
                <button class="btn btn-ban  btn-sm" id="mBanBtn" onclick="mAction('ban')" style="display:none"><i class="fas fa-gavel"></i> Ban</button>
            </div>
        </div>
    </div>

    <!-- ═══════ WARN MODAL ═══════ -->
    <div class="mov" id="warnMod">
        <div class="mob" style="max-width:460px">
            <div class="mob-accent" style="background:linear-gradient(90deg,#d97706,#f59e0b,#fbbf24)"></div>
            <div class="mob-profile" style="background:linear-gradient(135deg,#fffbeb,#fef3c7)">
                <div class="mob-av-lg" style="background:linear-gradient(135deg,#d97706,#f59e0b);box-shadow:0 4px 14px rgba(217,119,6,.3)">
                    <i class="fas fa-triangle-exclamation" style="font-size:1.3rem"></i>
                </div>
                <div class="mob-pinfo">
                    <h3>Send Warning</h3>
                    <p>To: <strong id="warnName">—</strong></p>
                </div>
                <button class="mob-x" onclick="closeWarn()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="mob-body" style="padding:20px 22px;display:flex;flex-direction:column;gap:12px;">
                <div style="background:var(--amber-s);border:1px solid rgba(217,119,6,.2);border-radius:10px;padding:11px 14px;font-size:.8rem;color:#92400e;display:flex;gap:8px;align-items:flex-start;">
                    <i class="fas fa-circle-info" style="margin-top:1px;flex-shrink:0"></i>
                    <span>This warning will be logged and associated with the student's account for admin records.</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text3)">Warning Message *</label>
                    <textarea id="warnMsg" rows="5"
                        placeholder="Describe the issue or violation clearly…"
                        style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:.85rem;color:var(--text);background:var(--surface);outline:none;resize:vertical;line-height:1.5;transition:border-color .15s"
                        onfocus="this.style.borderColor='var(--amber)'"
                        onblur="this.style.borderColor='var(--border)'"></textarea>
                    <div style="font-size:.72rem;color:var(--text3)">Be specific about what policy was violated and what the user should change.</div>
                </div>
            </div>
            <div class="mob-foot">
                <button class="btn btn-outline" onclick="closeWarn()">Cancel</button>
                <button class="btn" id="warnSendBtn" onclick="sendWarn()"
                    style="background:var(--amber);color:#fff;box-shadow:0 2px 8px rgba(217,119,6,.25)">
                    <i class="fas fa-paper-plane"></i> Send Warning
                </button>
            </div>
        </div>
    </div>

    <script>
        let curSt = 'all',
            curMod = null;
        const PER = 25;
        let pg = 1;

        /* ── Filter / search ── */
        function go() {
            const q = document.getElementById('searchQ').value.toLowerCase().trim();
            const cr = document.getElementById('fCourse').value;
            const st = document.getElementById('fState').value;
            const gd = document.getElementById('fGender').value;
            const all = [...document.querySelectorAll('.dr')];
            const vis = all.filter(r => {
                const ok = curSt === 'all' ||
                    (curSt === 'active' && r.dataset.active === 'active') ||
                    (curSt === 'inactive' && r.dataset.active === 'inactive') ||
                    (curSt === 'verified' && r.dataset.verified === 'verified') ||
                    (curSt === 'unverified' && r.dataset.verified === 'unverified');
                return ok &&
                    (!q || r.dataset.s.includes(q)) &&
                    (!cr || r.dataset.course === cr) &&
                    (!st || r.dataset.state === st) &&
                    (!gd || r.dataset.gender === gd);
            });
            const pages = Math.max(1, Math.ceil(vis.length / PER));
            if (pg > pages) pg = pages;
            all.forEach(r => r.style.display = 'none');
            vis.forEach((r, i) => r.style.display = (i >= (pg - 1) * PER && i < pg * PER) ? '' : 'none');
            document.getElementById('emptyMsg').style.display = vis.length === 0 ? 'block' : 'none';
            renderPg(vis.length, pages);
            clearSel();
        }

        function setSt(st, btn) {
            curSt = st;
            pg = 1;
            document.querySelectorAll('.ft').forEach(b => b.classList.remove('on'));
            btn.classList.add('on');
            go();
        }

        document.getElementById('searchQ').addEventListener('input', () => {
            pg = 1;
            go();
        });
        window.addEventListener('DOMContentLoaded', go);

        /* ── Pagination ── */
        function renderPg(total, pages) {
            document.getElementById('pgi').textContent = total === 0 ?
                'No results' :
                `Showing ${Math.min((pg-1)*PER+1,total)}–${Math.min(pg*PER,total)} of ${total}`;
            const c = document.getElementById('pgbtns');
            c.innerHTML = '';
            if (pages <= 1) return;
            const mk = (html, p, dis = false, act = false) => {
                const b = document.createElement('button');
                b.className = 'pgb' + (act ? ' on' : '');
                b.innerHTML = html;
                b.disabled = dis;
                b.onclick = () => {
                    pg = p;
                    go();
                };
                c.appendChild(b);
            };
            mk('<i class="fas fa-chevron-left"></i>', pg - 1, pg === 1);
            const s = Math.max(1, pg - 2),
                e = Math.min(pages, pg + 2);
            if (s > 1) {
                mk('1', 1);
                if (s > 2) c.insertAdjacentHTML('beforeend', '<span class="pg-ellipsis">…</span>');
            }
            for (let p = s; p <= e; p++) mk(p, p, false, p === pg);
            if (e < pages) {
                if (e < pages - 1) c.insertAdjacentHTML('beforeend', '<span class="pg-ellipsis">…</span>');
                mk(pages, pages);
            }
            mk('<i class="fas fa-chevron-right"></i>', pg + 1, pg === pages);
        }

        /* ── Checkbox selection ── */
        function tAll(v) {
            document.querySelectorAll('.dr').forEach(r => {
                if (r.style.display !== 'none') {
                    const cb = r.querySelector('.rcb');
                    cb.checked = v;
                    r.classList.toggle('sel', v);
                }
            });
            onCk();
        }

        function onCk() {
            const n = document.querySelectorAll('.rcb:checked').length;
            document.getElementById('bCount').textContent = n;
            document.getElementById('bulkBar').classList.toggle('vis', n > 0);
            document.querySelectorAll('.rcb').forEach(cb => cb.closest('tr').classList.toggle('sel', cb.checked));
        }

        function clearSel() {
            document.querySelectorAll('.rcb').forEach(cb => {
                cb.checked = false;
                cb.closest('tr').classList.remove('sel');
            });
            const sa = document.getElementById('selAll');
            if (sa) sa.checked = false;
            document.getElementById('bulkBar').classList.remove('vis');
            document.getElementById('bCount').textContent = '0';
        }

        /* ── API helper ── */
        const post = (url, d) => fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(d)
        }).then(r => r.json());

        /* ── Action meta ── */
        const ACTION_META = {
            activate: {
                title: 'Activate this student?',
                icon: 'question',
                btnTxt: '<i class="fas fa-circle-check"></i> Activate',
                color: '#059669'
            },
            deactivate: {
                title: 'Deactivate this student?',
                icon: 'warning',
                btnTxt: '<i class="fas fa-ban"></i> Deactivate',
                color: '#d97706'
            },
            verify: {
                title: 'Verify this student?',
                icon: 'question',
                btnTxt: '<i class="fas fa-badge-check"></i> Verify',
                color: '#0284c7'
            },
            ban: {
                title: 'Ban this student?',
                icon: 'warning',
                btnTxt: '<i class="fas fa-gavel"></i> Ban',
                color: '#e11d48'
            },
        };

        function doAction(id, action, name) {
            const m = ACTION_META[action];
            Swal.fire({
                title: m.title,
                html: `<span style="color:#64748b;font-size:.9rem"><strong style="color:#0f172a">${name}</strong> will be ${action}${action==='ban'?'ned':action==='verify'?'ied':'d'}.</span>`,
                icon: m.icon,
                showCancelButton: true,
                confirmButtonText: m.btnTxt,
                cancelButtonText: 'Cancel',
                confirmButtonColor: m.color,
                cancelButtonColor: '#e2e8f0'
            }).then(r => {
                if (!r.isConfirmed) return;
                post('auth/user_action.php', {
                    id,
                    action
                }).then(d => {
                    d.success ?
                        Swal.fire({
                            icon: 'success',
                            title: 'Done!',
                            text: d.message,
                            timer: 1800,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload()) :
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: d.message
                        });
                }).catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Network error.'
                }));
            });
        }

        function bAction(action) {
            const ids = [...document.querySelectorAll('.rcb:checked')].map(cb => parseInt(cb.dataset.id));
            if (!ids.length) return;
            const m = ACTION_META[action] || {
                title: `${action} selected?`,
                icon: 'question',
                btnTxt: 'Confirm',
                color: '#2563eb'
            };
            Swal.fire({
                title: m.title.replace('this student?', ids.length + ' student(s)?'),
                icon: m.icon,
                showCancelButton: true,
                confirmButtonText: m.btnTxt,
                cancelButtonText: 'Cancel',
                confirmButtonColor: m.color,
                cancelButtonColor: '#e2e8f0'
            }).then(r => {
                if (!r.isConfirmed) return;
                Swal.fire({
                    title: 'Processing…',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                post('auth/bulk_user_action.php', {
                    ids,
                    action
                }).then(d => {
                    d.success ?
                        Swal.fire({
                            icon: 'success',
                            title: 'Done!',
                            text: d.message,
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload()) :
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: d.message
                        });
                }).catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Network error.'
                }));
            });
        }

        /* ── View modal ── */
        function sv(id, val) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = val || '—';
            el.className = 'dg-v' + ((!val || val === '—') ? ' muted' : '');
        }

        function openMod(s) {
            curMod = s;

            // ── Avatar — show image with initials fallback ──
            const av = document.getElementById('mAvatar');
            av.className = 'mob-av-lg' + (s.active ? '' : ' banned');
            av.innerHTML = '';

            if (s.avatar) {
                const img = document.createElement('img');
                img.src = s.avatar;
                img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:12px';
                img.onerror = () => {
                    img.remove();
                    av.textContent = s.initials || '?';
                };
                av.appendChild(img);
            } else {
                av.textContent = s.initials || '?';
            }

            document.getElementById('mName').textContent = s.name;
            document.getElementById('mMeta').textContent = `@${s.username}  ·  ${s.email}`;

            // Badges
            const stBdg = s.active ?
                '<span class="bdg bdg-active"><span class="dot dot-on"></span>Active</span>' :
                '<span class="bdg bdg-inactive"><span class="dot dot-ban"></span>Banned</span>';
            const vrBdg = s.verified ?
                '<span class="bdg bdg-verified"><i class="fas fa-check" style="font-size:.5rem"></i> Verified</span>' :
                '<span class="bdg bdg-unverified"><i class="fas fa-clock" style="font-size:.5rem"></i> Unverified</span>';
            const gdCls = s.gender.toLowerCase().startsWith('m') ? 'bdg-gender-m' :
                s.gender.toLowerCase().startsWith('f') ? 'bdg-gender-f' :
                'bdg-gender-o';
            document.getElementById('mBadges').innerHTML = stBdg + vrBdg + (s.gender ? `<span class="bdg ${gdCls}">${s.gender}</span>` : '');

            // Metrics
            document.getElementById('mNotes').textContent = s.notes;
            document.getElementById('mBooks').textContent = s.books;
            document.getElementById('mDL').textContent = s.downloads;
            document.getElementById('mJoined').textContent = s.joined;

            document.getElementById('mBanNotice').style.display = s.active ? 'none' : 'flex';

            sv('mUsername', '@' + s.username);
            sv('mEmail', s.email);
            sv('mPhone', s.phone);
            sv('mGender', s.gender);
            sv('mDob', s.dob ? new Date(s.dob).toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }) : '—');
            sv('mLocation', [s.state, s.district].filter(Boolean).join(', ') || '—');
            sv('mCourse', s.course);
            sv('mInstitution', s.institution);
            sv('mSubjects', s.subjects);

            const bioEl = document.getElementById('mBio');
            bioEl.textContent = s.bio || 'No bio provided.';
            bioEl.className = 'dg-v' + (s.bio ? '' : ' muted');

            sv('mCurrAddr', s.current_addr);
            sv('mPermAddr', s.permanent_addr);

            document.getElementById('mActivity').innerHTML = `
                <div class="act-chip"><i class="fas fa-clock"></i>Last login: <strong>${s.last_login}</strong></div>
                <div class="act-chip"><i class="fas fa-calendar"></i>Joined: <strong>${s.joined}</strong></div>
                <div class="act-chip"><i class="fas fa-file-lines"></i>Notes: <strong>${s.notes}</strong></div>
                <div class="act-chip"><i class="fas fa-book"></i>Books: <strong>${s.books}</strong></div>
                <div class="act-chip"><i class="fas fa-download"></i>Downloads: <strong>${s.downloads}</strong></div>`;

            document.getElementById('mProfileLink').href = `../profile.php?u=${s.id}`;
            document.getElementById('mVerifyBtn').style.display = !s.verified ? '' : 'none';
            document.getElementById('mActivateBtn').style.display = !s.active ? '' : 'none';
            document.getElementById('mBanBtn').style.display = s.active ? '' : 'none';

            document.getElementById('vModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeMod() {
            document.getElementById('vModal').classList.remove('show');
            document.body.style.overflow = '';
            curMod = null;
        }

        function mAction(action) {
            if (!curMod) return;
            closeMod();
            doAction(curMod.id, action, curMod.name);
        }

        document.getElementById('vModal').addEventListener('click', e => {
            if (e.target === document.getElementById('vModal')) closeMod();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeMod();
                closeWarn();
            }
        });

        // URL feedback
        const _s = new URLSearchParams(location.search).get('s');
        if (_s === 'success') Swal.fire({
            icon: 'success',
            title: 'Done!',
            timer: 1800,
            timerProgressBar: true,
            showConfirmButton: false
        });
        else if (_s === 'failed') Swal.fire({
            icon: 'error',
            title: 'Failed',
            timer: 1800,
            showConfirmButton: false
        });
        if (_s) history.replaceState(null, '', 'students.php');

        /* ── Warn modal ── */
        let warnTarget = {
            id: 0,
            name: ''
        };

        function openWarn(id, name) {
            warnTarget = {
                id,
                name
            };
            closeMod();
            document.getElementById('warnName').textContent = name;
            document.getElementById('warnMsg').value = '';
            document.getElementById('warnMod').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function mWarn() {
            if (!curMod) return;
            openWarn(curMod.id, curMod.name);
        }

        function closeWarn() {
            document.getElementById('warnMod').classList.remove('show');
            document.body.style.overflow = '';
        }

        function sendWarn() {
            const msg = document.getElementById('warnMsg').value.trim();
            if (!msg) {
                document.getElementById('warnMsg').style.borderColor = 'var(--rose)';
                return;
            }
            document.getElementById('warnMsg').style.borderColor = '';
            const btn = document.getElementById('warnSendBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
            post('auth/user_action.php', {
                    id: warnTarget.id,
                    action: 'warn',
                    message: msg
                })
                .then(d => {
                    closeWarn();
                    d.success ?
                        Swal.fire({
                            icon: 'success',
                            title: 'Warning Sent!',
                            text: d.message,
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }) :
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: d.message
                        });
                })
                .catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Network error.'
                }))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Warning';
                });
        }

        document.getElementById('warnMod').addEventListener('click', e => {
            if (e.target === document.getElementById('warnMod')) closeWarn();
        });
    </script>
</body>

</html>