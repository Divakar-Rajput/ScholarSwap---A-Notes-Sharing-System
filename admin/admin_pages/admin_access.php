<?php
session_start();
require_once "config/connection.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$adminId   = (int)$_SESSION['admin_id'];
$meQ = $conn->prepare("SELECT first_name, last_name, role FROM admin_user WHERE admin_id=? LIMIT 1");
$meQ->execute([$adminId]);
$me = $meQ->fetch(PDO::FETCH_ASSOC);
$admin = $me; // alias expected by sidebar.php and adminheader.php
$myRole    = $me['role'] ?? 'admin';
$adminName = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?: 'Admin';
$isSuperAdmin = ($myRole === 'superadmin');

// ── Role config ─────────────────────────────────────────────────
$roles = [
    ['name' => 'superadmin', 'label' => 'Super Admin', 'icon' => 'fa-crown',          'color' => '#7c3aed', 'level' => 100, 'desc' => 'Full system access — manage roles, admins, all content & settings.'],
    ['name' => 'admin',     'label' => 'Admin',       'icon' => 'fa-shield-halved',  'color' => '#2563eb', 'level' => 50, 'desc' => 'Content & user management — approvals, reports, notifications.'],
    ['name' => 'moderator', 'label' => 'Moderator',   'icon' => 'fa-user-shield',    'color' => '#0d9488', 'level' => 25, 'desc' => 'Content review only — approve or reject pending content.'],
    ['name' => 'viewer',    'label' => 'Viewer',       'icon' => 'fa-eye',            'color' => '#94a3b8', 'level' => 10, 'desc' => 'Read-only access — dashboard & content lists, no actions.'],
];
$roleMap = array_column($roles, null, 'name');

function roleBadge(array $roleMap, string $role): string
{
    $r = $roleMap[$role] ?? null;
    if (!$r) return '<span class="bdg" style="background:#f1f5f9;color:#64748b">' . htmlspecialchars(ucfirst($role)) . '</span>';
    $hex = ltrim($r['color'], '#');
    $rgb = implode(',', array_map('hexdec', str_split($hex, 2)));
    return '<span class="bdg" style="background:rgba(' . $rgb . ',.13);color:' . htmlspecialchars($r['color']) . '">
        <i class="fas ' . htmlspecialchars($r['icon']) . '" style="font-size:.58rem"></i>
        ' . htmlspecialchars($r['label']) . '</span>';
}

