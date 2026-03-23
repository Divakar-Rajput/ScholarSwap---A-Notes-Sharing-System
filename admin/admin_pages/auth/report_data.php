<?php
/*
 * report_data.php
 * AJAX endpoint for report_generator.php
 * Place in: admin/auth/report_data.php
 */
ini_set('display_errors', 0);
error_reporting(0);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../config/connection.php';

$type   = $_POST['type']   ?? '';
$fields = json_decode($_POST['fields'] ?? '[]', true);
$filters = json_decode($_POST['filters'] ?? '{}', true);

try {
    $data = [];

    if ($type === 'students') {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where[] = 'u.is_active=1';
            }
            if ($filters['status'] === 'banned') {
                $where[] = 'u.is_active=0';
            }
            if ($filters['status'] === 'verified') {
                $where[] = 'u.is_verified=1';
            }
            if ($filters['status'] === 'unverified') {
                $where[] = 'u.is_verified=0';
            }
        }
        if (!empty($filters['course'])) {
            $where[] = 's.course=?';
            $params[] = $filters['course'];
        }
        if (!empty($filters['state'])) {
            $where[] = 's.state=?';
            $params[] = $filters['state'];
        }
        if (!empty($filters['gender'])) {
            $where[] = 's.gender=?';
            $params[] = $filters['gender'];
        }

        $sq = $conn->prepare("
            SELECT s.user_id, s.first_name, s.last_name, s.course, s.institution,
                   s.state, s.district, s.gender, s.dob, s.subjects_of_interest,
                   s.current_address, s.permanent_address,
                   u.username, u.email, u.phone, u.is_verified, u.is_active,
                   u.created_at AS joined, u.last_login,
                   (SELECT COUNT(*) FROM notes     WHERE user_id=s.user_id AND approval_status='approved') AS note_count,
                   (SELECT COUNT(*) FROM books     WHERE user_id=s.user_id AND approval_status='approved') AS book_count,
                   (SELECT COUNT(*) FROM downloads WHERE user_id=s.user_id) AS dl_count
            FROM students s JOIN users u ON s.user_id=u.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.created_at DESC
        ");
        $sq->execute($params);
        $rows = $sq->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['full_name']      = trim($r['first_name'] . ' ' . $r['last_name']);
            $r['status']         = $r['is_active']   ? 'Active'   : 'Banned';
            $r['verified_str']   = $r['is_verified']  ? 'Verified' : 'Unverified';
            $r['joined_fmt']     = $r['joined']     ? date('d M Y', strtotime($r['joined']))       : '—';
            $r['last_login_fmt'] = $r['last_login'] ? date('d M Y H:i', strtotime($r['last_login'])) : 'Never';
            $r['dob_fmt']        = $r['dob']        ? date('d M Y', strtotime($r['dob']))           : '—';
        }
        $data = ['rows' => $rows, 'count' => count($rows)];
    } elseif ($type === 'tutors') {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where[] = 'u.is_active=1';
            }
            if ($filters['status'] === 'banned') {
                $where[] = 'u.is_active=0';
            }
            if ($filters['status'] === 'verified') {
                $where[] = 'u.is_verified=1';
            }
        }
        if (!empty($filters['state'])) {
            $where[] = 't.state=?';
            $params[] = $filters['state'];
        }
        $tq = $conn->prepare("
            SELECT t.user_id, t.first_name, t.last_name, t.qualification, t.experience_years,
                   t.subjects_taught, t.institution, t.state, t.district, t.gender, t.dob,
                   t.bio, t.current_address,
                   u.username, u.email, u.phone, u.is_verified, u.is_active,
                   u.created_at AS joined, u.last_login,
                   (SELECT COUNT(*) FROM notes WHERE user_id=t.user_id AND approval_status='approved') AS note_count,
                   (SELECT COUNT(*) FROM books WHERE user_id=t.user_id AND approval_status='approved') AS book_count
            FROM tutors t JOIN users u ON t.user_id=u.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.created_at DESC
        ");
        $tq->execute($params);
        $rows = $tq->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['full_name']      = trim($r['first_name'] . ' ' . $r['last_name']);
            $r['status']         = $r['is_active']  ? 'Active'   : 'Banned';
            $r['verified_str']   = $r['is_verified'] ? 'Verified' : 'Unverified';
            $r['joined_fmt']     = $r['joined']     ? date('d M Y', strtotime($r['joined']))        : '—';
            $r['last_login_fmt'] = $r['last_login'] ? date('d M Y H:i', strtotime($r['last_login'])) : 'Never';
        }
        $data = ['rows' => $rows, 'count' => count($rows)];
    } elseif ($type === 'users') {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['role'])) {
            $where[] = 'u.role=?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where[] = 'u.is_active=1';
            }
            if ($filters['status'] === 'banned') {
                $where[] = 'u.is_active=0';
            }
            if ($filters['status'] === 'verified') {
                $where[] = 'u.is_verified=1';
            }
        }
        $uq = $conn->prepare("
            SELECT u.user_id, u.username, u.email, u.phone, u.role,
                   u.is_verified, u.is_active, u.created_at AS joined, u.last_login
            FROM users u
            WHERE " . implode(' AND ', $where) . "
            ORDER BY u.created_at DESC
        ");
        $uq->execute($params);
        $rows = $uq->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['status']         = $r['is_active']  ? 'Active'   : 'Banned';
            $r['verified_str']   = $r['is_verified'] ? 'Verified' : 'Unverified';
            $r['joined_fmt']     = $r['joined']     ? date('d M Y', strtotime($r['joined']))        : '—';
            $r['last_login_fmt'] = $r['last_login'] ? date('d M Y H:i', strtotime($r['last_login'])) : 'Never';
        }
        $data = ['rows' => $rows, 'count' => count($rows)];
    } elseif ($type === 'content') {
        $where = ["approval_status != ''"];
        $params = [];
        if (!empty($filters['approval'])) {
            $where[] = "approval_status=?";
            $params[] = $filters['approval'];
        }

        // Check which optional columns exist in notes table
        $notesCols = [];
        $colCheck = $conn->query("SHOW COLUMNS FROM notes")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['language', 'class_level'] as $col) {
            $notesCols[$col] = in_array($col, $colCheck) ? $col : "'' AS $col";
        }

        // Check which optional columns exist in books table
        $booksCols = [];
        $colCheck2 = $conn->query("SHOW COLUMNS FROM books")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['language', 'class_level'] as $col) {
            $booksCols[$col] = in_array($col, $colCheck2) ? $col : "'' AS $col";
        }

        $nSel = "n_code AS code, title, subject, document_type, {$notesCols['language']}, {$notesCols['class_level']}, approval_status, download_count, view_count, rating, created_at, 'note' AS content_type, user_id";
        $bSel = "b_code AS code, title, subject, document_type, {$booksCols['language']}, {$booksCols['class_level']}, approval_status, download_count, view_count, rating, created_at, 'book' AS content_type, user_id";

        $nq = $conn->prepare("SELECT $nSel FROM notes WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC");
        $nq->execute($params);
        $notes = $nq->fetchAll(PDO::FETCH_ASSOC);
        $bq = $conn->prepare("SELECT $bSel FROM books WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC");
        $bq->execute($params);
        $books = $bq->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_merge($notes, $books);
        usort($rows, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        foreach ($rows as &$r) {
            $r['created_fmt'] = date('d M Y', strtotime($r['created_at']));
        }
        $data = ['rows' => $rows, 'count' => count($rows)];
    } elseif ($type === 'requests') {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'mr.status=?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'mr.priority=?';
            $params[] = $filters['priority'];
        }
        $rq = $conn->prepare("
            SELECT mr.request_id, mr.tracking_number, mr.ref_code, mr.title,
                   mr.material_type, mr.priority, mr.status, mr.admin_note,
                   mr.created_at, mr.fulfilled_at,
                   u.username, u.email, u.phone
            FROM material_requests mr
            JOIN users u ON u.user_id = mr.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY mr.created_at DESC
        ");
        $rq->execute($params);
        $rows = $rq->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['created_fmt']   = date('d M Y', strtotime($r['created_at']));
            $r['fulfilled_fmt'] = $r['fulfilled_at'] ? date('d M Y', strtotime($r['fulfilled_at'])) : '—';
        }
        $data = ['rows' => $rows, 'count' => count($rows)];
    } elseif ($type === 'dashboard') {
        function qv($conn, $sql)
        {
            $s = $conn->prepare($sql);
            $s->execute();
            return (int)$s->fetchColumn();
        }
        $data = [
            'counts' => [
                'users'      => qv($conn, "SELECT COUNT(*) FROM users"),
                'students'   => qv($conn, "SELECT COUNT(*) FROM students"),
                'tutors'     => qv($conn, "SELECT COUNT(*) FROM tutors"),
                'admins'     => qv($conn, "SELECT COUNT(*) FROM admin_user"),
                'notes'      => qv($conn, "SELECT COUNT(*) FROM notes"),
                'books'      => qv($conn, "SELECT COUNT(*) FROM books"),
                'papers'     => qv($conn, "SELECT COUNT(*) FROM newspapers"),
                'verified'   => qv($conn, "SELECT COUNT(*) FROM users WHERE is_verified=1"),
                'n_approved' => qv($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='approved'"),
                'n_pending'  => qv($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='pending'"),
                'n_rejected' => qv($conn, "SELECT COUNT(*) FROM notes WHERE approval_status='rejected'"),
                'b_approved' => qv($conn, "SELECT COUNT(*) FROM books WHERE approval_status='approved'"),
                'b_pending'  => qv($conn, "SELECT COUNT(*) FROM books WHERE approval_status='pending'"),
                'b_rejected' => qv($conn, "SELECT COUNT(*) FROM books WHERE approval_status='rejected'"),
                'rq_total'   => qv($conn, "SELECT COUNT(*) FROM material_requests"),
                'rq_fulfilled' => qv($conn, "SELECT COUNT(*) FROM material_requests WHERE status='Fulfilled'"),
                'rq_pending' => qv($conn, "SELECT COUNT(*) FROM material_requests WHERE status='Pending'"),
            ],
            'monthly' => [],
            'top_downloads' => [],
        ];
        for ($i = 6; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("-$i months"));
            $label = date('M Y', strtotime("-$i months"));
            $sn = $conn->prepare("SELECT COUNT(*) FROM notes WHERE DATE_FORMAT(created_at,'%Y-%m')=?");
            $sn->execute([$m]);
            $sb = $conn->prepare("SELECT COUNT(*) FROM books WHERE DATE_FORMAT(created_at,'%Y-%m')=?");
            $sb->execute([$m]);
            $su = $conn->prepare("SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at,'%Y-%m')=?");
            $su->execute([$m]);
            $data['monthly'][] = ['month' => $label, 'notes' => (int)$sn->fetchColumn(), 'books' => (int)$sb->fetchColumn(), 'users' => (int)$su->fetchColumn()];
        }
        $td = $conn->prepare("(SELECT title,'Note' AS type,download_count FROM notes WHERE approval_status='approved' ORDER BY download_count DESC LIMIT 5) UNION ALL (SELECT title,'Book',download_count FROM books WHERE approval_status='approved' ORDER BY download_count DESC LIMIT 5) ORDER BY download_count DESC LIMIT 8");
        $td->execute();
        $data['top_downloads'] = $td->fetchAll(PDO::FETCH_ASSOC);
    }

    $data['generated'] = date('d F Y, h:i A');
    $data['report_type'] = $type;
    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
