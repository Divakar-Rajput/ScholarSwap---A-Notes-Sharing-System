<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once('config/connection.php');
$isLoggedIn = isset($_SESSION['user_id']);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl  = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/ScholarSwap/';

$profileImg = $baseUrl . 'assets/img/user.png';
$initials   = 'U';
$notifCount = 0;
$notifItems = [];

if ($isLoggedIn) {
    $uid = (int)$_SESSION['user_id'];

    $ps = $conn->prepare("
        SELECT u.profile_image,
               COALESCE(s.first_name, t.first_name, '') AS fn,
               COALESCE(s.last_name,  t.last_name,  '') AS ln
        FROM users u
        LEFT JOIN students s ON s.user_id = u.user_id
        LEFT JOIN tutors   t ON t.user_id = u.user_id
        WHERE u.user_id = ? LIMIT 1
    ");
    $ps->execute([$uid]);
    $pu = $ps->fetch(PDO::FETCH_ASSOC);

    if (!empty($pu['profile_image'])) {
        $profileImg = $baseUrl . ltrim($pu['profile_image'], '/');
    }
    $initials = strtoupper(substr($pu['fn'] ?? 'U', 0, 1) . substr($pu['ln'] ?? '', 0, 1));

    try {
        $nq = $conn->prepare("
            SELECT notif_id, type, title, message,
                   resource_type, resource_id, resource_title,
                   from_name, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $nq->execute([$uid]);

        $typeMap = [
            'warning'         => ['fa-triangle-exclamation', '#7A0C0C', 'rgba(122,12,12,.10)',  'Warning'],
            'admin_message'   => ['fa-envelope',             '#7A0C0C', 'rgba(122,12,12,.10)',  'Message'],
            'info'            => ['fa-circle-info',          '#2563eb', 'rgba(37,99,235,.10)',  'Info'],
            'success'         => ['fa-check-circle',         '#059669', 'rgba(5,150,105,.10)',  'Success'],
            'upload_approved' => ['fa-check-circle',         '#059669', 'rgba(5,150,105,.10)',  'Approved'],
            'upload_rejected' => ['fa-times-circle',         '#b45309', 'rgba(180,83,9,.10)',   'Rejected'],
            'new_upload'      => ['fa-cloud-arrow-up',       '#7A0C0C', 'rgba(122,12,12,.10)',  'New Upload'],
            'banned_content'  => ['fa-ban',                  '#dc2626', 'rgba(220,38,38,.10)',  'Content Banned'],
        ];

        foreach ($nq->fetchAll(PDO::FETCH_ASSOC) as $n) {
            [$icon, $color, $bg, $label] = $typeMap[$n['type']] ?? ['fa-bell', '#7A0C0C', 'rgba(122,12,12,.10)', 'Notification'];
            $href = '#';
            if (!empty($n['resource_id']) && !empty($n['resource_type'])) {
                if ($n['resource_type'] === 'newspaper') {
                    $href = $baseUrl . 'newspaper_reader.php?r=' . urlencode($n['resource_id']);
                } else {
                    $href = $baseUrl . 'notes_reader.php?r=' . urlencode($n['resource_id']) . '&t=' . urlencode($n['resource_type']);
                }
            }
            $notifItems[] = [
                'type' => $n['type'],
                'label' => $label,
                'icon' => $icon,
                'color' => $color,
                'bg' => $bg,
                'title' => $n['title'],
                'message' => $n['message'],
                'time' => $n['created_at'],
                'id' => $n['notif_id'],
                'href' => $href,
                'is_read' => (int)$n['is_read'],
                'resource_type' => $n['resource_type'] ?? '',
                'resource_title' => $n['resource_title'] ?? '',
                'from_name' => $n['from_name'] ?? '',
            ];
        }
    } catch (Exception $e) {
    }

    $notifCount = 0;
    foreach ($notifItems as $n) {
        if (!$n['is_read']) $notifCount++;
    }
}

$subCategories = [
    ['label' => 'Mathematics', 'url' => $baseUrl . 'search.php?q=mathematics', 'icon' => 'fa-square-root-variable'],
    ['label' => 'Physics',     'url' => $baseUrl . 'search.php?q=physics',     'icon' => 'fa-atom'],
    ['label' => 'Chemistry',   'url' => $baseUrl . 'search.php?q=chemistry',   'icon' => 'fa-flask'],
    ['label' => 'Biology',     'url' => $baseUrl . 'search.php?q=biology',     'icon' => 'fa-dna'],
    ['label' => 'History',     'url' => $baseUrl . 'search.php?q=history',     'icon' => 'fa-landmark'],
    ['label' => 'Geography',   'url' => $baseUrl . 'search.php?q=geography',   'icon' => 'fa-earth-asia'],
    ['label' => 'English',     'url' => $baseUrl . 'search.php?q=english',     'icon' => 'fa-book-open'],
    ['label' => 'Computer',    'url' => $baseUrl . 'search.php?q=computer',    'icon' => 'fa-laptop-code'],
    ['label' => 'Economics',   'url' => $baseUrl . 'search.php?q=economics',   'icon' => 'fa-chart-line'],
    ['label' => 'Accounting',  'url' => $baseUrl . 'search.php?q=accounting',  'icon' => 'fa-calculator'],
    ['label' => 'Law',         'url' => $baseUrl . 'search.php?q=law',         'icon' => 'fa-scale-balanced'],
    ['label' => 'Engineering', 'url' => $baseUrl . 'search.php?q=engineering', 'icon' => 'fa-gears'],
    ['label' => 'Medicine',    'url' => $baseUrl . 'search.php?q=medicine',    'icon' => 'fa-stethoscope'],
    ['label' => 'Psychology',  'url' => $baseUrl . 'search.php?q=psychology',  'icon' => 'fa-brain'],
    ['label' => 'Research',    'url' => $baseUrl . 'search.php?q=research',    'icon' => 'fa-microscope'],
];

$curPage    = basename($_SERVER['PHP_SELF']);
$curQ       = $_GET['q'] ?? '';
?>

<!-- PRELOADER -->
<div id="ss-preloader">
    <div class="ss-pre-grid"></div>
    <div class="ss-pre-content">
        <div class="ss-pre-logo">
            <div class="ss-pre-logo-mark">
                <img src="<?php echo $baseUrl; ?>assets/img/logo.png" alt="ScholarSwap"
                    onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-graduation-cap\'></i>'">
            </div>
            <div class="ss-pre-logo-text">Scholar<em>Swap</em></div>
        </div>
        <div class="ss-pre-tag">Your Academic Resource Hub</div>
        <div class="ss-pre-spinner">
            <div class="ss-pre-ring"></div>
            <div class="ss-pre-dot"></div>
        </div>
        <div class="ss-pre-bar-wrap">
            <div class="ss-pre-bar-track">
                <div class="ss-pre-bar-fill" id="ssBarFill"></div>
            </div>
        </div>
        <div class="ss-pre-status" id="ssPreStatus">Loading resources…</div>
        <div class="ss-pre-pills">
            <span class="ss-pre-pill"><i class="fas fa-file-lines"></i> Notes</span>
            <span class="ss-pre-pill"><i class="fas fa-book"></i> Books</span>
            <span class="ss-pre-pill"><i class="fas fa-newspaper"></i> Papers</span>
        </div>
    </div>
</div>
<script>
    (function() {
        var pre = document.getElementById('ss-preloader'),
            status = document.getElementById('ssPreStatus');
        var msgs = ['Loading resources…', 'Fetching your library…', 'Almost ready…', 'Preparing your workspace…'],
            i = 0;
        var iv = setInterval(function() {
            i = (i + 1) % msgs.length;
            if (status) status.textContent = msgs[i];
        }, 600);

        function hide() {
            clearInterval(iv);
            if (status) status.textContent = 'Welcome!';
            setTimeout(function() {
                if (pre) pre.classList.add('hide');
                setTimeout(function() {
                    if (pre && pre.parentNode) pre.parentNode.removeChild(pre);
                }, 520);
            }, 180);
        }
        if (document.readyState === 'complete') hide();
        else {
            window.addEventListener('load', hide);
            setTimeout(hide, 4000);
        }
    })();
</script>

<style>
    /* ─── TOPBAR ─── */
    .topbar {
        background: var(--primary);
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 32px;
        font-size: .72rem;
        color: rgba(255, 255, 255, .75);
        letter-spacing: .04em;
    }

    .topbar a {
        color: rgba(255, 255, 255, .75);
        text-decoration: none;
        transition: color .15s;
    }

    .topbar a:hover {
        color: #fff;
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .topbar-divider {
        width: 1px;
        height: 14px;
        background: rgba(255, 255, 255, .2);
    }

    @media(max-width:768px) {
        .topbar {
            display: none;
        }
    }

    /* ─── HAMBURGER ─── */
    .hdr-hamburger {
        display: none;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border: none;
        background: transparent;
        cursor: pointer;
        padding: 0;
        border-radius: 8px;
        flex-shrink: 0;
        transition: background .15s;
    }

    .hdr-hamburger:hover {
        background: rgba(122, 12, 12, .07);
    }

    .hbr-wrap {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .hbr-bar {
        display: block;
        width: 20px;
        height: 2px;
        background: var(--primary, #7A0C0C);
        border-radius: 2px;
        transition: transform .28s cubic-bezier(.4, 0, .2, 1), opacity .2s;
        transform-origin: center;
    }

    .hdr-hamburger.open .hbr-bar:nth-child(1) {
        transform: translateY(7px) rotate(45deg);
    }

    .hdr-hamburger.open .hbr-bar:nth-child(2) {
        opacity: 0;
        transform: scaleX(0);
    }

    .hdr-hamburger.open .hbr-bar:nth-child(3) {
        transform: translateY(-7px) rotate(-45deg);
    }

    @media(max-width:768px) {
        .hdr-hamburger {
            display: flex;
        }

        .hdr-nav {
            display: none !important;
        }

        .hdr-search {
            display: none !important;
        }

        .hdr-sp {
            display: none !important;
        }

        /* Hide desktop upload text-button & mobile search icon on mobile */
        .btn-upload {
            display: none !important;
        }

        .mobile-search-btn {
            display: none !important;
        }

        /* Mobile upload icon button — shown only on mobile */
        .mob-upload-icon {
            display: flex !important;
        }

        /* Push actions to the right on mobile */
        .hdr-actions {
            margin-left: auto;
        }
    }

    /* Mobile upload icon button — hidden on desktop */
    .mob-upload-icon {
        display: none;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 8px;
        border: 1px solid rgba(122, 12, 12, .25);
        background: var(--primary, #7A0C0C);
        color: #fff;
        font-size: .88rem;
        cursor: pointer;
        transition: all .18s;
        flex-shrink: 0;
        position: relative;
    }

    /* ─── MOBILE DRAWER ─── */
    .mob-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(10, 4, 4, .5);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 1100;
        opacity: 0;
        visibility: hidden;
        transition: opacity .28s ease, visibility .28s ease;
    }

    .mob-backdrop.open {
        opacity: 1;
        visibility: visible;
    }

    .mob-drawer {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 300px;
        max-width: 88vw;
        background: #fff;
        z-index: 1101;
        display: flex;
        flex-direction: column;
        transform: translateX(-100%);
        transition: transform .3s cubic-bezier(.4, 0, .2, 1);
        box-shadow: 6px 0 40px rgba(0, 0, 0, .18);
        overflow: hidden;
    }

    .mob-drawer.open {
        transform: translateX(0);
    }

    /* Drawer head */
    .mob-drawer-head {
        background: var(--primary, #7A0C0C);
        height: 64px;
        padding: 0 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .mob-dlogo {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }

    .mob-dlogo-mark {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(255, 255, 255, .18);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: .9rem;
    }

    .mob-dlogo-mark img {
        width: 20px;
        height: 20px;
        object-fit: contain;
        filter: brightness(10);
    }

    .mob-dlogo-text {
        font-size: 1.1rem;
        font-weight: 700;
        color: #fff;
        letter-spacing: -.01em;
    }

    .mob-dlogo-text em {
        font-style: italic;
        opacity: .85;
    }

    .mob-dclose {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(255, 255, 255, .15);
        border: none;
        color: #fff;
        font-size: .85rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s;
    }

    .mob-dclose:hover {
        background: rgba(255, 255, 255, .28);
    }

    /* User card */
    .mob-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        background: rgba(122, 12, 12, .04);
        border-bottom: 1px solid rgba(0, 0, 0, .07);
        cursor: pointer;
        flex-shrink: 0;
        transition: background .15s;
    }

    .mob-user:hover {
        background: rgba(122, 12, 12, .08);
    }

    .mob-uavatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        flex-shrink: 0;
        background: rgba(122, 12, 12, .1);
        border: 2px solid rgba(122, 12, 12, .2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: .9rem;
        color: var(--primary, #7A0C0C);
        overflow: hidden;
    }

    .mob-uavatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .mob-uname {
        font-size: .87rem;
        font-weight: 700;
        color: #1a1a1a;
        line-height: 1.3;
    }

    .mob-urole {
        font-size: .7rem;
        color: #999;
        margin-top: 1px;
    }

    /* Drawer search */
    .mob-dsearch {
        padding: 13px 16px 10px;
        border-bottom: 1px solid rgba(0, 0, 0, .07);
        flex-shrink: 0;
    }

    .mob-dsearch-inner {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f6f1f1;
        border-radius: 10px;
        padding: 0 13px;
        height: 40px;
        border: 1px solid rgba(122, 12, 12, .12);
    }

    .mob-dsearch-inner i {
        color: #bbb;
        font-size: .8rem;
    }

    .mob-dsearch-inner input {
        border: none;
        background: transparent;
        outline: none;
        font-size: .83rem;
        color: #1a1a1a;
        width: 100%;
    }

    .mob-dsearch-inner input::placeholder {
        color: #bbb;
    }

    /* Drawer body scroll */
    .mob-dbody {
        flex: 1;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .mob-dbody::-webkit-scrollbar {
        width: 3px;
    }

    .mob-dbody::-webkit-scrollbar-thumb {
        background: rgba(122, 12, 12, .15);
        border-radius: 3px;
    }

    /* Section label */
    .mob-slabel {
        font-size: .62rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #c0b8b8;
        padding: 14px 18px 5px;
    }

    /* Nav links */
    .mob-nlink {
        display: flex;
        align-items: center;
        gap: 13px;
        padding: 11px 18px;
        text-decoration: none;
        font-size: .87rem;
        font-weight: 500;
        color: #2a2a2a;
        border-left: 3px solid transparent;
        transition: all .14s;
    }

    .mob-nlink i {
        width: 20px;
        text-align: center;
        font-size: .83rem;
        color: #c0b0b0;
        transition: color .14s;
        flex-shrink: 0;
    }

    .mob-nlink:hover,
    .mob-nlink.active {
        background: rgba(122, 12, 12, .05);
        color: var(--primary, #7A0C0C);
        border-left-color: var(--primary, #7A0C0C);
    }

    .mob-nlink:hover i,
    .mob-nlink.active i {
        color: var(--primary, #7A0C0C);
    }

    /* Upload CTA inside drawer */
    .mob-upload-cta {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin: 10px 16px 4px;
        padding: 12px;
        background: var(--primary, #7A0C0C);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: .85rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        letter-spacing: .02em;
        transition: background .16s;
    }

    .mob-upload-cta:hover {
        background: #5a0808;
        color: #fff;
    }

    /* Subject chips */
    .mob-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        padding: 8px 16px 14px;
    }

    .mob-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 11px;
        border-radius: 20px;
        background: #f6f1f1;
        border: 1px solid rgba(122, 12, 12, .12);
        font-size: .72rem;
        font-weight: 600;
        color: var(--primary, #7A0C0C);
        text-decoration: none;
        transition: all .14s;
    }

    .mob-chip:hover {
        background: var(--primary, #7A0C0C);
        color: #fff;
        border-color: var(--primary, #7A0C0C);
    }

    .mob-chip i {
        font-size: .65rem;
    }

    /* Drawer footer */
    .mob-dfooter {
        margin-top: auto;
        border-top: 1px solid rgba(0, 0, 0, .07);
        padding: 10px 0 6px;
        flex-shrink: 0;
    }

    .mob-flink {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 18px;
        font-size: .82rem;
        color: #666;
        text-decoration: none;
        transition: all .14s;
    }

    .mob-flink i {
        width: 18px;
        text-align: center;
        font-size: .78rem;
        color: #ccc;
    }

    .mob-flink:hover {
        color: var(--primary, #7A0C0C);
        background: rgba(122, 12, 12, .04);
    }

    .mob-flink:hover i {
        color: var(--primary, #7A0C0C);
    }

    .mob-flink.danger {
        color: #c0392b;
    }

    .mob-flink.danger i {
        color: #e74c3c;
    }

    /* ─── SUBHEADER ─── */
    .ss-subheader {
        position: sticky;
        top: 64px;
        z-index: 10;
        background: #fff;
        display: flex;
        align-items: center;
        height: 40px;
        width: 100%;
        overflow: hidden;
        border-bottom: 1px solid #e2e2e2;
    }

    .ss-sub-scroll {
        display: flex;
        align-items: center;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        flex: 1;
        height: 100%;
    }

    .ss-sub-scroll::-webkit-scrollbar {
        display: none;
    }

    .ss-sub-scroll a {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        height: 100%;
        padding: 0 15px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--primary);
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
        border-bottom: 3px solid transparent;
        margin-bottom: -1px;
        transition: color .15s, border-color .15s, background .15s;
    }

    .ss-sub-scroll a:hover {
        color: #fff;
        background: var(--primary);
    }

    .ss-sub-scroll a.sub-active {
        color: #fff;
        background: var(--primary);
    }

    .ss-sub-arrow {
        width: 34px;
        height: 40px;
        flex-shrink: 0;
        border: none;
        background: var(--primary);
        color: #fff;
        font-size: .72rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s, opacity .2s;
        z-index: 2;
    }

    .ss-sub-arrow:hover {
        background: var(--primary-dark, #5a0808);
    }

    .ss-sub-arrow.ss-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .ss-fade-l,
    .ss-fade-r {
        position: absolute;
        top: 0;
        width: 36px;
        height: 100%;
        pointer-events: none;
        z-index: 1;
        opacity: 0;
        transition: opacity .2s;
    }

    .ss-fade-l {
        left: 34px;
        background: linear-gradient(to right, var(--primary), transparent);
    }

    .ss-fade-r {
        right: 34px;
        background: linear-gradient(to left, var(--primary), transparent);
    }

    .ss-fade-l.ss-show,
    .ss-fade-r.ss-show {
        opacity: 1;
    }

    @media(max-width:480px) {

        .ss-sub-arrow,
        .ss-fade-l,
        .ss-fade-r {
            display: none;
        }
    }

    /* ─── NOTIFICATION STYLES ─── */
    .notif-new-pill {
        background: var(--primary);
        color: #fff;
        font-size: .6rem;
        padding: 1px 7px;
        border-radius: 10px;
        margin-left: 5px;
        vertical-align: middle;
    }

    .notif-unread-dot {
        width: 8px;
        height: 8px;
        background: var(--primary);
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 5px;
    }

    .notif-item.notif-unread {
        background: var(--primary-xlight);
    }

    .notif-item.notif-unread:hover {
        background: var(--primary-light);
    }

    .notif-item {
        cursor: pointer;
    }

    #notifModal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        pointer-events: none;
    }

    #notifModal.open {
        pointer-events: all;
    }

    .nm-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(90, 9, 9, .45);
        backdrop-filter: blur(6px);
        opacity: 0;
        transition: opacity .25s ease;
    }

    #notifModal.open .nm-backdrop {
        opacity: 1;
    }

    .nm-box {
        position: relative;
        background: #fff;
        border-radius: 20px;
        width: min(480px, 96vw);
        max-height: 88vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--border);
        transform: scale(.93) translateY(22px);
        opacity: 0;
        transition: transform .3s cubic-bezier(.34, 1.56, .64, 1), opacity .25s ease;
        padding: 0 0 24px;
    }

    #notifModal.open .nm-box {
        transform: scale(1) translateY(0);
        opacity: 1;
    }

    .nm-bar {
        height: 4px;
        border-radius: 20px 20px 0 0;
        width: 100%;
        flex-shrink: 0;
    }

    .nm-close {
        position: absolute;
        top: 14px;
        right: 16px;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: 1.5px solid var(--border);
        background: var(--page-bg);
        color: var(--text2);
        font-size: .88rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .15s;
        z-index: 2;
        box-shadow: none;
    }

    .nm-close:hover {
        background: var(--primary-light);
        color: var(--primary);
        border-color: rgba(122, 12, 12, .28);
    }

    .nm-hero {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 22px 24px 12px;
    }

    .nm-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .nm-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 99px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .nm-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text);
        line-height: 1.3;
        padding: 0 24px 10px;
        margin: 0;
    }

    .nm-message {
        font-size: .875rem;
        color: var(--text2);
        line-height: 1.7;
        padding: 0 24px 18px;
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .nm-res-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, var(--primary), #9b1010);
        color: #fff;
        text-decoration: none;
        padding: 9px 18px;
        border-radius: 10px;
        font-size: .83rem;
        font-weight: 700;
        margin-top: 4px;
        transition: all .18s;
        box-shadow: 0 3px 10px rgba(122, 12, 12, .25);
        white-space: nowrap;
    }

    .nm-res-link:hover {
        background: linear-gradient(135deg, var(--primary-dark), var(--primary));
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(122, 12, 12, .32);
        color: #fff;
    }

    .nm-inline-link {
        color: var(--primary);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 2px;
        word-break: break-all;
    }

    .nm-inline-link:hover {
        color: var(--primary-dark);
    }

    .nm-meta {
        margin: 0 24px 20px;
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .nm-meta-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        font-size: .8rem;
    }

    .nm-meta-row:last-child {
        border-bottom: none;
    }

    .nm-meta-label {
        min-width: 90px;
        flex-shrink: 0;
        color: var(--text2);
        font-weight: 600;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        padding-top: 1px;
    }

    .nm-meta-val {
        color: var(--text);
        font-weight: 500;
        word-break: break-word;
    }

    .nm-actions {
        display: flex;
        gap: 10px;
        padding: 0 24px;
        flex-wrap: wrap;
    }

    .nm-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        border-radius: 10px;
        font-size: .82rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        font-family: inherit;
        transition: all .18s;
        text-decoration: none;
        box-shadow: none;
    }

    .nm-btn-secondary {
        background: var(--page-bg);
        color: var(--text2);
        border: 1.5px solid var(--border);
    }

    .nm-btn-secondary:hover {
        background: var(--primary-light);
        color: var(--primary);
        border-color: rgba(122, 12, 12, .25);
    }
</style>

<!-- ── TOPBAR ── -->
<div class="topbar">
    <div class="topbar-left">
        <span><i class="fas fa-graduation-cap" style="margin-right:5px;opacity:.7"></i>Academic Resource Hub</span>
        <div class="topbar-divider"></div>
        <a href="#">Help Center</a>
        <a href="#">Community</a>
    </div>
    <div class="topbar-right">
        <a href="#"><i class="fas fa-envelope" style="margin-right:4px;font-size:.68rem"></i>support@scholarswap.com</a>
        <div class="topbar-divider"></div>
        <a href="#">EN</a>
    </div>
</div>

<!-- ── MAIN HEADER ── -->
<header class="site-header">

    <!-- ☰ Hamburger — mobile only -->
    <button class="hdr-hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
        <div class="hbr-wrap">
            <span class="hbr-bar"></span>
            <span class="hbr-bar"></span>
            <span class="hbr-bar"></span>
        </div>
    </button>

    <a href="<?php echo $baseUrl; ?>index.php" class="hdr-logo">
        <div class="hdr-logo-mark">
            <img src="<?php echo $baseUrl; ?>assets/img/logo.png" alt="ScholarSwap"
                onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-graduation-cap\'></i>'">
        </div>
        <div class="hdr-logo-text">Scholar<em>Swap</em></div>
    </a>

    <nav class="hdr-nav">
        <?php
        $cur = basename($_SERVER['PHP_SELF']);
        foreach (
            [
                'index.php'     => ['fas fa-house',     'Home'],
                'notes.php'     => ['fas fa-file-lines', 'Notes'],
                'books.php'     => ['fas fa-book',       'Books'],
                'newspaper.php' => ['fas fa-newspaper',  'Newspaper'],
            ] as $page => [$icon, $label]
        ):
        ?>
            <a href="<?php echo $baseUrl . $page; ?>" class="<?php echo $cur === $page ? 'active' : ''; ?>">
                <i class="<?php echo $icon; ?>"></i> <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="hdr-search">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search notes, subjects…" id="hdrSearchInput" autocomplete="off">
    </div>

    <div class="hdr-sp"></div>

    <div class="hdr-actions">
        <?php if ($isLoggedIn): ?>
            <button class="btn-upload" id="openUploadModal">
                <i class="fas fa-cloud-arrow-up"></i>
                <span class="btn-upload-text">Upload</span>
            </button>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
            <!-- Upload icon — mobile only, shown before bell -->
            <button class="mob-upload-icon" id="mobUploadIconBtn" title="Upload" aria-label="Upload content">
                <i class="fas fa-cloud-arrow-up"></i>
            </button>

            <div class="notif-wrapper">
                <button class="icon-btn" id="notifBtn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="notif-badge" id="notifBadge"><?php echo $notifCount > 99 ? '99+' : $notifCount; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span class="notif-header-title">
                            <i class="fas fa-bell" style="color:var(--primary);margin-right:5px;font-size:.85em"></i>
                            Notifications
                            <?php if ($notifCount > 0): ?>
                                <span class="notif-new-pill"><?php echo $notifCount; ?> new</span>
                            <?php endif; ?>
                        </span>
                        <?php if ($notifCount > 0): ?>
                            <button class="notif-mark-all" id="markAllRead">Mark all read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notif-list" id="notifList">
                        <?php if (empty($notifItems)): ?>
                            <div class="notif-empty"><i class="fas fa-bell-slash"></i> You're all caught up!</div>
                        <?php else: ?>
                            <?php foreach ($notifItems as $n):
                                $diff = time() - strtotime($n['time']);
                                if ($diff < 60)        $ago = 'just now';
                                elseif ($diff < 3600)  $ago = floor($diff / 60) . 'm ago';
                                elseif ($diff < 86400) $ago = floor($diff / 3600) . 'h ago';
                                else                   $ago = floor($diff / 86400) . 'd ago';
                                $fullDate  = date('d M Y, h:i A', strtotime($n['time']));
                                $modalData = htmlspecialchars(json_encode([
                                    'id' => $n['id'],
                                    'type' => $n['type'],
                                    'label' => $n['label'],
                                    'icon' => $n['icon'],
                                    'color' => $n['color'],
                                    'bg' => $n['bg'],
                                    'title' => $n['title'],
                                    'message' => $n['message'],
                                    'href' => $n['href'],
                                    'resource_type' => $n['resource_type'],
                                    'resource_title' => $n['resource_title'],
                                    'from_name' => $n['from_name'],
                                    'time' => $fullDate,
                                    'ago' => $ago,
                                    'is_read' => $n['is_read'],
                                ]), ENT_QUOTES);
                            ?>
                                <div class="notif-item <?php echo !$n['is_read'] ? 'notif-unread' : ''; ?>"
                                    data-id="<?php echo $n['id']; ?>"
                                    data-modal='<?php echo $modalData; ?>'
                                    role="button" tabindex="0">
                                    <div class="notif-item-icon" style="background:<?php echo $n['bg']; ?>;color:<?php echo $n['color']; ?>">
                                        <i class="fas <?php echo $n['icon']; ?>"></i>
                                    </div>
                                    <div class="notif-item-body">
                                        <div class="notif-item-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                        <?php if (!empty($n['message'])): ?>
                                            <div class="notif-item-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                                        <?php endif; ?>
                                        <div class="notif-item-time"><?php echo $ago; ?></div>
                                    </div>
                                    <?php if (!$n['is_read']): ?><span class="notif-unread-dot"></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$isLoggedIn): ?>
            <a href="<?php echo $baseUrl; ?>login.html" class="btn-login">
                <i class="fas fa-right-to-bracket"></i> Log In
            </a>
        <?php else: ?>
            <div class="hdr-avatar" id="hdrAvatar"
                onclick="window.location.href='<?php echo $baseUrl; ?>admin/user_pages/myprofile.php'"
                title="My Profile">
                <img src="<?php echo htmlspecialchars($profileImg); ?>"
                    alt="<?php echo htmlspecialchars($initials); ?>"
                    onerror="this.remove();">
            </div>
            <script>
                (function() {
                    var img = document.querySelector('#hdrAvatar img'),
                        av = document.getElementById('hdrAvatar');
                    if (!img) {
                        av.textContent = '<?php echo $initials; ?>';
                        return;
                    }
                    img.addEventListener('error', function() {
                        av.textContent = '<?php echo $initials; ?>';
                    });
                    if (img.complete && !img.naturalWidth) av.textContent = '<?php echo $initials; ?>';
                })();
            </script>
        <?php endif; ?>
    </div>
</header>

<!-- ── SUBHEADER ── -->
<div class="ss-subheader" id="ssSubheader">
    <div class="ss-fade-l" id="ssFadeL"></div>
    <div class="ss-fade-r" id="ssFadeR"></div>
    <button class="ss-sub-arrow ss-sub-arrow-l ss-hidden" id="ssArrowL" aria-label="Scroll left">&#10094;</button>
    <div class="ss-sub-scroll" id="ssSubScroll">
        <?php foreach ($subCategories as $cat):
            $catFile = basename(parse_url($cat['url'], PHP_URL_PATH));
            parse_str(parse_url($cat['url'], PHP_URL_QUERY) ?? '', $cq);
            $isActive = ($curPage === $catFile && isset($cq['q']) && $curQ === $cq['q']);
        ?>
            <a href="<?php echo htmlspecialchars($cat['url']); ?>"
                class="<?php echo $isActive ? 'sub-active' : ''; ?>">
                <?php if (!empty($cat['icon'])): ?><i class="fas <?php echo $cat['icon']; ?>"></i><?php endif; ?>
                <?php echo htmlspecialchars($cat['label']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <button class="ss-sub-arrow ss-sub-arrow-r" id="ssArrowR" aria-label="Scroll right">&#10095;</button>
</div>

<!-- ══════════════════════════════════════
     MOBILE DRAWER
══════════════════════════════════════ -->
<div class="mob-backdrop" id="mobBackdrop"></div>
<div class="mob-drawer" id="mobDrawer" role="dialog" aria-modal="true" aria-label="Site navigation">

    <!-- Header bar -->
    <div class="mob-drawer-head">
        <a href="<?php echo $baseUrl; ?>index.php" class="mob-dlogo">
            <div class="mob-dlogo-mark">
                <img src="<?php echo $baseUrl; ?>assets/img/logo.png" alt=""
                    onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-graduation-cap\'></i>'">
            </div>
            <div class="mob-dlogo-text">Scholar<em>Swap</em></div>
        </a>
        <button class="mob-dclose" id="mobDClose" aria-label="Close menu"><i class="fas fa-xmark"></i></button>
    </div>

    <!-- Scrollable body -->
    <div class="mob-dbody">

        <!-- User card (logged in) -->
        <?php if ($isLoggedIn): ?>
            <div class="mob-user" onclick="window.location.href='<?php echo $baseUrl; ?>admin/user_pages/myprofile.php'">
                <div class="mob-uavatar" id="mobAvatar">
                    <img src="<?php echo htmlspecialchars($profileImg); ?>" alt="" onerror="this.remove();">
                </div>
                <div>
                    <div class="mob-uname"><?php echo htmlspecialchars(trim(($pu['fn'] ?? '') . ' ' . ($pu['ln'] ?? ''))); ?></div>
                    <div class="mob-urole">View my profile →</div>
                </div>
            </div>
            <script>
                (function() {
                    var img = document.querySelector('#mobAvatar img'),
                        av = document.getElementById('mobAvatar');
                    if (!img) {
                        av.textContent = '<?php echo $initials; ?>';
                        return;
                    }
                    img.addEventListener('error', function() {
                        av.textContent = '<?php echo $initials; ?>';
                    });
                    if (img.complete && !img.naturalWidth) av.textContent = '<?php echo $initials; ?>';
                })();
            </script>
        <?php endif; ?>

        <!-- Search -->
        <div class="mob-dsearch">
            <div class="mob-dsearch-inner">
                <i class="fas fa-search"></i>
                <input type="text" id="mobDSearch" placeholder="Search notes, subjects…" autocomplete="off">
            </div>
        </div>

        <!-- Main nav -->
        <div class="mob-slabel">Main Menu</div>
        <?php
        $cur = basename($_SERVER['PHP_SELF']);
        foreach (
            [
                'index.php'     => ['fas fa-house',     'Home'],
                'notes.php'     => ['fas fa-file-lines', 'Notes'],
                'books.php'     => ['fas fa-book',       'Books'],
                'newspaper.php' => ['fas fa-newspaper',  'Newspaper'],
            ] as $page => [$icon, $label]
        ):
        ?>
            <a href="<?php echo $baseUrl . $page; ?>"
                class="mob-nlink <?php echo $cur === $page ? 'active' : ''; ?>">
                <i class="<?php echo $icon; ?>"></i> <?php echo $label; ?>
            </a>
        <?php endforeach; ?>

        <!-- Upload / Login CTA -->
        <div style="padding:12px 16px 2px;">
            <?php if ($isLoggedIn): ?>
                <button class="mob-upload-cta" id="mobUploadCta">
                    <i class="fas fa-cloud-arrow-up"></i> Upload Content
                </button>
            <?php else: ?>
                <a href="<?php echo $baseUrl; ?>login.html" class="mob-upload-cta">
                    <i class="fas fa-right-to-bracket"></i> Log In to Upload
                </a>
            <?php endif; ?>
        </div>

    </div><!-- /mob-dbody -->

    <!-- Footer -->
    <div class="mob-dfooter">
        <a href="#" class="mob-flink"><i class="fas fa-circle-question"></i> Help Center</a>
        <a href="#" class="mob-flink"><i class="fas fa-users"></i> Community</a>
        <a href="#" class="mob-flink"><i class="fas fa-envelope"></i> support@scholarswap.com</a>
        <?php if ($isLoggedIn): ?>
            <a href="<?php echo $baseUrl; ?>logout.php" class="mob-flink danger">
                <i class="fas fa-right-from-bracket"></i> Log Out
            </a>
        <?php endif; ?>
    </div>

</div><!-- /mob-drawer -->


<?php if ($isLoggedIn): ?>
    <!-- UPLOAD MODAL -->
    <div class="upload-modal" id="uploadModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="upload-modal-inner">
            <button class="modal-close" id="closeUploadModal" aria-label="Close"><i class="fas fa-times"></i></button>
            <div class="modal-title" id="modalTitle">Upload Content</div>
            <div class="modal-sub">Choose what you'd like to share</div>
            <div class="upload-options">
                <a href="<?php echo $baseUrl; ?>admin/user_pages/notes_upload" class="upload-opt">
                    <div class="upload-opt-icon uoi-b"><i class="fas fa-file-lines"></i></div>
                    <div>
                        <div class="upload-opt-label">Notes</div>
                        <div class="upload-opt-desc">Study notes, summaries, handouts</div>
                    </div>
                    <i class="fas fa-chevron-right upload-opt-arrow"></i>
                </a>
                <a href="<?php echo $baseUrl; ?>admin/user_pages/book_upload.php" class="upload-opt">
                    <div class="upload-opt-icon uoi-g"><i class="fas fa-book"></i></div>
                    <div>
                        <div class="upload-opt-label">Books</div>
                        <div class="upload-opt-desc">Textbooks, reference materials</div>
                    </div>
                    <i class="fas fa-chevron-right upload-opt-arrow"></i>
                </a>
                <a href="<?php echo $baseUrl; ?>request.php" class="upload-opt">
                    <div class="upload-opt-icon uoi-g"><i class="fas fa-inbox"></i></div>
                    <div>
                        <div class="upload-opt-label">Request Material</div>
                        <div class="upload-opt-desc">Textbooks, reference materials</div>
                    </div>
                    <i class="fas fa-chevron-right upload-opt-arrow"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION DETAIL MODAL -->
    <div id="notifModal" role="dialog" aria-modal="true" aria-labelledby="nmTitle">
        <div class="nm-backdrop" id="nmBackdrop"></div>
        <div class="nm-box" id="nmBox">
            <div class="nm-bar" id="nmBar"></div>
            <button class="nm-close" id="nmClose" aria-label="Close"><i class="fas fa-xmark"></i></button>
            <div class="nm-hero">
                <div class="nm-icon-wrap" id="nmIconWrap"><i class="fas" id="nmIcon"></i></div>
                <span class="nm-type-badge" id="nmTypeBadge"></span>
            </div>
            <h2 class="nm-title" id="nmTitle"></h2>
            <div class="nm-message" id="nmMessage"></div>
            <div class="nm-meta" id="nmMeta"></div>
            <div class="nm-actions" id="nmActions"></div>
        </div>
    </div>

    <!-- Mobile search bar -->
    <div id="mobileSearchBar" style="display:none;position:sticky;top:108px;z-index:480;background:#fff;border-bottom:1px solid rgba(122,12,12,.1);padding:10px 16px;">
        <div class="hdr-search" style="max-width:100%;"><i class="fas fa-search"></i>
            <input type="text" placeholder="Search notes, subjects…" id="mobileSearchInput" autocomplete="off">
        </div>
    </div>
<?php endif; ?>

<!-- ══════════════════════════════════════
     ALL SCRIPTS
══════════════════════════════════════ -->
<script>
    (function() {
        function esc(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function ucFirst(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
        }

        /* ── DRAWER ── */
        var hamburger = document.getElementById('hamburgerBtn');
        var backdrop = document.getElementById('mobBackdrop');
        var drawer = document.getElementById('mobDrawer');
        var dClose = document.getElementById('mobDClose');

        function openDrawer() {
            drawer.classList.add('open');
            backdrop.classList.add('open');
            hamburger.classList.add('open');
            hamburger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeDrawer() {
            drawer.classList.remove('open');
            backdrop.classList.remove('open');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
        if (hamburger) hamburger.addEventListener('click', openDrawer);
        if (dClose) dClose.addEventListener('click', closeDrawer);
        if (backdrop) backdrop.addEventListener('click', closeDrawer);

        /* Drawer search */
        var mobDSearch = document.getElementById('mobDSearch');
        if (mobDSearch) {
            mobDSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && mobDSearch.value.trim())
                    window.location.href = '<?php echo $baseUrl; ?>search.php?q=' + encodeURIComponent(mobDSearch.value.trim());
            });
        }

        /* Drawer upload */
        var mobUploadCta = document.getElementById('mobUploadCta');
        if (mobUploadCta) {
            mobUploadCta.addEventListener('click', function() {
                closeDrawer();
                setTimeout(openModal, 240);
            });
        }

        /* Mobile header upload icon */
        var mobUploadIconBtn = document.getElementById('mobUploadIconBtn');
        if (mobUploadIconBtn) {
            mobUploadIconBtn.addEventListener('click', openModal);
        }

        /* ── UPLOAD MODAL ── */
        var modal = document.getElementById('uploadModal');
        var openBtn = document.getElementById('openUploadModal');
        var closeBtn = document.getElementById('closeUploadModal');

        function openModal() {
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal() {
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        if (openBtn) openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (modal) modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });

        /* ── ESCAPE key ── */
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            closeDrawer();
            closeModal();
            closeNmModal();
        });

        /* ── DESKTOP SEARCH SYNC ── */
        var desktopInput = document.getElementById('hdrSearchInput');

        function syncSearch(src, dst) {
            if (!src) return;
            src.addEventListener('input', function() {
                if (dst) dst.value = src.value;
            });
            src.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && src.value.trim())
                    window.location.href = '<?php echo $baseUrl; ?>search.php?q=' + encodeURIComponent(src.value.trim());
            });
        }
        syncSearch(desktopInput, null);

        /* ── NOTIFICATION DROPDOWN ── */
        var notifBtn = document.getElementById('notifBtn');
        var notifDd = document.getElementById('notifDropdown');
        var markAllBtn = document.getElementById('markAllRead');
        var notifBadge = document.getElementById('notifBadge');
        if (notifBtn) {
            notifBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (notifDd) notifDd.classList.toggle('open');
            });
        }
        document.addEventListener('click', function(e) {
            if (notifDd && !notifDd.contains(e.target) && e.target !== notifBtn) notifDd.classList.remove('open');
        });

        /* ── NOTIF MESSAGE RENDERER ── */
        function renderNotifMessage(raw) {
            if (!raw) return {
                html: '',
                links: []
            };
            var LINK = /^(?:🔗\s*)?(.+?):\s*(https?:\/\/\S+)\s*$/;
            var BARE = /(https?:\/\/[^\s<>"']+)/g;
            var tl = [],
                lk = [];
            raw.split('\n').forEach(function(line) {
                var m = line.trim().match(LINK);
                if (m && m[2].startsWith('http')) {
                    lk.push({
                        label: m[1].trim(),
                        url: m[2].trim()
                    });
                } else {
                    var s = esc(line);
                    s = s.replace(BARE, function(u) {
                        return '<a href="' + u + '" target="_blank" rel="noopener noreferrer" class="nm-inline-link">' + u + '</a>';
                    });
                    tl.push(s);
                }
            });
            while (tl.length && tl[tl.length - 1].trim() === '') tl.pop();
            return {
                html: tl.join('\n'),
                links: lk
            };
        }

        /* ── NOTIF DETAIL MODAL ── */
        var nmModal = document.getElementById('notifModal');
        var nmBackdrop = document.getElementById('nmBackdrop');
        var nmClose = document.getElementById('nmClose');
        var nmBar = document.getElementById('nmBar');
        var nmIconWrap = document.getElementById('nmIconWrap');
        var nmIcon = document.getElementById('nmIcon');
        var nmBadge = document.getElementById('nmTypeBadge');
        var nmTitle = document.getElementById('nmTitle');
        var nmMessage = document.getElementById('nmMessage');
        var nmMeta = document.getElementById('nmMeta');
        var nmActions = document.getElementById('nmActions');

        function openNmModal(data) {
            if (!nmModal) return;
            nmBar.style.background = 'linear-gradient(90deg,' + data.color + '99,' + data.color + ')';
            nmIconWrap.style.background = data.bg;
            nmIconWrap.style.color = data.color;
            nmIcon.className = 'fas ' + data.icon;
            nmBadge.textContent = data.label;
            nmBadge.style.background = data.bg;
            nmBadge.style.color = data.color;
            nmTitle.textContent = data.title;
            var p = renderNotifMessage(data.message || '');
            if (p.html || p.links.length) {
                var h = p.html;
                if (p.links.length) {
                    h += '\n<span class="nm-link-row">';
                    p.links.forEach(function(lb) {
                        h += '<a href="' + esc(lb.url) + '" target="_blank" rel="noopener noreferrer" class="nm-res-link"><i class="fas fa-arrow-up-right-from-square"></i> ' + esc(lb.label) + '</a> ';
                    });
                    h += '</span>';
                }
                nmMessage.innerHTML = h;
                nmMessage.style.display = '';
            } else {
                nmMessage.innerHTML = '';
                nmMessage.style.display = 'none';
            }
            var rows = [];
            if (data.from_name) rows.push(['From', data.from_name]);
            if (data.resource_type) rows.push(['Resource', ucFirst(data.resource_type) + (data.resource_title ? ' — ' + data.resource_title : '')]);
            rows.push(['Status', data.is_read ? '✓ Read' : '● Unread']);
            rows.push(['Received', data.time]);
            nmMeta.innerHTML = rows.map(function(r) {
                return '<div class="nm-meta-row"><span class="nm-meta-label">' + esc(r[0]) + '</span><span class="nm-meta-val">' + esc(r[1]) + '</span></div>';
            }).join('');
            nmActions.innerHTML = '';
            var ca = document.createElement('button');
            ca.className = 'nm-btn nm-btn-secondary';
            ca.innerHTML = '<i class="fas fa-xmark"></i> Close';
            ca.onclick = closeNmModal;
            nmActions.appendChild(ca);
            document.body.style.overflow = 'hidden';
            nmModal.classList.add('open');
        }

        function closeNmModal() {
            if (nmModal) {
                nmModal.classList.remove('open');
                document.body.style.overflow = '';
            }
        }
        if (nmClose) nmClose.addEventListener('click', closeNmModal);
        if (nmBackdrop) nmBackdrop.addEventListener('click', closeNmModal);

        /* Wire notif items */
        document.querySelectorAll('.notif-item[data-modal]').forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (notifDd) notifDd.classList.remove('open');
                var data;
                try {
                    data = JSON.parse(item.getAttribute('data-modal'));
                } catch (err) {
                    return;
                }
                if (!data.is_read) {
                    var fd = new FormData();
                    fd.append('action', 'mark_read');
                    fd.append('notif_id', data.id);
                    fetch('<?php echo $baseUrl; ?>admin/user_pages/auth/mark_notification.php', {
                        method: 'POST',
                        body: fd
                    }).catch(function() {});
                    item.classList.remove('notif-unread');
                    var dot = item.querySelector('.notif-unread-dot');
                    if (dot) dot.remove();
                    data.is_read = 1;
                    item.setAttribute('data-modal', JSON.stringify(data));
                    if (notifBadge) {
                        var cur = parseInt(notifBadge.textContent) || 0;
                        if (cur <= 1) notifBadge.remove();
                        else notifBadge.textContent = cur - 1;
                    }
                }
                openNmModal(data);
            });
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') item.click();
            });
        });

        /* Mark all read */
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function() {
                var fd = new FormData();
                fd.append('action', 'mark_all_read');
                fetch('<?php echo $baseUrl; ?>admin/user_pages/auth/mark_notification.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(function() {
                        if (notifBadge) notifBadge.remove();
                        document.querySelectorAll('.notif-item').forEach(function(el) {
                            el.classList.remove('notif-unread');
                            var d = el.querySelector('.notif-unread-dot');
                            if (d) d.remove();
                            try {
                                var x = JSON.parse(el.getAttribute('data-modal'));
                                x.is_read = 1;
                                el.setAttribute('data-modal', JSON.stringify(x));
                            } catch (e) {}
                        });
                        var np = document.querySelector('.notif-new-pill');
                        if (np) np.remove();
                        markAllBtn.remove();
                    }).catch(function() {});
            });
        }

        /* ── SUBHEADER ARROWS ── */
        (function() {
            var sc = document.getElementById('ssSubScroll');
            var aL = document.getElementById('ssArrowL'),
                aR = document.getElementById('ssArrowR');
            var fL = document.getElementById('ssFadeL'),
                fR = document.getElementById('ssFadeR');
            if (!sc) return;

            function upd() {
                var s = sc.scrollLeft <= 2,
                    e = sc.scrollLeft >= sc.scrollWidth - sc.clientWidth - 2;
                if (aL) aL.classList.toggle('ss-hidden', s);
                if (aR) aR.classList.toggle('ss-hidden', e);
                if (fL) fL.classList.toggle('ss-show', !s);
                if (fR) fR.classList.toggle('ss-show', !e);
            }
            if (aL) aL.addEventListener('click', function() {
                sc.scrollLeft -= 220;
            });
            if (aR) aR.addEventListener('click', function() {
                sc.scrollLeft += 220;
            });
            sc.addEventListener('scroll', upd);
            var act = sc.querySelector('a.sub-active');
            if (act) setTimeout(function() {
                act.scrollIntoView({
                    inline: 'center',
                    block: 'nearest',
                    behavior: 'smooth'
                });
            }, 120);
            upd();
            setTimeout(upd, 150);
        })();

    })();
</script>