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
    'users'     => q($conn, "SELECT COUNT(*) FROM users"),
    'students'  => q($conn, "SELECT COUNT(*) FROM students"),
    'tutors'    => q($conn, "SELECT COUNT(*) FROM tutors"),
    'admins'    => q($conn, "SELECT COUNT(*) FROM admin_user"),
    'notes'     => q($conn, "SELECT COUNT(*) FROM notes"),
    'books'     => q($conn, "SELECT COUNT(*) FROM books"),
    'papers'    => q($conn, "SELECT COUNT(*) FROM newspapers"),
    'n_pending' => q($conn, "SELECT COUNT(*) FROM notes      WHERE approval_status='pending'"),
    'b_pending' => q($conn, "SELECT COUNT(*) FROM books      WHERE approval_status='pending'"),
    'p_pending' => q($conn, "SELECT COUNT(*) FROM newspapers WHERE approval_status='pending'"),
];
$c['pending'] = $c['n_pending'] + $c['b_pending'] + $c['p_pending'];

$pq = $conn->prepare("SELECT * FROM admin_user WHERE admin_id=? LIMIT 1");
$pq->execute([$_SESSION['admin_id']]);
$admin = $pq->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    header("Location: admin_logout.php");
    exit;
}

/* ── Compute avatar variables from $admin (self-contained) ── */
$_base     = 'http://localhost/ScholarSwap/admin/';
$avatarSrc = !empty($admin['profile_image'])
    ? $_base . ltrim($admin['profile_image'], '/')
    : '';
$initials  = strtoupper(
    substr($admin['first_name'] ?? 'A', 0, 1) .
        substr($admin['last_name']  ?? '',  0, 1)
);

