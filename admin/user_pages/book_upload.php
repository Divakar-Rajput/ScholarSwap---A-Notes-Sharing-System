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
    <title>Upload Book | ScholarSwap</title>
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

        <div class="page-head">
            <div class="page-head-left">
                <div class="page-icon"><i class="fas fa-book"></i></div>
                <div>
                    <div class="page-title">Upload a Book</div>
                    <div class="page-sub">Fill in the details below — your submission will be reviewed before publishing.</div>
                </div>
            </div>
            <a href="../../index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="card">
            <form method="POST" action="auth/upload_book.php" enctype="multipart/form-data" id="uploadForm">

                <!-- ── BOOK INFORMATION ── -->
                <div class="slabel"><i class="fas fa-circle-info"></i> Book Information</div>
                <div class="g2">
                    <div class="field">
                        <label>Book Title <span class="req">*</span></label>
                        <input type="text" name="title" placeholder="e.g. Organic Chemistry Vol. 2" required>
                    </div>
                    <div class="field">
                        <label>Author Name <span class="req">*</span></label>
                        <input type="text" name="author" placeholder="e.g. R.K. Gupta" required>
                    </div>
                    <div class="field">
                        <label>Subject / Field <span class="req">*</span></label>
                        <input type="text" name="subject" placeholder="e.g. Chemistry, Mathematics" required>
                    </div>
                    <div class="field">
                        <label>Publication Name</label>
                        <input type="text" name="publication_name" placeholder="e.g. Oxford University Press">
                    </div>
                    <div class="field">
                        <label>Published Year</label>
                        <input type="date" name="publish_year">
                    </div>
                </div>
                <div class="g1">
                    <div class="field">
                        <label>Description</label>
                        <textarea name="description" placeholder="Topics covered, who it's useful for, edition info, etc."></textarea>
                    </div>
                </div>

                <!-- ── CLASS / LEVEL ── -->
                <div class="slabel"><i class="fas fa-graduation-cap"></i> Class / Level <span class="req" style="font-size:.7rem;letter-spacing:0;text-transform:none;font-weight:400;margin-left:2px;">*</span></div>
                <div class="field">
                    <label>Select the class or education level this book is for <span class="req">*</span></label>
                    <div class="class-grid" id="classGrid">

                        <div class="pill-group-label">Primary School</div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl1" value="Class 1"><label for="cl1">Class 1</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl2" value="Class 2"><label for="cl2">Class 2</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl3" value="Class 3"><label for="cl3">Class 3</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl4" value="Class 4"><label for="cl4">Class 4</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl5" value="Class 5"><label for="cl5">Class 5</label></div>

                        <div class="pill-group-label">Middle School</div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl6" value="Class 6"><label for="cl6">Class 6</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl7" value="Class 7"><label for="cl7">Class 7</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl8" value="Class 8"><label for="cl8">Class 8</label></div>

                        <div class="pill-group-label">Secondary School</div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl9" value="Class 9"><label for="cl9">Class 9</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl10" value="Class 10"><label for="cl10">Class 10</label></div>

                        <div class="pill-group-label">Higher Secondary</div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl11" value="Class 11"><label for="cl11">Class 11</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="cl12" value="Class 12"><label for="cl12">Class 12</label></div>

                        <div class="pill-group-label">University &amp; Professional</div>
                        <div class="class-pill"><input type="radio" name="class_level" id="clUG" value="Undergraduate"><label for="clUG"><i class="fas fa-university" style="font-size:.7rem;opacity:.7;"></i> Undergraduate</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="clPG" value="Postgraduate"><label for="clPG"><i class="fas fa-user-graduate" style="font-size:.7rem;opacity:.7;"></i> Postgraduate</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="clPHD" value="PhD / Research"><label for="clPHD"><i class="fas fa-flask" style="font-size:.7rem;opacity:.7;"></i> PhD / Research</label></div>
                        <div class="class-pill"><input type="radio" name="class_level" id="clGEN" value="General"><label for="clGEN"><i class="fas fa-globe" style="font-size:.7rem;opacity:.7;"></i> General / All Levels</label></div>

                    </div>
                    <div class="class-err" id="classErr"><i class="fas fa-circle-exclamation" style="font-size:.65rem;"></i> Please select a class or education level.</div>
                </div>

                <!-- ── COVER IMAGE ── -->
                <div class="slabel"><i class="fas fa-image"></i> Cover Image</div>
                <div class="field">
                    <label>Book Cover <span style="font-size:.72rem;font-weight:400;color:var(--text3);">(Optional)</span></label>
                    <div class="cover-area" onclick="document.getElementById('coverInput').click()">
                        <div class="cover-placeholder" id="coverPlaceholder"><i class="fas fa-image"></i></div>
                        <img id="coverPreview" src="" alt="Cover preview">
                        <div class="cover-text">
                            <strong>Click to upload cover image</strong>
                            <span>JPG, PNG or WEBP</span>
                        </div>
                    </div>
                    <input type="file" name="cover_image" id="coverInput" accept="image/*" hidden>
                    <!-- Cover size hint -->
                    <div class="cover-size-hint">
                        <i class="fas fa-ruler-combined"></i>
                        Recommended cover image size: <strong>448 × 234 pixels</strong> and padding 40px from top, right, bottom and left.
                    </div>
                </div>

                <!-- ── PDF UPLOAD ── -->
                <div class="slabel"><i class="fas fa-file-pdf"></i> Book File (PDF)</div>
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

                <!-- ── ACTIONS ── -->
                <div class="actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-cloud-arrow-up"></i> Upload Book</button>
                    <a href="../../index.php" class="btn-cancel"><i class="fas fa-xmark"></i> Cancel</a>
                </div>

            </form>
        </div>
    </div>

    <?php include_once "../files/footer.php"; ?>

    <script>
        /* ── Cover image preview ── */
        document.getElementById('coverInput').addEventListener('change', function() {
            if (!this.files[0]) return;
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.getElementById('coverPreview');
                const ph = document.getElementById('coverPlaceholder');
                img.src = e.target.result;
                img.style.display = 'block';
                ph.style.display = 'none';
            };
            reader.readAsDataURL(this.files[0]);
        });

        /* ── Drop zone ── */
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

        /* ── Class level validation ── */
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const selected = document.querySelector('input[name="class_level"]:checked');
            const errEl = document.getElementById('classErr');
            if (!selected) {
                e.preventDefault();
                errEl.classList.add('show');
                document.getElementById('classGrid').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            } else {
                errEl.classList.remove('show');
            }
        });
        document.querySelectorAll('input[name="class_level"]').forEach(r => {
            r.addEventListener('change', () => document.getElementById('classErr').classList.remove('show'));
        });
    </script>
</body>

</html>