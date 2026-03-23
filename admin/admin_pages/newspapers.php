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

$sa = $conn->prepare("SELECT first_name,last_name,role FROM admin_user WHERE admin_id=? LIMIT 1");
$sa->execute([$_SESSION['admin_id']]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);

// ── All newspapers (admin_id, no user_id) ──
$np = $conn->prepare("SELECT n.*,CONCAT(a.first_name,' ',a.last_name) AS uploader_name, a.email FROM newspapers n LEFT JOIN admin_user a ON n.admin_id=a.admin_id ORDER BY n.created_at DESC");
$np->execute();
$allPapers = $np->fetchAll(PDO::FETCH_ASSOC);

$ps = ['all' => count($allPapers), 'approved' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='approved'"), 'pending' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'"), 'featured' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE is_featured=1"), 'dl' => q($conn, "SELECT COALESCE(SUM(download_count),0) FROM newspapers"), 'views' => q($conn, "SELECT COALESCE(SUM(view_count),0) FROM newspapers")];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Newspapers | ScholarSwap Admin</title>
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
            --orange: #ea580c;
            --orange-s: #ffedd5;
            --indigo: #4f46e5;
            --indigo-s: #e0e7ff;
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
            color: var(--text);
        }

        .pg-head p {
            font-size: .8rem;
            color: var(--text3);
            margin-top: 3px;
        }

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(142px, 1fr));
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

        .si.t {
            background: var(--teal-s);
            color: var(--teal);
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
            max-width: 320px;
            transition: border-color .18s;
        }

        .t-search:focus-within {
            border-color: var(--purple);
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
            border-color: var(--purple);
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
            border-color: var(--purple);
            color: var(--purple);
        }

        .ft.on {
            background: var(--purple);
            color: #fff;
            border-color: var(--purple);
        }

        .bulk-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            background: var(--purple-s);
            border: 1.5px solid rgba(124, 58, 237, .22);
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
            color: var(--purple);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .bulk-acts {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

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
            background: var(--purple-s);
            color: var(--purple);
        }

        .btn-view:hover {
            background: var(--purple);
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

        .btn-del {
            background: var(--red);
            color: #fff;
            box-shadow: 0 2px 8px rgba(220, 38, 38, .2);
        }

        .btn-del:hover {
            background: #b91c1c;
        }

        .btn-star {
            background: var(--amber-s);
            color: var(--amber);
        }

        .btn-star:hover {
            background: var(--amber);
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

        .btn-file {
            background: var(--bg);
            color: var(--text2);
            border: 1px solid var(--border);
        }

        .btn-file:hover {
            background: var(--text);
            color: #fff;
        }

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
            background: #f5f3ff;
        }

        tbody tr.sel:hover td {
            background: #ede9fe;
        }

        .cb-td {
            text-align: center;
        }

        input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--purple);
            cursor: pointer;
        }

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

        .bdg-featured {
            background: #fef9c3;
            color: #854d0e;
        }

        .bdg-lang {
            background: var(--purple-s);
            color: var(--purple);
        }

        .bdg-region {
            background: var(--indigo-s);
            color: var(--indigo);
        }

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
            border-color: var(--purple);
            color: var(--purple);
        }

        .pgb.on {
            background: var(--purple);
            color: #fff;
            border-color: var(--purple);
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

        .u-meta {
            font-size: .67rem;
            color: var(--text3);
            margin-top: 1px;
        }

        /* Modal */
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
            max-height: 90vh;
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
            background: linear-gradient(90deg, #7c3aed, #4f46e5, #0d9488);
        }

        .mob-head {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex-shrink: 0;
        }

        .mob-ico {
            width: 44px;
            height: 44px;
            border-radius: 11px;
            background: var(--purple-s);
            color: var(--purple);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .mob-ttl {
            flex: 1;
            min-width: 0;
        }

        .mob-ttl h3 {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.3;
            margin: 0;
        }

        .mob-ttl p {
            font-size: .72rem;
            color: var(--text3);
            margin-top: 3px;
        }

        .mob-x {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            background: var(--bg);
            color: var(--text2);
            font-size: .88rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .13s;
            flex-shrink: 0;
        }

        .mob-x:hover {
            background: var(--red-s);
            color: var(--red);
        }

        .mob-body {
            overflow-y: auto;
            flex: 1;
            padding: 18px 22px;
        }

        .mob-body::-webkit-scrollbar {
            width: 4px;
        }

        .mob-body::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px;
        }

        .met-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .met {
            display: flex;
            align-items: center;
            gap: 7px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 8px 13px;
        }

        .met i {
            font-size: .78rem;
            color: var(--text3);
        }

        .met-v {
            font-size: .98rem;
            font-weight: 800;
            color: var(--text);
        }

        .met-l {
            font-size: .65rem;
            color: var(--text3);
        }

        .fstrip {
            background: var(--bg);
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 11px 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .fstrip-ico {
            width: 40px;
            height: 40px;
            border-radius: 9px;
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .fstrip-info {
            flex: 1;
            min-width: 0;
        }

        .fstrip-name {
            font-size: .84rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .fstrip-meta {
            font-size: .68rem;
            color: var(--text3);
            margin-top: 2px;
        }

        .dgrid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .dg {
            padding: 11px 0;
            border-bottom: 1px solid var(--border);
        }

        .dg.full {
            grid-column: 1/-1;
        }

        .dg:last-child,
        .dg:nth-last-child(2):not(.full) {
            border-bottom: none;
        }

        .dg-l {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text3);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
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

        .mob-foot {
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 7px;
            justify-content: flex-end;
            flex-shrink: 0;
            background: var(--bg);
            flex-wrap: wrap;
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
                    <h1>Newspapers Management</h1>
                    <p>View, filter, approve, feature and delete uploaded newspapers</p>
                </div>

                <button class="btn btn-view btn-sm" onclick=" window.location.href='newspaper_upload.php' "><i class="fas fa-plus"></i> Upload Newspaper</button>
            </div>

            <div class="stat-strip">
                <div class="sc">
                    <div class="si p"><i class="fas fa-newspaper"></i></div>
                    <div>
                        <div class="sv"><?php echo $ps['all']; ?></div>
                        <div class="sl">Total Papers</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si g"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="sv"><?php echo $ps['approved']; ?></div>
                        <div class="sl">Approved</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si a"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="sv"><?php echo $ps['pending']; ?></div>
                        <div class="sl">Pending</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si i"><i class="fas fa-star"></i></div>
                    <div>
                        <div class="sv"><?php echo $ps['featured']; ?></div>
                        <div class="sl">Featured</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si b"><i class="fas fa-download"></i></div>
                    <div>
                        <div class="sv"><?php echo number_format($ps['dl']); ?></div>
                        <div class="sl">Total Downloads</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si t"><i class="fas fa-eye"></i></div>
                    <div>
                        <div class="sv"><?php echo number_format($ps['views']); ?></div>
                        <div class="sl">Total Views</div>
                    </div>
                </div>
            </div>

            <div class="toolbar">
                <div class="t-search"><i class="fas fa-search"></i><input type="text" id="searchQ" placeholder="Search title, publisher, language, region…"></div>
                <select class="fsel" id="fLang" onchange="go()">
                    <option value="">All Languages</option>
                    <?php $langs = array_values(array_unique(array_filter(array_column($allPapers, 'language'))));
                    sort($langs);
                    foreach ($langs as $l): ?><option value="<?php echo strtolower(htmlspecialchars($l)); ?>"><?php echo htmlspecialchars($l); ?></option><?php endforeach; ?>
                </select>
                <select class="fsel" id="fRegion" onchange="go()">
                    <option value="">All Regions</option>
                    <?php $regions = array_values(array_unique(array_filter(array_column($allPapers, 'region'))));
                    sort($regions);
                    foreach ($regions as $rg): ?><option value="<?php echo strtolower(htmlspecialchars($rg)); ?>"><?php echo htmlspecialchars($rg); ?></option><?php endforeach; ?>
                </select>
                <div class="ftabs ml">
                    <button class="ft on" onclick="setSt('all',this)">All <span style="background:var(--bg);border-radius:99px;padding:1px 6px;font-size:.58rem;margin-left:2px"><?php echo $ps['all']; ?></span></button>
                    <button class="ft" onclick="setSt('approved',this)">Approved</button>
                    <button class="ft" onclick="setSt('pending',this)">Pending</button>
                </div>
            </div>

            <div class="bulk-bar" id="bulkBar">
                <div class="bulk-info"><i class="fas fa-check-square"></i><span id="bCount">0</span> paper(s) selected</div>
                <div class="bulk-acts">
                    <button class="btn btn-outline btn-sm" onclick="clearSel()"><i class="fas fa-xmark"></i> Clear</button>
                    <button class="btn btn-ok btn-sm" onclick="bAction('approved')"><i class="fas fa-check"></i> Approve</button>
                    <button class="btn btn-no btn-sm" onclick="bAction('rejected')"><i class="fas fa-ban"></i> Reject</button>
                    <button class="btn btn-del btn-sm" onclick="bDelete()"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>

            <div class="panel">
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selAll" onchange="tAll(this.checked)"></th>
                                <th>#</th>
                                <th>Title</th>
                                <th>Publisher</th>
                                <th>Uploaded By</th>
                                <th>Language</th>
                                <th>Region</th>
                                <th>Pub. Date</th>
                                <th>File Type</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>DL</th>
                                <th>Views</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allPapers as $i => $p):
                                $fp = !empty($p['file_path']) ? substr($p['file_path'], 6) : '#';
                                $pj = json_encode(['id' => $p['newspaper_id'], 'title' => $p['title'], 'publisher' => $p['publisher'] ?? '', 'uploader' => $p['uploader_name'] ?? 'Admin', 'email' => $p['email'] ?? '', 'language' => $p['language'] ?? '', 'region' => $p['region'] ?? '', 'pub_date' => $p['publication_date'] ?? '', 'status' => $p['approval_status'], 'featured' => (bool)$p['is_featured'], 'downloads' => (int)$p['download_count'], 'views' => (int)$p['view_count'], 'file_size' => $p['file_size'] ?? '', 'file_type' => $p['file_type'] ?? '', 'created_at' => date('d M Y, H:i', strtotime($p['created_at'])), 'file_path' => 'http://localhost/ScholarSwap/admin/'.$fp]);
                            ?>
                                <tr class="dr" data-st="<?php echo $p['approval_status']; ?>" data-lang="<?php echo strtolower($p['language'] ?? ''); ?>" data-region="<?php echo strtolower($p['region'] ?? ''); ?>" data-s="<?php echo strtolower(htmlspecialchars($p['title'] . ' ' . ($p['publisher'] ?? '') . ' ' . ($p['language'] ?? '') . ' ' . ($p['region'] ?? '') . ' ' . ($p['uploader_name'] ?? ''))); ?>">
                                    <td class="cb-td"><input type="checkbox" class="rcb" data-id="<?php echo $p['newspaper_id']; ?>" onchange="onCk()"></td>
                                    <td style="color:var(--text3);font-size:.7rem"><?php echo $i + 1; ?></td>
                                    <td style="max-width:200px">
                                        <div style="font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo htmlspecialchars($p['title']); ?>"><?php echo htmlspecialchars($p['title']); ?></div>
                                        <div class="u-meta"><?php echo date('d M Y', strtotime($p['publication_date'] ?? 'now')); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['publisher'] ?? '—'); ?></td>
                                    <td>
                                        <div style="font-weight:500;color:var(--text)"><?php echo htmlspecialchars($p['uploader_name'] ?? '—'); ?></div>
                                        <div class="u-meta"><?php echo htmlspecialchars($p['email'] ?? ''); ?></div>
                                    </td>
                                    <td><span class="bdg bdg-lang"><?php echo htmlspecialchars($p['language'] ?? '—'); ?></span></td>
                                    <td><span class="bdg bdg-region"><?php echo htmlspecialchars($p['region'] ?? '—'); ?></span></td>
                                    <td style="white-space:nowrap;font-size:.76rem"><?php echo $p['publication_date'] ? date('d M Y', strtotime($p['publication_date'])) : '—'; ?></td>
                                    <td style="font-size:.74rem"><?php echo strtoupper($p['file_type'] ?? '—'); ?></td>
                                    <td><span class="bdg bdg-<?php echo $p['approval_status']; ?>"><?php echo ucfirst($p['approval_status']); ?></span></td>
                                    <td><?php echo $p['is_featured'] ? '<span class="bdg bdg-featured">★ Yes</span>' : '<span style="color:var(--text3)">—</span>'; ?></td>
                                    <td style="font-weight:700;text-align:right"><?php echo number_format($p['download_count'] ?? 0); ?></td>
                                    <td style="font-weight:700;text-align:right"><?php echo number_format($p['view_count'] ?? 0); ?></td>
                                    <td style="white-space:nowrap">
                                        <button class="btn btn-view btn-sm" onclick='openMod(<?php echo htmlspecialchars($pj, ENT_QUOTES); ?>)'><i class="fas fa-eye"></i> View</button>
                                        <?php if ($p['approval_status'] === 'pending'): ?>
                                            <button class="btn btn-ok btn-sm" onclick="sStatus(<?php echo $p['newspaper_id']; ?>,'approved','<?php echo addslashes(htmlspecialchars($p['title'])); ?>')"><i class="fas fa-check"></i></button>
                                            <button class="btn btn-no btn-sm" onclick="sStatus(<?php echo $p['newspaper_id']; ?>,'rejected','<?php echo addslashes(htmlspecialchars($p['title'])); ?>')"><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
                                        <?php if (!$p['is_featured'] && $p['approval_status'] === 'approved'): ?>
                                            <button class="btn btn-star btn-sm" onclick="featPaper(<?php echo $p['newspaper_id']; ?>,'<?php echo addslashes(htmlspecialchars($p['title'])); ?>')"><i class="fas fa-star"></i></button>
                                        <?php endif; ?>
                                        <button class="btn btn-del btn-sm" onclick="delPaper(<?php echo $p['newspaper_id']; ?>,'<?php echo addslashes(htmlspecialchars($p['title'])); ?>')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="empty" id="emptyMsg" style="display:none"><i class="fas fa-newspaper"></i><strong>No newspapers found</strong>Try adjusting your filters or search.</div>
                <div class="pgbar">
                    <div class="pgi" id="pgi"></div>
                    <div class="pgbtns" id="pgbtns"></div>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL -->
    <div class="mov" id="vModal">
        <div class="mob">
            <div class="mob-accent"></div>
            <div class="mob-head">
                <div class="mob-ico"><i class="fas fa-newspaper"></i></div>
                <div class="mob-ttl">
                    <h3 id="mT">Newspaper Details</h3>
                    <p id="mS">—</p>
                </div>
                <button class="mob-x" onclick="closeMod()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="mob-body">
                <div class="met-row" id="mMet"></div>
                <div class="fstrip" id="mFile"></div>
                <div class="dgrid" id="mGrid"></div>
            </div>
            <div class="mob-foot">
                <button class="btn btn-outline" onclick="closeMod()">Close</button>
                <button class="btn btn-ok btn-sm" id="mAppBtn" style="display:none" onclick="mStatus('approved')"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-no btn-sm" id="mRejBtn" style="display:none" onclick="mStatus('rejected')"><i class="fas fa-times"></i> Reject</button>
                <a class="btn btn-file btn-sm" id="mOpenF" href="#" target="_blank"><i class="fas fa-file-arrow-down"></i> Open File</a>
                <button class="btn btn-del btn-sm" onclick="mDelete()"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>

    <script>
        let curSt = 'all',
            curMod = null;
        const PER = 20;
        let pg = 1;

        function go() {
            const q = document.getElementById('searchQ').value.toLowerCase().trim(),
                lg = document.getElementById('fLang').value,
                rg = document.getElementById('fRegion').value,
                all = [...document.querySelectorAll('.dr')],
                vis = all.filter(r => (curSt === 'all' || r.dataset.st === curSt) && (!q || r.dataset.s.includes(q)) && (!lg || r.dataset.lang === lg) && (!rg || r.dataset.region === rg));
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
        const post = (url, d) => fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(d)
        }).then(r => r.json());

        function sStatus(id, act, title) {
            const a = act === 'approved';
            Swal.fire({
                title: a ? 'Approve this newspaper?' : 'Reject this newspaper?',
                html: `<span style="color:#64748b;font-size:.9rem"><strong style="color:#0f172a">"${title}"</strong> will be marked <strong>${act}</strong>.</span>`,
                icon: a ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: a ? '<i class="fas fa-check"></i> Approve' : '<i class="fas fa-times"></i> Reject',
                cancelButtonText: 'Cancel',
                confirmButtonColor: a ? '#059669' : '#dc2626',
                cancelButtonColor: '#e2e8f0'
            }).then(r => {
                if (!r.isConfirmed) return;
                post('auth/update_newspaper_status.php', {
                    id,
                    action: act
                }).then(d => {
                    d.success ? Swal.fire({
                        icon: a ? 'success' : 'info',
                        title: a ? 'Approved!' : 'Rejected',
                        text: d.message,
                        timer: 1800,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => location.reload()) : Swal.fire({
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

        function delPaper(id, title) {
            Swal.fire({
                title: 'Delete this newspaper?',
                html: `<span style="color:#64748b;font-size:.9rem">Permanently delete <strong style="color:#0f172a">"${title}"</strong>. Cannot be undone.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Yes, Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#e2e8f0'
            }).then(r => {
                if (!r.isConfirmed) return;
                Swal.fire({
                    title: 'Deleting…',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                post('auth/delete_newspaper.php', {
                    id
                }).then(d => {
                    d.success ? Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: d.message,
                        timer: 1800,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => location.reload()) : Swal.fire({
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

        function featPaper(id, title) {
            Swal.fire({
                title: 'Feature this newspaper?',
                html: `<span style="color:#64748b;font-size:.9rem"><strong style="color:#0f172a">"${title}"</strong> will appear as featured.</span>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-star"></i> Yes, Feature',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#e2e8f0'
            }).then(r => {
                if (!r.isConfirmed) return;
                post('auth/feature_newspaper.php', {
                    id
                }).then(d => {
                    d.success ? Swal.fire({
                        icon: 'success',
                        title: 'Featured!',
                        text: d.message,
                        timer: 1600,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => location.reload()) : Swal.fire({
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

        function bAction(act) {
            const ids = [...document.querySelectorAll('.rcb:checked')].map(cb => ({
                id: parseInt(cb.dataset.id)
            }));
            if (!ids.length) return;
            const a = act === 'approved';
            Swal.fire({
                title: `${a?'Approve':'Reject'} ${ids.length} paper(s)?`,
                icon: a ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: `<i class="fas fa-${a?'check':'ban'}"></i> ${a?'Approve':'Reject'} All`,
                cancelButtonText: 'Cancel',
                confirmButtonColor: a ? '#059669' : '#dc2626',
                cancelButtonColor: '#e2e8f0'
            }).then(r => {
                if (!r.isConfirmed) return;
                Swal.fire({
                    title: 'Processing…',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                post('auth/bulk_newspaper_status.php', {
                    ids: ids.map(i => i.id),
                    action: act
                }).then(d => {
                    d.success ? Swal.fire({
                        icon: a ? 'success' : 'info',
                        title: 'Done!',
                        text: d.message,
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => location.reload()) : Swal.fire({
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

        function bDelete() {
            const ids = [...document.querySelectorAll('.rcb:checked')].map(cb => parseInt(cb.dataset.id));
            if (!ids.length) return;
            Swal.fire({
                title: `Delete ${ids.length} paper(s)?`,
                html: `<span style="color:#64748b;font-size:.9rem">Permanently deletes <strong style="color:#0f172a">${ids.length}</strong> newspaper(s). Cannot be undone.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Delete All Selected',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#e2e8f0'
            }).then(r => {
                if (!r.isConfirmed) return;
                Swal.fire({
                    title: 'Deleting…',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                post('auth/delete_newspaper.php', {
                    ids
                }).then(d => {
                    d.success ? Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: d.message,
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => location.reload()) : Swal.fire({
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

        function openMod(p) {
            curMod = p;
            document.getElementById('mT').textContent = p.title;
            document.getElementById('mS').textContent = `${p.publisher||'—'}  ·  Uploaded by ${p.uploader}  ·  ${p.created_at}`;
            const stC = p.status === 'approved' ? '#059669' : p.status === 'pending' ? '#d97706' : '#dc2626';
            document.getElementById('mMet').innerHTML = `<div class="met"><i class="fas fa-download"></i><div><div class="met-v">${Number(p.downloads).toLocaleString()}</div><div class="met-l">Downloads</div></div></div><div class="met"><i class="fas fa-eye"></i><div><div class="met-v">${Number(p.views).toLocaleString()}</div><div class="met-l">Views</div></div></div><div class="met"><i class="fas fa-circle" style="color:${stC};font-size:.55rem"></i><div><div class="met-v" style="font-size:.82rem;text-transform:capitalize">${p.status}</div><div class="met-l">Status</div></div></div>${p.featured?`<div class="met"><i class="fas fa-star" style="color:#d97706"></i><div><div class="met-v" style="font-size:.82rem">Featured</div><div class="met-l">Visibility</div></div></div>`:''}`;
            const ext = (p.file_type || 'FILE').toUpperCase(),
                sizeMB = p.file_size ? (parseFloat(p.file_size) / 1024 / 1024).toFixed(2) + ' MB' : 'Size unknown';
            document.getElementById('mFile').innerHTML = `<div class="fstrip-ico"><i class="fas fa-newspaper"></i></div><div class="fstrip-info"><div class="fstrip-name">${p.title}</div><div class="fstrip-meta">${ext} · ${sizeMB}${p.language?' · '+p.language:''}${p.region?' · '+p.region:''}</div></div><div style="display:flex;gap:6px;flex-shrink:0"><a class="btn btn-file btn-sm" href="${p.file_path}" target="_blank"><i class="fas fa-external-link-alt"></i> Open</a></div>`;
            const fields = [{
                i: 'fa-user',
                l: 'Uploaded By',
                v: p.uploader
            }, {
                i: 'fa-envelope',
                l: 'Email',
                v: p.email || '—'
            }, {
                i: 'fa-building-columns',
                l: 'Publisher',
                v: p.publisher || '—'
            }, {
                i: 'fa-language',
                l: 'Language',
                v: p.language || '—'
            }, {
                i: 'fa-map-pin',
                l: 'Region',
                v: p.region || '—'
            }, {
                i: 'fa-calendar-days',
                l: 'Publication Date',
                v: p.pub_date || '—'
            }, {
                i: 'fa-file',
                l: 'File Type',
                v: p.file_type || '—'
            }, {
                i: 'fa-clock',
                l: 'Uploaded',
                v: p.created_at
            }];
            document.getElementById('mGrid').innerHTML = fields.map(f => `<div class="dg"><div class="dg-l"><i class="fas ${f.i}"></i>${f.l}</div><div class="dg-v ${(!f.v||f.v==='—')?'muted':''}">${f.v}</div></div>`).join('');
            document.getElementById('mOpenF').href = p.file_path;
            document.getElementById('mAppBtn').style.display = p.status === 'pending' ? '' : 'none';
            document.getElementById('mRejBtn').style.display = p.status === 'pending' ? '' : 'none';
            document.getElementById('vModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeMod() {
            document.getElementById('vModal').classList.remove('show');
            document.body.style.overflow = '';
            curMod = null;
        }

        function mStatus(act) {
            if (!curMod) return;
            closeMod();
            sStatus(curMod.id, act, curMod.title);
        }

        function mDelete() {
            if (!curMod) return;
            closeMod();
            delPaper(curMod.id, curMod.title);
        }
        document.getElementById('vModal').addEventListener('click', e => {
            if (e.target === document.getElementById('vModal')) closeMod();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeMod();
        });
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
        if (_s) history.replaceState(null, '', 'newspapers.php');
    </script>
</body>

</html>