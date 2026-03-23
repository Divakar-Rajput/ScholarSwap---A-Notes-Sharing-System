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

// ── Counts (required by sidebar) ──
$c = [
    'users'      => q($conn, "SELECT COUNT(*) FROM users"),
    'students'   => q($conn, "SELECT COUNT(*) FROM students"),
    'tutors'     => q($conn, "SELECT COUNT(*) FROM tutors"),
    'admins'     => q($conn, "SELECT COUNT(*) FROM admin_user"),
    'notes'      => q($conn, "SELECT COUNT(*) FROM notes"),
    'books'      => q($conn, "SELECT COUNT(*) FROM books"),
    'papers'     => q($conn, "SELECT COUNT(*) FROM newspapers"),
    'n_pending'  => q($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='pending'"),
    'b_pending'  => q($conn, "SELECT COUNT(*) FROM books WHERE approval_status='pending'"),
    'p_pending'  => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'"),
];
$c['pending'] = $c['n_pending'] + $c['b_pending'] + $c['p_pending'];

// ── Admin info (required by sidebar) ──
$sa = $conn->prepare("SELECT first_name,last_name,role FROM admin_user WHERE admin_id=? LIMIT 1");
$sa->execute([$_SESSION['admin_id']]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);

// ── Pending Notes ──
$pN = $conn->prepare("
    SELECT notes.*, users.email, users.username,
        COALESCE(
            (SELECT CONCAT(s.first_name,' ',s.last_name) FROM students s WHERE s.user_id=notes.user_id),
            (SELECT CONCAT(t.first_name,' ',t.last_name) FROM tutors   t WHERE t.user_id=notes.user_id),
            users.username
        ) AS uploader_name
    FROM notes
    JOIN users ON notes.user_id = users.user_id
    WHERE notes.approval_status = 'pending'
    ORDER BY notes.created_at DESC
");
$pN->execute();
$pNotesData = $pN->fetchAll(PDO::FETCH_ASSOC);

// ── Pending Books ──
$pB = $conn->prepare("
    SELECT books.*, users.email, users.username,
        COALESCE(
            (SELECT CONCAT(s.first_name,' ',s.last_name) FROM students s WHERE s.user_id=books.user_id),
            (SELECT CONCAT(t.first_name,' ',t.last_name) FROM tutors   t WHERE t.user_id=books.user_id),
            users.username
        ) AS uploader_name
    FROM books
    JOIN users ON books.user_id = users.user_id
    WHERE books.approval_status = 'pending'
    ORDER BY books.book_id DESC
");
$pB->execute();
$pBooksData = $pB->fetchAll(PDO::FETCH_ASSOC);

$totalPending = count($pNotesData) + count($pBooksData);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Pending Approvals | ScholarSwap Admin</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --blue: #2563eb;
            --blue-s: #dbeafe;
            --blue-d: #1d4ed8;
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

        /* Page heading */
        .pg-head {
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pg-head-left h1 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
        }

        .pg-head-left p {
            font-size: .8rem;
            color: var(--text3);
            margin-top: 4px;
        }

        /* Stat strip */
        .stat-strip {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 11px 16px;
            box-shadow: var(--sh);
            flex: 1;
            min-width: 130px;
        }

        .stat-chip-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .88rem;
            flex-shrink: 0;
        }

        .stat-chip-icon.r {
            background: var(--red-s);
            color: var(--red);
        }

        .stat-chip-icon.b {
            background: var(--blue-s);
            color: var(--blue);
        }

        .stat-chip-icon.t {
            background: var(--teal-s);
            color: var(--teal);
        }

        .stat-chip-icon.a {
            background: var(--amber-s);
            color: var(--amber);
        }

        .stat-chip-num {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .stat-chip-lbl {
            font-size: .68rem;
            color: var(--text3);
            margin-top: 2px;
        }

        /* Bulk action bar */
        .bulk-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 14px;
            box-shadow: var(--sh);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s;
        }

        .bulk-bar.visible {
            opacity: 1;
            pointer-events: all;
        }

        .bulk-bar-info {
            font-size: .84rem;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .bulk-bar-info i {
            color: var(--blue);
        }

        .bulk-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Panel */
        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .ph {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pt {
            font-size: .9rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ph-count {
            font-size: .7rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 99px;
            background: var(--red-s);
            color: var(--red);
        }

        .ph-actions {
            display: flex;
            gap: 7px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Select all row */
        .sel-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            font-size: .76rem;
            color: var(--text2);
        }

        .sel-row label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        /* Table */
        .tw {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
            text-align: left;
        }

        thead th:first-child {
            width: 36px;
            text-align: center;
        }

        tbody td {
            padding: 10px 13px;
            font-size: .82rem;
            color: var(--text2);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr.selected td {
            background: #eff6ff;
        }

        tbody tr:hover td {
            background: #fafbff;
        }

        tbody tr.selected:hover td {
            background: #dbeafe;
        }

        /* Checkbox */
        input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--blue);
            cursor: pointer;
        }

        td.cb-td {
            text-align: center;
        }

        /* Badges */
        .bdg {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: .62rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .bdg-pending {
            background: var(--amber-s);
            color: #92400e;
        }

        .bdg-approved {
            background: var(--green-s);
            color: #065f46;
        }

        .bdg-rejected {
            background: var(--red-s);
            color: #991b1b;
        }

        .bdg-note {
            background: var(--blue-s);
            color: var(--blue);
        }

        .bdg-book {
            background: var(--teal-s);
            color: var(--teal);
        }

        .bdg-student {
            background: var(--blue-s);
            color: var(--blue);
        }

        .bdg-tutor {
            background: var(--orange-s);
            color: var(--orange);
        }

        .bdg-featured {
            background: #fef9c3;
            color: #854d0e;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            font-size: .74rem;
            font-weight: 600;
            transition: all .14s;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:active {
            transform: scale(.96);
        }

        .btn-ok {
            background: var(--green-s);
            margin-bottom: 10px;
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

        .btn-dl {
            background: var(--bg);
            color: var(--text2);
            border: 1px solid var(--border);
        }

        .btn-dl:hover {
            background: var(--text);
            color: #fff;
        }

        .btn-bulk-ok {
            background: var(--green);
            color: #fff;
            padding: 8px 16px;
            border-radius: 9px;
            font-size: .8rem;
            box-shadow: 0 3px 10px rgba(5, 150, 105, .25);
        }

        .btn-bulk-ok:hover {
            background: #047857;
        }

        .btn-bulk-no {
            background: var(--red);
            color: #fff;
            padding: 8px 16px;
            border-radius: 9px;
            font-size: .8rem;
            box-shadow: 0 3px 10px rgba(220, 38, 38, .25);
        }

        .btn-bulk-no:hover {
            background: #b91c1c;
        }

        .btn-bulk-outline {
            background: var(--surface);
            color: var(--text2);
            border: 1.5px solid var(--border);
            padding: 7px 14px;
            border-radius: 9px;
            font-size: .78rem;
            font-weight: 600;
        }

        .btn-bulk-outline:hover {
            background: var(--bg);
        }

        /* Empty */
        .empty {
            padding: 36px;
            text-align: center;
            color: var(--text3);
            font-size: .82rem;
        }

        .empty i {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
            opacity: .25;
        }

        .empty strong {
            display: block;
            font-size: .9rem;
            color: var(--text2);
            margin-bottom: 4px;
        }

        .user-meta {
            font-size: .68rem;
            color: var(--text3);
            margin-top: 1px;
        }

        /* Tab bar */
        .tab-bar {
            display: flex;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .tab-btn {
            padding: 12px 20px;
            font-size: .83rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--text3);
            border-bottom: 2px solid transparent;
            transition: all .16s;
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .tab-btn:hover {
            color: var(--text);
        }

        .tab-btn.active {
            color: var(--blue);
            border-bottom-color: var(--blue);
        }

        .tab-count {
            font-size: .62rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 99px;
        }

        .tc-note {
            background: var(--blue-s);
            color: var(--blue);
        }

        .tc-book {
            background: var(--teal-s);
            color: var(--teal);
        }

        .tab-body {
            display: none;
        }

        .tab-body.active {
            display: block;
        }
    </style>
</head>

<body>

    <?php include_once('sidebar.php'); ?>

    <!-- TOPBAR -->
    <?php include_once('adminheader.php'); ?>

    <div class="main">
        <div class="pg">

            <!-- Page heading -->
            <div class="pg-head">
                <div class="pg-head-left">
                    <h1>Pending Approvals</h1>
                    <p>Review and approve or reject uploaded content before it goes live</p>
                </div>
                <?php if ($totalPending > 0): ?>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-bulk-ok" onclick="bulkAll('approved')"><i class="fas fa-check-double"></i> Approve All</button>
                        <button class="btn btn-bulk-no" onclick="bulkAll('rejected')"><i class="fas fa-ban"></i> Reject All</button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stat strip -->
            <div class="stat-strip">
                <div class="stat-chip">
                    <div class="stat-chip-icon r"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="stat-chip-num"><?php echo $totalPending; ?></div>
                        <div class="stat-chip-lbl">Total Pending</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-icon b"><i class="fas fa-file-lines"></i></div>
                    <div>
                        <div class="stat-chip-num"><?php echo $c['n_pending']; ?></div>
                        <div class="stat-chip-lbl">Pending Notes</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-icon t"><i class="fas fa-book"></i></div>
                    <div>
                        <div class="stat-chip-num"><?php echo $c['b_pending']; ?></div>
                        <div class="stat-chip-lbl">Pending Books</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-icon a"><i class="fas fa-newspaper"></i></div>
                    <div>
                        <div class="stat-chip-num"><?php echo $c['p_pending']; ?></div>
                        <div class="stat-chip-lbl">Pending Papers</div>
                    </div>
                </div>
            </div>

            <!-- Bulk action bar (shows when checkboxes selected) -->
            <div class="bulk-bar" id="bulkBar">
                <div class="bulk-bar-info">
                    <i class="fas fa-check-square"></i>
                    <span id="bulkCount">0</span> item(s) selected
                </div>
                <div class="bulk-actions">
                    <button class="btn btn-bulk-outline" onclick="clearSelection()"><i class="fas fa-xmark"></i> Clear</button>
                    <button class="btn btn-bulk-ok" onclick="bulkSelected('approved')"><i class="fas fa-check"></i> Approve Selected</button>
                    <button class="btn btn-bulk-no" onclick="bulkSelected('rejected')"><i class="fas fa-ban"></i> Reject Selected</button>
                </div>
            </div>

            <!-- ══ TABS ══ -->
            <div class="panel">
                <div class="tab-bar">
                    <button class="tab-btn active" onclick="switchTab('notes',this)">
                        <i class="fas fa-file-lines"></i> Notes
                        <span class="tab-count tc-note"><?php echo $c['n_pending']; ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('books',this)">
                        <i class="fas fa-book"></i> Books
                        <span class="tab-count tc-book"><?php echo $c['b_pending']; ?></span>
                    </button>
                </div>

                <!-- ── NOTES TAB ── -->
                <div id="tab-notes" class="tab-body active">
                    <?php if (count($pNotesData)): ?>
                        <div class="sel-row">
                            <label>
                                <input type="checkbox" id="selAllNotes" onchange="toggleAll('note',this.checked)">
                                Select all notes
                            </label>
                            <span style="margin-left:auto;font-size:.72rem;color:var(--text3);"><?php echo count($pNotesData); ?> items</span>
                        </div>
                        <div class="tw">
                            <table id="notesTable">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selAllNotes2" onchange="toggleAll('note',this.checked)" title="Select all"></th>
                                        <th>Title</th>
                                        <th>Uploader</th>
                                        <th>Type</th>
                                        <th>Course</th>
                                        <th>Subject</th>
                                        <th>Featured</th>
                                        <th>File</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pNotesData as $n): ?>
                                        <tr data-id="<?php echo $n['note_id']; ?>" data-type="note"
                                            data-search="<?php echo strtolower(htmlspecialchars($n['title'] . ' ' . $n['subject'] . ' ' . ($n['uploader_name'] ?? ''))); ?>">
                                            <td class="cb-td"><input type="checkbox" class="row-cb" data-doctype="note" data-id="<?php echo $n['note_id']; ?>" onchange="onCheckChange()"></td>
                                            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo htmlspecialchars($n['title']); ?>">
                                                <strong style="color:var(--text)"><?php echo htmlspecialchars($n['title']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($n['uploader_name'] ?? '—'); ?>
                                                <div class="user-meta"><?php echo htmlspecialchars($n['email'] ?? ''); ?></div>
                                            </td>
                                            <td><span class="bdg bdg-<?php echo $n['uploaded_by'] ?? 'student'; ?>"><?php echo ucfirst($n['uploaded_by'] ?? '—'); ?></span></td>
                                            <td><?php echo htmlspecialchars($n['course'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($n['subject'] ?? '—'); ?></td>
                                            <td><?php echo $n['is_featured'] ? '<span class="bdg bdg-featured">★ Yes</span>' : '<span style="color:var(--text3)">—</span>'; ?></td>
                                            <td><a class="btn btn-dl" href="<?php echo 'http://localhost/ScholarSwap/admin/'.substr($n['file_path'], 6); ?>" target="_blank"><i class="fas fa-eye"></i> View</a></td>
                                            <td style="white-space:nowrap;color:var(--text3)"><?php echo date('M j, Y', strtotime($n['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-ok" onclick="singleAction(<?php echo $n['note_id']; ?>,'note','approved','<?php echo htmlspecialchars(addslashes($n['title'])); ?>')"><i class="fas fa-check"></i> Approve</button>
                                                <button class="btn btn-no" onclick="singleAction(<?php echo $n['note_id']; ?>,'note','rejected','<?php echo htmlspecialchars(addslashes($n['title'])); ?>')"><i class="fas fa-times"></i> Reject</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty">
                            <i class="fas fa-check-circle"></i>
                            <strong>All caught up!</strong>
                            No pending notes to review.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ── BOOKS TAB ── -->
                <div id="tab-books" class="tab-body">
                    <?php if (count($pBooksData)): ?>
                        <div class="sel-row">
                            <label>
                                <input type="checkbox" id="selAllBooks" onchange="toggleAll('book',this.checked)">
                                Select all books
                            </label>
                            <span style="margin-left:auto;font-size:.72rem;color:var(--text3);"><?php echo count($pBooksData); ?> items</span>
                        </div>
                        <div class="tw">
                            <table id="booksTable">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" onchange="toggleAll('book',this.checked)" title="Select all"></th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Uploader</th>
                                        <th>Subject</th>
                                        <th>Publication</th>
                                        <th>Year</th>
                                        <th>File Type</th>
                                        <th>Featured</th>
                                        <th>File</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pBooksData as $b): ?>
                                        <tr data-id="<?php echo $b['book_id']; ?>" data-type="book"
                                            data-search="<?php echo strtolower(htmlspecialchars($b['title'] . ' ' . ($b['author'] ?? '') . ' ' . $b['subject'] . ' ' . ($b['uploader_name'] ?? ''))); ?>">
                                            <td class="cb-td"><input type="checkbox" class="row-cb" data-doctype="book" data-id="<?php echo $b['book_id']; ?>" onchange="onCheckChange()"></td>
                                            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo htmlspecialchars($b['title']); ?>">
                                                <strong style="color:var(--text)"><?php echo htmlspecialchars($b['title']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($b['author'] ?? '—'); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($b['uploader_name'] ?? '—'); ?>
                                                <div class="user-meta"><?php echo htmlspecialchars($b['email'] ?? ''); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($b['subject'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($b['publication_name'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($b['published_year'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($b['file_type'] ?? '—'); ?></td>
                                            <td><?php echo $b['is_featured'] ? '<span class="bdg bdg-featured">★ Yes</span>' : '<span style="color:var(--text3)">—</span>'; ?></td>
                                            <td><a class="btn btn-dl" href="<?php echo 'http://localhost/ScholarSwap/admin/'.substr($b['file_path'], 6); ?>" target="_blank"><i class="fas fa-eye"></i> View</a></td>
                                            <td>
                                                <button class="btn btn-ok" onclick="singleAction(<?php echo $b['book_id']; ?>,'book','approved','<?php echo htmlspecialchars(addslashes($b['title'])); ?>')"><i class="fas fa-check"></i> Approve</button>
                                                <button class="btn btn-no" onclick="singleAction(<?php echo $b['book_id']; ?>,'book','rejected','<?php echo htmlspecialchars(addslashes($b['title'])); ?>')"><i class="fas fa-times"></i> Reject</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty">
                            <i class="fas fa-check-circle"></i>
                            <strong>All caught up!</strong>
                            No pending books to review.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        /* ── Tab switch ── */
        function switchTab(name, btn) {
            document.querySelectorAll('.tab-body').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            btn.classList.add('active');
            clearSelection();
        }

        /* ── Checkbox helpers ── */
        function toggleAll(doctype, checked) {
            document.querySelectorAll(`.row-cb[data-doctype="${doctype}"]`).forEach(cb => {
                cb.checked = checked;
                cb.closest('tr').classList.toggle('selected', checked);
            });
            // sync both select-all checkboxes
            document.querySelectorAll('input[type=checkbox][id^="selAll"]').forEach(cb => {
                if (cb.dataset.doctype === doctype || !cb.dataset.doctype) cb.checked = checked;
            });
            onCheckChange();
        }

        function onCheckChange() {
            const checked = document.querySelectorAll('.row-cb:checked');
            const bar = document.getElementById('bulkBar');
            document.getElementById('bulkCount').textContent = checked.length;
            bar.classList.toggle('visible', checked.length > 0);
            // highlight rows
            document.querySelectorAll('.row-cb').forEach(cb => {
                cb.closest('tr').classList.toggle('selected', cb.checked);
            });
        }

        function clearSelection() {
            document.querySelectorAll('.row-cb').forEach(cb => {
                cb.checked = false;
                cb.closest('tr').classList.remove('selected');
            });
            document.querySelectorAll('input[type=checkbox][id^="selAll"]').forEach(cb => cb.checked = false);
            document.getElementById('bulkBar').classList.remove('visible');
            document.getElementById('bulkCount').textContent = '0';
        }

        /* ── Live search ── */
        document.getElementById('searchInput').addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('tbody tr[data-search]').forEach(tr => {
                tr.style.display = (!q || tr.dataset.search.includes(q)) ? '' : 'none';
            });
        });

        /* ── AJAX call ── */
        function doAction(items, action) {
            return fetch('auth/bulk_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    items,
                    action
                })
            }).then(r => r.json());
        }

        /* ── Single row action ── */
        function singleAction(id, doctype, action, title) {
            const isApprove = action === 'approved';
            Swal.fire({
                title: isApprove ? 'Approve this item?' : 'Reject this item?',
                html: `<span style="color:#64748b;font-size:.9rem"><strong style="color:#0f172a">"${title}"</strong><br>will be marked as <strong>${action}</strong>.</span>`,
                icon: isApprove ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: isApprove ? '<i class="fas fa-check"></i> Yes, Approve' : '<i class="fas fa-times"></i> Yes, Reject',
                cancelButtonText: 'Cancel',
                confirmButtonColor: isApprove ? '#059669' : '#dc2626',
                cancelButtonColor: '#e2e8f0',
            }).then(result => {
                if (!result.isConfirmed) return;
                doAction([{
                    id,
                    doctype
                }], action).then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: isApprove ? 'success' : 'info',
                            title: isApprove ? 'Approved!' : 'Rejected',
                            text: data.message || `1 item ${action}.`,
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: data.message || 'Something went wrong.'
                        });
                    }
                }).catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Could not reach the server.'
                }));
            });
        }

        /* ── Bulk selected ── */
        function bulkSelected(action) {
            const checked = [...document.querySelectorAll('.row-cb:checked')];
            if (!checked.length) return;
            const items = checked.map(cb => ({
                id: parseInt(cb.dataset.id),
                doctype: cb.dataset.doctype
            }));
            const isApprove = action === 'approved';
            Swal.fire({
                title: isApprove ? `Approve ${items.length} item(s)?` : `Reject ${items.length} item(s)?`,
                html: `<span style="color:#64748b;font-size:.9rem">This will mark <strong style="color:#0f172a">${items.length}</strong> selected item(s) as <strong>${action}</strong>.</span>`,
                icon: isApprove ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: isApprove ? '<i class="fas fa-check-double"></i> Approve All Selected' : '<i class="fas fa-ban"></i> Reject All Selected',
                cancelButtonText: 'Cancel',
                confirmButtonColor: isApprove ? '#059669' : '#dc2626',
                cancelButtonColor: '#e2e8f0',
            }).then(result => {
                if (!result.isConfirmed) return;
                Swal.fire({
                    title: 'Processing…',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                doAction(items, action).then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: isApprove ? 'success' : 'info',
                            title: isApprove ? 'Approved!' : 'Rejected',
                            text: data.message || `${items.length} item(s) ${action}.`,
                            timer: 2200,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: data.message || 'Something went wrong.'
                        });
                    }
                }).catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Could not reach the server.'
                }));
            });
        }

        /* ── Approve / Reject ALL ── */
        function bulkAll(action) {
            const isApprove = action === 'approved';
            const total = <?php echo $totalPending; ?>;
            Swal.fire({
                title: isApprove ? `Approve ALL ${total} items?` : `Reject ALL ${total} items?`,
                html: `<span style="color:#64748b;font-size:.9rem">This will ${action} <strong style="color:#0f172a">every pending note and book</strong> at once. This cannot be undone easily.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: isApprove ? '<i class="fas fa-check-double"></i> Yes, Approve All' : '<i class="fas fa-ban"></i> Yes, Reject All',
                cancelButtonText: 'Cancel',
                confirmButtonColor: isApprove ? '#059669' : '#dc2626',
                cancelButtonColor: '#e2e8f0',
            }).then(result => {
                if (!result.isConfirmed) return;
                // Collect all visible ids
                const items = [...document.querySelectorAll('.row-cb')].map(cb => ({
                    id: parseInt(cb.dataset.id),
                    doctype: cb.dataset.doctype
                }));
                Swal.fire({
                    title: 'Processing…',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                doAction(items, action).then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: isApprove ? 'success' : 'info',
                            title: 'Done!',
                            text: data.message || `All ${total} items ${action}.`,
                            timer: 2500,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: data.message || 'Something went wrong.'
                        });
                    }
                }).catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Could not reach the server.'
                }));
            });
        }

        /* SweetAlert on redirect */
        const up = new URLSearchParams(window.location.search).get('s');
        if (up === 'success') Swal.fire({
            icon: 'success',
            title: 'Done!',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false
        });
        else if (up === 'failed') Swal.fire({
            icon: 'error',
            title: 'Failed',
            timer: 2000,
            showConfirmButton: false
        });
        if (up) history.replaceState(null, '', 'pending.php');
    </script>
</body>

</html>