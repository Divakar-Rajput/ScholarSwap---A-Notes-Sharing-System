<?php
session_start();

// Clear only admin session keys
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

// If no other sessions remain, destroy entirely
if (empty($_SESSION)) {
    session_destroy();
}

header("Location: http://localhost/ScholarSwap/admin/admin_pages/admin_login.php?s=logout");
exit;