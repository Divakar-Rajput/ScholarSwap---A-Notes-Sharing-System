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

$sa = $conn->prepare("SELECT first_name, last_name, role FROM admin_user WHERE admin_id=? LIMIT 1");
$sa->execute([$_SESSION['admin_id']]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);

// Filter options
$courses = $conn->query("SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course != '' ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);
$states  = $conn->query("SELECT DISTINCT state  FROM students WHERE state  IS NOT NULL AND state  != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$tStates = $conn->query("SELECT DISTINCT state  FROM tutors   WHERE state  IS NOT NULL AND state  != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$allStates = array_values(array_unique(array_merge($states, $tStates)));
sort($allStates);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Report Generator | ScholarSwap Admin</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <style>
        :root {
            --primary: #7A0C0C;
            --primary-dark: #5a0909;
            --primary-g: #9b1010;
            --primary-light: #fde8e8;
            --primary-xlight: #fff1f1;
            --accent: #F2B400;
            --accent-g: #d49c00;
            --accent-dark: #b45309;
            --accent-light: #fff4cc;
            --accent-xlight: #fff9e6;
            --gold: #b45309;
            --amber: #f59e0b;
            --amber-xlight: #fef3c7;
            --red: #dc2626;
            --red-s: #fee2e2;
            --green: #059669;
            --green-s: #d1fae5;
            --green-xlight: #dcfce7;
            --blue: var(--primary);
            --blue-s: #dbeafe;
            --blue-d: #1d4ed8;
            --teal: #0d9488;
            --teal-s: #ccfbf1;
            --purple: #7c3aed;
            --purple-s: #ede9fe;
            --sky: #0284c7;
            --sky-s: #e0f2fe;
            --rose: #e11d48;
            --rose-s: #ffe4e6;
            --surface: #ffffff;
            --bg: #fffaf5;
            --page-bg: #fffaf5;
            --border: rgba(122, 12, 12, 0.15);
            --border2: rgba(122, 12, 12, 0.25);
            --text: #000000;
            --text2: #767472;
            --text3: #3d3d3c;
            --r: 10px;
            --r2: 16px;
            --sh: var(--shadow-sm);
            --sh2: var(--shadow-md);
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

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--page-bg);
            color: var(--text);
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
            background: var(--primary-light);
            border-radius: 99px
        }

        /* ── Page head ── */
        .pg-head {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px
        }

        .pg-head h1 {
            font-size: 1.3rem;
            font-weight: 800
        }

        .pg-head p {
            font-size: .8rem;
            color: var(--text3);
            margin-top: 3px
        }

        /* ── Report type cards ── */
        .type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 24px
        }

        .type-card {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: var(--r2);
            padding: 16px 14px;
            cursor: pointer;
            transition: all .18s;
            text-align: center;
            position: relative;
        }

        .type-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md)
        }

        .type-card.sel {
            border-color: var(--primary);
            background: var(--primary-xlight);
            box-shadow: 0 0 0 3px rgba(122, 12, 12, .12)
        }

        .type-card .tc-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: var(--bg);
            transition: all .18s;
        }

        

        .type-card h4 {
            font-size: .82rem;
            font-weight: 700;
            color: var(--text)
        }

        .type-card p {
            font-size: .65rem;
            color: var(--text3);
            margin-top: 3px
        }

        .type-card .sel-check {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            font-size: .6rem;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .type-card.sel .sel-check {
            display: flex
        }

        /* ── Builder layout ── */
        .builder-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 16px;
            align-items: start
        }

        @media(max-width:900px) {
            .builder-grid {
                grid-template-columns: 1fr
            }
        }

        /* ── Panel ── */
        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden
        }

        .ph {
            padding: 12px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            color: var(--text3)
        }

        /* ── Fields ── */
        .fields-wrap {
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-height: 340px;
            overflow-y: auto
        }

        .field-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background .12s;
            border: 1px solid transparent;
            font-size: .8rem;
            color: var(--text2);
        }

        .field-item:hover {
            background: var(--primary-xlight);
            color: var(--primary)
        }

        .field-item.on {
            background: var(--primary-xlight);
            border-color: rgba(122, 12, 12, .2);
            color: var(--primary);
            font-weight: 600
        }

        .field-item input[type=checkbox] {
            width: 14px;
            height: 14px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0
        }

        .field-icon {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .6rem;
            flex-shrink: 0;
            background: var(--bg);
            color: var(--text3)
        }

        .field-item.on .field-icon {
            background: var(--primary);
            color: #fff
        }

        .field-group-label {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text3);
            padding: 8px 10px 4px;
            margin-top: 4px
        }

        /* ── Filters ── */
        .filters-wrap {
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px
        }

        .frow {
            display: flex;
            flex-direction: column;
            gap: 4px
        }

        .flbl {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--text3)
        }

        .fsel,
        .finp {
            width: 100%;
            padding: 8px 11px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: .81rem;
            color: var(--text2);
            background: var(--surface);
            outline: none;
            transition: border-color .15s;
            font-family: inherit;
        }

        .fsel:focus,
        .finp:focus {
            border-color: var(--primary)
        }

        /* ── Preview table ── */
        .preview-wrap {
            overflow-x: auto
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        thead th {
            padding: 8px 11px;
            background: var(--page-bg);
            font-size: .58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            text-align: left
        }

        tbody td {
            padding: 9px 11px;
            font-size: .79rem;
            color: var(--text2);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            white-space: nowrap;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis
        }

        tbody tr:last-child td {
            border-bottom: none
        }

        tbody tr:hover td {
            background: var(--primary-xlight)
        }

        tbody tr.banned-row td {
            background: var(--primary-xlight);
            font-style: italic;
            color: var(--primary-dark)
        }

        /* ── Badges ── */
        .bdg {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 7px;
            border-radius: 99px;
            font-size: .6rem;
            font-weight: 700;
            white-space: nowrap
        }

        .bdg-active {
            background: var(--green-s);
            color: #065f46
        }

        .bdg-inactive,
        .bdg-banned {
            background: var(--rose-s);
            color: var(--rose)
        }

        .bdg-verified {
            background: var(--sky-s);
            color: var(--sky)
        }

        .bdg-unverified {
            background: var(--amber-s);
            color: #92400e
        }

        .bdg-approved {
            background: var(--green-s);
            color: var(--green)
        }

        .bdg-pending {
            background: var(--amber-s);
            color: var(--amber)
        }

        .bdg-rejected {
            background: var(--red-s);
            color: var(--red)
        }

        .bdg-note {
            background: var(--primary-xlight);
            color: var(--primary)
        }

        .bdg-book {
            background: var(--teal-s);
            color: var(--teal)
        }

        .bdg-fulfilled {
            background: var(--green-s);
            color: var(--green)
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            font-size: .8rem;
            font-weight: 600;
            transition: all .14s;
            font-family: inherit;
            white-space: nowrap
        }

        .btn:active {
            transform: scale(.96)
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 14px rgba(122, 12, 12, .25)
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            box-shadow: 0 6px 20px rgba(122, 12, 12, .35);
            transform: translateY(-1px)
        }

        .btn-dl {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: #fff;
            box-shadow: 0 4px 14px rgba(122, 12, 12, .3)
        }

        .btn-dl:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(122, 12, 12, .4)
        }

        .btn-dl:disabled {
            opacity: .5;
            pointer-events: none
        }

        .btn-ghost {
            background: var(--surface);
            color: var(--text2);
            border: 1.5px solid var(--border)
        }

        .btn-ghost:hover {
            background: var(--bg)
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: .74rem;
            border-radius: 7px
        }

        .btn-sel-all {
            background: var(--primary-xlight);
            color: var(--primary);
            border: none;
            font-size: .72rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer
        }

        .btn-sel-all:hover {
            background: var(--primary);
            color: #fff
        }

        /* ── Action bar ── */
        .action-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            background: var(--page-bg)
        }

        .action-bar .left {
            flex: 1;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap
        }

        .rec-count {
            font-size: .78rem;
            color: var(--text3);
            padding: 5px 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 7px
        }

        .rec-count strong {
            color: var(--text);
            font-weight: 700
        }

        /* ── Empty / loading ── */
        .empty-state {
            padding: 52px;
            text-align: center;
            color: var(--text3)
        }

        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 12px;
            opacity: .15
        }

        .empty-state h3 {
            font-size: .95rem;
            color: var(--text2);
            margin-bottom: 4px
        }

        .empty-state p {
            font-size: .8rem
        }

        .loading-state {
            padding: 40px;
            text-align: center
        }

        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin .7s linear infinite;
            margin: 0 auto 12px
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        /* ── Format pills ── */
        .format-pills {
            display: flex;
            gap: 6px
        }

        .fp {
            padding: 6px 14px;
            border-radius: 99px;
            border: 1.5px solid var(--border);
            font-size: .74rem;
            font-weight: 600;
            cursor: pointer;
            background: var(--surface);
            color: var(--text3);
            transition: all .14s
        }

        .fp.on {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary)
        }

        /* ── Report info bar ── */
        .report-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: .76rem;
            color: var(--text3);
            padding: 10px 18px;
            background: var(--primary-xlight);
            border-bottom: 1px solid var(--border)
        }

        .report-info strong {
            color: var(--primary)
        }

        .ri-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--border2);
            flex-shrink: 0
        }

        /* ── Progress bar ── */
        .prog-bar-wrap {
            margin: 6px 0
        }

        .prog-bar {
            height: 5px;
            background: var(--border);
            border-radius: 99px;
            overflow: hidden
        }

        .prog-fill {
            height: 100%;
            border-radius: 99px;
            background: var(--primary);
            width: 0;
            transition: width .4s
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
                    <h1><i class="fas fa-file-chart-column" style="color:var(--primary);margin-right:8px;font-size:1.1rem"></i>Report Generator</h1>
                    <p>Build, preview and export custom reports for any data set</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <span id="genStatus" style="font-size:.76rem;color:var(--text3);display:none"></span>
                    <div class="format-pills">
                        <button class="fp on" onclick="setFmt('pdf',this)"><i class="fas fa-file-pdf" style="font-size:.7rem"></i> PDF</button>
                        <button class="fp" onclick="setFmt('csv',this)"><i class="fas fa-file-csv" style="font-size:.7rem"></i> CSV</button>
                    </div>
                    <button class="btn btn-dl" id="btnGenerate" onclick="generateReport()" disabled>
                        <i class="fas fa-download"></i> Generate Report
                    </button>
                </div>
            </div>

            <!-- ── STEP 1: Report Type ── -->
            <div style="margin-bottom:8px">
                <div style="font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text3);margin-bottom:10px;display:flex;align-items:center;gap:6px">
                    <span style="width:20px;height:20px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800">1</span>
                    Select Report Type
                </div>
                <div class="type-grid">
                    <div class="type-card" onclick="selectType('students',this)" data-type="students">
                        <div class="sel-check"><i class="fas fa-check"></i></div>
                        <div class="tc-icon" style="color:var(--blue)"><i class="fas fa-user-graduate"></i></div>
                        <h4>Students</h4>
                        <p>Full student profiles, contacts &amp; activity</p>
                    </div>
                    <div class="type-card" onclick="selectType('tutors',this)" data-type="tutors">
                        <div class="sel-check"><i class="fas fa-check"></i></div>
                        <div class="tc-icon" style="color:var(--orange)"><i class="fas fa-chalkboard-user"></i></div>
                        <h4>Tutors</h4>
                        <p>Tutor profiles, subjects &amp; credentials</p>
                    </div>
                    <div class="type-card" onclick="selectType('users',this)" data-type="users">
                        <div class="sel-check"><i class="fas fa-check"></i></div>
                        <div class="tc-icon" style="color:var(--purple)"><i class="fas fa-users"></i></div>
                        <h4>All Users</h4>
                        <p>Combined user registry with roles</p>
                    </div>
                    <div class="type-card" onclick="selectType('content',this)" data-type="content">
                        <div class="sel-check"><i class="fas fa-check"></i></div>
                        <div class="tc-icon" style="color:var(--teal)"><i class="fas fa-book-open"></i></div>
                        <h4>Content</h4>
                        <p>Notes, books, approval status &amp; stats</p>
                    </div>
                    <div class="type-card" onclick="selectType('requests',this)" data-type="requests">
                        <div class="sel-check"><i class="fas fa-check"></i></div>
                        <div class="tc-icon" style="color:var(--amber)"><i class="fas fa-inbox"></i></div>
                        <h4>Requests</h4>
                        <p>Material requests, status &amp; fulfilment</p>
                    </div>
                    <div class="type-card" onclick="selectType('dashboard',this)" data-type="dashboard">
                        <div class="sel-check"><i class="fas fa-check"></i></div>
                        <div class="tc-icon" style="color:var(--green)"><i class="fas fa-chart-line"></i></div>
                        <h4>Dashboard</h4>
                        <p>Platform overview &amp; monthly trends</p>
                    </div>
                </div>
            </div>

            <!-- ── STEP 2: Builder ── -->
            <div id="builderWrap" style="display:none">
                <div style="font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text3);margin-bottom:10px;display:flex;align-items:center;gap:6px">
                    <span style="width:20px;height:20px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800">2</span>
                    Configure Fields &amp; Filters
                </div>
                <div class="builder-grid">
                    <!-- Left: Fields + Filters -->
                    <div style="display:flex;flex-direction:column;gap:14px">
                        <!-- Fields -->
                        <div class="panel">
                            <div class="ph">
                                <span class="pt">Fields to Include</span>
                                <button class="btn-sel-all" onclick="selAllFields()">All</button>
                            </div>
                            <div class="fields-wrap" id="fieldsWrap"></div>
                        </div>
                        <!-- Filters -->
                        <div class="panel">
                            <div class="ph"><span class="pt">Filters</span><span class="ph-sub" id="filterCount">None active</span></div>
                            <div class="filters-wrap" id="filtersWrap"></div>
                        </div>
                        <!-- Preview btn -->
                        <button class="btn btn-primary" onclick="previewReport()" id="btnPreview" style="width:100%">
                            <i class="fas fa-eye"></i> Preview Data
                        </button>
                    </div>

                    <!-- Right: Preview -->
                    <div class="panel" style="min-width:0">
                        <div id="reportInfoBar" class="report-info" style="display:none"></div>
                        <div id="previewArea">
                            <div class="empty-state">
                                <i class="fas fa-table-list"></i>
                                <h3>No preview yet</h3>
                                <p>Configure fields &amp; filters, then click <strong>Preview Data</strong></p>
                            </div>
                        </div>
                        <div class="action-bar" id="actionBar" style="display:none">
                            <div class="left">
                                <div class="rec-count" id="recCount"></div>
                                <span style="font-size:.72rem;color:var(--text3)">Showing first 20 rows in preview</span>
                            </div>
                            <button class="btn btn-dl btn-sm" id="btnGenerate2" onclick="generateReport()">
                                <i class="fas fa-download"></i> Generate Full Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /pg -->
    </div><!-- /main -->

    <script>
        /* ════════════════════════════════════════════════════
   REPORT GENERATOR — ScholarSwap Admin
════════════════════════════════════════════════════ */

        let currentType = null;
        let currentData = null;
        let exportFmt = 'pdf';

        /* ── All field definitions per type ── */
        const FIELD_DEFS = {
            students: [{
                    group: 'Identity'
                },
                {
                    key: 'full_name',
                    label: 'Full Name',
                    icon: 'fa-user'
                },
                {
                    key: 'username',
                    label: 'Username',
                    icon: 'fa-at'
                },
                {
                    key: 'email',
                    label: 'Email',
                    icon: 'fa-envelope'
                },
                {
                    key: 'phone',
                    label: 'Phone',
                    icon: 'fa-phone'
                },
                {
                    key: 'gender',
                    label: 'Gender',
                    icon: 'fa-venus-mars'
                },
                {
                    key: 'dob_fmt',
                    label: 'Date of Birth',
                    icon: 'fa-cake-candles'
                },
                {
                    group: 'Academic'
                },
                {
                    key: 'course',
                    label: 'Course',
                    icon: 'fa-graduation-cap'
                },
                {
                    key: 'institution',
                    label: 'Institution',
                    icon: 'fa-building-columns'
                },
                {
                    key: 'subjects_of_interest',
                    label: 'Subjects',
                    icon: 'fa-book'
                },
                {
                    group: 'Location'
                },
                {
                    key: 'state',
                    label: 'State',
                    icon: 'fa-map-pin'
                },
                {
                    key: 'district',
                    label: 'District',
                    icon: 'fa-location-dot'
                },
                {
                    key: 'current_address',
                    label: 'Current Address',
                    icon: 'fa-house'
                },
                {
                    key: 'permanent_address',
                    label: 'Permanent Address',
                    icon: 'fa-home'
                },
                {
                    group: 'Account'
                },
                {
                    key: 'status',
                    label: 'Status',
                    icon: 'fa-circle'
                },
                {
                    key: 'verified_str',
                    label: 'Verified',
                    icon: 'fa-badge-check'
                },
                {
                    key: 'joined_fmt',
                    label: 'Date Joined',
                    icon: 'fa-calendar'
                },
                {
                    key: 'last_login_fmt',
                    label: 'Last Login',
                    icon: 'fa-clock'
                },
                {
                    group: 'Activity'
                },
                {
                    key: 'note_count',
                    label: 'Notes Uploaded',
                    icon: 'fa-file-lines'
                },
                {
                    key: 'book_count',
                    label: 'Books Uploaded',
                    icon: 'fa-book-open'
                },
                {
                    key: 'dl_count',
                    label: 'Downloads',
                    icon: 'fa-download'
                },
            ],
            tutors: [{
                    group: 'Identity'
                },
                {
                    key: 'full_name',
                    label: 'Full Name',
                    icon: 'fa-user'
                },
                {
                    key: 'username',
                    label: 'Username',
                    icon: 'fa-at'
                },
                {
                    key: 'email',
                    label: 'Email',
                    icon: 'fa-envelope'
                },
                {
                    key: 'phone',
                    label: 'Phone',
                    icon: 'fa-phone'
                },
                {
                    key: 'gender',
                    label: 'Gender',
                    icon: 'fa-venus-mars'
                },
                {
                    key: 'dob',
                    label: 'Date of Birth',
                    icon: 'fa-cake-candles'
                },
                {
                    group: 'Professional'
                },
                {
                    key: 'qualification',
                    label: 'Qualification',
                    icon: 'fa-certificate'
                },
                {
                    key: 'experience_years',
                    label: 'Experience (yrs)',
                    icon: 'fa-briefcase'
                },
                {
                    key: 'subjects_taught',
                    label: 'Subjects Taught',
                    icon: 'fa-chalkboard'
                },
                {
                    key: 'institution',
                    label: 'Institution',
                    icon: 'fa-building-columns'
                },
                {
                    group: 'Location'
                },
                {
                    key: 'state',
                    label: 'State',
                    icon: 'fa-map-pin'
                },
                {
                    key: 'district',
                    label: 'District',
                    icon: 'fa-location-dot'
                },
                {
                    key: 'current_address',
                    label: 'Address',
                    icon: 'fa-house'
                },
                {
                    group: 'Account'
                },
                {
                    key: 'status',
                    label: 'Status',
                    icon: 'fa-circle'
                },
                {
                    key: 'verified_str',
                    label: 'Verified',
                    icon: 'fa-badge-check'
                },
                {
                    key: 'joined_fmt',
                    label: 'Date Joined',
                    icon: 'fa-calendar'
                },
                {
                    key: 'last_login_fmt',
                    label: 'Last Login',
                    icon: 'fa-clock'
                },
                {
                    group: 'Activity'
                },
                {
                    key: 'note_count',
                    label: 'Notes Uploaded',
                    icon: 'fa-file-lines'
                },
                {
                    key: 'book_count',
                    label: 'Books Uploaded',
                    icon: 'fa-book-open'
                },
            ],
            users: [{
                    group: 'Identity'
                },
                {
                    key: 'username',
                    label: 'Username',
                    icon: 'fa-at'
                },
                {
                    key: 'email',
                    label: 'Email',
                    icon: 'fa-envelope'
                },
                {
                    key: 'phone',
                    label: 'Phone',
                    icon: 'fa-phone'
                },
                {
                    key: 'role',
                    label: 'Role',
                    icon: 'fa-id-badge'
                },
                {
                    group: 'Account'
                },
                {
                    key: 'status',
                    label: 'Status',
                    icon: 'fa-circle'
                },
                {
                    key: 'verified_str',
                    label: 'Verified',
                    icon: 'fa-badge-check'
                },
                {
                    key: 'joined_fmt',
                    label: 'Date Joined',
                    icon: 'fa-calendar'
                },
                {
                    key: 'last_login_fmt',
                    label: 'Last Login',
                    icon: 'fa-clock'
                },
            ],
            content: [{
                    group: 'Content'
                },
                {
                    key: 'title',
                    label: 'Title',
                    icon: 'fa-heading'
                },
                {
                    key: 'content_type',
                    label: 'Type',
                    icon: 'fa-file'
                },
                {
                    key: 'subject',
                    label: 'Subject',
                    icon: 'fa-book'
                },
                {
                    key: 'document_type',
                    label: 'Document Type',
                    icon: 'fa-file-alt'
                },
                {
                    key: 'language',
                    label: 'Language',
                    icon: 'fa-language'
                },
                {
                    key: 'class_level',
                    label: 'Class Level',
                    icon: 'fa-layer-group'
                },
                {
                    group: 'Status'
                },
                {
                    key: 'approval_status',
                    label: 'Approval Status',
                    icon: 'fa-check-circle'
                },
                {
                    key: 'created_fmt',
                    label: 'Upload Date',
                    icon: 'fa-calendar'
                },
                {
                    group: 'Stats'
                },
                {
                    key: 'download_count',
                    label: 'Downloads',
                    icon: 'fa-download'
                },
                {
                    key: 'view_count',
                    label: 'Views',
                    icon: 'fa-eye'
                },
                {
                    key: 'rating',
                    label: 'Rating',
                    icon: 'fa-star'
                },
            ],
            requests: [{
                    group: 'Request Info'
                },
                {
                    key: 'tracking_number',
                    label: 'Tracking #',
                    icon: 'fa-radar'
                },
                {
                    key: 'ref_code',
                    label: 'Ref Code',
                    icon: 'fa-hashtag'
                },
                {
                    key: 'title',
                    label: 'Title',
                    icon: 'fa-heading'
                },
                {
                    key: 'material_type',
                    label: 'Type',
                    icon: 'fa-file'
                },
                {
                    key: 'priority',
                    label: 'Priority',
                    icon: 'fa-flag'
                },
                {
                    key: 'status',
                    label: 'Status',
                    icon: 'fa-circle'
                },
                {
                    group: 'Requester'
                },
                {
                    key: 'username',
                    label: 'Username',
                    icon: 'fa-at'
                },
                {
                    key: 'email',
                    label: 'Email',
                    icon: 'fa-envelope'
                },
                {
                    key: 'phone',
                    label: 'Phone',
                    icon: 'fa-phone'
                },
                {
                    group: 'Dates'
                },
                {
                    key: 'created_fmt',
                    label: 'Requested On',
                    icon: 'fa-calendar'
                },
                {
                    key: 'fulfilled_fmt',
                    label: 'Fulfilled On',
                    icon: 'fa-calendar-check'
                },
                {
                    group: 'Admin'
                },
                {
                    key: 'admin_note',
                    label: 'Admin Note',
                    icon: 'fa-comment'
                },
            ],
            dashboard: [{
                    group: 'Overview'
                },
                {
                    key: 'users',
                    label: 'Total Users',
                    icon: 'fa-users'
                },
                {
                    key: 'students',
                    label: 'Students',
                    icon: 'fa-user-graduate'
                },
                {
                    key: 'tutors',
                    label: 'Tutors',
                    icon: 'fa-chalkboard-user'
                },
                {
                    key: 'notes',
                    label: 'Notes',
                    icon: 'fa-file-lines'
                },
                {
                    key: 'books',
                    label: 'Books',
                    icon: 'fa-book'
                },
                {
                    key: 'verified',
                    label: 'Verified Users',
                    icon: 'fa-badge-check'
                },
                {
                    group: 'Approvals'
                },
                {
                    key: 'n_approved',
                    label: 'Notes Approved',
                    icon: 'fa-check'
                },
                {
                    key: 'n_pending',
                    label: 'Notes Pending',
                    icon: 'fa-hourglass'
                },
                {
                    key: 'b_approved',
                    label: 'Books Approved',
                    icon: 'fa-check'
                },
                {
                    key: 'b_pending',
                    label: 'Books Pending',
                    icon: 'fa-hourglass'
                },
                {
                    group: 'Requests'
                },
                {
                    key: 'rq_total',
                    label: 'Total Requests',
                    icon: 'fa-inbox'
                },
                {
                    key: 'rq_fulfilled',
                    label: 'Fulfilled',
                    icon: 'fa-circle-check'
                },
                {
                    key: 'rq_pending',
                    label: 'Pending',
                    icon: 'fa-clock'
                },
                {
                    group: 'Monthly Trends'
                },
                {
                    key: 'monthly',
                    label: 'Monthly Table',
                    icon: 'fa-table'
                },
                {
                    group: 'Top Content'
                },
                {
                    key: 'top_downloads',
                    label: 'Top Downloads',
                    icon: 'fa-trophy'
                },
            ],
        };

        const FILTER_DEFS = {
            students: [{
                    key: 'status',
                    label: 'Status',
                    type: 'sel',
                    opts: [
                        ['', 'All'],
                        ['active', 'Active'],
                        ['banned', 'Banned'],
                        ['verified', 'Verified'],
                        ['unverified', 'Unverified']
                    ]
                },
                {
                    key: 'course',
                    label: 'Course',
                    type: 'sel',
                    opts: [
                        ['', 'All Courses'], ...<?php echo json_encode(array_map(fn($c) => [$c, $c], $courses)); ?>
                    ]
                },
                {
                    key: 'state',
                    label: 'State',
                    type: 'sel',
                    opts: [
                        ['', 'All States'], ...<?php echo json_encode(array_map(fn($s) => [$s, $s], $states)); ?>
                    ]
                },
                {
                    key: 'gender',
                    label: 'Gender',
                    type: 'sel',
                    opts: [
                        ['', 'All'],
                        ['male', 'Male'],
                        ['female', 'Female'],
                        ['other', 'Other']
                    ]
                },
            ],
            tutors: [{
                    key: 'status',
                    label: 'Status',
                    type: 'sel',
                    opts: [
                        ['', 'All'],
                        ['active', 'Active'],
                        ['banned', 'Banned'],
                        ['verified', 'Verified']
                    ]
                },
                {
                    key: 'state',
                    label: 'State',
                    type: 'sel',
                    opts: [
                        ['', 'All States'], ...<?php echo json_encode(array_map(fn($s) => [$s, $s], $tStates)); ?>
                    ]
                },
            ],
            users: [{
                    key: 'role',
                    label: 'Role',
                    type: 'sel',
                    opts: [
                        ['', 'All'],
                        ['student', 'Student'],
                        ['tutor', 'Tutor']
                    ]
                },
                {
                    key: 'status',
                    label: 'Status',
                    type: 'sel',
                    opts: [
                        ['', 'All'],
                        ['active', 'Active'],
                        ['banned', 'Banned'],
                        ['verified', 'Verified']
                    ]
                },
            ],
            content: [{
                key: 'approval',
                label: 'Approval',
                type: 'sel',
                opts: [
                    ['', 'All'],
                    ['approved', 'Approved'],
                    ['pending', 'Pending'],
                    ['rejected', 'Rejected']
                ]
            }, ],
            requests: [{
                    key: 'status',
                    label: 'Status',
                    type: 'sel',
                    opts: [
                        ['', 'All'],
                        ['Pending', 'Pending'],
                        ['In Progress', 'In Progress'],
                        ['Fulfilled', 'Fulfilled'],
                        ['Cancelled', 'Cancelled'],
                        ['Cannot Fulfil', 'Cannot Fulfil']
                    ]
                },
                {
                    key: 'priority',
                    label: 'Priority',
                    type: 'sel',
                    opts: [
                        ['', 'All'],
                        ['High', 'High'],
                        ['Medium', 'Medium'],
                        ['Low', 'Low']
                    ]
                },
            ],
            dashboard: [],
        };

        /* ── Selected fields ── */
        let selectedFields = new Set();

        function selectType(type, card) {
            currentType = type;
            currentData = null;
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('sel'));
            card.classList.add('sel');
            document.getElementById('builderWrap').style.display = 'block';
            document.getElementById('builderWrap').scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
            renderFields(type);
            renderFilters(type);
            resetPreview();
            updateGenBtn();
        }

        function renderFields(type) {
            const defs = FIELD_DEFS[type] || [];
            const wrap = document.getElementById('fieldsWrap');
            wrap.innerHTML = '';
            selectedFields.clear();
            defs.forEach(f => {
                if (f.group) {
                    const g = document.createElement('div');
                    g.className = 'field-group-label';
                    g.textContent = f.group;
                    wrap.appendChild(g);
                    return;
                }
                // Default: select first key fields
                const defaultOn = ['full_name', 'username', 'email', 'phone', 'course', 'state', 'status', 'verified_str', 'joined_fmt', 'title', 'content_type', 'approval_status', 'tracking_number', 'priority'].includes(f.key);
                if (defaultOn) selectedFields.add(f.key);
                const item = document.createElement('label');
                item.className = 'field-item' + (defaultOn ? ' on' : '');
                item.innerHTML = `
            <input type="checkbox" ${defaultOn?'checked':''} onchange="toggleField('${f.key}',this)">
            <div class="field-icon"><i class="fas ${f.icon}"></i></div>
            ${f.label}`;
                wrap.appendChild(item);
            });
        }

        function toggleField(key, cb) {
            cb.checked ? selectedFields.add(key) : selectedFields.delete(key);
            cb.closest('.field-item').classList.toggle('on', cb.checked);
            if (currentData) renderPreviewTable(currentData);
        }

        function selAllFields() {
            document.querySelectorAll('#fieldsWrap input[type=checkbox]').forEach(cb => {
                cb.checked = true;
                cb.closest('.field-item').classList.add('on');
                const key = cb.getAttribute('onchange').match(/'([^']+)'/)?.[1];
                if (key) selectedFields.add(key);
            });
            if (currentData) renderPreviewTable(currentData);
        }

        function renderFilters(type) {
            const defs = FILTER_DEFS[type] || [];
            const wrap = document.getElementById('filtersWrap');
            wrap.innerHTML = '';
            if (!defs.length) {
                wrap.innerHTML = '<div style="font-size:.78rem;color:var(--text3);padding:8px 2px">No filters available for this report type.</div>';
                return;
            }
            defs.forEach(f => {
                const row = document.createElement('div');
                row.className = 'frow';
                let input = '';
                if (f.type === 'sel') {
                    const opts = f.opts.map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
                    input = `<select class="fsel" id="f_${f.key}" onchange="countFilters()">${opts}</select>`;
                } else {
                    input = `<input class="finp" type="text" id="f_${f.key}" placeholder="Filter by ${f.label.toLowerCase()}…" oninput="countFilters()">`;
                }
                row.innerHTML = `<div class="flbl">${f.label}</div>${input}`;
                wrap.appendChild(row);
            });
            countFilters();
        }

        function countFilters() {
            const defs = FILTER_DEFS[currentType] || [];
            let active = 0;
            defs.forEach(f => {
                const el = document.getElementById('f_' + f.key);
                if (el && el.value.trim()) active++;
            });
            document.getElementById('filterCount').textContent = active ? `${active} active` : 'None active';
        }

        function getFilters() {
            const defs = FILTER_DEFS[currentType] || [];
            const out = {};
            defs.forEach(f => {
                const el = document.getElementById('f_' + f.key);
                if (el && el.value.trim()) out[f.key] = el.value.trim();
            });
            return out;
        }

        function getFieldDefs() {
            return (FIELD_DEFS[currentType] || []).filter(f => !f.group && selectedFields.has(f.key));
        }

        /* ── Preview ── */
        async function previewReport() {
            if (!currentType) return;
            const btn = document.getElementById('btnPreview');
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;margin:0"></div> Loading…';
            document.getElementById('previewArea').innerHTML = '<div class="loading-state"><div class="spinner"></div><div style="font-size:.8rem;color:var(--text3)">Fetching data…</div></div>';

            try {
                const fd = new FormData();
                fd.append('type', currentType);
                fd.append('fields', JSON.stringify([...selectedFields]));
                fd.append('filters', JSON.stringify(getFilters()));
                const res = await fetch('auth/report_data.php', {
                    method: 'POST',
                    body: fd
                });
                const rawText = await res.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (e) {
                    throw new Error('Server error: ' + rawText.replace(/<[^>]+>/g, '').trim().slice(0, 200));
                }
                if (data.error) throw new Error(data.error);
                currentData = data;
                renderPreviewTable(data);
                document.getElementById('actionBar').style.display = 'flex';
                document.getElementById('btnGenerate').disabled = false;
                const g2 = document.getElementById('btnGenerate2');
                if (g2) g2.disabled = false;
            } catch (err) {
                document.getElementById('previewArea').innerHTML = `<div class="empty-state"><i class="fas fa-triangle-exclamation" style="color:var(--red)"></i><h3>Error</h3><p>${err.message}</p></div>`;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-eye"></i> Preview Data';
            }
        }

        function renderPreviewTable(data) {
            const fieldDefs = getFieldDefs();
            if (!fieldDefs.length) {
                document.getElementById('previewArea').innerHTML = '<div class="empty-state"><i class="fas fa-table-list"></i><h3>No fields selected</h3><p>Check at least one field to include in the report.</p></div>';
                return;
            }

            // Info bar
            const infoBar = document.getElementById('reportInfoBar');
            infoBar.style.display = 'flex';
            infoBar.innerHTML = `
        <strong>${TYPE_LABELS[currentType]}</strong>
        <div class="ri-dot"></div>
        <span>${data.generated}</span>
        <div class="ri-dot"></div>
        <span><strong>${data.count ?? (data.rows?.length ?? 0)}</strong> records</span>
        <div class="ri-dot"></div>
        <span>${fieldDefs.length} fields selected</span>`;

            // Update rec count
            const total = data.count ?? (data.rows?.length ?? 0);
            document.getElementById('recCount').innerHTML = `<strong>${total}</strong> total records`;

            if (currentType === 'dashboard') {
                renderDashboardPreview(data);
                return;
            }

            const rows = data.rows || [];
            const preview = rows.slice(0, 20);

            let html = '<div class="preview-wrap"><table><thead><tr>';
            fieldDefs.forEach(f => {
                html += `<th>${f.label}</th>`;
            });
            html += '</tr></thead><tbody>';

            if (!preview.length) {
                html += `<tr><td colspan="${fieldDefs.length}" style="text-align:center;padding:24px;color:var(--text3)">No records match the filters.</td></tr>`;
            } else {
                preview.forEach(row => {
                    const banned = (row.status === 'Banned' || row.is_active == 0);
                    html += `<tr class="${banned?'banned-row':''}">`;
                    fieldDefs.forEach(f => {
                        const val = row[f.key] ?? '—';
                        html += `<td>${formatCell(f.key, val)}</td>`;
                    });
                    html += '</tr>';
                });
            }
            html += '</tbody></table></div>';
            document.getElementById('previewArea').innerHTML = html;
        }

        function renderDashboardPreview(data) {
            const C = data.counts;
            let html = '<div style="padding:16px">';
            html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:16px">';
            const kpis = [
                ['Users', C.users],
                ['Students', C.students],
                ['Tutors', C.tutors],
                ['Notes', C.notes],
                ['Books', C.books],
                ['Verified', C.verified],
                ['Approved', (C.n_approved || 0) + (C.b_approved || 0)],
                ['Pending', (C.n_pending || 0) + (C.b_pending || 0)],
                ['Requests', C.rq_total],
                ['Fulfilled', C.rq_fulfilled],
            ];
            kpis.forEach(([l, v]) => {
                if (v === undefined) return;
                html += `<div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center">
            <div style="font-family:'Space Grotesk',sans-serif;font-size:1.3rem;font-weight:800">${v}</div>
            <div style="font-size:.65rem;color:var(--text3);margin-top:3px">${l}</div>
        </div>`;
            });
            html += '</div>';
            if (data.monthly && selectedFields.has('monthly')) {
                html += '<div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-bottom:8px">Monthly Trends</div>';
                html += '<div class="preview-wrap"><table><thead><tr><th>Month</th><th>Notes</th><th>Books</th><th>New Users</th></tr></thead><tbody>';
                data.monthly.forEach(m => {
                    html += `<tr><td>${m.month}</td><td>${m.notes}</td><td>${m.books}</td><td>${m.users}</td></tr>`;
                });
                html += '</tbody></table></div>';
            }
            if (data.top_downloads && selectedFields.has('top_downloads')) {
                html += '<div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin:12px 0 8px">Top Downloads</div>';
                html += '<div class="preview-wrap"><table><thead><tr><th>#</th><th>Title</th><th>Type</th><th>Downloads</th></tr></thead><tbody>';
                data.top_downloads.forEach((r, i) => {
                    html += `<tr><td>${i+1}</td><td>${r.title}</td><td><span class="bdg bdg-${r.type.toLowerCase()}">${r.type}</span></td><td>${r.download_count}</td></tr>`;
                });
                html += '</tbody></table></div>';
            }
            html += '</div>';
            document.getElementById('previewArea').innerHTML = html;
        }

        function formatCell(key, val) {
            if (val === null || val === undefined || val === '') return '<span style="color:var(--text3)">—</span>';
            const s = String(val);
            if (key === 'status') {
                const cls = s === 'Active' ? 'bdg-active' : 'bdg-inactive';
                return `<span class="bdg ${cls}">${s}</span>`;
            }
            if (key === 'verified_str') {
                return `<span class="bdg ${s==='Verified'?'bdg-verified':'bdg-unverified'}">${s}</span>`;
            }
            if (key === 'approval_status') {
                const cls = s === 'approved' ? 'bdg-approved' : s === 'pending' ? 'bdg-pending' : 'bdg-rejected';
                return `<span class="bdg ${cls}">${s}</span>`;
            }
            if (key === 'content_type') {
                return `<span class="bdg bdg-${s}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`;
            }
            if (key === 'priority') {
                const c = s === 'High' ? 'var(--red)' : s === 'Medium' ? 'var(--amber)' : 'var(--green)';
                return `<span style="color:${c};font-weight:700;font-size:.74rem">${s}</span>`;
            }
            return `<span title="${s.replace(/"/g,'&quot;')}">${s.length>35?s.slice(0,33)+'…':s}</span>`;
        }

        const TYPE_LABELS = {
            students: 'Students',
            tutors: 'Tutors',
            users: 'All Users',
            content: 'Content',
            requests: 'Requests',
            dashboard: 'Dashboard'
        };

        function resetPreview() {
            currentData = null;
            document.getElementById('previewArea').innerHTML = '<div class="empty-state"><i class="fas fa-table-list"></i><h3>No preview yet</h3><p>Configure fields &amp; filters, then click <strong>Preview Data</strong></p></div>';
            document.getElementById('reportInfoBar').style.display = 'none';
            document.getElementById('actionBar').style.display = 'none';
            document.getElementById('btnGenerate').disabled = true;
        }

        function updateGenBtn() {
            document.getElementById('btnGenerate').disabled = !currentData;
        }

        function setFmt(fmt, btn) {
            exportFmt = fmt;
            document.querySelectorAll('.fp').forEach(b => b.classList.remove('on'));
            btn.classList.add('on');
        }

        /* ═══════════════════════════════════════════
           PDF GENERATION — Black & White, jsPDF
        ═══════════════════════════════════════════ */
        async function generateReport() {
            if (!currentData) {
                await previewReport();
                if (!currentData) return;
            }
            if (exportFmt === 'csv') {
                generateCSV();
                return;
            }

            const btn = document.getElementById('btnGenerate');
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;margin:0"></div> Generating PDF…';

            await new Promise(r => setTimeout(r, 60));

            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF({
                    orientation: 'l',
                    unit: 'mm',
                    format: 'a4'
                });
                const PW = doc.internal.pageSize.getWidth(),
                    PH = doc.internal.pageSize.getHeight();
                const ML = 10,
                    MR = 10,
                    CW = PW - ML - MR,
                    FOOTH = 7;
                const C = {
                    dk: [15, 23, 42],
                    md: [71, 85, 105],
                    lt: [148, 163, 184],
                    xl: [226, 232, 240],
                    wh: [255, 255, 255],
                    bgH: [122, 12, 12],
                    bgA: [255, 249, 249],
                    bgT: [253, 232, 232],
                    bdr: [245, 220, 220],
                    acc: [122, 12, 12]
                };
                let y = 0,
                    pg = 1;

                const sf = (st, sz, col) => {
                    doc.setFont('helvetica', st);
                    doc.setFontSize(sz);
                    if (col) doc.setTextColor(...col);
                };
                const fr = (x, w, ry, rh, f, s) => {
                    if (f) doc.setFillColor(...f);
                    if (s) doc.setDrawColor(...s);
                    doc.rect(x, ry, w, rh, f && s ? 'FD' : f ? 'F' : 'D');
                };
                const addFooter = () => {
                    doc.setDrawColor(...C.bdr);
                    doc.line(ML, PH - FOOTH, PW - MR, PH - FOOTH);
                    sf('normal', 6.5, C.lt);
                    doc.text(`ScholarSwap Admin — ${TYPE_LABELS[currentType]} Report  ·  ${currentData.generated}  ·  Page ${pg}  ·  Confidential`, ML, PH - 3);
                    pg++;
                };
                const needPg = (n) => {
                    if (y + n > PH - FOOTH - 4) {
                        addFooter();
                        doc.addPage();
                        y = 14;
                        return true;
                    }
                    return false;
                };

                /* ── COVER (light theme) ── */
                // Outer border
                doc.setDrawColor(122, 12, 12);
                doc.setLineWidth(0.8);
                doc.rect(ML - 2, 4, PW - ML - MR + 4, 38);
                doc.setLineWidth(0.2);
                // Accent left stripe
                fr(ML - 2, 3.5, 4, 38, [122, 12, 12], null);
                // Title area (white background)
                sf('bold', 22, [15, 23, 42]);
                doc.text('SCHOLARSWAP', ML + 8, 18);
                sf('normal', 10, [71, 85, 105]);
                doc.text(`${TYPE_LABELS[currentType]} Report`, ML + 8, 26);
                sf('normal', 7.5, [148, 163, 184]);
                doc.text(`Generated: ${currentData.generated}`, ML + 8, 33);
                // Confidential pill top-right
                fr(PW - 60, 50, 6, [253, 232, 232], null);
                doc.setDrawColor(122, 12, 12);
                doc.setDrawColor(122, 12, 12);
                doc.rect(PW - 60, 6, 50, 10);
                sf('bold', 6.5, [122, 12, 12]);
                doc.text('CONFIDENTIAL — INTERNAL USE ONLY', PW - 58, 12.5);
                // Divider line under cover
                doc.setDrawColor(...C.bdr);
                doc.line(ML, 44, PW - MR, 44);
                y = 52;

                if (currentType === 'dashboard') {
                    generateDashboardPDF(doc, C, sf, fr, needPg, addFooter, ML, CW, PW, MR);
                } else {
                    const rows = currentData.rows || [];
                    const fDefs = getFieldDefs();

                    /* Summary strip */
                    const total = currentData.count ?? rows.length;
                    const kpis = [{
                            l: 'Total Records',
                            v: total
                        },
                        {
                            l: 'Active',
                            v: rows.filter(r => r.status === 'Active').length
                        },
                        {
                            l: 'Banned',
                            v: rows.filter(r => r.status === 'Banned').length
                        },
                        {
                            l: 'Verified',
                            v: rows.filter(r => r.verified_str === 'Verified').length
                        },
                    ].filter((k, i) => i === 0 || fDefs.some(f => ['status', 'verified_str'].includes(f.key)));
                    const kw = CW / Math.min(kpis.length, 6);
                    kpis.forEach((k, i) => {
                        const x = ML + i * kw;
                        fr(x + 1.5, kw - 3, y, 15, [255, 241, 241], C.acc);
                        sf('bold', 13, C.dk);
                        doc.text(String(k.v), x + 4, y + 8.5);
                        sf('normal', 6.5, C.lt);
                        doc.text(k.l, x + 4, y + 13);
                    });
                    y += 20;

                    /* Table — smart column widths based on field type */
                    const RH = 6.5,
                        HH = 8;

                    // Assign weight per field key — wider for text-heavy fields
                    const COL_WEIGHTS = {
                        full_name: 14,
                        username: 11,
                        email: 22,
                        phone: 14,
                        course: 12,
                        institution: 18,
                        state: 10,
                        district: 10,
                        gender: 8,
                        dob_fmt: 11,
                        dob: 11,
                        current_address: 20,
                        permanent_address: 20,
                        subjects_of_interest: 18,
                        qualification: 14,
                        experience_years: 8,
                        subjects_taught: 18,
                        status: 9,
                        verified_str: 9,
                        joined_fmt: 11,
                        last_login_fmt: 14,
                        note_count: 7,
                        book_count: 7,
                        dl_count: 7,
                        title: 22,
                        content_type: 8,
                        subject: 13,
                        document_type: 11,
                        language: 9,
                        class_level: 9,
                        approval_status: 10,
                        download_count: 9,
                        view_count: 9,
                        rating: 7,
                        created_fmt: 11,
                        tracking_number: 16,
                        ref_code: 10,
                        material_type: 11,
                        priority: 8,
                        admin_note: 20,
                        fulfilled_fmt: 11,
                        role: 9,
                    };
                    // Build proportional widths that sum to CW
                    const rawW = fDefs.map(f => COL_WEIGHTS[f.key] || 12);
                    const totalW = rawW.reduce((a, b) => a + b, 0);
                    const colWidths = rawW.map(w => (w / totalW) * CW);

                    const drawHeader = () => {
                        needPg(HH + RH);
                        fr(ML, CW, y, HH, C.bgH, null);
                        let xc = ML;
                        fDefs.forEach((f, ci) => {
                            sf('bold', 5.8, C.wh);
                            let h = f.label;
                            const mw = colWidths[ci] - 2.5;
                            while (h.length > 1 && doc.getTextWidth(h) > mw) h = h.slice(0, -2) + '…';
                            doc.text(h, xc + 1.5, y + HH - 2.5);
                            doc.setDrawColor(230, 180, 180);
                            doc.line(xc + colWidths[ci], y + 1, xc + colWidths[ci], y + HH - 1);
                            xc += colWidths[ci];
                        });
                        y += HH;
                    };
                    drawHeader();
                    rows.forEach((row, ri) => {
                        if (ri > 0 && ri % 28 === 0) {
                            needPg(HH + RH + 2);
                            drawHeader();
                        }
                        needPg(RH + 2);
                        const banned = row.status === 'Banned';
                        fr(ML, CW, y, RH, banned ? [255, 241, 242] : (ri % 2 === 1 ? C.bgA : [255, 255, 255]), null);
                        doc.setDrawColor(...C.bdr);
                        doc.line(ML, y + RH, ML + CW, y + RH);
                        let xc = ML;
                        fDefs.forEach((f, ci) => {
                            let val = String(row[f.key] ?? '—');
                            const mw = colWidths[ci] - 2.5;
                            // Truncate to fit column width
                            while (val.length > 1 && doc.getTextWidth(val) > mw) val = val.slice(0, -2) + '…';
                            sf(banned ? 'italic' : 'normal', 6.5, banned ? C.md : C.dk);
                            doc.text(val, xc + 1.5, y + RH - 2);
                            xc += colWidths[ci];
                        });
                        y += RH;
                    });
                    /* Totals */
                    needPg(RH + 2);
                    doc.setDrawColor(...C.acc);
                    doc.setLineWidth(0.4);
                    doc.line(ML, y, ML + CW, y);
                    doc.setLineWidth(0.2);
                    fr(ML, CW, y, RH + 1, C.bgT, null);
                    sf('bold', 7, C.acc);
                    doc.text(`TOTAL: ${total} records`, ML + 2, y + RH - 1.5);
                }

                addFooter();
                const fname = `ScholarSwap_${TYPE_LABELS[currentType].replace(/\s/g,'_')}_${new Date().toISOString().slice(0,10)}.pdf`;
                doc.save(fname);

            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'PDF Failed',
                    text: err.message,
                    confirmButtonColor: '#7A0C0C'
                });
                console.error(err);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-download"></i> Generate Report';
            }
        }

        function generateDashboardPDF(doc, C, sf, fr, needPg, addFooter, ML, CW, PW, MR) {
            const d = currentData;
            const cnts = d.counts;
            let y = 56;
            const RH = 6.5,
                HH = 8;
            const PH = doc.internal.pageSize.getHeight();

            const kpis = [{
                    l: 'Users',
                    v: cnts.users
                }, {
                    l: 'Students',
                    v: cnts.students
                }, {
                    l: 'Tutors',
                    v: cnts.tutors
                },
                {
                    l: 'Notes',
                    v: cnts.notes
                }, {
                    l: 'Books',
                    v: cnts.books
                }, {
                    l: 'Verified',
                    v: cnts.verified
                },
            ];
            const kw = CW / kpis.length;
            kpis.forEach((k, i) => {
                const x = ML + i * kw;
                fr(x + 1.5, kw - 3, y, 15, [255, 241, 241], [122, 12, 12]);
                sf('bold', 13, C.dk);
                doc.text(String(k.v ?? 0), x + 4, y + 8.5);
                sf('normal', 6.5, C.lt);
                doc.text(k.l, x + 4, y + 13);
            });
            y += 20;

            if (d.monthly && selectedFields.has('monthly')) {
                needPg(HH + RH * 8);
                sf('bold', 8, C.lt);
                doc.text('MONTHLY TRENDS'.toUpperCase(), ML, y);
                y += 6;
                fr(ML, CW, y, HH, [122, 12, 12], null);
                const mc = ['Month', 'Notes', 'Books', 'New Users'];
                const mw = [CW * .34, CW * .18, CW * .18, CW * .30];
                let xc = ML;
                mc.forEach((h, i) => {
                    sf('bold', 6, C.wh);
                    doc.text(h, xc + 1.5, y + HH - 2.5);
                    xc += mw[i];
                });
                y += HH;
                d.monthly.forEach((m, ri) => {
                    fr(ML, CW, y, RH, ri % 2 === 1 ? [255, 249, 249] : [255, 255, 255], null);
                    doc.setDrawColor(245, 220, 220);
                    doc.line(ML, y + RH, ML + CW, y + RH);
                    let xc = ML;
                    [m.month, m.notes, m.books, m.users].forEach((v, i) => {
                        sf('normal', 6.5, C.dk);
                        doc.text(String(v), xc + 1.5, y + RH - 2);
                        xc += mw[i];
                    });
                    y += RH;
                });
                y += 5;
            }

            if (d.top_downloads && selectedFields.has('top_downloads')) {
                needPg(HH + RH * 9);
                sf('bold', 8, C.lt);
                doc.text('TOP DOWNLOADS', ML, y);
                y += 6;
                fr(ML, CW, y, HH, [122, 12, 12], null);
                const dc = ['#', 'Title', 'Type', 'Downloads'];
                const dw = [CW * .05, CW * .58, CW * .15, CW * .22];
                let xc = ML;
                dc.forEach((h, i) => {
                    sf('bold', 6, C.wh);
                    doc.text(h, xc + 1.5, y + HH - 2.5);
                    xc += dw[i];
                });
                y += HH;
                d.top_downloads.forEach((r, ri) => {
                    fr(ML, CW, y, RH, ri % 2 === 1 ? [255, 249, 249] : [255, 255, 255], null);
                    doc.setDrawColor(245, 220, 220);
                    doc.line(ML, y + RH, ML + CW, y + RH);
                    let xc = ML;
                    [ri + 1, r.title, r.type, r.download_count].forEach((v, i) => {
                        let val = String(v);
                        while (val.length > 1 && doc.getTextWidth(val) > dw[i] - 3) val = val.slice(0, -2) + '…';
                        sf('normal', 6.5, C.dk);
                        doc.text(val, xc + 1.5, y + RH - 2);
                        xc += dw[i];
                    });
                    y += RH;
                });
            }
        }

        /* ── CSV Export ── */
        function generateCSV() {
            if (!currentData) return;
            const fDefs = getFieldDefs();
            if (currentType === 'dashboard') {
                alert('CSV not available for Dashboard report. Use PDF.');
                return;
            }
            const rows = currentData.rows || [];
            const header = fDefs.map(f => `"${f.label}"`).join(',');
            const lines = rows.map(row =>
                fDefs.map(f => {
                    const v = String(row[f.key] ?? '').replace(/"/g, '""');
                    return `"${v}"`;
                }).join(',')
            );
            const csv = [header, ...lines].join('\r\n');
            const blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ScholarSwap_${TYPE_LABELS[currentType]}_${new Date().toISOString().slice(0,10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>

</html>