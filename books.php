<?php
include_once('admin/config/connection.php');
include_once("admin/encryption.php");
require_once('admin/auth_check.php');

$stmt = $conn->prepare("
    SELECT b.*,
        COALESCE(
            (SELECT CONCAT(s.first_name,' ',s.last_name) FROM students   s WHERE s.user_id  = b.user_id),
            (SELECT CONCAT(t.first_name,' ',t.last_name) FROM tutors     t WHERE t.user_id  = b.user_id),
            (SELECT CONCAT(a.first_name,' ',a.last_name) FROM admin_user a WHERE a.admin_id = b.admin_id),
            'ScholarSwap Admin'
        ) AS uploader_name
    FROM books b
    WHERE b.approval_status = 'approved'
    ORDER BY b.book_id DESC
");
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($books);

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

        <div class="page-banner">
            <div class="banner-inner">
                <div class="banner-left">
                    <div class="banner-icon"><i class="fas fa-book-open"></i></div>
                    <div>
                        <div class="banner-title">Digital Library</div>
                        <div class="banner-sub">Browse and download books shared by students and tutors</div>
                    </div>
                </div>
                <a href="admin/user_pages/book_upload.php" class="btn-upload">
                    <i class="fas fa-cloud-arrow-up"></i> Upload Book
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

            .book-uploader {
                font-size: .75rem;
                color: var(--text2);
                display: flex;
                align-items: center;
                gap: 5px;
            }
        </style>
        <div class="req-breadcrumb">
            <div class="req-bc-inner">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span class="sep">›</span>
                <span class="cur">Books</span>
            </div>
        </div>

        <div class="search-bar-wrap">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by title, author, subject…">
                <button id="searchClear"><i class="fas fa-xmark"></i></button>
            </div>
        </div>

        <div class="layout">

            <aside class="sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-title">
                        <h3><i class="fas fa-sliders"></i> Filters</h3>
                        <span class="clear-link" id="clearAll">Clear all</span>
                    </div>

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
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="engineering"> Engineering</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="medicine"> Medicine</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="law"> Law</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="arts"> Arts</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="general"> General</label>
                            <div id="moreSubjects" style="display:none">
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="statistics"> Statistics</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="environmental science"> Environmental Science</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="physical education"> Physical Education</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="fine arts"> Fine Arts</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="music"> Music</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="home science"> Home Science</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="philosophy"> Philosophy</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="subject" value="agriculture"> Agriculture</label>
                            </div>
                            <span class="show-more-link" id="toggleSubjects">+ Show more</span>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Class / Level <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 1"> Class 1</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 2"> Class 2</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 3"> Class 3</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 4"> Class 4</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 5"> Class 5</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 6"> Class 6</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 7"> Class 7</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 8"> Class 8</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 9"> Class 9</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 10"> Class 10</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 11"> Class 11</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Class 12"> Class 12</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Undergraduate"> Undergraduate</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="Postgraduate"> Postgraduate</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="PhD / Research"> PhD / Research</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="class_level" value="General"> General / All Levels</label>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Document Type <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="textbook"> Textbook</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="reference book"> Reference Book</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="guide"> Guide / Help Book</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="question bank"> Question Bank</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="solved papers"> Solved Papers</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="notes"> Notes / Summary</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="novel"> Novel / Fiction</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="magazine"> Magazine / Journal</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="research paper"> Research Paper</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="doctype" value="other"> Other</label>
                        </div>
                    </div>

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

                    <div class="filter-section">
                        <h4>Published Year <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="year" value="2024"> 2024 – 2025</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="year" value="2022"> 2022 – 2023</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="year" value="2020"> 2020 – 2021</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="year" value="2015"> 2015 – 2019</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="year" value="2010"> 2010 – 2014</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="year" value="2000"> Before 2010</label>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Rating <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="5"><span class="star-filter">★★★★★</span>&nbsp;5 Stars</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="4"><span class="star-filter">★★★★☆</span>&nbsp;4+ Stars</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="3"><span class="star-filter">★★★☆☆</span>&nbsp;3+ Stars</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="2"><span class="star-filter">★★☆☆</span>&nbsp;2+ Stars</label>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Downloads <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="1000"> 1000+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="500"> 500+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="100"> 100+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="10"> 10+</label>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Availability <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="featured" value="1"> ⭐ Featured Only</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="availability" value="free"> Free to Download</label>
                        </div>
                    </div>

                </div>
            </aside>

            <main>
                <div class="results-head">
                    <p class="results-count">
                        Showing <strong id="showFrom">0</strong>–<strong id="showTo">0</strong>
                        of <strong id="showTotal">0</strong> books
                    </p>
                    <select class="sort-select" id="sortSelect">
                        <option value="newest">Newest First</option>
                        <option value="popular">Most Popular</option>
                        <option value="rated">Highest Rated</option>
                        <option value="downloads">Most Downloads</option>
                    </select>
                </div>

                <div class="books-grid" id="booksGrid">
                    <?php foreach ($books as $i => $b):

                        // Skip rows with no b_code — corrupt/incomplete DB rows
                        // NOTE: user_id CAN be NULL for admin-uploaded books — do NOT skip on that
                        if (empty($b['b_code'])) {
                            error_log('[books.php] Skipping book with missing b_code. book_id=' . ($b['book_id'] ?? 'unknown'));
                            continue;
                        }

                        $gc             = 'gc-' . ($i % 8);
                        $stars          = min(5, max(0, round((float)($b['rating'] ?? 0))));
                        $starStr        = str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
                        $byear          = !empty($b['published_year']) ? (int)substr($b['published_year'], 0, 4) : 0;
                        $doctype        = strtolower(trim($b['document_type'] ?? ''));
                        $lang           = strtolower(trim($b['language'] ?? 'english'));
                        $feat           = (int)($b['is_featured'] ?? 0);
                        $classLevel     = trim($b['class_level'] ?? '');
                        $classLevelData = htmlspecialchars($classLevel);

                        // Cover image — only filename stored in DB
                        $coverSrc = '';
                        if (!empty($b['cover_image'])) {
                            $ci = $b['cover_image'];
                            // If it already contains a slash it's a legacy full path, use as-is
                            // Otherwise it's just the filename — prepend the folder path
                            $coverSrc = (strpos($ci, '/') !== false)
                                ? htmlspecialchars($ci)
                                : 'admin/user_pages/uploads/cover_img/' . htmlspecialchars($ci);
                        }

                        $rtoken = safeEncrypt($b['b_code']);

                        // For admin-uploaded books user_id is NULL — encrypt admin_id instead
                        $utoken = !empty($b['user_id'])
                            ? safeEncrypt($b['user_id'])
                            : safeEncrypt($b['admin_id']);

                        $ttoken = safeEncrypt('book');

                        // If any token is empty after encryption, skip the card
                        if ($rtoken === '' || $utoken === '' || $ttoken === '') {
                            error_log('[books.php] encryptId returned empty for book b_code=' . $b['b_code']);
                            continue;
                        }

                        $readUrl = 'notes_reader?r=' . $rtoken . '&u=' . $utoken . '&t=' . $ttoken;
                    ?>
                        <div class="book-card"
                            data-title="<?php echo htmlspecialchars(strtolower($b['title'])); ?>"
                            data-subject="<?php echo htmlspecialchars(strtolower($b['subject'] ?? '')); ?>"
                            data-author="<?php echo htmlspecialchars(strtolower($b['author'] ?? '')); ?>"
                            data-rating="<?php echo $stars; ?>"
                            data-downloads="<?php echo (int)($b['download_count'] ?? 0); ?>"
                            data-views="<?php echo (int)($b['view_count'] ?? 0); ?>"
                            data-doctype="<?php echo htmlspecialchars($doctype); ?>"
                            data-year="<?php echo $byear; ?>"
                            data-language="<?php echo htmlspecialchars($lang); ?>"
                            data-featured="<?php echo $feat; ?>"
                            data-class_level="<?php echo $classLevelData; ?>"
                            onclick="window.location.href='<?php echo $readUrl; ?>'">

                            <div class="book-cover">
                                <?php if ($coverSrc): ?>
                                    <img src="<?php echo $coverSrc; ?>"
                                        alt="<?php echo htmlspecialchars($b['title']); ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                    <div class="cover-fallback <?php echo $gc; ?>" style="display:none">
                                        <i class="fas fa-book" style="opacity:.4;color:#fff"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="cover-fallback <?php echo $gc; ?>">
                                        <i class="fas fa-book" style="opacity:.4;color:#fff"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="cover-overlay"></div>
                                <span class="book-badge">Book</span>
                                <?php if ($classLevel): ?>
                                    <span class="class-badge"><?php echo htmlspecialchars($classLevel); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="book-body">
                                <?php if (!empty($b['subject'])): ?>
                                    <div class="book-subject"><?php echo htmlspecialchars($b['subject']); ?></div>
                                <?php endif; ?>
                                <div class="book-title"><?php echo htmlspecialchars($b['title']); ?></div>
                                <?php if (!empty($b['author'])): ?>
                                    <div class="book-author"><i class="fas fa-user-pen"></i><?php echo htmlspecialchars($b['author']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($b['uploader_name'])): ?>
                                    <div class="book-uploader">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($b['uploader_name']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($b['description'])): ?>
                                    <div class="book-desc"><?php echo htmlspecialchars($b['description']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="book-footer">
                                <div class="book-stats">
                                    <span class="bstat"><i class="fas fa-eye"></i><?php echo number_format($b['view_count'] ?? 0); ?></span>
                                    <span class="bstat"><i class="fas fa-download"></i><?php echo number_format($b['download_count'] ?? 0); ?></span>
                                    <?php if ($stars > 0): ?>
                                        <span class="book-stars"><?php echo $starStr; ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="book-free">Free</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pagination" id="pagination"></div>

                <!-- ── Request CTA ── -->
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
                            <i class="fas fa-compass"></i> Request a Book
                        </a>
                    </div>
                </section>
            </main>

        </div>
    </div>

    <?php include_once('admin/files/footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const grid = document.getElementById('booksGrid');
            const allCards = Array.from(grid.querySelectorAll('.book-card'));
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

            function matchYear(cardYear, filterYears) {
                if (!filterYears.length) return true;
                return filterYears.some(fy => {
                    const y = parseInt(fy);
                    if (y === 2024) return cardYear >= 2024;
                    if (y === 2022) return cardYear >= 2022 && cardYear <= 2023;
                    if (y === 2020) return cardYear >= 2020 && cardYear <= 2021;
                    if (y === 2015) return cardYear >= 2015 && cardYear <= 2019;
                    if (y === 2010) return cardYear >= 2010 && cardYear <= 2014;
                    if (y === 2000) return cardYear > 0 && cardYear < 2010;
                    return false;
                });
            }

            function applyFilters() {
                const q = searchInp.value.trim().toLowerCase();
                searchClr.style.display = q ? 'block' : 'none';
                const subjects = getChecked('subject');
                const classLevels = getChecked('class_level');
                const doctypes = getChecked('doctype');
                const languages = getChecked('language');
                const years = getChecked('year');
                const ratings = getChecked('rating').map(Number);
                const downloads = getChecked('downloads').map(Number);
                const featured = getChecked('featured');

                filtered = allCards.filter(c => {
                    const d = c.dataset;
                    const cardYear = parseInt(d.year) || 0;
                    const cardClass = d.class_level || '';
                    if (q && !d.title.includes(q) && !d.author.includes(q) && !d.subject.includes(q)) return false;
                    if (subjects.length && !subjects.some(s => d.subject.includes(s))) return false;
                    if (classLevels.length && !classLevels.includes(cardClass)) return false;
                    if (doctypes.length && !doctypes.some(dt => d.doctype.includes(dt))) return false;
                    if (languages.length && !languages.includes(d.language)) return false;
                    if (!matchYear(cardYear, years)) return false;
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
                    if (v === 'popular') return b.dataset.views - a.dataset.views;
                    if (v === 'rated') return b.dataset.rating - a.dataset.rating;
                    if (v === 'downloads') return b.dataset.downloads - a.dataset.downloads;
                    return 0;
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
                            <div class="empty-icon"><i class="fas fa-book-open"></i></div>
                            <h3>No books found</h3>
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
                        scrollTo({
                            top: 200,
                            behavior: 'smooth'
                        });
                    }
                };
                pagination.appendChild(prev);

                const range = 2;
                const rs = Math.max(1, page - range),
                    re = Math.min(totalPages, page + range);
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
                        scrollTo({
                            top: 200,
                            behavior: 'smooth'
                        });
                    }
                };
                pagination.appendChild(next);
            }

            function addEllipsis() {
                const el = document.createElement('span');
                el.className = 'pag-ellipsis';
                el.textContent = '…';
                pagination.appendChild(el);
            }

            function mkPageBtn(i) {
                const b = mkBtn(i);
                if (i === page) b.classList.add('active');
                b.onclick = () => {
                    page = i;
                    render();
                    scrollTo({
                        top: 200,
                        behavior: 'smooth'
                    });
                };
                return b;
            }

            function mkBtn(label) {
                const b = document.createElement('button');
                b.className = 'pag-btn';
                b.innerHTML = label;
                return b;
            }

            document.querySelectorAll('.filter-section h4').forEach(h => {
                h.addEventListener('click', () => {
                    const open = h.classList.toggle('open');
                    h.nextElementSibling.style.display = open ? '' : 'none';
                });
            });

            const moreEl = document.getElementById('moreSubjects');
            const toggleEl = document.getElementById('toggleSubjects');
            if (toggleEl && moreEl) {
                toggleEl.addEventListener('click', () => {
                    const showing = moreEl.style.display !== 'none';
                    moreEl.style.display = showing ? 'none' : 'block';
                    toggleEl.textContent = showing ? '+ Show more' : '− Show less';
                });
            }

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
                    color: '#0f172a',
                    iconColor: '#059669'
                });
                setTimeout(() => history.replaceState(null, '', 'books.php'), 500);
            } else if (s === 'failed') {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: 'Something went wrong. Please try again.',
                    timer: 3000,
                    showConfirmButton: false,
                    background: '#ffffff',
                    color: '#0f172a',
                    iconColor: '#dc2626'
                });
                setTimeout(() => history.replaceState(null, '', 'books.php'), 500);
            }
        });
    </script>
</body>

</html>