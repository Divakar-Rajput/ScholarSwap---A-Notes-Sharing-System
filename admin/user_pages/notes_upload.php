<?php
require_once('../auth_check.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta -->
    <meta name="description" content="ScholarSwap is a collaborative academic platform where students exchange notes, textbooks, and study resources. Save money, study smarter, and succeed together.">
    <meta name="keywords" content="student notes sharing, swap textbooks, academic resources, study materials exchange, college notes, university books, free study resources, peer learning, student community, notes marketplace, textbook swap, academic collaboration, study guides, exam preparation, student platform, educational resource sharing, buy sell books, second hand textbooks, course notes, lecture notes">
    <meta name="author" content="ScholarSwap">
    <meta name="robots" content="index, follow">
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="theme-color" content="#4F46E5">

    <!-- Canonical -->
    <link rel="canonical" href="https://www.scholarswap.com/">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.scholarswap.com/">
    <meta property="og:title" content="ScholarSwap — Share, Swap &amp; Succeed Together">
    <meta property="og:description" content="Join thousands of students sharing notes, swapping textbooks, and exchanging academic resources. Study smarter, spend less.">
    <meta property="og:image" content="https://www.scholarswap.com/assets/img/og-banner.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="ScholarSwap">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://www.scholarswap.com/">
    <meta name="twitter:title" content="ScholarSwap — Share, Swap &amp; Succeed Together">
    <meta name="twitter:description" content="A student-powered platform to share notes, swap textbooks, and ace your academics together.">
    <meta name="twitter:image" content="https://www.scholarswap.com/assets/img/twitter-banner.png">
    <meta name="twitter:site" content="@ScholarSwap">
    <meta name="twitter:creator" content="@ScholarSwap">

    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": "ScholarSwap",
            "url": "https://www.scholarswap.com",
            "description": "A collaborative academic platform for students to exchange notes, books and resources.",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "https://www.scholarswap.com/search?q={search_term_string}",
                "query-input": "required name=search_term_string"
            },
            "sameAs": [
                "https://twitter.com/ScholarSwap",
                "https://www.instagram.com/scholarswap",
                "https://www.facebook.com/scholarswap",
                "https://www.linkedin.com/company/scholarswap"
            ]
        }
    </script>
    <title>Upload Notes | ScholarSwap</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/upload.css">
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

    <div class="page-grid"></div>
    <?php include_once "../files/header.php"; ?>

    <div class="wrap">

        <!-- Page header -->
        <div class="page-head">
            <div class="page-head-left">
                <div class="page-icon"><i class="fas fa-file-lines"></i></div>
                <div>
                    <div class="page-title">Upload Notes</div>
                    <div class="page-sub">Share your notes with the ScholarSwap community — reviewed before publishing.</div>
                </div>
            </div>
            <a href="../../index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="card">
            <form method="POST" action="auth/upload_notes.php" enctype="multipart/form-data">

                <!-- NOTE INFO -->
                <div class="slabel"><i class="fas fa-circle-info"></i> Note Information</div>
                <div class="g2">
                    <div class="field">
                        <label>Note Title <span class="req">*</span></label>
                        <input type="text" name="title" placeholder="e.g. Thermodynamics Chapter 3 Summary" required>
                    </div>
                    <div class="field">
                        <label>Subject / Topic <span class="req">*</span></label>
                        <input type="text" name="subject" placeholder="e.g. Physics, Mathematics" required>
                    </div>
                    <div class="field">
                        <label>Course / Syllabus <span class="req">*</span></label>
                        <input type="text" name="course" placeholder="e.g. B.Sc, Class 12, JEE" required>
                    </div>
                    <div class="field">
                        <label>Language</label>
                        <select name="language">
                            <option value="">Select language</option>
                            <option value="English">English</option>
                            <option value="Hindi">Hindi</option>
                            <option value="Bengali">Bengali</option>
                            <option value="Tamil">Tamil</option>
                            <option value="Telugu">Telugu</option>
                            <option value="Marathi">Marathi</option>
                            <option value="Gujarati">Gujarati</option>
                            <option value="Kannada">Kannada</option>
                            <option value="Malayalam">Malayalam</option>
                            <option value="Punjabi">Punjabi</option>
                            <option value="Urdu">Urdu</option>
                            <option value="Odia">Odia</option>
                        </select>
                    </div>
                </div>
                <div class="g1">
                    <div class="field">
                        <label>Description</label>
                        <textarea name="description" placeholder="What topics does this cover? Who is it useful for? Any extra context…"></textarea>
                    </div>
                </div>

                <!-- DOCUMENT TYPE -->
                <div class="slabel"><i class="fas fa-tags"></i> Document Type</div>
                <div class="field">
                    <label>Select type <span class="req">*</span></label>
                    <div class="doctype-group">
                        <label class="doctype-pill"><input type="radio" name="document_type" value="class notes" checked><i class="fas fa-file-lines"></i> Class Notes</label>
                        <label class="doctype-pill"><input type="radio" name="document_type" value="hand written" checked><i class="fas fa-file-lines"></i> Hand Written</label>
                        <label class="doctype-pill"><input type="radio" name="document_type" value="summary"><i class="fas fa-list-check"></i> Summary</label>
                        <label class="doctype-pill"><input type="radio" name="document_type" value="assignment"><i class="fas fa-pen-ruler"></i> Assignment</label>
                        <label class="doctype-pill"><input type="radio" name="document_type" value="question_bank"><i class="fas fa-circle-question"></i> Question Bank</label>
                        <label class="doctype-pill"><input type="radio" name="document_type" value="solved_paper"><i class="fas fa-check-to-slot"></i> Solved Paper</label>
                        <label class="doctype-pill"><input type="radio" name="document_type" value="lab_manual"><i class="fas fa-flask"></i> Lab Manual</label>
                        <label class="doctype-pill"><input type="radio" name="document_type" value="study_guide"><i class="fas fa-book-open"></i> Study Guide</label>
                        <label class="doctype-pill"><input type="radio" name="document_type" value="other"><i class="fas fa-file"></i> Other</label>
                    </div>
                </div>

                <!-- PDF UPLOAD -->
                <div class="slabel"><i class="fas fa-file-pdf"></i> PDF File</div>
                <div class="field">
                    <label>Upload PDF <span class="req">*</span></label>
                    <div class="drop-zone" id="dropZone">
                        <input type="file" name="pdf" id="fileInput" accept="application/pdf" hidden>
                        <div class="drop-content">
                            <div class="drop-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                            <p><strong>Drag &amp; drop PDF here</strong><br>or click to browse · any size</p>
                        </div>
                    </div>
                    <div class="file-chosen" id="fileChosen">
                        <div class="fc-icon"><i class="fas fa-file-pdf"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="fc-name" id="fcName">—</div>
                            <div class="fc-size" id="fcSize"></div>
                        </div>
                        <div class="fc-remove" id="fcRemove" title="Remove file"><i class="fas fa-xmark"></i></div>
                    </div>
                    <div class="prog-wrap">
                        <div class="prog-bar" id="progBar"></div>
                    </div>
                    <div class="prog-row" id="progRow"><span>Preparing…</span><span id="pct">0%</span></div>
                    <div style="display:flex;justify-content:center;">
                        <div class="upload-tick" id="uploadTick"><i class="fas fa-check"></i></div>
                    </div>
                    <p class="hint" style="margin-top:8px;">
                        <i class="fas fa-circle-info"></i>
                        PDF only · no file size limit · reviewed by admin before publishing
                    </p>
                </div>

                <!-- TIPS -->
                <div class="tips-box">
                    <i class="fas fa-lightbulb tips-icon"></i>
                    <div class="tips-text">
                        <strong>Tips for a great upload</strong>
                        Make sure your notes are clearly written and well-organized. Include the subject, chapter, and course name in the title for better discoverability. Avoid uploading copyrighted textbook content.
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-cloud-arrow-up"></i> Upload Notes</button>
                    <a href="../../index.php" class="btn-cancel"><i class="fas fa-xmark"></i> Cancel</a>
                </div>

            </form>
        </div>
    </div>

    <?php include_once "../files/footer.php"; ?>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const chosen = document.getElementById('fileChosen');
        const fcName = document.getElementById('fcName');
        const fcSize = document.getElementById('fcSize');
        const progWrap = document.querySelector('.prog-wrap');
        const progBar = document.getElementById('progBar');
        const progRow = document.getElementById('progRow');
        const pct = document.getElementById('pct');
        const tick = document.getElementById('uploadTick');

        dropZone.addEventListener('click', () => fileInput.click());

        ['dragover', 'dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, e => e.preventDefault()));
        dropZone.addEventListener('dragover', () => dropZone.classList.add('dragover'));
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone.addEventListener('drop', e => {
            dropZone.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (file && file.type === 'application/pdf') {
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                handleFile(file);
            }
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) handleFile(fileInput.files[0]);
        });

        document.getElementById('fcRemove').addEventListener('click', () => {
            fileInput.value = '';
            chosen.classList.remove('show');
            dropZone.style.display = 'flex';
            progWrap.style.display = 'none';
            progRow.style.display = 'none';
            progBar.style.width = '0%';
            tick.style.display = 'none';
        });

        function fmt(b) {
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
            if (b < 1073741824) return (b / 1048576).toFixed(1) + ' MB';
            return (b / 1073741824).toFixed(2) + ' GB';
        }

        function handleFile(file) {
            dropZone.style.display = 'none';
            fcName.textContent = file.name;
            fcSize.textContent = fmt(file.size);
            chosen.classList.add('show');
            tick.style.display = 'none';
            runProgress();
        }

        function runProgress() {
            progWrap.style.display = 'block';
            progRow.style.display = 'flex';
            progBar.style.width = '0%';
            pct.textContent = '0%';
            let p = 0;
            const iv = setInterval(() => {
                p += 7 + Math.random() * 8;
                if (p >= 100) {
                    p = 100;
                    clearInterval(iv);
                    setTimeout(() => {
                        progWrap.style.display = 'none';
                        progRow.style.display = 'none';
                        tick.style.display = 'flex';
                    }, 250);
                }
                progBar.style.width = p + '%';
                pct.textContent = Math.floor(p) + '%';
            }, 150);
        }

        /* Doctype pill highlight fallback for older browsers */
        document.querySelectorAll('.doctype-pill input').forEach(inp => {
            inp.addEventListener('change', () => {
                document.querySelectorAll('.doctype-pill').forEach(p => p.classList.remove('checked'));
                inp.closest('.doctype-pill').classList.add('checked');
            });
        });
    </script>
</body>

</html>