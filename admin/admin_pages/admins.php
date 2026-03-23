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

$c = ['users' => q($conn, "SELECT COUNT(*) FROM users"), 'students' => q($conn, "SELECT COUNT(*) FROM students"), 'tutors' => q($conn, "SELECT COUNT(*) FROM tutors"), 'admins' => q($conn, "SELECT COUNT(*) FROM admin_user"), 'notes' => q($conn, "SELECT COUNT(*) FROM notes"), 'books' => q($conn, "SELECT COUNT(*) FROM books"), 'papers' => q($conn, "SELECT COUNT(*) FROM newspapers"), 'n_pending' => q($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='pending'"), 'b_pending' => q($conn, "SELECT COUNT(*) FROM books WHERE approval_status='pending'"), 'p_pending' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'")];
$c['pending'] = $c['n_pending'] + $c['b_pending'] + $c['p_pending'];

$selfId = (int)$_SESSION['admin_id'];
$sa = $conn->prepare("SELECT * FROM admin_user WHERE admin_id=? LIMIT 1");
$sa->execute([$selfId]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);
$isSuperAdmin = strtolower($admin['role'] ?? '') === 'superadmin';

// ── All admins ──
$aq = $conn->prepare("SELECT * FROM admin_user ORDER BY created_at DESC");
$aq->execute();
$allAdmins = $aq->fetchAll(PDO::FETCH_ASSOC);

