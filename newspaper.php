<?php
include_once('admin/config/connection.php');
include_once('admin/encryption.php');
require_once('admin/auth_check.php');

$stmt = $conn->prepare("SELECT * FROM newspapers WHERE approval_status='approved' ORDER BY created_at DESC");
$stmt->execute();
$papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total  = count($papers);

$pubStmt = $conn->prepare("SELECT publisher, COUNT(*) AS cnt FROM newspapers WHERE approval_status='approved' GROUP BY publisher ORDER BY cnt DESC LIMIT 12");
$pubStmt->execute();
$publishers = $pubStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>

<body>

    <div class="page-grid"></div>
    <div class="content">

        <?php include_once("admin/files/header.php"); ?>

        <div class="page-banner">
            <div class="banner-inner">
                <div class="banner-left">
                    <div class="banner-icon"><i class="fas fa-newspaper"></i></div>
                    <div>
                        <div class="banner-title">E-Newspaper Archive</div>
                        <div class="banner-sub">Browse current affairs, regional papers and academic publications</div>
                    </div>
                </div>
                <div class="banner-stat">
                    <i class="fas fa-layer-group"></i>
                    <strong><?php echo $total; ?></strong> papers available
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
                <span class="cur">Newspaper</span>
            </div>
        </div>

        <div class="search-bar-wrap">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by title, publisher, region, language…">
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
                        <h4>Publication Date <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="date" value="today"> Today</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="date" value="7"> Last 7 Days</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="date" value="30"> Last 30 Days</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="date" value="90"> Last 3 Months</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="date" value="365"> Last 1 Year</label>
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
                        <h4>Region <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="national"> National</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="international"> International</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="delhi"> Delhi</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="mumbai"> Mumbai</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="kolkata"> Kolkata</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="chennai"> Chennai</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="bengaluru"> Bengaluru</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="hyderabad"> Hyderabad</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="pune"> Pune</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="region" value="ahmedabad"> Ahmedabad</label>
                            <div id="moreRegions" style="display:none">
                                <label class="filter-opt"><input type="checkbox" data-filter="region" value="jaipur"> Jaipur</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="region" value="lucknow"> Lucknow</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="region" value="patna"> Patna</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="region" value="bhopal"> Bhopal</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="region" value="chandigarh"> Chandigarh</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="region" value="guwahati"> Guwahati</label>
                                <label class="filter-opt"><input type="checkbox" data-filter="region" value="kochi"> Kochi</label>
                            </div>
                            <span class="show-more-link" id="toggleRegions">+ Show more</span>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Publisher <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="the hindu"> The Hindu</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="times of india"> Times of India</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="hindustan times"> Hindustan Times</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="indian express"> Indian Express</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="deccan herald"> Deccan Herald</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="telegraph"> The Telegraph</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="dainik bhaskar"> Dainik Bhaskar</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="amar ujala"> Amar Ujala</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="publisher" value="navbharat times"> Navbharat Times</label>
                            <?php foreach ($publishers as $pub):
                                $pval   = strtolower(trim($pub['publisher']));
                                $pknown = ['the hindu', 'times of india', 'hindustan times', 'indian express', 'deccan herald', 'telegraph', 'dainik bhaskar', 'amar ujala', 'navbharat times'];
                                if (!in_array($pval, $pknown)):
                            ?>
                                    <label class="filter-opt">
                                        <input type="checkbox" data-filter="publisher" value="<?php echo htmlspecialchars($pval); ?>">
                                        <?php echo htmlspecialchars($pub['publisher']); ?>
                                        <span class="fc"><?php echo $pub['cnt']; ?></span>
                                    </label>
                            <?php endif;
                            endforeach; ?>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Category <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="current affairs"> Current Affairs</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="politics"> Politics</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="science"> Science &amp; Tech</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="economy"> Economy &amp; Business</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="sports"> Sports</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="education"> Education</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="international"> International</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="environment"> Environment</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="health"> Health</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="category" value="editorial"> Editorial</label>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Popularity <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="1000"> 1000+ Downloads</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="500"> 500+ Downloads</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="100"> 100+ Downloads</label>
                            <label class="filter-opt"><input type="checkbox" data-filter="downloads" value="10"> 10+ Downloads</label>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Availability <i class="fas fa-chevron-down"></i></h4>
                        <div class="filter-body" style="display:none">
                            <label class="filter-opt"><input type="checkbox" data-filter="featured" value="1"> ⭐ Featured Only</label>
                        </div>
                    </div>

                </div>
            </aside>

            <main>
                <div class="results-head">
                    <p class="results-count">
                        Showing <strong id="showFrom">0</strong>–<strong id="showTo">0</strong>
                        of <strong id="showTotal">0</strong> newspapers
                    </p>
                    <select class="sort-select" id="sortSelect">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="popular">Most Viewed</option>
                        <option value="downloads">Most Downloaded</option>
                    </select>
                </div>

                <div class="np-grid" id="npGrid">
                    <?php foreach ($papers as $p):
                        $pubDate  = $p['publication_date'] ?? '';
                        $filePath = htmlspecialchars($p['file_path'] ?? '');
                    ?>
                        <?php
                        $rtoken = encryptId($p['n_code']);
                        $utoken = encryptId($p['admin_id']);
                        $ttoken = encryptId('newspaper');
                        ?>
                        <div class="np-card"
                            data-title="<?php echo htmlspecialchars(strtolower($p['title'])); ?>"
                            data-publisher="<?php echo htmlspecialchars(strtolower($p['publisher'] ?? '')); ?>"
                            data-language="<?php echo htmlspecialchars(strtolower($p['language'] ?? '')); ?>"
                            data-region="<?php echo htmlspecialchars(strtolower($p['region'] ?? '')); ?>"
                            data-date="<?php echo htmlspecialchars($pubDate); ?>"
                            data-downloads="<?php echo (int)($p['download_count'] ?? 0); ?>"
                            data-views="<?php echo (int)($p['view_count'] ?? 0); ?>"
                            data-featured="<?php echo (int)($p['is_featured'] ?? 0); ?>"
                            data-category=""
                            onclick="window.location.href='notes_reader?r=<?php echo $rtoken; ?>&u=<?php echo $utoken; ?>&t=<?php echo $ttoken ?>'">

                            <div class="np-thumb" id="thumb-<?php echo $p['newspaper_id']; ?>">
                                <?php if ($filePath): ?>
                                    <div class="thumb-loading" id="loading-<?php echo $p['newspaper_id']; ?>">
                                        <div class="thumb-spinner"></div>
                                        <span>Loading preview…</span>
                                    </div>
                                    <canvas id="canvas-<?php echo $p['newspaper_id']; ?>" style="display:none;width:100%;height:100%;object-fit:cover;"></canvas>
                                <?php else: ?>
                                    <div class="thumb-fallback"><i class="fas fa-newspaper"></i><span>No preview</span></div>
                                <?php endif; ?>
                                <div class="np-thumb-overlay"></div>
                                <span class="np-badge">Newspaper</span>
                                <?php if (!empty($p['language'])): ?>
                                    <span class="np-lang"><?php echo htmlspecialchars($p['language']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="np-body">
                                <?php if (!empty($p['region'])): ?>
                                    <div class="np-region"><?php echo htmlspecialchars($p['region']); ?></div>
                                <?php endif; ?>
                                <div class="np-title"><?php echo htmlspecialchars($p['title']); ?></div>
                                <?php if (!empty($p['publisher'])): ?>
                                    <div class="np-publisher"><i class="fas fa-building-columns"></i><?php echo htmlspecialchars($p['publisher']); ?></div>
                                <?php endif; ?>
                                <?php if ($pubDate): ?>
                                    <div class="np-date"><i class="fas fa-calendar-days"></i><?php echo date('d M Y', strtotime($pubDate)); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="np-footer">
                                <div class="np-stats">
                                    <span class="npstat"><i class="fas fa-eye"></i><?php echo number_format($p['view_count'] ?? 0); ?></span>
                                    <span class="npstat"><i class="fas fa-download"></i><?php echo number_format($p['download_count'] ?? 0); ?></span>
                                    <span class="npstat"><i class="fas fa-clock"></i><?php echo date('d M', strtotime($p['created_at'])); ?></span>
                                </div>
                                <span class="np-read">Read Now</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pagination" id="pagination"></div>

            </main>

        </div>
    </div>

    <?php include_once("admin/files/footer.php"); ?>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const pdfMeta = <?php
                        $meta = [];
                        foreach ($papers as $p) {
                            if (!empty($p['file_path']))
                                $meta[] = ['id' => $p['newspaper_id'], 'path' => $p['file_path']];
                        }
                        echo json_encode($meta);
                        ?>;

        const thumbObs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    renderThumb(e.target.dataset.npId, e.target.dataset.npPath);
                    thumbObs.unobserve(e.target);
                }
            });
        }, {
            rootMargin: '200px'
        });

        pdfMeta.forEach(item => {
            const thumb = document.getElementById('thumb-' + item.id);
            if (thumb) {
                thumb.dataset.npId = item.id;
                thumb.dataset.npPath = item.path;
                thumbObs.observe(thumb);
            }
        });

        async function renderThumb(id, pdfPath) {
            const canvas = document.getElementById('canvas-' + id);
            const loading = document.getElementById('loading-' + id);
            if (!canvas) return;
            try {
                const pdf = await pdfjsLib.getDocument(pdfPath).promise;
                const page = await pdf.getPage(1);
                const vp0 = page.getViewport({
                    scale: 1
                });
                const vp = page.getViewport({
                    scale: 200 / vp0.height
                });
                canvas.width = vp.width;
                canvas.height = vp.height;
                await page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport: vp
                }).promise;
                if (loading) loading.style.display = 'none';
                canvas.style.display = 'block';
            } catch {
                if (loading) loading.innerHTML = '<div class="thumb-fallback"><i class="fas fa-newspaper"></i><span>Preview unavailable</span></div>';
            }
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const grid = document.getElementById('npGrid');
            const allCards = Array.from(grid.querySelectorAll('.np-card'));
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

            function matchDate(dateStr, filters) {
                if (!filters || filters.length === 0) return true;
                if (!dateStr) return false;
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const fileDate = new Date(dateStr);
                if (isNaN(fileDate)) return false;
                fileDate.setHours(0, 0, 0, 0);
                const diff = Math.floor((today - fileDate) / 86400000);
                return filters.some(f => f === 'today' ? diff === 0 : diff <= parseInt(f, 10));
            }

            function applyFilters() {
                const q = searchInp.value.trim().toLowerCase();
                searchClr.style.display = q ? 'block' : 'none';
                const langs = getChecked('language');
                const regions = getChecked('region');
                const pubs = getChecked('publisher');
                const dates = getChecked('date');
                const cats = getChecked('category');
                const dls = getChecked('downloads').map(Number);
                const featured = getChecked('featured');

                filtered = allCards.filter(c => {
                    const d = c.dataset;
                    if (q && !d.title.includes(q) && !d.publisher.includes(q) && !d.language.includes(q) && !d.region.includes(q)) return false;
                    if (langs.length && !langs.includes(d.language)) return false;
                    if (regions.length && !regions.some(r => d.region.includes(r))) return false;
                    if (pubs.length && !pubs.some(p => d.publisher.includes(p))) return false;
                    if (!matchDate(d.date, dates)) return false;
                    if (cats.length && !cats.some(cat => d.category.includes(cat))) return false;
                    if (dls.length && !dls.some(dl => parseInt(d.downloads) >= dl)) return false;
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
                    if (v === 'downloads') return parseInt(b.dataset.downloads) - parseInt(a.dataset.downloads);
                    if (v === 'oldest') return new Date(a.dataset.date || 0) - new Date(b.dataset.date || 0);
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
                            <div class="empty-icon"><i class="fas fa-newspaper"></i></div>
                            <h3>No newspapers found</h3>
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

            const moreR = document.getElementById('moreRegions');
            const togR = document.getElementById('toggleRegions');
            if (togR && moreR) {
                togR.addEventListener('click', () => {
                    const show = moreR.style.display !== 'none';
                    moreR.style.display = show ? 'none' : 'block';
                    togR.textContent = show ? '+ Show more' : '− Show less';
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
                    text: 'Task completed successfully.',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    background: '#ffffff',
                    color: '#0f172a',
                    iconColor: '#059669'
                });
                setTimeout(() => history.replaceState(null, '', 'newspaper.php'), 500);
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
                setTimeout(() => history.replaceState(null, '', 'newspaper.php'), 500);
            }
        });
    </script>
</body>

</html>