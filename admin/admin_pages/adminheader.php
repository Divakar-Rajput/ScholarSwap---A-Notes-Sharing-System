<?php

/**
 * adminheader.php
 * Drop-in admin topbar — self-contained, no parent variables needed.
 * Fetches its own admin profile data using $_SESSION['admin_id'].
 */

// ── Fetch THIS admin's profile (self-contained) ───────────────
$_ahAdminId = (int)$_SESSION['admin_id'];

$_ahStmt = $conn->prepare("
    SELECT first_name, last_name, role, profile_image
    FROM admin_user WHERE admin_id = ? LIMIT 1
");
$_ahStmt->execute([$_ahAdminId]);
$_ahAdmin = $_ahStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$_ahBase       = 'http://localhost/ScholarSwap/admin/';
$_ahProfileImg = !empty($_ahAdmin['profile_image'])
    ? $_ahBase . ltrim($_ahAdmin['profile_image'], '/')
    : '';
$_ahInitials   = strtoupper(
    substr($_ahAdmin['first_name'] ?? 'A', 0, 1) .
        substr($_ahAdmin['last_name']  ?? '',  0, 1)
);
$_ahName = trim(($_ahAdmin['first_name'] ?? '') . ' ' . ($_ahAdmin['last_name'] ?? '')) ?: 'Admin';

// ── Fetch THIS admin's notifications only ─────────────────────
$adminNotifItems  = [];
$adminUnreadCount = 0;

try {
    $anq = $conn->prepare("
        SELECT notif_id, type, title, message,
               resource_type, resource_id, resource_title,
               from_name, is_read, created_at
        FROM   notifications
        WHERE  admin_id = ?
        ORDER  BY created_at DESC
        LIMIT  20
    ");
    $anq->execute([$_ahAdminId]);

    $typeMap = [
        'warning'         => ['fa-triangle-exclamation', '#dc2626', 'rgba(220,38,38,.12)'],
        'admin_message'   => ['fa-envelope',             '#2563eb', 'rgba(37,99,235,.12)'],
        'info'            => ['fa-circle-info',          '#2563eb', 'rgba(37,99,235,.12)'],
        'success'         => ['fa-check-circle',         '#059669', 'rgba(5,150,105,.12)'],
        'upload_approved' => ['fa-check-circle',         '#059669', 'rgba(5,150,105,.12)'],
        'upload_rejected' => ['fa-times-circle',         '#d97706', 'rgba(217,119,6,.12)'],
        'new_upload'      => ['fa-cloud-arrow-up',       '#0d9488', 'rgba(13,148,136,.12)'],
        'banned_content'  => ['fa-ban',                  '#dc2626', 'rgba(220,38,38,.12)'],
    ];

    foreach ($anq->fetchAll(PDO::FETCH_ASSOC) as $n) {
        [$icon, $color, $bg] = $typeMap[$n['type']] ?? ['fa-bell', '#6366f1', 'rgba(99,102,241,.12)'];

        $diff = time() - strtotime($n['created_at']);
        if ($diff < 60)        $ago = 'just now';
        elseif ($diff < 3600)  $ago = floor($diff / 60)    . 'm ago';
        elseif ($diff < 86400) $ago = floor($diff / 3600)  . 'h ago';
        else                   $ago = floor($diff / 86400) . 'd ago';

        $adminNotifItems[] = [
            'id'             => (int)$n['notif_id'],
            'type'           => $n['type'],
            'icon'           => $icon,
            'color'          => $color,
            'bg'             => $bg,
            'title'          => $n['title'],
            'message'        => $n['message'],
            'resource_type'  => $n['resource_type']  ?? '',
            'resource_title' => $n['resource_title'] ?? '',
            'from_name'      => $n['from_name']      ?? '',
            'is_read'        => (int)$n['is_read'],
            'ago'            => $ago,
            'time'           => date('d M Y, h:i A', strtotime($n['created_at'])),
        ];

        if (!(int)$n['is_read']) $adminUnreadCount++;
    }
} catch (Throwable $e) {
    error_log('[adminheader.php] Notification fetch error: ' . $e->getMessage());
}
?>

<!-- ══════════════════════════════════════════════════════════════
     STYLES
══════════════════════════════════════════════════════════════ -->
<style>
    .adm-notif-wrap {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: #ffffff;
        border: 1.5px solid rgba(15, 23, 42, .10);
        color: var(--text2);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .88rem;
        transition: all .18s;
        position: relative;
        box-shadow: 0 1px 4px rgba(15, 23, 42, .06);
    }

    .adm-bell-btn:hover {
        background: var(--bg) !important;
    }

    .adm-notif-dd {
        position: absolute;
        top: calc(100% + 10px);
        right: -8px;
        width: 340px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r2);
        box-shadow: var(--sh2);
        z-index: 9000;
        overflow: hidden;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px) scale(.97);
        transform-origin: top right;
        transition: opacity .2s, visibility .2s, transform .2s cubic-bezier(.34, 1.3, .64, 1);
    }

    .adm-notif-dd.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
    }

    .adm-nd-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-bottom: 1px solid var(--border);
        background: var(--bg);
    }

    .adm-nd-title {
        font-size: .82rem;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
    }

    .adm-nd-new-pill {
        background: var(--blue);
        color: #fff;
        font-size: .58rem;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 99px;
        margin-left: 6px;
    }

    .adm-nd-mark-all {
        font-size: .7rem;
        font-weight: 600;
        color: var(--blue);
        background: none;
        border: none;
        cursor: pointer;
        font-family: inherit;
        padding: 0;
        transition: color .15s;
    }

    .adm-nd-mark-all:hover {
        color: var(--blue-d);
        text-decoration: underline;
    }

    .adm-nd-list {
        max-height: 320px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--border2) transparent;
    }

    .adm-nd-list::-webkit-scrollbar {
        width: 4px;
    }

    .adm-nd-list::-webkit-scrollbar-thumb {
        background: var(--border2);
        border-radius: 99px;
    }

    .adm-nd-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 11px 14px;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        transition: background .15s;
        text-decoration: none;
    }

    .adm-nd-item:last-child {
        border-bottom: none;
    }

    .adm-nd-item:hover {
        background: var(--bg);
    }

    .adm-nd-item.adm-nd-unread {
        background: #f0f5ff;
    }

    .adm-nd-item.adm-nd-unread:hover {
        background: #e8f0fe;
    }

    .adm-nd-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .82rem;
        flex-shrink: 0;
    }

    .adm-nd-body {
        flex: 1;
        min-width: 0;
    }

    .adm-nd-item-title {
        font-size: .78rem;
        font-weight: 600;
        color: var(--text);
        line-height: 1.3;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 2px;
    }

    .adm-nd-dot {
        width: 6px;
        height: 6px;
        background: var(--blue);
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: 0 0 4px rgba(37, 99, 235, .4);
    }

    .adm-nd-msg {
        font-size: .7rem;
        color: var(--text3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .adm-nd-time {
        font-size: .66rem;
        color: var(--text3);
        margin-top: 3px;
    }

    .adm-nd-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
        gap: 8px;
        color: var(--text3);
        font-size: .8rem;
    }

    .adm-nd-empty i {
        font-size: 1.6rem;
        opacity: .25;
    }

    .adm-nd-footer {
        padding: 10px 14px;
        border-top: 1px solid var(--border);
        background: var(--bg);
        text-align: center;
    }

    .adm-nd-footer a {
        font-size: .75rem;
        font-weight: 600;
        color: var(--blue);
        text-decoration: none;
        transition: color .15s;
    }

    .adm-nd-footer a:hover {
        color: var(--blue-d);
        text-decoration: underline;
    }

    /* Avatar in topbar */
    .adm-tb-av {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid var(--border2);
        cursor: pointer;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 800;
        color: #fff;
        overflow: hidden;
        transition: border-color .18s;
        flex-shrink: 0;
    }

    .adm-tb-av:hover {
        border-color: #7a0c0c;
    }

    .adm-tb-av img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        display: block;
    }