$as = [
    'total'      => count($allAdmins),
    'approved'   => q($conn, "SELECT COUNT(*) FROM admin_user WHERE status='approved'"),
    'pending'    => q($conn, "SELECT COUNT(*) FROM admin_user WHERE status='pending'"),
    'rejected'   => q($conn, "SELECT COUNT(*) FROM admin_user WHERE status='rejected'"),
    'superadmin' => q($conn, "SELECT COUNT(*) FROM admin_user WHERE role='superadmin'"),
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admins | ScholarSwap Admin</title>
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

        /* Page head */
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

        .si.p {
            background: var(--purple-s);
            color: var(--purple);
        }

        .si.i {
            background: var(--indigo-s);
            color: var(--indigo);
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
            border-color: var(--indigo);
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
            border-color: var(--indigo);
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
            border-color: var(--indigo);
            color: var(--indigo);
        }

        .ft.on {
            background: var(--indigo);
            color: #fff;
            border-color: var(--indigo);
        }

        /* Bulk bar */
        .bulk-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            background: var(--indigo-s);
            border: 1.5px solid rgba(79, 70, 229, .22);
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
            color: var(--indigo);
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
            background: var(--indigo-s);
            color: var(--indigo);
        }

        .btn-view:hover {
            background: var(--indigo);
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

        .btn-no {
            background: var(--red-s);
            color: var(--red);
        }

        .btn-no:hover {
            background: var(--red);
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

        .btn-purple {
            background: var(--purple-s);
            color: var(--purple);
        }

        .btn-purple:hover {
            background: var(--purple);
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

        .btn-lock {
            background: #1e293b;
            color: #f1f5f9;
        }

        .btn-lock:hover {
            background: #0f172a;
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
            background: #eff0ff;
        }

        tbody tr.self-row td {
            background: #f5f3ff;
        }

        tbody tr.self-row:hover td {
            background: #ede9fe;
        }

        tbody tr.rejected-row td {
            background: #fff8f8;
        }

        .cb-td {
            text-align: center;
        }

        input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--indigo);
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

        .bdg-approved {
            background: var(--green-s);
            color: #065f46;
        }

        .bdg-pending {
            background: var(--amber-s);
            color: #92400e;
        }

        .bdg-rejected {
            background: var(--red-s);
            color: #991b1b;
        }

        .bdg-super {
            background: var(--indigo-s);
            color: var(--indigo);
        }

        .bdg-admin {
            background: var(--purple-s);
            color: var(--purple);
        }

        .bdg-self {
            background: #fef9c3;
            color: #854d0e;
        }

        .bdg-you {
            background: var(--teal-s);
            color: var(--teal);
        }

        /* Avatar */
        .av {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
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

        /* Dot status */
        .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-on {
            background: #059669;
        }

        .dot-pend {
            background: #d97706;
        }

        .dot-rej {
            background: #dc2626;
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
            border-color: var(--indigo);
            color: var(--indigo);
        }

        .pgb.on {
            background: var(--indigo);
            color: #fff;
            border-color: var(--indigo);
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

        /* Empty */
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

        /* ═══ MODAL base ═══ */
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
            width: min(720px, 96vw);
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
            background: linear-gradient(90deg, #4f46e5, #7c3aed, #0d9488);
        }

        .mob-profile {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #f5f3ff, #ede9fe);
        }

        .mob-av-lg {
            width: 62px;
            height: 62px;
            border-radius: 16px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(79, 70, 229, .3);
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

        /* Metrics bar */
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
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .mob-met-l {
            font-size: .62rem;
            color: var(--text3);
            margin-top: 3px;
        }

        /* Body */
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

        /* Activity chips */
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

        /* ── Inline role switcher (table cell) ── */
        .role-cell {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: .64rem;
            font-weight: 700;
            white-space: nowrap;
            border: 1.5px solid transparent;
            transition: all .14s;
        }

        .role-pill.super {
            background: var(--purple-s);
            color: var(--purple);
            border-color: rgba(124, 58, 237, .2);
        }

        .role-pill.admin {
            background: var(--indigo-s);
            color: var(--indigo);
            border-color: rgba(79, 70, 229, .2);
        }

        /* Inline change-role button (superadmin page-level action) */
        .role-change-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 7px;
            font-size: .64rem;
            font-weight: 700;
            border: 1.5px solid;
            cursor: pointer;
            transition: all .14s;
            background: transparent;
            white-space: nowrap;
        }

        .role-change-btn.promote {
            color: var(--purple);
            border-color: rgba(124, 58, 237, .3);
            background: var(--purple-s);
        }

        .role-change-btn.promote:hover {
            background: var(--purple);
            color: #fff;
            border-color: var(--purple);
        }

        .role-change-btn.demote {
            color: var(--text3);
            border-color: var(--border);
            background: var(--bg);
        }

        .role-change-btn.demote:hover {
            background: #1e293b;
            color: #f1f5f9;
            border-color: #1e293b;
        }

        /* Modal role change strip */
        .role-strip {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .role-opt {
            display: none;
        }

        .role-lbl {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            border-radius: 9px;
            border: 1.5px solid var(--border);
            font-size: .8rem;
            font-weight: 600;
            color: var(--text2);
            cursor: pointer;
            transition: all .14s;
            background: var(--surface);
        }

        .role-lbl:hover {
            border-color: var(--indigo);
            color: var(--indigo);
        }

        .role-opt:checked+.role-lbl {
            background: var(--indigo);
            color: #fff;
            border-color: var(--indigo);
        }

        .role-opt[value="superadmin"]:checked+.role-lbl {
            background: var(--purple);
            border-color: var(--purple);
        }

        .role-save-btn {
            padding: 7px 14px;
            border-radius: 9px;
            background: var(--green);
            color: #fff;
            font-size: .78rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: none;
            transition: all .14s;
        }

        .role-save-btn.show {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .role-save-btn:hover {
            background: #047857;
        }

        /* Footer */
        .mob-foot {
            padding: 13px 22px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 7px;
            justify-content: flex-end;
            flex-shrink: 0;
            background: var(--bg);
            flex-wrap: wrap;
            align-items: center;
        }

        /* Warn modal specific size */
        .mob-sm {
            max-width: 460px;
        }

        /* Rejected notice */
        .rej-notice {
            margin: 14px 22px;
            padding: 11px 14px;
            background: #fff8f8;
            border: 1px solid rgba(220, 38, 38, .2);
            border-radius: 10px;
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: .8rem;
            color: #991b1b;
        }

        .rej-notice i {
            font-size: .9rem;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .pend-notice {
            margin: 14px 22px;
            padding: 11px 14px;
            background: var(--amber-s);
            border: 1px solid rgba(217, 119, 6, .2);
            border-radius: 10px;
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: .8rem;
            color: #92400e;
        }

        .pend-notice i {
            font-size: .9rem;
            margin-top: 1px;
            flex-shrink: 0;
        }

        @media(max-width:880px) {
            .dgrid {
                grid-template-columns: 1fr;
            }
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
        }

        /* SweetAlert role popup width */
        .swal-role-popup.swal2-popup {
            max-width: 420px !important;
        }
    </style>
</head>

<body>

    <?php include_once('sidebar.php'); ?>

    <?php include_once('adminheader.php'); ?>

    <div class="main">
        <div class="pg">

            <div class="pg-head">
                <div>
                    <h1>Admin Accounts</h1>
                    <p>Manage admin registrations, approvals, roles and activity</p>
                </div>
                <button class="btn btn-view btn-sm" onclick=" window.location.href='admin_signup' "><i class="fas fa-plus"></i> Register New User</button>
            </div>

            <!-- Stats -->
            <div class="stat-strip">
                <div class="sc">
                    <div class="si b"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <div class="sv"><?php echo $as['total']; ?></div>
                        <div class="sl">Total Admins</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si g"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="sv"><?php echo $as['approved']; ?></div>
                        <div class="sl">Approved</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si a"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="sv"><?php echo $as['pending']; ?></div>
                        <div class="sl">Pending</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si r"><i class="fas fa-times-circle"></i></div>
                    <div>
                        <div class="sv"><?php echo $as['rejected']; ?></div>
                        <div class="sl">Rejected</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si p"><i class="fas fa-crown"></i></div>
                    <div>
                        <div class="sv"><?php echo $as['superadmin']; ?></div>
                        <div class="sl">Super Admins</div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="t-search"><i class="fas fa-search"></i><input type="text" id="searchQ" placeholder="Search name, email, username, institution…"></div>
                <select class="fsel" id="fRole" onchange="go()">
                    <option value="">All Roles</option>
                    <option value="superadmin">Super Admin</option>
                    <option value="admin">Admin</option>
                </select>
                <div class="ftabs ml">
                    <button class="ft on" onclick="setSt('all',this)">All <span style="background:var(--bg);border-radius:99px;padding:1px 6px;font-size:.58rem;margin-left:2px"><?php echo $as['total']; ?></span></button>
                    <button class="ft" onclick="setSt('approved',this)">Approved</button>
                    <button class="ft" onclick="setSt('pending',this)">Pending <?php if ($as['pending'] > 0): ?><span style="background:var(--amber);color:#fff;border-radius:99px;padding:1px 6px;font-size:.55rem;margin-left:2px"><?php echo $as['pending']; ?></span><?php endif; ?></button>
                    <button class="ft" onclick="setSt('rejected',this)">Rejected</button>
                </div>
            </div>

            <!-- Bulk bar -->
            <div class="bulk-bar" id="bulkBar">
                <div class="bulk-info"><i class="fas fa-check-square"></i><span id="bCount">0</span> admin(s) selected</div>
                <div class="bulk-acts">
                    <button class="btn btn-outline btn-sm" onclick="clearSel()"><i class="fas fa-xmark"></i> Clear</button>
                    <button class="btn btn-ok   btn-sm" onclick="bAction('approve')"><i class="fas fa-check"></i> Approve</button>
                    <button class="btn btn-no   btn-sm" onclick="bAction('reject')"><i class="fas fa-times"></i> Reject</button>
                    <button class="btn btn-warn btn-sm" onclick="bAction('warn')"><i class="fas fa-triangle-exclamation"></i> Warn</button>
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
                                <th>Admin</th>
                                <th>Role</th>
                                <th>Institution</th>
                                <th>State</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allAdmins as $i => $a):
                                $fullName = trim($a['first_name'] . ' ' . $a['last_name']);
                                $initials  = strtoupper(($a['first_name'][0] ?? '') . ($a['last_name'][0] ?? ''));
                                $isSelf    = ($a['admin_id'] == $selfId);
                                $isSuper   = strtolower($a['role'] ?? '') === 'superadmin';
                                $aj = json_encode([
                                    'id'            => $a['admin_id'],
                                    'name'          => $fullName,
                                    'username'      => $a['username'] ?? '',
                                    'email'         => $a['email'] ?? '',
                                    'phone'         => $a['phone'] ?? '',
                                    'role'          => $a['role'] ?? 'admin',
                                    'gender'        => ucfirst($a['gender'] ?? ''),
                                    'dob'           => $a['dob'] ?? '',
                                    'state'         => $a['state'] ?? '',
                                    'district'      => $a['district'] ?? '',
                                    'course'        => $a['course'] ?? '',
                                    'institution'   => $a['institution'] ?? '',
                                    'subjects'      => $a['subjects'] ?? '',
                                    'bio'           => $a['bio'] ?? '',
                                    'current_addr'  => $a['current_address'] ?? '',
                                    'permanent_addr' => $a['permanent_address'] ?? '',
                                    'status'        => $a['status'] ?? 'pending',
                                    'registered'    => date('d M Y', strtotime($a['created_at'])),
                                    'initials'      => $initials,
                                    'is_self'       => $isSelf,
                                    'is_super'      => $isSuper,
                                ]);
                            ?>
                                <tr class="dr <?php echo $isSelf ? 'self-row' : ''; ?> <?php echo $a['status'] === 'rejected' ? 'rejected-row' : ''; ?>"
                                    data-st="<?php echo $a['status']; ?>"
                                    data-role="<?php echo strtolower($a['role'] ?? 'admin'); ?>"
                                    data-s="<?php echo strtolower(htmlspecialchars($fullName . ' ' . ($a['username'] ?? '') . ' ' . ($a['email'] ?? '') . ' ' . ($a['institution'] ?? '') . ' ' . ($a['state'] ?? '') . ' ' . ($a['district'] ?? ''))); ?>">
                                    <td class="cb-td">
                                        <?php if (!$isSelf): ?>
                                            <input type="checkbox" class="rcb" data-id="<?php echo $a['admin_id']; ?>" onchange="onCk()">
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:var(--text3);font-size:.7rem"><?php echo $i + 1; ?></td>
                                    <td>
                                        <div class="name-cell">
                                            <div class="av"><?php echo $initials; ?></div>
                                            <div>
                                                <div style="font-weight:600;color:var(--text);display:flex;align-items:center;gap:5px">
                                                    <?php echo htmlspecialchars($fullName); ?>
                                                    <?php if ($isSelf): ?><span class="bdg bdg-you" style="font-size:.55rem">You</span><?php endif; ?>
                                                </div>
                                                <div class="u-meta">@<?php echo htmlspecialchars($a['username'] ?? ''); ?> · <?php echo htmlspecialchars($a['email'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="role-cell">
                                            <?php if ($isSuper): ?>
                                                <span class="role-pill super"><i class="fas fa-crown" style="font-size:.52rem"></i> Super Admin</span>
                                            <?php else: ?>
                                                <span class="role-pill admin"><i class="fas fa-user-shield" style="font-size:.52rem"></i> Admin</span>
                                            <?php endif; ?>
                                            <?php if ($isSuperAdmin && !$isSelf): ?>
                                                <?php if ($isSuper): ?>
                                                    <button class="role-change-btn demote" title="Demote to Admin"
                                                        onclick="changeRole(<?php echo $a['admin_id']; ?>,'admin','<?php echo addslashes($fullName); ?>')">
                                                        <i class="fas fa-arrow-down"></i> Demote
                                                    </button>
                                                <?php else: ?>
                                                    <button class="role-change-btn promote" title="Promote to Super Admin"
                                                        onclick="changeRole(<?php echo $a['admin_id']; ?>,'superadmin','<?php echo addslashes($fullName); ?>')">
                                                        <i class="fas fa-crown"></i> Promote
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.8rem"><?php echo htmlspecialchars($a['institution'] ?? '—'); ?></td>
                                    <td style="font-size:.8rem"><?php echo htmlspecialchars($a['state'] ?? '—'); ?></td>
                                    <td style="font-size:.8rem"><?php echo htmlspecialchars($a['phone'] ?? '—'); ?></td>
                                    <td>
                                        <?php
                                        $dotCls = match ($a['status'] ?? 'pending') {
                                            'approved' => 'dot-on',
                                            'pending' => 'dot-pend',
                                            default => 'dot-rej'
                                        };
                                        $bdgCls = 'bdg-' . ($a['status'] ?? 'pending');
                                        ?>
                                        <span class="bdg <?php echo $bdgCls; ?>">
                                            <span class="dot <?php echo $dotCls; ?>"></span>
                                            <?php echo ucfirst($a['status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;color:var(--text3);font-size:.75rem"><?php echo date('M j, Y', strtotime($a['created_at'])); ?></td>
                                    <td style="white-space:nowrap">
                                        <!-- View -->
                                        <button class="btn btn-view btn-sm" onclick='openMod(<?php echo htmlspecialchars($aj, ENT_QUOTES); ?>)'><i class="fas fa-eye"></i> View</button>

                                        <?php if (!$isSelf): ?>
                                            <!-- Approve / Reject (for pending) -->
                                            <?php if ($a['status'] === 'pending'): ?>
                                                <button class="btn btn-ok btn-sm" title="Approve" onclick="doAction(<?php echo $a['admin_id']; ?>,'approve','<?php echo addslashes($fullName); ?>')"><i class="fas fa-check"></i></button>
                                                <button class="btn btn-no btn-sm" title="Reject" onclick="doAction(<?php echo $a['admin_id']; ?>,'reject','<?php echo addslashes($fullName); ?>')"><i class="fas fa-times"></i></button>
                                            <?php endif; ?>

                                            <!-- Re-approve if rejected -->
                                            <?php if ($a['status'] === 'rejected'): ?>
                                                <button class="btn btn-ok btn-sm" title="Re-approve" onclick="doAction(<?php echo $a['admin_id']; ?>,'approve','<?php echo addslashes($fullName); ?>')"><i class="fas fa-rotate-left"></i> Re-approve</button>
                                            <?php endif; ?>

                                            <!-- Warn -->
                                            <button class="btn btn-warn btn-sm" title="Send warning" onclick="openWarn(<?php echo $a['admin_id']; ?>,'<?php echo addslashes($fullName); ?>')"><i class="fas fa-triangle-exclamation"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="empty" id="emptyMsg" style="display:none"><i class="fas fa-user-shield"></i><strong>No admins found</strong>Try adjusting your filters or search term.</div>
                <div class="pgbar">
                    <div class="pgi" id="pgi"></div>
                    <div class="pgbtns" id="pgbtns"></div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══════════════════════ VIEW MODAL ═══════════════════════ -->
    <div class="mov" id="vModal">
        <div class="mob">
            <div class="mob-accent"></div>
            <div class="mob-profile">
                <div class="mob-av-lg" id="mAvatar">?</div>
                <div class="mob-pinfo">
                    <h3 id="mName">Admin</h3>
                    <p id="mMeta">—</p>
                    <div class="mob-badges" id="mBadges"></div>
                </div>
                <button class="mob-x" onclick="closeMod()"><i class="fas fa-xmark"></i></button>
            </div>

            <!-- Metrics: Registered / Status / Role / State -->
            <div class="mob-metrics">
                <div class="mob-met">
                    <div class="mob-met-v" id="mMetStatus" style="font-size:.85rem">—</div>
                    <div class="mob-met-l">Status</div>
                </div>
                <div class="mob-met">
                    <div class="mob-met-v" id="mMetRole" style="font-size:.85rem">—</div>
                    <div class="mob-met-l">Role</div>
                </div>
                <div class="mob-met">
                    <div class="mob-met-v" id="mMetState" style="font-size:.85rem">—</div>
                    <div class="mob-met-l">State</div>
                </div>
                <div class="mob-met">
                    <div class="mob-met-v" id="mMetReg" style="font-size:.85rem">—</div>
                    <div class="mob-met-l">Registered</div>
                </div>
            </div>

            <div class="mob-body">
                <!-- Status notices -->
                <div class="pend-notice" id="mPendNotice" style="display:none">
                    <i class="fas fa-hourglass-half"></i>
                    <div><strong>Account pending approval.</strong> This admin cannot log in until approved.</div>
                </div>
                <div class="rej-notice" id="mRejNotice" style="display:none">
                    <i class="fas fa-times-circle"></i>
                    <div><strong>Account has been rejected.</strong> This admin cannot access the platform.</div>
                </div>

                <!-- Account info -->
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-user-shield"></i> Account Information</div>
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
                            <div class="dg-l">Registered</div>
                            <div class="dg-v" id="mReg">—</div>
                        </div>
                    </div>
                </div>

                <!-- Role management (superadmin only, non-self) -->
                <?php if ($isSuperAdmin): ?>
                    <div class="mob-section" id="mRoleSection" style="display:none">
                        <div class="mob-sec-title"><i class="fas fa-crown"></i> Role Management</div>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <div style="font-size:.8rem;color:var(--text2)">Change this admin's role. This affects what they can manage on the platform.</div>
                            <div class="role-strip" id="roleStrip">
                                <input class="role-opt" type="radio" name="mRoleOpt" id="roleAdmin" value="admin">
                                <label class="role-lbl" for="roleAdmin"><i class="fas fa-user-shield"></i> Admin</label>
                                <input class="role-opt" type="radio" name="mRoleOpt" id="roleSuperAdmin" value="superadmin">
                                <label class="role-lbl" for="roleSuperAdmin"><i class="fas fa-crown"></i> Super Admin</label>
                                <button class="role-save-btn" id="roleSaveBtn" onclick="saveRoleFromModal()"><i class="fas fa-floppy-disk"></i> Save Role</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Personal info -->
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-user"></i> Personal Information</div>
                    <div class="dgrid">
                        <div class="dg">
                            <div class="dg-l">Gender</div>
                            <div class="dg-v" id="mGender">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">Date of Birth</div>
                            <div class="dg-v" id="mDob">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">State</div>
                            <div class="dg-v" id="mState">—</div>
                        </div>
                        <div class="dg">
                            <div class="dg-l">District</div>
                            <div class="dg-v" id="mDistrict">—</div>
                        </div>
                    </div>
                </div>

                <!-- Academic -->
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-graduation-cap"></i> Academic / Professional</div>
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
                            <div class="dg-l">Subjects</div>
                            <div class="dg-v" id="mSubjects">—</div>
                        </div>
                    </div>
                </div>

                <!-- Bio -->
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-align-left"></i> Bio</div>
                    <div class="dg-v" id="mBio" style="font-size:.84rem;line-height:1.6;color:var(--text2)">—</div>
                </div>

                <!-- Address -->
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

                <!-- Activity -->
                <div class="mob-section">
                    <div class="mob-sec-title"><i class="fas fa-clock-rotate-left"></i> Activity</div>
                    <div class="act-row" id="mActivity"></div>
                </div>
            </div>

            <div class="mob-foot">
                <button class="btn btn-outline" onclick="closeMod()">Close</button>
                <!-- Approve / Reject (pending only, non-self) -->
                <button class="btn btn-ok  btn-sm" id="mAppBtn" style="display:none" onclick="mDoAction('approve')"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-no  btn-sm" id="mRejBtn" style="display:none" onclick="mDoAction('reject')"><i class="fas fa-times"></i> Reject</button>
                <!-- Re-approve if rejected -->
                <button class="btn btn-ok  btn-sm" id="mReAppBtn" style="display:none" onclick="mDoAction('approve')"><i class="fas fa-rotate-left"></i> Re-approve</button>
                <!-- Warn (non-self only) -->
                <button class="btn btn-warn btn-sm" id="mWarnBtn" style="display:none" onclick="mWarn()"><i class="fas fa-triangle-exclamation"></i> Warn</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ WARN MODAL ═══════════════════════ -->
    <div class="mov" id="warnMod">
        <div class="mob mob-sm">
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
                    <span>This warning will be logged against this admin's account for review and record purposes.</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text3)">Warning Message *</label>
                    <textarea id="warnMsg" rows="5" placeholder="Describe the issue or violation clearly…"
                        style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:.85rem;color:var(--text);background:var(--surface);outline:none;resize:vertical;line-height:1.5;transition:border-color .15s;"
                        onfocus="this.style.borderColor='var(--amber)'" onblur="this.style.borderColor='var(--border)'"></textarea>
                    <div style="font-size:.72rem;color:var(--text3)">Be specific about what policy was violated and what should change.</div>
                </div>
            </div>
            <div class="mob-foot">
                <button class="btn btn-outline" onclick="closeWarn()">Cancel</button>
                <button class="btn" id="warnSendBtn" onclick="sendWarn()" style="background:var(--amber);color:#fff;box-shadow:0 2px 8px rgba(217,119,6,.25);">
                    <i class="fas fa-paper-plane"></i> Send Warning
                </button>
            </div>
        </div>
    </div>

    <script>
        let curSt = 'all',
            curMod = null,
            warnTarget = {
                id: 0,
                name: ''
            };
        const PER = 25;
        let pg = 1;
        const SELF = <?php echo $selfId; ?>;
        const IS_SUPER = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;

        /* ─── Filter & Pagination ─── */
        function go() {
            const q = document.getElementById('searchQ').value.toLowerCase().trim();
            const rl = document.getElementById('fRole').value;
            const all = [...document.querySelectorAll('.dr')];
            const vis = all.filter(r => (curSt === 'all' || r.dataset.st === curSt) && (!q || r.dataset.s.includes(q)) && (!rl || r.dataset.role === rl));
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

        function renderPg(total, pages) {
            document.getElementById('pgi').textContent = total === 0 ? 'No results' : `Showing ${Math.min((pg-1)*PER+1,total)}–${Math.min(pg*PER,total)} of ${total}`;
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

        /* ─── Checkbox / Bulk ─── */
        function tAll(v) {
            document.querySelectorAll('.dr').forEach(r => {
                if (r.style.display !== 'none') {
                    const cb = r.querySelector('.rcb');
                    if (cb) {
                        cb.checked = v;
                        r.classList.toggle('sel', v);
                    }
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

        const post = (url, d) => fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(d)
        }).then(r => r.json());

        /* ─── Single action ─── */
        const ACTION_CFG = {
            approve: {
                title: 'Approve this admin?',
                icon: 'question',
                btnTxt: '<i class="fas fa-check"></i> Approve',
                color: '#059669',
                msg: 'approved'
            },
            reject: {
                title: 'Reject this admin?',
                icon: 'warning',
                btnTxt: '<i class="fas fa-times"></i> Reject',
                color: '#dc2626',
                msg: 'rejected'
            },
            warn: {
                title: 'Send a warning?',
                icon: 'warning',
                btnTxt: '<i class="fas fa-paper-plane"></i> Send',
                color: '#d97706',
                msg: 'warned'
            },
        };

        function doAction(id, action, name) {
            if (id === SELF && action !== 'warn') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cannot perform this action on yourself.'
                });
                return;
            }
            const m = ACTION_CFG[action];
            Swal.fire({
                title: m.title,
                html: `<span style="color:#64748b;font-size:.9rem"><strong style="color:#0f172a">${name}</strong> will be ${m.msg}.</span>`,
                icon: m.icon,
                showCancelButton: true,
                confirmButtonText: m.btnTxt,
                cancelButtonText: 'Cancel',
                confirmButtonColor: m.color,
                cancelButtonColor: '#e2e8f0'
            }).then(r => {
                if (!r.isConfirmed) return;
                post('auth/admin_action.php', {
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

        /* ─── Change Role ─── */
        function changeRole(id, newRole, name, rowEl) {
            if (id === SELF) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cannot change your own role.'
                });
                return;
            }
            if (!IS_SUPER) {
                Swal.fire({
                    icon: 'error',
                    title: 'Unauthorized',
                    text: 'Only Super Admins can change roles.'
                });
                return;
            }
            const isPromote = newRole === 'superadmin';
            Swal.fire({
                title: isPromote ? 'Promote to Super Admin?' : 'Demote to Admin?',
                html: `
        <div style="display:flex;flex-direction:column;align-items:center;gap:14px;padding:4px 0">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="display:flex;flex-direction:column;align-items:center;gap:5px;">
                    <div style="width:44px;height:44px;border-radius:12px;background:${isPromote?'var(--indigo-s)':'var(--purple-s)'};display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:${isPromote?'var(--indigo)':'var(--purple)'}"><i class="fas ${isPromote?'fa-user-shield':'fa-crown'}"></i></div>
                    <span style="font-size:.7rem;font-weight:700;color:var(--text3)">${isPromote?'ADMIN':'SUPER ADMIN'}</span>
                </div>
                <i class="fas fa-arrow-right" style="color:var(--text3);font-size:.9rem"></i>
                <div style="display:flex;flex-direction:column;align-items:center;gap:5px;">
                    <div style="width:44px;height:44px;border-radius:12px;background:${isPromote?'var(--purple-s)':'var(--indigo-s)'};display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:${isPromote?'var(--purple)':'var(--indigo)'}"><i class="fas ${isPromote?'fa-crown':'fa-user-shield'}"></i></div>
                    <span style="font-size:.7rem;font-weight:700;color:${isPromote?'var(--purple)':'var(--indigo)'}">${isPromote?'SUPER ADMIN':'ADMIN'}</span>
                </div>
            </div>
            <div style="font-size:.84rem;color:#64748b"><strong style="color:#0f172a">${name}</strong> will be ${isPromote?'promoted':'demoted'}.</div>
            ${isPromote?'<div style="background:var(--purple-s);border:1px solid rgba(124,58,237,.2);border-radius:9px;padding:9px 14px;font-size:.78rem;color:#5b21b6;max-width:340px;text-align:center"><i class="fas fa-triangle-exclamation" style="margin-right:5px"></i>Super Admins have full platform access.</div>':''}
        </div>`,
                icon: undefined,
                showCancelButton: true,
                confirmButtonText: isPromote ? '<i class="fas fa-crown"></i> Yes, Promote' : '<i class="fas fa-arrow-down"></i> Yes, Demote',
                cancelButtonText: 'Cancel',
                confirmButtonColor: isPromote ? '#7c3aed' : '#4f46e5',
                cancelButtonColor: '#e2e8f0',
                customClass: {
                    popup: 'swal-role-popup'
                }
            }).then(r => {
                if (!r.isConfirmed) return;
                Swal.fire({
                    title: isPromote ? 'Promoting…' : 'Demoting…',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                post('auth/admin_action.php', {
                    id,
                    action: 'change_role',
                    role: newRole
                }).then(d => {
                    if (d.success) {
                        Swal.fire({
                            icon: 'success',
                            title: isPromote ? '<span style="color:#7c3aed">Promoted!</span>' : 'Demoted!',
                            html: `<span style="color:#64748b"><strong style="color:#0f172a">${name}</strong> is now a ${isPromote?'<strong style="color:#7c3aed">Super Admin</strong>':'<strong>Admin</strong>'}.</span>`,
                            timer: 2200,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: d.message
                        });
                    }
                }).catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Network error.'
                }));
            });
        }

        /* ─── Bulk action ─── */
        function bAction(action) {
            const ids = [...document.querySelectorAll('.rcb:checked')].map(cb => parseInt(cb.dataset.id));
            if (!ids.length) return;
            const m = ACTION_CFG[action] || {
                title: `${action} selected?`,
                icon: 'question',
                btnTxt: 'Confirm',
                color: '#4f46e5'
            };
            Swal.fire({
                title: m.title.replace('this admin?', `${ids.length} admin(s)?`),
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
                post('auth/bulk_admin_action.php', {
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

        /* ─── View Modal ─── */
        function sv(id, val) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = val || '—';
            el.className = 'dg-v' + ((!val || val === '—') ? ' muted' : '');
        }

        function openMod(a) {
            curMod = a;
            /* Avatar */
            document.getElementById('mAvatar').textContent = a.initials || '?';
            document.getElementById('mName').textContent = a.name + (a.is_self ? ' (You)' : '');
            document.getElementById('mMeta').textContent = `@${a.username}  ·  ${a.email}`;

            /* Badges */
            const roleCls = a.role === 'superadmin' ? 'bdg-super' : 'bdg-admin';
            const stCls = {
                'approved': 'bdg-approved',
                'pending': 'bdg-pending',
                'rejected': 'bdg-rejected'
            } [a.status] || 'bdg-pending';
            document.getElementById('mBadges').innerHTML =
                `<span class="bdg ${roleCls}">${a.role==='superadmin'?'<i class="fas fa-crown" style="font-size:.5rem"></i> Super Admin':'Admin'}</span>` +
                `<span class="bdg ${stCls}">${a.status.charAt(0).toUpperCase()+a.status.slice(1)}</span>` +
                (a.is_self ? '<span class="bdg bdg-you">You</span>' : '');

            /* Metrics */
            document.getElementById('mMetStatus').textContent = a.status.charAt(0).toUpperCase() + a.status.slice(1);
            document.getElementById('mMetRole').textContent = a.role === 'superadmin' ? 'Super Admin' : 'Admin';
            document.getElementById('mMetState').textContent = a.state || '—';
            document.getElementById('mMetReg').textContent = a.registered;

            /* Notices */
            document.getElementById('mPendNotice').style.display = a.status === 'pending' ? 'flex' : 'none';
            document.getElementById('mRejNotice').style.display = a.status === 'rejected' ? 'flex' : 'none';

            /* Fields */
            sv('mUsername', '@' + a.username);
            sv('mEmail', a.email);
            sv('mPhone', a.phone);
            sv('mReg', a.registered);
            sv('mGender', a.gender);
            sv('mDob', a.dob ? new Date(a.dob).toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }) : '—');
            sv('mState', a.state);
            sv('mDistrict', a.district);
            sv('mCourse', a.course);
            sv('mInstitution', a.institution);
            sv('mSubjects', a.subjects);
            const bioEl = document.getElementById('mBio');
            bioEl.textContent = a.bio || 'No bio provided.';
            bioEl.className = 'dg-v' + (a.bio ? '' : ' muted');
            sv('mCurrAddr', a.current_addr);
            sv('mPermAddr', a.permanent_addr);

            /* Activity strip */
            document.getElementById('mActivity').innerHTML = `
        <div class="act-chip"><i class="fas fa-calendar"></i>Registered: <strong>${a.registered}</strong></div>
        <div class="act-chip"><i class="fas fa-user-shield"></i>Role: <strong>${a.role==='superadmin'?'Super Admin':'Admin'}</strong></div>
        <div class="act-chip"><i class="fas fa-circle-dot"></i>Status: <strong>${a.status.charAt(0).toUpperCase()+a.status.slice(1)}</strong></div>
        <div class="act-chip"><i class="fas fa-map-pin"></i>State: <strong>${a.state||'—'}</strong></div>
        <div class="act-chip"><i class="fas fa-building-columns"></i>Institution: <strong>${a.institution||'—'}</strong></div>
    `;

            /* Role management (superadmin only, non-self) */
            const roleSec = document.getElementById('mRoleSection');
            if (roleSec) {
                roleSec.style.display = (!a.is_self && IS_SUPER) ? 'block' : 'none';
                if (!a.is_self && IS_SUPER) {
                    document.querySelectorAll('input[name="mRoleOpt"]').forEach(r => {
                        r.checked = r.value === a.role;
                    });
                    document.getElementById('roleSaveBtn').classList.remove('show');
                    document.querySelectorAll('input[name="mRoleOpt"]').forEach(r => {
                        r.onchange = () => document.getElementById('roleSaveBtn').classList.toggle('show', r.value !== a.role);
                    });
                }
            }

            /* Footer buttons */
            const notSelf = !a.is_self;
            document.getElementById('mAppBtn').style.display = (notSelf && a.status === 'pending') ? '' : 'none';
            document.getElementById('mRejBtn').style.display = (notSelf && a.status === 'pending') ? '' : 'none';
            document.getElementById('mReAppBtn').style.display = (notSelf && a.status === 'rejected') ? '' : 'none';
            document.getElementById('mWarnBtn').style.display = notSelf ? '' : 'none';

            document.getElementById('vModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeMod() {
            document.getElementById('vModal').classList.remove('show');
            document.body.style.overflow = '';
            curMod = null;
        }

        function mDoAction(action) {
            if (!curMod) return;
            closeMod();
            doAction(curMod.id, action, curMod.name);
        }

        function mWarn() {
            if (!curMod) return;
            closeMod();
            openWarn(curMod.id, curMod.name);
        }

        /* Save role from inside modal */
        function saveRoleFromModal() {
            if (!curMod) return;
            const sel = document.querySelector('input[name="mRoleOpt"]:checked');
            if (!sel) return;
            closeMod();
            changeRole(curMod.id, sel.value, curMod.name);
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

        /* ─── Warn Modal ─── */
        function openWarn(id, name) {
            warnTarget = {
                id,
                name
            };
            document.getElementById('warnName').textContent = name;
            document.getElementById('warnMsg').value = '';
            document.getElementById('warnMod').classList.add('show');
            document.body.style.overflow = 'hidden';
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
            post('auth/admin_action.php', {
                    id: warnTarget.id,
                    action: 'warn',
                    message: msg
                }).then(d => {
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
                }).catch(() => Swal.fire({
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

        /* flash */
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
        if (_s) history.replaceState(null, '', 'admins.php');
    </script>
</body>

</html>