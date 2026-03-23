<?php
include_once('../config/connection.php');
$userId  = $_SESSION['admin_id'];
$cur = basename($_SERVER['PHP_SELF']);

function nb(string $file, string $cur): string
{
    return $file === $cur ? 'nb on' : 'nb';
}

/* Fetch full admin profile — name, role, and avatar all in one query */
$stmt = $conn->prepare('SELECT first_name, last_name, role, profile_image FROM admin_user WHERE admin_id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$sbAdmin = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$base       = 'http://localhost/ScholarSwap/admin/';
$profileImg = !empty($sbAdmin['profile_image']) ? $base . $sbAdmin['profile_image'] : '';
$sbInitials = strtoupper(
    substr($sbAdmin['first_name'] ?? 'A', 0, 1) .
        substr($sbAdmin['last_name']  ?? '',  0, 1)
);
$sbName = trim(($sbAdmin['first_name'] ?? '') . ' ' . ($sbAdmin['last_name'] ?? '')) ?: 'Admin';
$sbRole = $sbAdmin['role'] ?? 'admin';

// ── Initial counter values (loaded on page load) ──
$cntPending      = 0;   // total pending approvals (notes + books)
$cntNotesPending = 0;   // notes pending
$cntBooksPending = 0;   // books pending
$cntNewsPending  = 0;   // newspapers pending
$cntPendAdmins   = 0;
$cntRfUnread     = 0;
$cntRequests     = 0;   // material requests: Pending + In Progress
$cntStudents     = 0;   // total registered students
$cntTutors       = 0;   // total registered tutors
$cntAdmins       = 0;   // total admin users (active)

try {
    $q = $conn->query("SELECT COUNT(*) FROM notes WHERE approval_status='pending'");
    $cntNotesPending = $q ? (int)$q->fetchColumn() : 0;
    $q = $conn->query("SELECT COUNT(*) FROM books WHERE approval_status='pending'");
    $cntBooksPending = $q ? (int)$q->fetchColumn() : 0;
    $q = $conn->query("SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'");
    $cntNewsPending  = $q ? (int)$q->fetchColumn() : 0;
    $cntPending = $cntNotesPending + $cntBooksPending + $cntNewsPending;
} catch (Exception $e) {
}

try {
    $q = $conn->query("SELECT COUNT(*) FROM admin_user WHERE status='pending'");
    $cntPendAdmins = $q ? (int)$q->fetchColumn() : 0;
} catch (Exception $e) {
}

try {
    $q = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM reports  WHERE status = 'pending') +
            (SELECT COUNT(*) FROM feedback WHERE status = 'new')
    ");
    $cntRfUnread = $q ? (int)$q->fetchColumn() : 0;
} catch (Exception $e) {
}

try {
    $q = $conn->query("SELECT COUNT(*) FROM material_requests WHERE status IN ('Pending','In Progress')");
    $cntRequests = $q ? (int)$q->fetchColumn() : 0;
} catch (Exception $e) {
}

try {
    $q = $conn->query("SELECT COUNT(*) FROM students");
    $cntStudents = $q ? (int)$q->fetchColumn() : 0;
} catch (Exception $e) {
}

try {
    $q = $conn->query("SELECT COUNT(*) FROM tutors");
    $cntTutors = $q ? (int)$q->fetchColumn() : 0;
} catch (Exception $e) {
}

try {
    $q = $conn->query("SELECT COUNT(*) FROM admin_user WHERE status='active'");
    $cntAdmins = $q ? (int)$q->fetchColumn() : 0;
} catch (Exception $e) {
}
?>

