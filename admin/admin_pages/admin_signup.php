<?php
session_start();
require_once "config/connection.php";

// Already logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$error   = "";
$success = "";
$old     = []; // repopulate fields on error

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Collect & sanitise
    $old = $_POST;
    $first_name        = trim($_POST['first_name']        ?? '');
    $last_name         = trim($_POST['last_name']         ?? '');
    $username          = trim($_POST['username']          ?? '');
    $dob               = trim($_POST['dob']               ?? '');
    $gender            = trim($_POST['gender']            ?? '');
    $state             = trim($_POST['state']             ?? '');
    $district          = trim($_POST['district']          ?? '');
    $email             = trim($_POST['email']             ?? '');
    $phone             = trim($_POST['phone']             ?? '');
    $role              = trim($_POST['role']              ?? '');
    $course            = trim($_POST['course']            ?? '');
    $institution       = trim($_POST['institution']       ?? '');
    $subjects          = trim($_POST['subjects']          ?? '');
    $bio               = trim($_POST['bio']               ?? '');
    $current_address   = trim($_POST['current_address']   ?? '');
    $permanent_address = trim($_POST['permanent_address'] ?? '');
    $password          =      $_POST['password']          ?? '';
    $confirm_password  =      $_POST['confirm_password']  ?? '';

    // ── Validation ────────────────────────────────────
    if (
        empty($first_name) || empty($last_name) || empty($username) || empty($dob) ||
        empty($gender) || empty($state) || empty($district) || empty($email) ||
        empty($phone) || empty($role) || empty($course) || empty($institution) ||
        empty($current_address) || empty($permanent_address) || empty($password)
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // ── Duplicate email check ──────────────────────
        $chk = $conn->prepare("SELECT admin_id FROM admin_user WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->rowCount() > 0) {
            $error = "An account with that email already exists.";
        } else {
            // ── Duplicate username check ───────────────
            $chk2 = $conn->prepare("SELECT admin_id FROM admin_user WHERE username = ? LIMIT 1");
            $chk2->execute([$username]);
            if ($chk2->rowCount() > 0) {
                $error = "That username is already taken. Please choose another.";
            } else {
                // ── Insert ─────────────────────────────
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO admin_user
                        (first_name, last_name, username, dob, gender, state, district,
                         email, phone, role, course, institution, subjects, bio,
                         current_address, permanent_address, password, status, created_at)
                    VALUES
                        (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',NOW())
                ");

                $ok = $stmt->execute([
                    $first_name,
                    $last_name,
                    $username,
                    $dob,
                    $gender,
                    $state,
                    $district,
                    $email,
                    $phone,
                    $role,
                    $course,
                    $institution,
                    $subjects ?: null,
                    $bio ?: null,
                    $current_address,
                    $permanent_address,
                    $hashed
                ]);

                if ($ok) {
                    // Redirect to login with success param
                    header("Location: admin_login.php?s=registered");
                    exit;
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    }
}

// Helper: repopulate old value safely
function old($key, $default = '')
{
    global $old;
    return htmlspecialchars($old[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signup | ScholarSwap</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0d1b35;
            --navy2: #132040;
            --navy3: #1a2d50;
            --blue: #2563eb;
            --blue-g: #3b82f6;
            --blue-d: #1d4ed8;
            --gold: #f59e0b;
            --green: #10b981;
            --red: #ef4444;
            --text: #e8edf5;
            --text2: #8fa3c0;
            --text3: #5a7299;
            --border: rgba(255, 255, 255, .09);
            --border2: rgba(255, 255, 255, .14);
            --input-bg: rgba(255, 255, 255, .04);
            --card-bg: rgba(255, 255, 255, .032);
            --r: 10px;
            --r2: 14px;
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--navy);
            color: var(--text);
            min-height: 100vh;
            padding: 0;
        }

        /* ── BACKGROUND ── */
        .bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: radial-gradient(ellipse 70% 50% at 15% 15%, rgba(37, 99, 235, .22) 0%, transparent 60%),
                radial-gradient(ellipse 55% 45% at 85% 85%, rgba(245, 158, 11, .1) 0%, transparent 55%),
                var(--navy);
        }

        .bg-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: linear-gradient(rgba(255, 255, 255, .022) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .022) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 100% 100% at 50% 50%, black 30%, transparent 100%);
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            z-index: 0;
            pointer-events: none;
            animation: drift 14s ease-in-out infinite alternate;
        }

        .orb1 {
            width: 420px;
            height: 420px;
            background: rgba(37, 99, 235, .13);
            top: -120px;
            left: -80px;
        }

        .orb2 {
            width: 280px;
            height: 280px;
            background: rgba(245, 158, 11, .07);
            bottom: -50px;
            right: -50px;
            animation-delay: -5s;
        }

        @keyframes drift {
            from {
                transform: translate(0, 0);
            }

            to {
                transform: translate(25px, 18px);
            }
        }

        /* ── LAYOUT ── */
        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px 60px;
        }

        /* ── TOPBAR ── */
        .topbar {
            width: 100%;
            max-width: 860px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .logo-mark {
            width: 50px;
            height: 50px;
            border-radius: 11px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #fff;
            box-shadow: 0 5px 18px rgba(37, 99, 235, .38);
        }

        .logo-mark img {
            width: 100%;
            height: 100%;
        }

        .logo-text {
            font-size: 1.15rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }

        .logo-text em {
            color: var(--gold);
            font-style: normal;
        }

        .back-link {
            font-size: .82rem;
            color: var(--text2);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color .2s;
        }

        .back-link:hover {
            color: #fff;
        }

        /* ── PAGE HEADER ── */
        .page-head {
            width: 100%;
            max-width: 860px;
            margin-bottom: 28px;
            animation: fadeUp .6s ease both;
        }

        .page-head h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
        }

        .page-head p {
            font-size: .9rem;
            color: var(--text2);
        }

        /* ── ALERTS ── */
        .alert {
            width: 100%;
            max-width: 860px;
            border-radius: var(--r2);
            padding: 13px 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: .85rem;
            margin-bottom: 18px;
            animation: shake .3s ease;
        }

        .alert-error {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .28);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, .1);
            border: 1px solid rgba(16, 185, 129, .28);
            color: #6ee7b7;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        /* ── FORM WRAPPER ── */
        .form-wrap {
            width: 100%;
            max-width: 860px;
            animation: fadeUp .6s .1s ease both;
        }

        /* ── SECTIONS ── */
        .section {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            padding: 24px 26px;
            margin-bottom: 18px;
            backdrop-filter: blur(12px);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .92rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        .section-title i {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
            flex-shrink: 0;
        }

        .si-b {
            background: rgba(37, 99, 235, .2);
            color: var(--blue-g);
        }

        .si-g {
            background: rgba(16, 185, 129, .15);
            color: var(--green);
        }

        .si-a {
            background: rgba(245, 158, 11, .15);
            color: var(--gold);
        }

        .si-r {
            background: rgba(239, 68, 68, .15);
            color: var(--red);
        }

        /* ── GRID ── */
        .grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 20px;
        }

        .span2 {
            grid-column: 1/-1;
        }

        /* ── FORM FIELDS ── */
        .fgroup {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .fgroup label {
            font-size: .76rem;
            font-weight: 600;
            color: var(--text2);
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .fgroup label .req {
            color: var(--red);
            margin-left: 2px;
        }

        .finput,
        .fselect,
        .ftextarea {
            width: 100%;
            padding: 10px 13px;
            background: var(--input-bg);
            border: 1.5px solid var(--border);
            border-radius: var(--r);
            color: var(--text);
            font-size: .875rem;
            outline: none;
            transition: all .18s;
            appearance: none;
            -webkit-appearance: none;
        }

        .finput::placeholder,
        .ftextarea::placeholder {
            color: var(--text3);
        }

        .finput:focus,
        .fselect:focus,
        .ftextarea:focus {
            border-color: var(--blue);
            background: rgba(37, 99, 235, .07);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .13);
        }

        .finput::-webkit-calendar-picker-indicator {
            filter: invert(.5);
        }

        .fselect {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238fa3c0' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        .fselect option {
            background: var(--navy2);
            color: var(--text);
        }

        .ftextarea {
            resize: vertical;
            min-height: 80px;
        }

        /* ── RADIO GROUP ── */
        .radio-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 4px;
        }

        .radio-opt {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            background: var(--input-bg);
            cursor: pointer;
            font-size: .85rem;
            color: var(--text2);
            transition: all .16s;
        }

        .radio-opt input[type=radio] {
            display: none;
        }

        .radio-opt:has(input:checked) {
            border-color: var(--blue);
            background: rgba(37, 99, 235, .1);
            color: #fff;
        }

        .radio-opt i {
            font-size: .78rem;
        }

        /* ── PASSWORD FIELD ── */
        .pw-wrap {
            position: relative;
        }

        .pw-wrap .finput {
            padding-right: 42px;
        }

        .pw-toggle {
            position: absolute;
            right: 11px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text3);
            cursor: pointer;
            font-size: .85rem;
            padding: 4px;
            transition: color .18s;
        }

        .pw-toggle:hover {
            color: var(--text);
        }

        /* ── STRENGTH BAR ── */
        .strength-wrap {
            margin-top: 6px;
        }

        .strength-bar {
            height: 3px;
            border-radius: 99px;
            background: rgba(255, 255, 255, .08);
            overflow: hidden;
            margin-bottom: 4px;
        }

        .strength-fill {
            height: 100%;
            border-radius: 99px;
            width: 0;
            transition: width .3s, background .3s;
        }

        .strength-text {
            font-size: .72rem;
        }

        /* ── CHECKBOX ── */
        .check-row {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: .84rem;
            color: var(--text2);
            cursor: pointer;
            margin-top: 6px;
        }

        .check-row input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--blue);
            cursor: pointer;
            flex-shrink: 0;
        }

        .check-row a {
            color: var(--blue-g);
            text-decoration: none;
        }

        .check-row a:hover {
            text-decoration: underline;
        }

        /* ── SUBMIT ── */
        .submit-section {
            margin-top: 4px;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: var(--r2);
            background: linear-gradient(135deg, var(--blue), var(--blue-g));
            color: #fff;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            box-shadow: 0 6px 20px rgba(37, 99, 235, .32);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, .1), transparent);
            opacity: 0;
            transition: opacity .2s;
        }

        .submit-btn:hover::before {
            opacity: 1;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(37, 99, 235, .42);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn.loading .btn-text {
            display: none;
        }

        .submit-btn.loading .btn-loader {
            display: flex;
        }

        .btn-loader {
            display: none;
            align-items: center;
            gap: 8px;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .login-link {
            text-align: center;
            font-size: .84rem;
            color: var(--text2);
            margin-top: 14px;
        }

        .login-link a {
            color: var(--blue-g);
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            color: #fff;
        }

        /* STEP INDICATOR */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 28px;
            width: 100%;
            max-width: 860px;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 14px;
            left: 50%;
            right: -50%;
            height: 1px;
            background: var(--border);
            z-index: 0;
        }

        .step-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--navy3);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .68rem;
            font-weight: 700;
            color: var(--text3);
            position: relative;
            z-index: 1;
            transition: all .3s;
        }

        .step.done .step-dot {
            background: var(--blue);
            border-color: var(--blue);
            color: #fff;
        }

        .step-lbl {
            font-size: .68rem;
            color: var(--text3);
            margin-top: 5px;
            white-space: nowrap;
        }

        .step.done .step-lbl {
            color: var(--blue-g);
        }

        /* ANIMATIONS */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* RESPONSIVE */
        @media(max-width:640px) {
            .grid2 {
                grid-template-columns: 1fr;
            }

            .span2 {
                grid-column: 1;
            }

            .steps {
                display: none;
            }

            .page {
                padding: 24px 14px 50px;
            }

            .section {
                padding: 18px 16px;
            }
        }
    </style>
