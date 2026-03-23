<?php
session_start();
require_once "config/connection.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email      = trim($_POST['email']   ?? '');
    $dob        = trim($_POST['dob']     ?? '');
    $password   =      $_POST['password'] ?? '';
    $ip         = $_SERVER['REMOTE_ADDR']     ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (empty($email) || empty($dob) || empty($password)) {
        $error = "All fields are required.";
    } else {

        $stmt = $conn->prepare("
            SELECT admin_id, first_name, last_name, role, password
            FROM admin_user
            WHERE email = ? AND dob = ?
            LIMIT 1
        ");
        $stmt->execute([$email, $dob]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {

            $_SESSION['admin_id']   = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
            $_SESSION['admin_role'] = $admin['role'];

            $log = $conn->prepare("
                INSERT INTO admin_login_activity (admin_id, login_time, ip_address, user_agent, status)
                VALUES (?, NOW(), ?, ?, 'success')
            ");
            $log->execute([$admin['admin_id'], $ip, $user_agent]);

            // Redirect back to this page with success params so SweetAlert can fire
            header("Location: admin_login.php?s=success");
            exit;
        } else {

            $error = $admin
                ? "Incorrect password. Please try again."
                : "No admin account found with those credentials.";

            if ($admin) {
                $log = $conn->prepare("
                    INSERT INTO admin_login_activity (admin_id, login_time, ip_address, user_agent, status)
                    VALUES (?, NOW(), ?, ?, 'wrong_password')
                ");
                $log->execute([$admin['admin_id'], $ip, $user_agent]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | ScholarSwap</title>
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --navy: #0d1b35;
            --blue: #2563eb;
            --blue-g: #3b82f6;
            --gold: #f59e0b;
            --text: #e8edf5;
            --text2: #8fa3c0;
            --border: rgba(255, 255, 255, .1);
            --input-bg: rgba(255, 255, 255, .05);
            --error: #ef4444;
            --green: #10b981;
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--navy);
            display: flex;
            min-height: 100vh;
            overflow: hidden;
        }

        .bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 20%, rgba(37, 99, 235, .25) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%, rgba(245, 158, 11, .12) 0%, transparent 55%),
                var(--navy);
        }

        .bg-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, .025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .025) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 20%, transparent 100%);
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            pointer-events: none;
            animation: drift 12s ease-in-out infinite alternate;
        }

        .orb1 {
            width: 400px;
            height: 400px;
            background: rgba(37, 99, 235, .15);
            top: -100px;
            left: -80px;
        }

        .orb2 {
            width: 300px;
            height: 300px;
            background: rgba(245, 158, 11, .08);
            bottom: -60px;
            right: -60px;
            animation-delay: -4s;
        }

        .orb3 {
            width: 200px;
            height: 200px;
            background: rgba(16, 185, 129, .07);
            top: 40%;
            right: 10%;
            animation-delay: -8s;
        }

        @keyframes drift {
            from {
                transform: translate(0, 0)scale(1)
            }

            to {
                transform: translate(30px, 20px)scale(1.05)
            }
        }

        .wrap {
            position: relative;
            z-index: 1;
            display: flex;
            width: 100%;
        }

        /* LEFT */
        .left {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 56px;
            border-right: 1px solid var(--border);
        }

        .left-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 56px;
        }

        .left-logo-mark {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ffff;
            font-size: 1.1rem;
            color: #fff;
            box-shadow: 0 6px 20px rgba(37, 99, 235, .4);
        }

        .left-logo-mark img {
            width: 100%;
            height: 100%;
        }

        .left-logo-text {

            font-size: 1.25rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }

        .left-logo-text em {
            color: var(--gold);
            font-style: normal;
        }

        .left-headline {

            font-size: 2.6rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.18;
            margin-bottom: 16px;
            opacity: 0;
            animation: slideUp .7s .1s ease forwards;
        }

        .left-headline span {
            color: var(--gold);
        }

        .left-sub {
            font-size: 1rem;
            color: var(--text2);
            line-height: 1.65;
            max-width: 360px;
            opacity: 0;
            animation: slideUp .7s .2s ease forwards;
        }

        .features {
            margin-top: 52px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .feat {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            opacity: 0;
            animation: slideUp .6s ease forwards;
        }

        .feat:nth-child(1) {
            animation-delay: .3s
        }

        .feat:nth-child(2) {
            animation-delay: .4s
        }

        .feat:nth-child(3) {
            animation-delay: .5s
        }

        .feat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
        }

        .feat-icon.b {
            background: rgba(37, 99, 235, .2);
            color: var(--blue-g)
        }

        .feat-icon.g {
            background: rgba(16, 185, 129, .15);
            color: var(--green)
        }

        .feat-icon.a {
            background: rgba(245, 158, 11, .15);
            color: var(--gold)
        }

        .feat-title {
            font-size: .88rem;
            font-weight: 600;
            color: var(--text);
            line-height: 1.2
        }

        .feat-desc {
            font-size: .78rem;
            color: var(--text2);
            margin-top: 2px
        }

        /* RIGHT */
        .right {
            width: 460px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
        }

        .card {
            width: 100%;
            background: rgba(255, 255, 255, .035);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px 36px;
            backdrop-filter: blur(20px);
            box-shadow: 0 24px 60px rgba(0, 0, 0, .3);
            opacity: 0;
            animation: fadeIn .7s .15s ease forwards;
        }

        .card-head {
            margin-bottom: 30px;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
        }

        .card-sub {
            font-size: .85rem;
            color: var(--text2);
        }

        /* ERROR */
        .alert-error {
            background: rgba(239, 68, 68, .12);
            border: 1px solid rgba(239, 68, 68, .3);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: .84rem;
            color: #fca5a5;
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 20px;
            animation: shake .35s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0)
            }

            25% {
                transform: translateX(-6px)
            }

            75% {
                transform: translateX(6px)
            }
        }

        /* FORM */
        .fgroup {
            margin-bottom: 18px;
        }

        .fgroup label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 7px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .finput {
            width: 100%;
            padding: 12px 14px;
            background: var(--input-bg);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            color: #fff;
            font-size: .9rem;
            outline: none;
            transition: all .2s;
        }

        .finput::placeholder {
            color: rgba(255, 255, 255, .2);
        }

        .finput:focus {
            border-color: var(--blue);
            background: rgba(37, 99, 235, .08);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
        }

        .finput::-webkit-calendar-picker-indicator {
            filter: invert(.6);
        }

        .pw-wrap {
            position: relative;
        }

        .pw-wrap .finput {
            padding-right: 44px;
        }

        .pw-toggle {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text2);
            cursor: pointer;
            font-size: .88rem;
            transition: color .2s;
            padding: 4px;
        }

        .pw-toggle:hover {
            color: #fff;
        }

        .remember {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            font-size: .82rem;
        }

        .check-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text2);
            cursor: pointer;
        }

        .check-label input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--blue);
            cursor: pointer;
        }

        .remember a {
            color: var(--text2);
            text-decoration: none;
            transition: color .2s;
        }

        .remember a:hover {
            color: var(--gold);
        }

        /* BUTTON */
        .submit-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--blue), var(--blue-g));
            color: #fff;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .22s;
            box-shadow: 0 6px 20px rgba(37, 99, 235, .35);
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
            background: linear-gradient(135deg, rgba(255, 255, 255, .12), transparent);
            opacity: 0;
            transition: opacity .2s;
        }

        .submit-btn:hover::before {
            opacity: 1;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(37, 99, 235, .45);
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
                transform: rotate(360deg)
            }
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            color: rgba(255, 255, 255, .15);
            font-size: .75rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .card-foot {
            text-align: center;
            font-size: .8rem;
            color: var(--text2);
        }

        .card-foot a {
            color: var(--blue-g);
            text-decoration: none;
            font-weight: 600;
        }

        .card-foot a:hover {
            color: #fff;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(18px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @media(max-width:900px) {
            .left {
                display: none
            }

            .right {
                width: 100%;
                padding: 30px 20px
            }

            .card {
                padding: 32px 26px
            }
        }

        @media(max-width:420px) {
            .right {
                padding: 20px 14px
            }

            .card {
                padding: 26px 20px
            }
        }
    </style>
