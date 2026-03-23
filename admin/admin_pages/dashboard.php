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

// ── Counts ──────────────────────────────────────────────────────
$c = [
    'users'      => q($conn, "SELECT COUNT(*) FROM users"),
    'students'   => q($conn, "SELECT COUNT(*) FROM students"),
    'tutors'     => q($conn, "SELECT COUNT(*) FROM tutors"),
    'admins'     => q($conn, "SELECT COUNT(*) FROM admin_user"),
    'notes'      => q($conn, "SELECT COUNT(*) FROM notes"),
    'books'      => q($conn, "SELECT COUNT(*) FROM books"),
    'papers'     => q($conn, "SELECT COUNT(*) FROM newspapers"),
    'verified'   => q($conn, "SELECT COUNT(*) FROM users WHERE is_verified=1"),
    'featured_n' => q($conn, "SELECT COUNT(*) FROM notes WHERE is_featured=1"),
    'featured_b' => q($conn, "SELECT COUNT(*) FROM books WHERE is_featured=1"),
    'n_pending'  => q($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='pending'"),
    'n_approved' => q($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='approved'"),
    'n_rejected' => q($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='rejected'"),
    'b_pending'  => q($conn, "SELECT COUNT(*) FROM books WHERE approval_status='pending'"),
    'b_approved' => q($conn, "SELECT COUNT(*) FROM books WHERE approval_status='approved'"),
    'b_rejected' => q($conn, "SELECT COUNT(*) FROM books WHERE approval_status='rejected'"),
    'p_pending'  => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'"),
    'p_approved' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='approved'"),
];
$c['pending']  = $c['n_pending']  + $c['b_pending']  + $c['p_pending'];
$c['approved'] = $c['n_approved'] + $c['b_approved']  + $c['p_approved'];
$c['rejected'] = $c['n_rejected'] + $c['b_rejected'];

// ── Admin info ───────────────────────────────────────────────────
$sa = $conn->prepare("SELECT first_name,last_name,role FROM admin_user WHERE admin_id=? LIMIT 1");
$sa->execute([$_SESSION['admin_id']]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);

// ── Chart data ───────────────────────────────────────────────────
$monthLabels = $notesM = $booksM = $papersM = $usersM = [];
for ($i = 6; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $monthLabels[] = date('M', strtotime("-$i months"));
    $sn = $conn->prepare("SELECT COUNT(*) FROM notes      WHERE DATE_FORMAT(created_at,'%Y-%m')=?");
    $sn->execute([$m]);
    $notesM[]  = (int)$sn->fetchColumn();
    $sb = $conn->prepare("SELECT COUNT(*) FROM books      WHERE DATE_FORMAT(created_at,'%Y-%m')=?");
    $sb->execute([$m]);
    $booksM[]  = (int)$sb->fetchColumn();
    $sp = $conn->prepare("SELECT COUNT(*) FROM newspapers WHERE DATE_FORMAT(created_at,'%Y-%m')=?");
    $sp->execute([$m]);
    $papersM[] = (int)$sp->fetchColumn();
    $su = $conn->prepare("SELECT COUNT(*) FROM users      WHERE DATE_FORMAT(created_at,'%Y-%m')=?");
    $su->execute([$m]);
    $usersM[]  = (int)$su->fetchColumn();
}

$subjQ = $conn->prepare("SELECT subject,COUNT(*) AS cnt FROM notes WHERE subject!='' AND subject IS NOT NULL GROUP BY subject ORDER BY cnt DESC LIMIT 6");
$subjQ->execute();
$subjData   = $subjQ->fetchAll(PDO::FETCH_ASSOC);
$subjLabels = array_column($subjData, 'subject');
$subjCounts = array_column($subjData, 'cnt');

// ── Top lists ────────────────────────────────────────────────────
$topDL = $conn->prepare("(SELECT title,'note' AS type,download_count FROM notes WHERE approval_status='approved' ORDER BY download_count DESC LIMIT 5) UNION ALL (SELECT title,'book',download_count FROM books WHERE approval_status='approved' ORDER BY download_count DESC LIMIT 5) ORDER BY download_count DESC LIMIT 6");
$topDL->execute();
$topDLData = $topDL->fetchAll(PDO::FETCH_ASSOC);

$topRated = $conn->prepare("(SELECT title,'note' AS type,rating FROM notes WHERE approval_status='approved' AND rating>0 ORDER BY rating DESC LIMIT 3) UNION ALL (SELECT title,'book',rating FROM books WHERE approval_status='approved' AND rating>0 ORDER BY rating DESC LIMIT 3) ORDER BY rating DESC LIMIT 5");
$topRated->execute();
$topRatedData = $topRated->fetchAll(PDO::FETCH_ASSOC);

// ── Recent signups ───────────────────────────────────────────────
$ru = $conn->prepare("SELECT username,role,created_at FROM users ORDER BY created_at DESC LIMIT 8");
$ru->execute();
$recentUsersData = $ru->fetchAll(PDO::FETCH_ASSOC);

// ── Report generation date ───────────────────────────────────────
$reportDate = date('d F Y, h:i A');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Dashboard | ScholarSwap Admin</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

        .pg-head {
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px
        }

        .pg-head-left h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2
        }

        .pg-head-left p {
            font-size: .82rem;
            color: var(--text3);
            margin-top: 4px
        }

        /* ── PDF Download Button ── */
        .btn-pdf {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, var(--blue), var(--blue-d));
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            font-family: inherit;
            box-shadow: 0 4px 14px rgba(37, 99, 235, .28);
            transition: all .2s;
            white-space: nowrap;
        }

        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, .38)
        }

        .btn-pdf:active {
            transform: scale(.97)
        }

        .btn-pdf i {
            font-size: .8rem
        }

        /* ── Stat grid ── */
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

        .si.r {
            background: var(--red-s);
            color: var(--red)
        }

        .si.p {
            background: var(--purple-s);
            color: var(--purple)
        }

        .si.t {
            background: var(--teal-s);
            color: var(--teal)
        }

        .si.o {
            background: var(--orange-s);
            color: var(--orange)
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

        /* ── Chart grids ── */
        .cg2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 18px
        }

        .cg3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 18px
        }

        .cc {
            background: var(--surface);
            border-radius: var(--r2);
            padding: 18px 20px;
            box-shadow: var(--sh);
            border: 1px solid var(--border)
        }

        .cc-t {
            font-family: 'Space Grotesk', sans-serif;
            font-size: .87rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 2px
        }

        .cc-s {
            font-size: .7rem;
            color: var(--text3);
            margin-bottom: 13px
        }

        .dl {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 12px
        }

        .dl-item {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: .76rem;
            color: var(--text2)
        }

        .dl-dot {
            width: 8px;
            height: 8px;
            border-radius: 3px;
            flex-shrink: 0
        }

        /* ── Layout ── */
        .bg3 {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 16px;
            margin-bottom: 18px;
        }

        /* ── Panel ── */
        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden
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

        .ph-link {
            font-size: .75rem;
            color: var(--blue);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px
        }

        .ph-link:hover {
            text-decoration: underline
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
            padding: 10px 13px;
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

        /* ── Badges ── */
        .bdg {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: .63rem;
            font-weight: 700;
            white-space: nowrap
        }

        .bdg-note {
            background: var(--blue-s);
            color: var(--blue)
        }

        .bdg-book {
            background: var(--teal-s);
            color: var(--teal)
        }

        .bdg-student {
            background: var(--blue-s);
            color: var(--blue)
        }

        .bdg-tutor {
            background: var(--orange-s);
            color: var(--orange)
        }

        /* ── Top resource ── */
        .tr-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border)
        }

        .tr-item:last-child {
            border-bottom: none
        }

        .tr-rank {
            width: 20px;
            height: 20px;
            border-radius: 5px;
            background: var(--bg);
            font-size: .64rem;
            font-weight: 800;
            color: var(--text3);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0
        }

        .tr-rank.g1 {
            background: #fef3c7;
            color: #b45309
        }

        .tr-rank.g2 {
            background: #f1f5f9;
            color: #475569
        }

        .tr-rank.g3 {
            background: #ffedd5;
            color: #c2410c
        }

        .tr-name {
            flex: 1;
            font-size: .8rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .tr-cnt {
            font-size: .72rem;
            color: var(--text3);
            display: flex;
            align-items: center;
            gap: 3px;
            flex-shrink: 0
        }

        /* ── Progress ── */
        .pr-row {
            padding: 0 18px 12px
        }

        .pr-lbl {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px
        }

        .pr-lbl span:first-child {
            font-size: .76rem;
            font-weight: 600;
            color: var(--text2)
        }

        .pr-lbl span:last-child {
            font-size: .72rem;
            color: var(--text3)
        }

        .pr-bar {
            height: 6px;
            background: var(--bg);
            border-radius: 99px;
            overflow: hidden
        }

        .pr-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 1.1s ease
        }

        /* ── Empty ── */
        .empty {
            padding: 28px;
            text-align: center;
            color: var(--text3);
            font-size: .8rem
        }

        .empty i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 7px;
            opacity: .3
        }

        /* ════════════════════════════════════════════════════════
           PDF PRINT STYLES — only visible when printing
           ════════════════════════════════════════════════════════ */
        @media print {

            /* Hide everything that shouldn't be in the PDF */
            .sidebar,
            .topbar,
            .btn-pdf,
            .cg2 canvas,
            .cg3 canvas,
            /* charts render blank in print — use tables instead */
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
                display: block;
                font-size: 11px;
            }

            .main {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .pg {
                padding: 0 !important;
            }

            /* Report cover header */
            .pdf-header {
                display: flex !important;
                justify-content: space-between;
                align-items: flex-start;
                padding: 0 0 16px;
                border-bottom: 2px solid #2563eb;
                margin-bottom: 24px;
            }

            .pdf-header h1 {
                font-size: 1.4rem;
                font-weight: 800;
                color: #0f172a;
                margin: 0;
            }

            .pdf-header p {
                font-size: .75rem;
                color: #64748b;
                margin-top: 4px;
            }

            .pdf-header .pdf-logo {
                font-size: .72rem;
                color: #64748b;
                text-align: right;
            }

            /* Stat cards side-by-side */
            .sg {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 8px !important;
            }

            .sc {
                padding: 10px 12px !important;
                box-shadow: none !important;
                border: 1px solid #e2e8f0 !important;
            }

            .sv {
                font-size: 1.1rem !important;
            }

            .sl {
                font-size: .62rem !important;
            }

            .si {
                width: 32px !important;
                height: 32px !important;
                font-size: .75rem !important;
            }

            /* Chart area → show print-only data tables instead */
            .cg2,
            .cg3 {
                display: none !important;
            }

            .print-chart-tables {
                display: block !important;
                margin-bottom: 18px;
            }

            /* Progress + top panels */
            .bg3 {
                grid-template-columns: 1fr 1fr !important;
                gap: 12px !important;
            }

            .panel {
                box-shadow: none !important;
                border: 1px solid #e2e8f0 !important;
            }

            /* Recent signups table */
            table {
                font-size: .78rem !important;
            }

            thead th {
                font-size: .58rem !important;
                padding: 6px 8px !important;
            }

            tbody td {
                padding: 7px 8px !important;
            }

            /* Page breaks */
            .page-break {
                page-break-before: always;
            }

            /* Footer on every page */
            .pdf-footer {
                display: block !important;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                border-top: 1px solid #e2e8f0;
                padding: 6px 0;
                font-size: .65rem;
                color: #94a3b8;
                text-align: center;
                background: #fff;
            }
        }

        /* Normally hidden, only shown during print */
        .pdf-header {
            display: none;
        }

        .pdf-footer {
            display: none;
        }

        .print-chart-tables {
            display: none;
        }

        /* ── Responsive ── */
        @media(max-width:1100px) {

            .cg2,
            .bg3 {
                grid-template-columns: 1fr
            }

            .cg3 {
                grid-template-columns: 1fr 1fr
            }
        }

        @media(max-width:780px) {
            .sg {
                grid-template-columns: repeat(2, 1fr)
            }

            .cg3 {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:500px) {
            .sg {
                grid-template-columns: 1fr 1fr
            }

            .pg {
                padding: 14px
            }
        }
    </style>
</head>

<body>

    <?php include_once('sidebar.php'); ?>
    <?php include_once('adminheader.php'); ?>

    <div class="main">
        <div class="pg">

            <!-- ── PDF Report header (visible only on print) ── -->
            <div class="pdf-header">
                <div>
                    <h1>ScholarSwap — Admin Status Report</h1>
                    <p>Generated by <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                        &nbsp;·&nbsp; <?php echo $reportDate; ?></p>
                </div>
                <div class="pdf-logo">
                    <strong>ScholarSwap</strong><br>Admin Dashboard<br>
                    <span style="color:#2563eb">Confidential</span>
                </div>
            </div>

            <!-- ── PAGE HEADING ── -->
            <div class="pg-head">
                <div class="pg-head-left">
                    <h1>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>,
                        <?php echo htmlspecialchars($admin['first_name']); ?> 👋</h1>
                    <p>Here's what's happening on ScholarSwap today — <?php echo date('l, d F Y'); ?></p>
                </div>
            </div>

            <!-- ── STAT CARDS ── -->
            <div class="sg">
                <div class="sc">
                    <div class="si b"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['users']; ?></div>
                        <div class="sl">Total Users</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si g"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['students']; ?></div>
                        <div class="sl">Students</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si a"><i class="fas fa-user-tie"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['tutors']; ?></div>
                        <div class="sl">Tutors</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si p"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['admins']; ?></div>
                        <div class="sl">Admins</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si b"><i class="fas fa-file-lines"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['notes']; ?></div>
                        <div class="sl">Notes</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si t"><i class="fas fa-book"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['books']; ?></div>
                        <div class="sl">Books</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si p"><i class="fas fa-newspaper"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['papers']; ?></div>
                        <div class="sl">Newspapers</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si g"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['verified']; ?></div>
                        <div class="sl">Verified Users</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si r"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['pending']; ?></div>
                        <div class="sl">Pending</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si g"><i class="fas fa-check-double"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['approved']; ?></div>
                        <div class="sl">Approved</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si o"><i class="fas fa-ban"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['rejected']; ?></div>
                        <div class="sl">Rejected</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si a"><i class="fas fa-star"></i></div>
                    <div>
                        <div class="sv"><?php echo $c['featured_n'] + $c['featured_b']; ?></div>
                        <div class="sl">Featured</div>
                    </div>
                </div>
            </div>

            <!-- ── PRINT-ONLY data tables replacing charts ── -->
            <div class="print-chart-tables">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                    <!-- Monthly upload summary -->
                    <div class="panel">
                        <div class="ph"><span class="pt">Monthly Uploads (Last 7 Months)</span></div>
                        <div class="tw">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Notes</th>
                                        <th>Books</th>
                                        <th>Newspapers</th>
                                        <th>Users Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 0; $i < 7; $i++): ?>
                                        <tr>
                                            <td><?php echo $monthLabels[$i]; ?></td>
                                            <td><?php echo $notesM[$i]; ?></td>
                                            <td><?php echo $booksM[$i]; ?></td>
                                            <td><?php echo $papersM[$i]; ?></td>
                                            <td><?php echo $usersM[$i]; ?></td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Approval summary -->
                    <div class="panel">
                        <div class="ph"><span class="pt">Approval Status Summary</span></div>
                        <div class="tw">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Approved</th>
                                        <th>Pending</th>
                                        <th>Rejected</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Notes</td>
                                        <td><?php echo $c['n_approved']; ?></td>
                                        <td><?php echo $c['n_pending']; ?></td>
                                        <td><?php echo $c['n_rejected']; ?></td>
                                        <td><?php echo $c['notes']; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Books</td>
                                        <td><?php echo $c['b_approved']; ?></td>
                                        <td><?php echo $c['b_pending']; ?></td>
                                        <td><?php echo $c['b_rejected']; ?></td>
                                        <td><?php echo $c['books']; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Newspapers</td>
                                        <td><?php echo $c['p_approved']; ?></td>
                                        <td><?php echo $c['p_pending']; ?></td>
                                        <td>—</td>
                                        <td><?php echo $c['papers']; ?></td>
                                    </tr>
                                    <tr style="font-weight:700;background:#f8fafc">
                                        <td><strong>Total</strong></td>
                                        <td><?php echo $c['approved']; ?></td>
                                        <td><?php echo $c['pending']; ?></td>
                                        <td><?php echo $c['rejected']; ?></td>
                                        <td><?php echo $c['approved'] + $c['pending'] + $c['rejected']; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Top subjects -->
                <?php if (!empty($subjData)): ?>
                    <div class="panel" style="margin-bottom:14px">
                        <div class="ph"><span class="pt">Top Subjects by Notes</span></div>
                        <div class="tw">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Subject</th>
                                        <th>Note Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjData as $k => $s): ?>
                                        <tr>
                                            <td><?php echo $k + 1; ?></td>
                                            <td><?php echo htmlspecialchars($s['subject']); ?></td>
                                            <td><?php echo $s['cnt']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── CHARTS ROW 1 (screen only) ── -->
            <div class="cg2 no-print">
                <div class="cc">
                    <div class="cc-t">Monthly Uploads</div>
                    <div class="cc-s">Notes · Books · Newspapers — last 7 months</div>
                    <canvas id="lineChart" height="105"></canvas>
                </div>
                <div class="cc">
                    <div class="cc-t">Content Mix</div>
                    <div class="cc-s">Distribution by type</div>
                    <canvas id="donut1" height="150"></canvas>
                    <div class="dl">
                        <div class="dl-item"><span class="dl-dot" style="background:#2563eb"></span>Notes — <strong><?php echo $c['notes']; ?></strong></div>
                        <div class="dl-item"><span class="dl-dot" style="background:#0d9488"></span>Books — <strong><?php echo $c['books']; ?></strong></div>
                        <div class="dl-item"><span class="dl-dot" style="background:#7c3aed"></span>Papers — <strong><?php echo $c['papers']; ?></strong></div>
                    </div>
                </div>
            </div>

            <!-- ── CHARTS ROW 2 (screen only) ── -->
            <div class="cg3 no-print">
                <div class="cc">
                    <div class="cc-t">Approval Status</div>
                    <div class="cc-s">All content combined</div>
                    <canvas id="approvalBar" height="140"></canvas>
                </div>
                <div class="cc">
                    <div class="cc-t">New Users / Month</div>
                    <div class="cc-s">Last 7 months</div>
                    <canvas id="userLine" height="140"></canvas>
                </div>
                <div class="cc">
                    <div class="cc-t">Top Subjects</div>
                    <div class="cc-s">Notes by subject</div>
                    <canvas id="subjBar" height="140"></canvas>
                </div>
            </div>
            <!-- ── RIGHT PANELS ── -->
            <div class="bg3" style="width: 100%;">
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <!-- Approval Progress -->
                    <div class="panel">
                        <div class="ph"><span class="pt">Approval Progress</span></div>
                        <?php
                        $tot = max($c['approved'] + $c['pending'] + $c['rejected'], 1);
                        $pa  = round($c['approved'] / $tot * 100);
                        $pp  = round($c['pending']  / $tot * 100);
                        $pr  = round($c['rejected'] / $tot * 100);
                        ?>
                        <div style="padding:14px 0 4px">
                            <div class="pr-row">
                                <div class="pr-lbl"><span>Approved (<?php echo $c['approved']; ?>)</span><span><?php echo $pa; ?>%</span></div>
                                <div class="pr-bar">
                                    <div class="pr-fill" style="width:<?php echo $pa; ?>%;background:var(--green)"></div>
                                </div>
                            </div>
                            <div class="pr-row">
                                <div class="pr-lbl"><span>Pending (<?php echo $c['pending']; ?>)</span><span><?php echo $pp; ?>%</span></div>
                                <div class="pr-bar">
                                    <div class="pr-fill" style="width:<?php echo $pp; ?>%;background:var(--amber)"></div>
                                </div>
                            </div>
                            <div class="pr-row">
                                <div class="pr-lbl"><span>Rejected (<?php echo $c['rejected']; ?>)</span><span><?php echo $pr; ?>%</span></div>
                                <div class="pr-bar">
                                    <div class="pr-fill" style="width:<?php echo $pr; ?>%;background:var(--red)"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Downloads -->
                    <div class="panel">
                        <div class="ph"><span class="pt">Top Downloads</span></div>
                        <?php if (count($topDLData)): $rk = ['g1', 'g2', 'g3', '', '', ''];
                            foreach ($topDLData as $k => $r): ?>
                                <div class="tr-item">
                                    <div class="tr-rank <?php echo $rk[$k] ?? ''; ?>"><?php echo $k + 1; ?></div>
                                    <div class="tr-name"><?php echo htmlspecialchars($r['title']); ?></div>
                                    <span class="bdg bdg-<?php echo $r['type'] === 'note' ? 'note' : 'book'; ?>"><?php echo ucfirst($r['type']); ?></span>
                                    <div class="tr-cnt"><i class="fas fa-download" style="font-size:.58rem"></i><?php echo $r['download_count']; ?></div>
                                </div>
                            <?php endforeach;
                        else: ?><div class="empty"><i class="fas fa-download"></i>No data</div><?php endif; ?>
                    </div>

                    <!-- Top Rated -->
                    <div class="panel">
                        <div class="ph"><span class="pt">Top Rated</span></div>
                        <?php if (count($topRatedData)): foreach ($topRatedData as $r): ?>
                                <div class="tr-item">
                                    <span style="color:#f59e0b;font-size:.8rem">★</span>
                                    <div class="tr-name"><?php echo htmlspecialchars($r['title']); ?></div>
                                    <span class="bdg bdg-<?php echo $r['type'] === 'note' ? 'note' : 'book'; ?>"><?php echo ucfirst($r['type']); ?></span>
                                    <div class="tr-cnt"><?php echo number_format($r['rating'], 1); ?></div>
                                </div>
                            <?php endforeach;
                        else: ?><div class="empty"><i class="fas fa-star"></i>No ratings</div><?php endif; ?>
                    </div>                    
                </div>
                
                <div class="panel">
                            <h1>sss</h1>
                </div>
            </div>
            <!-- Recent Signups -->
            <div class="panel" style="height:fit-content">
                <div class="ph">
                    <span class="pt">Recent Signups</span>
                    <a class="ph-link" href="students.php">View all <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsersData as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><span class="bdg bdg-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($u['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- /pg -->
    </div><!-- /main -->

    <script>
        /* ── Chart data ── */
        const ML = <?php echo json_encode($monthLabels); ?>,
            NM = <?php echo json_encode($notesM);      ?>,
            BM = <?php echo json_encode($booksM);      ?>,
            PM = <?php echo json_encode($papersM);     ?>,
            UM = <?php echo json_encode($usersM);      ?>,
            SL = <?php echo json_encode($subjLabels);  ?>,
            SC = <?php echo json_encode($subjCounts);  ?>,
            APP = <?php echo $c['approved']; ?>,
            PEN = <?php echo $c['pending'];  ?>,
            REJ = <?php echo $c['rejected']; ?>,
            CN = <?php echo $c['notes'];    ?>,
            CB = <?php echo $c['books'];    ?>,
            CP = <?php echo $c['papers'];   ?>;

        Chart.defaults.font.family = "'DM Sans',sans-serif";
        Chart.defaults.color = '#94a3b8';

        new Chart('lineChart', {
            type: 'line',
            data: {
                labels: ML,
                datasets: [{
                        label: 'Notes',
                        data: NM,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.07)',
                        tension: .4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#2563eb'
                    },
                    {
                        label: 'Books',
                        data: BM,
                        borderColor: '#0d9488',
                        backgroundColor: 'rgba(13,148,136,.07)',
                        tension: .4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#0d9488'
                    },
                    {
                        label: 'Papers',
                        data: PM,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124,58,237,.05)',
                        tension: .4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#7c3aed'
                    },
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 8,
                            padding: 12,
                            usePointStyle: true
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,.04)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        new Chart('donut1', {
            type: 'doughnut',
            data: {
                labels: ['Notes', 'Books', 'Papers'],
                datasets: [{
                    data: [CN, CB, CP],
                    backgroundColor: ['#2563eb', '#0d9488', '#7c3aed'],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                cutout: '74%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        new Chart('approvalBar', {
            type: 'bar',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [APP, PEN, REJ],
                    backgroundColor: ['rgba(5,150,105,.15)', 'rgba(217,119,6,.15)', 'rgba(220,38,38,.15)'],
                    borderColor: ['#059669', '#d97706', '#dc2626'],
                    borderWidth: 2,
                    borderRadius: 7
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,.04)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        new Chart('userLine', {
            type: 'line',
            data: {
                labels: ML,
                datasets: [{
                    data: UM,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124,58,237,.08)',
                    tension: .4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#7c3aed'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,.04)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        if (SL.length) new Chart('subjBar', {
            type: 'bar',
            data: {
                labels: SL,
                datasets: [{
                    data: SC,
                    backgroundColor: 'rgba(37,99,235,.14)',
                    borderColor: '#2563eb',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,.04)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        /* ── Flash messages ── */
        const up = new URLSearchParams(window.location.search).get('s');
        if (up === 'success') Swal.fire({
            icon: 'success',
            title: 'Done!',
            timer: 2500,
            timerProgressBar: true,
            showConfirmButton: false
        });
        else if (up === 'failed') Swal.fire({
            icon: 'error',
            title: 'Failed',
            timer: 2500,
            showConfirmButton: false
        });
        if (up) history.replaceState(null, '', 'dashboard.php');
    </script>
</body>

</html>