</style>

<!-- ══════════════════════════════════════════════════════════════
     TOPBAR
══════════════════════════════════════════════════════════════ -->
<header class="tb">
    <button class="menu-btn" id="menuBtn"><i class="fas fa-bars"></i></button>
    <span class="tb-title">
        <?php
        $pgNames = [
            'dashboard.php'        => 'Dashboard',
            'students.php'         => 'Students',
            'pending.php'          => 'Pending',
            'notifications.php'    => 'Notifications',
            'reports_feedback.php' => 'Reports & Feedback',
            'admin_access.php'     => 'Admin Access',
            'warnings.php'         => 'Warnings',
            'material_requests.php' => 'Material Requests',
        ];
        $cur = basename($_SERVER['PHP_SELF']);
        echo $pgNames[$cur] ?? 'Admin Panel';
        ?>
    </span>
    <div class="tb-sp"></div>

    <!-- ── Notification Bell ── -->
    <div class="adm-notif-wrap" id="admNotifWrap">
        <button class="adm-bell-btn" id="admNotifBtn"
            title="Notifications"
            style="position:relative;cursor:pointer;background:none;border:none;
                   padding:6px 8px;border-radius:8px;transition:background .15s">
            <i class="fas fa-bell" style="font-size:1rem;color:var(--text2)"></i>
            <?php if ($adminUnreadCount > 0): ?>
                <span id="admNotifBadge" style="
                    position:absolute; top:2px; right:2px;
                    min-width:16px; height:16px; padding:0 4px;
                    background:var(--red); color:#fff;
                    border-radius:99px; font-size:.58rem; font-weight:700;
                    display:flex; align-items:center; justify-content:center;
                    line-height:1; border:2px solid var(--surface);">
                    <?php echo $adminUnreadCount > 99 ? '99+' : $adminUnreadCount; ?>
                </span>
            <?php endif; ?>
        </button>

        <div class="adm-notif-dd" id="admNotifDd">
            <div class="adm-nd-head">
                <span class="adm-nd-title">
                    <i class="fas fa-bell" style="color:var(--blue);font-size:.85em;margin-right:5px"></i>
                    Notifications
                    <?php if ($adminUnreadCount > 0): ?>
                        <span class="adm-nd-new-pill"><?php echo $adminUnreadCount; ?> new</span>
                    <?php endif; ?>
                </span>
                <?php if ($adminUnreadCount > 0): ?>
                    <button class="adm-nd-mark-all" id="admMarkAll">Mark all read</button>
                <?php endif; ?>
            </div>

            <div class="adm-nd-list" id="admNdList">
                <?php if (empty($adminNotifItems)): ?>
                    <div class="adm-nd-empty">
                        <i class="fas fa-bell-slash"></i>
                        No notifications yet
                    </div>
                <?php else: ?>
                    <?php foreach ($adminNotifItems as $n):
                        $payload = htmlspecialchars(json_encode($n), ENT_QUOTES);
                    ?>
                        <div class="adm-nd-item <?php echo !$n['is_read'] ? 'adm-nd-unread' : ''; ?>"
                            data-id="<?php echo $n['id']; ?>"
                            data-payload='<?php echo $payload; ?>'
                            role="button" tabindex="0"
                            title="<?php echo htmlspecialchars($n['title']); ?>">
                            <div class="adm-nd-icon"
                                style="background:<?php echo $n['bg']; ?>;color:<?php echo $n['color']; ?>">
                                <i class="fas <?php echo $n['icon']; ?>"></i>
                            </div>
                            <div class="adm-nd-body">
                                <div class="adm-nd-item-title">
                                    <?php if (!$n['is_read']): ?>
                                        <span class="adm-nd-dot"></span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($n['title']); ?>
                                </div>
                                <?php if (!empty($n['message'])): ?>
                                    <div class="adm-nd-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                                <?php endif; ?>
                                <div class="adm-nd-time"><?php echo $n['ago']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Admin Avatar ── -->
    <div class="adm-tb-av" onclick="window.location.href='profile.php'" title="<?php echo htmlspecialchars($_ahName); ?>">
        <?php if (!empty($_ahProfileImg)): ?>
            <img src="<?php echo htmlspecialchars($_ahProfileImg); ?>"
                alt="<?php echo htmlspecialchars($_ahInitials); ?>"
                onerror="this.parentElement.removeChild(this);this.parentElement.textContent='<?php echo $_ahInitials; ?>'">
        <?php else: ?>
            <?php echo $_ahInitials; ?>
        <?php endif; ?>
    </div>
