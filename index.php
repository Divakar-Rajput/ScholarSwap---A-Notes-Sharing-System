<?php
include_once('admin/config/connection.php');
include_once('admin/encryption.php');

session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$user_id    = $_SESSION['user_id'] ?? null;

// ── Safe encrypt ─────────────────────────────────────────────────
function se($v): string
{
    if ($v === null || $v === '') return '';
    return encryptId((string)$v);
}

function readerUrl(array $row): string
{
    $type = $row['type'] ?? 'note';
    $eR   = se($row['id']);
    if (!$eR) return '#';
    if ($type === 'newspaper') {
        return 'newspaper_reader.php?r=' . urlencode($eR);
    }
    $eT = se($type);
    $eU = se($row['user_id'] ?? '');
    if (!$eT) return '#';
    $url = 'notes_reader.php?r=' . urlencode($eR) . '&t=' . urlencode($eT);
    if ($eU) $url .= '&u=' . urlencode($eU);
    return $url;
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 0)      return date('d M Y', strtotime($datetime));
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60)    . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600)  . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function dbCount($conn, $sql): int
{
    $s = $conn->prepare($sql);
    $s->execute();
    return (int)$s->fetchColumn();
}

$stat_students = dbCount($conn, "SELECT COUNT(*) FROM students");
$stat_notes    = dbCount($conn, "SELECT COUNT(*) FROM notes      WHERE approval_status='approved'");
$stat_books    = dbCount($conn, "SELECT COUNT(*) FROM books      WHERE approval_status='approved'");
$stat_papers   = dbCount($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='approved'");

// ── MY UPLOADS ───────────────────────────────────────────────────
$myUploads = [];
if ($isLoggedIn) {
    $sq = $conn->prepare("
        SELECT n_code AS id, user_id, title, subject, course AS level,
               download_count, view_count, rating, created_at, 'note' AS type
        FROM notes WHERE user_id = :uid AND approval_status = 'approved'
        UNION ALL
        SELECT b_code, user_id, title, subject, class_level,
               download_count, view_count, rating, created_at, 'book'
        FROM books WHERE user_id = :uid2 AND approval_status = 'approved'
        ORDER BY created_at DESC LIMIT 6
    ");
    $sq->execute([':uid' => $user_id, ':uid2' => $user_id]);
    $myUploads = $sq->fetchAll(PDO::FETCH_ASSOC);
}

// ── TRENDING ─────────────────────────────────────────────────────
$trendingQ = $conn->prepare("
    SELECT n_code AS id, user_id, title, subject, course AS level,
           download_count, view_count, rating, created_at, 'note' AS type
    FROM notes WHERE approval_status='approved'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY download_count DESC LIMIT 6
");
$trendingQ->execute();
$trending = $trendingQ->fetchAll(PDO::FETCH_ASSOC);

// ── LATEST NOTES ─────────────────────────────────────────────────
$latestQ = $conn->prepare("
    SELECT n.n_code AS id, n.user_id, n.title, n.subject, n.course AS level,
           n.download_count, n.view_count, n.rating, n.created_at, 'note' AS type,
           COALESCE(
               (SELECT CONCAT(s.first_name,' ',s.last_name) FROM students s WHERE s.user_id=n.user_id),
               (SELECT CONCAT(t.first_name,' ',t.last_name) FROM tutors   t WHERE t.user_id=n.user_id),
               'Anonymous') AS author_name
    FROM notes n WHERE n.approval_status='approved'
    ORDER BY n.created_at DESC LIMIT 6
");
$latestQ->execute();
$latest = $latestQ->fetchAll(PDO::FETCH_ASSOC);

// ── FEATURED ─────────────────────────────────────────────────────
$featuredQ = $conn->prepare("
    SELECT n_code AS id, user_id, title, subject, course AS level,
           download_count, view_count, rating, created_at, 'note' AS type
    FROM notes WHERE approval_status='approved' AND is_featured=1
    UNION ALL
    SELECT b_code, user_id, title, subject, class_level,
           download_count, view_count, rating, created_at, 'book'
    FROM books WHERE approval_status='approved' AND is_featured=1
    UNION ALL
    SELECT n_code, admin_id AS user_id, title, region, language,
           download_count, view_count, 0, created_at, 'newspaper'
    FROM newspapers WHERE approval_status='approved' AND is_featured=1
    ORDER BY download_count DESC LIMIT 6
");
$featuredQ->execute();
$featured = $featuredQ->fetchAll(PDO::FETCH_ASSOC);

// ── BOOKS ─────────────────────────────────────────────────────────
$booksTrendingQ = $conn->prepare("
    SELECT b_code AS id, user_id, title, author, subject, class_level AS level,
           download_count, view_count, rating, cover_image, 'book' AS type, created_at
    FROM books WHERE approval_status='approved'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY download_count DESC LIMIT 6
");
$booksTrendingQ->execute();
$booksTrending = $booksTrendingQ->fetchAll(PDO::FETCH_ASSOC);

$booksLatestQ = $conn->prepare("
    SELECT b.b_code AS id, b.user_id, b.title, b.author, b.subject, b.class_level AS level,
           b.download_count, b.view_count, b.rating, b.cover_image, 'book' AS type, b.created_at,
           COALESCE(
               (SELECT CONCAT(s.first_name,' ',s.last_name) FROM students s WHERE s.user_id=b.user_id),
               (SELECT CONCAT(t.first_name,' ',t.last_name) FROM tutors   t WHERE t.user_id=b.user_id),
               'Anonymous') AS uploader_name
    FROM books b WHERE b.approval_status='approved'
    ORDER BY b.created_at DESC LIMIT 6
");
$booksLatestQ->execute();
$booksLatest = $booksLatestQ->fetchAll(PDO::FETCH_ASSOC);

$booksFeaturedQ = $conn->prepare("
    SELECT b_code AS id, user_id, title, author, subject, class_level AS level,
           download_count, view_count, rating, cover_image, 'book' AS type, created_at
    FROM books WHERE approval_status='approved' AND is_featured=1
    ORDER BY download_count DESC LIMIT 6
");
$booksFeaturedQ->execute();
$booksFeatured = $booksFeaturedQ->fetchAll(PDO::FETCH_ASSOC);

$myBooks = [];
if ($isLoggedIn) {
    $mbQ = $conn->prepare("
        SELECT b_code AS id, user_id, title, author, subject, class_level AS level,
               download_count, view_count, rating, cover_image, 'book' AS type, created_at
        FROM books WHERE user_id=:uid AND approval_status='approved'
        ORDER BY created_at DESC LIMIT 6
    ");
    $mbQ->execute([':uid' => $user_id]);
    $myBooks = $mbQ->fetchAll(PDO::FETCH_ASSOC);
}

// ── NEWSPAPERS ────────────────────────────────────────────────────
$newsTrendingQ = $conn->prepare("
    SELECT n_code AS id, admin_id AS user_id, title, publisher AS author,
           region AS subject, language AS level,
           download_count, view_count, 0 AS rating,
           NULL AS cover_image, 'newspaper' AS type, created_at
    FROM newspapers WHERE approval_status='approved'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY download_count DESC LIMIT 6
");
$newsTrendingQ->execute();
$newsTrending = $newsTrendingQ->fetchAll(PDO::FETCH_ASSOC);

$newsLatestQ = $conn->prepare("
    SELECT n_code AS id, admin_id AS user_id, title, publisher AS author,
           region AS subject, language AS level,
           download_count, view_count, 0 AS rating,
           NULL AS cover_image, 'newspaper' AS type, created_at,
           publisher AS uploader_name
    FROM newspapers WHERE approval_status='approved'
    ORDER BY created_at DESC LIMIT 6
");
$newsLatestQ->execute();
$newsLatest = $newsLatestQ->fetchAll(PDO::FETCH_ASSOC);

$newsFeaturedQ = $conn->prepare("
    SELECT n_code AS id, admin_id AS user_id, title, publisher AS author,
           region AS subject, language AS level,
           download_count, view_count, 0 AS rating,
           NULL AS cover_image, 'newspaper' AS type, created_at
    FROM newspapers WHERE approval_status='approved' AND is_featured=1
    ORDER BY download_count DESC LIMIT 6
");
$newsFeaturedQ->execute();
$newsFeatured = $newsFeaturedQ->fetchAll(PDO::FETCH_ASSOC);
$myNews = [];

// ── FEEDBACK ─────────────────────────────────────────────────────
// FIX: Get the 10 newest feedback entries — no rating filter,
// no length filter — just the most recent 10 from real users.
// Only exclude 'closed' status (rejected/spam by admin).
$feedbackQ = $conn->prepare("
    SELECT f.feedback_id, f.message, f.subject AS fb_subject,
           f.rating, f.category, f.created_at,
           COALESCE(
               NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
               u.username,
               'Anonymous'
           ) AS user_name,
           u.profile_image
    FROM feedback f
    LEFT JOIN users    u ON u.user_id = f.user_id
    LEFT JOIN students s ON s.user_id = f.user_id
    WHERE (f.status IS NULL OR f.status != 'closed')
      AND f.message IS NOT NULL
      AND TRIM(f.message) != ''
    ORDER BY f.created_at DESC
    LIMIT 10
");
$feedbackQ->execute();
$userFeedback = $feedbackQ->fetchAll(PDO::FETCH_ASSOC);

// Build slider card data — real DB or static fallback
if (!empty($userFeedback)) {
    $sliderCards = array_map(function ($fb) {
        $ago    = timeAgo($fb['created_at']);
        $imgSrc = '';
        if (!empty($fb['profile_image'])) {
            $imgSrc = (str_starts_with($fb['profile_image'], 'http') || str_starts_with($fb['profile_image'], '/'))
                ? htmlspecialchars($fb['profile_image'])
                : htmlspecialchars('http://localhost/ScholarSwap/' . ltrim($fb['profile_image'], '/'));
        }
        $rating = max(0, min(5, (int)$fb['rating']));
        return [
            'name'     => htmlspecialchars($fb['user_name'] ?? 'Anonymous'),
            'rating'   => $rating,
            'stars'    => str_repeat('★', $rating) . str_repeat('☆', 5 - $rating),
            'subject'  => htmlspecialchars($fb['fb_subject'] ?? ''),
            'message'  => htmlspecialchars($fb['message']),
            'category' => ucfirst(str_replace('_', ' ', $fb['category'] ?? 'general')),
            'ago'      => $ago,
            'img'      => $imgSrc,
            'initials' => strtoupper(substr($fb['user_name'] ?? 'U', 0, 1)),
        ];
    }, $userFeedback);
} else {
    // Static fallback shown when no feedback exists in DB yet
    $sliderCards = [
        ['name' => 'Emily R.',   'rating' => 5, 'stars' => '★★★★★', 'subject' => 'Great study notes!',   'message' => 'ScholarSwap has been a lifesaver! I found notes for my toughest classes within minutes.', 'category' => 'General',  'ago' => '', 'img' => 'https://i.pravatar.cc/100?img=32', 'initials' => 'E'],
        ['name' => 'James T.',   'rating' => 4, 'stars' => '★★★★☆', 'subject' => 'Easy to use platform', 'message' => 'Easy to share resources. It feels like a permanent online study group for everyone.',         'category' => 'Platform', 'ago' => '', 'img' => 'https://i.pravatar.cc/100?img=45', 'initials' => 'J'],
        ['name' => 'Sophia L.',  'rating' => 5, 'stars' => '★★★★★', 'subject' => 'Amazing community',    'message' => 'The community is amazing. Got great study guides that really helped me ace my exams.',      'category' => 'General',  'ago' => '', 'img' => 'https://i.pravatar.cc/100?img=58', 'initials' => 'S'],
        ['name' => 'Michael C.', 'rating' => 5, 'stars' => '★★★★★', 'subject' => 'Highly recommended',   'message' => 'Perfect platform for students. Highly recommended to every college and school student.',    'category' => 'General',  'ago' => '', 'img' => 'https://i.pravatar.cc/100?img=71', 'initials' => 'M'],
        ['name' => 'Sarah K.',   'rating' => 4, 'stars' => '★★★★☆', 'subject' => 'Saves so much time',   'message' => 'Saved me hours of studying. Super helpful notes and very easy to use for exam prep.',       'category' => 'Notes',    'ago' => '', 'img' => 'https://i.pravatar.cc/100?img=12', 'initials' => 'S'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>ScholarSwap — Share, Swap &amp; Succeed Together</title>
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
    <meta property="og:image" content="https://www.scholarswap.com/assets/img/og-banner.jpg">
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
    <link rel="stylesheet" href="assets/css/media.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* ══ INFINITE LOOP FEEDBACK SLIDER ══════════════════════════ */
        .fb-slider-section {
            padding: 70px 0 50px;
            background: linear-gradient(135deg, #f8fafc 0%, #f0f4ff 100%);
        }

        .fb-slider-outer {
            overflow: hidden;
            position: relative;
            -webkit-mask-image: linear-gradient(to right, transparent 0%, black 8%, black 92%, transparent 100%);
            mask-image: linear-gradient(to right, transparent 0%, black 8%, black 92%, transparent 100%);
        }

        .fb-slider-track {
            display: flex;
            gap: 20px;
            width: max-content;
        }

        /* Pause on hover */
        .fb-slider-outer:hover .fb-slider-track {
            animation-play-state: paused;
        }

        @keyframes fbSlide {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(var(--fb-slide-dist));
            }
        }

        /* Card */
        .fb-s-card {
            width: 300px;
            flex-shrink: 0;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 22px 20px 18px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: box-shadow .2s;
        }

        .fb-s-card:hover {
            box-shadow: 0 8px 28px rgba(99, 102, 241, .12);
        }

        .fb-s-head {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fb-s-av {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            font-weight: 800;
            color: #fff;
            overflow: hidden;
        }

        .fb-s-av img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .fb-s-name {
            font-size: .86rem;
            font-weight: 700;
            color: #111827;
        }

        .fb-s-meta {
            font-size: .67rem;
            color: #9ca3af;
            margin-top: 1px;
        }

        .fb-s-stars {
            color: #f59e0b;
            font-size: .9rem;
            letter-spacing: 1px;
            line-height: 1;
        }

        .fb-s-subject {
            font-size: .78rem;
            font-weight: 600;
            color: #374151;
        }

        .fb-s-msg {
            font-size: .8rem;
            color: #6b7280;
            line-height: 1.65;
            font-style: italic;
            flex: 1;
        }

        /* Controls */
        .fb-slider-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 24px;
        }

        .fb-ctrl-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1.5px solid #e5e7eb;
            background: #fff;
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            transition: all .18s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        }

        .fb-ctrl-btn:hover {
            background: #6366f1;
            color: #fff;
            border-color: #6366f1;
        }

        .fb-ctrl-pause {
            padding: 0 18px;
            height: 38px;
            border-radius: 99px;
            border: 1.5px solid #e5e7eb;
            background: #fff;
            color: #6b7280;
            cursor: pointer;
            font-size: .75rem;
            font-weight: 600;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .18s;
        }

        .fb-ctrl-pause:hover {
            background: #6366f1;
            color: #fff;
            border-color: #6366f1;
        }

        /* ══ FEEDBACK FORM SECTION ═══════════════════════════════ */
        .feedback-section {
            padding: 60px 0 70px;
            background: #fff;
        }

        .feedback-form-wrap {
            max-width: 640px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .feedback-card {
            background: #f8faff;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 8px 40px rgba(99, 102, 241, .07);
            padding: 36px 40px;
        }

        @media (max-width:600px) {
            .feedback-card {
                padding: 24px 20px;
            }
        }

        .feedback-card h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 6px;
        }

        .feedback-card>p {
            font-size: .85rem;
            color: #6b7280;
            margin-bottom: 24px;
        }

        .fb-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .fb-field label {
            font-size: .76rem;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .fb-field input,
        .fb-field select,
        .fb-field textarea {
            background: #fff;
            border: 1.5px solid #e5e7eb;
            color: #111827;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: .85rem;
            font-family: inherit;
            outline: none;
            transition: border-color .2s;
            width: 100%;
        }

        .fb-field input:focus,
        .fb-field select:focus,
        .fb-field textarea:focus {
            border-color: #6366f1;
        }

        .fb-field textarea {
            resize: vertical;
            min-height: 100px;
        }

        .star-row {
            display: flex;
            gap: 6px;
        }

        .star-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #d1d5db;
            transition: color .15s;
            padding: 0;
            line-height: 1;
        }

        .star-btn.active,
        .star-btn:hover {
            color: #f59e0b;
        }

        .btn-submit-fb {
            width: 100%;
            padding: 12px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background .2s;
        }

        .btn-submit-fb:hover {
            background: #818cf8;
        }

        .btn-submit-fb:disabled {
            opacity: .5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>

    <div class="page-bg"></div>
    <div class="page-grid"></div>
    <div class="content">
        <?php include_once "admin/files/header.php"; ?>

        <!-- ① HERO -->
        <div class="hero-search-banner reveal">
            <div class="hsb-bg"></div>
            <div class="hsb-grid"></div>
            <div class="hsb-orb hsb-orb1"></div>
            <div class="hsb-orb hsb-orb2"></div>
            <div class="hsb-orb hsb-orb3"></div>
            <div class="hsb-inner">
                <div class="hsb-eyebrow"><i class="fas fa-bolt"></i> Free Academic Resources</div>
                <h1 class="hsb-h1">
                    Unlock Learning with ScholarSwap<br>
                    <em>Free PDF Notes for Schools, Colleges &amp; Exams</em>
                </h1>
                <p class="hsb-sub">ScholarSwap offers free, syllabus-based notes, textbooks and study material for school and college students — covering entrance exams, competitive tests, and interviews.</p>
                <div class="hsb-search" id="hsbSearchWrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="hsbInput" placeholder="Search your favourite notes, books, subjects…">
                    <button class="hsb-search-btn" onclick="doSearch()">Search</button>
                </div>
            </div>
        </div>

        <!-- ② TABS -->
        <div class="tabs-section reveal">
            <div class="tabs-header" id="tabsHeader">
                <?php if ($isLoggedIn): ?>
                    <button class="tab-btn active" data-tab="my-uploads">
                        <i class="fas fa-user-pen"></i> By You
                        <?php $totalMine = count($myUploads) + count($myBooks) + count($myNews); ?>
                        <?php if ($totalMine): ?><span class="tab-badge"><?php echo $totalMine; ?></span>
                        <?php else: ?><span class="tab-new">Start</span><?php endif; ?>
                    </button>
                <?php endif; ?>
                <button class="tab-btn <?php echo !$isLoggedIn ? 'active' : ''; ?>" data-tab="trending"><i class="fas fa-fire"></i> Trending</button>
                <button class="tab-btn" data-tab="latest"><i class="fas fa-clock-rotate-left"></i> Latest</button>
                <button class="tab-btn" data-tab="featured"><i class="fas fa-star"></i> Featured</button>
            </div>

            <?php
            function renderCards(array $items, string $emptyIcon, string $emptyMsg, string $viewAllHref, string $viewAllLabel, bool $showRank = false, string $sid = ''): void
            {
                static $n = 0;
                $n++;
                if (!$sid) $sid = 'rcs' . $n;
                if (empty($items)) {
                    echo '<div class="tab-empty" style="padding:40px 0"><i class="fas ' . $emptyIcon . '" style="font-size:2rem;opacity:.3;display:block;margin-bottom:10px"></i><p>' . $emptyMsg . '</p></div>';
                    echo '<div class="view-all-row"><a href="' . htmlspecialchars($viewAllHref) . '" class="btn-view-all" style="margin-top:0"><i class="fas fa-arrow-right"></i> ' . $viewAllLabel . '</a></div>';
                    return;
                }
                echo '<div class="slider-wrap"><div class="slider-viewport"><div class="tab-grid" id="' . $sid . '">';
                foreach ($items as $k => $item) {
                    $type    = $item['type'] ?? 'note';
                    $href    = readerUrl($item);
                    $tbClass = $type === 'book' ? 'rtb-book' : ($type === 'newspaper' ? 'rtb-news' : 'rtb-note');
                    $icon    = $type === 'book' ? 'fa-book'  : ($type === 'newspaper' ? 'fa-newspaper' : 'fa-file-lines');
                    $label   = $type === 'book' ? 'Book'     : ($type === 'newspaper' ? 'Newspaper' : 'Note');
                    echo '<a href="' . htmlspecialchars($href) . '" class="res-card">';
                    echo '<div class="res-card-top"><div style="display:flex;align-items:center;gap:8px;">';
                    if ($showRank) echo '<div class="trend-rank">' . ($k + 1) . '</div>';
                    echo '<span class="res-type-badge ' . $tbClass . '"><i class="fas ' . $icon . '"></i> ' . $label . '</span></div>';
                    if (!empty($item['level'])) echo '<span class="res-level">' . htmlspecialchars($item['level']) . '</span>';
                    echo '</div>';
                    if (!empty($item['subject'])) echo '<div class="res-subject">' . htmlspecialchars($item['subject']) . '</div>';
                    echo '<div class="res-title">' . htmlspecialchars($item['title']) . '</div>';
                    $af = $item['uploader_name'] ?? ($item['author_name'] ?? ($item['author'] ?? null));
                    if (!empty($af)) echo '<div class="res-author"><i class="fas fa-user-circle"></i>' . htmlspecialchars($af) . '</div>';
                    echo '<div class="res-footer"><div class="res-stats">';
                    echo '<span class="rstat"><i class="fas fa-eye"></i>' . number_format($item['view_count'] ?? 0) . '</span>';
                    echo '<span class="rstat"><i class="fas fa-download"></i>' . number_format($item['download_count'] ?? 0) . '</span>';
                    echo '</div>';
                    if (!empty($item['rating']) && $item['rating'] > 0)
                        echo '<div class="res-rating"><i class="fas fa-star"></i>' . number_format($item['rating'], 1) . '</div>';
                    echo '</div></a>';
                }
                echo '</div></div></div>';
                echo '<div class="slider-nav"><div class="slider-dots-row" id="' . $sid . '-dots"></div>';
                echo '<div style="display:flex;align-items:center;gap:10px"><div class="slider-arrows">';
                echo '<button class="sarr" id="' . $sid . '-prev"><i class="fas fa-chevron-left"></i></button>';
                echo '<button class="sarr" id="' . $sid . '-next"><i class="fas fa-chevron-right"></i></button>';
                echo '</div><a href="' . htmlspecialchars($viewAllHref) . '" class="btn-view-all" style="margin-top:0"><i class="fas fa-arrow-right"></i> ' . $viewAllLabel . '</a></div></div>';
                echo '<script>document.addEventListener("DOMContentLoaded",function(){initResSlider("' . $sid . '");});</' . 'script>';
            }

            function renderSubSwitcher(string $tabId, array $notes, array $books, array $newspapers, string $notesHref, string $booksHref, string $newsHref, bool $showRank = false): void
            {
                echo '<div class="res-type-switcher"><span class="rts-label">Show:</span>
                    <button class="rts-btn active-notes" onclick="switchSub(\'' . htmlspecialchars($tabId) . '\',\'notes\',this)"><i class="fas fa-file-lines"></i> Notes</button>
                    <button class="rts-btn" onclick="switchSub(\'' . htmlspecialchars($tabId) . '\',\'books\',this)"><i class="fas fa-book"></i> Books</button>
                    <button class="rts-btn" onclick="switchSub(\'' . htmlspecialchars($tabId) . '\',\'news\',this)"><i class="fas fa-newspaper"></i> Newspapers</button>
                </div>';
                echo '<div class="sub-pane active" id="sub-' . $tabId . '-notes">';
                renderCards($notes,      'fa-file-lines', 'No notes yet.',      $notesHref, 'View All Notes',      $showRank, $tabId . '-snotes');
                echo '</div>';
                echo '<div class="sub-pane" id="sub-' . $tabId . '-books">';
                renderCards($books,      'fa-book',       'No books yet.',      $booksHref, 'View All Books',      $showRank, $tabId . '-sbooks');
                echo '</div>';
                echo '<div class="sub-pane" id="sub-' . $tabId . '-news">';
                renderCards($newspapers, 'fa-newspaper',  'No newspapers yet.', $newsHref,  'View All Newspapers', $showRank, $tabId . '-snews');
                echo '</div>';
            }
            ?>

            <?php if ($isLoggedIn): ?>
                <div class="tab-pane active" id="pane-my-uploads">
                    <?php renderSubSwitcher(
                        'mine',
                        $myUploads,
                        $myBooks,
                        $myNews,
                        'admin/user_pages/my_uploads.php',
                        'admin/user_pages/my_uploads.php?type=book',
                        'admin/user_pages/my_uploads.php?type=newspaper'
                    ); ?>
                </div>
            <?php endif; ?>
            <div class="tab-pane <?php echo !$isLoggedIn ? 'active' : ''; ?>" id="pane-trending">
                <?php renderSubSwitcher(
                    'trending',
                    $trending,
                    $booksTrending,
                    $newsTrending,
                    'notes.php?sort=popular',
                    'books.php?sort=popular',
                    'newspaper.php?sort=popular',
                    true
                ); ?>
            </div>
            <div class="tab-pane" id="pane-latest">
                <?php renderSubSwitcher(
                    'latest',
                    $latest,
                    $booksLatest,
                    $newsLatest,
                    'notes.php?sort=newest',
                    'books.php?sort=newest',
                    'newspaper.php?sort=newest'
                ); ?>
            </div>
            <div class="tab-pane" id="pane-featured">
                <?php renderSubSwitcher(
                    'featured',
                    $featured,
                    $booksFeatured,
                    $newsFeatured,
                    'notes.php?featured=1',
                    'books.php?featured=1',
                    'newspaper.php?featured=1'
                ); ?>
            </div>
        </div>

        <!-- ③ STATS -->
        <div class="stats-strip reveal">
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-num" data-target="<?php echo $stat_students; ?>">0</div>
                    <div class="stat-lbl">Registered Students</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num" data-target="<?php echo $stat_notes; ?>">0</div>
                    <div class="stat-lbl">Notes Shared</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num" data-target="<?php echo $stat_books; ?>">0</div>
                    <div class="stat-lbl">Books Available</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num" data-target="<?php echo $stat_papers; ?>">0</div>
                    <div class="stat-lbl">Newspapers &amp; Papers</div>
                </div>
            </div>
        </div>

        <!-- ④ HOW IT WORKS -->
        <section class="section reveal">
            <div class="container">
                <div class="center">
                    <div class="section-eyebrow"><i class="fas fa-route"></i> How it works</div>
                    <h2 class="section-title">Three Simple Steps</h2>
                    <p class="section-sub">A simple way for students to collaborate, exchange resources, and grow together.</p>
                </div>
                <div class="how-grid">
                    <div class="how-card">
                        <div class="how-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-cloud-arrow-up"></i></div>
                        <h3>Share Knowledge</h3>
                        <p>Upload notes, summaries, textbooks, or study guides you've already mastered.</p>
                    </div>
                    <div class="how-card">
                        <div class="how-icon" style="background:#fef3c7;color:#b45309"><i class="fas fa-arrow-right-arrow-left"></i></div>
                        <h3>Swap Resources</h3>
                        <p>Exchange materials with other students and tutors. Access notes, books, newspapers and more.</p>
                    </div>
                    <div class="how-card">
                        <div class="how-icon" style="background:#dcfce7;color:#059669"><i class="fas fa-trophy"></i></div>
                        <h3>Succeed Together</h3>
                        <p>Learn collaboratively, save time, and perform better through shared knowledge.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ⑤ WHAT YOU CAN EXCHANGE -->
        <section class="section reveal" style="padding-top:0">
            <div class="container">
                <div class="center">
                    <div class="section-eyebrow"><i class="fas fa-layer-group"></i> Resources</div>
                    <h2 class="section-title">What You Can Exchange</h2>
                    <p class="section-sub">Share and access a wide range of academic resources from students like you.</p>
                </div>
                <div class="cats-grid">
                    <a href="notes.php" class="cat-card">
                        <div class="cat-emoji">📘</div>
                        <div>
                            <h3>Notes &amp; Summaries</h3>
                            <p>Well-organized class notes, chapter summaries, and quick revision material.</p>
                        </div>
                    </a>
                    <a href="books.php" class="cat-card">
                        <div class="cat-emoji">📚</div>
                        <div>
                            <h3>Textbooks</h3>
                            <p>Swap or share textbooks and reference books across different subjects.</p>
                        </div>
                    </a>
                    <a href="notes.php?type=guide" class="cat-card">
                        <div class="cat-emoji">🧠</div>
                        <div>
                            <h3>Study Guides</h3>
                            <p>Curated study guides and exam-focused preparation resources.</p>
                        </div>
                    </a>
                    <a href="notes.php?type=lab" class="cat-card">
                        <div class="cat-emoji">🧪</div>
                        <div>
                            <h3>Lab Material</h3>
                            <p>Lab manuals, experiment notes, and practical preparation resources.</p>
                        </div>
                    </a>
                    <a href="newspaper.php" class="cat-card">
                        <div class="cat-emoji">📝</div>
                        <div>
                            <h3>Past Papers</h3>
                            <p>Previous exam papers and sample questions to practice effectively.</p>
                        </div>
                    </a>
                    <a href="notes.php?type=asgn" class="cat-card">
                        <div class="cat-emoji">📄</div>
                        <div>
                            <h3>Assignments</h3>
                            <p>Reference assignments and project material to guide your work.</p>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- ⑥ WHY SCHOLARSWAP -->
        <section class="section why-section reveal">
            <div class="container">
                <div class="center">
                    <div class="section-eyebrow"><i class="fas fa-shield-halved"></i> Our promise</div>
                    <h2 class="section-title">Why ScholarSwap is Different</h2>
                    <p class="section-sub">Built specifically for students, with collaboration and fairness at its core.</p>
                </div>
                <div class="why-grid">
                    <div class="why-card">
                        <div class="why-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-graduation-cap"></i></div>
                        <h3>Student-Focused</h3>
                        <p>Designed exclusively for students, not advertisers or corporations.</p>
                    </div>
                    <div class="why-card">
                        <div class="why-icon" style="background:#dcfce7;color:#059669"><i class="fas fa-handshake"></i></div>
                        <h3>Fair Exchange</h3>
                        <p>Give what you can, get what you need — a trust-based sharing system.</p>
                    </div>
                    <div class="why-card">
                        <div class="why-icon" style="background:#fef3c7;color:#b45309"><i class="fas fa-circle-xmark"></i></div>
                        <h3>No Paywalls</h3>
                        <p>Access shared resources without hidden fees or subscriptions.</p>
                    </div>
                    <div class="why-card">
                        <div class="why-icon" style="background:#e0e7ff;color:#4f46e5"><i class="fas fa-lock"></i></div>
                        <h3>Safe &amp; Secure</h3>
                        <p>Your data and shared materials are protected and admin-moderated.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ⑦ INFINITE LOOP FEEDBACK SLIDER -->
        <section class="fb-slider-section reveal">
            <div class="container">
                <div class="center" style="margin-bottom:36px">
                    <div class="section-eyebrow"><i class="fas fa-quote-left"></i> What People Say</div>
                    <h2 class="section-title">Feedback from Our Users</h2>
                    <p class="section-sub">
                        <?php echo !empty($userFeedback)
                            ? 'Here\'s what students and tutors are saying about ScholarSwap.'
                            : 'Be the first to share your experience with ScholarSwap!'; ?>
                    </p>
                </div>
            </div>

            <div class="fb-slider-outer" id="fbSliderOuter">
                <div class="fb-slider-track" id="fbSliderTrack">
                    <?php foreach ($sliderCards as $card): ?>
                        <div class="fb-s-card">
                            <div class="fb-s-head">
                                <div class="fb-s-av">
                                    <?php if (!empty($card['img'])): ?>
                                        <img src="<?php echo $card['img']; ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $card['initials']; ?>'">
                                    <?php else: ?>
                                        <?php echo $card['initials']; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fb-s-name"><?php echo $card['name']; ?></div>
                                    <div class="fb-s-meta">
                                        <?php echo $card['category']; ?>
                                        <?php if (!empty($card['ago'])): ?>&nbsp;·&nbsp;<?php echo $card['ago']; ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="fb-s-stars"><?php echo $card['stars']; ?></div>
                            <?php if (!empty($card['subject'])): ?>
                                <div class="fb-s-subject"><?php echo $card['subject']; ?></div>
                            <?php endif; ?>
                            <div class="fb-s-msg">"<?php echo $card['message']; ?>"</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="fb-slider-controls">
                <button class="fb-ctrl-btn" id="fbPrev" title="Previous"><i class="fas fa-chevron-left"></i></button>
                <button class="fb-ctrl-pause" id="fbPause"><i class="fas fa-pause"></i> Pause</button>
                <button class="fb-ctrl-btn" id="fbNext" title="Next"><i class="fas fa-chevron-right"></i></button>
            </div>
        </section>

        <!-- ⑧ LEAVE FEEDBACK FORM -->
        <section class="feedback-section reveal">
            <div class="container">
                <div class="center">
                    <div class="section-eyebrow"><i class="fas fa-comments"></i> Your Voice</div>
                    <h2 class="section-title">Share Your Feedback</h2>
                    <p class="section-sub">Help us improve ScholarSwap with your honest thoughts.</p>
                </div>
                <div class="feedback-form-wrap">
                    <?php if (!$isLoggedIn): ?>
                        <div class="feedback-card" style="text-align:center;padding:32px">
                            <i class="fas fa-lock" style="font-size:2rem;color:#d1d5db;margin-bottom:12px;display:block"></i>
                            <h3 style="margin-bottom:8px">Login to leave feedback</h3>
                            <p>Join ScholarSwap to share your experience and help improve the platform.</p>
                            <a href="login.html" style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;background:#6366f1;color:#fff;padding:10px 22px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.85rem">
                                <i class="fas fa-sign-in-alt"></i> Login to continue
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="feedback-card">
                            <h3><i class="fas fa-pen-to-square" style="color:#6366f1;margin-right:8px"></i>Leave a Review</h3>
                            <p>Your feedback helps us improve ScholarSwap for everyone.</p>
                            <div class="fb-field">
                                <label>Category</label>
                                <select id="fbCategory">
                                    <option value="general">General</option>
                                    <option value="notes">Notes &amp; Study Material</option>
                                    <option value="books">Books</option>
                                    <option value="platform">Platform Experience</option>
                                    <option value="suggestion">Suggestion</option>
                                    <option value="bug">Bug Report</option>
                                </select>
                            </div>
                            <div class="fb-field">
                                <label>Subject / Title</label>
                                <input type="text" id="fbSubject" placeholder="e.g. Great study notes!" maxlength="150">
                            </div>
                            <div class="fb-field">
                                <label>Your Rating</label>
                                <div class="star-row" id="starRow">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <button class="star-btn" data-val="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)" type="button">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="fb-field">
                                <label>Message</label>
                                <textarea id="fbMessage" placeholder="Share your experience with ScholarSwap…" maxlength="800"></textarea>
                            </div>
                            <button class="btn-submit-fb" id="fbSubmitBtn" onclick="submitFeedback()" type="button">
                                <i class="fas fa-paper-plane"></i> Submit Feedback
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ⑨ CTA -->
        <section class="cta-section reveal">
            <div class="cta-inner">
                <h2>Ready to share knowledge<br>and grow together?</h2>
                <p>Join ScholarSwap today and become part of a student-powered academic community. Free forever.</p>
                <div class="cta-btns">
                    <?php if (!$isLoggedIn): ?>
                        <a href="signup.html" class="btn btn-primary"><i class="fas fa-user-plus"></i> Join ScholarSwap</a>
                        <a href="notes.php" class="btn btn-ghost"><i class="fas fa-compass"></i> Explore Resources</a>
                    <?php else: ?>
                        <a href="admin/user_pages/notes_upload.php" class="btn btn-primary"><i class="fas fa-cloud-arrow-up"></i> Upload Resources</a>
                        <a href="notes.php" class="btn btn-ghost"><i class="fas fa-compass"></i> Explore</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <section class="fb-slider-section reveal">
            <div class="container">
                <div class="center" style="margin-bottom:36px">
                    <div class="section-eyebrow"><i class="fas fa-quote-left"></i> Study Material Requests</div>
                    <h2 class="section-title">Can't find your study material?</h2>
                    <p class="section-sub">
                        Submit a request and our community will source your notes, books, or past papers — usually within 24–48 hours.
                    </p>
                </div>
            </div>
            <div class="fb-slider-controls">
                <a href="request.php" class="btn btn-ghost"><i class="fas fa-compass"></i> Request Resource</a>
            </div>
        </section>

        <!-- ⑩ FOOTER -->
        <?php include_once('admin/files/footer.php'); ?>

    </div><!-- /content -->

    <script>
        /* ══ INFINITE LOOP FEEDBACK SLIDER ══════════════════════════════
       1. JS clones original cards enough times to fill viewport
       2. CSS @keyframes fbSlide scrolls left by singleSetWidth px
       3. Loops seamlessly — no visible jump
       4. Pauses on hover (CSS), Prev/Next nudge, Pause button
    ═══════════════════════════════════════════════════════════════ */
        (function() {
            var track = document.getElementById('fbSliderTrack');
            var prevBtn = document.getElementById('fbPrev');
            var nextBtn = document.getElementById('fbNext');
            var pauseBtn = document.getElementById('fbPause');
            if (!track) return;

            var CARD_W = 300; // matches .fb-s-card width
            var GAP = 20; // matches .fb-slider-track gap
            var SPEED = 40; // px per second — lower is slower
            var paused = false;

            var origCards = Array.from(track.children);
            var origCount = origCards.length;
            if (origCount === 0) return;

            // Clone cards until track is wide enough for seamless loop
            var cloneRounds = Math.max(3, Math.ceil((window.innerWidth * 3) / ((CARD_W + GAP) * origCount)));
            for (var r = 0; r < cloneRounds; r++) {
                origCards.forEach(function(c) {
                    track.appendChild(c.cloneNode(true));
                });
            }

            var singleSetW = origCount * (CARD_W + GAP);
            var duration = singleSetW / SPEED; // animation duration in seconds

            track.style.setProperty('--fb-slide-dist', '-' + singleSetW + 'px');
            track.style.animation = 'fbSlide ' + duration + 's linear infinite';

            // Pause / Resume button
            pauseBtn.addEventListener('click', function() {
                paused = !paused;
                track.style.animationPlayState = paused ? 'paused' : 'running';
                pauseBtn.innerHTML = paused ?
                    '<i class="fas fa-play"></i> Resume' :
                    '<i class="fas fa-pause"></i> Pause';
            });

            // Nudge left/right by one card
            function nudge(dir) {
                var wasPlaying = !paused;
                track.style.animationPlayState = 'paused';

                var matrix = window.getComputedStyle(track).transform;
                var currentX = (matrix && matrix !== 'none') ? (parseFloat(matrix.split(',')[4]) || 0) : 0;
                var newX = currentX + dir * (CARD_W + GAP);

                // Keep within cloned range
                if (newX > 0) newX = -(singleSetW * (cloneRounds - 1));
                if (newX < -(singleSetW * cloneRounds)) newX = 0;

                track.style.transition = 'transform 0.32s ease';
                track.style.transform = 'translateX(' + newX + 'px)';

                setTimeout(function() {
                    track.style.transition = '';
                    if (wasPlaying && !paused) {
                        var delay = Math.abs(newX) / SPEED;
                        track.style.animation = 'none';
                        track.offsetHeight; // force reflow
                        track.style.animation = 'fbSlide ' + duration + 's linear infinite';
                        track.style.animationDelay = '-' + delay + 's';
                        track.style.animationPlayState = 'running';
                    }
                }, 350);
            }

            prevBtn.addEventListener('click', function() {
                nudge(1);
            });
            nextBtn.addEventListener('click', function() {
                nudge(-1);
            });
        })();

        /* ══ FEEDBACK FORM ══ */
        var fbRating = 0;

        function setRating(val) {
            fbRating = val;
            document.querySelectorAll('.star-btn').forEach(function(btn, i) {
                btn.classList.toggle('active', i < val);
            });
        }
        async function submitFeedback() {
            var category = document.getElementById('fbCategory')?.value;
            var subject = document.getElementById('fbSubject')?.value.trim();
            var message = document.getElementById('fbMessage')?.value.trim();
            var btn = document.getElementById('fbSubmitBtn');
            if (!fbRating) return sw('warning', 'Rating required', 'Please select a star rating.');
            if (!subject) return sw('warning', 'Subject required', 'Please add a short subject.');
            if (!message) return sw('warning', 'Message required', 'Please write your feedback.');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
            var fd = new FormData();
            fd.append('category', category);
            fd.append('subject', subject);
            fd.append('message', message);
            fd.append('rating', fbRating);
            try {
                var res = await fetch('admin/user_pages/auth/submit_feedback.php', {
                    method: 'POST',
                    body: fd
                });
                var data = await res.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Thank you!',
                        text: 'Your feedback has been submitted.',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    document.getElementById('fbSubject').value = '';
                    document.getElementById('fbMessage').value = '';
                    fbRating = 0;
                    document.querySelectorAll('.star-btn').forEach(function(b) {
                        b.classList.remove('active');
                    });
                } else sw('error', 'Failed', data.message || 'Could not submit feedback.');
            } catch (e) {
                sw('error', 'Error', 'Could not connect. Please try again.');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';
        }

        /* ══ RESOURCE CARD SLIDER ══ */
        var _sliders = {};

        function initResSlider(sid) {
            var track = document.getElementById(sid);
            if (!track) return;
            var dotsEl = document.getElementById(sid + '-dots');
            var prevBtn = document.getElementById(sid + '-prev');
            var nextBtn = document.getElementById(sid + '-next');
            var cards = track.querySelectorAll('.res-card');
            if (!cards.length) return;
            var perView, idx = 0,
                total;

            function getPerView() {
                var w = window.innerWidth;
                return w <= 500 ? 1 : w <= 768 ? 2 : w <= 1100 ? 3 : 4;
            }

            function buildDots() {
                if (!dotsEl) return;
                dotsEl.innerHTML = '';
                for (var i = 0; i < total; i++) {
                    (function(i) {
                        var d = document.createElement('button');
                        d.className = 'sdot2' + (i === 0 ? ' on' : '');
                        d.setAttribute('aria-label', 'Slide ' + (i + 1));
                        d.onclick = function() {
                            goto(i);
                        };
                        dotsEl.appendChild(d);
                    })(i);
                }
            }

            function goto(n) {
                idx = Math.max(0, Math.min(n, total - 1));
                var cardW = cards[0].offsetWidth + 16;
                track.style.transform = 'translateX(-' + (idx * cardW) + 'px)';
                if (dotsEl) dotsEl.querySelectorAll('.sdot2').forEach(function(d, i) {
                    d.classList.toggle('on', i === idx);
                });
                if (prevBtn) prevBtn.disabled = idx === 0;
                if (nextBtn) nextBtn.disabled = idx >= total - 1;
            }

            function setup() {
                perView = getPerView();
                total = Math.max(1, cards.length - perView + 1);
                idx = 0;
                buildDots();
                goto(0);
            }
            if (prevBtn) prevBtn.onclick = function() {
                goto(idx - 1);
            };
            if (nextBtn) nextBtn.onclick = function() {
                goto(idx + 1);
            };
            var tx = 0;
            track.addEventListener('touchstart', function(e) {
                tx = e.touches[0].clientX;
            }, {
                passive: true
            });
            track.addEventListener('touchend', function(e) {
                var d = tx - e.changedTouches[0].clientX;
                if (Math.abs(d) > 40) goto(d > 0 ? idx + 1 : idx - 1);
            }, {
                passive: true
            });
            setup();
            window.addEventListener('resize', setup);
            _sliders[sid] = {
                goto: goto,
                setup: setup
            };
        }

        /* ══ TAB SWITCHER ══ */
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                document.querySelectorAll('.tab-pane').forEach(function(p) {
                    p.classList.remove('active');
                });
                btn.classList.add('active');
                var pane = document.getElementById('pane-' + btn.dataset.tab);
                if (pane) {
                    pane.classList.add('active');
                    pane.querySelectorAll('.tab-grid').forEach(function(g) {
                        if (_sliders[g.id]) _sliders[g.id].setup();
                    });
                }
            });
        });

        /* ══ SUB-TYPE SWITCHER ══ */
        function switchSub(tabId, type, clickedBtn) {
            ['notes', 'books', 'news'].forEach(function(t) {
                var el = document.getElementById('sub-' + tabId + '-' + t);
                if (el) el.classList.remove('active');
            });
            clickedBtn.closest('.res-type-switcher').querySelectorAll('.rts-btn').forEach(function(b) {
                b.classList.remove('active-notes', 'active-books', 'active-news');
            });
            var target = document.getElementById('sub-' + tabId + '-' + type);
            if (target) {
                target.classList.add('active');
                target.querySelectorAll('.tab-grid').forEach(function(g) {
                    if (_sliders[g.id]) _sliders[g.id].setup();
                });
            }
            clickedBtn.classList.add(type === 'notes' ? 'active-notes' : type === 'books' ? 'active-books' : 'active-news');
        }

        /* ══ HERO SEARCH ══ */
        function doSearch() {
            var q = document.getElementById('hsbInput').value.trim();
            if (q) window.location.href = 'search.php?s=' + encodeURIComponent(q);
        }
        document.getElementById('hsbInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') doSearch();
        });

        /* ══ SCROLL REVEAL ══ */
        var ro = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    ro.unobserve(e.target);
                }
            });
        }, {
            threshold: .1
        });
        document.querySelectorAll('.reveal').forEach(function(el) {
            ro.observe(el);
        });

        /* ══ COUNTER ANIMATION ══ */
        function animateCount(el) {
            var target = parseFloat(el.dataset.target),
                isFloat = !Number.isInteger(target),
                duration = 1800,
                start = performance.now();

            function step(now) {
                var p = Math.min((now - start) / duration, 1),
                    ease = 1 - Math.pow(1 - p, 3),
                    val = target * ease;
                el.textContent = isFloat ? val.toFixed(1) : Math.floor(val).toLocaleString();
                if (p < 1) requestAnimationFrame(step);
                else el.textContent = isFloat ? target.toFixed(1) : target.toLocaleString();
            }
            requestAnimationFrame(step);
        }
        var cObs = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) {
                if (e.isIntersecting) {
                    animateCount(e.target);
                    cObs.unobserve(e.target);
                }
            });
        }, {
            threshold: .5
        });
        document.querySelectorAll('.stat-num[data-target]').forEach(function(el) {
            cObs.observe(el);
        });

        /* ══ LOGOUT FLASH ══ */
        var sp = new URLSearchParams(window.location.search).get('s');
        if (sp === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Logged out!',
                text: 'You have been logged out successfully.',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            });
            setTimeout(function() {
                history.replaceState(null, '', 'index.php');
            }, 500);
        }

        function sw(icon, title, text) {
            return Swal.fire({
                icon: icon,
                title: title,
                text: text,
                iconColor: icon === 'error' ? '#ef4444' : icon === 'warning' ? '#f59e0b' : '#10b981'
            });
        }
    </script>
</body>

</html>