</head>

<body>

    <div class="bg"></div>
    <div class="bg-grid"></div>
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    <div class="orb orb3"></div>

    <div class="wrap">

        <!-- LEFT -->
        <div class="left">
            <div class="left-logo">
                <div class="left-logo-mark">
                    <img src="../../assets/img/logo.png" alt="ScholarSwap"
                        onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-graduation-cap\'></i>'">
                </div>
                <div class="left-logo-text">Scholar<em>Swap</em></div>
            </div>
            <h1 class="left-headline">Admin<br><span>Control Centre</span></h1>
            <p class="left-sub">Manage content, users, and platform settings from one secure, powerful dashboard.</p>
            <div class="features">
                <div class="feat">
                    <div class="feat-icon b"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="feat-title">Secure Access</div>
                        <div class="feat-desc">Multi-factor credential verification with login activity logging.</div>
                    </div>
                </div>
                <div class="feat">
                    <div class="feat-icon g"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="feat-title">Content Approvals</div>
                        <div class="feat-desc">Review and approve notes, books & newspapers in one place.</div>
                    </div>
                </div>
                <div class="feat">
                    <div class="feat-icon a"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="feat-title">Live Analytics</div>
                        <div class="feat-desc">Real-time charts, user growth, and download statistics.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="right">
            <div class="card">
                <div class="card-head">
                    <div class="card-title">Welcome back 👋</div>
                    <div class="card-sub">Sign in to your admin account to continue.</div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error">
                        <i class="fas fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" novalidate>

                    <div class="fgroup">
                        <label>Email Address</label>
                        <input class="finput" type="email" name="email" placeholder="admin@scholarswap.in"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email">
                    </div>

                    <div class="fgroup">
                        <label>Date of Birth</label>
                        <input class="finput" type="date" name="dob"
                            value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>" required>
                    </div>

                    <div class="fgroup">
                        <label>Password</label>
                        <div class="pw-wrap">
                            <input class="finput" type="password" name="password" id="passwordInput"
                                placeholder="••••••••" required autocomplete="current-password">
                            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Toggle password">
                                <i class="fas fa-eye" id="pwIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="remember">
                        <label class="check-label">
                            <input type="checkbox" name="remember" id="rememberMe"> Remember me
                        </label>
                        <a href="#">Forgot password?</a>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <span class="btn-text"><i class="fas fa-arrow-right-to-bracket"></i>&nbsp; Sign In</span>
                        <span class="btn-loader"><span class="spinner"></span> Signing in…</span>
                    </button>

                </form>

                <div class="divider">or</div>
            </div>
        </div>

    </div>

    <script>
        /* ── Password toggle ── */
        const pwInput = document.getElementById('passwordInput');
        const pwToggle = document.getElementById('pwToggle');
        const pwIcon = document.getElementById('pwIcon');
        pwToggle.addEventListener('click', () => {
            const show = pwInput.type === 'password';
            pwInput.type = show ? 'text' : 'password';
            pwIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });

        /* ── Loading state on submit ── */
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        form.addEventListener('submit', (e) => {
            const email = form.querySelector('[name="email"]').value.trim();
            const dob = form.querySelector('[name="dob"]').value.trim();
            const password = form.querySelector('[name="password"]').value;
            if (!email || !dob || !password) {
                e.preventDefault();
                return;
            }
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });
        window.addEventListener('load', () => {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        });

        /* ── Remember me ── */
        const rememberMe = document.getElementById('rememberMe');
        const emailInput = form.querySelector('[name="email"]');
        const saved = localStorage.getItem('ss_admin_email');
        if (saved) {
            emailInput.value = saved;
            rememberMe.checked = true;
        }
        form.addEventListener('submit', () => {
            if (rememberMe.checked) localStorage.setItem('ss_admin_email', emailInput.value.trim());
            else localStorage.removeItem('ss_admin_email');
        });

        /* ══════════════════════════════════════════
           SWEETALERT2 — Login success
        ══════════════════════════════════════════ */
        const params = new URLSearchParams(window.location.search);

        if (params.get('s') === 'success') {
            const name = decodeURIComponent(params.get('name') || 'Admin');

            Swal.fire({
                icon: 'success',
                title: `Welcome back, ${name}! 👋`,
                html: `
                <p style="color:#8fa3c0;font-size:.9rem;margin-top:4px">
                    You have signed in successfully.<br>
                    Redirecting to your dashboard…
                </p>`,
                timer: 2800,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                background: '#0e1a30',
                color: '#e8edf5',
                iconColor: '#10b981',
                didOpen: () => {
                    /* Clean URL after alert is open — so params are already read */
                    history.replaceState(null, '', 'admin_login.php');

                    /* Tint the progress bar to match the blue theme */
                    const bar = Swal.getTimerProgressBar();
                    if (bar) {
                        bar.style.background = 'linear-gradient(90deg, #2563eb, #3b82f6)';
                        bar.style.height = '4px';
                    }
                }
            }).then(() => {
                window.location.replace('dashboard.php');
            });
        }
    </script>

</body>

</html>