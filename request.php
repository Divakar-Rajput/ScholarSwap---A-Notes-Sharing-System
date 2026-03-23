<?php
include_once('admin/config/connection.php');
include_once('admin/encryption.php');
include_once("admin/auth_check.php");

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$user_id    = $_SESSION['user_id'] ?? null;

if ($isLoggedIn) {
    $uid = (int)$user_id;

    /* resource_link is stored directly in material_requests table */
    $rq = $conn->prepare("
        SELECT ref_code, tracking_number, material_type,
               title, priority, status, created_at, resource_link
        FROM material_requests
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $rq->execute([':uid' => $uid]);
    $myRequests = $rq->fetchAll(PDO::FETCH_ASSOC);

    foreach ($myRequests as &$r) {
        $r['viewer_url'] = null;
        if ($r['status'] === 'Fulfilled' && !empty($r['resource_link'])) {
            /* Strip domain prefix so it works on any domain:
               http://localhost/ScholarSwap/notes_reader?... → notes_reader?... */
            $r['viewer_url'] = preg_replace('#^https?://[^/]+/[^/]+/#i', '', trim($r['resource_link']));
        }
    }
    unset($r);
}

/* ════════════════════════════════════════════════════
   PLATFORM STATS
════════════════════════════════════════════════════ */
$q = $conn->query("SELECT COUNT(*) FROM material_requests WHERE status = 'Fulfilled'");
$totalFulfilled = (int)$q->fetchColumn();

$q = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, fulfilled_at)) FROM material_requests WHERE status = 'Fulfilled' AND fulfilled_at IS NOT NULL");
$avgHours = round((float)($q->fetchColumn() ?: 0));
$avgHoursDisplay = $avgHours > 0 ? $avgHours . ' hrs' : '2 Days';

$q = $conn->query("SELECT COUNT(DISTINCT user_id) FROM users WHERE role IN ('student','tutor')");
$activeStudents = (int)$q->fetchColumn();

$q = $conn->query("SELECT COUNT(*) FROM notes WHERE approval_status = 'approved'");
$totalNotes = (int)$q->fetchColumn();

$q = $conn->query("SELECT COUNT(*) FROM books WHERE approval_status = 'approved'");
$totalBooks = (int)$q->fetchColumn();

$q = $conn->query("SELECT COUNT(*) FROM material_requests WHERE status IN ('Fulfilled','Cannot Fulfil')");
$resolved = (int)$q->fetchColumn();
$successRate = $resolved > 0 ? round($totalFulfilled / $resolved * 100, 1) : 0;
$successRateDisplay = $resolved > 0 ? $successRate . '%' : '—';

$q = $conn->query("SELECT COUNT(*) FROM material_requests");
$totalRequests = (int)$q->fetchColumn();

