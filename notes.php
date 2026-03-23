<?php
include_once('admin/config/connection.php');
include_once('admin/encryption.php');
require_once('admin/auth_check.php');

$stmt = $conn->prepare("
    SELECT n.*,
        COALESCE(
            (SELECT CONCAT(s.first_name,' ',s.last_name) FROM students   s WHERE s.user_id  = n.user_id),
            (SELECT CONCAT(t.first_name,' ',t.last_name) FROM tutors     t WHERE t.user_id  = n.user_id),
            (SELECT CONCAT(a.first_name,' ',a.last_name) FROM admin_user a WHERE a.admin_id = n.admin_id),
            'ScholarSwap Admin'
        ) AS uploader_name
    FROM notes n
    WHERE n.approval_status = 'approved'
    ORDER BY n.created_at DESC
");
$stmt->execute();
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($notes);

// Safe wrapper around encryptId — returns '' for NULL/empty values
// instead of throwing TypeError.
function safeEncrypt($value): string
{
    if ($value === null || $value === '') return '';
    return encryptId((string)$value);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta -->
    <title>ScholarSwap — Share, Swap &amp; Succeed Together</title>
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

    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="manifest" href="assets/site.webmanifest">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="page-grid"></div>
    <div class="content">

        <?php include_once('admin/files/header.php'); ?>

        <!-- ── BANNER ── -->
        <div class="page-banner">
            <div class="banner-inner">
                <div class="banner-left">
                    <div class="banner-icon"><i class="fas fa-file-lines"></i></div>
                    <div>
                        <div class="banner-title">Browse Notes</div>
                        <div class="banner-sub">Discover study notes, summaries and solved papers shared by students &amp; tutors</div>
                    </div>
                </div>
                <a href="admin/user_pages/notes_upload.php" class="btn-upload">
                    <i class="fas fa-cloud-arrow-up"></i> Upload Notes
                </a>
            </div>
        </div>
        <style>
            .req-breadcrumb {
                background: var(--surface);
                border-bottom: 1px solid var(--border);
                padding: 10px 0;
            }

            .req-bc-inner {
                max-width: 1160px;
                margin: 0 auto;
                padding: 0 20px;
                display: flex;
                align-items: center;
                gap: 7px;
                font-size: .78rem;
                color: var(--text2);
            }

            .req-bc-inner a {
                color: var(--primary);
                font-weight: 500;
                text-decoration: none;
            }

            .req-bc-inner a:hover {
                opacity: .75;
            }

            .req-bc-inner .sep {
                color: rgb(0 0 0 / 45%);
            }

            .req-bc-inner .cur {
                color: var(--text);
                font-weight: 600;
            }
        </style>
        <div class="req-breadcrumb">
            <div class="req-bc-inner">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span class="sep">›</span>
                <span class="cur">Notes</span>
            </div>
        </div>

        <!-- ── SEARCH ── -->
        <div class="search-bar-wrap">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by title, subject, course, uploader…">
                <button id="searchClear"><i class="fas fa-xmark"></i></button>
            </div>
        </div>

        <!-- ── LAYOUT ── -->
        <div class="layout">

            <!-- SIDEBAR -->
            <aside class="sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-title">
                        <h3><i class="fas fa-sliders"></i> Filters</h3>
                        <span class="clear-link" id="clearAll">Clear all</span>
                    </div>

                    <!-- Subject -->
                    <div class="filter-section">
                        <h4>Subject <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="mathematics"> Mathematics</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="physics"> Physics</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="chemistry"> Chemistry</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="biology"> Biology</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="computer science"> Computer Science</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="english"> English</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="history"> History</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="geography"> Geography</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="economics"> Economics</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="accountancy"> Accountancy</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="commerce"> Commerce</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="political science"> Political Science</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="psychology"> Psychology</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="sociology"> Sociology</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="literature"> Literature</label>
                            <div id="moreSubjects" style="display:none">
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="statistics"> Statistics</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="engineering"> Engineering</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="medicine"> Medicine</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="law"> Law</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="environmental science"> Environmental Science</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="physical education"> Physical Education</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="fine arts"> Fine Arts</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="home science"> Home Science</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="agriculture"> Agriculture</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="general"> General</label>
                            </div>
                            <span class="show-more-link" id="toggleSubjects">+ Show more</span>
                        </div>
                    </div>

                    <!-- Course / Level -->
                    <div class="filter-section">
                        <h4>Course / Level <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="class 9"> Class 9</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="class 10"> Class 10</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="class 11"> Class 11</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="class 12"> Class 12</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="undergraduate"> Undergraduate (UG)</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="postgraduate"> Postgraduate (PG)</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="btech"> B.Tech / B.E.</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="bsc"> B.Sc</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="bca"> BCA / MCA</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="bcom"> B.Com / MBA</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="jee"> JEE / NEET</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="upsc"> UPSC / SSC</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="course" value="diploma"> Diploma / ITI</label>
                        </div>
                    </div>

                    <!-- Document Type -->
                    <div class="filter-section">
                        <h4>Document Type <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="notes"> Class Notes</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="summary"> Summary</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="assignment"> Assignment</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="question_bank"> Question Bank</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="solved_paper"> Solved Paper</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="lab_manual"> Lab Manual</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="study_guide"> Study Guide</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="other"> Other</label>
                        </div>
                    </div>

                    <!-- Language -->
                    <div class="filter-section">
                        <h4>Language <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="english"> English</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="hindi"> Hindi</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="bengali"> Bengali</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="tamil"> Tamil</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="telugu"> Telugu</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="marathi"> Marathi</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="gujarati"> Gujarati</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="kannada"> Kannada</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="malayalam"> Malayalam</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="punjabi"> Punjabi</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="urdu"> Urdu</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="odia"> Odia</label>
                        </div>
                    </div>

                    <!-- Rating -->
                    <div class="filter-section">
                        <h4>Rating <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="5"><span class="star-filter">★★★★★</span>&nbsp;5 Stars</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="4"><span class="star-filter">★★★★☆</span>&nbsp;4+ Stars</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="3"><span class="star-filter">★★★☆☆</span>&nbsp;3+ Stars</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="2"><span class="star-filter">★★☆☆☆</span>&nbsp;2+ Stars</label>
                        </div>
                    </div>

                    <!-- Downloads -->
                    <div class="filter-section">
                        <h4>Downloads <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="1000"> 1000+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="500"> 500+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="100"> 100+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="10"> 10+</label>
                        </div>
                    </div>

                    <!-- Availability -->
                    <div class="filter-section">
                        <h4>Availability <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="featured" value="1"> ⭐ Featured Only</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="availability" value="free"> Free to Download</label>
                        </div>
                    </div>

                </div><!-- /sidebar-card -->
            </aside>

            <!-- ── RESULTS ── -->
            <main>
                <div class="results-head">
                    <p class="results-count">
                        Showing <strong id="showFrom">0</strong>–<strong id="showTo">0</strong>
                        of <strong id="showTotal">0</strong> notes
                    </p>
                    <select class="sort-select" id="sortSelect">
                        <option value="newest">Newest First</option>
                        <option value="popular">Most Popular</option>
                        <option value="rated">Highest Rated</option>
                        <option value="downloads">Most Downloads</option>
                    </select>
                </div>

                <div class="notes-grid" id="notesGrid">
                    <?php foreach ($notes as $i => $n):

                        // Skip rows with no n_code — corrupt/incomplete DB rows
                        // NOTE: user_id CAN be NULL for admin-uploaded notes — do NOT skip on that
                        if (empty($n['n_code'])) {
                            error_log('[notes.php] Skipping note with missing n_code. note_id=' . ($n['note_id'] ?? 'unknown'));
                            continue;
                        }

                        $gc       = 'gc-' . ($i % 8);
                        $stars    = min(5, max(0, (int)($n['rating'] ?? 0)));
                        $starStr  = str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
                        $doctype  = strtolower(trim($n['document_type'] ?? 'notes'));
                        $doclevel = strtolower(trim($n['notes_level']   ?? 'notes'));
                        $lang     = strtolower(trim($n['language']      ?? 'english'));
                        $feat     = (int)($n['is_featured'] ?? 0);

                        $rtoken = safeEncrypt($n['n_code']);

                        // For admin-uploaded notes user_id is NULL — encrypt admin_id instead
                        $utoken = !empty($n['user_id'])
                            ? safeEncrypt($n['user_id'])
                            : safeEncrypt($n['admin_id']);

                        $ttoken = safeEncrypt('note');

                        // If any token is empty after encryption, skip the card
                        if ($rtoken === '' || $utoken === '' || $ttoken === '') {
                            error_log('[notes.php] encryptId returned empty for note n_code=' . $n['n_code']);
                            continue;
                        }

                        $readUrl = 'notes_reader?r=' . $rtoken . '&u=' . $utoken . '&t=' . $ttoken;
                    ?>
                        <div class="note-card"
                            data-title="<?php echo htmlspecialchars(strtolower($n['title'])); ?>"
                            data-subject="<?php echo htmlspecialchars(strtolower($n['subject'] ?? '')); ?>"
                            data-course="<?php echo htmlspecialchars(strtolower($n['course'] ?? '')); ?>"
                            data-doctype="<?php echo htmlspecialchars($doctype); ?>"
                            data-language="<?php echo htmlspecialchars($lang); ?>"
                            data-uploader="<?php echo htmlspecialchars(strtolower($n['uploader_name'])); ?>"
                            data-rating="<?php echo $stars; ?>"
                            data-downloads="<?php echo (int)($n['download_count'] ?? 0); ?>"
                            data-views="<?php echo (int)($n['view_count'] ?? 0); ?>"
                            data-featured="<?php echo $feat; ?>"
                            onclick="window.location.href='<?php echo $readUrl; ?>'">

                            <div class="note-band <?php echo $gc; ?>"></div>

                            <div class="note-body">
                                <div class="note-tags">
                                    <span class="ntag ntag-type"><?php echo htmlspecialchars(str_replace('_', ' ', $doctype)); ?></span>
                                    <?php if (!empty($n['course'])): ?>
                                        <span class="ntag ntag-course"><?php echo htmlspecialchars($n['course']); ?></span>
                                    <?php endif; ?>
                                    <span class="ntag ntag-level"><?php echo htmlspecialchars($doclevel); ?></span>
                                </div>
                                <?php if (!empty($n['subject'])): ?>
                                    <div class="note-subject"><?php echo htmlspecialchars($n['subject']); ?></div>
                                <?php endif; ?>
                                <div class="note-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                <?php if (!empty($n['description'])): ?>
                                    <div class="note-desc"><?php echo htmlspecialchars($n['description']); ?></div>
                                <?php endif; ?>
                                <div class="note-uploader">
                                    <i class="fas fa-user-circle"></i>
                                    <?php echo htmlspecialchars($n['uploader_name']); ?>
                                    <?php if (!empty($n['language'])): ?>
                                        &nbsp;·&nbsp;<?php echo htmlspecialchars($n['language']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="note-footer">
                                <div class="note-stats">
                                    <span class="nstat"><i class="fas fa-eye"></i><?php echo number_format($n['view_count'] ?? 0); ?></span>
                                    <span class="nstat"><i class="fas fa-download"></i><?php echo number_format($n['download_count'] ?? 0); ?></span>
                                    <?php if ($stars > 0): ?>
                                        <span class="note-stars"><?php echo $starStr; ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="note-free">Free</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pagination" id="pagination"></div>
                <section style="
                    margin-top: 40px;
                    background: linear-gradient(135deg, var(--primary-xlight) 0%, #fff4e6 60%, var(--page-bg) 100%);
                    border: 1px solid rgba(122,12,12,.12);
                    border-radius: 20px;
                    padding: 44px 32px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;">
                    <div style="position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(122,12,12,.06);filter:blur(72px);top:-80px;right:-60px;pointer-events:none"></div>
                    <div style="position:absolute;width:200px;height:200px;border-radius:50%;background:rgba(242,180,0,.08);filter:blur(60px);bottom:-60px;left:-40px;pointer-events:none"></div>

                    <div style="position:relative;z-index:1;max-width:520px;margin:0 auto">
                        <div style="
                            display:inline-flex;align-items:center;gap:7px;
                            background:rgba(242,180,0,.18);border:1px solid rgba(242,180,0,.4);
                            border-radius:99px;padding:5px 16px;
                            font-size:.72rem;font-weight:700;color:var(--gold);
                            letter-spacing:.1em;text-transform:uppercase;
                            margin-bottom:18px;
                        "><i class="fas fa-inbox"></i> Study Material Requests</div>

                        <h2 style="
                            font-family:'Space Grotesk',sans-serif;
                            font-size:clamp(1.4rem,3vw,1.9rem);
                            font-weight:800;color:var(--text);
                            line-height:1.2;margin-bottom:12px;
                        ">Can't find your study material?</h2>

                        <p style="font-size:.9rem;color:var(--text2);line-height:1.75;margin-bottom:26px;">
                            Submit a request and our library team will source your notes,
                            books, or past papers — usually within
                            <strong style="color:var(--text)">24–48 hours</strong>.
                        </p>

                        <div style="display:flex;align-items:center;justify-content:center;gap:20px;flex-wrap:wrap;margin-bottom:26px;">
                            <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--text3)">
                                <i class="fas fa-circle-check" style="color:var(--green);font-size:.7rem"></i> 96%+ success rate
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--text3)">
                                <i class="fas fa-clock" style="color:var(--primary);font-size:.7rem"></i> Avg. response in 24 hrs
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--text3)">
                                <i class="fas fa-lock" style="color:var(--gold);font-size:.7rem"></i> Always free
                            </div>
                        </div>

                        <a href="request.php"
                            style="
                               display:inline-flex;align-items:center;gap:9px;
                               background:var(--primary);color:#fff;
                               padding:13px 30px;border-radius:12px;
                               font-size:.9rem;font-weight:700;text-decoration:none;
                               box-shadow:0 6px 20px rgba(122,12,12,.28);
                               transition:all .2s;
                           "
                            onmouseover="this.style.background='#5a0909';this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.background='var(--primary)';this.style.transform='translateY(0)'">
                            <i class="fas fa-compass"></i> Request a Note
                        </a>
                    </div>
                </section>
            </main>

        </div><!-- /layout -->

    </div><!-- /content -->

    <?php include_once('admin/files/footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const grid = document.getElementById('notesGrid');
            const allCards = Array.from(grid.querySelectorAll('.note-card'));
            const pagination = document.getElementById('pagination');
            const elFrom = document.getElementById('showFrom');
            const elTo = document.getElementById('showTo');
            const elTotal = document.getElementById('showTotal');
            const searchInp = document.getElementById('searchInput');
            const searchClr = document.getElementById('searchClear');
            const sortSel = document.getElementById('sortSelect');
            const PER_PAGE = 12;
            let page = 1,
                filtered = [...allCards];

            function getChecked(type) {
                return [...document.querySelectorAll(`input[data-filter="${type}"]:checked`)].map(c => c.value);
            }

            function applyFilters() {
                const q = searchInp.value.trim().toLowerCase();
                searchClr.style.display = q ? 'block' : 'none';
                const subjects = getChecked('subject');
                const courses = getChecked('course');
                const doctypes = getChecked('doctype');
                const languages = getChecked('language');
                const ratings = getChecked('rating').map(Number);
                const downloads = getChecked('downloads').map(Number);
                const featured = getChecked('featured');

                filtered = allCards.filter(c => {
                    const d = c.dataset;
                    if (q && !d.title.includes(q) && !d.subject.includes(q) && !d.course.includes(q) && !d.uploader.includes(q)) return false;
                    if (subjects.length && !subjects.some(s => d.subject.includes(s))) return false;
                    if (courses.length && !courses.some(co => d.course.includes(co))) return false;
                    if (doctypes.length && !doctypes.includes(d.doctype)) return false;
                    if (languages.length && !languages.includes(d.language)) return false;
                    if (ratings.length && !ratings.some(r => parseInt(d.rating) >= r)) return false;
                    if (downloads.length && !downloads.some(dl => parseInt(d.downloads) >= dl)) return false;
                    if (featured.length && d.featured !== '1') return false;
                    return true;
                });
                page = 1;
                applySort();
            }

            function applySort() {
                const v = sortSel.value;
                filtered.sort((a, b) => {
                    if (v === 'popular') return parseInt(b.dataset.views) - parseInt(a.dataset.views);
                    if (v === 'rated') return parseInt(b.dataset.rating) - parseInt(a.dataset.rating);
                    if (v === 'downloads') return parseInt(b.dataset.downloads) - parseInt(a.dataset.downloads);
                    return 0; // newest — original DOM order
                });
                render();
            }

            function render() {
                grid.innerHTML = '';
                const start = (page - 1) * PER_PAGE;
                const slice = filtered.slice(start, start + PER_PAGE);

                if (!slice.length) {
                    grid.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-file-lines"></i></div>
                            <h3>No notes found</h3>
                            <p>Try adjusting your search or filters.</p>
                        </div>`;
                    elFrom.textContent = 0;
                    elTo.textContent = 0;
                    elTotal.textContent = 0;
                } else {
                    slice.forEach(c => grid.appendChild(c));
                    elFrom.textContent = start + 1;
                    elTo.textContent = Math.min(start + PER_PAGE, filtered.length);
                    elTotal.textContent = filtered.length;
                }

                buildPagination();
            }

            function buildPagination() {
                pagination.innerHTML = '';
                const totalPages = Math.ceil(filtered.length / PER_PAGE);
                if (totalPages <= 1) return;

                const prev = mkBtn('<i class="fas fa-chevron-left"></i>');
                prev.disabled = page === 1;
                prev.onclick = () => {
                    if (page > 1) {
                        page--;
                        render();
                        scrollTop();
                    }
                };
                pagination.appendChild(prev);

                const range = 2;
                const rs = Math.max(1, page - range);
                const re = Math.min(totalPages, page + range);

                if (rs > 1) {
                    pagination.appendChild(mkPageBtn(1));
                    if (rs > 2) addEllipsis();
                }
                for (let i = rs; i <= re; i++) pagination.appendChild(mkPageBtn(i));
                if (re < totalPages) {
                    if (re < totalPages - 1) addEllipsis();
                    pagination.appendChild(mkPageBtn(totalPages));
                }

                const next = mkBtn('<i class="fas fa-chevron-right"></i>');
                next.disabled = page === totalPages;
                next.onclick = () => {
                    if (page < totalPages) {
                        page++;
                        render();
                        scrollTop();
                    }
                };
                pagination.appendChild(next);
            }

            function mkPageBtn(i) {
                const b = mkBtn(i);
                if (i === page) b.classList.add('active');
                b.onclick = () => {
                    page = i;
                    render();
                    scrollTop();
                };
                return b;
            }

            function mkBtn(label) {
                const b = document.createElement('button');
                b.className = 'pag-btn';
                b.innerHTML = label;
                return b;
            }

            function addEllipsis() {
                const el = document.createElement('span');
                el.className = 'pag-ellipsis';
                el.textContent = '…';
                pagination.appendChild(el);
            }

            function scrollTop() {
                scrollTo({
                    top: 200,
                    behavior: 'smooth'
                });
            }

            /* ── Collapsible filter sections ── */
            document.querySelectorAll('.filter-section h4').forEach(h => {
                h.addEventListener('click', () => {
                    const open = h.classList.toggle('open');
                    h.nextElementSibling.style.display = open ? '' : 'none';
                });
            });

            /* ── Show more subjects ── */
            const moreS = document.getElementById('moreSubjects');
            const togS = document.getElementById('toggleSubjects');
            if (togS && moreS) {
                togS.addEventListener('click', () => {
                    const show = moreS.style.display !== 'none';
                    moreS.style.display = show ? 'none' : 'block';
                    togS.textContent = show ? '+ Show more' : '− Show less';
                });
            }

            /* ── Clear all filters ── */
            document.getElementById('clearAll').addEventListener('click', () => {
                document.querySelectorAll('input[data-filter]').forEach(c => c.checked = false);
                searchInp.value = '';
                applyFilters();
            });

            searchInp.addEventListener('input', applyFilters);
            searchClr.addEventListener('click', () => {
                searchInp.value = '';
                applyFilters();
            });
            sortSel.addEventListener('change', applySort);
            document.querySelectorAll('input[data-filter]').forEach(c => c.addEventListener('change', applyFilters));

            applyFilters();

            /* ── SweetAlert flash messages ── */
            const s = new URLSearchParams(location.search).get('s');
            if (s === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Done!',
                    text: 'Your task completed successfully.',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    background: '#ffffff',
                    color: '#0f172a'
                });
                setTimeout(() => history.replaceState(null, '', 'notes.php'), 500);
            } else if (s === 'failed') {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: 'Something went wrong. Please try again.',
                    timer: 3000,
                    showConfirmButton: false,
                    background: '#ffffff',
                    color: '#0f172a'
                });
                setTimeout(() => history.replaceState(null, '', 'notes.php'), 500);
            }
        });
    </script>
</body>

</html>