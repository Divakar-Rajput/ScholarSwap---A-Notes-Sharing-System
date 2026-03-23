<?php
session_start();
require_once "config/connection.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$adminId = (int)$_SESSION['admin_id'];
$sa = $conn->prepare("SELECT first_name, last_name, role FROM admin_user WHERE admin_id = ? LIMIT 1");
$sa->execute([$adminId]);
$admin = $sa->fetch(PDO::FETCH_ASSOC);
$adminName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: 'Admin';

// Get request_id from URL if provided
$requestId = isset($_GET['req']) ? (int)$_GET['req'] : 0;
$requestData = null;
$uploadType = isset($_GET['type']) ? $_GET['type'] : 'note'; // 'note' or 'book'

if ($requestId) {
    $rq = $conn->prepare("
        SELECT
            mr.request_id,
            mr.ref_code,
            mr.tracking_number,
            mr.material_type,
            mr.title,
            mr.author,
            mr.subject_code,
            mr.description,
            mr.user_id,
            u.username,
            u.email,
            COALESCE(
                NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
                NULLIF(TRIM(CONCAT(t.first_name,' ',t.last_name)),''),
                u.username
            ) AS display_name
        FROM material_requests mr
        JOIN users u ON u.user_id = mr.user_id
        LEFT JOIN students s ON s.user_id = mr.user_id
        LEFT JOIN tutors t ON t.user_id = mr.user_id
        WHERE mr.request_id = ?
        LIMIT 1
    ");
    $rq->execute([$requestId]);
    $requestData = $rq->fetch(PDO::FETCH_ASSOC);
    
    // Auto-detect type from request if not specified in URL
    if (!isset($_GET['type']) && $requestData) {
        $uploadType = (strpos($requestData['material_type'], 'Textbook') !== false) ? 'book' : 'note';
    }
}

// Set page title and icon based on type
$pageTitle = $uploadType === 'book' ? 'Upload Book' : 'Upload Notes';
$pageIcon = $uploadType === 'book' ? 'fa-book' : 'fa-file-alt';
$pageDesc = $uploadType === 'book' 
    ? 'Share textbooks and reference books with the ScholarSwap community' 
    : 'Share your notes with the ScholarSwap community — reviewed before publishing';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | ScholarSwap Admin</title>
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
            --green: #059669;
            --green-s: #d1fae5;
            --red: #dc2626;
            --red-s: #fee2e2;
            --purple: #7c3aed;
            --purple-s: #ede9fe;
            --maroon: #7a0c0c;
            --maroon-s: #fde8e8;
            --gold: #b45309;
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
            font-size: 14px;
        }

        a {
            text-decoration: none;
            color: inherit
        }

        .pg-head {
            margin-bottom: 22px;
        }

        .pg-head h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pg-head p {
            font-size: .82rem;
            color: var(--text3);
            margin-top: 6px;
        }

        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            margin-bottom: 16px;
            max-width: 900px;
        }

        .ph {
            padding: 13px 18px;
            border-bottom: 1px solid var(--border);
        }

        .pt {
            font-family: 'Space Grotesk', sans-serif;
            font-size: .87rem;
            font-weight: 700;
            color: var(--text);
        }

        .panel-body {
            padding: 20px;
        }

        .ctx-card {
            background: linear-gradient(135deg, var(--maroon-s), #fff4cc);
            border: 1.5px solid rgba(122, 12, 12, .2);
            border-radius: var(--r2);
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        .ctx-card-title {
            font-size: .92rem;
            font-weight: 800;
            color: var(--maroon);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ctx-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
            font-size: .76rem;
            color: var(--text2);
        }

        .ctx-row i {
            font-size: .65rem;
            color: var(--gold);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: .76rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
            display: block;
        }

        .form-label-opt {
            font-weight: 400;
            color: var(--text3);
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: .84rem;
            font-family: inherit;
            color: var(--text);
            background: var(--surface);
            outline: none;
            transition: border-color .18s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
        }

        .form-textarea {
            resize: vertical;
            min-height: 90px;
            line-height: 1.6;
        }

        /* Document Type Cards */
        .doc-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 10px;
        }

        .doc-type-card {
            position: relative;
            padding: 16px 12px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            cursor: pointer;
            transition: all .2s;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .doc-type-card:hover {
            border-color: var(--blue);
            background: var(--blue-s);
            transform: translateY(-2px);
        }

        .doc-type-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .doc-type-card input[type="radio"]:checked ~ .doc-type-icon,
        .doc-type-card input[type="radio"]:checked ~ .doc-type-name {
            color: var(--blue);
        }

        .doc-type-card:has(input:checked) {
            border-color: var(--blue);
            background: var(--blue-s);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .doc-type-icon {
            font-size: 1.8rem;
            color: var(--text3);
            transition: color .2s;
        }

        .doc-type-name {
            font-size: .72rem;
            font-weight: 600;
            color: var(--text2);
            transition: color .2s;
        }

        /* Class/Level Pills */
        .level-section {
            margin-bottom: 18px;
        }

        .level-header {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text3);
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border);
        }

        .class-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .class-pill {
            position: relative;
            cursor: pointer;
        }

        .class-pill input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .class-pill span {
            display: block;
            padding: 7px 16px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            font-size: .76rem;
            font-weight: 600;
            color: var(--text2);
            transition: all .18s;
            white-space: nowrap;
        }

        .class-pill:hover span {
            border-color: var(--blue);
            background: var(--blue-s);
        }

        .class-pill input:checked ~ span {
            border-color: var(--blue);
            background: var(--blue);
            color: #fff;
            box-shadow: 0 2px 8px rgba(37, 99, 235, .3);
        }

        /* Updated File Upload Zone */
        .file-upload-zone-v2 {
            border: 2px dashed var(--border2);
            border-radius: 12px;
            padding: 40px 30px;
            text-align: center;
            background: #fef8f8;
            cursor: pointer;
            transition: all .2s;
            position: relative;
        }

        .file-upload-zone-v2:hover {
            border-color: var(--red);
            background: var(--red-s);
        }

        .file-upload-zone-v2.dragover {
            border-color: var(--red);
            background: var(--red-s);
            transform: scale(1.01);
        }

        .file-upload-zone-v2 input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon-v2 {
            font-size: 3rem;
            color: var(--red);
            margin-bottom: 12px;
        }

        .upload-text-v2 {
            font-size: .92rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }

        .upload-subtext-v2 {
            font-size: .76rem;
            color: var(--text3);
        }

        .file-preview {
            display: none;
            margin-top: 12px;
            padding: 12px;
            background: var(--green-s);
            border: 1px solid var(--green);
            border-radius: 9px;
            align-items: center;
            gap: 10px;
        }

        .file-preview.show {
            display: flex;
        }

        .file-preview-icon {
            font-size: 1.8rem;
        }

        .file-preview-info {
            flex: 1;
            min-width: 0;
        }

        .file-preview-name {
            font-size: .84rem;
            font-weight: 700;
            color: var(--green);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-preview-size {
            font-size: .7rem;
            color: #065f46;
        }

        .file-preview-remove {
            background: var(--red-s);
            color: var(--red);
            border: none;
            border-radius: 7px;
            padding: 6px 11px;
            font-size: .7rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
        }

        .file-preview-remove:hover {
            background: var(--red);
            color: #fff;
        }

        /* Updated Cover Upload Zone */
        .cover-upload-zone-v2 {
            border: 2px dashed var(--border2);
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            background: var(--bg);
            cursor: pointer;
            transition: all .2s;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .cover-upload-zone-v2:hover {
            border-color: var(--purple);
            background: var(--purple-s);
        }

        .cover-upload-zone-v2 input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .cover-upload-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: var(--bg);
            border: 1.5px solid var(--border2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--text3);
        }

        .cover-upload-text {
            font-size: .8rem;
            color: var(--text2);
            line-height: 1.5;
        }

        .cover-upload-text strong {
            font-weight: 600;
            color: var(--text);
        }

        .cover-upload-text span {
            color: var(--text3);
            font-size: .74rem;
        }

        .cover-preview {
            display: none;
            margin-top: 10px;
        }

        .cover-preview.show {
            display: block;
        }

        .cover-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 9px;
            box-shadow: var(--sh);
        }

        /* Help Note */
        .help-note {
            margin-top: 12px;
            padding: 12px 14px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 9px;
            font-size: .76rem;
            color: #92400e;
            display: flex;
            gap: 10px;
            line-height: 1.6;
        }

        .help-note i {
            color: #f59e0b;
            font-size: .9rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .help-note strong {
            font-weight: 700;
            color: #78350f;
        }

        .btn-primary {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #7a0c0c, #991b1b);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .18s;
            box-shadow: 0 4px 14px rgba(122, 12, 12, .25);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(122, 12, 12, .35);
        }

        .btn-primary:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .btn-secondary {
            width: 100%;
            padding: 10px;
            background: var(--surface);
            color: var(--text2);
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: .82rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all .15s;
        }

        .btn-secondary:hover {
            background: var(--bg);
            border-color: var(--border2);
        }

        @media(max-width:768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .doc-type-grid {
                grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            }

            .class-pills {
                gap: 6px;
            }

            .class-pill span {
                padding: 6px 12px;
                font-size: .72rem;
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
                <h1>
                    <i class="fas <?php echo $pageIcon; ?>" style="color:var(--blue)"></i>
                    <?php echo $pageTitle; ?>
                </h1>
                <p><?php echo $pageDesc; ?></p>
            </div>

            <?php if ($requestData): ?>
                <div class="ctx-card">
                    <div class="ctx-card-title">
                        <i class="fas fa-link"></i>
                        Fulfilling Request
                    </div>
                    <div class="ctx-row">
                        <i class="fas fa-book"></i>
                        <strong><?php echo htmlspecialchars($requestData['title']); ?></strong>
                    </div>
                    <div class="ctx-row">
                        <i class="fas fa-user"></i>
                        Requested by: <?php echo htmlspecialchars($requestData['display_name']); ?>
                    </div>
                    <div class="ctx-row">
                        <i class="fas fa-tag"></i>
                        Ref: <?php echo htmlspecialchars($requestData['ref_code']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                <input type="hidden" name="requester_user_id" value="<?php echo $requestData['user_id'] ?? ''; ?>">
                <input type="hidden" name="material_type" value="<?php echo $uploadType; ?>">

                <?php if ($uploadType === 'note'): ?>
                <!-- Note Information Section -->
                <div class="panel">
                    <div class="ph">
                        <div class="pt"><i class="fas fa-file-alt" style="color:var(--blue);margin-right:6px"></i>Note Information</div>
                    </div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Note Title <span style="color:var(--red)">*</span></label>
                                <input type="text" name="title" class="form-input" 
                                    placeholder="e.g. Thermodynamics Chapter 3 Summary" 
                                    value="<?php echo htmlspecialchars($requestData['title'] ?? ''); ?>" 
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Subject / Topic <span style="color:var(--red)">*</span></label>
                                <input type="text" name="subject" class="form-input" 
                                    placeholder="e.g. Physics, Mathematics" 
                                    value="<?php echo htmlspecialchars($requestData['subject_code'] ?? ''); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Course / Syllabus <span style="color:var(--red)">*</span></label>
                                <input type="text" name="course" class="form-input" 
                                    placeholder="e.g. B.Sc. Class 12, JEE" 
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Language</label>
                                <select name="language" class="form-select">
                                    <option value="">Select language</option>
                                    <option value="English" selected>English</option>
                                    <option value="Hindi">Hindi</option>
                                    <option value="Bilingual">Bilingual</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group full">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-textarea" 
                                    placeholder="What topics does this cover? Who is it useful for? Any extra context..."
                                    style="min-height:80px"><?php echo htmlspecialchars($requestData['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Book Information Section -->
                <div class="panel">
                    <div class="ph">
                        <div class="pt"><i class="fas fa-book" style="color:var(--purple);margin-right:6px"></i>Book Information</div>
                    </div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Book Title <span style="color:var(--red)">*</span></label>
                                <input type="text" name="title" class="form-input" 
                                    placeholder="e.g. Physical Chemistry Vol. 2" 
                                    value="<?php echo htmlspecialchars($requestData['title'] ?? ''); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Author Name <span style="color:var(--red)">*</span></label>
                                <input type="text" name="author" class="form-input" 
                                    placeholder="Author name" 
                                    value="<?php echo htmlspecialchars($requestData['author'] ?? ''); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Subject / Field <span style="color:var(--red)">*</span></label>
                                <input type="text" name="subject" class="form-input" 
                                    placeholder="e.g. Chemistry, Mathematics"
                                    value="<?php echo htmlspecialchars($requestData['subject_code'] ?? ''); ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Publication Name</label>
                                <input type="text" name="publication" class="form-input" 
                                    placeholder="e.g. Oxford University Press">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Publication Year</label>
                                <input type="text" name="pub_year" class="form-input" 
                                    placeholder="YYYY"
                                    maxlength="4"
                                    pattern="[0-9]{4}">
                            </div>

                            <div class="form-group full">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-textarea" 
                                    placeholder="Topics covered, who it's useful for, edition info, etc..."
                                    style="min-height:80px"><?php echo htmlspecialchars($requestData['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Document Type Section -->
                <div class="panel">
                    <div class="ph">
                        <div class="pt"><i class="fas fa-layer-group" style="color:var(--teal);margin-right:6px"></i>Document Type</div>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="form-label">Select Type <span style="color:var(--red)">*</span></label>
                            <div class="doc-type-grid">
                                <?php if ($uploadType === 'note'): ?>
                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="class_notes" checked>
                                    <div class="doc-type-icon"><i class="fas fa-file-alt"></i></div>
                                    <div class="doc-type-name">Class Notes</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="handwritten">
                                    <div class="doc-type-icon"><i class="fas fa-pen"></i></div>
                                    <div class="doc-type-name">Hand Written</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="summary">
                                    <div class="doc-type-icon"><i class="fas fa-file-invoice"></i></div>
                                    <div class="doc-type-name">Summary</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="assignment">
                                    <div class="doc-type-icon"><i class="fas fa-tasks"></i></div>
                                    <div class="doc-type-name">Assignment</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="question_bank">
                                    <div class="doc-type-icon"><i class="fas fa-question-circle"></i></div>
                                    <div class="doc-type-name">Question Bank</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="lab_manual">
                                    <div class="doc-type-icon"><i class="fas fa-flask"></i></div>
                                    <div class="doc-type-name">Lab Manual</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="study_guide">
                                    <div class="doc-type-icon"><i class="fas fa-graduation-cap"></i></div>
                                    <div class="doc-type-name">Study Guide</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="other">
                                    <div class="doc-type-icon"><i class="fas fa-ellipsis-h"></i></div>
                                    <div class="doc-type-name">Other</div>
                                </label>
                                <?php else: ?>
                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="textbook" checked>
                                    <div class="doc-type-icon"><i class="fas fa-book"></i></div>
                                    <div class="doc-type-name">Textbook</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="reference">
                                    <div class="doc-type-icon"><i class="fas fa-bookmark"></i></div>
                                    <div class="doc-type-name">Reference</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="guide">
                                    <div class="doc-type-icon"><i class="fas fa-map"></i></div>
                                    <div class="doc-type-name">Guide Book</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="workbook">
                                    <div class="doc-type-icon"><i class="fas fa-pencil-alt"></i></div>
                                    <div class="doc-type-name">Workbook</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="handbook">
                                    <div class="doc-type-icon"><i class="fas fa-book-open"></i></div>
                                    <div class="doc-type-name">Handbook</div>
                                </label>

                                <label class="doc-type-card">
                                    <input type="radio" name="doc_subtype" value="other">
                                    <div class="doc-type-icon"><i class="fas fa-ellipsis-h"></i></div>
                                    <div class="doc-type-name">Other</div>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Class/Level Selection -->
                        <div class="form-group">
                            <label class="form-label">Class / Level</label>
                            <div style="margin-bottom:10px;font-size:.75rem;color:var(--text3)">Select the class or education level this <?php echo $uploadType === 'book' ? 'book' : 'material'; ?> is for</div>
                            
                            <!-- Primary School -->
                            <div class="level-section">
                                <div class="level-header">Primary School</div>
                                <div class="class-pills">
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 1">
                                        <span>Class 1</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 2">
                                        <span>Class 2</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 3">
                                        <span>Class 3</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 4">
                                        <span>Class 4</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 5">
                                        <span>Class 5</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Middle School -->
                            <div class="level-section">
                                <div class="level-header">Middle School</div>
                                <div class="class-pills">
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 6">
                                        <span>Class 6</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 7">
                                        <span>Class 7</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 8">
                                        <span>Class 8</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Secondary School -->
                            <div class="level-section">
                                <div class="level-header">Secondary School</div>
                                <div class="class-pills">
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 9">
                                        <span>Class 9</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 10">
                                        <span>Class 10</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Higher Secondary -->
                            <div class="level-section">
                                <div class="level-header">Higher Secondary</div>
                                <div class="class-pills">
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 11">
                                        <span>Class 11</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Class 12">
                                        <span>Class 12</span>
                                    </label>
                                </div>
                            </div>

                            <!-- University & Professional -->
                            <div class="level-section">
                                <div class="level-header">University & Professional</div>
                                <div class="class-pills">
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Undergraduate">
                                        <span><i class="fas fa-user-graduate" style="font-size:.7rem;margin-right:3px"></i> Undergraduate</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="Postgraduate">
                                        <span><i class="fas fa-user-graduate" style="font-size:.7rem;margin-right:3px"></i> Postgraduate</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="PhD / Research">
                                        <span><i class="fas fa-microscope" style="font-size:.7rem;margin-right:3px"></i> PhD / Research</span>
                                    </label>
                                    <label class="class-pill">
                                        <input type="radio" name="class_level" value="General / All Levels">
                                        <span><i class="fas fa-layer-group" style="font-size:.7rem;margin-right:3px"></i> General / All Levels</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PDF File Upload -->
                <div class="panel">
                    <div class="ph">
                        <div class="pt"><i class="fas fa-file-pdf" style="color:var(--red);margin-right:6px"></i>PDF File</div>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="form-label">Upload PDF <span style="color:var(--red)">*</span></label>
                            <div class="file-upload-zone-v2" id="fileDropZone">
                                <input type="file" name="pdf_file" id="pdfInput" accept=".pdf" required>
                                <div class="upload-icon-v2"><i class="fas fa-cloud-upload-alt"></i></div>
                                <div class="upload-text-v2">Drag & drop PDF here</div>
                                <div class="upload-subtext-v2">or click to browse · any size</div>
                            </div>
                            <div class="file-preview" id="filePreview">
                                <div class="file-preview-icon">📄</div>
                                <div class="file-preview-info">
                                    <div class="file-preview-name" id="fileName">—</div>
                                    <div class="file-preview-size" id="fileSize">—</div>
                                </div>
                                <button type="button" class="file-preview-remove" onclick="removeFile()">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                            <div class="help-note">
                                <i class="fas fa-lightbulb"></i>
                                <div>
                                    <strong>Tips for a great upload</strong><br>
                                    Make sure your <?php echo $uploadType === 'book' ? 'book is clear and well-scanned' : 'notes are clearly written and well-organized'; ?>. Include the subject, chapter, and course name in the title for better discoverability. Avoid uploading copyrighted textbook content.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($uploadType === 'book'): ?>
                <!-- Cover Image Upload (Books only) -->
                <div class="panel">
                    <div class="ph">
                        <div class="pt"><i class="fas fa-image" style="color:var(--purple);margin-right:6px"></i>Cover Image</div>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="form-label">Book Cover (Optional)</label>
                            <div class="cover-upload-zone-v2" id="coverDropZone">
                                <input type="file" name="cover_image" id="coverInput" accept="image/*" onchange="previewCover(this)">
                                <div class="cover-upload-placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                                <div class="cover-upload-text">
                                    <strong>Click to upload cover image</strong><br>
                                    <span>JPG, PNG or WebP</span>
                                </div>
                            </div>
                            <div class="cover-preview" id="coverPreview">
                                <img id="coverImg" src="" alt="Cover preview">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Submit Buttons -->
                <div class="panel">
                    <div class="panel-body">
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <i class="fas fa-cloud-upload-alt"></i>
                            Upload <?php echo $uploadType === 'book' ? 'Book' : 'Notes'; ?>
                        </button>
                        <div style="height:10px"></div>
                        <button type="button" class="btn-secondary" onclick="history.back()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                </div>

            </form>

        </div>
    </div>

    <script>
        const fileDropZone = document.getElementById('fileDropZone');
        const pdfInput = document.getElementById('pdfInput');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileDropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileDropZone.addEventListener(eventName, () => {
                fileDropZone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileDropZone.addEventListener(eventName, () => {
                fileDropZone.classList.remove('dragover');
            }, false);
        });

        fileDropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type === 'application/pdf') {
                pdfInput.files = files;
                handleFileSelect();
            }
        });

        pdfInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = pdfInput.files[0];
            if (file) {
                if (file.type !== 'application/pdf') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid File',
                        text: 'Please select a PDF file'
                    });
                    pdfInput.value = '';
                    return;
                }
                if (file.size > 50 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        text: 'Maximum file size is 50MB'
                    });
                    pdfInput.value = '';
                    return;
                }
                fileName.textContent = file.name;
                fileSize.textContent = formatBytes(file.size);
                filePreview.classList.add('show');
            }
        }

        function removeFile() {
            pdfInput.value = '';
            filePreview.classList.remove('show');
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function previewCover(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('coverImg').src = e.target.result;
                    document.getElementById('coverPreview').classList.add('show');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = document.getElementById('submitBtn');
            const uploadType = '<?php echo $uploadType; ?>';

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

            try {
                const response = await fetch('auth/handle_upload.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Upload Successful!',
                        text: data.message || 'Material uploaded successfully',
                        timer: 2500,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'material_requests.php?s=success';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: data.message || 'Something went wrong'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Could not reach the server'
                });
            }

            submitBtn.disabled = false;
            const btnText = uploadType === 'book' ? 'Upload Book' : 'Upload Notes';
            submitBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> ' + btnText;
        });
    </script>

</body>

</html>