<!-- ══ SIDEBAR CSS ══ -->
<style>
    :root {
        --sw: 265px;
        --hh: 62px;
        --navy-sb: #0b1628;
        --navy-sb2: #0f1e36;
    }

    .sb {
        width: var(--sw);
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        background: var(--navy-sb);
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 300;
        transition: transform .28s cubic-bezier(.4, 0, .2, 1);
        border-right: 1px solid rgba(255, 255, 255, .06);
    }

    .sb::-webkit-scrollbar {
        width: 3px;
    }

    .sb::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, .08);
        border-radius: 99px;
    }

    .sb-logo {
        padding: 20px 18px 16px;
        display: flex;
        align-items: center;
        gap: 11px;
        border-bottom: 1px solid rgba(255, 255, 255, .07);
        flex-shrink: 0;
        text-decoration: none;
    }

    .sb-mark {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
        color: #fff;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(37, 99, 235, .45);
        transition: transform .2s;
    }

    .sb-mark img {
        width: 100%;
        height: 100%;
    }

    .sb-logo:hover .sb-mark {
        transform: rotate(-5deg) scale(1.06);
    }

    .sb-brand {
        font-family: 'Syne', serif;
        font-size: 1.1rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
    }

    .sb-brand em {
        color: #fbbf24;
        font-style: normal;
    }

    .sb-grp {
        padding: 18px 10px 4px;
    }

    .sb-lbl {
        font-size: .58rem;
        font-weight: 700;
        letter-spacing: .15em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .2);
        padding: 0 10px;
        margin-bottom: 5px;
        user-select: none;
    }

    .nb {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 11px;
        border-radius: 9px;
        color: rgba(255, 255, 255, .46);
        font-size: .84rem;
        font-weight: 500;
        cursor: pointer;
        transition: background .15s, color .15s, transform .12s;
        margin-bottom: 2px;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        text-decoration: none;
        position: relative;
        white-space: nowrap;
    }

    .nb i {
        width: 17px;
        text-align: center;
        font-size: .82rem;
        flex-shrink: 0;
        transition: color .15s;
    }

    .nb:hover {
        background: rgba(255, 255, 255, .06);
        color: rgba(255, 255, 255, .9);
        transform: translateX(2px);
    }

    .nb:hover i {
        color: #60a5fa;
    }

    .nb.on {
        background: linear-gradient(90deg, rgba(37, 99, 235, .85), rgba(37, 99, 235, .6));
        color: #fff;
        font-weight: 600;
        box-shadow: 0 2px 12px rgba(37, 99, 235, .35), inset 0 1px 0 rgba(255, 255, 255, .1);
        transform: none;
    }

    .nb.on i {
        color: #fff;
    }

    .nb.on::before {
        content: '';
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: #93c5fd;
        box-shadow: 0 0 6px #93c5fd;
    }

    .nb:active {
        transform: scale(.97);
    }

    /* ── Counter chip ── */
    .chip {
        margin-left: auto;
        background: #dc2626;
        color: #fff;
        font-size: .58rem;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 99px;
        min-width: 18px;
        text-align: center;
        line-height: 1.4;
        box-shadow: 0 2px 6px rgba(220, 38, 38, .4);
        animation: chipPop 2s ease-in-out infinite;
        flex-shrink: 0;
        transition: transform .25s, opacity .25s;
    }

    .chip.chip-amber {
        background: #d97706;
        box-shadow: 0 2px 6px rgba(217, 119, 6, .4);
    }

    .chip.chip-blue {
        background: #2563eb;
        box-shadow: 0 2px 6px rgba(37, 99, 235, .4);
        animation: none;
    }

    .chip.chip-green {
        background: #059669;
        box-shadow: 0 2px 6px rgba(5, 150, 105, .4);
        animation: none;
    }

    .chip.chip-slate {
        background: #475569;
        box-shadow: 0 2px 6px rgba(71, 85, 105, .3);
        animation: none;
    }

    .chip.chip-hide {
        opacity: 0;
        transform: scale(.6);
        pointer-events: none;
    }

    @keyframes chipPop {

        0%,
        100% {
            transform: scale(1)
        }

        50% {
            transform: scale(1.1)
        }
    }

    /* ── Live indicator dot ── */
    .sb-live {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #22c55e;
        margin-left: auto;
        box-shadow: 0 0 0 0 rgba(34, 197, 94, .6);
        animation: livePulse 2s ease-in-out infinite;
        flex-shrink: 0;
    }

    @keyframes livePulse {
        0% {
            box-shadow: 0 0 0 0 rgba(34, 197, 94, .6);
        }

        70% {
            box-shadow: 0 0 0 5px rgba(34, 197, 94, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
        }
    }

    /* Footer */
    .sb-foot {
        margin-top: auto;
        padding: 12px;
        border-top: 1px solid rgba(255, 255, 255, .07);
        flex-shrink: 0;
    }

    .sb-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 11px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .05);
        border: 1px solid rgba(255, 255, 255, .07);
        cursor: pointer;
        transition: background .18s;
        text-decoration: none;
    }

    .sb-user:hover {
        background: rgba(255, 255, 255, .09);
    }

    .sb-av-init {
        width: 33px;
        height: 33px;
        border-radius: 50%;
        flex-shrink: 0;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .74rem;
        font-weight: 800;
        color: #fff;
        box-shadow: 0 2px 8px rgba(99, 102, 241, .35);
    }

    .sb-uname {
        font-size: .81rem;
        font-weight: 600;
        color: #fff;
        line-height: 1.25;
        text-transform: capitalize;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 142px;
    }

    .sb-urole {
        font-size: .62rem;
        color: rgba(255, 255, 255, .3);
        text-transform: capitalize;
        margin-top: 1px;
    }

    /* Topbar */
    .tb {
        position: fixed;
        top: 0;
        left: var(--sw);
        right: 0;
        height: var(--hh);
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        padding: 0 22px;
        gap: 12px;
        z-index: 200;
    }

    .tb-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .tb-sp {
        flex: 1;
    }

    .tb-search {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f1f5f9;
        border: 1.5px solid #e2e8f0;
        border-radius: 99px;
        padding: 6px 14px;
        width: 200px;
        transition: all .2s;
    }

    .tb-search:focus-within {
        border-color: #2563eb;
        background: #fff;
        width: 240px;
    }

    .tb-search i {
        color: #94a3b8;
        font-size: .82rem;
    }

    .tb-search input {
        border: none;
        background: none;
        outline: none;
        font-size: .84rem;
        color: #0f172a;
        width: 100%;
    }

    .tb-search input::placeholder {
        color: #94a3b8;
    }

    .tb-ico {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: #f1f5f9;
        border: 1.5px solid #e2e8f0;
        color: #475569;
        cursor: pointer;
        font-size: .88rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .18s;
        position: relative;
    }

    .tb-ico:hover {
        background: #fff;
        color: #2563eb;
        border-color: #2563eb;
    }

    .tb-dot {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 7px;
        height: 7px;
        background: #dc2626;
        border-radius: 50%;
        border: 2px solid #fff;
    }

    .tb-av {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 800;
        color: #fff;
        cursor: pointer;
        background: linear-gradient(135deg, #6366f1, #818cf8);
    }

    .menu-btn {
        display: none;
        background: none;
        border: none;
        font-size: 1.1rem;
        color: #475569;
        cursor: pointer;
        padding: 4px;
    }

    .main {
        margin-left: var(--sw);
        padding-top: var(--hh);
        min-height: 100vh;
        flex: 1;
        background: #f1f5f9;
    }

    .pg {
        padding: 22px 24px;
    }

    @media(max-width:700px) {
        .sb {
            transform: translateX(-100%);
            box-shadow: none;
        }

        .sb.open {
            transform: translateX(0);
            box-shadow: 6px 0 40px rgba(0, 0, 0, .5);
        }

        .main {
            margin-left: 0;
        }

        .tb {
            left: 0;
        }

        .menu-btn {
            display: flex;
        }
    }
</style>

<!-- ══ SIDEBAR HTML ══ -->
<nav class="sb" id="sb">

    <a class="sb-logo" href="dashboard.php">
        <div class="sb-mark">
            <img src="../../assets/img/logo.png" alt="ScholarSwap"
                onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-graduation-cap\'></i>'">
        </div>
        <div class="sb-brand">Scholar<em>Swap</em></div>
        <span class="sb-live" id="live-dot" title="Counters auto-refresh" style="margin-left:auto;flex-shrink:0"></span>
    </a>

    <!-- Overview -->
    <div class="sb-grp">
        <div class="sb-lbl">Overview</div>
        <a class="<?php echo nb('dashboard.php', $cur); ?>" href="dashboard.php">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
    </div>

    <!-- Approvals -->
    <div class="sb-grp">
        <div class="sb-lbl">Approvals</div>
        <a class="<?php echo nb('pending.php', $cur); ?>" href="pending.php">
            <i class="fas fa-hourglass-half"></i> Pending
            <span class="chip<?php echo $cntPending === 0 ? ' chip-hide' : ''; ?>" id="chip-pending">
                <?php echo $cntPending; ?>
            </span>
        </a>
    </div>

    <!-- Content -->
    <div class="sb-grp">
        <div class="sb-lbl">Content</div>
        <a class="<?php echo nb('notes.php', $cur); ?>" href="notes.php">
            <i class="fas fa-file-lines"></i> Notes
            <span class="chip chip-amber<?php echo $cntNotesPending === 0 ? ' chip-hide' : ''; ?>" id="chip-notes-pending" title="Pending approval">
                <?php echo $cntNotesPending; ?>
            </span>
        </a>
        <a class="<?php echo nb('books.php', $cur); ?>" href="books.php">
            <i class="fas fa-book"></i> Books
            <span class="chip chip-amber<?php echo $cntBooksPending === 0 ? ' chip-hide' : ''; ?>" id="chip-books-pending" title="Pending approval">
                <?php echo $cntBooksPending; ?>
            </span>
        </a>
        <a class="<?php echo nb('newspapers.php', $cur); ?>" href="newspapers.php">
            <i class="fas fa-newspaper"></i> Newspapers
            <span class="chip chip-amber<?php echo $cntNewsPending === 0 ? ' chip-hide' : ''; ?>" id="chip-news-pending" title="Pending approval">
                <?php echo $cntNewsPending; ?>
            </span>
        </a>
    </div>

    <!-- Users -->
    <div class="sb-grp">
        <div class="sb-lbl">Users</div>
        <a class="<?php echo nb('students.php', $cur); ?>" href="students.php">
            <i class="fas fa-user-graduate"></i> Students
            <span class="chip chip-slate<?php echo $cntStudents === 0 ? ' chip-hide' : ''; ?>" id="chip-students">
                <?php echo $cntStudents; ?>
            </span>
        </a>
        <a class="<?php echo nb('tutors.php', $cur); ?>" href="tutors.php">
            <i class="fas fa-user-tie"></i> Tutors
            <span class="chip chip-slate<?php echo $cntTutors === 0 ? ' chip-hide' : ''; ?>" id="chip-tutors">
                <?php echo $cntTutors; ?>
            </span>
        </a>
        <a class="<?php echo nb('admins.php', $cur); ?>" href="admins.php">
            <i class="fas fa-user-shield"></i> Admins
            <span class="chip chip-slate<?php echo $cntAdmins === 0 ? ' chip-hide' : ''; ?>" id="chip-admins-total">
                <?php echo $cntAdmins; ?>
            </span>
        </a>
    </div>

    <!-- Actions -->
    <div class="sb-grp">
        <div class="sb-lbl">Actions</div>

        <a class="<?php echo nb('admin_access.php', $cur); ?>" href="admin_access.php">
            <i class="fas fa-user-shield"></i> Admin Access
            <span class="chip<?php echo $cntPendAdmins === 0 ? ' chip-hide' : ''; ?>" id="chip-admins">
                <?php echo $cntPendAdmins; ?>
            </span>
        </a>

        <a class="<?php echo nb('reports_feedback.php', $cur); ?>" href="reports_feedback.php">
            <i class="fas fa-flag"></i> Reports &amp; Feedback
            <span class="chip<?php echo $cntRfUnread === 0 ? ' chip-hide' : ''; ?>" id="chip-rf">
                <?php echo $cntRfUnread; ?>
            </span>
        </a>

        <!-- ── Material Requests (new) ── -->
        <a class="<?php echo nb('material_requests.php', $cur); ?>" href="material_requests.php">
            <i class="fas fa-inbox"></i> Material Requests
            <span class="chip chip-amber<?php echo $cntRequests === 0 ? ' chip-hide' : ''; ?>" id="chip-requests">
                <?php echo $cntRequests; ?>
            </span>
        </a>

        <a class="<?php echo nb('admin_notifications.php', $cur); ?>" href="admin_notifications.php">
            <i class="fa fa-bell"></i> Notifications
        </a>
        <a class="<?php echo nb('report_data.php', $cur); ?>" href="report_data.php">
            <i class="fas fa-file-pdf"></i> Generate Report
        </a>
    </div>

    <!-- Account -->
    <div class="sb-grp">
        <div class="sb-lbl">Account</div>
        <a class="<?php echo nb('profile.php', $cur); ?>" href="profile.php">
            <i class="fas fa-user-circle"></i> My Profile
        </a>
        <a class="nb" href="../admin_pages/admin_logout.php">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>
    </div>

    <div class="sb-foot">
        <a class="sb-user" href="profile.php">
            <div class="sb-av-init">
                <?php if (!empty($sbAdmin['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($profileImg); ?>"
                        alt="profile"
                        style="width:100%;height:100%;object-fit:cover;border-radius:50%"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span style="display:none"><?php echo $sbInitials; ?></span>
                <?php else: ?>
                    <?php echo $sbInitials; ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="sb-uname"><?php echo htmlspecialchars($sbName); ?></div>
                <div class="sb-urole"><?php echo htmlspecialchars($sbRole); ?></div>
            </div>
        </a>
    </div>
</nav>

<script>
    /* ════════════════════════════════════════════════════════
   LIVE COUNTER — polls every 30 seconds without reload
   Endpoint: auth/sidebar_counts.php  (returns JSON)
════════════════════════════════════════════════════════ */
    (function() {
        var INTERVAL = 30000; // 30 seconds
        var liveDot = document.getElementById('live-dot');

        function setChip(id, count, alwaysShow) {
            var el = document.getElementById(id);
            if (!el) return;
            el.textContent = count;
            if (count > 0 || alwaysShow) {
                el.classList.remove('chip-hide');
            } else {
                el.classList.add('chip-hide');
            }
        }

        function pulse() {
            if (!liveDot) return;
            liveDot.style.background = '#fbbf24';
            setTimeout(function() {
                liveDot.style.background = '#22c55e';
            }, 600);
        }

        function fetchCounts() {
            fetch('auth/sidebar_counts.php', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    if (!d) return;

                    /* Approvals */
                    setChip('chip-pending', (d.notes_pending || 0) + (d.books_pending || 0) + (d.news_pending || 0));

                    /* Content — per-type pending */
                    setChip('chip-notes-pending', d.notes_pending || 0);
                    setChip('chip-books-pending', d.books_pending || 0);
                    setChip('chip-news-pending', d.news_pending || 0);

                    /* Users — always show totals */
                    setChip('chip-students', d.students || 0, true);
                    setChip('chip-tutors', d.tutors || 0, true);
                    setChip('chip-admins-total', d.admins || 0, true);

                    /* Actions */
                    setChip('chip-admins', d.admins_pending || 0);
                    setChip('chip-rf', d.rf || 0);
                    setChip('chip-requests', d.requests || 0);

                    pulse();
                })
                .catch(function() {
                    /* silent fail — counters stay at last known value */
                });
        }

        /* Poll every 30 s */
        setInterval(fetchCounts, INTERVAL);

        /* Also refresh immediately when tab becomes visible again */
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') fetchCounts();
        });

    })();

    /* Mobile toggle */
    document.addEventListener('DOMContentLoaded', function() {
        var menuBtn = document.getElementById('menuBtn');
        var sb = document.getElementById('sb');
        if (menuBtn) menuBtn.addEventListener('click', function() {
            sb.classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            if (sb.classList.contains('open') && !sb.contains(e.target) && e.target !== menuBtn) {
                sb.classList.remove('open');
            }
        });
    });
</script>