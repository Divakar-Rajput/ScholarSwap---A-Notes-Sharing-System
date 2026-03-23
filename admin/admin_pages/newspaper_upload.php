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
$c = [
    'n_pending' => q($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='pending'"),
    'b_pending' => q($conn, "SELECT COUNT(*) FROM books WHERE approval_status='pending'"),
    'p_pending' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'")
];
$c['pending'] = $c['n_pending'] + $c['b_pending'] + $c['p_pending'];
$sa = $conn->prepare("SELECT first_name,last_name,role FROM admin_user WHERE admin_id=? LIMIT 1");
$sa->execute([$_SESSION['admin_id']]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);
$flash =  $_GET['s']   ?? '';
$errMsg = urldecode($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Upload Newspaper | ScholarSwap Admin</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --blue: #2563eb;
            --blue-s: #dbeafe;
            --blue-xs: #eff6ff;
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
            --indigo: #4f46e5;
            --indigo-s: #e0e7ff;
            --sky: #0284c7;
            --sky-s: #e0f2fe;
            --bg: #f4f7fe;
            --surface: #ffffff;
            --border: #e2e8f0;
            --border2: #cbd5e1;
            --text: #0f172a;
            --text2: #475569;
            --text3: #94a3b8;
            --r: 10px;
            --r2: 16px;
            --sh: 0 1px 4px rgba(15, 23, 42, .06), 0 4px 14px rgba(15, 23, 42, .05);
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            color-scheme: light;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px;
        }

        .pg-head {
            margin-bottom: 22px;
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

        .upload-grid {
            display: grid;
            grid-template-columns: 1fr 316px;
            gap: 20px;
            align-items: start;
        }

        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .panel:last-of-type {
            margin-bottom: 0;
        }

        .panel-head {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 11px;
            background: linear-gradient(135deg, #fafbff, #f5f7ff);
        }

        .ph-ico {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            flex-shrink: 0;
        }

        .ph-ico.pur {
            background: var(--purple-s);
            color: var(--purple);
        }

        .ph-ico.sky {
            background: var(--sky-s);
            color: var(--sky);
        }

        .pht h2 {
            font-size: .93rem;
            font-weight: 800;
            color: var(--text);
        }

        .pht p {
            font-size: .72rem;
            color: var(--text3);
            margin-top: 1px;
        }

        .panel-body {
            padding: 20px;
        }

        .fgrid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 18px;
        }

        .fgrp {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .fgrp.full {
            grid-column: 1/-1;
        }

        .flabel {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--text3);
        }

        .req {
            color: var(--red);
            margin-left: 2px;
        }

        input[type=text],
        input[type=date],
        select {
            width: 100%;
            padding: 9px 13px;
            border: 1.5px solid var(--border);
            border-radius: var(--r);
            font-size: .85rem;
            color: var(--text);
            background: var(--surface);
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
            -webkit-appearance: none;
        }

        input:focus,
        select:focus {
            border-color: var(--purple);
            background: var(--purple-s);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, .09);
        }

        input::placeholder {
            color: var(--text3);
        }

        input.err,
        select.err {
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, .07) !important;
        }

        select option {
            background: #fff;
            color: var(--text);
        }

        input[type=date]::-webkit-calendar-picker-indicator {
            opacity: .45;
            cursor: pointer;
        }

        .char-row {
            display: flex;
            justify-content: space-between;
            margin-top: 2px;
        }

        .char-hint {
            font-size: .63rem;
            color: var(--text3);
        }

        .drop-zone {
            width: 100%;
            min-height: 185px;
            border: 2px dashed var(--border2);
            border-radius: var(--r2);
            background: linear-gradient(135deg, var(--blue-xs), #f5f3ff);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .22s;
        }

        .drop-zone:hover,
        .drop-zone.dragover {
            border-color: var(--purple);
            background: var(--purple-s);
        }

        .drop-zone.dragover {
            transform: scale(1.01);
        }

        .drop-zone.has-file {
            border-color: var(--green);
            border-style: solid;
            background: var(--green-s);
        }

        .drop-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 9px;
            padding: 28px 24px;
            text-align: center;
            pointer-events: none;
        }

        .drop-ico {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--purple-s), var(--indigo-s));
            color: var(--purple);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: all .22s;
            box-shadow: 0 4px 12px rgba(124, 58, 237, .15);
        }

        .drop-zone.has-file .drop-ico {
            background: var(--green-s);
            color: var(--green);
            box-shadow: 0 4px 12px rgba(5, 150, 105, .15);
        }

        .drop-zone.dragover .drop-ico {
            background: var(--purple);
            color: #fff;
            transform: scale(1.1);
        }

        .drop-title {
            font-weight: 700;
            font-size: .9rem;
            color: var(--text);
        }

        .drop-sub {
            font-size: .76rem;
            color: var(--text3);
            line-height: 1.5;
        }

        .drop-chips {
            display: flex;
            gap: 6px;
            margin-top: 2px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .dchip {
            padding: 3px 10px;
            border-radius: 99px;
            font-size: .63rem;
            font-weight: 700;
        }

        .dchip.red {
            background: var(--red-s);
            color: var(--red);
        }

        .dchip.grn {
            background: var(--green-s);
            color: var(--green);
        }

        .file-strip {
            margin-top: 12px;
            padding: 11px 14px;
            background: var(--blue-xs);
            border: 1px solid rgba(37, 99, 235, .15);
            border-radius: var(--r);
            display: none;
            align-items: center;
            gap: 11px;
        }

        .file-strip.show {
            display: flex;
        }

        .fs-ico {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: var(--red-s);
            color: var(--red);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            flex-shrink: 0;
        }

        .fs-info {
            flex: 1;
            min-width: 0;
        }

        .fs-name {
            font-size: .82rem;
            font-weight: 600;
            color: var(--text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .fs-meta {
            font-size: .68rem;
            color: var(--text3);
            margin-top: 1px;
        }

        .fs-rm {
            width: 26px;
            height: 26px;
            border-radius: 7px;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--text3);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: .7rem;
            transition: all .13s;
            flex-shrink: 0;
        }

        .fs-rm:hover {
            background: var(--red-s);
            color: var(--red);
            border-color: transparent;
        }

        .prog-wrap {
            margin-top: 10px;
            display: none;
        }

        .prog-track {
            height: 5px;
            background: var(--border);
            border-radius: 99px;
            overflow: hidden;
        }

        .prog-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--purple), var(--indigo));
            border-radius: 99px;
            transition: width .18s linear;
        }

        .prog-row {
            display: flex;
            justify-content: space-between;
            font-size: .67rem;
            color: var(--text3);
            margin-top: 4px;
        }

        .file-err {
            margin-top: 10px;
            padding: 10px 14px;
            background: var(--red-s);
            border: 1px solid rgba(220, 38, 38, .2);
            border-radius: 9px;
            font-size: .8rem;
            color: var(--red);
            display: none;
            align-items: center;
            gap: 8px;
        }

        .file-err.show {
            display: flex;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: var(--r);
            border: none;
            cursor: pointer;
            font-size: .83rem;
            font-weight: 600;
            transition: all .14s;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:active {
            transform: scale(.97);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--purple), var(--indigo));
            color: #fff;
            box-shadow: 0 4px 14px rgba(124, 58, 237, .28);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(124, 58, 237, .38);
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .btn-outline {
            background: var(--surface);
            color: var(--text2);
            border: 1.5px solid var(--border);
        }

        .btn-outline:hover {
            border-color: var(--purple);
            color: var(--purple);
            background: var(--purple-s);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .preview-card {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden;
            position: sticky;
            top: calc(var(--hh, 62px) + 16px);
        }

        .pc-accent {
            height: 5px;
            background: linear-gradient(90deg, var(--purple), var(--indigo), var(--teal));
        }

        .pc-body {
            padding: 18px 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .pc-title {
            font-size: .9rem;
            font-weight: 800;
            color: var(--text);
        }

        .pc-sub {
            font-size: .7rem;
            color: var(--text3);
            margin-top: 1px;
        }

        .pc-divider {
            height: 1px;
            background: var(--border);
        }

        .pc-field {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .pf-l {
            font-size: .58rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: var(--text3);
        }

        .pf-v {
            font-size: .82rem;
            color: var(--text);
            font-weight: 500;
            line-height: 1.4;
            word-break: break-word;
        }

        .pf-v.muted {
            color: var(--text3);
            font-style: italic;
            font-weight: 400;
        }

        .tip-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .tip {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: .75rem;
            color: var(--text2);
            line-height: 1.5;
        }

        .tip-dot {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .6rem;
            margin-top: 1px;
        }

        .tip-dot.p {
            background: var(--purple-s);
            color: var(--purple);
        }

        .tip-dot.g {
            background: var(--green-s);
            color: var(--green);
        }

        .tip-dot.a {
            background: var(--amber-s);
            color: var(--amber);
        }

        .tip-dot.s {
            background: var(--sky-s);
            color: var(--sky);
        }

        @media(max-width:900px) {
            .upload-grid {
                grid-template-columns: 1fr;
            }

            .preview-card {
                position: static;
            }
        }

        @media(max-width:540px) {
            .fgrid {
                grid-template-columns: 1fr;
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
                    <h1><i class="fas fa-newspaper" style="color:var(--purple);margin-right:8px;font-size:1.1rem"></i>Upload Newspaper</h1>
                    <p>Add a new newspaper edition to the ScholarSwap library</p>
                </div>
                <a class="btn btn-outline" href="newspapers.php"><i class="fas fa-arrow-left"></i> Back to Newspapers</a>
            </div>

            <form id="uploadForm" method="POST" action="auth/upload_newspaper.php" enctype="multipart/form-data" novalidate>
                <div class="upload-grid">

                    <!-- LEFT -->
                    <div>
                        <div class="panel">
                            <div class="panel-head">
                                <div class="ph-ico pur"><i class="fas fa-newspaper"></i></div>
                                <div class="pht">
                                    <h2>Newspaper Details</h2>
                                    <p>Basic information about this edition</p>
                                </div>
                            </div>
                            <div class="panel-body">
                                <div class="fgrid">
                                    <div class="fgrp full">
                                        <label class="flabel">Title / Newspaper Name <span class="req">*</span></label>
                                        <input type="text" name="title" id="fTitle" placeholder="e.g. Dainik Jagran – Delhi Edition, The Hindu Morning Digest…" maxlength="200" required>
                                        <div class="char-row"><span class="char-hint">Full name of this edition</span><span class="char-hint"><span id="titleCount">0</span> / 200</span></div>
                                    </div>
                                    <div class="fgrp">
                                        <label class="flabel">Publisher <span class="req">*</span></label>
                                        <input type="text" name="publisher" id="fPublisher" placeholder="e.g. Amarujala, Dainik Jagran, The Hindu…" maxlength="120" required>
                                    </div>
                                    <div class="fgrp">
                                        <label class="flabel">Language <span class="req">*</span></label>
                                        <select name="language" id="fLanguage" required>
                                            <option value="">Select language…</option>
                                            <option>Hindi</option>
                                            <option>English</option>
                                            <option>Urdu</option>
                                            <option>Bengali</option>
                                            <option>Marathi</option>
                                            <option>Tamil</option>
                                            <option>Telugu</option>
                                            <option>Gujarati</option>
                                            <option>Punjabi</option>
                                            <option>Kannada</option>
                                            <option>Malayalam</option>
                                            <option>Odia</option>
                                            <option>Other</option>
                                        </select>
                                    </div>
                                    <div class="fgrp">
                                        <label class="flabel">Publication Date <span class="req">*</span></label>
                                        <input type="date" name="publication_date" id="fDate" max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="fgrp full">
                                        <label class="flabel">Region / Edition <span class="req">*</span></label>
                                        <input type="text" name="region" id="fRegion" placeholder="e.g. Delhi, Mumbai, Aligarh, National…" maxlength="100" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="panel">
                            <div class="panel-head">
                                <div class="ph-ico sky"><i class="fas fa-sliders"></i></div>
                                <div class="pht">
                                    <h2>Publication Settings</h2>
                                    <p>Visibility and approval for this edition</p>
                                </div>
                            </div>
                            <div class="panel-body">
                                <div class="fgrid">
                                    <div class="fgrp">
                                        <label class="flabel">Approval Status</label>
                                        <select name="approval_status">
                                            <option value="approved">✅ Approved — visible immediately</option>
                                            <option value="pending">⏳ Pending — review later</option>
                                        </select>
                                    </div>
                                    <div class="fgrp">
                                        <label class="flabel">Feature on Homepage?</label>
                                        <select name="is_featured">
                                            <option value="0">No</option>
                                            <option value="1">⭐ Yes — show on homepage</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="panel">
                            <div class="panel-head">
                                <div class="ph-ico pur"><i class="fas fa-file-pdf"></i></div>
                                <div class="pht">
                                    <h2>Newspaper PDF File</h2>
                                    <p>Drag &amp; drop or click to select — PDF only, no size limit</p>
                                </div>
                            </div>
                            <div class="panel-body" style="display:flex;flex-direction:column;gap:13px;">
                                <div class="drop-zone" id="dropZone">
                                    <input type="file" name="pdf" id="fileInput" accept="application/pdf" hidden required>
                                    <div class="drop-inner">
                                        <div class="drop-ico"><i class="fas fa-cloud-arrow-up"></i></div>
                                        <div class="drop-title" id="dropTitle">Drag &amp; Drop PDF here</div>
                                        <div class="drop-sub" id="dropSub">or click anywhere in this box to browse</div>
                                        <div class="drop-chips">
                                            <span class="dchip red"><i class="fas fa-file-pdf"></i> PDF only</span>
                                            <span class="dchip grn"><i class="fas fa-infinity"></i> No size limit</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="file-strip" id="fileStrip">
                                    <div class="fs-ico"><i class="fas fa-file-pdf"></i></div>
                                    <div class="fs-info">
                                        <div class="fs-name" id="fsName">—</div>
                                        <div class="fs-meta" id="fsMeta">—</div>
                                    </div>
                                    <div class="fs-rm" id="fsRm" title="Remove"><i class="fas fa-xmark"></i></div>
                                </div>
                                <div class="prog-wrap" id="progWrap">
                                    <div class="prog-track">
                                        <div class="prog-bar" id="progBar"></div>
                                    </div>
                                    <div class="prog-row"><span id="progSt">Preparing…</span><span id="progPct">0%</span></div>
                                </div>
                                <div class="file-err" id="fileErr"><i class="fas fa-circle-exclamation"></i><span id="fileErrMsg">Invalid file.</span></div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fas fa-cloud-arrow-up"></i> Upload Newspaper</button>
                            <a href="newspapers.php" class="btn btn-outline"><i class="fas fa-xmark"></i> Cancel</a>
                        </div>
                    </div><!-- /left -->

                    <!-- RIGHT -->
                    <div>
                        <div class="preview-card">
                            <div class="pc-accent"></div>
                            <div class="pc-body">
                                <div>
                                    <div class="pc-title" id="pvTitle">Edition Preview</div>
                                    <div class="pc-sub" id="pvSub">Fill in the form to see a preview</div>
                                </div>
                                <div class="pc-divider"></div>
                                <div class="pc-field">
                                    <div class="pf-l">Publisher</div>
                                    <div class="pf-v muted" id="pvPublisher">—</div>
                                </div>
                                <div class="pc-field">
                                    <div class="pf-l">Language</div>
                                    <div class="pf-v muted" id="pvLanguage">—</div>
                                </div>
                                <div class="pc-field">
                                    <div class="pf-l">Region / Edition</div>
                                    <div class="pf-v muted" id="pvRegion">—</div>
                                </div>
                                <div class="pc-field">
                                    <div class="pf-l">Publication Date</div>
                                    <div class="pf-v muted" id="pvDate">—</div>
                                </div>
                                <div class="pc-field">
                                    <div class="pf-l">File</div>
                                    <div class="pf-v muted" id="pvFile">No file selected</div>
                                </div>
                                <div class="pc-divider"></div>
                                <div style="font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text3)">Upload Guidelines</div>
                                <div class="tip-list">
                                    <div class="tip">
                                        <div class="tip-dot p"><i class="fas fa-file-pdf"></i></div><span>Upload as <strong>PDF only</strong>. There is <strong>no file size limit</strong>.</span>
                                    </div>
                                    <div class="tip">
                                        <div class="tip-dot g"><i class="fas fa-check"></i></div><span>Make sure the PDF is <strong>complete and legible</strong> before uploading.</span>
                                    </div>
                                    <div class="tip">
                                        <div class="tip-dot a"><i class="fas fa-calendar"></i></div><span>The <strong>publication date</strong> must match the actual print date.</span>
                                    </div>
                                    <div class="tip">
                                        <div class="tip-dot s"><i class="fas fa-globe"></i></div><span>Use clear region names — <strong>Delhi, Mumbai, National</strong> — for better search.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /right -->

                </div>
            </form>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('dropZone'),
            fileInput = document.getElementById('fileInput'),
            fileStrip = document.getElementById('fileStrip'),
            fsName = document.getElementById('fsName'),
            fsMeta = document.getElementById('fsMeta'),
            fsRm = document.getElementById('fsRm'),
            progWrap = document.getElementById('progWrap'),
            progBar = document.getElementById('progBar'),
            progPct = document.getElementById('progPct'),
            progSt = document.getElementById('progSt'),
            fileErr = document.getElementById('fileErr'),
            fileErrMsg = document.getElementById('fileErrMsg'),
            submitBtn = document.getElementById('submitBtn');

        dropZone.addEventListener('click', () => fileInput.click());
        ['dragenter', 'dragover'].forEach(ev => dropZone.addEventListener(ev, e => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        }));
        ['dragleave', 'dragend'].forEach(ev => dropZone.addEventListener(ev, () => dropZone.classList.remove('dragover')));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) setFile(fileInput.files[0]);
        });

        function fmtBytes(b) {
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
            if (b < 1073741824) return (b / 1048576).toFixed(2) + ' MB';
            return (b / 1073741824).toFixed(2) + ' GB';
        }

        function showErr(msg) {
            fileErrMsg.textContent = msg;
            fileErr.classList.add('show');
            dropZone.classList.remove('has-file');
            fileStrip.classList.remove('show');
            document.getElementById('dropTitle').textContent = 'Drag & Drop PDF here';
            document.getElementById('dropSub').textContent = 'or click anywhere in this box to browse';
        }

        function hideErr() {
            fileErr.classList.remove('show');
        }

        function setFile(file) {
            hideErr();
            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                showErr('Only PDF files are allowed.');
                return;
            }
            fsName.textContent = file.name;
            fsMeta.textContent = fmtBytes(file.size) + ' · PDF Document';
            fileStrip.classList.add('show');
            dropZone.classList.add('has-file');
            document.getElementById('dropTitle').textContent = 'File selected ✓';
            document.getElementById('dropSub').textContent = 'Click to replace the file';
            setPv('pvFile', file.name + ' (' + fmtBytes(file.size) + ')');
            runProg();
        }

        function runProg() {
            progWrap.style.display = 'block';
            progBar.style.width = '0%';
            let p = 0;
            progSt.textContent = 'Reading file…';
            const iv = setInterval(() => {
                p = Math.min(p + 14, 100);
                progBar.style.width = p + '%';
                progPct.textContent = p + '%';
                if (p >= 100) {
                    clearInterval(iv);
                    progSt.textContent = 'Ready to upload ✓';
                    setTimeout(() => progWrap.style.display = 'none', 900);
                }
            }, 80);
        }
        fsRm.addEventListener('click', () => {
            fileInput.value = '';
            fileStrip.classList.remove('show');
            dropZone.classList.remove('has-file');
            progWrap.style.display = 'none';
            hideErr();
            document.getElementById('dropTitle').textContent = 'Drag & Drop PDF here';
            document.getElementById('dropSub').textContent = 'or click anywhere in this box to browse';
            const pv = document.getElementById('pvFile');
            pv.textContent = 'No file selected';
            pv.className = 'pf-v muted';
        });

        function setPv(id, val) {
            const el = document.getElementById(id);
            el.textContent = val || '—';
            el.className = 'pf-v' + (val ? '' : ' muted');
        }

        function livePreview() {
            const t = document.getElementById('fTitle').value.trim(),
                pub = document.getElementById('fPublisher').value.trim(),
                lng = document.getElementById('fLanguage').value,
                reg = document.getElementById('fRegion').value.trim(),
                dt = document.getElementById('fDate').value;
            document.getElementById('pvTitle').textContent = t || 'Edition Preview';
            document.getElementById('pvSub').textContent = pub ? 'By ' + pub : 'Fill in the form to see a preview';
            setPv('pvPublisher', pub);
            setPv('pvLanguage', lng);
            setPv('pvRegion', reg);
            if (dt) {
                const d = new Date(dt + 'T00:00:00');
                setPv('pvDate', d.toLocaleDateString('en-IN', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                }));
            } else setPv('pvDate', '');
        }
        ['fTitle', 'fPublisher', 'fRegion'].forEach(id => document.getElementById(id).addEventListener('input', livePreview));
        ['fLanguage', 'fDate'].forEach(id => document.getElementById(id).addEventListener('change', livePreview));
        document.getElementById('fTitle').addEventListener('input', function() {
            document.getElementById('titleCount').textContent = this.value.length;
        });

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            let ok = true;
            ['fTitle', 'fPublisher', 'fRegion'].forEach(id => {
                const el = document.getElementById(id);
                if (!el.value.trim()) {
                    el.classList.add('err');
                    ok = false;
                } else el.classList.remove('err');
            });
            ['fLanguage', 'fDate'].forEach(id => {
                const el = document.getElementById(id);
                if (!el.value) {
                    el.classList.add('err');
                    ok = false;
                } else el.classList.remove('err');
            });
            if (!fileInput.files || !fileInput.files[0]) {
                showErr('Please select a PDF file.');
                ok = false;
            }
            if (!ok) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Fields',
                    text: 'Please fill in all required fields and select a PDF file.',
                    confirmButtonColor: '#7c3aed'
                });
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading…';
        });
        document.querySelectorAll('input,select').forEach(el => {
            ['input', 'change'].forEach(ev => el.addEventListener(ev, () => el.classList.remove('err')));
        });

        const _s = '<?php echo htmlspecialchars($flash, ENT_QUOTES); ?>',
            _msg = '<?php echo htmlspecialchars($errMsg, ENT_QUOTES); ?>';
        if (_s === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Newspaper Uploaded!',
                html: 'The newspaper has been added to the library.<br><small style="color:#64748b">It will appear on the newspapers page shortly.</small>',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        } else if (_s === 'error') {
            Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: _msg || 'Something went wrong. Please try again.',
                confirmButtonColor: '#7c3aed'
            });
        }
        if (_s) history.replaceState(null, '', 'upload_newspaper.php');
    </script>
</body>

</html>