// ── Pending registration requests ───────────────────────────────
$pendQ = $conn->prepare("
    SELECT admin_id, first_name, last_name, username, email, phone,
           role, institution, course, subjects, created_at, status
    FROM admin_user
    WHERE status = 'pending'
    ORDER BY created_at ASC
");
$pendQ->execute();
$pendingList = $pendQ->fetchAll(PDO::FETCH_ASSOC);

// ── All approved admins (for role management) ────────────────────
$allQ = $conn->prepare("
    SELECT admin_id, first_name, last_name, username, email, role,
           institution, created_at, status
    FROM admin_user
    WHERE status = 'approved'
    ORDER BY
        CASE role
            WHEN 'superadmin' THEN 0
            WHEN 'admin'      THEN 1
            WHEN 'moderator'  THEN 2
            ELSE 3
        END, first_name ASC
");
$allQ->execute();
$allAdmins = $allQ->fetchAll(PDO::FETCH_ASSOC);

// ── Recent decisions ─────────────────────────────────────────────
$recQ = $conn->prepare("
    SELECT admin_id, first_name, last_name, username, email, role,
           status, created_at
    FROM admin_user
    WHERE status IN ('approved','rejected')
    ORDER BY created_at DESC
    LIMIT 15
");
$recQ->execute();
$recentDecisions = $recQ->fetchAll(PDO::FETCH_ASSOC);

// ── Stats ────────────────────────────────────────────────────────
$stats = [
    'pending'    => count($pendingList),
    'total'      => count($allAdmins),
    'superadmin' => count(array_filter($allAdmins, fn($a) => $a['role'] === 'superadmin')),
    'moderator'  => count(array_filter($allAdmins, fn($a) => $a['role'] === 'moderator')),
];

function ago(string $dt): string
{
    $d = time() - strtotime($dt);
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return floor($d / 60)   . 'm ago';
    if ($d < 86400)  return floor($d / 3600) . 'h ago';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    return date('d M Y', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Access Management | ScholarSwap</title>
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
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text)
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
            flex: 1;
            min-width: 130px
        }

        .spi {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            flex-shrink: 0
        }

        .spv {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1
        }

        .spl {
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
            box-shadow: var(--sh);
            flex-wrap: wrap
        }

        .mtab {
            padding: 9px 18px;
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
            gap: 7px;
            white-space: nowrap
        }

        .mtab:hover:not(.on) {
            background: var(--bg)
        }

        .mtab.on {
            background: var(--blue);
            color: #fff;
            box-shadow: 0 3px 10px rgba(37, 99, 235, .3)
        }

        .cnt {
            font-size: .6rem;
            padding: 1px 6px;
            border-radius: 99px;
            font-weight: 700
        }

        .mtab.on .cnt {
            background: rgba(255, 255, 255, .25);
            color: #fff
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

        .filter-select,
        .filter-search {
            padding: 6px 10px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: .78rem;
            font-family: inherit;
            color: var(--text);
            background: var(--surface);
            outline: none;
            transition: border-color .15s;
            cursor: pointer
        }

        .filter-select:focus,
        .filter-search:focus {
            border-color: var(--blue)
        }

        .filter-search {
            flex: 1;
            min-width: 180px;
            cursor: text
        }

        /* ── Table ── */
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
            font-size: .62rem;
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

        .filtered-out {
            display: none !important
        }

        /* ── Badges ── */
        .bdg {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 9px;
            border-radius: 99px;
            font-size: .63rem;
            font-weight: 700;
            white-space: nowrap
        }

        .bdg-pending {
            background: var(--amber-s);
            color: #92400e
        }

        .bdg-approved {
            background: var(--green-s);
            color: #065f46
        }

        .bdg-rejected {
            background: var(--red-s);
            color: #991b1b
        }

        /* ── Admin avatar cell ── */
        .av-cell {
            display: flex;
            align-items: center;
            gap: 9px
        }

        .av {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 700;
            color: #fff;
            overflow: hidden
        }

        .av img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%
        }

        .av-name {
            font-size: .83rem;
            font-weight: 600;
            color: var(--text)
        }

        .av-sub {
            font-size: .68rem;
            color: var(--text3);
            margin-top: 1px
        }

        /* ── Action buttons ── */
        .act {
            display: flex;
            gap: 5px;
            flex-wrap: wrap
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

        .btn-approve {
            background: var(--green-s);
            color: var(--green)
        }

        .btn-approve:hover {
            background: var(--green);
            color: #fff
        }

        .btn-reject {
            background: var(--red-s);
            color: var(--red)
        }

        .btn-reject:hover {
            background: var(--red);
            color: #fff
        }

        .btn-role {
            background: var(--purple-s);
            color: var(--purple)
        }

        .btn-role:hover {
            background: var(--purple);
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

        .btn-view {
            background: var(--bg);
            color: var(--text2);
            border: 1px solid var(--border)
        }

        .btn-view:hover {
            background: var(--text);
            color: #fff
        }

        .btn-demote {
            background: var(--amber-s);
            color: #92400e
        }

        .btn-demote:hover {
            background: var(--amber);
            color: #fff
        }

        /* ── Empty ── */
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

        /* ══════════════════════════════════════════════════════
           MODAL
        ══════════════════════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9000;
            background: rgba(15, 23, 42, .6);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            visibility: hidden;
            transition: opacity .24s, visibility .24s
        }

        .modal-overlay.open {
            opacity: 1;
            visibility: visible
        }

        .modal-box {
            background: var(--surface);
            border-radius: 20px;
            width: min(600px, 96vw);
            max-height: 90vh;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: scale(.93) translateY(20px);
            opacity: 0;
            transition: transform .28s cubic-bezier(.34, 1.56, .64, 1), opacity .22s
        }

        .modal-overlay.open .modal-box {
            transform: scale(1) translateY(0);
            opacity: 1
        }

        .modal-box::before {
            content: '';
            display: block;
            height: 4px;
            flex-shrink: 0;
            background: linear-gradient(90deg, #7c3aed, #2563eb, #0d9488)
        }

        .modal-head {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 13px;
            flex-shrink: 0
        }

        .modal-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            flex-shrink: 0
        }

        .modal-head h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text);
            margin: 0
        }

        .modal-sub {
            font-size: .72rem;
            color: var(--text3);
            margin-top: 2px
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            background: var(--bg);
            color: var(--text2);
            font-size: .9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            transition: all .14s;
            flex-shrink: 0
        }

        .modal-close:hover {
            background: var(--red-s);
            color: var(--red)
        }

        .modal-body {
            padding: 20px 22px;
            overflow-y: auto;
            flex: 1;
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

        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            background: #fafafa;
            display: flex;
            gap: 9px;
            justify-content: flex-end;
            flex-shrink: 0
        }

        /* Info grid inside modal */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0
        }

        .ig {
            padding: 10px 0;
            border-bottom: 1px solid var(--border)
        }

        .ig.full {
            grid-column: 1/-1
        }

        .ig:last-child {
            border-bottom: none
        }

        .ig-lbl {
            font-size: .63rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text3);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px
        }

        .ig-val {
            font-size: .86rem;
            color: var(--text);
            font-weight: 500;
            word-break: break-word
        }

        .ig-val.muted {
            color: var(--text3);
            font-weight: 400
        }

        /* Role picker cards */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 6px
        }

        .role-card {
            padding: 12px 14px;
            border: 2px solid var(--border);
            border-radius: 11px;
            cursor: pointer;
            transition: all .15s;
            background: var(--bg);
            display: flex;
            align-items: flex-start;
            gap: 10px
        }

        .role-card:hover {
            border-color: var(--border2);
            background: var(--surface)
        }

        .role-card.sel {
            border-color: currentColor;
            background: var(--surface)
        }

        .role-card-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            flex-shrink: 0
        }

        .role-card-name {
            font-size: .82rem;
            font-weight: 700;
            color: var(--text)
        }

        .role-card-desc {
            font-size: .7rem;
            color: var(--text3);
            margin-top: 2px;
            line-height: 1.5
        }

        /* Form elements */
        .fg {
            margin-bottom: 14px
        }

        .fl {
            font-size: .72rem;
            font-weight: 700;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 5px;
            display: block
        }

        .fi,
        .fs,
        .ft {
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

        .fi:focus,
        .fs:focus,
        .ft:focus {
            border-color: var(--blue)
        }

        .ft {
            resize: vertical;
            min-height: 100px;
            line-height: 1.6
        }

        /* Notif type pills */
        .ntype-row {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px
        }

        .ntype {
            padding: 5px 12px;
            border-radius: 7px;
            border: 1.5px solid var(--border);
            background: var(--bg);
            font-size: .72rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--text2);
            transition: all .14s
        }

        .ntype.sel.nt-admin_message {
            background: var(--purple-s);
            color: var(--purple);
            border-color: var(--purple)
        }

        .ntype.sel.nt-warning {
            background: var(--red-s);
            color: var(--red);
            border-color: var(--red)
        }

        .ntype.sel.nt-info {
            background: var(--blue-s);
            color: var(--blue);
            border-color: var(--blue)
        }

        .ntype.sel.nt-promo {
            background: var(--green-s);
            color: var(--green);
            border-color: var(--green)
        }

        /* Action buttons in modal footer */
        .btn-cancel {
            padding: 9px 18px;
            border-radius: 9px;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--text2);
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            font-family: inherit
        }

        .btn-cancel:hover {
            background: var(--text);
            color: #fff;
            border-color: var(--text)
        }

        .btn-primary {
            padding: 9px 20px;
            border-radius: 9px;
            border: none;
            background: linear-gradient(135deg, var(--blue), var(--blue-d));
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: all .18s;
            font-family: inherit;
            box-shadow: 0 4px 12px rgba(37, 99, 235, .22)
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(37, 99, 235, .32)
        }

        .btn-primary:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none
        }

        .btn-primary.green {
            background: linear-gradient(135deg, var(--green), #047857);
            box-shadow: 0 4px 12px rgba(5, 150, 105, .22)
        }

        .btn-primary.green:hover {
            box-shadow: 0 8px 18px rgba(5, 150, 105, .32)
        }

        .btn-primary.red {
            background: linear-gradient(135deg, var(--red), #b91c1c);
            box-shadow: 0 4px 12px rgba(220, 38, 38, .22)
        }

        /* Approve/reject banner inside modal */
        .decision-bar {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: .82rem;
            font-weight: 600;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .decision-bar.approve {
            background: var(--green-s);
            color: #065f46;
            border: 1px solid #6ee7b7
        }

        .decision-bar.reject {
            background: var(--red-s);
            color: #991b1b;
            border: 1px solid #fca5a5
        }

        .decision-bar.role {
            background: var(--purple-s);
            color: var(--purple);
            border: 1px solid #c4b5fd
        }

        .decision-bar.msg {
            background: var(--blue-s);
            color: var(--blue);
            border: 1px solid #93c5fd
        }

        /* Access level indicator */
        .level-bar {
            height: 5px;
            border-radius: 99px;
            background: var(--border);
            overflow: hidden;
            margin-top: 6px
        }

        .level-fill {
            height: 100%;
            border-radius: 99px;
            transition: width .6s ease
        }

        @media(max-width:700px) {

            .info-grid,
            .role-grid {
                grid-template-columns: 1fr
            }

            .act {
                flex-wrap: wrap
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
                    <h1><i class="fas fa-user-shield" style="color:var(--purple);margin-right:8px"></i>Admin Access Management</h1>
                    <p>Review registration requests, manage admin roles, and send messages to your team.</p>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <a href="admin_signup.php" target="_blank"
                        style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;background:linear-gradient(135deg,var(--green),#047857);color:#fff;font-size:.82rem;font-weight:700;text-decoration:none;white-space:nowrap;box-shadow:0 4px 12px rgba(5,150,105,.25);transition:all .18s"
                        onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 8px 18px rgba(5,150,105,.35)'"
                        onmouseout="this.style.transform='';this.style.boxShadow='0 4px 12px rgba(5,150,105,.25)'">
                        <i class="fas fa-user-plus"></i>
                        Register User
                        <i class="fas fa-arrow-up-right-from-square" style="font-size:.62rem;opacity:.75"></i>
                    </a>
                    <?php if (!$isSuperAdmin): ?>
                        <div style="background:var(--amber-s);border:1px solid #fcd34d;border-radius:10px;padding:10px 14px;font-size:.78rem;color:#92400e;display:flex;align-items:center;gap:7px">
                            <i class="fas fa-lock" style="font-size:.75rem"></i>
                            Read-only — only Super Admins can approve requests or change roles
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Stat strip ── -->
            <div class="stat-strip">
                <div class="stat-pill">
                    <div class="spi" style="background:var(--amber-s);color:var(--amber)"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="spv"><?php echo $stats['pending']; ?></div>
                        <div class="spl">Pending Requests</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="spi" style="background:var(--green-s);color:var(--green)"><i class="fas fa-users-gear"></i></div>
                    <div>
                        <div class="spv"><?php echo $stats['total']; ?></div>
                        <div class="spl">Active Admins</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="spi" style="background:var(--purple-s);color:var(--purple)"><i class="fas fa-crown"></i></div>
                    <div>
                        <div class="spv"><?php echo $stats['superadmin']; ?></div>
                        <div class="spl">Super Admins</div>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="spi" style="background:var(--teal-s);color:var(--teal)"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <div class="spv"><?php echo $stats['moderator']; ?></div>
                        <div class="spl">Moderators</div>
                    </div>
                </div>
            </div>

            <!-- ── Tabs ── -->
            <div class="main-tabs">
                <button class="mtab on" onclick="switchTab('pending',this)">
                    <i class="fas fa-hourglass-half"></i> Pending Requests
                    <?php if ($stats['pending']): ?><span class="cnt"><?php echo $stats['pending']; ?></span><?php endif; ?>
                </button>
                <button class="mtab" onclick="switchTab('admins',this)">
                    <i class="fas fa-users-gear"></i> Active Admins
                </button>
                <button class="mtab" onclick="switchTab('history',this)">
                    <i class="fas fa-clock-rotate-left"></i> Decision History
                </button>
            </div>

            <!-- ══════════════ TAB: PENDING ══════════════ -->
            <div id="tab-pending">
                <div class="panel">
                    <div class="ph">
                        <div>
                            <div class="pt"><i class="fas fa-hourglass-half" style="color:var(--amber);margin-right:6px"></i>Pending Registration Requests</div>
                            <div class="ph-sub">New admins waiting for approval — only Super Admins can approve or reject</div>
                        </div>
                        <?php if ($stats['pending'] > 0): ?>
                            <span style="background:var(--red-s);color:var(--red);border:1px solid #fca5a5;border-radius:99px;padding:3px 12px;font-size:.72rem;font-weight:700;animation:pulse 2s ease-in-out infinite">
                                <i class="fas fa-circle-dot" style="font-size:.5rem"></i> <?php echo $stats['pending']; ?> awaiting
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($pendingList)): ?>
                        <div class="empty"><i class="fas fa-inbox"></i>No pending requests. All clear!</div>
                    <?php else: ?>
                        <div class="tw">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Applicant</th>
                                        <th>Requested Role</th>
                                        <th>Institution</th>
                                        <th>Subjects</th>
                                        <th>Applied</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingList as $k => $a):
                                        $initials = strtoupper(($a['first_name'][0] ?? '') . ($a['last_name'][0] ?? ''));
                                        $fullName = trim($a['first_name'] . ' ' . $a['last_name']);
                                        $jsA = htmlspecialchars(json_encode($a), ENT_QUOTES);
                                    ?>
                                        <tr>
                                            <td style="color:var(--text3);font-size:.72rem"><?php echo $k + 1; ?></td>
                                            <td>
                                                <div class="av-cell">
                                                    <div class="av"><?php echo $initials; ?></div>
                                                    <div>
                                                        <div class="av-name"><?php echo htmlspecialchars($fullName); ?></div>
                                                        <div class="av-sub">@<?php echo htmlspecialchars($a['username']); ?> · <?php echo htmlspecialchars($a['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo roleBadge($roleMap, $a['role']); ?></td>
                                            <td style="font-size:.8rem"><?php echo htmlspecialchars($a['institution'] ?? '—'); ?></td>
                                            <td style="font-size:.78rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo htmlspecialchars($a['subjects'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($a['subjects'] ?? '—'); ?>
                                            </td>
                                            <td style="font-size:.77rem;color:var(--text3);white-space:nowrap"><?php echo ago($a['created_at']); ?></td>
                                            <td>
                                                <div class="act">
                                                    <button class="btn btn-view" onclick="openDetailModal(<?php echo $jsA; ?>,'view')">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <?php if ($isSuperAdmin): ?>
                                                        <button class="btn btn-approve" onclick="openDetailModal(<?php echo $jsA; ?>,'approve')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-reject" onclick="openDetailModal(<?php echo $jsA; ?>,'reject')">
                                                            <i class="fas fa-xmark"></i> Reject
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

            <!-- ══════════════ TAB: ACTIVE ADMINS ══════════════ -->
            <div id="tab-admins" style="display:none">
                <div class="panel">
                    <div class="ph">
                        <div>
                            <div class="pt"><i class="fas fa-users-gear" style="color:var(--blue);margin-right:6px"></i>Active Admin Team</div>
                            <div class="ph-sub">Manage roles, send messages, promote or demote admins</div>
                        </div>
                    </div>
                    <div class="filter-bar">
                        <select class="filter-select" id="roleFilter" onchange="filterAdmins()">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['name']; ?>"><?php echo $r['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input class="filter-search" type="text" id="adminSearch" placeholder="Search by name, email or username…" oninput="filterAdmins()">
                    </div>

                    <?php if (empty($allAdmins)): ?>
                        <div class="empty"><i class="fas fa-users"></i>No active admins found.</div>
                    <?php else: ?>
                        <div class="tw">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Admin</th>
                                        <th>Role</th>
                                        <th>Institution</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allAdmins as $k => $a):
                                        $initials = strtoupper(($a['first_name'][0] ?? '') . ($a['last_name'][0] ?? ''));
                                        $fullName = trim($a['first_name'] . ' ' . $a['last_name']);
                                        $isSelf = ($a['admin_id'] == $adminId);
                                        $jsA = htmlspecialchars(json_encode($a), ENT_QUOTES);
                                    ?>
                                        <tr class="admin-row"
                                            data-role="<?php echo htmlspecialchars($a['role']); ?>"
                                            data-search="<?php echo strtolower(htmlspecialchars($fullName . ' ' . $a['email'] . ' ' . $a['username'])); ?>">
                                            <td style="color:var(--text3);font-size:.72rem"><?php echo $k + 1; ?></td>
                                            <td>
                                                <div class="av-cell">
                                                    <div class="av" style="background:<?php echo $roleMap[$a['role']]['color'] ?? '#6366f1'; ?>"><?php echo $initials; ?></div>
                                                    <div>
                                                        <div class="av-name">
                                                            <?php echo htmlspecialchars($fullName); ?>
                                                            <?php if ($isSelf): ?><span style="font-size:.6rem;background:var(--blue-s);color:var(--blue);padding:1px 6px;border-radius:99px;font-weight:700;margin-left:5px">You</span><?php endif; ?>
                                                        </div>
                                                        <div class="av-sub">@<?php echo htmlspecialchars($a['username']); ?> · <?php echo htmlspecialchars($a['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo roleBadge($roleMap, $a['role']); ?></td>
                                            <td style="font-size:.8rem;color:var(--text2)"><?php echo htmlspecialchars($a['institution'] ?? '—'); ?></td>
                                            <td style="font-size:.77rem;color:var(--text3);white-space:nowrap"><?php echo date('d M Y', strtotime($a['created_at'])); ?></td>
                                            <td>
                                                <div class="act">
                                                    <button class="btn btn-msg" onclick="openMessageModal(<?php echo $jsA; ?>)">
                                                        <i class="fas fa-envelope"></i> Message
                                                    </button>
                                                    <?php if ($isSuperAdmin && !$isSelf): ?>
                                                        <button class="btn btn-role" onclick="openRoleModal(<?php echo $jsA; ?>)">
                                                            <i class="fas fa-user-pen"></i> Change Role
                                                        </button>
                                                        <?php if ($a['role'] !== 'superadmin'): ?>
                                                            <button class="btn btn-reject" onclick="revokeAccess(<?php echo (int)$a['admin_id']; ?>, '<?php echo htmlspecialchars(addslashes($fullName)); ?>')">
                                                                <i class="fas fa-user-slash"></i> Revoke
                                                            </button>
                                                        <?php endif; ?>
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

            <!-- ══════════════ TAB: HISTORY ══════════════ -->
            <div id="tab-history" style="display:none">
                <div class="panel">
                    <div class="ph">
                        <div>
                            <div class="pt"><i class="fas fa-clock-rotate-left" style="color:var(--text3);margin-right:6px"></i>Recent Decisions</div>
                            <div class="ph-sub">Last 15 approved and rejected registrations</div>
                        </div>
                    </div>

                    <?php if (empty($recentDecisions)): ?>
                        <div class="empty"><i class="fas fa-history"></i>No decisions yet.</div>
                    <?php else: ?>
                        <div class="tw">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Admin</th>
                                        <th>Role</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentDecisions as $k => $a):
                                        $initials = strtoupper(($a['first_name'][0] ?? '') . ($a['last_name'][0] ?? ''));
                                        $fullName = trim($a['first_name'] . ' ' . $a['last_name']);
                                        $stCls = $a['status'] === 'approved' ? 'bdg-approved' : 'bdg-rejected';
                                        $stIcon = $a['status'] === 'approved' ? 'fa-check-circle' : 'fa-xmark-circle';
                                    ?>
                                        <tr>
                                            <td style="color:var(--text3);font-size:.72rem"><?php echo $k + 1; ?></td>
                                            <td>
                                                <div class="av-cell">
                                                    <div class="av"><?php echo $initials; ?></div>
                                                    <div>
                                                        <div class="av-name"><?php echo htmlspecialchars($fullName); ?></div>
                                                        <div class="av-sub">@<?php echo htmlspecialchars($a['username']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo roleBadge($roleMap, $a['role']); ?></td>
                                            <td style="font-size:.8rem"><?php echo htmlspecialchars($a['email']); ?></td>
                                            <td>
                                                <span class="bdg <?php echo $stCls; ?>">
                                                    <i class="fas <?php echo $stIcon; ?>" style="font-size:.58rem"></i>
                                                    <?php echo ucfirst($a['status']); ?>
                                                </span>
                                            </td>
                                            <td style="font-size:.77rem;color:var(--text3);white-space:nowrap"><?php echo date('d M Y', strtotime($a['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div><!-- /pg /main -->

    <!-- ══════════════════════════════════════════════════════════════
     MODAL: View / Approve / Reject
══════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-box">
            <div class="modal-head">
                <div class="modal-icon" id="dmIcon" style="background:var(--blue-s);color:var(--blue)"><i class="fas fa-user-shield"></i></div>
                <div>
                    <h3 id="dmTitle">Admin Details</h3>
                    <div class="modal-sub" id="dmSub">Registration Request</div>
                </div>
                <button class="modal-close" onclick="closeModal('detailModal')"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <!-- Decision banner -->
                <div class="decision-bar approve" id="dmBanner" style="display:none"></div>

                <!-- Info grid -->
                <div class="info-grid" id="dmInfoGrid"></div>

                <!-- Approve: role picker + message -->
                <div id="dmApproveSection" style="display:none;margin-top:16px">
                    <div class="fg">
                        <label class="fl"><i class="fas fa-crown" style="margin-right:4px"></i>Assign Role</label>
                        <div class="role-grid" id="dmRoleGrid">
                            <?php foreach ($roles as $r):
                                $hex = ltrim($r['color'], '#');
                                $rgb = implode(',', array_map('hexdec', str_split($hex, 2)));
                            ?>
                                <div class="role-card <?php echo $r['name'] === 'admin' ? 'sel' : ''; ?>"
                                    data-role="<?php echo $r['name']; ?>"
                                    style="<?php echo $r['name'] === 'admin' ? 'border-color:' . $r['color'] . ';' : ''; ?>"
                                    onclick="selectRole(this,'<?php echo $r['name']; ?>','<?php echo $r['color']; ?>')">
                                    <div class="role-card-icon" style="background:rgba(<?php echo $rgb; ?>,.13);color:<?php echo $r['color']; ?>">
                                        <i class="fas <?php echo $r['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="role-card-name"><?php echo $r['label']; ?></div>
                                        <div class="role-card-desc"><?php echo $r['desc']; ?></div>
                                        <div class="level-bar" style="margin-top:6px">
                                            <div class="level-fill" style="width:<?php echo $r['level']; ?>%;background:<?php echo $r['color']; ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="dmSelectedRole" value="admin">
                    </div>
                    <div class="fg">
                        <label class="fl">Welcome Message (optional)</label>
                        <textarea class="ft" id="dmApproveMsg" placeholder="Write a welcome message to the new admin…" rows="3" style="min-height:80px"></textarea>
                    </div>
                </div>

                <!-- Reject: reason + message -->
                <div id="dmRejectSection" style="display:none;margin-top:16px">
                    <div class="fg">
                        <label class="fl">Reason for Rejection</label>
                        <select class="fs" id="dmRejectReason">
                            <option value="does_not_meet_criteria">Does not meet criteria</option>
                            <option value="incomplete_information">Incomplete information</option>
                            <option value="duplicate_application">Duplicate application</option>
                            <option value="not_affiliated">Not affiliated with institution</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl">Message to Applicant (optional)</label>
                        <textarea class="ft" id="dmRejectMsg" placeholder="Explain why the application was rejected…" rows="3" style="min-height:80px"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="dmFooter">
                <button class="btn-cancel" onclick="closeModal('detailModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
     MODAL: Change Role
══════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="roleModal">
        <div class="modal-box">
            <div class="modal-head">
                <div class="modal-icon" style="background:var(--purple-s);color:var(--purple)"><i class="fas fa-user-pen"></i></div>
                <div>
                    <h3 id="rmTitle">Change Role</h3>
                    <div class="modal-sub" id="rmSub">Promote or demote admin role</div>
                </div>
                <button class="modal-close" onclick="closeModal('roleModal')"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="decision-bar role" style="margin-bottom:16px">
                    <i class="fas fa-info-circle"></i>
                    The admin will receive a notification about their role change.
                </div>
                <div class="fg">
                    <label class="fl">Select New Role</label>
                    <div class="role-grid" id="rmRoleGrid">
                        <?php foreach ($roles as $r):
                            $hex = ltrim($r['color'], '#');
                            $rgb = implode(',', array_map('hexdec', str_split($hex, 2)));
                        ?>
                            <div class="role-card"
                                data-role="<?php echo $r['name']; ?>"
                                onclick="selectRoleModal(this,'<?php echo $r['name']; ?>','<?php echo $r['color']; ?>')">
                                <div class="role-card-icon" style="background:rgba(<?php echo $rgb; ?>,.13);color:<?php echo $r['color']; ?>">
                                    <i class="fas <?php echo $r['icon']; ?>"></i>
                                </div>
                                <div>
                                    <div class="role-card-name"><?php echo $r['label']; ?></div>
                                    <div class="role-card-desc"><?php echo $r['desc']; ?></div>
                                    <div class="level-bar" style="margin-top:6px">
                                        <div class="level-fill" style="width:<?php echo $r['level']; ?>%;background:<?php echo $r['color']; ?>"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="rmSelectedRole" value="">
                </div>
                <div class="fg">
                    <label class="fl">Message with Role Change (optional)</label>
                    <textarea class="ft" id="rmMsg" placeholder="Write a message to accompany the role change notification…" rows="3" style="min-height:80px"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal('roleModal')">Cancel</button>
                <button class="btn-primary" id="rmSubmitBtn" onclick="submitRoleChange()">
                    <i class="fas fa-user-pen"></i> Apply Role Change
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
     MODAL: Send Message / Notification
══════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="msgModal">
        <div class="modal-box">
            <div class="modal-head">
                <div class="modal-icon" style="background:var(--blue-s);color:var(--blue)"><i class="fas fa-envelope"></i></div>
                <div>
                    <h3 id="mmTitle">Send Message</h3>
                    <div class="modal-sub" id="mmSub">Send notification to admin</div>
                </div>
                <button class="modal-close" onclick="closeModal('msgModal')"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="decision-bar msg" style="margin-bottom:16px" id="mmRecipientBar">
                    <i class="fas fa-user-shield"></i> <span id="mmRecipientName">Admin</span>
                </div>
                <div class="fg">
                    <label class="fl">Notification Type</label>
                    <div class="ntype-row">
                        <span class="ntype nt-admin_message sel" data-val="admin_message" onclick="setNType(this)">📨 Admin Message</span>
                        <span class="ntype nt-warning" data-val="warning" onclick="setNType(this)">⚠️ Warning</span>
                        <span class="ntype nt-info" data-val="info" onclick="setNType(this)">ℹ️ Info</span>
                        <span class="ntype nt-promo" data-val="promo" onclick="setNType(this)">🎉 Promotion</span>
                    </div>
                </div>
                <div class="fg">
                    <label class="fl">Title</label>
                    <input type="text" class="fi" id="mmTitle2" placeholder="e.g. Important Update" maxlength="150">
                </div>
                <div class="fg">
                    <label class="fl">Message</label>
                    <textarea class="ft" id="mmBody" placeholder="Write your message…" maxlength="800"></textarea>
                </div>

                <!-- Quick templates -->
                <div class="fg">
                    <label class="fl" style="margin-bottom:8px">Quick Templates</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <button type="button" class="btn btn-view" onclick="setTemplate('welcome')">👋 Welcome</button>
                        <button type="button" class="btn btn-view" onclick="setTemplate('warning')">⚠️ Policy Warning</button>
                        <button type="button" class="btn btn-view" onclick="setTemplate('reminder')">🔔 Duty Reminder</button>
                        <button type="button" class="btn btn-view" onclick="setTemplate('commend')">🏆 Commendation</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal('msgModal')">Cancel</button>
                <button class="btn-primary" id="mmSubmitBtn" onclick="submitMessage()">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    </div>

    <!-- pulse animation -->
    <style>
        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .55
            }
        }
    </style>

    <script>
        /* ══════════════════════════════════════════════════════════════
   STATE
══════════════════════════════════════════════════════════════ */
        var _dmAdmin = {}; // current detail modal admin data
        var _dmAction = ''; // 'view' | 'approve' | 'reject'
        var _rmAdmin = {}; // role modal target
        var _mmAdmin = {}; // message modal target
        var _mmNType = 'admin_message';

        /* ══════════════════════════════════════════════════════════════
           TABS
        ══════════════════════════════════════════════════════════════ */
        function switchTab(name, btn) {
            document.querySelectorAll('.mtab').forEach(b => b.classList.remove('on'));
            btn.classList.add('on');
            ['pending', 'admins', 'history'].forEach(t => {
                document.getElementById('tab-' + t).style.display = t === name ? '' : 'none';
            });
        }

        /* ══════════════════════════════════════════════════════════════
           FILTER
        ══════════════════════════════════════════════════════════════ */
        function filterAdmins() {
            var role = document.getElementById('roleFilter').value;
            var q = document.getElementById('adminSearch').value.toLowerCase().trim();
            document.querySelectorAll('.admin-row').forEach(row => {
                var show = true;
                if (role && row.dataset.role !== role) show = false;
                if (q && !row.dataset.search.includes(q)) show = false;
                row.classList.toggle('filtered-out', !show);
            });
        }

        /* ══════════════════════════════════════════════════════════════
           MODAL HELPERS
        ══════════════════════════════════════════════════════════════ */
        function openModal(id) {
            document.getElementById(id).classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }

        ['detailModal', 'roleModal', 'msgModal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) closeModal(id);
            });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape')['detailModal', 'roleModal', 'msgModal'].forEach(closeModal);
        });

        /* ══════════════════════════════════════════════════════════════
           DETAIL MODAL (View / Approve / Reject)
        ══════════════════════════════════════════════════════════════ */
        function openDetailModal(data, action) {
            _dmAdmin = data;
            _dmAction = action;

            var name = (data.first_name || '') + ' ' + (data.last_name || '');

            // Header
            document.getElementById('dmTitle').textContent = action === 'view' ? 'Registration Details' :
                action === 'approve' ? 'Approve Request' :
                'Reject Request';
            document.getElementById('dmSub').textContent = name.trim();

            // Icon
            var iconEl = document.getElementById('dmIcon');
            if (action === 'approve') {
                iconEl.style.background = 'var(--green-s)';
                iconEl.style.color = 'var(--green)';
                iconEl.innerHTML = '<i class="fas fa-check-circle"></i>';
            } else if (action === 'reject') {
                iconEl.style.background = 'var(--red-s)';
                iconEl.style.color = 'var(--red)';
                iconEl.innerHTML = '<i class="fas fa-xmark-circle"></i>';
            } else {
                iconEl.style.background = 'var(--blue-s)';
                iconEl.style.color = 'var(--blue)';
                iconEl.innerHTML = '<i class="fas fa-user-shield"></i>';
            }

            // Banner
            var banner = document.getElementById('dmBanner');
            if (action === 'approve') {
                banner.style.display = '';
                banner.className = 'decision-bar approve';
                banner.innerHTML = '<i class="fas fa-check-circle"></i> You are about to <strong>approve</strong> this registration. Choose a role below.';
            } else if (action === 'reject') {
                banner.style.display = '';
                banner.className = 'decision-bar reject';
                banner.innerHTML = '<i class="fas fa-xmark-circle"></i> You are about to <strong>reject</strong> this registration.';
            } else {
                banner.style.display = 'none';
            }

            // Info grid
            var fields = [{
                    icon: 'fa-user',
                    label: 'Full Name',
                    value: name.trim(),
                    full: false
                },
                {
                    icon: 'fa-at',
                    label: 'Username',
                    value: '@' + (data.username || '—'),
                    full: false
                },
                {
                    icon: 'fa-envelope',
                    label: 'Email',
                    value: data.email || '—',
                    full: false
                },
                {
                    icon: 'fa-phone',
                    label: 'Phone',
                    value: data.phone || '—',
                    full: false
                },
                {
                    icon: 'fa-crown',
                    label: 'Requested Role',
                    value: data.role || '—',
                    full: false
                },
                {
                    icon: 'fa-building',
                    label: 'Institution',
                    value: data.institution || '—',
                    full: false
                },
                {
                    icon: 'fa-book-open',
                    label: 'Course',
                    value: data.course || '—',
                    full: false
                },
                {
                    icon: 'fa-calendar',
                    label: 'Applied',
                    value: data.created_at || '—',
                    full: false
                },
                {
                    icon: 'fa-tags',
                    label: 'Subjects',
                    value: data.subjects || '—',
                    full: true
                },
            ];
            document.getElementById('dmInfoGrid').innerHTML = fields.map(f =>
                `<div class="ig ${f.full?'full':''}">
            <div class="ig-lbl"><i class="fas ${f.icon}"></i>${f.label}</div>
            <div class="ig-val ${f.value==='—'?'muted':''}">${esc(f.value)}</div>
        </div>`
            ).join('');

            // Sections
            document.getElementById('dmApproveSection').style.display = action === 'approve' ? '' : 'none';
            document.getElementById('dmRejectSection').style.display = action === 'reject' ? '' : 'none';

            // Pre-fill role picker with requested role
            if (action === 'approve') {
                selectRoleInGrid('dmRoleGrid', 'dmSelectedRole', data.role || 'admin');
            }

            // Footer
            var footer = document.getElementById('dmFooter');
            if (action === 'approve') {
                footer.innerHTML = `<button class="btn-cancel" onclick="closeModal('detailModal')">Cancel</button>
            <button class="btn-primary green" onclick="submitDecision('approved')"><i class="fas fa-check"></i> Approve & Grant Access</button>`;
            } else if (action === 'reject') {
                footer.innerHTML = `<button class="btn-cancel" onclick="closeModal('detailModal')">Cancel</button>
            <button class="btn-primary red" onclick="submitDecision('rejected')"><i class="fas fa-xmark"></i> Reject Application</button>`;
            } else {
                footer.innerHTML = `<button class="btn-cancel" onclick="closeModal('detailModal')">Close</button>`;
                if (<?php echo $isSuperAdmin ? 'true' : 'false'; ?>) {
                    footer.innerHTML += `<button class="btn-primary green" onclick="closeModal('detailModal');openDetailModal(_dmAdmin,'approve')"><i class="fas fa-check"></i> Approve</button>
                <button class="btn-primary red" onclick="closeModal('detailModal');openDetailModal(_dmAdmin,'reject')"><i class="fas fa-xmark"></i> Reject</button>`;
                }
            }

            openModal('detailModal');
        }

        function selectRole(el, role, color) {
            document.querySelectorAll('#dmRoleGrid .role-card').forEach(c => {
                c.classList.remove('sel');
                c.style.borderColor = '';
            });
            el.classList.add('sel');
            el.style.borderColor = color;
            document.getElementById('dmSelectedRole').value = role;
        }

        function selectRoleModal(el, role, color) {
            document.querySelectorAll('#rmRoleGrid .role-card').forEach(c => {
                c.classList.remove('sel');
                c.style.borderColor = '';
            });
            el.classList.add('sel');
            el.style.borderColor = color;
            document.getElementById('rmSelectedRole').value = role;
        }

        function selectRoleInGrid(gridId, inputId, roleName) {
            var roles = <?php echo json_encode(array_column($roles, 'color', 'name')); ?>;
            document.querySelectorAll('#' + gridId + ' .role-card').forEach(c => {
                c.classList.remove('sel');
                c.style.borderColor = '';
                if (c.dataset.role === roleName) {
                    c.classList.add('sel');
                    c.style.borderColor = roles[roleName] || '#2563eb';
                }
            });
            document.getElementById(inputId).value = roleName;
        }

        async function submitDecision(action) {
            var role = document.getElementById('dmSelectedRole').value || _dmAdmin.role;
            var msg = action === 'approved' ?
                document.getElementById('dmApproveMsg').value.trim() :
                document.getElementById('dmRejectMsg').value.trim();
            var reason = action === 'rejected' ?
                document.getElementById('dmRejectReason').value : '';

            var btn = document.querySelector('#dmFooter .btn-primary');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';
            }

            var fd = new FormData();
            fd.append('action', action === 'approved' ? 'approve_admin' : 'reject_admin');
            fd.append('admin_id', _dmAdmin.admin_id);
            fd.append('role', role);
            fd.append('message', msg);
            fd.append('reason', reason);

            try {
                var res = await fetch('auth/update_admin_status.php', {
                    method: 'POST',
                    body: fd
                });
                var data = await res.json();
                if (data.success) {
                    closeModal('detailModal');
                    Swal.fire({
                        icon: action === 'approved' ? 'success' : 'info',
                        title: action === 'approved' ? '✅ Access Granted!' : 'Application Rejected',
                        text: data.message || 'Done.',
                        timer: 2800,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else sw('error', 'Failed', data.message || 'Something went wrong.');
            } catch (e) {
                sw('error', 'Network Error', 'Could not reach the server.');
            }

            if (btn) {
                btn.disabled = false;
            }
        }

        /* ══════════════════════════════════════════════════════════════
           ROLE CHANGE MODAL
        ══════════════════════════════════════════════════════════════ */
        function openRoleModal(data) {
            _rmAdmin = data;
            var name = (data.first_name || '') + ' ' + (data.last_name || '');
            document.getElementById('rmTitle').textContent = 'Change Role — ' + name.trim();
            document.getElementById('rmSub').textContent = 'Current: ' + (data.role || '—');
            document.getElementById('rmMsg').value = '';
            // Pre-select current role
            selectRoleInGrid('rmRoleGrid', 'rmSelectedRole', data.role || 'admin');
            openModal('roleModal');
        }

        async function submitRoleChange() {
            var newRole = document.getElementById('rmSelectedRole').value;
            var msg = document.getElementById('rmMsg').value.trim();
            var name = (_rmAdmin.first_name || '') + ' ' + (_rmAdmin.last_name || '');

            if (!newRole) return sw('warning', 'No Role Selected', 'Please choose a role.');
            if (newRole === _rmAdmin.role) return sw('info', 'No Change', 'The selected role is the same as current.');

            var btn = document.getElementById('rmSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating…';

            var fd = new FormData();
            fd.append('action', 'change_role');
            fd.append('admin_id', _rmAdmin.admin_id);
            fd.append('new_role', newRole);
            fd.append('message', msg);
            fd.append('old_role', _rmAdmin.role);

            try {
                var res = await fetch('auth/update_admin_status.php', {
                    method: 'POST',
                    body: fd
                });
                var data = await res.json();
                if (data.success) {
                    closeModal('roleModal');
                    Swal.fire({
                        icon: 'success',
                        title: 'Role Updated!',
                        text: name.trim() + ' is now a ' + newRole + '.',
                        timer: 2500,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else sw('error', 'Failed', data.message);
            } catch (e) {
                sw('error', 'Network Error', 'Could not reach the server.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-pen"></i> Apply Role Change';
        }

        /* ══════════════════════════════════════════════════════════════
           REVOKE ACCESS
        ══════════════════════════════════════════════════════════════ */
        function revokeAccess(adminId, name) {
            Swal.fire({
                title: 'Revoke Access?',
                html: `<p style="color:#64748b;font-size:.88rem">This will reject <strong style="color:#0f172a">${name}</strong>'s admin account. They will lose all admin access immediately.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-user-slash"></i> Yes, Revoke',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#e2e8f0',
                reverseButtons: true
            }).then(r => {
                if (!r.isConfirmed) return;
                var fd = new FormData();
                fd.append('action', 'revoke_access');
                fd.append('admin_id', adminId);
                fetch('auth/update_admin_status.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) Swal.fire({
                            icon: 'success',
                            title: 'Access Revoked',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                        else sw('error', 'Failed', d.message);
                    }).catch(() => sw('error', 'Network Error'));
            });
        }

        /* ══════════════════════════════════════════════════════════════
           MESSAGE MODAL
        ══════════════════════════════════════════════════════════════ */
        function openMessageModal(data) {
            _mmAdmin = data;
            _mmNType = 'admin_message';
            var name = (data.first_name || '') + ' ' + (data.last_name || '');
            document.getElementById('mmTitle').textContent = 'Send Message';
            document.getElementById('mmSub').textContent = 'To: ' + name.trim();
            document.getElementById('mmRecipientName').textContent = name.trim() + ' (@' + (data.username || '') + ')';
            document.getElementById('mmTitle2').value = '';
            document.getElementById('mmBody').value = '';
            // Reset type pills
            document.querySelectorAll('.ntype').forEach(n => n.classList.remove('sel'));
            document.querySelector('.ntype.nt-admin_message').classList.add('sel');
            openModal('msgModal');
        }

        function setNType(el) {
            document.querySelectorAll('.ntype').forEach(n => n.classList.remove('sel'));
            el.classList.add('sel');
            _mmNType = el.dataset.val;
        }

        function setTemplate(tpl) {
            var name = (_mmAdmin.first_name || 'Admin');
            var templates = {
                welcome: {
                    title: 'Welcome to ScholarSwap Admin Team!',
                    body: 'Hi ' + name + ',\n\nWelcome aboard! We\'re thrilled to have you join the ScholarSwap admin team. Your role gives you access to manage platform content and help our community grow.\n\nIf you have any questions, feel free to reach out.\n\nBest regards,\nScholarSwap Super Admin'
                },
                warning: {
                    title: 'Content Policy Reminder',
                    body: 'Hi ' + name + ',\n\nThis is a reminder that all admin actions must comply with our content policy and guidelines. Please review the admin handbook if you have any concerns.\n\nThank you for your cooperation.'
                },
                reminder: {
                    title: 'Admin Duty Reminder',
                    body: 'Hi ' + name + ',\n\nJust a friendly reminder to review the pending content queue and process any outstanding approvals or reports. Keeping the queue clear helps maintain a great user experience.\n\nThanks!'
                },
                commend: {
                    title: 'Great Work! 🏆',
                    body: 'Hi ' + name + ',\n\nWe wanted to take a moment to commend your excellent work as an admin. Your dedication to maintaining platform quality is truly appreciated.\n\nKeep up the fantastic work!\n\nBest regards,\nScholarSwap Super Admin'
                },
            };
            var t = templates[tpl];
            if (t) {
                document.getElementById('mmTitle2').value = t.title;
                document.getElementById('mmBody').value = t.body;
            }
        }

        async function submitMessage() {
            var title = document.getElementById('mmTitle2').value.trim();
            var body = document.getElementById('mmBody').value.trim();
            var btn = document.getElementById('mmSubmitBtn');

            if (!title) return sw('warning', 'Missing Title', 'Please enter a notification title.');
            if (!body) return sw('warning', 'Missing Message', 'Please write a message.');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';

            var fd = new FormData();
            fd.append('action', 'send_admin_message');
            fd.append('admin_id', _mmAdmin.admin_id);
            fd.append('notif_type', _mmNType);
            fd.append('title', title);
            fd.append('message', body);

            try {
                var res = await fetch('auth/update_admin_status.php', {
                    method: 'POST',
                    body: fd
                });
                var data = await res.json();
                if (data.success) {
                    closeModal('msgModal');
                    sw('success', 'Message Sent!', data.message || 'Notification delivered.');
                } else sw('error', 'Failed', data.message);
            } catch (e) {
                sw('error', 'Network Error', 'Could not reach the server.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Message';
        }

        /* ══════════════════════════════════════════════════════════════
           UTILS
        ══════════════════════════════════════════════════════════════ */
        function sw(icon, title, text) {
            return Swal.fire({
                icon,
                title,
                text,
                iconColor: icon === 'error' ? '#ef4444' : icon === 'warning' ? '#f59e0b' : '#10b981'
            });
        }

        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /* Flash */
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
        if (sp) history.replaceState(null, '', 'admin_access.php');
    </script>
</body>

</html>