</header>

<!-- ══════════════════════════════════════════════════════════════
     NOTIFICATION DETAIL MODAL
══════════════════════════════════════════════════════════════ -->
<div id="admNotifModal" style="
    position:fixed;inset:0;z-index:99999;
    background:rgba(15,23,42,.55);backdrop-filter:blur(8px);
    display:flex;align-items:center;justify-content:center;padding:16px;
    opacity:0;visibility:hidden;transition:opacity .24s,visibility .24s;">
    <div id="admNotifModalInner" style="
        background:var(--surface);border-radius:20px;
        width:min(520px,96vw);max-height:88vh;
        box-shadow:0 24px 60px rgba(0,0,0,.18),0 0 0 1px rgba(0,0,0,.04);
        display:flex;flex-direction:column;overflow:hidden;
        transform:scale(.93) translateY(20px);opacity:0;
        transition:transform .28s cubic-bezier(.34,1.56,.64,1),opacity .22s;">
        <div style="height:4px;flex-shrink:0;background:linear-gradient(90deg,var(--blue),var(--purple),var(--teal))"></div>
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:15px 20px;border-bottom:1px solid var(--border);flex-shrink:0">
            <div style="display:flex;align-items:center;gap:11px">
                <div id="admMIcon" style="width:42px;height:42px;border-radius:11px;
                     display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0">
                    <i class="fas fa-bell" id="admMIconI"></i>
                </div>
                <div>
                    <div id="admMTypeLabel" style="font-size:.68rem;font-weight:800;
                         text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-bottom:2px"></div>
                    <div id="admMTime" style="font-size:.72rem;color:var(--text3)"></div>
                </div>
            </div>
            <button onclick="closeAdmModal()"
                style="width:32px;height:32px;border-radius:8px;border:none;cursor:pointer;
                       background:var(--bg);color:var(--text2);font-size:.88rem;
                       display:flex;align-items:center;justify-content:center;transition:all .15s"
                onmouseover="this.style.background='var(--red-s)';this.style.color='var(--red)'"
                onmouseout="this.style.background='var(--bg)';this.style.color='var(--text2)'">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div id="admMBody" style="padding:20px 22px;overflow-y:auto;flex:1;
             scrollbar-width:thin;scrollbar-color:var(--border2) transparent"></div>
        <div style="padding:13px 20px;border-top:1px solid var(--border);
                    background:var(--bg);display:flex;justify-content:flex-end;flex-shrink:0">
            <button onclick="closeAdmModal()"
                style="padding:8px 18px;border-radius:9px;border:1.5px solid var(--border);
                       background:var(--surface);color:var(--text2);font-size:.82rem;
                       font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s"
                onmouseover="this.style.background='var(--text)';this.style.color='#fff'"
                onmouseout="this.style.background='var(--surface)';this.style.color='var(--text2)'">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════════════════ -->
