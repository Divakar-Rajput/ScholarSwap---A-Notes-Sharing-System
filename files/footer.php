<?php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl  = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/ScholarSwap/';
?>
<footer class="site-footer">
    <div class="footer-grid">

        <!-- Brand -->
        <div class="footer-brand">
            <div class="footer-logo">
                <div class="footer-logo-mark">
                    <img src="<?php echo $baseUrl; ?>assets/img/logo.png" alt="ScholarSwap"
                        onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-graduation-cap\'></i>'">
                </div>
                <div class="footer-logo-text">Scholar<em>Swap</em></div>
            </div>
            <p class="footer-tagline">
                A collaborative academic platform where students and tutors
                exchange notes, books and resources freely.
            </p>
            <div class="footer-socials">
                <a href="#" class="fsoc" title="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" class="fsoc" title="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" class="fsoc" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <a href="#" class="fsoc" title="GitHub"><i class="fab fa-github"></i></a>
            </div>
        </div>

        <!-- Explore -->
        <div class="footer-col">
            <h4>Explore</h4>
            <ul>
                <li><a href="<?php echo $baseUrl; ?>index.php">Home</a></li>
                <li><a href="<?php echo $baseUrl; ?>notes.php">Notes</a></li>
                <li><a href="<?php echo $baseUrl; ?>books.php">Books</a></li>
                <li><a href="<?php echo $baseUrl; ?>newspaper.php">Newspapers</a></li>
            </ul>
        </div>

        <!-- Account -->
        <div class="footer-col">
            <h4>Account</h4>
            <ul>
                <li><a href="<?php echo $baseUrl; ?>login.html">Login</a></li>
                <li><a href="<?php echo $baseUrl; ?>signup.html">Sign Up</a></li>
                <li><a href="<?php echo $baseUrl; ?>admin/user_pages/myprofile.php">My Profile</a></li>
                <li><a href="<?php echo $baseUrl; ?>admin/user_pages/notes_upload.php">Upload</a></li>
            </ul>
        </div>

        <!-- Support -->
        <div class="footer-col">
            <h4>Support</h4>
            <ul>
                <li><a href="#">About Us</a></li>
                <li><a href="#">Contact</a></li>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Use</a></li>
            </ul>
        </div>

    </div><!-- /footer-grid -->

    <div class="footer-bottom">
        <span>© <?php echo date('Y'); ?> ScholarSwap. All rights reserved.</span>
        <span>Made with <i class="fas fa-heart" style="color:var(--red);font-size:.7rem"></i> for students</span>
    </div>
</footer>