$flash = $_GET['s'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Profile | ScholarSwap Admin</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --blue: #2563eb;
            --blue-s: #dbeafe;
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
            --sh2: 0 8px 32px rgba(0, 0, 0, .12);
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
            min-height: 100vh;
            font-size: 14px;
            display: flex;
        }

        a {
            text-decoration: none;
            color: inherit
        }

        ::-webkit-scrollbar {
            width: 5px
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px
        }

        .pg-head {
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px
        }

        .pg-head h1 {
            font-size: 1.3rem;
            font-weight: 800
        }

        .pg-head p {
            font-size: .8rem;
            color: var(--text3);
            margin-top: 3px
        }

        .prof-grid {
            display: grid;
            grid-template-columns: 290px 1fr;
            gap: 20px;
            align-items: start
        }

        .prof-card {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border);
            overflow: hidden;
            position: sticky;
            top: calc(var(--hh, 62px) + 20px)
        }

        .prof-cover {
            height: 88px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed, #0d9488)
        }

        .prof-av-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 20px 20px;
            margin-top: -40px
        }

        .prof-av {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            font-weight: 800;
            color: #fff;
            border: 4px solid var(--surface);
            box-shadow: 0 4px 16px rgba(79, 70, 229, .3);
            overflow: hidden;
        }

        .prof-av img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 16px;
            display: block
        }

        .prof-name {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text);
            margin-top: 10px;
            text-align: center
        }

        .prof-sub {
            font-size: .75rem;
            color: var(--text3);
            text-align: center;
            margin-top: 2px
        }

        .bdg {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: .62rem;
            font-weight: 700
        }

        .bdg-approved {
            background: var(--green-s);
            color: #065f46
        }

        .bdg-pending {
            background: var(--amber-s);
            color: #92400e
        }

        .bdg-rejected {
            background: var(--red-s);
            color: #991b1b
        }

        .bdg-super {
            background: var(--indigo-s);
            color: var(--indigo)
        }

        .bdg-admin {
            background: var(--purple-s);
            color: var(--purple)
        }

        .prof-badges {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap
        }

        .pi-list {
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 0
        }

        .pi {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 9px 0;
            border-bottom: 1px solid var(--border)
        }

        .pi:last-child {
            border-bottom: none
        }

        .pi-ic {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            color: var(--text3);
            flex-shrink: 0;
            margin-top: 1px
        }

        .pi-l {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--text3)
        }

        .pi-v {
            font-size: .82rem;
            color: var(--text);
            font-weight: 500;
            margin-top: 1px;
            line-height: 1.4;
            word-break: break-word
        }

        .pi-v.muted {
            color: var(--text3);
            font-weight: 400
        }

        .prof-edit-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: 10px 16px 16px;
            padding: 9px;
            border-radius: 10px;
            background: var(--indigo);
            color: #fff;
            font-weight: 600;
            font-size: .82rem;
            border: none;
            cursor: pointer;
            transition: all .14s
        }

        .prof-edit-btn:hover {
            background: #3730a3
        }

        .right-col {
            display: flex;
            flex-direction: column;
            gap: 18px
        }

        .panel {
            background: var(--surface);
            border-radius: var(--r2);
            box-shadow: var(--sh);
            border: 1px solid var(--border)
        }

        .panel-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px
        }

        .ph-ico {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            flex-shrink: 0
        }

        .ph-ico.ind {
            background: var(--indigo-s);
            color: var(--indigo)
        }

        .ph-ico.grn {
            background: var(--green-s);
            color: var(--green)
        }

        .ph-ico.amb {
            background: var(--amber-s);
            color: var(--amber)
        }

        .panel-head h2 {
            font-size: .95rem;
            font-weight: 800
        }

        .panel-head p {
            font-size: .73rem;
            color: var(--text3);
            margin-top: 1px
        }

        .panel-body {
            padding: 18px 20px
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px
        }

        .sg {
            background: var(--bg);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            border: 1px solid var(--border)
        }

        .sg-v {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1
        }

        .sg-l {
            font-size: .63rem;
            color: var(--text3);
            margin-top: 3px
        }

        .alert-strip {
            margin-top: 14px;
            padding: 11px 14px;
            background: var(--amber-s);
            border: 1px solid rgba(217, 119, 6, .25);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .81rem;
            color: #92400e
        }

        .alert-strip a {
            margin-left: auto;
            font-weight: 700;
            color: #d97706
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 24px
        }

        .dg {
            display: flex;
            flex-direction: column;
            gap: 3px
        }

        .dg.full {
            grid-column: 1/-1
        }

        .dg-l {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text3)
        }

        .dg-v {
            font-size: .87rem;
            color: var(--text);
            font-weight: 500;
            line-height: 1.5;
            word-break: break-word
        }

        .dg-v.muted {
            color: var(--text3);
            font-weight: 400
        }

        .sec-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            gap: 14px
        }

        .sec-item:last-child {
            border-bottom: none
        }

        .sec-info h4 {
            font-size: .87rem;
            font-weight: 600;
            color: var(--text)
        }

        .sec-info p {
            font-size: .73rem;
            color: var(--text3);
            margin-top: 2px
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            font-size: .78rem;
            font-weight: 600;
            transition: all .14s;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: .73rem;
            border-radius: 7px
        }

        .btn-outline {
            background: var(--surface);
            color: var(--text2);
            border: 1.5px solid var(--border)
        }

        .btn-outline:hover {
            border-color: var(--indigo);
            color: var(--indigo)
        }

        .btn-red {
            background: var(--red-s);
            color: var(--red)
        }

        .btn-red:hover {
            background: var(--red);
            color: #fff
        }

        @media(max-width:900px) {
            .prof-grid {
                grid-template-columns: 1fr
            }

            .prof-card {
                position: static
            }

            .stat-grid {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        @media(max-width:520px) {
            .detail-grid {
                grid-template-columns: 1fr
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
                    <h1>My Profile</h1>
                    <p>View and manage your admin account details</p>
                </div>
                <a class="btn btn-outline" href="edit_profile.php"><i class="fas fa-pen"></i> Edit Profile</a>
            </div>

            <div class="prof-grid">

                <!-- ── Left card ── -->
                <div>
                    <div class="prof-card">
                        <div class="prof-cover"></div>
                        <div class="prof-av-wrap">
                            <div class="prof-av">
                                <?php if (!empty($avatarSrc)): ?>
                                    <img src="<?php echo htmlspecialchars($avatarSrc); ?>"
                                        alt="<?php echo htmlspecialchars($initials); ?>"
                                        onerror="this.parentElement.removeChild(this);this.parentElement.textContent='<?php echo $initials; ?>'">
                                <?php else: ?>
                                    <?php echo $initials; ?>
                                <?php endif; ?>
                            </div>
                            <div class="prof-name"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                            <div class="prof-sub">@<?php echo htmlspecialchars($admin['username']); ?></div>
                            <div class="prof-badges">
                                <span class="bdg <?php echo strtolower($admin['role'] ?? '') === 'superadmin' ? 'bdg-super' : 'bdg-admin'; ?>">
                                    <?php echo $admin['role'] === 'superadmin' ? 'Super Admin' : 'Admin'; ?>
                                </span>
                                <span class="bdg bdg-<?php echo htmlspecialchars($admin['status']); ?>">
                                    <?php echo ucfirst($admin['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="pi-list">
                            <?php foreach (
                                [
                                    ['fa-envelope',        'Email',       $admin['email']       ?? ''],
                                    ['fa-phone',           'Phone',       $admin['phone']       ?? ''],
                                    ['fa-venus-mars',      'Gender',      ucfirst($admin['gender'] ?? '')],
                                    ['fa-cake-candles',    'Date of Birth', $admin['dob'] ? date('d M Y', strtotime($admin['dob'])) : ''],
                                    ['fa-map-pin',         'State',       $admin['state']       ?? ''],
                                    ['fa-location-dot',    'District',    $admin['district']    ?? ''],
                                    ['fa-graduation-cap',  'Course',      $admin['course']      ?? ''],
                                    ['fa-building-columns', 'Institution', $admin['institution'] ?? ''],
                                    ['fa-calendar',        'Registered',  date('d M Y', strtotime($admin['created_at']))],
                                ] as [$ic, $lbl, $val]
                            ): ?>
                                <div class="pi">
                                    <div class="pi-ic"><i class="fas <?php echo $ic; ?>"></i></div>
                                    <div>
                                        <div class="pi-l"><?php echo $lbl; ?></div>
                                        <div class="pi-v <?php echo $val ? '' : 'muted'; ?>">
                                            <?php echo $val ? htmlspecialchars($val) : 'Not provided'; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a class="prof-edit-btn" href="edit_profile.php">
                            <i class="fas fa-pen-to-square"></i> Edit My Profile
                        </a>
                    </div>
                </div>

                <!-- ── Right col ── -->
                <div class="right-col">

                    <!-- Platform Stats -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="ph-ico grn"><i class="fas fa-chart-simple"></i></div>
                            <div>
                                <h2>Platform Overview</h2>
                                <p>Key stats across ScholarSwap</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="stat-grid">
                                <div class="sg">
                                    <div class="sg-v"><?php echo number_format($c['users']); ?></div>
                                    <div class="sg-l">Total Users</div>
                                </div>
                                <div class="sg">
                                    <div class="sg-v"><?php echo number_format($c['students']); ?></div>
                                    <div class="sg-l">Students</div>
                                </div>
                                <div class="sg">
                                    <div class="sg-v"><?php echo number_format($c['tutors']); ?></div>
                                    <div class="sg-l">Tutors</div>
                                </div>
                                <div class="sg">
                                    <div class="sg-v"><?php echo number_format($c['notes']); ?></div>
                                    <div class="sg-l">Notes</div>
                                </div>
                                <div class="sg">
                                    <div class="sg-v"><?php echo number_format($c['books']); ?></div>
                                    <div class="sg-l">Books</div>
                                </div>
                                <div class="sg">
                                    <div class="sg-v"><?php echo number_format($c['papers']); ?></div>
                                    <div class="sg-l">Newspapers</div>
                                </div>
                            </div>
                            <?php if ($c['pending'] > 0): ?>
                                <div class="alert-strip">
                                    <i class="fas fa-triangle-exclamation"></i>
                                    <span><strong><?php echo $c['pending']; ?></strong> item(s) awaiting approval.</span>
                                    <a href="pending.php">Review →</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Personal Details -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="ph-ico ind"><i class="fas fa-user"></i></div>
                            <div>
                                <h2>Personal Details</h2>
                                <p>Complete profile information</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="detail-grid">
                                <div class="dg">
                                    <div class="dg-l">First Name</div>
                                    <div class="dg-v"><?php echo htmlspecialchars($admin['first_name']); ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">Last Name</div>
                                    <div class="dg-v"><?php echo htmlspecialchars($admin['last_name']); ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">Email</div>
                                    <div class="dg-v"><?php echo htmlspecialchars($admin['email'] ?? '—'); ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">Phone</div>
                                    <div class="dg-v <?php echo empty($admin['phone']) ? 'muted' : ''; ?>"><?php echo htmlspecialchars($admin['phone'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">Gender</div>
                                    <div class="dg-v <?php echo empty($admin['gender']) ? 'muted' : ''; ?>"><?php echo ucfirst($admin['gender'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">Date of Birth</div>
                                    <div class="dg-v <?php echo empty($admin['dob']) ? 'muted' : ''; ?>"><?php echo $admin['dob'] ? date('d M Y', strtotime($admin['dob'])) : 'Not provided'; ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">State</div>
                                    <div class="dg-v <?php echo empty($admin['state']) ? 'muted' : ''; ?>"><?php echo htmlspecialchars($admin['state'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">District</div>
                                    <div class="dg-v <?php echo empty($admin['district']) ? 'muted' : ''; ?>"><?php echo htmlspecialchars($admin['district'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">Course</div>
                                    <div class="dg-v <?php echo empty($admin['course']) ? 'muted' : ''; ?>"><?php echo htmlspecialchars($admin['course'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="dg">
                                    <div class="dg-l">Institution</div>
                                    <div class="dg-v <?php echo empty($admin['institution']) ? 'muted' : ''; ?>"><?php echo htmlspecialchars($admin['institution'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="dg full">
                                    <div class="dg-l">Subjects</div>
                                    <div class="dg-v <?php echo empty($admin['subjects']) ? 'muted' : ''; ?>"><?php echo htmlspecialchars($admin['subjects'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="dg full">
                                    <div class="dg-l">Current Address</div>
                                    <div class="dg-v <?php echo empty($admin['current_address']) ? 'muted' : ''; ?>"><?php echo htmlspecialchars($admin['current_address'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="dg full">
                                    <div class="dg-l">Permanent Address</div>
                                    <div class="dg-v <?php echo empty($admin['permanent_address']) ? 'muted' : ''; ?>"><?php echo htmlspecialchars($admin['permanent_address'] ?? 'Not provided'); ?></div>
                                </div>
                                <?php if (!empty($admin['bio'])): ?>
                                    <div class="dg full">
                                        <div class="dg-l">Bio</div>
                                        <div class="dg-v" style="line-height:1.6"><?php echo nl2br(htmlspecialchars($admin['bio'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Security -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="ph-ico amb"><i class="fas fa-shield-halved"></i></div>
                            <div>
                                <h2>Security</h2>
                                <p>Manage your account security settings</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="sec-item">
                                <div class="sec-info">
                                    <h4>Password</h4>
                                    <p>Change your login password to keep your account secure</p>
                                </div>
                                <a class="btn btn-outline btn-sm" href="edit_profile.php#password"><i class="fas fa-key"></i> Change</a>
                            </div>
                            <div class="sec-item">
                                <div class="sec-info">
                                    <h4>Sign Out</h4>
                                    <p>End your current admin session on this device</p>
                                </div>
                                <a class="btn btn-red btn-sm" href="admin_logout.php"><i class="fas fa-right-from-bracket"></i> Log Out</a>
                            </div>
                        </div>
                    </div>

                </div><!-- /right-col -->
            </div><!-- /prof-grid -->
        </div><!-- /pg -->
    </div><!-- /main -->

    <script>
        const _s = '<?php echo htmlspecialchars($flash); ?>';
        if (_s === 'updated') Swal.fire({
            icon: 'success',
            title: 'Profile Updated!',
            text: 'Your changes have been saved.',
            timer: 2500,
            timerProgressBar: true,
            showConfirmButton: false
        });
        else if (_s === 'pw_updated') Swal.fire({
            icon: 'success',
            title: 'Password Changed!',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false
        });
        else if (_s === 'failed') Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: 'Something went wrong. Please try again.',
            timer: 2000,
            showConfirmButton: false
        });
        if (_s) history.replaceState(null, '', 'profile.php');
    </script>
</body>

</html>