</head>

<body>

    <div class="bg"></div>
    <div class="bg-grid"></div>
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>

    <div class="page">

        <!-- Topbar -->
        <div class="topbar">
            <div class="logo">
                <div class="logo-mark">
                    <img src="../../assets/img/logo.png" alt="ScholarSwap"
                        onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-graduation-cap\'></i>'">
                </div>
                <div class="logo-text">Scholar<em>Swap</em></div>
            </div>
        </div>

        <!-- Page header -->
        <div class="page-head">
            <h1>Create Admin Account</h1>
            <p>Fill in your details below. Your account will be reviewed before activation.</p>
        </div>

        <!-- Steps -->
        <div class="steps">
            <div class="step done">
                <div class="step-dot"><i class="fas fa-user"></i></div>
                <div class="step-lbl">Personal</div>
            </div>
            <div class="step done">
                <div class="step-dot"><i class="fas fa-envelope"></i></div>
                <div class="step-lbl">Account</div>
            </div>
            <div class="step done">
                <div class="step-dot"><i class="fas fa-book"></i></div>
                <div class="step-lbl">Academic</div>
            </div>
            <div class="step done">
                <div class="step-dot"><i class="fas fa-map-pin"></i></div>
                <div class="step-lbl">Address</div>
            </div>
            <div class="step done">
                <div class="step-dot"><i class="fas fa-lock"></i></div>
                <div class="step-lbl">Security</div>
            </div>
        </div>

        <!-- Alert -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" id="alertBox">
                <i class="fas fa-circle-exclamation" style="margin-top:2px;flex-shrink:0"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- FORM -->
        <div class="form-wrap">
            <form method="POST" action="" id="signupForm" novalidate>

                <!-- ── 1. Personal Details ── -->
                <div class="section">
                    <div class="section-title">
                        <span class="si-b"><i class="fas fa-user"></i></span>
                        Personal Details
                    </div>
                    <div class="grid2">
                        <div class="fgroup">
                            <label>First Name <span class="req">*</span></label>
                            <input class="finput" type="text" name="first_name" placeholder="John" value="<?php echo old('first_name'); ?>" required>
                        </div>
                        <div class="fgroup">
                            <label>Last Name <span class="req">*</span></label>
                            <input class="finput" type="text" name="last_name" placeholder="Doe" value="<?php echo old('last_name'); ?>" required>
                        </div>
                        <div class="fgroup">
                            <label>Username <span class="req">*</span></label>
                            <input class="finput" type="text" name="username" placeholder="john_doe" value="<?php echo old('username'); ?>" required>
                        </div>
                        <div class="fgroup">
                            <label>Date of Birth <span class="req">*</span></label>
                            <input class="finput" type="date" name="dob" value="<?php echo old('dob'); ?>" required>
                        </div>
                        <div class="fgroup">
                            <label>State <span class="req">*</span></label>
                            <select class="fselect" name="state" required>
                                <option value="">Select State / UT</option>
                                <?php
                                $states = ["Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal", "Andaman and Nicobar Islands", "Chandigarh", "Dadra and Nagar Haveli and Daman and Diu", "Delhi (NCT)", "Jammu and Kashmir", "Ladakh", "Lakshadweep", "Puducherry"];
                                foreach ($states as $s) {
                                    $sel = (old('state') === $s) ? 'selected' : '';
                                    echo "<option value=\"$s\" $sel>$s</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="fgroup">
                            <label>District <span class="req">*</span></label>
                            <input class="finput" type="text" name="district" placeholder="Enter your district" value="<?php echo old('district'); ?>" required>
                        </div>
                        <div class="fgroup span2">
                            <label>Gender <span class="req">*</span></label>
                            <div class="radio-group">
                                <label class="radio-opt">
                                    <input type="radio" name="gender" value="male" <?php echo (old('gender') === 'male') ? 'checked' : ''; ?> required>
                                    <i class="fas fa-mars"></i> Male
                                </label>
                                <label class="radio-opt">
                                    <input type="radio" name="gender" value="female" <?php echo (old('gender') === 'female') ? 'checked' : ''; ?>>
                                    <i class="fas fa-venus"></i> Female
                                </label>
                                <label class="radio-opt">
                                    <input type="radio" name="gender" value="other" <?php echo (old('gender') === 'other') ? 'checked' : ''; ?>>
                                    <i class="fas fa-genderless"></i> Other
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── 2. Account Info ── -->
                <div class="section">
                    <div class="section-title">
                        <span class="si-g"><i class="fas fa-envelope"></i></span>
                        Account Information
                    </div>
                    <div class="grid2">
                        <div class="fgroup">
                            <label>Email Address <span class="req">*</span></label>
                            <input class="finput" type="email" name="email" placeholder="admin@scholarswap.in" value="<?php echo old('email'); ?>" required>
                        </div>
                        <div class="fgroup">
                            <label>Phone Number <span class="req">*</span></label>
                            <input class="finput" type="tel" name="phone" placeholder="+91 98765 43210" value="<?php echo old('phone'); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- ── 3. Academic Details ── -->
                <div class="section">
                    <div class="section-title">
                        <span class="si-a"><i class="fas fa-book-open"></i></span>
                        Academic & Role Details
                    </div>
                    <div class="grid2">
                        <div class="fgroup">
                            <label>Role <span class="req">*</span></label>
                            <select class="fselect" name="role" required>
                                <option value="">Select Role</option>
                                <option value="super_admin" <?php echo old('role') === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                <option value="admin" <?php echo old('role') === 'admin'      ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="fgroup">
                            <label>Course / Higher Education <span class="req">*</span></label>
                            <input class="finput" type="text" name="course" placeholder="B.Tech, MBA…" value="<?php echo old('course'); ?>" required>
                        </div>
                        <div class="fgroup span2">
                            <label>Institution / University <span class="req">*</span></label>
                            <input class="finput" type="text" name="institution" placeholder="University name" value="<?php echo old('institution'); ?>" required>
                        </div>
                        <div class="fgroup span2">
                            <label>Subjects of Interest</label>
                            <input class="finput" type="text" name="subjects" placeholder="Math, Physics, Programming…" value="<?php echo old('subjects'); ?>">
                        </div>
                        <div class="fgroup span2">
                            <label>Short Bio <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
                            <textarea class="ftextarea" name="bio" placeholder="Tell us a bit about yourself…"><?php echo old('bio'); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ── 4. Address ── -->
                <div class="section">
                    <div class="section-title">
                        <span class="si-b"><i class="fas fa-map-location-dot"></i></span>
                        Address
                    </div>
                    <div class="grid2">
                        <div class="fgroup span2">
                            <label>Current Address <span class="req">*</span></label>
                            <input class="finput" type="text" name="current_address" id="currentAddress" placeholder="House No., Street, City, State" value="<?php echo old('current_address'); ?>" required>
                        </div>
                        <div class="fgroup span2">
                            <label>Permanent Address <span class="req">*</span></label>
                            <input class="finput" type="text" name="permanent_address" id="permanentAddress" placeholder="House No., Street, City, State" value="<?php echo old('permanent_address'); ?>" required>
                            <label class="check-row" style="margin-top:8px;">
                                <input type="checkbox" id="sameAddress">
                                Same as current address
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ── 5. Security ── -->
                <div class="section">
                    <div class="section-title">
                        <span class="si-r"><i class="fas fa-lock"></i></span>
                        Security
                    </div>
                    <div class="grid2">
                        <div class="fgroup">
                            <label>Password <span class="req">*</span></label>
                            <div class="pw-wrap">
                                <input class="finput" type="password" name="password" id="passwordInput" placeholder="Min. 8 characters" required>
                                <button type="button" class="pw-toggle" id="pwToggle1"><i class="fas fa-eye" id="pwIcon1"></i></button>
                            </div>
                            <div class="strength-wrap">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <div class="strength-text" id="strengthText" style="color:var(--text3)">Minimum 8 characters</div>
                            </div>
                        </div>
                        <div class="fgroup">
                            <label>Confirm Password <span class="req">*</span></label>
                            <div class="pw-wrap">
                                <input class="finput" type="password" name="confirm_password" id="confirmInput" placeholder="Repeat your password" required>
                                <button type="button" class="pw-toggle" id="pwToggle2"><i class="fas fa-eye" id="pwIcon2"></i></button>
                            </div>
                            <div class="strength-text" id="matchText" style="margin-top:6px;"></div>
                        </div>
                    </div>
                </div>

                <!-- ── Submit ── -->
                <div class="section submit-section">
                    <label class="check-row" style="margin-bottom:18px;">
                        <input type="checkbox" id="termsCheck" required>
                        I agree to the <a href="#">Terms &amp; Conditions</a> and <a href="#">Privacy Policy</a>
                    </label>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <span class="btn-text"><i class="fas fa-user-plus"></i>&nbsp; Create Account</span>
                        <span class="btn-loader"><span class="spinner"></span> Creating account…</span>
                    </button>

                </div>

            </form>
        </div><!-- /form-wrap -->

    </div><!-- /page -->

    <script>
        /* ── Password toggle x2 ── */
        function setupPwToggle(inputId, iconId, btnId) {
            const inp = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            document.getElementById(btnId).addEventListener('click', () => {
                const show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        }
        setupPwToggle('passwordInput', 'pwIcon1', 'pwToggle1');
        setupPwToggle('confirmInput', 'pwIcon2', 'pwToggle2');

        /* ── Strength meter ── */
        const passInp = document.getElementById('passwordInput');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');

        passInp.addEventListener('input', () => {
            const v = passInp.value;
            let score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;

            const levels = [{
                    w: '0%',
                    c: 'transparent',
                    t: 'Enter a password'
                },
                {
                    w: '25%',
                    c: '#ef4444',
                    t: 'Weak'
                },
                {
                    w: '50%',
                    c: '#f97316',
                    t: 'Fair'
                },
                {
                    w: '75%',
                    c: '#f59e0b',
                    t: 'Good'
                },
                {
                    w: '100%',
                    c: '#10b981',
                    t: 'Strong ✓'
                },
            ];
            const l = levels[score] ?? levels[0];
            strengthFill.style.width = l.w;
            strengthFill.style.background = l.c;
            strengthText.textContent = l.t;
            strengthText.style.color = l.c || 'var(--text3)';
        });

        /* ── Password match ── */
        const confirmInp = document.getElementById('confirmInput');
        const matchText = document.getElementById('matchText');

        function checkMatch() {
            if (!confirmInp.value) {
                matchText.textContent = '';
                return;
            }
            const ok = passInp.value === confirmInp.value;
            matchText.textContent = ok ? '✓ Passwords match' : '✗ Passwords do not match';
            matchText.style.color = ok ? 'var(--green)' : 'var(--red)';
        }
        confirmInp.addEventListener('input', checkMatch);
        passInp.addEventListener('input', checkMatch);

        /* ── Same address ── */
        const sameChk = document.getElementById('sameAddress');
        const curAddr = document.getElementById('currentAddress');
        const permAddr = document.getElementById('permanentAddress');

        sameChk.addEventListener('change', () => {
            permAddr.value = sameChk.checked ? curAddr.value : '';
            permAddr.readOnly = sameChk.checked;
            permAddr.style.opacity = sameChk.checked ? '.6' : '1';
        });
        curAddr.addEventListener('input', () => {
            if (sameChk.checked) permAddr.value = curAddr.value;
        });

        /* ── Client-side validation ── */
        const form = document.getElementById('signupForm');
        const submitBtn = document.getElementById('submitBtn');
        const termsChk = document.getElementById('termsCheck');

        form.addEventListener('submit', e => {
            // Terms
            if (!termsChk.checked) {
                e.preventDefault();
                termsChk.closest('.section').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                termsChk.style.outline = '2px solid var(--red)';
                setTimeout(() => termsChk.style.outline = '', 2000);
                return;
            }
            // Password match
            if (passInp.value !== confirmInp.value) {
                e.preventDefault();
                confirmInp.focus();
                confirmInp.style.borderColor = 'var(--red)';
                setTimeout(() => confirmInp.style.borderColor = '', 2000);
                return;
            }
            // Password length
            if (passInp.value.length < 8) {
                e.preventDefault();
                passInp.focus();
                return;
            }
            // Loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });

        // Re-enable on page load (in case of back-button)
        window.addEventListener('load', () => {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        });

        /* ── Scroll to error on load ── */
        const alertBox = document.getElementById('alertBox');
        if (alertBox) alertBox.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    </script>
</body>

</html>