<script>
    (function() {
        var btn = document.getElementById('admNotifBtn');
        var dd = document.getElementById('admNotifDd');
        var badge = document.getElementById('admNotifBadge');
        var markAllBtn = document.getElementById('admMarkAll');
        var modal = document.getElementById('admNotifModal');
        var inner = document.getElementById('admNotifModalInner');

        var MARK_URL = '/ScholarSwap/admin/admin_pages/auth/mark_notification.php';

        var TYPE_LABELS = {
            'warning': 'Warning',
            'admin_message': 'Admin Message',
            'info': 'Info',
            'success': 'Success',
            'upload_approved': 'Upload Approved',
            'upload_rejected': 'Upload Rejected',
            'new_upload': 'New Upload',
            'banned_content': 'Content Banned',
        };

        /* ── Toggle dropdown ── */
        if (btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                dd.classList.toggle('open');
            });
        }
        document.addEventListener('click', function(e) {
            if (dd && !dd.contains(e.target) && e.target !== btn)
                dd.classList.remove('open');
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dd.classList.remove('open');
                closeAdmModal();
            }
        });

        /* ── Mark single read ── */
        function markRead(id, itemEl) {
            var fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('notif_id', id);
            fetch(MARK_URL, {
                method: 'POST',
                body: fd
            }).catch(function() {});

            if (itemEl) {
                itemEl.classList.remove('adm-nd-unread');
                var dot = itemEl.querySelector('.adm-nd-dot');
                if (dot) dot.remove();
                try {
                    var d = JSON.parse(itemEl.dataset.payload);
                    d.is_read = 1;
                    itemEl.dataset.payload = JSON.stringify(d);
                } catch (e) {}
            }

            if (badge) {
                var cur = parseInt(badge.textContent) || 0;
                if (cur <= 1) {
                    badge.remove();
                    badge = null;
                } else badge.textContent = cur - 1;
            }

            var pill = document.querySelector('.adm-nd-new-pill');
            if (pill) {
                var cnt = parseInt(pill.textContent) || 0;
                if (cnt <= 1) pill.remove();
                else pill.textContent = (cnt - 1) + ' new';
            }
        }

        /* ── Mark all read ── */
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function() {
                var fd = new FormData();
                fd.append('action', 'mark_all_read');
                fetch(MARK_URL, {
                        method: 'POST',
                        body: fd
                    })
                    .then(function() {
                        if (badge) {
                            badge.remove();
                            badge = null;
                        }
                        document.querySelectorAll('.adm-nd-item').forEach(function(el) {
                            el.classList.remove('adm-nd-unread');
                            var dot = el.querySelector('.adm-nd-dot');
                            if (dot) dot.remove();
                            try {
                                var d = JSON.parse(el.dataset.payload);
                                d.is_read = 1;
                                el.dataset.payload = JSON.stringify(d);
                            } catch (e) {}
                        });
                        var pill = document.querySelector('.adm-nd-new-pill');
                        if (pill) pill.remove();
                        markAllBtn.remove();
                    }).catch(function() {});
            });
        }

        /* ── Click item → open modal ── */
        document.querySelectorAll('.adm-nd-item').forEach(function(item) {
            function handleOpen() {
                dd.classList.remove('open');
                var n;
                try {
                    n = JSON.parse(item.dataset.payload);
                } catch (e) {
                    return;
                }
                if (!n.is_read) markRead(n.id, item);
                openAdmModal(n);
            }
            item.addEventListener('click', handleOpen);
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleOpen();
                }
            });
        });

        /* ── Open modal ── */
        window.openAdmModal = function(n) {
            document.getElementById('admMIcon').style.background = n.bg;
            document.getElementById('admMIcon').style.color = n.color;
            document.getElementById('admMIconI').className = 'fas ' + n.icon;

            var tl = document.getElementById('admMTypeLabel');
            tl.textContent = TYPE_LABELS[n.type] || 'Notification';
            tl.style.cssText = 'font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px;color:' + n.color;

            document.getElementById('admMTime').textContent = n.time + ' · ' + n.ago;

            /* Parse 🔗 links from message — same logic as user header */
            var LINK_LINE = /^(?:🔗\s*)?(.+?):\s*(https?:\/\/\S+)\s*$/;
            var textLines = [],
                links = [];
            (n.message || '').split('\n').forEach(function(line) {
                var m = line.trim().match(LINK_LINE);
                if (m && m[2].startsWith('http')) {
                    links.push({
                        label: m[1].trim(),
                        url: m[2].trim()
                    });
                } else {
                    textLines.push(esc(line));
                }
            });
            while (textLines.length && !textLines[textLines.length - 1].trim()) textLines.pop();

            var linkHtml = '';
            if (links.length) {
                linkHtml = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px">';
                links.forEach(function(lb) {
                    linkHtml += '<a href="' + esc(lb.url) + '" target="_blank" rel="noopener noreferrer" style="' +
                        'display:inline-flex;align-items:center;gap:7px;' +
                        'background:linear-gradient(135deg,#7a0c0c,#9b1010);color:#fff;' +
                        'text-decoration:none;padding:8px 16px;border-radius:9px;' +
                        'font-size:.8rem;font-weight:700;transition:all .18s;' +
                        'box-shadow:0 3px 10px rgba(122,12,12,.25)">' +
                        '<i class="fas fa-arrow-up-right-from-square" style="font-size:.7rem"></i> ' +
                        esc(lb.label) + '</a>';
                });
                linkHtml += '</div>';
            }

            var resourceChip = '';
            if (n.resource_type) {
                var ri = {
                    note: 'fa-file-lines',
                    book: 'fa-book',
                    newspaper: 'fa-newspaper'
                } [n.resource_type] || 'fa-file';
                resourceChip = '<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:.7rem;font-weight:600;background:var(--purple-s);color:var(--purple);border:1px solid #c4b5fd;margin-right:6px">' +
                    '<i class="fas ' + ri + '" style="font-size:.62rem"></i>' +
                    cap(n.resource_type) + (n.resource_title ? ' — ' + esc(n.resource_title) : '') + '</span>';
            }
            var senderChip = n.from_name ?
                '<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:.7rem;font-weight:600;background:var(--blue-s);color:var(--blue);border:1px solid #93c5fd">' +
                '<i class="fas fa-shield-halved" style="font-size:.62rem"></i>From: ' + esc(n.from_name) + '</span>' : '';

            document.getElementById('admMBody').innerHTML =
                '<h3 style="font-size:1.05rem;font-weight:800;color:var(--text);margin:0 0 10px;line-height:1.3">' + esc(n.title) + '</h3>' +
                '<p style="font-size:.86rem;color:var(--text2);line-height:1.7;margin:0;white-space:pre-wrap">' + textLines.join('\n') + '</p>' +
                linkHtml +
                ((resourceChip || senderChip) ?
                    '<div style="display:flex;flex-wrap:wrap;gap:7px;margin-top:14px">' + resourceChip + senderChip + '</div>' : '');

            modal.style.opacity = '0';
            modal.style.visibility = 'visible';
            inner.style.transform = 'scale(.93) translateY(20px)';
            inner.style.opacity = '0';
            requestAnimationFrame(function() {
                modal.style.transition = 'opacity .24s,visibility .24s';
                modal.style.opacity = '1';
                inner.style.transition = 'transform .28s cubic-bezier(.34,1.56,.64,1),opacity .22s';
                inner.style.transform = 'scale(1) translateY(0)';
                inner.style.opacity = '1';
            });
            document.body.style.overflow = 'hidden';
        };

        /* ── Close modal ── */
        window.closeAdmModal = function() {
            modal.style.opacity = '0';
            modal.style.visibility = 'hidden';
            inner.style.transform = 'scale(.93) translateY(20px)';
            inner.style.opacity = '0';
            document.body.style.overflow = '';
        };
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeAdmModal();
        });

        /* ── Mobile sidebar toggle ── */
        var menuBtn = document.getElementById('menuBtn');
        var sidebar = document.getElementById('sb');
        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }

        /* ── Utils ── */
        function esc(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function cap(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
        }
    })();
</script>