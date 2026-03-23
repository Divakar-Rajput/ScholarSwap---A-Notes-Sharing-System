<?php
require_once 'admin/config/connection.php';
require_once 'admin/encryption.php';
require_once('admin/auth_check.php');

$myId = (int)$_SESSION['user_id'];
$q    = trim($_GET['q'] ?? $_GET['s'] ?? '');
$type = $_GET['type'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$like = '%' . $q . '%';

$notes = $books = $papers = $people = [];

// ── Safe encrypt helper ──────────────────────────────────────────
function safeEncrypt($value): string
{
    if ($value === null || $value === '') return '';
    return encryptId((string)$value);
}

if ($q !== '') {

    // ── Notes ────────────────────────────────────────────────────
    if ($type === 'all' || $type === 'note') {
        $st = $conn->prepare("
            SELECT 'note' AS rtype, n_code AS rid, user_id,
                   title, subject, description, document_type,
                   download_count, view_count, rating, is_featured, created_at,
                   COALESCE(
                       (SELECT CONCAT(s.first_name,' ',s.last_name) FROM students s WHERE s.user_id = n.user_id),
                       (SELECT CONCAT(t.first_name,' ',t.last_name) FROM tutors   t WHERE t.user_id = n.user_id),
                       'Anonymous'
                   ) AS uploader_name,
                   course, '' AS author, '' AS publisher,
                   '' AS language, '' AS publication_date
            FROM notes n
            WHERE approval_status = 'approved'
              AND (title LIKE :q OR subject LIKE :q2 OR description LIKE :q3 OR course LIKE :q4)
        ");
        $st->execute([':q' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);
        $notes = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Books ────────────────────────────────────────────────────
    if ($type === 'all' || $type === 'book') {
        $st = $conn->prepare("
            SELECT 'book' AS rtype, b_code AS rid, user_id,
                   title, subject, description, document_type,
                   download_count, view_count, rating, is_featured, created_at,
                   COALESCE(
                       (SELECT CONCAT(s.first_name,' ',s.last_name) FROM students s WHERE s.user_id = b.user_id),
                       (SELECT CONCAT(t.first_name,' ',t.last_name) FROM tutors   t WHERE t.user_id = b.user_id),
                       'Anonymous'
                   ) AS uploader_name,
                   '' AS course, author, '' AS publisher,
                   '' AS language, '' AS publication_date
            FROM books b
            WHERE approval_status = 'approved'
              AND (title LIKE :q OR subject LIKE :q2 OR description LIKE :q3 OR author LIKE :q4)
        ");
        $st->execute([':q' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);
        $books = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Newspapers ───────────────────────────────────────────────
    if ($type === 'all' || $type === 'newspaper') {
        $st = $conn->prepare("
            SELECT 'newspaper' AS rtype, n_code AS rid, admin_id AS user_id,
                   title, '' AS subject, '' AS description, 'newspaper' AS document_type,
                   download_count, view_count, 0 AS rating, is_featured, created_at,
                   'Admin' AS uploader_name,
                   '' AS course, '' AS author, publisher,
                   COALESCE(language,'') AS language, publication_date
            FROM newspapers
            WHERE approval_status = 'approved'
              AND (title LIKE :q OR publisher LIKE :q2 OR language LIKE :q3 OR region LIKE :q4)
        ");
        $st->execute([':q' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);
        $papers = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── People ───────────────────────────────────────────────────
    // ── People ───────────────────────────────────────────────────
    if ($type === 'all' || $type === 'people') {
        try {
            $st = $conn->prepare("
                SELECT
                    u.user_id,
                    u.username,
                    u.email,
                    u.role,
                    u.profile_image,
                    u.is_verified,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(
                            COALESCE(s.first_name,''), ' ',
                            COALESCE(s.last_name,'')
                        )),''),
                        NULLIF(TRIM(CONCAT(
                            COALESCE(t.first_name,''), ' ',
                            COALESCE(t.last_name,'')
                        )),''),
                        u.username
                    ) AS full_name,
                    COALESCE(s.institution,          t.institution,     '') AS institution,
                    COALESCE(s.subjects_of_interest, t.subjects_taught, '') AS subjects,
                    COALESCE(s.state,                t.state,           '') AS state,
                    COALESCE(s.district,             t.district,        '') AS district,
                    COALESCE(t.qualification,        '')                    AS qualification,
                    COALESCE(t.experience_years,     0)                    AS experience_years,
                    COALESCE(s.bio,                  t.bio,             '') AS bio,
                    (SELECT COUNT(*) FROM notes  n2 WHERE n2.user_id = u.user_id AND n2.approval_status='approved') AS note_count,
                    (SELECT COUNT(*) FROM books  b2 WHERE b2.user_id = u.user_id AND b2.approval_status='approved') AS book_count,
                    (SELECT COUNT(*) FROM follows f  WHERE f.following_id  = u.user_id)   AS follower_count,
                    (SELECT COUNT(*) FROM follows f2 WHERE f2.follower_id  = :myId
                                                       AND f2.following_id = u.user_id)   AS i_follow
                FROM users u
                LEFT JOIN students s ON s.user_id = u.user_id
                LEFT JOIN tutors   t ON t.user_id = u.user_id
                WHERE u.role IN ('student', 'tutor')
                  AND (
                        u.username                           LIKE :q
                     OR u.email                             LIKE :q2
                     OR s.first_name                        LIKE :q3
                     OR s.last_name                         LIKE :q4
                     OR t.first_name                        LIKE :q5
                     OR t.last_name                         LIKE :q6
                     OR COALESCE(s.institution,'')          LIKE :q7
                     OR COALESCE(t.institution,'')          LIKE :q8
                     OR COALESCE(s.subjects_of_interest,'') LIKE :q9
                     OR COALESCE(t.subjects_taught,'')      LIKE :q10
                     OR COALESCE(t.qualification,'')        LIKE :q11
                     OR COALESCE(s.state,'')                LIKE :q12
                     OR COALESCE(t.state,'')                LIKE :q13
                     OR COALESCE(s.district,'')             LIKE :q14
                     OR COALESCE(t.district,'')             LIKE :q15
                     OR COALESCE(s.bio,'')                  LIKE :q16
                     OR COALESCE(t.bio,'')                  LIKE :q17
                  )
                ORDER BY follower_count DESC, note_count DESC
                LIMIT 50
            ");
            $st->execute([
                ':myId'  => $myId,
                ':q'    => $like,
                ':q2'  => $like,
                ':q3'  => $like,
                ':q4'   => $like,
                ':q5'  => $like,
                ':q6'  => $like,
                ':q7'   => $like,
                ':q8'  => $like,
                ':q9'  => $like,
                ':q10'  => $like,
                ':q11' => $like,
                ':q12' => $like,
                ':q13'  => $like,
                ':q14' => $like,
                ':q15' => $like,
                ':q16'  => $like,
                ':q17' => $like,
            ]);
            $people = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[search.php] People query error: ' . $e->getMessage());
            $people = [];
        }
    }
}

// ── Sort resources (not people) ──────────────────────────────────
$all = array_merge($notes, $books, $papers);
usort($all, function ($a, $b) use ($sort) {
    if ($sort === 'popular')   return (int)$b['view_count']     - (int)$a['view_count'];
    if ($sort === 'downloads') return (int)$b['download_count'] - (int)$a['download_count'];
    if ($sort === 'rated')     return (float)$b['rating']       - (float)$a['rating'];
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$nCount = count($notes);
$bCount = count($books);
$pCount = count($papers);
$uCount = count($people);
$total  = count($all);

// ── Helpers ──────────────────────────────────────────────────────
function typeIcon($t)
{
    return match ($t) {
        'note' => 'fa-file-lines',
        'book' => 'fa-book',
        'newspaper' => 'fa-newspaper',
        default => 'fa-file'
    };
}
function typeLabel($t)
{
    return match ($t) {
        'note' => 'Note',
        'book' => 'Book',
        'newspaper' => 'Newspaper',
        default => 'Resource'
    };
}
function typeColor($t)
{
    return match ($t) {
        'note' => 'rc-note',
        'book' => 'rc-book',
        'newspaper' => 'rc-paper',
        default => 'rc-note'
    };
}

// FIX: use encrypted codes instead of raw integer IDs
function readUrl($r): string
{
    $eR = safeEncrypt($r['rid']);
    $eU = safeEncrypt($r['user_id']);
    $eT = safeEncrypt($r['rtype']);
    if (!$eR || !$eU || !$eT) return '#';
    return 'notes_reader.php?r=' . urlencode($eR) . '&u=' . urlencode($eU) . '&t=' . urlencode($eT);
}

function profileUrl($userId): string
{
    $enc = safeEncrypt($userId);
    if (!$enc) return '#';
    return 'admin/user_pages/userprofile.php?u=' . urlencode($enc);
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
    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        }

        /* ── People cards ─────────────────────────────────────── */
        .people-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .person-card {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .09);
            border-radius: 16px;
            padding: 20px 18px 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            cursor: pointer;
            transition: transform .18s, box-shadow .18s, border-color .18s;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 1px 4px rgba(15, 23, 42, .06);
        }

        .person-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, .10);
            border-color: rgba(37, 99, 235, .2);
        }

        .pc-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pc-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, #4f46e5, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            overflow: hidden;
            border: 2px solid rgba(15, 23, 42, .08);
            position: relative;
        }

        .pc-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .pc-verified {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #059669;
            border: 2px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .42rem;
            color: #fff;
        }

        .pc-info {
            flex: 1;
            min-width: 0;
        }

        .pc-name {
            font-size: .92rem;
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pc-username {
            font-size: .72rem;
            color: #94a3b8;
            margin-top: 1px;
        }

        .pc-role {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 5px;
            padding: 2px 9px;
            border-radius: 99px;
            font-size: .63rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .pc-role-student {
            background: #dbeafe;
            color: #2563eb;
            border: 1px solid rgba(37, 99, 235, .2);
        }

        .pc-role-tutor {
            background: #f0fdfa;
            color: #0d9488;
            border: 1px solid rgba(13, 148, 136, .2);
        }

        .pc-institution {
            font-size: .72rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pc-institution i {
            color: #94a3b8;
            font-size: .62rem;
            flex-shrink: 0;
        }

        .pc-stats {
            display: flex;
            gap: 14px;
            border-top: 1px solid rgba(15, 23, 42, .06);
            padding-top: 10px;
        }

        .pc-stat {
            text-align: center;
            flex: 1;
        }

        .pc-stat-num {
            font-size: .95rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        .pc-stat-lbl {
            font-size: .6rem;
            color: #94a3b8;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .pc-subjects {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .pc-tag {
            font-size: .62rem;
            padding: 2px 8px;
            border-radius: 99px;
            background: #e0e7ff;
            color: #4f46e5;
            border: 1px solid rgba(79, 70, 229, .18);
        }

        .pc-tag-more {
            background: #f1f5f9;
            color: #64748b;
            border-color: #e2e8f0;
        }

        /* People section header */
        .section-head {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .82rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(15, 23, 42, .08);
        }

        .section-head i {
            color: #6366f1;
        }

        .section-head .sh-count {
            margin-left: auto;
            font-size: .68rem;
            font-weight: 600;
            background: #e0e7ff;
            color: #4f46e5;
            padding: 2px 8px;
            border-radius: 99px;
        }
    </style>
</head>

<body>
    <div class="page-grid"></div>
    <div class="content">

        <?php include_once 'admin/files/header.php'; ?>

        <div class="search-hero">
            <div class="hero-inner">
                <?php if ($q): ?>
                    <div class="hero-label"><i class="fas fa-magnifying-glass"></i> Search Results</div>
                    <h1 class="hero-title">Results for <em>"<?php echo htmlspecialchars($q); ?>"</em></h1>
                    <p class="hero-sub">
                        <?php
                        $parts = [];
                        if ($total > 0)  $parts[] = $total  . ' resource' . ($total  !== 1 ? 's' : '');
                        if ($uCount > 0) $parts[] = $uCount . ' people';
                        echo $parts ? implode(' &nbsp;·&nbsp; ', $parts) . ' found'
                            : 'No results found — try different keywords';
                        ?>
                    </p>
                <?php else: ?>
                    <div class="hero-label"><i class="fas fa-search"></i> Search ScholarSwap</div>
                    <h1 class="hero-title">Find any <em>resource</em> or <em>person</em></h1>
                    <p class="hero-sub">Search notes, books, newspapers — and the people who upload them</p>
                <?php endif; ?>

                <form method="GET" action="search.php">
                    <?php if ($type !== 'all'): ?>
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                    <?php endif; ?>
                    <div class="big-search">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
                            placeholder="Search notes, books, and users by username, name..." autofocus>
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                    </div>
                </form>

                <?php
                $tabs = [
                    'all'       => ['All',        $total + $uCount, 'fa-layer-group'],
                    'note'      => ['Notes',       $nCount,          'fa-file-lines'],
                    'book'      => ['Books',        $bCount,          'fa-book'],
                    'newspaper' => ['Newspapers',  $pCount,          'fa-newspaper'],
                    'people'    => ['People',      $uCount,          'fa-users'],
                ];
                ?>
                <div class="type-tabs">
                    <?php foreach ($tabs as $key => [$label, $cnt, $icon]): ?>
                        <a href="search.php?q=<?php echo urlencode($q); ?>&type=<?php echo $key; ?>&sort=<?php echo htmlspecialchars($sort); ?>"
                            class="type-tab <?php echo $type === $key ? 'active' : ''; ?>">
                            <i class="fas <?php echo $icon; ?>"></i>
                            <?php echo $label; ?>
                            <span class="tc"><?php echo $cnt; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
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
                <span class="cur">Search</span>
            </div>
        </div>

        <?php if ($q): ?>
            <div class="layout">
                <aside class="sidebar">
                    <div class="sidebar-card">
                        <div class="s-title">
                            <span><i class="fas fa-sliders"></i> Filters</span>
                            <span id="clearFilters">Clear all</span>
                        </div>
                        <div class="filter-section">
                            <h4>Resource Type</h4>
                            <label class="filter-opt"><input type="checkbox" data-filter="rtype" value="note"> <i class="fas fa-file-lines" style="font-size:.7rem;color:var(--blue)"></i> Notes</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rtype" value="book"> <i class="fas fa-book" style="font-size:.7rem;color:var(--green)"></i> Books</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rtype" value="newspaper"> <i class="fas fa-newspaper" style="font-size:.7rem;color:var(--gold)"></i> Newspapers</label>
                        </div>
                        <div class="filter-section">
                            <h4>Subject</h4>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="mathematics"> Mathematics</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="physics"> Physics</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="chemistry"> Chemistry</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="biology"> Biology</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="computer science"> Computer Science</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="economics"> Economics</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="english"> English</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="subject" value="history"> History</label>
                        </div>
                        <div class="filter-section">
                            <h4>Language</h4>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="english"> English</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="hindi"> Hindi</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="bengali"> Bengali</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="marathi"> Marathi</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="tamil"> Tamil</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="language" value="telugu"> Telugu</label>
                        </div>
                        <div class="filter-section">
                            <h4>Rating</h4>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="5"><span class="star-filter">★★★★★</span>&nbsp;5 Stars</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="4"><span class="star-filter">★★★★☆</span>&nbsp;4+ Stars</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="rating" value="3"><span class="star-filter">★★★☆☆</span>&nbsp;3+ Stars</label>
                        </div>
                        <div class="filter-section">
                            <h4>Downloads</h4>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="1000"> 1000+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="500"> 500+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="100"> 100+</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="10"> 10+</label>
                        </div>

                        <!-- People-specific filters — only visible on people/all tab -->
                        <div class="filter-section" id="peopleFilters"
                            style="<?php echo ($type === 'people' || $type === 'all') ? '' : 'display:none'; ?>">
                            <h4><i class="fas fa-users" style="font-size:.7rem;color:#6366f1;margin-right:4px"></i> People</h4>
                            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px">Role</div>
                            <label class="filter-opt">
                                <input type="checkbox" data-filter="prole" value="student">
                                <i class="fas fa-graduation-cap" style="font-size:.7rem;color:#2563eb"></i> Students
                            </label>
                            <label class="filter-opt">
                                <input type="checkbox" data-filter="prole" value="tutor">
                                <i class="fas fa-chalkboard-user" style="font-size:.7rem;color:#0d9488"></i> Tutors
                            </label>
                            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin:10px 0 6px">State</div>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="maharashtra"> Maharashtra</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="uttar pradesh"> Uttar Pradesh</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="delhi"> Delhi</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="karnataka"> Karnataka</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="tamil nadu"> Tamil Nadu</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="gujarat"> Gujarat</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="rajasthan"> Rajasthan</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="west bengal"> West Bengal</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="bihar"> Bihar</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="pstate" value="telangana"> Telangana</label>
                            <div style="font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin:10px 0 6px">Uploads</div>
                            <label class="filter-opt"><input type="checkbox" data-filter="puploads" value="10"> 10+ uploads</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="puploads" value="5"> 5+ uploads</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="puploads" value="1"> Has uploads</label>
                        </div>
                    </div>
                </aside>

                <main>
                    <!-- ── People section (shown in 'all' or 'people' tab) ── -->
                    <?php if (($type === 'all' || $type === 'people') && !empty($people)): ?>
                        <div id="peopleSection">
                            <div class="section-head">
                                <i class="fas fa-users"></i> People
                                <span class="sh-count"><?php echo $uCount; ?> found</span>
                            </div>
                            <div class="people-grid" id="peopleGrid">
                                <?php foreach ($people as $p):
                                    $pInitials = strtoupper(
                                        substr(explode(' ', $p['full_name'])[0], 0, 1) .
                                            substr(explode(' ', $p['full_name'])[1] ?? '', 0, 1)
                                    );
                                    $pImg = '';
                                    if (!empty($p['profile_image'])) {
                                        $pImg = (str_starts_with($p['profile_image'], 'http') || str_starts_with($p['profile_image'], '/'))
                                            ? htmlspecialchars($p['profile_image'])
                                            : htmlspecialchars('http://localhost/ScholarSwap/' . ltrim($p['profile_image'], '/'));
                                    }
                                    $pUrl  = profileUrl($p['user_id']);
                                    $sArr  = array_filter(array_slice(array_map('trim', explode(',', $p['subjects'])), 0, 3));
                                    $sAll  = array_filter(array_map('trim', explode(',', $p['subjects'])));
                                    $sMore = count($sAll) - count($sArr);
                                    $roleCls  = $p['role'] === 'tutor' ? 'pc-role-tutor' : 'pc-role-student';
                                    $roleIcon = $p['role'] === 'tutor' ? 'fa-chalkboard-user' : 'fa-graduation-cap';
                                    $totalUploads = (int)$p['note_count'] + (int)$p['book_count'];
                                ?>
                                    <a class="person-card" href="<?php echo $pUrl; ?>"
                                        data-prole="<?php echo htmlspecialchars($p['role']); ?>"
                                        data-pstate="<?php echo htmlspecialchars(strtolower($p['state'] ?? '')); ?>"
                                        data-puploads="<?php echo $totalUploads; ?>">
                                        <div class="pc-top">
                                            <div class="pc-avatar">
                                                <?php if ($pImg): ?>
                                                    <img src="<?php echo $pImg; ?>"
                                                        alt="<?php echo htmlspecialchars($p['full_name']); ?>"
                                                        onerror="this.remove()">
                                                <?php else: ?>
                                                    <?php echo $pInitials; ?>
                                                <?php endif; ?>
                                                <?php if ($p['is_verified'] ?? 0): ?>
                                                    <div class="pc-verified"><i class="fas fa-check"></i></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="pc-info">
                                                <div class="pc-name"><?php echo htmlspecialchars($p['full_name']); ?></div>
                                                <div class="pc-username">@<?php echo htmlspecialchars($p['username']); ?></div>
                                                <span class="pc-role <?php echo $roleCls; ?>">
                                                    <i class="fas <?php echo $roleIcon; ?>"></i>
                                                    <?php echo ucfirst($p['role']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Institution -->
                                        <?php if (!empty($p['institution'])): ?>
                                            <div class="pc-institution">
                                                <i class="fas fa-building-columns"></i>
                                                <?php echo htmlspecialchars($p['institution']); ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- State / qualification row -->
                                        <?php if (!empty($p['state']) || !empty($p['qualification'])): ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:8px;font-size:.72rem;color:#64748b">
                                                <?php if (!empty($p['state'])): ?>
                                                    <span style="display:flex;align-items:center;gap:4px">
                                                        <i class="fas fa-location-dot" style="font-size:.62rem;color:#94a3b8"></i>
                                                        <?php echo htmlspecialchars($p['state']); ?>
                                                        <?php if (!empty($p['district'])): ?>
                                                            , <?php echo htmlspecialchars($p['district']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($p['qualification'])): ?>
                                                    <span style="display:flex;align-items:center;gap:4px">
                                                        <i class="fas fa-certificate" style="font-size:.62rem;color:#94a3b8"></i>
                                                        <?php echo htmlspecialchars($p['qualification']); ?>
                                                        <?php if ($p['experience_years'] > 0): ?>
                                                            &nbsp;·&nbsp; <?php echo (int)$p['experience_years']; ?>yr exp
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Subject tags -->
                                        <?php if (!empty($sArr)): ?>
                                            <div class="pc-subjects">
                                                <?php foreach ($sArr as $s): ?>
                                                    <span class="pc-tag"><?php echo htmlspecialchars($s); ?></span>
                                                <?php endforeach; ?>
                                                <?php if ($sMore > 0): ?>
                                                    <span class="pc-tag pc-tag-more">+<?php echo $sMore; ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="pc-stats">
                                            <div class="pc-stat">
                                                <div class="pc-stat-num"><?php echo number_format($p['follower_count']); ?></div>
                                                <div class="pc-stat-lbl">Followers</div>
                                            </div>
                                            <div class="pc-stat">
                                                <div class="pc-stat-num"><?php echo number_format($p['note_count']); ?></div>
                                                <div class="pc-stat-lbl">Notes</div>
                                            </div>
                                            <div class="pc-stat">
                                                <div class="pc-stat-num"><?php echo number_format($p['book_count']); ?></div>
                                                <div class="pc-stat-lbl">Books</div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ── Resources section ── -->
                    <?php if ($type !== 'people'): ?>

                        <div class="stat-strip">
                            <span class="stat-pill sp-b"><i class="fas fa-file-lines"></i> <?php echo $nCount; ?> Notes</span>
                            <span class="stat-pill sp-g"><i class="fas fa-book"></i> <?php echo $bCount; ?> Books</span>
                            <span class="stat-pill sp-a"><i class="fas fa-newspaper"></i> <?php echo $pCount; ?> Newspapers</span>
                            <span class="stat-pill sp-t"><i class="fas fa-layer-group"></i> <?php echo $total; ?> Total</span>
                        </div>

                        <div class="results-head">
                            <p class="results-count">
                                Showing <strong id="showFrom">0</strong>–<strong id="showTo">0</strong>
                                of <strong id="showTotal">0</strong>
                                result<?php echo $total !== 1 ? 's' : ''; ?>
                                for <strong>"<?php echo htmlspecialchars($q); ?>"</strong>
                            </p>
                            <select class="sort-sel" id="sortSel" onchange="changeSort(this.value)">
                                <option value="newest" <?php echo $sort === 'newest'    ? 'selected' : ''; ?>>Newest First</option>
                                <option value="popular" <?php echo $sort === 'popular'   ? 'selected' : ''; ?>>Most Popular</option>
                                <option value="downloads" <?php echo $sort === 'downloads' ? 'selected' : ''; ?>>Most Downloads</option>
                                <option value="rated" <?php echo $sort === 'rated'     ? 'selected' : ''; ?>>Highest Rated</option>
                            </select>
                        </div>

                        <?php if ($total === 0): ?>
                            <div class="no-results">
                                <div class="no-results-icon"><i class="fas fa-magnifying-glass"></i></div>
                                <h3>No resources for "<?php echo htmlspecialchars($q); ?>"</h3>
                                <p>Try different keywords or browse all resources below</p>
                                <div class="suggestions">
                                    <a href="notes.php" class="sug-pill"><i class="fas fa-file-lines"></i> Browse Notes</a>
                                    <a href="books.php" class="sug-pill"><i class="fas fa-book"></i> Browse Books</a>
                                    <a href="newspaper.php" class="sug-pill"><i class="fas fa-newspaper"></i> Browse Newspapers</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($people) && $type === 'all'): ?>
                                <div class="section-head" style="margin-top:8px">
                                    <i class="fas fa-layer-group"></i> Resources
                                    <span class="sh-count"><?php echo $total; ?> found</span>
                                </div>
                            <?php endif; ?>

                            <div class="results-list" id="resultsList">
                                <?php foreach ($all as $r):
                                    $rtype    = $r['rtype'];
                                    $colorCls = typeColor($rtype);
                                    $stars    = min(5, max(0, (int)($r['rating'] ?? 0)));
                                    $starStr  = str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
                                    $subj     = strtolower(trim($r['subject'] ?? ''));
                                    $lang     = strtolower(trim($r['language'] ?? ''));
                                    $dl       = (int)($r['download_count'] ?? 0);
                                    $url      = readUrl($r);
                                ?>
                                    <div class="rc <?php echo $colorCls; ?>"
                                        data-rtype="<?php echo htmlspecialchars($rtype); ?>"
                                        data-subject="<?php echo htmlspecialchars($subj); ?>"
                                        data-language="<?php echo htmlspecialchars($lang); ?>"
                                        data-rating="<?php echo $stars; ?>"
                                        data-downloads="<?php echo $dl; ?>"
                                        onclick="<?php echo $url !== '#' ? "window.location.href='$url'" : ''; ?>">

                                        <div class="rc-strip"></div>
                                        <div class="rc-icon-col">
                                            <div class="rc-icon"><i class="fas <?php echo typeIcon($rtype); ?>"></i></div>
                                        </div>
                                        <div class="rc-body">
                                            <div class="rc-top">
                                                <span class="rc-badge"><?php echo typeLabel($rtype); ?></span>
                                                <?php if (!empty($r['subject'])): ?><span class="rc-subject"><?php echo htmlspecialchars($r['subject']); ?></span><?php endif; ?>
                                                <?php if (!empty($r['author'])): ?><span class="rcm" style="font-size:.72rem"><i class="fas fa-pen-nib"></i><?php echo htmlspecialchars($r['author']); ?></span><?php endif; ?>
                                                <?php if ($r['is_featured'] ?? 0): ?><span class="rc-featured"><i class="fas fa-star"></i> Featured</span><?php endif; ?>
                                            </div>
                                            <div class="rc-title"><?php echo htmlspecialchars($r['title']); ?></div>
                                            <?php if (!empty($r['description'])): ?>
                                                <div class="rc-desc"><?php echo htmlspecialchars($r['description']); ?></div>
                                            <?php endif; ?>
                                            <div class="rc-meta">
                                                <span class="rcm"><i class="fas fa-user-circle"></i><?php echo htmlspecialchars($r['uploader_name']); ?></span>
                                                <span class="rcm"><i class="fas fa-eye"></i><?php echo number_format($r['view_count'] ?? 0); ?></span>
                                                <span class="rcm"><i class="fas fa-download"></i><?php echo number_format($dl); ?></span>
                                                <?php if ($stars > 0): ?><span class="rcm" style="color:var(--amber)"><?php echo $starStr; ?></span><?php endif; ?>
                                                <?php if (!empty($r['language'])): ?><span class="rcm"><i class="fas fa-globe"></i><?php echo htmlspecialchars($r['language']); ?></span><?php endif; ?>
                                                <span class="rcm"><i class="fas fa-clock"></i><?php echo date('d M Y', strtotime($r['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <?php if ($url !== '#'): ?>
                                            <div class="rc-actions">
                                                <button class="btn-view" onclick="event.stopPropagation();window.location.href='<?php echo $url; ?>'">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="pagination" id="pagination"></div>
                        <?php endif; ?>

                    <?php endif; /* type !== people */ ?>
                </main>
            </div>

        <?php else: ?>
            <div class="zero-state">
                <div class="zero-state-icon"><i class="fas fa-magnifying-glass"></i></div>
                <h2>What are you looking for?</h2>
                <p>Search notes, books, newspapers — or find people by name or username</p>
                <div class="suggestions" style="margin-top:24px;">
                    <a href="notes.php" class="sug-pill"><i class="fas fa-file-lines"></i> Browse Notes</a>
                    <a href="books.php" class="sug-pill"><i class="fas fa-book"></i> Browse Books</a>
                    <a href="newspaper.php" class="sug-pill"><i class="fas fa-newspaper"></i> Browse Newspapers</a>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <?php include_once 'admin/files/footer.php'; ?>

    <script>
        function changeSort(v) {
            const u = new URL(location.href);
            u.searchParams.set('sort', v);
            if (u.searchParams.has('s')) {
                u.searchParams.set('q', u.searchParams.get('s'));
                u.searchParams.delete('s');
            }
            location.href = u.toString();
        }

        document.addEventListener('DOMContentLoaded', () => {
            const list = document.getElementById('resultsList');
            const pagEl = document.getElementById('pagination');
            const elFrom = document.getElementById('showFrom');
            const elTo = document.getElementById('showTo');
            const elTotal = document.getElementById('showTotal');
            if (!list) return;

            const allCards = Array.from(list.querySelectorAll('.rc'));
            const PER_PAGE = 15;
            let page = 1,
                filtered = [...allCards];

            function getChecked(type) {
                return [...document.querySelectorAll(`input[data-filter="${type}"]:checked`)].map(c => c.value);
            }

            function applyFilters() {
                const rtypes = getChecked('rtype');
                const subjs = getChecked('subject');
                const langs = getChecked('language');
                const ratings = getChecked('rating').map(Number);
                const dls = getChecked('downloads').map(Number);
                filtered = allCards.filter(c => {
                    const d = c.dataset;
                    if (rtypes.length && !rtypes.includes(d.rtype)) return false;
                    if (subjs.length && !subjs.some(s => d.subject.includes(s))) return false;
                    if (langs.length && !langs.includes(d.language)) return false;
                    if (ratings.length && !ratings.some(r => parseInt(d.rating) >= r)) return false;
                    if (dls.length && !dls.some(dl => parseInt(d.downloads) >= dl)) return false;
                    return true;
                });
                page = 1;
                render();
            }

            function render() {
                list.innerHTML = '';
                const start = (page - 1) * PER_PAGE;
                const slice = filtered.slice(start, start + PER_PAGE);
                if (!slice.length) {
                    list.innerHTML = `<div class="no-results">
                    <div class="no-results-icon"><i class="fas fa-filter"></i></div>
                    <h3>No matches</h3><p>Try adjusting your filters.</p></div>`;
                    elFrom.textContent = elTo.textContent = elTotal.textContent = 0;
                } else {
                    slice.forEach(c => list.appendChild(c));
                    elFrom.textContent = start + 1;
                    elTo.textContent = Math.min(start + PER_PAGE, filtered.length);
                    elTotal.textContent = filtered.length;
                }
                buildPagination();
            }

            function buildPagination() {
                if (!pagEl) return;
                pagEl.innerHTML = '';
                const total = Math.ceil(filtered.length / PER_PAGE);
                if (total <= 1) return;

                const prev = mkBtn('<i class="fas fa-chevron-left"></i>');
                prev.disabled = page === 1;
                prev.onclick = () => {
                    if (page > 1) {
                        page--;
                        render();
                        scrollTo({
                            top: 300,
                            behavior: 'smooth'
                        });
                    }
                };
                pagEl.appendChild(prev);

                const rs = Math.max(1, page - 2),
                    re = Math.min(total, page + 2);
                if (rs > 1) {
                    pagEl.appendChild(mkPageBtn(1));
                    if (rs > 2) addEllipsis();
                }
                for (let i = rs; i <= re; i++) pagEl.appendChild(mkPageBtn(i));
                if (re < total) {
                    if (re < total - 1) addEllipsis();
                    pagEl.appendChild(mkPageBtn(total));
                }

                const next = mkBtn('<i class="fas fa-chevron-right"></i>');
                next.disabled = page === total;
                next.onclick = () => {
                    if (page < total) {
                        page++;
                        render();
                        scrollTo({
                            top: 300,
                            behavior: 'smooth'
                        });
                    }
                };
                pagEl.appendChild(next);
            }

            function addEllipsis() {
                const el = document.createElement('span');
                el.className = 'pag-ellipsis';
                el.textContent = '…';
                pagEl.appendChild(el);
            }

            function mkPageBtn(i) {
                const b = mkBtn(i);
                if (i === page) b.classList.add('active');
                b.onclick = () => {
                    page = i;
                    render();
                    scrollTo({
                        top: 300,
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

            document.getElementById('clearFilters')?.addEventListener('click', () => {
                document.querySelectorAll('input[data-filter]').forEach(c => c.checked = false);
                applyFilters();
                applyPeopleFilters();
            });
            document.querySelectorAll('input[data-filter]').forEach(c => c.addEventListener('change', () => {
                applyFilters();
                applyPeopleFilters();
            }));
            render();

            // ── People grid client-side filtering ──────────────────────
            function applyPeopleFilters() {
                const grid = document.getElementById('peopleGrid');
                if (!grid) return;

                const proles = getChecked('prole');
                const pstates = getChecked('pstate');
                const puploads = getChecked('puploads').map(Number);

                let visible = 0;
                grid.querySelectorAll('.person-card').forEach(card => {
                    const role = card.dataset.prole || '';
                    const state = card.dataset.pstate || '';
                    const uploads = parseInt(card.dataset.puploads) || 0;

                    let show = true;
                    if (proles.length && !proles.includes(role)) show = false;
                    if (pstates.length && !pstates.some(s => state.includes(s))) show = false;
                    if (puploads.length && !puploads.some(u => uploads >= u)) show = false;

                    card.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                // Update the people section count badge
                const badge = document.querySelector('#peopleSection .sh-count');
                if (badge) badge.textContent = visible + ' found';
            }

            // Run people filters on load too (in case of back-navigation)
            applyPeopleFilters();
        });
    </script>
</body>

</html>