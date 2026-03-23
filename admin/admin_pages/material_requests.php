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

/* ── Fetch all material requests with user info ── */
$rq = $conn->prepare("
    SELECT
        mr.request_id,
        mr.ref_code,
        mr.tracking_number,
        mr.material_type,
        mr.title,
        mr.author,
        mr.subject_code,
        mr.edition,
        mr.exam_year,
        mr.lecturer,
        mr.unit_needed,
        mr.note_type,
        mr.university,
        mr.description,
        mr.priority,
        mr.status,
        mr.admin_note,
        mr.fulfilled_by,
        mr.fulfilled_at,
        mr.created_at,
        mr.updated_at,
        mr.user_id,
        u.username,
        u.email,
        u.profile_image,
        COALESCE(
            NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
            NULLIF(TRIM(CONCAT(t.first_name,' ',t.last_name)),''),
            u.username
        ) AS display_name,
        COALESCE(s.student_id,'') AS student_roll
    FROM material_requests mr
    JOIN users u ON u.user_id = mr.user_id
    LEFT JOIN students s ON s.user_id = mr.user_id
    LEFT JOIN tutors   t ON t.user_id = mr.user_id
    ORDER BY
        CASE mr.status
            WHEN 'Pending'     THEN 0
            WHEN 'In Progress' THEN 1
            WHEN 'Fulfilled'   THEN 2
            WHEN 'Cannot Fulfil' THEN 3
            WHEN 'Cancelled'   THEN 4
            ELSE 5
        END,
        CASE mr.priority
            WHEN 'High'   THEN 0
            WHEN 'Medium' THEN 1
            ELSE 2
        END,
        mr.created_at DESC
    LIMIT 100
");
$rq->execute();
$requests = $rq->fetchAll(PDO::FETCH_ASSOC);

/* ── Stat counts ── */
$cntPending      = count(array_filter($requests, fn($r) => $r['status'] === 'Pending'));
$cntInProgress   = count(array_filter($requests, fn($r) => $r['status'] === 'In Progress'));
$cntFulfilled    = count(array_filter($requests, fn($r) => $r['status'] === 'Fulfilled'));
$cntCannotFulfil = count(array_filter($requests, fn($r) => $r['status'] === 'Cannot Fulfil'));
$cntCancelled    = count(array_filter($requests, fn($r) => $r['status'] === 'Cancelled'));
$cntHighPriority = count(array_filter($requests, fn($r) => $r['priority'] === 'High' && in_array($r['status'], ['Pending', 'In Progress'])));

function ago(string $dt): string
{
    $d = time() - strtotime($dt);
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return floor($d / 60)    . 'm ago';
    if ($d < 86400)  return floor($d / 3600)  . 'h ago';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    return date('d M Y', strtotime($dt));
}

// Statuses where Upload button should be hidden
$terminalStatuses = ['Fulfilled', 'Cannot Fulfil', 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Material Requests | ScholarSwap Admin</title>
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
            --maroon: #7a0c0c;
            --maroon-s: #fde8e8;
            --gold: #b45309;
            --gold-s: #fef3c7;
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
            min-width: 140px
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

        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            margin-bottom: 16px
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

        .panel {
            width: 1050px;
        }

        .tw {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            border-radius: 0 0 var(--r2) var(--r2);
            background: linear-gradient(to right, var(--surface) 24px, transparent 24px), linear-gradient(to left, var(--surface) 24px, transparent 24px) right, radial-gradient(farthest-side at 0 50%, rgba(0, 0, 0, .09), transparent) left, radial-gradient(farthest-side at 100% 50%, rgba(0, 0, 0, .09), transparent) right;
            background-repeat: no-repeat;
            background-color: var(--surface);
            background-size: 40px 100%, 40px 100%, 14px 100%, 14px 100%;
            background-attachment: local, local, scroll, scroll;
        }

        .tw::-webkit-scrollbar {
            height: 6px;
        }

        .tw::-webkit-scrollbar-track {
            background: var(--bg);
            border-radius: 0 0 var(--r2) var(--r2);
        }

        .tw::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px;
            cursor: grab;
        }

        .tw::-webkit-scrollbar-thumb:hover {
            background: #7a0c0c55;
        }

        .tw {
            scrollbar-width: thin;
            scrollbar-color: var(--border2) var(--bg);
        }

        .scroll-hint {
            display: none;
            align-items: center;
            gap: 5px;
            font-size: .68rem;
            color: var(--text3);
            padding: 6px 18px 4px;
            border-top: 1px solid var(--border);
            background: var(--bg);
        }

        @media (max-width: 860px) {
            .scroll-hint {
                display: flex;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
            min-width: 980px;
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
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        thead th:first-child {
            width: 36px;
            min-width: 36px;
        }

        thead th:nth-child(2) {
            min-width: 120px;
        }

        thead th:nth-child(3) {
            min-width: 150px;
        }

        thead th:nth-child(4) {
            min-width: 160px;
        }

        thead th:nth-child(5),
        thead th:nth-child(6),
        thead th:nth-child(7) {
            min-width: 100px;
        }

        thead th:nth-child(8) {
            min-width: 130px;
        }

        thead th:nth-child(9) {
            min-width: 80px;
        }

        thead th:nth-child(10) {
            min-width: 280px;
        }

        tbody td {
            padding: 10px 11px;
            font-size: .8rem;
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

        .tc {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .filtered-out {
            display: none !important;
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

        .bdg-pending {
            background: var(--amber-s);
            color: #92400e
        }

        .bdg-inprogress {
            background: var(--blue-s);
            color: var(--blue)
        }

        .bdg-fulfilled {
            background: var(--green-s);
            color: #065f46
        }

        .bdg-cannot {
            background: var(--red-s);
            color: #7f1d1d
        }

        .bdg-cancelled {
            background: var(--bg);
            color: var(--text3);
            border: 1px solid var(--border2)
        }

        .bdg-high {
            background: #fee2e2;
            color: #991b1b
        }

        .bdg-medium {
            background: var(--amber-s);
            color: #92400e
        }

        .bdg-low {
            background: var(--green-s);
            color: #065f46
        }

        .bdg-textbook {
            background: var(--blue-s);
            color: var(--blue)
        }

        .bdg-notes {
            background: var(--teal-s);
            color: var(--teal)
        }

        .bdg-papers {
            background: var(--purple-s);
            color: var(--purple)
        }

        .bdg-other {
            background: var(--bg);
            color: var(--text3)
        }

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

        .btn-fulfill {
            background: var(--green-s);
            color: var(--green)
        }

        .btn-fulfill:hover {
            background: var(--green);
            color: #fff
        }

        .btn-progress {
            background: var(--blue-s);
            color: var(--blue)
        }

        .btn-progress:hover {
            background: var(--blue);
            color: #fff
        }

        .btn-cannot {
            background: var(--red-s);
            color: var(--red)
        }

        .btn-cannot:hover {
            background: var(--red);
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

        .btn-notif {
            background: var(--purple-s);
            color: var(--purple)
        }

        .btn-notif:hover {
            background: var(--purple);
            color: #fff
        }

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

        /* DETAIL MODAL */
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
            max-width: 660px;
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
            background: linear-gradient(90deg, #7a0c0c, #b45309, #2563eb);
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

        .modal-head-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--maroon-s), #fff4cc);
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
            gap: 7px;
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
            scrollbar-width: thin;
            scrollbar-color: var(--border2) transparent
        }

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

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px
        }

        .info-item {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 10px 12px
        }

        .info-item-label {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text3);
            margin-bottom: 4px
        }

        .info-item-val {
            font-size: .82rem;
            font-weight: 600;
            color: var(--text)
        }

        .desc-box {
            font-size: .84rem;
            color: var(--text2);
            line-height: 1.7;
            background: var(--bg);
            border-radius: 9px;
            padding: 12px 14px;
            border: 1px solid var(--border)
        }

        .trk-display {
            background: linear-gradient(135deg, var(--maroon-s), #fff4cc);
            border: 1.5px solid rgba(122, 12, 12, .2);
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px
        }

        .trk-code {
            font-family: 'Space Grotesk', sans-serif;
            font-size: .95rem;
            font-weight: 800;
            color: #7a0c0c;
            letter-spacing: .06em
        }

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

        /* ACTION DRAWER */
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
            background: linear-gradient(90deg, #7a0c0c, #b45309, #2563eb)
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

        .drawer-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            background: #fafafa;
            flex-shrink: 0
        }

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
            font-size: .65rem
        }

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

        .ntype-warning.sel {
            background: var(--red-s);
            color: var(--red);
            border-color: var(--red)
        }

        .ntype-admin_msg.sel {
            background: var(--purple-s);
            color: var(--purple);
            border-color: var(--purple)
        }

        .ntype-info.sel {
            background: var(--blue-s);
            color: var(--blue);
            border-color: var(--blue)
        }

        .ntype-success.sel {
            background: var(--green-s);
            color: var(--green);
            border-color: var(--green)
        }

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
            max-height: 800px;
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

        .res-link-box {
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            transition: border-color .18s;
        }

        .res-link-box:focus-within {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
        }

        .res-link-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            gap: 8px;
        }

        .res-link-header-left {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: .75rem;
            font-weight: 700;
            color: var(--text2);
        }

        .res-link-header-left i {
            color: var(--blue);
            font-size: .7rem;
        }

        .res-link-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .68rem;
            font-weight: 600;
            color: var(--text3);
            cursor: pointer;
            padding: 3px 9px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg);
            transition: all .15s;
            font-family: inherit;
        }

        .res-link-toggle:hover,
        .res-link-toggle.active {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-s);
        }

        .res-link-body {
            display: none;
            padding: 10px 12px;
            flex-direction: column;
            gap: 8px;
        }

        .res-link-body.open {
            display: flex;
        }

        .res-quick-chips {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .res-qchip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 6px;
            border: 1.5px solid var(--border);
            background: var(--surface);
            font-size: .66rem;
            font-weight: 700;
            cursor: pointer;
            color: var(--text2);
            transition: all .14s;
            font-family: inherit;
        }

        .res-qchip:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-s);
        }

        .res-qchip.active {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-s);
        }

        .res-link-input-row {
            display: flex;
            gap: 6px;
            align-items: stretch;
        }

        .res-link-input {
            flex: 1;
            padding: 8px 11px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: .82rem;
            font-family: inherit;
            color: var(--text);
            background: var(--surface);
            outline: none;
            transition: border-color .15s;
        }

        .res-link-input:focus {
            border-color: var(--blue);
        }

        .res-link-input::placeholder {
            color: var(--text3);
            font-size: .76rem;
        }

        .res-link-label-inp {
            width: 100%;
            padding: 7px 11px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: .8rem;
            font-family: inherit;
            color: var(--text);
            background: var(--surface);
            outline: none;
            transition: border-color .15s;
        }

        .res-link-label-inp:focus {
            border-color: var(--blue);
        }

        .res-link-label-inp::placeholder {
            color: var(--text3);
            font-size: .76rem;
        }

        .res-link-preview {
            display: none;
            align-items: center;
            gap: 9px;
            padding: 8px 11px;
            background: var(--blue-s);
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            font-size: .75rem;
            color: var(--blue);
            word-break: break-all;
            line-height: 1.4;
        }

        .res-link-preview.show {
            display: flex;
        }

        .res-link-preview i {
            flex-shrink: 0;
            font-size: .72rem;
        }

        .res-link-clear {
            padding: 6px 10px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text3);
            font-size: .7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .14s;
            font-family: inherit;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .res-link-clear:hover {
            border-color: var(--red-s);
            background: var(--red-s);
            color: var(--red);
        }

        .status-action-row {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 14px
        }

        .sact {
            padding: 6px 13px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            background: var(--bg);
            font-size: .76rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: inherit
        }

        .sact:hover {
            transform: translateY(-1px)
        }

        .sact-progress.sel {
            background: var(--blue-s);
            border-color: var(--blue);
            color: var(--blue)
        }

        .sact-fulfill.sel {
            background: var(--green-s);
            border-color: var(--green);
            color: var(--green)
        }

        .sact-cannot.sel {
            background: var(--red-s);
            border-color: var(--red);
            color: var(--red)
        }

        @media(max-width:600px) {
            .stat-pill {
                flex: 1;
                min-width: 0
            }

            .info-grid {
                grid-template-columns: 1fr
            }

            .tpl-panel {
                grid-template-columns: 1fr
            }

            .modal-footer {
                gap: 5px
            }

            .modal-footer .btn {
                font-size: .7rem;
                padding: 8px 8px
            }

            .modal-head {
                padding: 14px 16px 12px;
                gap: 10px
            }

            .modal-section {
                padding: 12px 16px
            }
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
                    <h1><i class="fas fa-inbox" style="color:#7a0c0c;margin-right:8px"></i>Material Requests</h1>
                    <p>Manage student material requests — update status, send notifications, and communicate with students.</p>
                </div>
            </div>

            <!-- Stat strip -->
            <div class="stat-strip">
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--amber-s);color:var(--amber)"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo $cntPending; ?></div>
                        <div class="stat-pill-lbl">Pending</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--blue-s);color:var(--blue)"><i class="fas fa-spinner"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo $cntInProgress; ?></div>
                        <div class="stat-pill-lbl">In Progress</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--green-s);color:var(--green)"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo $cntFulfilled; ?></div>
                        <div class="stat-pill-lbl">Fulfilled</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--red-s);color:var(--red)"><i class="fas fa-circle-xmark"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo $cntCannotFulfil; ?></div>
                        <div class="stat-pill-lbl">Cannot Fulfil</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--red-s);color:#991b1b"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo $cntHighPriority; ?></div>
                        <div class="stat-pill-lbl">High Priority</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon" style="background:var(--bg);color:var(--text3)"><i class="fas fa-list"></i></div>
                    <div>
                        <div class="stat-pill-val"><?php echo count($requests); ?></div>
                        <div class="stat-pill-lbl">Total</div>
                    </div>
                </div>
            </div>

            <!-- Table panel -->
            <div class="panel">
                <div class="ph">
                    <div>
                        <div class="pt"><i class="fas fa-inbox" style="color:#7a0c0c;margin-right:6px"></i>All Material Requests</div>
                        <div class="ph-sub">Click <strong>View</strong> to see full request details and take action</div>
                    </div>
                </div>
                <div class="filter-bar">
                    <span class="filter-label">Filter:</span>
                    <select class="filter-select" id="statusFilter" onchange="filterTable()">
                        <option value="">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Fulfilled">Fulfilled</option>
                        <option value="Cannot Fulfil">Cannot Fulfil</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                    <select class="filter-select" id="priorityFilter" onchange="filterTable()">
                        <option value="">All Priorities</option>
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                    </select>
                    <select class="filter-select" id="typeFilter" onchange="filterTable()">
                        <option value="">All Types</option>
                        <option value="Textbook">Textbook</option>
                        <option value="Lecture Notes">Notes</option>
                        <option value="Past Papers">Past Papers</option>
                        <option value="Other">Other</option>
                    </select>
                    <input class="filter-search" type="text" id="searchInput" placeholder="Search by title, student, or ref code…" oninput="filterTable()">
                </div>

                <?php if (empty($requests)): ?>
                    <div class="empty"><i class="fas fa-inbox"></i>No material requests yet.</div>
                <?php else: ?>
                    <div class="tw">
                        <table id="reqTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Ref / Tracking</th>
                                    <th>Student</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Admin Note</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $i => $req):
                                    $statusCls = match ($req['status']) {
                                        'Pending'       => 'bdg-pending',
                                        'In Progress'   => 'bdg-inprogress',
                                        'Fulfilled'     => 'bdg-fulfilled',
                                        'Cannot Fulfil' => 'bdg-cannot',
                                        'Cancelled'     => 'bdg-cancelled',
                                        default         => 'bdg-cancelled',
                                    };
                                    $priCls = match ($req['priority']) {
                                        'High'   => 'bdg-high',
                                        'Medium' => 'bdg-medium',
                                        default  => 'bdg-low',
                                    };
                                    $typeCls = match (true) {
                                        str_contains($req['material_type'], 'Textbook') => 'bdg-textbook',
                                        str_contains($req['material_type'], 'Notes')    => 'bdg-notes',
                                        str_contains($req['material_type'], 'Papers')   => 'bdg-papers',
                                        default                                         => 'bdg-other',
                                    };
                                    $displayName = $req['display_name'] ?? $req['username'];

                                    // Auto-detect upload type
                                    $isBook     = (strpos($req['material_type'], 'Textbook') !== false || strpos($req['material_type'], 'Book') !== false);
                                    $uploadType = $isBook ? 'book' : 'note';

                                    // Hide Upload button for terminal statuses
                                    $isTerminal = in_array($req['status'], $terminalStatuses);

                                    $jsData = htmlspecialchars(json_encode([
                                        'request_id'      => (int)$req['request_id'],
                                        'ref_code'        => $req['ref_code'] ?? '',
                                        'tracking_number' => $req['tracking_number'] ?? '',
                                        'material_type'   => $req['material_type'],
                                        'title'           => $req['title'],
                                        'author'          => $req['author'] ?? '',
                                        'subject_code'    => $req['subject_code'] ?? '',
                                        'edition'         => $req['edition'] ?? '',
                                        'exam_year'       => $req['exam_year'] ?? '',
                                        'lecturer'        => $req['lecturer'] ?? '',
                                        'unit_needed'     => $req['unit_needed'] ?? '',
                                        'note_type'       => $req['note_type'] ?? '',
                                        'university'      => $req['university'] ?? '',
                                        'description'     => $req['description'] ?? '',
                                        'priority'        => $req['priority'],
                                        'status'          => $req['status'],
                                        'admin_note'      => $req['admin_note'] ?? '',
                                        'created_at'      => $req['created_at'],
                                        'updated_at'      => $req['updated_at'] ?? '',
                                        'fulfilled_at'    => $req['fulfilled_at'] ?? '',
                                        'user_id'         => (int)$req['user_id'],
                                        'display_name'    => $displayName,
                                        'username'        => $req['username'] ?? '',
                                        'email'           => $req['email'] ?? '',
                                        'student_roll'    => $req['student_roll'] ?? '',
                                        'profile_image'   => $req['profile_image'] ?? '',
                                    ]), ENT_QUOTES);
                                ?>
                                    <tr class="req-row"
                                        data-status="<?php echo htmlspecialchars($req['status']); ?>"
                                        data-priority="<?php echo htmlspecialchars($req['priority']); ?>"
                                        data-type="<?php echo htmlspecialchars($req['material_type']); ?>"
                                        data-search="<?php echo strtolower(htmlspecialchars($req['title'] . ' ' . $displayName . ' ' . ($req['ref_code'] ?? '') . ' ' . ($req['tracking_number'] ?? ''))); ?>">

                                        <td style="color:var(--text3);font-size:.72rem"><?php echo $i + 1; ?></td>

                                        <td>
                                            <div style="font-size:.72rem;font-weight:800;color:#7a0c0c;letter-spacing:.05em"><?php echo htmlspecialchars($req['ref_code'] ?? '—'); ?></div>
                                            <?php if (!empty($req['tracking_number'])): ?>
                                                <div style="font-size:.62rem;color:var(--text3);margin-top:1px"><?php echo htmlspecialchars($req['tracking_number']); ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="user-cell">
                                                <div class="u-av">
                                                    <?php if (!empty($req['profile_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($req['profile_image']); ?>" onerror="this.remove()">
                                                    <?php endif; ?>
                                                    <?php echo strtoupper(substr($displayName, 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="u-name"><?php echo htmlspecialchars($displayName); ?></div>
                                                    <div class="u-sub">@<?php echo htmlspecialchars($req['username'] ?? ''); ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="tc" title="<?php echo htmlspecialchars($req['title']); ?>">
                                            <span style="font-weight:600;color:var(--text)"><?php echo htmlspecialchars($req['title']); ?></span>
                                        </td>

                                        <td><span class="bdg <?php echo $typeCls; ?>"><?php echo htmlspecialchars($req['material_type']); ?></span></td>

                                        <td><span class="bdg <?php echo $priCls; ?>"><?php echo $req['priority']; ?></span></td>

                                        <td>
                                            <?php
                                            $statusIcon = match ($req['status']) {
                                                'Pending'       => '🕐',
                                                'In Progress'   => '🔄',
                                                'Fulfilled'     => '✅',
                                                'Cannot Fulfil' => '❌',
                                                'Cancelled'     => '🚫',
                                                default         => ''
                                            };
                                            ?>
                                            <span class="bdg <?php echo $statusCls; ?>"><?php echo $statusIcon . ' ' . htmlspecialchars($req['status']); ?></span>
                                        </td>

                                        <td class="tc" title="<?php echo htmlspecialchars($req['admin_note'] ?? ''); ?>" style="font-size:.75rem">
                                            <?php echo !empty($req['admin_note'])
                                                ? '<span style="color:var(--teal)"><i class="fas fa-check-circle" style="font-size:.62rem"></i> ' . htmlspecialchars(substr($req['admin_note'], 0, 30)) . (strlen($req['admin_note']) > 30 ? '…' : '') . '</span>'
                                                : '<span style="color:var(--text3)">—</span>'; ?>
                                        </td>

                                        <td style="font-size:.75rem;color:var(--text3)"><?php echo ago($req['created_at']); ?></td>

                                        <td>
                                            <div class="act">
                                                <button class="btn btn-view"
                                                    onclick="openDetail(<?php echo $jsData; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>

                                                <?php if (!$isTerminal): ?>
                                                    <button class="btn btn-fulfill"
                                                        onclick="window.location.href='upload_material.php?req=<?php echo $req['request_id']; ?>&type=<?php echo $uploadType; ?>'"
                                                        title="Upload material to fulfill this request">
                                                        <i class="fas fa-cloud-upload-alt"></i> Upload
                                                    </button>
                                                <?php endif; ?>

                                                <button class="btn btn-msg"
                                                    onclick="openActionDrawer(<?php echo $jsData; ?>, 'message')">
                                                    <i class="fas fa-envelope"></i> Message
                                                </button>
                                                <button class="btn btn-notif"
                                                    onclick="openActionDrawer(<?php echo $jsData; ?>, 'notify')">
                                                    <i class="fas fa-bell"></i> Notify
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="scroll-hint">
                        <i class="fas fa-arrows-left-right" style="font-size:.6rem"></i>
                        Scroll horizontally to see all columns
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- MODAL: Full Request Detail -->
    <div class="modal-overlay" id="detailOverlay">
        <div class="modal-box">
            <div class="modal-accent"></div>
            <div class="modal-head">
                <div class="modal-head-icon" id="mvIcon">📚</div>
                <div class="modal-head-info">
                    <h2 id="mvTitle">Request Title</h2>
                    <div class="modal-meta-row">
                        <span class="bdg" id="mvTypeBdg">Textbook</span>
                        <span class="bdg" id="mvStatusBdg">Pending</span>
                        <span class="bdg" id="mvPriBdg">High</span>
                    </div>
                    <div style="font-size:.75rem;color:var(--text3);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <span><i class="fas fa-user" style="margin-right:4px"></i>Student: <strong id="mvStudent" style="color:var(--text2)">—</strong></span>
                        <span><i class="fas fa-calendar" style="margin-right:4px"></i>Submitted: <strong id="mvDate" style="color:var(--text2)">—</strong></span>
                    </div>
                </div>
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="modal-body">
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-radar"></i> Tracking</div>
                    <div class="trk-display">
                        <div>
                            <div style="font-size:.62rem;color:var(--text3);margin-bottom:3px;text-transform:uppercase;letter-spacing:.08em;font-weight:700">Tracking Number</div>
                            <div class="trk-code" id="mvTrackingNum">TRK-—</div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-size:.62rem;color:var(--text3);margin-bottom:3px;text-transform:uppercase;letter-spacing:.08em;font-weight:700">Ref Code</div>
                            <div style="font-size:.82rem;font-weight:800;color:#7a0c0c" id="mvRefCode">SS-—</div>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-book-open"></i> Material Details</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-item-label">Author / Publisher</div>
                            <div class="info-item-val" id="mvAuthor">—</div>
                        </div>
                        <div class="info-item">
                            <div class="info-item-label">Subject / Course Code</div>
                            <div class="info-item-val" id="mvSubject">—</div>
                        </div>
                        <div class="info-item" id="mvEditionItem">
                            <div class="info-item-label">Edition</div>
                            <div class="info-item-val" id="mvEdition">—</div>
                        </div>
                        <div class="info-item" id="mvUnivItem">
                            <div class="info-item-label">University / Board</div>
                            <div class="info-item-val" id="mvUniversity">—</div>
                        </div>
                        <div class="info-item" id="mvExamItem">
                            <div class="info-item-label">Exam Year(s)</div>
                            <div class="info-item-val" id="mvExamYear">—</div>
                        </div>
                        <div class="info-item" id="mvLecturerItem">
                            <div class="info-item-label">Lecturer / Professor</div>
                            <div class="info-item-val" id="mvLecturer">—</div>
                        </div>
                        <div class="info-item" id="mvUnitItem">
                            <div class="info-item-label">Unit / Chapter</div>
                            <div class="info-item-val" id="mvUnit">—</div>
                        </div>
                        <div class="info-item" id="mvNoteTypeItem">
                            <div class="info-item-label">Note Type</div>
                            <div class="info-item-val" id="mvNoteType">—</div>
                        </div>
                    </div>
                    <div style="margin-top:8px" id="mvDescWrap">
                        <div class="info-item-label" style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:6px">Additional Context</div>
                        <div class="desc-box" id="mvDescription">—</div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-user-graduate"></i> Student</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-item-label">Full Name</div>
                            <div class="info-item-val" id="mvStuName">—</div>
                        </div>
                        <div class="info-item">
                            <div class="info-item-label">Username</div>
                            <div class="info-item-val" id="mvStuUsername">—</div>
                        </div>
                        <div class="info-item">
                            <div class="info-item-label">Email</div>
                            <div class="info-item-val" id="mvStuEmail" style="font-size:.78rem">—</div>
                        </div>
                        <div class="info-item">
                            <div class="info-item-label">Roll / ID</div>
                            <div class="info-item-val" id="mvStuRoll">—</div>
                        </div>
                    </div>
                </div>

                <div class="modal-section" id="mvAdminNoteSection">
                    <div class="modal-section-title"><i class="fas fa-comment-dots" style="color:var(--teal)"></i> Admin Note</div>
                    <div class="desc-box" id="mvAdminNote" style="border-color:var(--teal-s);background:#f0fdfa;color:var(--teal)">—</div>
                </div>
            </div>

            <div class="modal-footer" id="mvFooter">
                <button class="btn btn-progress" onclick="mvQuickAction('progress')"><i class="fas fa-spinner"></i> In Progress</button>
                <button class="btn btn-fulfill" id="mvUploadBtn" onclick="mvQuickAction('upload')"><i class="fas fa-cloud-upload-alt"></i> Upload Material</button>
                <button class="btn btn-cannot" onclick="mvQuickAction('cannot')"><i class="fas fa-ban"></i> Cannot Fulfil</button>
                <button class="btn btn-msg" onclick="mvQuickAction('message')"><i class="fas fa-envelope"></i> Message</button>
                <button class="btn btn-notif" onclick="mvQuickAction('notify')"><i class="fas fa-bell"></i> Notify</button>
            </div>
        </div>
    </div>

    <!-- DRAWER: Action Panel -->
    <div class="drawer-overlay" id="actionDrawerOverlay">
        <div class="drawer">
            <div class="drawer-head">
                <div class="drawer-icon" id="adIcon" style="background:var(--blue-s);color:var(--blue)">
                    <i class="fas fa-envelope"></i>
                </div>
                <div>
                    <h3 id="adTitle">Send Message</h3>
                    <div class="drawer-sub" id="adSub">Communicate with the student</div>
                </div>
                <button class="drawer-close" onclick="closeDrawer()"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="drawer-body">
                <div class="ctx-card">
                    <strong id="adReqTitle">Request Title</strong>
                    <div class="ctx-row"><i class="fas fa-tag"></i><span id="adReqType">—</span></div>
                    <div class="ctx-row"><i class="fas fa-user"></i><span id="adReqStudent">—</span></div>
                    <div class="ctx-row"><i class="fas fa-circle" style="font-size:.45rem"></i> Status: <span id="adReqStatus">—</span></div>
                </div>

                <div class="form-group" id="adNotifTypeGroup">
                    <label class="form-label"><i class="fas fa-bell" style="margin-right:4px"></i>Notification Type</label>
                    <div class="notif-types" id="adNotifTypes">
                        <span class="ntype ntype-admin_msg sel" data-val="admin_message" onclick="adSelectType(this)">📨 Admin Message</span>
                        <span class="ntype ntype-info" data-val="info" onclick="adSelectType(this)">ℹ️ Info</span>
                        <span class="ntype ntype-success" data-val="success" onclick="adSelectType(this)">✅ Success</span>
                        <span class="ntype ntype-warning" data-val="warning" onclick="adSelectType(this)">⚠️ Warning</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-arrows-rotate" style="margin-right:4px"></i>Update Request Status</label>
                    <div class="status-action-row" id="adStatusRow">
                        <button type="button" class="sact sact-progress" data-val="In Progress" onclick="adSelectStatus(this)">🔄 In Progress</button>
                        <button type="button" class="sact sact-fulfill" data-val="Fulfilled" onclick="adSelectStatus(this)">✅ Fulfilled</button>
                        <button type="button" class="sact sact-cannot" data-val="Cannot Fulfil" onclick="adSelectStatus(this)">❌ Cannot Fulfil</button>
                    </div>
                    <select class="form-select" id="adStatusSelect">
                        <option value="">— Keep Current Status —</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Fulfilled">Fulfilled</option>
                        <option value="Cannot Fulfil">Cannot Fulfil</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px">
                        <label class="form-label" style="margin:0">Quick Templates</label>
                        <button type="button" class="tpl-toggle-btn" id="adTplBtn" onclick="adToggleTpl()">
                            <i class="fas fa-bolt"></i> Templates <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="tpl-panel" id="adTplPanel">
                        <div class="tpl-section-head">✅ Fulfillment Updates</div>
                        <button type="button" class="tpl-chip" onclick="adApplyTpl('fulfilled')">
                            <div class="tpl-chip-head"><i class="fas fa-check-circle" style="color:var(--green);font-size:.65rem"></i> Request Fulfilled</div>
                            <div class="tpl-chip-preview">Great news! We've sourced your requested material…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="adApplyTpl('in_progress')">
                            <div class="tpl-chip-head"><i class="fas fa-spinner" style="color:var(--blue);font-size:.65rem"></i> Now In Progress</div>
                            <div class="tpl-chip-preview">Your request is now being actively worked on…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="adApplyTpl('partial')">
                            <div class="tpl-chip-head"><i class="fas fa-circle-half-stroke" style="color:var(--amber);font-size:.65rem"></i> Partially Found</div>
                            <div class="tpl-chip-preview">We've found part of what you requested…</div>
                        </button>
                        <div class="tpl-section-head">❌ Unable to Fulfil</div>
                        <button type="button" class="tpl-chip" onclick="adApplyTpl('cannot_unavailable')">
                            <div class="tpl-chip-head"><i class="fas fa-circle-xmark" style="color:var(--red);font-size:.65rem"></i> Not Available</div>
                            <div class="tpl-chip-preview">Unfortunately we were unable to source this material…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="adApplyTpl('cannot_copyright')">
                            <div class="tpl-chip-head"><i class="fas fa-copyright" style="color:var(--red);font-size:.65rem"></i> Copyright Issue</div>
                            <div class="tpl-chip-preview">This material cannot be shared due to copyright restrictions…</div>
                        </button>
                        <div class="tpl-section-head">ℹ️ Information Needed</div>
                        <button type="button" class="tpl-chip" onclick="adApplyTpl('need_more_info')">
                            <div class="tpl-chip-head"><i class="fas fa-question" style="color:var(--blue);font-size:.65rem"></i> Need More Details</div>
                            <div class="tpl-chip-preview">To process your request we need additional information…</div>
                        </button>
                        <button type="button" class="tpl-chip" onclick="adApplyTpl('already_exists')">
                            <div class="tpl-chip-head"><i class="fas fa-magnifying-glass" style="color:var(--teal);font-size:.65rem"></i> Already in Library</div>
                            <div class="tpl-chip-preview">This material may already be available on ScholarSwap…</div>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notification Title</label>
                    <input type="text" class="form-input" id="adMsgTitle" placeholder="e.g. Update on your material request" maxlength="150">
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea class="form-textarea" id="adMsgBody" placeholder="Write your message to the student…" maxlength="1000"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-lock" style="margin-right:4px;font-size:.62rem"></i>Admin Note <span style="font-weight:400;text-transform:none;letter-spacing:0">(internal — shown to student in tracking)</span></label>
                    <textarea class="form-textarea" id="adAdminNote" placeholder="Optional note visible to student when they track their request…" maxlength="500" style="min-height:70px"></textarea>
                </div>

                <div class="form-group">
                    <div class="res-link-box">
                        <div class="res-link-header">
                            <div class="res-link-header-left">
                                <i class="fas fa-link"></i>
                                Attach a Resource Link
                                <span style="font-weight:400;font-size:.67rem;color:var(--text3)">(optional — sent in notification)</span>
                            </div>
                            <button type="button" class="res-link-toggle" id="resLinkToggle" onclick="toggleResLink()">
                                <i class="fas fa-plus" id="resLinkToggleIco"></i> Add Link
                            </button>
                        </div>
                        <div class="res-link-body" id="resLinkBody">
                            <div>
                                <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:5px">Quick Sources</div>
                                <div class="res-quick-chips">
                                    <button type="button" class="res-qchip" data-base="https://scholar.google.com/scholar?q=" onclick="resSetSource(this)"><i class="fas fa-graduation-cap" style="font-size:.6rem"></i> Google Scholar</button>
                                    <button type="button" class="res-qchip" data-base="https://archive.org/search?query=" onclick="resSetSource(this)"><i class="fas fa-archive" style="font-size:.6rem"></i> Archive.org</button>
                                    <button type="button" class="res-qchip" data-base="https://www.pdfdrive.com/search?q=" onclick="resSetSource(this)"><i class="fas fa-file-pdf" style="font-size:.6rem"></i> PDF Drive</button>
                                    <button type="button" class="res-qchip" data-base="https://libgen.is/search.php?req=" onclick="resSetSource(this)"><i class="fas fa-book-open" style="font-size:.6rem"></i> LibGen</button>
                                    <button type="button" class="res-qchip" data-base="https://www.google.com/search?q=" onclick="resSetSource(this)"><i class="fab fa-google" style="font-size:.6rem"></i> Google Search</button>
                                    <button type="button" class="res-qchip" data-base="" onclick="resSetSource(this)"><i class="fas fa-pen-to-square" style="font-size:.6rem"></i> Custom URL</button>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:5px">URL</div>
                                <div class="res-link-input-row">
                                    <input type="url" class="res-link-input" id="adResUrl" placeholder="Paste the full URL here…" oninput="resUrlChanged()" onpaste="setTimeout(resUrlChanged,50)">
                                    <button type="button" class="res-link-clear" onclick="resClearLink()" title="Clear link"><i class="fas fa-xmark"></i></button>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:5px">Button Label <span style="font-weight:400;text-transform:none">(what the student sees)</span></div>
                                <input type="text" class="res-link-label-inp" id="adResLabel" placeholder="e.g. View on Google Scholar, Download PDF…" maxlength="80" oninput="resUrlChanged()">
                            </div>
                            <div class="res-link-preview" id="resLinkPreview">
                                <i class="fas fa-link"></i>
                                <div>
                                    <div style="font-size:.64rem;font-weight:700;margin-bottom:2px;opacity:.7">Preview — will appear in notification as:</div>
                                    <div id="resLinkPreviewText">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:10px;background:var(--bg);padding:10px 12px;border-radius:9px;border:1px solid var(--border)">
                    <input type="checkbox" id="adSendNotif" checked style="width:15px;height:15px;cursor:pointer;accent-color:var(--blue)">
                    <label for="adSendNotif" style="font-size:.8rem;font-weight:600;color:var(--text);cursor:pointer">
                        <i class="fas fa-bell" style="color:var(--blue);margin-right:5px"></i>
                        Send in-app notification to student
                    </label>
                </div>
            </div>

            <div class="drawer-footer">
                <button class="btn-send" id="adSendBtn" onclick="submitAction()">
                    <i class="fas fa-paper-plane"></i> Send & Update
                </button>
            </div>
        </div>
    </div>

    <script>
        var _cur = {};
        var _mode = 'message';
        var _notifType = 'admin_message';
        var _newStatus = '';
        var _resSource = '';

        var TERMINAL = ['Fulfilled', 'Cannot Fulfil', 'Cancelled'];

        function filterTable() {
            var status = document.getElementById('statusFilter').value;
            var priority = document.getElementById('priorityFilter').value;
            var type = document.getElementById('typeFilter').value;
            var q = document.getElementById('searchInput').value.toLowerCase().trim();
            document.querySelectorAll('.req-row').forEach(function(row) {
                var show = true;
                if (status && row.dataset.status !== status) show = false;
                if (priority && row.dataset.priority !== priority) show = false;
                if (type && !row.dataset.type.includes(type)) show = false;
                if (q && !row.dataset.search.includes(q)) show = false;
                row.classList.toggle('filtered-out', !show);
            });
        }

        var TYPE_ICONS = {
            'Textbook': '📚',
            'Lecture Notes': '📝',
            'Past Papers': '📋',
            'Other': '📦'
        };
        var TYPE_CLS = {
            'Textbook': 'bdg-textbook',
            'Lecture Notes': 'bdg-notes',
            'Past Papers': 'bdg-papers',
            'Other': 'bdg-other'
        };
        var STATUS_CLS = {
            'Pending': 'bdg-pending',
            'In Progress': 'bdg-inprogress',
            'Fulfilled': 'bdg-fulfilled',
            'Cannot Fulfil': 'bdg-cannot',
            'Cancelled': 'bdg-cancelled'
        };
        var STATUS_ICO = {
            'Pending': '🕐',
            'In Progress': '🔄',
            'Fulfilled': '✅',
            'Cannot Fulfil': '❌',
            'Cancelled': '🚫'
        };
        var PRI_CLS = {
            'High': 'bdg-high',
            'Medium': 'bdg-medium',
            'Low': 'bdg-low'
        };

        function set(id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = val || '—';
        }

        function hide(id, cond) {
            var el = document.getElementById(id);
            if (el) el.style.display = cond ? 'none' : '';
        }

        function openDetail(data) {
            _cur = data;

            document.getElementById('mvIcon').textContent = TYPE_ICONS[data.material_type] || '📦';
            set('mvTitle', data.title);

            var tb = document.getElementById('mvTypeBdg');
            tb.textContent = data.material_type;
            tb.className = 'bdg ' + (TYPE_CLS[data.material_type] || 'bdg-other');

            var sb = document.getElementById('mvStatusBdg');
            sb.textContent = (STATUS_ICO[data.status] || '') + ' ' + data.status;
            sb.className = 'bdg ' + (STATUS_CLS[data.status] || 'bdg-cancelled');

            var pb = document.getElementById('mvPriBdg');
            pb.textContent = data.priority;
            pb.className = 'bdg ' + (PRI_CLS[data.priority] || 'bdg-low');

            set('mvStudent', data.display_name + ' (@' + data.username + ')');
            set('mvDate', data.created_at ? new Date(data.created_at).toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }) : '—');
            set('mvTrackingNum', data.tracking_number || '—');
            set('mvRefCode', data.ref_code || '—');
            set('mvAuthor', data.author || '—');
            set('mvSubject', data.subject_code || '—');
            set('mvEdition', data.edition || '—');
            set('mvUniversity', data.university || '—');
            set('mvExamYear', data.exam_year || '—');
            set('mvLecturer', data.lecturer || '—');
            set('mvUnit', data.unit_needed || '—');
            set('mvNoteType', data.note_type || '—');
            set('mvDescription', data.description || 'No additional context provided.');

            hide('mvEditionItem', !data.edition);
            hide('mvUnivItem', !data.university);
            hide('mvExamItem', !data.exam_year);
            hide('mvLecturerItem', !data.lecturer);
            hide('mvUnitItem', !data.unit_needed);
            hide('mvNoteTypeItem', !data.note_type);

            set('mvStuName', data.display_name);
            set('mvStuUsername', '@' + data.username);
            set('mvStuEmail', data.email || '—');
            set('mvStuRoll', data.student_roll || '—');

            var adminNoteSection = document.getElementById('mvAdminNoteSection');
            if (data.admin_note) {
                set('mvAdminNote', data.admin_note);
                adminNoteSection.style.display = '';
            } else {
                adminNoteSection.style.display = 'none';
            }

            var isTerminal = TERMINAL.includes(data.status);

            /* Hide In Progress / Upload / Cannot Fulfil footer buttons for terminal statuses */
            document.getElementById('mvFooter').querySelectorAll('.btn-progress,.btn-cannot').forEach(function(b) {
                b.style.display = isTerminal ? 'none' : '';
            });

            /* Upload button: hide for terminal statuses, also hide In Progress if already In Progress */
            var mvUploadBtn = document.getElementById('mvUploadBtn');
            if (mvUploadBtn) mvUploadBtn.style.display = isTerminal ? 'none' : '';

            if (data.status === 'In Progress') {
                var pb2 = document.querySelector('#mvFooter .btn-progress');
                if (pb2) pb2.style.display = 'none';
            }

            document.getElementById('detailOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('detailOverlay').classList.remove('open');
            document.body.style.overflow = '';
        }
        document.getElementById('detailOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        function mvQuickAction(type) {
            closeModal();
            setTimeout(function() {
                if (type === 'message') openActionDrawer(_cur, 'message');
                else if (type === 'notify') openActionDrawer(_cur, 'notify');
                else if (type === 'upload') {
                    var uploadType = 'note';
                    if (_cur.material_type && (_cur.material_type.includes('Textbook') || _cur.material_type.includes('Book'))) uploadType = 'book';
                    window.location.href = 'upload_material.php?req=' + _cur.request_id + '&type=' + uploadType;
                } else if (type === 'progress') quickStatus(_cur.request_id, 'In Progress', _cur.title);
                else if (type === 'cannot') quickStatus(_cur.request_id, 'Cannot Fulfil', _cur.title);
            }, 200);
        }

        var TEMPLATES = {
            fulfilled: {
                type: 'success',
                title: 'Your Material Request Has Been Fulfilled! 🎉',
                msg: 'Great news! We have successfully sourced the material you requested. You can now find it in the ScholarSwap library. Simply search for the title or check the link below. Thank you for using our request service — happy studying!'
            },
            in_progress: {
                type: 'info',
                title: 'Your Request is Now In Progress',
                msg: 'We wanted to let you know that your material request is now being actively worked on by our library team. We are searching our sources and community database. You will receive another update once we have located the material or have further information for you.'
            },
            partial: {
                type: 'info',
                title: 'Partial Match Found for Your Request',
                msg: 'We found material that partially matches your request. While it may not be exactly what you specified (e.g., a different edition or subset of chapters), it could still be useful. Please check the library and let us know if you need further assistance.'
            },
            cannot_unavailable: {
                type: 'warning',
                title: 'Unable to Fulfil Your Material Request',
                msg: 'Unfortunately, after an extensive search our team was unable to source the material you requested at this time. This may be because it is out of print, extremely rare, or not yet available digitally. We apologize for the inconvenience and encourage you to try other sources. Your request has been marked accordingly.'
            },
            cannot_copyright: {
                type: 'warning',
                title: 'Request Cannot Be Fulfilled — Copyright Restriction',
                msg: 'We were unable to fulfil your request because the material is protected by copyright and cannot be shared on ScholarSwap without proper licensing. We recommend purchasing this material through official channels or accessing it through your institution\'s library subscription.'
            },
            need_more_info: {
                type: 'admin_message',
                title: 'Additional Information Needed for Your Request',
                msg: 'Thank you for submitting your material request. To help us locate the exact resource you need, could you provide additional details such as the full title, author name, edition, ISBN, or any other identifying information? The more specific you are, the better chance we have of finding it quickly.'
            },
            already_exists: {
                type: 'info',
                title: 'This Material May Already Be Available',
                msg: 'Good news — we checked our library and material matching your request may already be available on ScholarSwap! Please search the library using the title or subject keywords. If you have trouble finding it or if what is available does not meet your needs, please let us know and we will continue searching.'
            }
        };

        function openActionDrawer(data, mode) {
            _cur = data;
            _mode = mode;
            _newStatus = '';
            _notifType = 'admin_message';

            document.getElementById('adReqTitle').textContent = data.title;
            document.getElementById('adReqType').textContent = data.material_type;
            document.getElementById('adReqStudent').textContent = data.display_name + ' (@' + data.username + ')';
            document.getElementById('adReqStatus').textContent = data.status;

            document.querySelectorAll('.sact').forEach(function(s) {
                s.classList.remove('sel');
            });
            document.getElementById('adStatusSelect').value = '';
            document.getElementById('adMsgTitle').value = '';
            document.getElementById('adMsgBody').value = '';
            document.getElementById('adAdminNote').value = data.admin_note || '';

            resClearLink();
            document.getElementById('resLinkBody').classList.remove('open');
            document.getElementById('resLinkToggle').classList.remove('active');
            document.getElementById('resLinkToggleIco').className = 'fas fa-plus';
            document.querySelectorAll('.res-qchip').forEach(function(c) {
                c.classList.remove('active');
            });
            _resSource = '';

            document.querySelectorAll('#adNotifTypes .ntype').forEach(function(n) {
                n.classList.remove('sel', 'ntype-admin_msg', 'ntype-info', 'ntype-success', 'ntype-warning');
            });
            var defType = document.querySelector('#adNotifTypes .ntype[data-val="admin_message"]');
            if (defType) defType.classList.add('sel', 'ntype-admin_msg');

            if (mode === 'notify') {
                document.getElementById('adTitle').textContent = 'Send Notification';
                document.getElementById('adSub').textContent = 'Send a notification to the student';
                document.getElementById('adIcon').style.background = 'var(--purple-s)';
                document.getElementById('adIcon').style.color = 'var(--purple)';
                document.getElementById('adIcon').innerHTML = '<i class="fas fa-bell"></i>';
                document.getElementById('adNotifTypeGroup').style.display = '';
            } else {
                document.getElementById('adTitle').textContent = 'Send Message';
                document.getElementById('adSub').textContent = 'Communicate with the student';
                document.getElementById('adIcon').style.background = 'var(--blue-s)';
                document.getElementById('adIcon').style.color = 'var(--blue)';
                document.getElementById('adIcon').innerHTML = '<i class="fas fa-envelope"></i>';
                document.getElementById('adNotifTypeGroup').style.display = 'none';
            }

            var isTerminal = TERMINAL.includes(data.status);
            document.getElementById('adStatusRow').style.display = isTerminal ? 'none' : '';
            document.getElementById('adStatusSelect').style.display = isTerminal ? 'none' : '';

            document.getElementById('adTplPanel').classList.remove('open');
            document.getElementById('adTplBtn').classList.remove('open');
            document.getElementById('actionDrawerOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function toggleResLink() {
            var body = document.getElementById('resLinkBody');
            var toggle = document.getElementById('resLinkToggle');
            var isOpen = body.classList.toggle('open');
            toggle.classList.toggle('active', isOpen);
            if (isOpen) {
                toggle.innerHTML = '<i class="fas fa-minus"></i> Hide';
                var urlInp = document.getElementById('adResUrl');
                if (urlInp && !urlInp.value) setTimeout(function() {
                    urlInp.focus();
                }, 80);
            } else {
                toggle.innerHTML = '<i class="fas fa-plus" id="resLinkToggleIco"></i> Add Link';
            }
        }

        function resSetSource(chip) {
            document.querySelectorAll('.res-qchip').forEach(function(c) {
                c.classList.remove('active');
            });
            chip.classList.add('active');
            _resSource = chip.dataset.base;
            var titleVal = document.getElementById('adMsgTitle').value.trim() || (_cur.title || '');
            var urlInp = document.getElementById('adResUrl');
            if (_resSource && titleVal) {
                urlInp.value = _resSource + encodeURIComponent(titleVal);
                resUrlChanged();
            } else if (_resSource === '') {
                urlInp.value = '';
                urlInp.focus();
            }
            var labelInp = document.getElementById('adResLabel');
            if (!labelInp.value.trim()) labelInp.value = 'View on ' + chip.textContent.trim();
        }

        function resUrlChanged() {
            var url = (document.getElementById('adResUrl').value || '').trim();
            var label = (document.getElementById('adResLabel').value || '').trim() || 'View Resource';
            var preview = document.getElementById('resLinkPreview');
            var prevTxt = document.getElementById('resLinkPreviewText');
            if (url) {
                try {
                    var host = new URL(url).hostname.replace('www.', '');
                    prevTxt.textContent = '🔗 ' + label + '  →  ' + host;
                } catch (e) {
                    prevTxt.textContent = '🔗 ' + label + '  →  ' + url.substring(0, 60);
                }
                preview.classList.add('show');
            } else {
                preview.classList.remove('show');
            }
        }

        function resClearLink() {
            var urlInp = document.getElementById('adResUrl');
            var labelInp = document.getElementById('adResLabel');
            if (urlInp) urlInp.value = '';
            if (labelInp) labelInp.value = '';
            var preview = document.getElementById('resLinkPreview');
            if (preview) preview.classList.remove('show');
            document.querySelectorAll('.res-qchip').forEach(function(c) {
                c.classList.remove('active');
            });
            _resSource = '';
        }

        function closeDrawer() {
            document.getElementById('actionDrawerOverlay').classList.remove('open');
            document.body.style.overflow = '';
        }
        document.getElementById('actionDrawerOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeDrawer();
        });

        function adSelectType(el) {
            document.querySelectorAll('#adNotifTypes .ntype').forEach(function(n) {
                n.classList.remove('sel', 'ntype-admin_msg', 'ntype-info', 'ntype-success', 'ntype-warning');
            });
            el.classList.add('sel', 'ntype-' + el.dataset.val.replace('_message', '_msg'));
            _notifType = el.dataset.val;
        }

        function adSelectStatus(el) {
            document.querySelectorAll('.sact').forEach(function(s) {
                s.classList.remove('sel');
            });
            if (el.classList.contains('sel')) {
                _newStatus = '';
            } else {
                el.classList.add('sel');
                _newStatus = el.dataset.val;
                document.getElementById('adStatusSelect').value = _newStatus;
            }
        }

        document.getElementById('adStatusSelect').addEventListener('change', function() {
            _newStatus = this.value;
            document.querySelectorAll('.sact').forEach(function(s) {
                s.classList.remove('sel');
            });
            if (_newStatus) {
                var m = document.querySelector('.sact[data-val="' + _newStatus + '"]');
                if (m) m.classList.add('sel');
            }
        });

        function adToggleTpl() {
            var p = document.getElementById('adTplPanel'),
                b = document.getElementById('adTplBtn');
            p.classList.toggle('open');
            b.classList.toggle('open', p.classList.contains('open'));
        }

        function adApplyTpl(key) {
            var t = TEMPLATES[key];
            if (!t) return;
            document.getElementById('adMsgTitle').value = t.title;
            document.getElementById('adMsgBody').value = t.msg;
            document.querySelectorAll('#adNotifTypes .ntype').forEach(function(n) {
                n.classList.remove('sel', 'ntype-admin_msg', 'ntype-info', 'ntype-success', 'ntype-warning');
            });
            var te = document.querySelector('#adNotifTypes .ntype[data-val="' + t.type + '"]');
            if (te) te.classList.add('sel', 'ntype-' + t.type.replace('_message', '_msg'));
            _notifType = t.type;
            document.getElementById('adTplPanel').classList.remove('open');
            document.getElementById('adTplBtn').classList.remove('open');
            document.getElementById('adMsgTitle').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            document.getElementById('adMsgTitle').focus();
        }

        async function submitAction() {
            var title = document.getElementById('adMsgTitle').value.trim();
            var body = document.getElementById('adMsgBody').value.trim();
            var adminNote = document.getElementById('adAdminNote').value.trim();
            var resUrlEl = document.getElementById('adResUrl');
            var resLabelEl = document.getElementById('adResLabel');
            var resUrl = resUrlEl ? resUrlEl.value.trim() : '';
            var resLabel = resLabelEl ? resLabelEl.value.trim() : '';

            var linkPanelOpen = document.getElementById('resLinkBody').classList.contains('open');
            if (!linkPanelOpen || !resUrl.match(/^https?:\/\//i)) {
                resUrl = '';
                resLabel = '';
            }
            if (!resLabel) resLabel = 'View Resource';

            var newStatus = document.getElementById('adStatusSelect').value || _newStatus;
            var sendNotif = document.getElementById('adSendNotif').checked;

            if (!body && !newStatus && !adminNote) return sw('warning', 'Nothing to do', 'Please write a message, update the status, or add an admin note.');
            if (body && !title) return sw('warning', 'Missing Title', 'Please enter a notification title.');

            var rawUrl = (document.getElementById('adResUrl').value || '').trim();
            if (rawUrl && !document.getElementById('resLinkBody').classList.contains('open')) return sw('warning', 'Link Not Attached', 'You typed a URL but the "Attach a Resource Link" panel is closed. Click "Add Link" to open it, then submit again.');

            var btn = document.getElementById('adSendBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

            var fd = new FormData();
            fd.append('action', 'update_request');
            fd.append('request_id', _cur.request_id);
            fd.append('user_id', _cur.user_id);
            fd.append('new_status', newStatus);
            fd.append('admin_note', adminNote);
            fd.append('notif_type', _notifType);
            fd.append('notif_title', title);
            fd.append('message', body);
            fd.append('send_notif', sendNotif ? '1' : '0');
            fd.append('ref_code', _cur.ref_code || '');
            fd.append('tracking_num', _cur.tracking_number || '');
            fd.append('mat_title', _cur.title || '');
            fd.append('res_url', resUrl);
            fd.append('res_label', resLabel);

            try {
                var res = await fetch('auth/handle_request_admin.php', {
                    method: 'POST',
                    body: fd
                });
                var data = await res.json();
                if (data.success) {
                    closeDrawer();
                    Swal.fire({
                            icon: 'success',
                            title: 'Done!',
                            text: data.message || 'Request updated successfully.',
                            timer: 2500,
                            timerProgressBar: true,
                            showConfirmButton: false
                        })
                        .then(function() {
                            location.reload();
                        });
                } else {
                    sw('error', 'Failed', data.message || 'Something went wrong.');
                }
            } catch (e) {
                sw('error', 'Network Error', 'Could not reach the server.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send & Update';
        }

        function quickStatus(requestId, newStatus, title) {
            var icons = {
                'In Progress': '🔄',
                'Fulfilled': '✅',
                'Cannot Fulfil': '❌'
            };
            Swal.fire({
                title: icons[newStatus] + ' ' + newStatus + '?',
                html: '<p style="color:#64748b;font-size:.88rem;margin-bottom:6px">Update request:</p><p style="font-weight:700;color:#0f172a;font-size:.92rem">"' + esc(title) + '"</p>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, update it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: newStatus === 'Fulfilled' ? '#059669' : newStatus === 'In Progress' ? '#2563eb' : '#dc2626',
                cancelButtonColor: '#e2e8f0',
                reverseButtons: true,
                input: 'textarea',
                inputLabel: 'Admin Note (optional — shown to student in tracking)',
                inputPlaceholder: 'e.g. Found in university repository…',
                inputAttributes: {
                    maxlength: 500,
                    rows: 2
                }
            }).then(function(r) {
                if (!r.isConfirmed) return;
                Swal.fire({
                    title: 'Updating…',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });
                var fd = new FormData();
                fd.append('action', 'quick_status');
                fd.append('request_id', requestId);
                fd.append('new_status', newStatus);
                fd.append('admin_note', r.value || '');
                fetch('auth/handle_request_admin.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(d) {
                        if (d.success) Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(function() {
                            location.reload();
                        });
                        else sw('error', 'Failed', d.message);
                    }).catch(function() {
                        sw('error', 'Network Error', 'Could not reach the server.');
                    });
            });
        }

        function sw(icon, title, text) {
            return Swal.fire({
                icon: icon,
                title: title,
                text: text,
                iconColor: icon === 'error' ? '#ef4444' : icon === 'warning' ? '#f59e0b' : '#10b981'
            });
        }

        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDrawer();
                closeModal();
            }
        });

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
        if (sp) history.replaceState(null, '', 'material_requests.php');
    </script>

</body>

</html>