function fmtNum(int $n): string
{
    if ($n <= 0) return '—';
    if ($n >= 1000) return number_format($n / 1000, 1) . 'k+';
    return $n . '+';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Request Study Material — ScholarSwap</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#7A0C0C">
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/media.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .req-hero { position:relative;background:var(--primary-dark);padding:64px 0 52px;overflow:hidden; }
        .req-hero-bg { position:absolute;inset:0;z-index:0;background:url('assets/img/login-bg.jpg') center/cover no-repeat;opacity:.10; }
        .req-hero::before { content:'';position:absolute;inset:0;z-index:1;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:44px 44px;pointer-events:none; }
        .req-hero::after { content:'';position:absolute;bottom:0;left:0;right:0;z-index:1;height:56px;background:linear-gradient(transparent,var(--primary-dark));pointer-events:none; }
        .req-hero-orb { position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none;z-index:1; }
        .req-hero-orb.a { width:420px;height:420px;background:rgba(242,180,0,.22);top:-120px;right:-70px; }
        .req-hero-orb.b { width:280px;height:280px;background:rgba(122,12,12,.60);bottom:-80px;left:-60px; }
        .req-hero-orb.c { width:160px;height:160px;background:rgba(255,255,255,.04);top:30%;left:12%; }
        .req-hero-inner { position:relative;z-index:2;max-width:740px;margin:0 auto;padding:0 24px;text-align:center; }
        .req-hero-eyebrow { display:inline-flex;align-items:center;gap:7px;background:rgba(242,180,0,.18);border:1px solid rgba(242,180,0,.40);border-radius:99px;padding:5px 16px;font-size:.7rem;font-weight:700;color:#fde68a;letter-spacing:.1em;text-transform:uppercase;margin-bottom:16px;backdrop-filter:blur(6px); }
        .req-hero h1 { font-family:'Playfair Display',Georgia,serif;font-size:clamp(1.95rem,4.5vw,3rem);font-weight:900;color:#fff;line-height:1.15;margin-bottom:14px;text-shadow:0 2px 20px rgba(0,0,0,.3); }
        .req-hero h1 em { font-style:italic;color:var(--accent); }
        .req-hero-sub { font-size:.93rem;color:rgba(255,255,255,.72);max-width:460px;margin:0 auto 26px;line-height:1.75;text-shadow:0 1px 8px rgba(0,0,0,.2); }
        .req-hero-badges { display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:7px; }
        .req-badge { display:flex;align-items:center;gap:6px;background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:6px 13px;font-size:.72rem;font-weight:700;color:rgba(255,255,255,.92);backdrop-filter:blur(8px); }
        .req-badge i { color:var(--accent);font-size:.64rem; }

        .req-breadcrumb { background:var(--surface);border-bottom:1px solid var(--border);padding:10px 0; }
        .req-bc-inner { max-width:1160px;margin:0 auto;padding:0 20px;display:flex;align-items:center;gap:7px;font-size:.78rem;color:var(--text2); }
        .req-bc-inner a { color:var(--primary);font-weight:500;text-decoration:none; }
        .req-bc-inner a:hover { opacity:.75; }
        .req-bc-inner .sep { color:rgb(0 0 0/45%); }
        .req-bc-inner .cur { color:var(--text);font-weight:600; }

        .req-page-body { background:var(--page-bg);min-height:55vh; }
        .req-body { max-width:1160px;margin:0 auto;padding:34px 20px 60px;display:grid;grid-template-columns:1fr 296px;gap:22px;align-items:start; }
        @media(max-width:900px){.req-body{grid-template-columns:1fr;}.req-sidebar{order:-1;}}

        .req-form-card { background:var(--surface);border:1px solid var(--border);border-radius:20px;overflow:hidden;box-shadow:var(--shadow-md); }
        .req-type-selector { padding:20px 22px 0;background:var(--page-bg);border-bottom:1px solid var(--border); }
        .req-type-label { font-size:.65rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase;color:var(--text2);margin-bottom:11px;display:flex;align-items:center;gap:6px; }
        .req-type-label i { color:var(--primary); }
        .req-type-tabs { display:flex;overflow-x:auto;scrollbar-width:none; }
        .req-type-tabs::-webkit-scrollbar{display:none;}
        .req-type-btn { flex:1;min-width:76px;display:flex;flex-direction:column;align-items:center;gap:4px;padding:11px 7px 9px;background:transparent;border:none;border-bottom:2.5px solid transparent;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:.66rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.06em;transition:all .18s;white-space:nowrap; }
        .req-type-btn .tb-ico { font-size:1.25rem;line-height:1;transition:transform .2s; }
        .req-type-btn:hover { background:var(--primary-xlight);color:var(--primary); }
        .req-type-btn.active { background:var(--surface);color:var(--primary);border-bottom-color:var(--primary);font-weight:800; }
        .req-type-btn.active .tb-ico { transform:scale(1.14); }

        .req-form-body { padding:22px 22px 4px; }
        .req-section { margin-bottom:22px; }
        .req-section-title { font-size:.62rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--primary);display:flex;align-items:center;gap:7px;margin-bottom:13px;padding-bottom:8px;border-bottom:1px solid var(--border); }
        .req-section-title i { font-size:.66rem; }
        .frow { display:grid;gap:11px;margin-bottom:11px; }
        .frow-2 { grid-template-columns:1fr 1fr; }
        .frow-3 { grid-template-columns:1fr 1fr 1fr; }
        @media(max-width:540px){.frow-2,.frow-3{grid-template-columns:1fr;}}
        @media(min-width:541px) and (max-width:700px){.frow-3{grid-template-columns:1fr 1fr;}}

        .req-field { display:flex;flex-direction:column;gap:5px; }
        .req-field label { font-size:.69rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text2);display:flex;align-items:center;gap:4px; }
        .req-field label .star { color:var(--primary);font-size:.88rem;line-height:1; }
        .req-field input,.req-field select,.req-field textarea { background:var(--page-bg);border:1.5px solid var(--border);border-radius:9px;padding:10px 12px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.875rem;color:var(--text);outline:none;width:100%;transition:border-color .18s,background .18s,box-shadow .18s;-webkit-appearance:none;appearance:none; }
        .req-field input::placeholder,.req-field textarea::placeholder { color:var(--text2);opacity:.55; }
        .req-field input:focus,.req-field select:focus,.req-field textarea:focus { border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 3px rgba(122,12,12,.09); }
        .req-field select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237A0C0C' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px;cursor:pointer;background-color:var(--page-bg); }
        .req-field textarea { resize:vertical;min-height:82px;line-height:1.65; }
        .req-field.err input,.req-field.err select,.req-field.err textarea { border-color:var(--red);background:#fff5f5; }
        .req-field .field-err { font-size:.68rem;color:var(--red);display:none;align-items:center;gap:4px; }
        .req-field.err .field-err { display:flex; }
        .char-row { display:flex;justify-content:flex-end;margin-top:-5px; }
        .char-ct { font-size:.66rem;color:var(--text2);font-variant-numeric:tabular-nums; }
        .char-ct.warn { color:var(--gold); }
        .char-ct.over { color:var(--red); }

        .priority-row { display:flex;gap:7px;flex-wrap:wrap; }
        .p-opt { position:relative; }
        .p-opt input[type="radio"] { position:absolute;opacity:0;pointer-events:none; }
        .p-opt label { display:flex;align-items:center;gap:6px;padding:7px 13px;border:1.5px solid var(--border);border-radius:99px;background:var(--page-bg);cursor:pointer;font-size:.79rem;font-weight:600;color:var(--text2);transition:all .15s;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;box-shadow:none; }
        .p-dot { width:7px;height:7px;border-radius:50%;flex-shrink:0; }
        .p-opt.low .p-dot { background:var(--green); }
        .p-opt.medium .p-dot { background:var(--gold); }
        .p-opt.high .p-dot { background:var(--red); }
        .p-opt.low input:checked+label { border-color:var(--green);background:#dcfce7;color:var(--green); }
        .p-opt.medium input:checked+label { border-color:var(--gold);background:var(--amber-xlight);color:var(--gold); }
        .p-opt.high input:checked+label { border-color:var(--red);background:#fee2e2;color:var(--red); }
        .p-opt label:hover { border-color:var(--primary);background:var(--primary-xlight);color:var(--primary); }

        .req-upload { border:2px dashed rgba(122,12,12,.2);border-radius:10px;padding:18px 14px;text-align:center;background:var(--page-bg);cursor:pointer;transition:all .2s;position:relative; }
        .req-upload:hover,.req-upload.drag-over { border-color:var(--primary);background:var(--primary-xlight); }
        .req-upload input[type="file"] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
        .req-upload-ico { font-size:1.5rem;color:rgba(122,12,12,.2);margin-bottom:5px; }
        .req-upload-txt { font-size:.81rem;color:var(--text2); }
        .req-upload-txt strong { color:var(--primary); }
        .req-upload-sub { font-size:.67rem;color:var(--text2);margin-top:3px; }
        #req-fname { font-size:.73rem;color:var(--primary);margin-top:5px;display:none;font-weight:600; }

        .req-tip { display:flex;gap:9px;background:var(--amber-xlight);border:1px solid rgba(180,83,9,.2);border-radius:9px;padding:10px 12px;margin-bottom:11px; }
        .req-tip i { color:var(--gold);flex-shrink:0;margin-top:2px;font-size:.8rem; }
        .req-tip p { font-size:.77rem;color:#78350f;line-height:1.6;margin:0; }

        .req-actions { padding:15px 22px 18px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;background:var(--page-bg); }
        .req-actions-note { font-size:.72rem;color:var(--text2);display:flex;align-items:center;gap:5px; }
        .btn-req-submit { display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:#fff;border:none;border-radius:10px;padding:11px 24px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.875rem;font-weight:700;cursor:pointer;transition:all .18s;box-shadow:none; }
        .btn-req-submit:hover { background:var(--primary-dark);transform:translateY(-1px); }
        .btn-req-submit:disabled { opacity:.5;pointer-events:none; }
        .btn-req-submit .spin { display:none;animation:reqSpin .7s linear infinite; }
        .btn-req-submit.loading .spin { display:inline; }
        .btn-req-submit.loading .send-ico { display:none; }
        @keyframes reqSpin { to{transform:rotate(360deg);} }

        .dyn-field { overflow:hidden;max-height:0;opacity:0;transition:max-height .3s cubic-bezier(.4,0,.2,1),opacity .25s ease,margin .27s ease;margin-bottom:0; }
        .dyn-field.visible { max-height:320px;opacity:1;margin-bottom:11px; }

        .req-success { display:none;flex-direction:column;align-items:center;text-align:center;padding:44px 22px 38px;gap:12px; }
        .req-success.show { display:flex;animation:reqFadeUp .4s ease both; }
        @keyframes reqFadeUp { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }
        .req-success-ring { width:68px;height:68px;border-radius:50%;border:2px solid var(--primary);background:var(--primary-xlight);display:flex;align-items:center;justify-content:center;font-size:1.75rem;color:var(--primary);animation:reqPopIn .45s cubic-bezier(.175,.885,.32,1.275) both; }
        @keyframes reqPopIn { from{transform:scale(.4);opacity:0;}to{transform:scale(1);opacity:1;} }
        .req-success h2 { font-family:'Playfair Display',serif;font-size:1.55rem;font-weight:900;color:var(--text); }
        .req-success-ref { background:var(--primary-xlight);border:1px solid rgba(122,12,12,.22);border-radius:7px;padding:5px 15px;font-size:.77rem;font-weight:800;color:var(--primary);letter-spacing:.12em; }
        .req-success p { font-size:.85rem;color:var(--text2);max-width:350px;line-height:1.7; }
        .req-success-steps { display:flex;gap:7px;flex-wrap:wrap;justify-content:center; }
        .req-ss { background:var(--page-bg);border:1px solid var(--border);border-radius:9px;padding:10px 13px;text-align:center;min-width:84px; }
        .req-ss .n { font-size:.58rem;font-weight:800;color:var(--primary);letter-spacing:.09em;margin-bottom:3px; }
        .req-ss .t { font-size:.72rem;color:var(--text2);line-height:1.4; }
        .btn-req-reset { background:var(--page-bg);border:1.5px solid var(--border);border-radius:9px;padding:8px 17px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.81rem;font-weight:600;color:var(--text2);cursor:pointer;transition:all .15s;box-shadow:none;display:inline-flex;align-items:center;gap:6px; }
        .btn-req-reset:hover { border-color:var(--primary);color:var(--primary);background:var(--primary-xlight); }

        .req-login-gate { text-align:center;padding:42px 22px;background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-md); }
        .gate-ico { width:58px;height:58px;border-radius:14px;background:var(--primary-xlight);border:1px solid rgba(122,12,12,.14);display:flex;align-items:center;justify-content:center;font-size:1.35rem;color:var(--primary);margin:0 auto 15px; }
        .req-login-gate h3 { font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:800;color:var(--text);margin-bottom:7px; }
        .req-login-gate p { font-size:.83rem;color:var(--text2);line-height:1.65;max-width:310px;margin:0 auto 18px; }
        .gate-btns { display:flex;gap:8px;justify-content:center;flex-wrap:wrap; }
        .gate-btn-primary { display:inline-flex;align-items:center;gap:7px;background:var(--primary);color:#fff;padding:9px 19px;border-radius:9px;text-decoration:none;font-size:.83rem;font-weight:700;transition:background .15s;box-shadow:none; }
        .gate-btn-primary:hover { background:var(--primary-dark); }
        .gate-btn-ghost { display:inline-flex;align-items:center;gap:7px;background:var(--page-bg);color:var(--text2);padding:9px 19px;border:1.5px solid var(--border);border-radius:9px;text-decoration:none;font-size:.83rem;font-weight:600;transition:all .15s;box-shadow:none; }
        .gate-btn-ghost:hover { border-color:var(--primary);color:var(--primary);background:var(--primary-xlight); }

        .req-sidebar { display:flex;flex-direction:column;gap:15px; }
        .req-side-card { background:var(--surface);border:1px solid var(--border);border-radius:15px;padding:17px;box-shadow:var(--shadow-sm); }
        .req-side-title { font-size:.78rem;font-weight:800;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:7px;padding-bottom:9px;border-bottom:1px solid var(--border); }
        .req-side-title i { color:var(--primary);font-size:.74rem; }

        .stat-list { display:flex;flex-direction:column;gap:7px; }
        .stat-row { display:flex;align-items:center;justify-content:space-between; }
        .stat-lbl { font-size:.77rem;color:var(--text2); }
        .stat-val { font-size:.79rem;font-weight:800;color:var(--primary); }
        .stat-div { height:1px;background:var(--border);margin:2px 0; }

        .how-list { display:flex;flex-direction:column;gap:9px; }
        .how-item { display:flex;gap:9px;align-items:flex-start; }
        .how-n { width:19px;height:19px;border-radius:50%;flex-shrink:0;background:var(--primary-xlight);border:1px solid rgba(122,12,12,.18);display:flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:800;color:var(--primary); }
        .how-text { font-size:.77rem;color:var(--text2);line-height:1.55; }
        .how-text strong { color:var(--text);font-weight:600; }

        /* ── Your Requests table ── */
        .my-req-table { width:100%;border-collapse:collapse;font-size:.74rem; }
        .my-req-table th { text-align:left;padding:5px 6px;font-size:.6rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border); }
        .my-req-table td { padding:6px 6px;border-bottom:1px solid var(--border);color:var(--text2);vertical-align:middle; }
        .my-req-table tr:last-child td { border-bottom:none; }
        .my-req-table .ref-code { font-weight:700;color:var(--primary);font-size:.66rem;letter-spacing:.06em; }

        .status-pill { font-size:.59rem;font-weight:700;padding:2px 6px;border-radius:4px;white-space:nowrap;display:inline-block; }
        .sp-pending    { background:var(--amber-xlight);color:var(--gold); }
        .sp-inprogress { background:var(--primary-light);color:var(--primary); }
        .sp-fulfilled  { background:#dcfce7;color:var(--green); }
        .sp-cancelled  { background:rgba(100,100,100,.08);color:#555;border:1px solid rgba(0,0,0,.10); }
        .sp-cannot     { background:rgba(220,38,38,.07);color:var(--red);border:1px solid rgba(220,38,38,.18); }
        .sp-other      { background:var(--page-bg);color:var(--text2); }

        /* ── Direct "View Material" button in table ── */
        .btn-view-resource {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: var(--primary);
            color: #fff;
            border-radius: 7px;
            font-size: .65rem;
            font-weight: 700;
            text-decoration: none;
            transition: background .14s, transform .1s;
            white-space: nowrap;
            box-shadow: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-view-resource:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            color: #fff;
        }
        .btn-view-resource i { font-size: .6rem; }

        .browse-card { background:linear-gradient(135deg,var(--primary-xlight),#fff4e6);border-color:rgba(122,12,12,.15); }
        .browse-card .req-side-title { border-bottom-color:rgba(122,12,12,.1); }
        .browse-card p { font-size:.76rem;color:var(--text2);line-height:1.6;margin-bottom:10px; }
        .browse-links { display:flex;flex-direction:column;gap:6px; }
        .browse-link { display:flex;align-items:center;gap:7px;padding:8px 11px;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none;transition:all .15s;box-shadow:none; }
        .browse-link.ghost { background:var(--surface);color:var(--primary);border:1.5px solid rgba(122,12,12,.2); }
        .browse-link.ghost:hover { background:var(--primary-xlight); }

        .req-tracking-box { background:var(--primary-xlight);border:1.5px solid rgba(122,12,12,.25);border-radius:14px;padding:18px 20px;text-align:center;width:100%;max-width:380px; }
        .rtb-label { font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--primary);margin-bottom:8px;display:flex;align-items:center;justify-content:center;gap:6px; }
        .rtb-code { font-size:1.05rem;font-weight:900;color:var(--primary);letter-spacing:.08em;font-variant-numeric:tabular-nums;margin-bottom:10px;word-break:break-all; }
        .rtb-copy { display:inline-flex;align-items:center;gap:6px;background:var(--primary);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;font-weight:700;cursor:pointer;transition:background .15s;box-shadow:none;margin-bottom:8px; }
        .rtb-copy:hover { background:var(--primary-dark); }
        .rtb-copy.copied { background:var(--green); }
        .rtb-hint { font-size:.71rem;color:var(--text2); }

        .track-input-row { display:flex;gap:7px;margin-bottom:10px; }
        .track-input-row input { flex:1;background:var(--page-bg);border:1.5px solid var(--border);border-radius:8px;padding:8px 11px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;color:var(--text);outline:none;transition:border-color .15s;-webkit-appearance:none;appearance:none; }
        .track-input-row input:focus { border-color:var(--primary);box-shadow:0 0 0 3px rgba(122,12,12,.08); }
        .track-input-row input::placeholder { color:var(--text2);opacity:.6; }
        .btn-track { background:var(--primary);color:#fff;border:none;border-radius:8px;padding:8px 13px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.78rem;font-weight:700;cursor:pointer;transition:background .15s;flex-shrink:0;box-shadow:none;display:flex;align-items:center;gap:5px; }
        .btn-track:hover { background:var(--primary-dark); }
        .btn-track .t-spin { display:none;animation:reqSpin .7s linear infinite; }
        .btn-track.loading .t-ico { display:none; }
        .btn-track.loading .t-spin { display:inline; }

        .track-result { display:none; }
        .track-result.show { display:block;animation:reqFadeUp .3s ease both; }

        .track-card { background:var(--page-bg);border:1px solid var(--border);border-radius:10px;padding:12px 13px; }
        .track-card-head { display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px; }
        .track-card-title { font-size:.82rem;font-weight:700;color:var(--text);line-height:1.35; }
        .track-card-type { font-size:.62rem;font-weight:700;color:var(--primary);background:var(--primary-xlight);border:1px solid rgba(122,12,12,.15);border-radius:4px;padding:2px 7px;white-space:nowrap; }
        .track-meta { display:flex;flex-direction:column;gap:5px; }
        .track-meta-row { display:flex;align-items:center;justify-content:space-between;font-size:.75rem; }
        .track-meta-lbl { color:var(--text2); }
        .track-meta-val { font-weight:600;color:var(--text); }

        /* ── View Material button in tracker result ── */
        .track-view-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
            padding: 10px 14px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 9px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background .15s, transform .1s;
            box-shadow: 0 3px 10px rgba(122,12,12,.25);
            width: 100%;
        }
        .track-view-btn:hover { background:var(--primary-dark);transform:translateY(-1px);color:#fff; }
        .track-view-btn i { font-size:.8rem; }

        .track-status-bar { display:flex;align-items:center;gap:0;margin:12px 0 4px;position:relative; }
        .tsb-step { flex:1;text-align:center;position:relative;font-size:.62rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.06em; }
        .tsb-dot { width:18px;height:18px;border-radius:50%;border:2px solid var(--border);background:var(--surface);display:flex;align-items:center;justify-content:center;margin:0 auto 4px;font-size:.55rem;color:transparent;transition:all .3s;position:relative;z-index:1; }
        .tsb-step.done .tsb-dot { background:var(--green);border-color:var(--green);color:#fff; }
        .tsb-step.active .tsb-dot { background:var(--primary);border-color:var(--primary);color:#fff; }
        .tsb-step.tsb-dead .tsb-dot { background:rgba(100,100,100,.12);border-color:rgba(0,0,0,.12);color:#999; }
        .tsb-step.tsb-dead { color:#aaa; }
        .tsb-step.tsb-dead::before { background:rgba(0,0,0,.08) !important; }
        .tsb-step::before { content:'';position:absolute;top:8px;left:50%;right:-50%;height:2px;background:var(--border);z-index:0; }
        .tsb-step:last-child::before { display:none; }
        .tsb-step.done::before { background:var(--green); }

        .track-err { background:#fff5f5;border:1px solid rgba(220,38,38,.2);border-radius:9px;padding:10px 12px;font-size:.78rem;color:var(--red);display:flex;align-items:center;gap:7px; }

        @media(max-width:580px){
            .req-form-body{padding:16px 14px 4px;}
            .req-actions{padding:12px 14px 15px;}
            .req-type-btn{font-size:.61rem;padding:9px 5px 7px;min-width:64px;}
            .req-type-selector{padding:15px 14px 0;}
            .req-body{padding:18px 12px 44px;}
        }
    
        /* ── Chip selectors ── */
        .chip-group{display:flex;flex-wrap:wrap;gap:7px;}
        .chip-opt{position:relative;}
        .chip-opt input[type="radio"],.chip-opt input[type="checkbox"]{position:absolute;opacity:0;pointer-events:none;}
        .chip-opt label{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1.5px solid var(--border);border-radius:99px;background:var(--page-bg);font-size:.76rem;font-weight:600;color:var(--text2);cursor:pointer;transition:all .15s;font-family:'Plus Jakarta Sans',sans-serif;white-space:nowrap;}
        .chip-opt label:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-xlight);}
        .chip-opt input:checked+label{border-color:var(--primary);background:var(--primary-light);color:var(--primary);font-weight:700;}
        .chip-opt label i{font-size:.6rem;}
        .chip-group-sub{font-size:.6rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--text2);margin:10px 0 5px;width:100%;}
        .class-level-group{margin-bottom:8px;}
        .class-level-group-title{font-size:.6rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--text2);margin-bottom:5px;}
</style>
</head>

<body>
    <div class="page-grid"></div>
    <div class="content">
        <?php include_once "admin/files/header.php"; ?>

        <div class="req-hero reveal">
            <div class="req-hero-bg"></div>
            <div class="req-hero-orb a"></div>
            <div class="req-hero-orb b"></div>
            <div class="req-hero-orb c"></div>
            <div class="req-hero-inner">
                <div class="req-hero-eyebrow"><i class="fas fa-inbox"></i> Study Material Requests</div>
                <h1>Can't find your<br><em>study material?</em></h1>
                <p class="req-hero-sub">Submit a request — our library team and community will source it within 24–48 hours, delivered straight to your registered email.</p>
                <div class="req-hero-badges">
                    <div class="req-badge"><i class="fas fa-circle-check"></i><?php echo $successRateDisplay !== '—' ? $successRateDisplay . ' success rate' : '96%+ success rate'; ?></div>
                    <div class="req-badge"><i class="fas fa-clock"></i>Avg. <?php echo $avgHoursDisplay; ?></div>
                    <div class="req-badge"><i class="fas fa-users"></i><?php echo $activeStudents > 0 ? fmtNum($activeStudents) . ' students' : '8,000+ students'; ?></div>
                    <div class="req-badge"><i class="fas fa-lock"></i> Always free</div>
                </div>
            </div>
        </div>

        <div class="req-breadcrumb">
            <div class="req-bc-inner">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span class="sep">›</span>
                <span class="cur">Request Material</span>
            </div>
        </div>

        <div class="req-page-body">
            <div class="req-body">

                <div>
                    <?php if (!$isLoggedIn): ?>
                        <div class="req-login-gate reveal">
                            <div class="gate-ico"><i class="fas fa-lock"></i></div>
                            <h3>Sign in to submit a request</h3>
                            <p>You need a free ScholarSwap account to request study material. Takes 30 seconds to join.</p>
                            <div class="gate-btns">
                                <a href="login.html" class="gate-btn-primary"><i class="fas fa-right-to-bracket"></i> Log In</a>
                                <a href="signup.html" class="gate-btn-ghost"><i class="fas fa-user-plus"></i> Create Account</a>
                            </div>
                        </div>
                    <?php else: ?>

                        <div class="req-form-card reveal" id="reqFormCard">
                            <div class="req-type-selector">
                                <div class="req-type-label"><i class="fas fa-layer-group"></i> What are you looking for?</div>
                                <div class="req-type-tabs" role="tablist">
                                    <button class="req-type-btn active" data-type="Textbook" type="button"><span class="tb-ico">📚</span>Textbook</button>
                                    <button class="req-type-btn" data-type="Lecture Notes" type="button"><span class="tb-ico">📝</span>Notes</button>
                                    <button class="req-type-btn" data-type="Past Papers" type="button"><span class="tb-ico">📋</span>Past Papers</button>
                                    <button class="req-type-btn" data-type="Other" type="button"><span class="tb-ico">📦</span>Other</button>
                                </div>
                            </div>

                            <form id="reqForm" novalidate enctype="multipart/form-data">
                                <input type="hidden" name="mat_type" id="hiddenMatType" value="Textbook">
                                <div class="req-form-body">
                                    <div class="req-section">
                                        <div class="req-section-title"><i class="fas fa-book-open"></i><span id="sectionHeading">Book Details</span></div>
                                        <div class="frow">
                                            <div class="req-field" id="rf-title">
                                                <label><span id="titleLabel">Book Title</span> <span class="star">✦</span></label>
                                                <input type="text" name="title" id="titleInput" placeholder="e.g. Operating Systems — Tanenbaum, 4th Ed." autocomplete="off">
                                                <span class="field-err"><i class="fas fa-circle-exclamation"></i> This field is required</span>
                                            </div>
                                        </div>
                                        <div class="frow frow-2 dyn-field visible" id="df-author-subject">
                                            <div class="req-field"><label id="authorLabel">Author / Publisher</label><input type="text" name="author" id="authorInput" placeholder="e.g. Andrew Tanenbaum"></div>
                                            <div class="req-field"><label id="subjectLabel">Subject / Course Code</label><input type="text" name="subject" id="subjectInput" placeholder="e.g. CS301 — OS"></div>
                                        </div>
                                        <div class="frow dyn-field" id="df-edition">
                                            <div class="req-field"><label>Edition / Year of Publication</label><input type="text" name="edition" placeholder="e.g. 4th Edition, 2022"></div>
                                        </div>
                                        <div class="frow frow-2 dyn-field" id="df-notes-extra">
                                            <div class="req-field"><label>Lecturer / Professor</label><input type="text" name="lecturer" placeholder="e.g. Prof. R. Kumar"></div>
                                            <div class="req-field"><label>Unit / Chapter Needed</label><input type="text" name="unit" placeholder="e.g. Unit 3, All units"></div>
                                        </div>
                                        <div class="frow dyn-field" id="df-notes-type">
                                            <div class="req-field">
                                                <label>Preferred Note Type</label>
                                                <select name="note_type">
                                                    <option value="" disabled selected>Any type…</option>
                                                    <option>Handwritten notes</option>
                                                    <option>Typed / digital notes</option>
                                                    <option>Slides / PPT</option>
                                                    <option>Any — whatever's available</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="frow frow-2 dyn-field" id="df-papers-extra">
                                            <div class="req-field" id="rf-university">
                                                <label>University / Board <span class="star">✦</span></label>
                                                <input type="text" name="university" placeholder="e.g. VTU, Anna University, CBSE">
                                                <span class="field-err"><i class="fas fa-circle-exclamation"></i> Required for past papers</span>
                                            </div>
                                            <div class="req-field"><label>Exam Year(s)</label><input type="text" name="exam_year" placeholder="e.g. 2019–2023, May 2022"></div>
                                        </div>
                                        <div class="frow">
                                            <div class="req-field">
                                                <label id="descLabel">Additional Context</label>
                                                <textarea name="desc" id="req-desc" maxlength="500" placeholder="Any extra detail that helps us find the right material…"></textarea>
                                                <div class="char-row"><span class="char-ct" id="req-char">0 / 500</span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="req-section">
                                        <div class="req-section-title"><i class="fas fa-gauge-high"></i> How Soon Do You Need It?</div>
                                        <div class="priority-row">
                                            <div class="p-opt low"><input type="radio" name="priority" id="rp-lo" value="Low" checked><label for="rp-lo"><span class="p-dot"></span> No rush</label></div>
                                            <div class="p-opt medium"><input type="radio" name="priority" id="rp-md" value="Medium"><label for="rp-md"><span class="p-dot"></span> Within a week</label></div>
                                            <div class="p-opt high"><input type="radio" name="priority" id="rp-hi" value="High"><label for="rp-hi"><span class="p-dot"></span> Urgent — exam soon!</label></div>
                                        </div>
                                    </div>


                                    <!-- ── BOOK: Publication + Class Level ── -->
                                    <div class="req-section dyn-field" id="df-book-extra">
                                        <div class="req-section-title"><i class="fas fa-building-columns"></i> Publication Details</div>
                                        <div class="frow frow-2">
                                            <div class="req-field">
                                                <label>Publication / Publisher Name</label>
                                                <input type="text" name="publication_name" id="publicationInput" placeholder="e.g. Oxford University Press">
                                            </div>
                                            <div class="req-field">
                                                <label>Published Year</label>
                                                <input type="date" name="published_year" id="publishedYearInput" placeholder="dd-mm-yyyy">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ── BOOK: Class / Level chips ── -->
                                    <div class="req-section dyn-field" id="df-class-level">
                                        <div class="req-section-title"><i class="fas fa-layer-group"></i> Class / Level <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text2);margin-left:3px">— required</span></div>
                                        <div class="req-field" id="rf-class-level">
                                            <label>Select the class or education level <span class="star">✦</span></label>
                                            <span class="field-err"><i class="fas fa-circle-exclamation"></i> Please select a class level</span>
                                            <div style="margin-top:8px">
                                                <div class="class-level-group">
                                                    <div class="class-level-group-title">Primary School</div>
                                                    <div class="chip-group">
                                                        <?php foreach(['Class 1','Class 2','Class 3','Class 4','Class 5'] as $cl): ?>
                                                        <div class="chip-opt"><input type="radio" name="class_level" id="cl-<?php echo str_replace(' ','-',$cl); ?>" value="<?php echo $cl; ?>"><label for="cl-<?php echo str_replace(' ','-',$cl); ?>"><?php echo $cl; ?></label></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="class-level-group">
                                                    <div class="class-level-group-title">Middle School</div>
                                                    <div class="chip-group">
                                                        <?php foreach(['Class 6','Class 7','Class 8'] as $cl): ?>
                                                        <div class="chip-opt"><input type="radio" name="class_level" id="cl-<?php echo str_replace(' ','-',$cl); ?>" value="<?php echo $cl; ?>"><label for="cl-<?php echo str_replace(' ','-',$cl); ?>"><?php echo $cl; ?></label></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="class-level-group">
                                                    <div class="class-level-group-title">Secondary School</div>
                                                    <div class="chip-group">
                                                        <?php foreach(['Class 9','Class 10'] as $cl): ?>
                                                        <div class="chip-opt"><input type="radio" name="class_level" id="cl-<?php echo str_replace(' ','-',$cl); ?>" value="<?php echo $cl; ?>"><label for="cl-<?php echo str_replace(' ','-',$cl); ?>"><?php echo $cl; ?></label></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="class-level-group">
                                                    <div class="class-level-group-title">Higher Secondary</div>
                                                    <div class="chip-group">
                                                        <?php foreach(['Class 11','Class 12'] as $cl): ?>
                                                        <div class="chip-opt"><input type="radio" name="class_level" id="cl-<?php echo str_replace(' ','-',$cl); ?>" value="<?php echo $cl; ?>"><label for="cl-<?php echo str_replace(' ','-',$cl); ?>"><?php echo $cl; ?></label></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="class-level-group">
                                                    <div class="class-level-group-title">University &amp; Professional</div>
                                                    <div class="chip-group">
                                                        <?php foreach(['Undergraduate','Postgraduate','PhD / Research','General / All Levels'] as $cl): ?>
                                                        <div class="chip-opt"><input type="radio" name="class_level" id="cl-<?php echo str_replace([' ','/',' '],' ',strtolower($cl)); ?>" value="<?php echo $cl; ?>"><label for="cl-<?php echo str_replace([' ','/',' '],' ',strtolower($cl)); ?>"><?php echo $cl; ?></label></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ── NOTES: Course / Language / Document Type ── -->
                                    <div class="req-section dyn-field" id="df-notes-course-lang">
                                        <div class="req-section-title"><i class="fas fa-graduation-cap"></i> Course Details</div>
                                        <div class="frow frow-2">
                                            <div class="req-field">
                                                <label>Course / Syllabus <span class="star">✦</span></label>
                                                <input type="text" name="course_syllabus" placeholder="e.g. B.Sc., Class 12, JEE">
                                            </div>
                                            <div class="req-field">
                                                <label>Language</label>
                                                <select name="language">
                                                    <option value="" disabled selected>Select language</option>
                                                    <option>English</option>
                                                    <option>Hindi</option>
                                                    <option>Bengali</option>
                                                    <option>Tamil</option>
                                                    <option>Telugu</option>
                                                    <option>Marathi</option>
                                                    <option>Gujarati</option>
                                                    <option>Kannada</option>
                                                    <option>Malayalam</option>
                                                    <option>Punjabi</option>
                                                    <option>Urdu</option>
                                                    <option>Odia</option>
                                                    <option>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ── NOTES: Document Type chips ── -->
                                    <div class="req-section dyn-field" id="df-notes-doctype">
                                        <div class="req-section-title"><i class="fas fa-tags"></i> Document Type <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text2);margin-left:3px">— select preferred type</span></div>
                                        <div class="chip-group">
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-class" value="Class Notes"><label for="ndt-class"><i class="fas fa-chalkboard"></i> Class Notes</label></div>
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-hand" value="Hand Written"><label for="ndt-hand"><i class="fas fa-pen-nib"></i> Hand Written</label></div>
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-sum" value="Summary"><label for="ndt-sum"><i class="fas fa-list-check"></i> Summary</label></div>
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-assign" value="Assignment"><label for="ndt-assign"><i class="fas fa-file-pen"></i> Assignment</label></div>
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-qb" value="Question Bank"><label for="ndt-qb"><i class="fas fa-circle-question"></i> Question Bank</label></div>
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-solved" value="Solved Paper"><label for="ndt-solved"><i class="fas fa-check-double"></i> Solved Paper</label></div>
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-lab" value="Lab Manual"><label for="ndt-lab"><i class="fas fa-flask"></i> Lab Manual</label></div>
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-guide" value="Study Guide"><label for="ndt-guide"><i class="fas fa-book-open"></i> Study Guide</label></div>
                                            <div class="chip-opt"><input type="radio" name="note_doc_type" id="ndt-other" value="Other"><label for="ndt-other"><i class="fas fa-ellipsis"></i> Other</label></div>
                                        </div>
                                    </div>

                                                                        <div class="req-section dyn-field" id="df-attachment">
                                        <div class="req-section-title"><i class="fas fa-paperclip"></i> Reference Attachment <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text2);margin-left:3px">— optional</span></div>
                                        <div class="req-tip"><i class="fas fa-lightbulb"></i><p>A cover photo, Amazon/Google Books link, or ISBN helps us find the exact edition much faster.</p></div>
                                        <div class="frow" style="margin-bottom:10px">
                                            <div class="req-upload" id="reqUploadZone">
                                                <input type="file" id="req-file" name="attachment" accept="image/*,.pdf">
                                                <div class="req-upload-ico"><i class="fas fa-image"></i></div>
                                                <div class="req-upload-txt"><strong>Click to attach</strong> or drag &amp; drop</div>
                                                <div class="req-upload-sub">JPG · PNG · PDF — max 5 MB</div>
                                                <div id="req-fname"></div>
                                            </div>
                                        </div>
                                        <div class="frow">
                                            <div class="req-field">
                                                <label>Reference URL <span style="font-weight:400;text-transform:none;letter-spacing:0">(Amazon, Google Books…)</span></label>
                                                <input type="url" name="ref_url" placeholder="https://www.amazon.in/…">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="req-actions" id="reqActionsBar">
                                    <span class="req-actions-note"><i class="fas fa-shield-halved" style="color:var(--green)"></i> We'll respond to your registered email within 24–48 hrs.</span>
                                    <button type="button" class="btn-req-submit" id="reqSubmitBtn">
                                        <i class="fas fa-paper-plane send-ico"></i>
                                        <i class="fas fa-circle-notch spin"></i>
                                        Submit Request
                                    </button>
                                </div>
                            </form>

                            <div class="req-success" id="reqSuccess">
                                <div class="req-success-ring"><i class="fas fa-check"></i></div>
                                <h2>Request Submitted!</h2>
                                <div class="req-tracking-box">
                                    <div class="rtb-label"><i class="fas fa-radar"></i> Your Tracking Number</div>
                                    <div class="rtb-code" id="reqTrackingNumber">TRK-00000000-000000</div>
                                    <button class="rtb-copy" id="copyTrackingBtn" onclick="copyTracking()"><i class="fas fa-copy" id="copyIco"></i> Copy</button>
                                    <div class="rtb-hint">Use this number to check your request status anytime below ↓</div>
                                </div>
                                <div class="req-success-ref" id="reqRefCode">SS-XXXXXX</div>
                                <p>We'll update you at your registered email within <strong>24–48 hours</strong>.</p>
                                <div class="req-success-steps">
                                    <div class="req-ss"><div class="n">STEP 01</div><div class="t">Request received &amp; logged</div></div>
                                    <div class="req-ss"><div class="n">STEP 02</div><div class="t">Library team searches</div></div>
                                    <div class="req-ss"><div class="n">STEP 03</div><div class="t">Email sent to you</div></div>
                                </div>
                                <button class="btn-req-reset" onclick="reqReset()"><i class="fas fa-plus"></i> Submit another request</button>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>

                <!-- SIDEBAR -->
                <div class="req-sidebar">

                    <!-- TRACK -->
                    <div class="req-side-card reveal" id="trackWidget">
                        <div class="req-side-title"><i class="fas fa-radar"></i> Track a Request</div>
                        <div class="track-widget">
                            <div class="track-input-row">
                                <input type="text" id="trackInput" placeholder="TRK-20241218-A3F9C1" autocomplete="off" maxlength="40">
                                <button class="btn-track" id="trackBtn" onclick="doTrack()">
                                    <i class="fas fa-magnifying-glass t-ico"></i>
                                    <i class="fas fa-circle-notch t-spin"></i>
                                    Track
                                </button>
                            </div>
                            <div class="track-result" id="trackResult"></div>
                        </div>
                    </div>

                    <!-- STATS -->
                    <div class="req-side-card reveal">
                        <div class="req-side-title"><i class="fas fa-chart-simple"></i> Platform Stats</div>
                        <div class="stat-list">
                            <div class="stat-row"><span class="stat-lbl">Requests fulfilled</span><span class="stat-val"><?php echo $totalFulfilled > 0 ? fmtNum($totalFulfilled) : '—'; ?></span></div>
                            <div class="stat-row"><span class="stat-lbl">Avg. response time</span><span class="stat-val"><?php echo $avgHoursDisplay; ?></span></div>
                            <div class="stat-div"></div>
                            <div class="stat-row"><span class="stat-lbl">Active students</span><span class="stat-val"><?php echo $activeStudents > 0 ? fmtNum($activeStudents) : '—'; ?></span></div>
                            <div class="stat-row"><span class="stat-lbl">Notes in library</span><span class="stat-val"><?php echo $totalNotes > 0 ? fmtNum($totalNotes) : '—'; ?></span></div>
                            <div class="stat-row"><span class="stat-lbl">Books in library</span><span class="stat-val"><?php echo $totalBooks > 0 ? fmtNum($totalBooks) : '—'; ?></span></div>
                            <div class="stat-div"></div>
                            <div class="stat-row"><span class="stat-lbl">Success rate</span><span class="stat-val"><?php echo $successRateDisplay; ?></span></div>
                            <div class="stat-row"><span class="stat-lbl">Total requests</span><span class="stat-val"><?php echo $totalRequests > 0 ? fmtNum($totalRequests) : '—'; ?></span></div>
                        </div>
                    </div>

                    <!-- HOW IT WORKS -->
                    <div class="req-side-card reveal">
                        <div class="req-side-title"><i class="fas fa-bolt"></i> How It Works</div>
                        <div class="how-list">
                            <div class="how-item"><div class="how-n">1</div><div class="how-text"><strong>Pick type &amp; fill details</strong> — under 2 minutes</div></div>
                            <div class="how-item"><div class="how-n">2</div><div class="how-text"><strong>We search</strong> our library &amp; community database</div></div>
                            <div class="how-item"><div class="how-n">3</div><div class="how-text"><strong>Email notification</strong> with download link or pickup info</div></div>
                            <div class="how-item"><div class="how-n">4</div><div class="how-text"><strong>Rate &amp; review</strong> to help the next student</div></div>
                        </div>
                    </div>

                    <!-- YOUR REQUESTS — direct View Material button for fulfilled ones -->
                    <?php if ($isLoggedIn && !empty($myRequests)): ?>
                        <div class="req-side-card reveal">
                            <div class="req-side-title"><i class="fas fa-clock-rotate-left"></i> Your Requests</div>
                            <table class="my-req-table">
                                <thead><tr><th>Ref</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($myRequests as $r):
                                        $sc = match ($r['status']) {
                                            'Pending'       => 'sp-pending',
                                            'In Progress'   => 'sp-inprogress',
                                            'Fulfilled'     => 'sp-fulfilled',
                                            'Cancelled'     => 'sp-cancelled',
                                            'Cannot Fulfil' => 'sp-cannot',
                                            default         => 'sp-other',
                                        };
                                        $icon = match ($r['status']) {
                                            'Cancelled','Cannot Fulfil' => '✕ ',
                                            'Fulfilled'                 => '✓ ',
                                            default                     => '',
                                        };
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="ref-code"><?php echo htmlspecialchars($r['ref_code']); ?></span>
                                                <div style="font-size:.62rem;color:var(--text2);margin-top:1px;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo htmlspecialchars($r['title']); ?>">
                                                    <?php echo htmlspecialchars($r['title']); ?>
                                                </div>
                                            </td>
                                            <td><span class="status-pill <?php echo $sc; ?>"><?php echo $icon . htmlspecialchars($r['status']); ?></span></td>
                                            <td>
                                                <?php if ($r['status'] === 'Fulfilled' && !empty($r['viewer_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($r['viewer_url']); ?>"
                                                       class="btn-view-resource">
                                                        <i class="fas fa-book-open"></i> View Material
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top:10px;text-align:right">
                                <a href="admin/user_pages/myprofile.php#requests" style="font-size:.72rem;font-weight:600;color:var(--primary)">View all requests →</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- BROWSE -->
                    <div class="req-side-card browse-card reveal">
                        <div class="req-side-title"><i class="fas fa-compass"></i> Browse First?</div>
                        <p>Your material might already be in the library — check before submitting.</p>
                        <div class="browse-links">
                            <a href="notes.php" class="browse-link ghost"><i class="fas fa-file-lines"></i> Browse Notes</a>
                            <a href="books.php" class="browse-link ghost"><i class="fas fa-book"></i> Browse Books</a>
                            <a href="newspaper.php" class="browse-link ghost"><i class="fas fa-newspaper"></i> Past Papers</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <?php include_once('admin/files/footer.php'); ?>
    </div>

    <script>
        var TYPE_CFG = {
            'Textbook':     { heading:'Book Details',      titleLabel:'Book Title',          titlePh:'e.g. Organic Chemistry Vol. 2',              authorLabel:'Author Name',         authorPh:'e.g. R.K. Gupta',          subjectLabel:'Subject / Field',       subjectPh:'e.g. Chemistry, Mathematics', descPh:'Topics covered, who it\'s useful for, edition info, etc.', showAuthor:true,  showEdition:true,  showNotesExtra:false, showNotesType:false, showPapersExtra:false, showBookExtra:true,  showClassLevel:true,  showNotesCourseLang:false, showNotesDocType:false, showAttachment:true  },
            'Lecture Notes':{ heading:'Notes Details',     titleLabel:'Note Title',           titlePh:'e.g. Thermodynamics Chapter 3 Summary',     authorLabel:'Lecturer / Professor', authorPh:'e.g. Prof. R. Kumar',      subjectLabel:'Subject / Topic',       subjectPh:'e.g. Physics, Mathematics',   descPh:'What topics does this cover? Who is it useful for? Any extra context…', showAuthor:false, showEdition:false, showNotesExtra:true,  showNotesType:false, showPapersExtra:false, showBookExtra:false, showClassLevel:false, showNotesCourseLang:true,  showNotesDocType:true,  showAttachment:false },
            'Past Papers':  { heading:'Past Paper Details',titleLabel:'Subject / Paper Name', titlePh:'e.g. Data Structures End Semester',          authorLabel:'Author',               authorPh:'',                         subjectLabel:'Course Code',           subjectPh:'e.g. CS301',                  descPh:'e.g. \"Mid-sem only\" or \"all available years please\"',      showAuthor:false, showEdition:false, showNotesExtra:false, showNotesType:false, showPapersExtra:true,  showBookExtra:false, showClassLevel:false, showNotesCourseLang:false, showNotesDocType:false, showAttachment:false },
            'Other':        { heading:'Material Details',  titleLabel:'What do you need?',    titlePh:'Describe the material as clearly as possible',authorLabel:'Source / Author',     authorPh:'Optional',                 subjectLabel:'Subject / Course',      subjectPh:'Optional',                    descPh:'Any detail that helps us find or source it…',             showAuthor:true,  showEdition:false, showNotesExtra:false, showNotesType:false, showPapersExtra:false, showBookExtra:false, showClassLevel:false, showNotesCourseLang:false, showNotesDocType:false, showAttachment:true  },
        }

        var currentType = 'Textbook';

        function applyType(type) {
            currentType = type;
            var c = TYPE_CFG[type] || TYPE_CFG['Other'];
            document.getElementById('hiddenMatType').value        = type;
            document.getElementById('sectionHeading').textContent = c.heading;
            document.getElementById('titleLabel').textContent     = c.titleLabel;
            document.getElementById('titleInput').placeholder     = c.titlePh;
            document.getElementById('authorLabel').textContent    = c.authorLabel;
            document.getElementById('authorInput').placeholder    = c.authorPh;
            document.getElementById('subjectLabel').textContent   = c.subjectLabel;
            document.getElementById('subjectInput').placeholder   = c.subjectPh;
            document.getElementById('req-desc').placeholder       = c.descPh;
            setDyn('df-author-subject',    c.showAuthor);
            setDyn('df-edition',           c.showEdition);
            setDyn('df-notes-extra',       c.showNotesExtra);
            setDyn('df-notes-type',        c.showNotesType);
            setDyn('df-papers-extra',      c.showPapersExtra);
            setDyn('df-book-extra',        c.showBookExtra);
            setDyn('df-class-level',       c.showClassLevel);
            setDyn('df-notes-course-lang', c.showNotesCourseLang);
            setDyn('df-notes-doctype',     c.showNotesDocType);
            setDyn('df-attachment',        c.showAttachment);
            document.querySelectorAll('.req-type-btn').forEach(function(b) {
                b.classList.toggle('active', b.dataset.type === type);
            });
        }

        function setDyn(id, show) {
            var el = document.getElementById(id); if (!el) return;
            if (show) { el.classList.add('visible'); }
            else { el.classList.remove('visible'); el.querySelectorAll('input,select,textarea').forEach(function(i){i.value='';}); }
        }

        document.querySelectorAll('.req-type-btn').forEach(function(b) {
            b.addEventListener('click', function() { applyType(b.dataset.type); });
        });

        var descTa = document.getElementById('req-desc');
        var charEl = document.getElementById('req-char');
        if (descTa) {
            descTa.addEventListener('input', function() {
                var l = descTa.value.length;
                charEl.textContent = l + ' / 500';
                charEl.className = 'char-ct' + (l > 450 ? ' warn' : '') + (l >= 500 ? ' over' : '');
            });
        }

        var uploadZone = document.getElementById('reqUploadZone');
        var fileInpEl  = document.getElementById('req-file');
        var fnameEl    = document.getElementById('req-fname');
        if (fileInpEl) {
            fileInpEl.addEventListener('change', function() {
                var f = fileInpEl.files[0];
                if (f && fnameEl) { fnameEl.style.display='block'; fnameEl.textContent='📎 '+f.name+' ('+(f.size/1024).toFixed(0)+' KB)'; }
            });
        }
        if (uploadZone) {
            uploadZone.addEventListener('dragover', function(e){e.preventDefault();uploadZone.classList.add('drag-over');});
            uploadZone.addEventListener('dragleave', function(){uploadZone.classList.remove('drag-over');});
            uploadZone.addEventListener('drop', function(e){
                e.preventDefault();uploadZone.classList.remove('drag-over');
                if (fileInpEl && e.dataTransfer.files.length){fileInpEl.files=e.dataTransfer.files;fileInpEl.dispatchEvent(new Event('change'));}
            });
        }

        function validateForm() {
            var ok = true;
            var rfT = document.getElementById('rf-title');
            var tIn = document.querySelector('[name="title"]');
            if (!tIn || tIn.value.trim().length < 2) { rfT && rfT.classList.add('err'); ok = false; }
            else { rfT && rfT.classList.remove('err'); }
            if (currentType === 'Past Papers') {
                var uIn = document.querySelector('[name="university"]');
                var rfU = document.getElementById('rf-university');
                if (!uIn || uIn.value.trim().length < 2) { rfU && rfU.classList.add('err'); ok = false; }
                else { rfU && rfU.classList.remove('err'); }
            }
            // Class level required for Textbook
            if (currentType === 'Textbook') {
                var clChecked = document.querySelector('input[name="class_level"]:checked');
                var rfCL = document.getElementById('rf-class-level');
                if (!clChecked) { rfCL && rfCL.classList.add('err'); ok = false; }
                else            { rfCL && rfCL.classList.remove('err'); }
            }
            if (!ok) { var first = document.querySelector('.req-field.err input,.req-field.err select'); if (first) first.scrollIntoView({behavior:'smooth',block:'center'}); }
            return ok;
        }

        document.querySelectorAll('.req-field input,.req-field select,.req-field textarea').forEach(function(el) {
            el.addEventListener('input', function() {
                var f = el.closest('.req-field');
                if (f && f.classList.contains('err') && el.value.trim()) f.classList.remove('err');
            });
        });

        document.getElementById('reqSubmitBtn').addEventListener('click', function() {
            if (!validateForm()) return;
            var btn = document.getElementById('reqSubmitBtn');
            btn.classList.add('loading'); btn.disabled = true;
            fetch('admin/user_pages/auth/submit_material_request.php', {method:'POST',body:new FormData(document.getElementById('reqForm'))})
                .then(function(r){return r.json();})
                .then(function(d) {
                    btn.classList.remove('loading'); btn.disabled = false;
                    if (d.success) {
                        document.getElementById('reqTrackingNumber').textContent = d.tracking_number;
                        document.getElementById('reqRefCode').textContent = d.ref_code;
                        document.querySelector('.req-form-body').style.display = 'none';
                        document.getElementById('reqActionsBar').style.display = 'none';
                        document.querySelector('.req-type-selector').style.display = 'none';
                        document.getElementById('reqSuccess').classList.add('show');
                        document.getElementById('reqSuccess').scrollIntoView({behavior:'smooth',block:'center'});
                        var ti = document.getElementById('trackInput');
                        if (ti) {
                            ti.value = d.tracking_number;
                            setTimeout(function(){
                                var tw = document.getElementById('trackWidget');
                                if (tw) tw.scrollIntoView({behavior:'smooth',block:'center'});
                            }, 2800);
                        }
                    } else {
                        Swal.fire({icon:'error',title:'Could not submit',text:d.message||'Please try again.',confirmButtonColor:'#7A0C0C',background:'#fff',color:'#000'});
                    }
                })
                .catch(function(){
                    btn.classList.remove('loading'); btn.disabled = false;
                    Swal.fire({icon:'error',title:'Connection error',text:'Please check your connection and try again.',confirmButtonColor:'#7A0C0C',background:'#fff',color:'#000'});
                });
        });

        function copyTracking() {
            var code = document.getElementById('reqTrackingNumber').textContent;
            var btn  = document.getElementById('copyTrackingBtn');
            navigator.clipboard.writeText(code).then(function(){
                btn.classList.add('copied'); btn.innerHTML='<i class="fas fa-check"></i> Copied!';
                setTimeout(function(){btn.classList.remove('copied');btn.innerHTML='<i class="fas fa-copy" id="copyIco"></i> Copy';},2200);
            }).catch(function(){
                var ta=document.createElement('textarea');ta.value=code;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);
                btn.innerHTML='<i class="fas fa-check"></i> Copied!';
                setTimeout(function(){btn.innerHTML='<i class="fas fa-copy"></i> Copy';},2200);
            });
        }
        window.copyTracking = copyTracking;

        /* ════════════════════ TRACK STATUS ════════════════════ */
        var STATUS_STEPS = {'Pending':0,'In Progress':1,'Fulfilled':2,'Cannot Fulfil':-1,'Cancelled':-1};
        var STEP_LABELS  = ['Received','In Progress','Fulfilled'];

        function doTrack() {
            var input = document.getElementById('trackInput');
            var code  = (input ? input.value.trim() : '').toUpperCase();
            var btn   = document.getElementById('trackBtn');
            var res   = document.getElementById('trackResult');

            if (!code || code.length < 6) {
                res.innerHTML = '<div class="track-err"><i class="fas fa-circle-exclamation"></i> Enter a valid tracking number.</div>';
                res.classList.add('show');
                return;
            }

            btn.classList.add('loading'); btn.disabled = true;
            res.classList.remove('show');

            fetch('admin/user_pages/auth/track_request.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'tracking_number='+encodeURIComponent(code)
            })
            .then(function(r){return r.json();})
            .then(function(d) {
                btn.classList.remove('loading'); btn.disabled = false;

                if (!d.success) {
                    res.innerHTML = '<div class="track-err"><i class="fas fa-circle-exclamation"></i>'+(d.message||'No request found with that tracking number.')+'</div>';
                    res.classList.add('show');
                    return;
                }

                var r            = d.request;
                var sIdx         = STATUS_STEPS[r.status] !== undefined ? STATUS_STEPS[r.status] : 0;
                var isCancelled  = (r.status==='Cancelled');
                var isCannotFulfil = (r.status==='Cannot Fulfil');
                var isTerminated = isCancelled||isCannotFulfil;
                var isFulfilled  = (r.status==='Fulfilled');

                var stepsHtml = '';
                if (isTerminated) {
                    STEP_LABELS.forEach(function(lbl,i){
                        stepsHtml+='<div class="tsb-step tsb-dead"><div class="tsb-dot"><i class="fas fa-'+(i===0?'xmark':'minus')+'"></i></div>'+lbl+'</div>';
                    });
                } else {
                    STEP_LABELS.forEach(function(lbl,i){
                        var cls=i<sIdx?'done':(i===sIdx?'active':'');
                        stepsHtml+='<div class="tsb-step '+cls+'"><div class="tsb-dot"><i class="fas fa-check"></i></div>'+lbl+'</div>';
                    });
                }

                var priColour = r.priority==='High'?'var(--red)':r.priority==='Medium'?'var(--gold)':'var(--green)';
                var spClass = {'Pending':'sp-pending','In Progress':'sp-inprogress','Fulfilled':'sp-fulfilled','Cancelled':'sp-cancelled','Cannot Fulfil':'sp-cannot'}[r.status]||'sp-other';

                var terminatedBanner = '';
                if (isTerminated) {
                    var bBg=isCancelled?'rgba(100,100,100,.07)':'rgba(220,38,38,.07)';
                    var bBord=isCancelled?'rgba(0,0,0,.10)':'rgba(220,38,38,.2)';
                    var bColor=isCancelled?'#555':'var(--red)';
                    var bIcon=isCancelled?'fa-ban':'fa-circle-xmark';
                    var bTitle=isCancelled?'Request Cancelled':'Cannot Be Fulfilled';
                    var bMsg=isCancelled?'This request was cancelled'+(r.cancelled_by==='admin'?' by an administrator.':' by you.'):'Our team was unable to source this material.';
                    terminatedBanner='<div style="display:flex;align-items:flex-start;gap:8px;padding:9px 11px;margin-bottom:10px;background:'+bBg+';border:1px solid '+bBord+';border-radius:8px;"><i class="fas '+bIcon+'" style="color:'+bColor+';margin-top:1px;flex-shrink:0;font-size:.9rem"></i><div><div style="font-size:.76rem;font-weight:800;color:'+bColor+';margin-bottom:2px">'+bTitle+'</div><div style="font-size:.72rem;color:'+bColor+';opacity:.8">'+bMsg+'</div></div></div>';
                }

                var adminNoteHtml = '';
                if (r.admin_note) {
                    adminNoteHtml='<div style="margin-top:8px;padding:8px 10px;border-radius:7px;background:var(--amber-xlight);border:1px solid rgba(180,83,9,.18);display:flex;gap:7px;align-items:flex-start"><i class="fas fa-comment-dots" style="color:var(--gold);flex-shrink:0;margin-top:1px;font-size:.8rem"></i><div><div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gold);margin-bottom:2px">Admin Note</div><div style="font-size:.74rem;color:#78350f;line-height:1.55">'+esc(r.admin_note)+'</div></div></div>';
                }

                /* Only show View Material button when URL exists — no fallback */
                var viewBtnHtml = '';
                if (isFulfilled && r.viewer_url) {
                    viewBtnHtml = '<a href="' + esc(r.viewer_url) + '" class="track-view-btn">' +
                        '<i class="fas fa-book-open"></i> View Material' +
                        '</a>';
                }

                res.innerHTML=
                    '<div class="track-card">'+
                    terminatedBanner+
                    '<div class="track-card-head"><div class="track-card-title">'+esc(r.title)+'</div><div class="track-card-type">'+esc(r.material_type)+'</div></div>'+
                    '<div class="track-status-bar">'+stepsHtml+'</div>'+
                    '<div class="track-meta" style="margin-top:10px">'+
                    '<div class="track-meta-row"><span class="track-meta-lbl">Status</span><span class="status-pill '+spClass+'">'+esc(r.status)+'</span></div>'+
                    '<div class="track-meta-row"><span class="track-meta-lbl">Priority</span><span class="track-meta-val" style="color:'+priColour+'">'+esc(r.priority)+'</span></div>'+
                    '<div class="track-meta-row"><span class="track-meta-lbl">Submitted</span><span class="track-meta-val">'+esc(r.submitted)+'</span></div>'+
                    '</div>'+
                    adminNoteHtml+
                    viewBtnHtml+
                    '</div>';

                res.classList.add('show');
            })
            .catch(function(){
                btn.classList.remove('loading'); btn.disabled = false;
                res.innerHTML='<div class="track-err"><i class="fas fa-wifi"></i> Connection error. Please try again.</div>';
                res.classList.add('show');
            });
        }
        window.doTrack = doTrack;

        document.getElementById('trackInput') &&
            document.getElementById('trackInput').addEventListener('keydown',function(e){if(e.key==='Enter')doTrack();});

        function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

        function reqReset() {
            document.getElementById('reqForm').reset();
            if (charEl){charEl.textContent='0 / 500';charEl.className='char-ct';}
            if (fnameEl){fnameEl.style.display='none';fnameEl.textContent='';}
            document.querySelector('.req-form-body').style.display='';
            document.getElementById('reqActionsBar').style.display='';
            document.querySelector('.req-type-selector').style.display='';
            document.getElementById('reqSuccess').classList.remove('show');
            document.querySelectorAll('.req-field.err').forEach(function(e){e.classList.remove('err');});
            applyType('Textbook');
            window.scrollTo({top:0,behavior:'smooth'});
        }
        window.reqReset = reqReset;

        var ro = new IntersectionObserver(function(entries){
            entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');ro.unobserve(e.target);}});
        },{threshold:.08});
        document.querySelectorAll('.reveal').forEach(function(el){ro.observe(el);});

        applyType('Textbook');
    </script>
</body>
</html>