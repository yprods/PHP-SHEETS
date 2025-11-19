<?php
/**
 * מערכת ניהול פיצ'רים משולבת - API
 * Feature Management System - API
 * 
 * @author System
 * @version 1.0
 */

// Database configuration
define('DB_PATH', __DIR__ . '/data.db');

// Initialize database
function initDatabase() {
    $db = new SQLite3(DB_PATH);
    
    // Features table
    $db->exec("CREATE TABLE IF NOT EXISTS features (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        feature TEXT NOT NULL,
        description TEXT,
        color TEXT DEFAULT '#3498db',
        user TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Versions table for versioning
    $db->exec("CREATE TABLE IF NOT EXISTS versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id INTEGER NOT NULL,
        field_name TEXT NOT NULL,
        old_value TEXT,
        new_value TEXT,
        user TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id) REFERENCES features(id)
    )");
    
    // Audit table
    $db->exec("CREATE TABLE IF NOT EXISTS audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id INTEGER,
        action TEXT NOT NULL,
        field_name TEXT,
        old_value TEXT,
        new_value TEXT,
        user TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id) REFERENCES features(id)
    )");
    
    // Pages table for multi-page support
    $db->exec("CREATE TABLE IF NOT EXISTS pages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL DEFAULT 'דף חדש',
        page_order INTEGER DEFAULT 0,
        is_locked INTEGER DEFAULT 0,
        lock_pin TEXT DEFAULT NULL,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add lock_pin column if it doesn't exist
    $columns = $db->query("PRAGMA table_info(pages)");
    $hasLockPin = false;
    while ($col = $columns->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'lock_pin') {
            $hasLockPin = true;
            break;
        }
    }
    if (!$hasLockPin) {
        $db->exec("ALTER TABLE pages ADD COLUMN lock_pin TEXT DEFAULT NULL");
    }
    
    // Page permissions table
    $db->exec("CREATE TABLE IF NOT EXISTS page_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_id INTEGER NOT NULL,
        username TEXT NOT NULL,
        can_view INTEGER DEFAULT 1,
        can_edit INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (page_id) REFERENCES pages(id),
        UNIQUE(page_id, username)
    )");
    
    // Custom columns table
    $db->exec("CREATE TABLE IF NOT EXISTS custom_columns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_id INTEGER NOT NULL,
        column_name TEXT NOT NULL,
        column_type TEXT DEFAULT 'text',
        column_order INTEGER DEFAULT 0,
        is_visible INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (page_id) REFERENCES pages(id)
    )");
    
    // Add page_id to features table if not exists
    $db->exec("PRAGMA table_info(features)");
    $columns = $db->query("PRAGMA table_info(features)");
    $hasPageId = false;
    while ($col = $columns->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'page_id') {
            $hasPageId = true;
            break;
        }
    }
    if (!$hasPageId) {
        $db->exec("ALTER TABLE features ADD COLUMN page_id INTEGER DEFAULT 1");
    }
    
    // Create default page if none exists
    $result = $db->query("SELECT COUNT(*) as count FROM pages");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row['count'] == 0) {
        $db->exec("INSERT INTO pages (title, page_order) VALUES ('דף ראשי', 1)");
    }
    
    // Comments/Chat table
    $db->exec("CREATE TABLE IF NOT EXISTS feature_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id INTEGER NOT NULL,
        user TEXT NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id) REFERENCES features(id)
    )");
    
    // Likes/Dislikes table (one per user per feature)
    $db->exec("CREATE TABLE IF NOT EXISTS feature_likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id INTEGER NOT NULL,
        user TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'like',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id) REFERENCES features(id),
        UNIQUE(feature_id, user)
    )");
    
    // Shares table
    $db->exec("CREATE TABLE IF NOT EXISTS feature_shares (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id INTEGER NOT NULL,
        user TEXT NOT NULL,
        shared_with TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id) REFERENCES features(id)
    )");
    
    // Attachments table (images and files)
    $db->exec("CREATE TABLE IF NOT EXISTS feature_attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id INTEGER NOT NULL,
        user TEXT NOT NULL,
        file_name TEXT NOT NULL,
        file_path TEXT NOT NULL,
        file_type TEXT NOT NULL,
        file_size INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id) REFERENCES features(id)
    )");
    
    // Feature connections table
    $db->exec("CREATE TABLE IF NOT EXISTS feature_connections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id_1 INTEGER NOT NULL,
        feature_id_2 INTEGER NOT NULL,
        user TEXT NOT NULL,
        connection_type TEXT DEFAULT 'related',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id_1) REFERENCES features(id),
        FOREIGN KEY (feature_id_2) REFERENCES features(id),
        UNIQUE(feature_id_1, feature_id_2)
    )");
    
    // Tags table
    $db->exec("CREATE TABLE IF NOT EXISTS feature_tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id INTEGER NOT NULL,
        tag TEXT NOT NULL,
        user TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id) REFERENCES features(id)
    )");
    
    // Email tracking table for line change notifications
    $db->exec("CREATE TABLE IF NOT EXISTS feature_tracking (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        feature_id INTEGER NOT NULL,
        email TEXT NOT NULL,
        user TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feature_id) REFERENCES features(id),
        UNIQUE(feature_id, email)
    )");
    
    // Notifications table for real-time notifications
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user TEXT NOT NULL,
        type TEXT NOT NULL,
        title TEXT NOT NULL,
        message TEXT,
        link TEXT,
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Online users tracking table
    $db->exec("CREATE TABLE IF NOT EXISTS online_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(username)
    )");
    
    // Shared board items table
    $db->exec("CREATE TABLE IF NOT EXISTS shared_board_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_id INTEGER NOT NULL,
        user TEXT NOT NULL,
        content TEXT NOT NULL,
        position_x REAL DEFAULT 0,
        position_y REAL DEFAULT 0,
        color TEXT DEFAULT '#ffffff',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (page_id) REFERENCES pages(id)
    )");
    
    // Create uploads directory if it doesn't exist
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    return $db;
}

// Get current user from NTLM/IIS
function getCurrentUser() {
    return isset($_SERVER['AUTH_USER']) ? $_SERVER['AUTH_USER'] : 
           (isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : 'System');
}

// Send email notification using PHP mail()
function sendEmailNotification($to, $subject, $message, $featureId, $field, $oldValue, $newValue, $changedBy) {
    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $body = "<html><body dir='rtl' style='font-family: Arial, sans-serif;'>";
    $body .= "<h2>עדכון פיצ'ר #{$featureId}</h2>";
    $body .= "<p><strong>השדה שהשתנה:</strong> {$field}</p>";
    $body .= "<p><strong>ערך ישן:</strong> " . htmlspecialchars($oldValue) . "</p>";
    $body .= "<p><strong>ערך חדש:</strong> " . htmlspecialchars($newValue) . "</p>";
    $body .= "<p><strong>שונה על ידי:</strong> {$changedBy}</p>";
    $body .= "<p><strong>תאריך:</strong> " . date('Y-m-d H:i:s') . "</p>";
    $body .= "<hr>";
    $body .= "<p><small>זהו מייל אוטומטי, אנא אל תשיב</small></p>";
    $body .= "</body></html>";
    
    return mail($to, $subject, $body, $headers);
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $db = initDatabase();
    $user = getCurrentUser();
    $currentPageId = isset($_POST['page_id']) ? intval($_POST['page_id']) : (isset($_GET['page_id']) ? intval($_GET['page_id']) : 1);
    
    switch ($_POST['action']) {
        case 'save':
            $id = intval($_POST['id']);
            $field = $_POST['field'];
            $value = $_POST['value'];
            
            // Get old value
            $stmt = $db->prepare("SELECT $field FROM features WHERE id = ?");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $oldValue = $row[$field] ?? '';
            
            // Update feature
            $stmt = $db->prepare("UPDATE features SET $field = ?, user = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bindValue(1, $value, SQLITE3_TEXT);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $stmt->bindValue(3, $id, SQLITE3_INTEGER);
            $stmt->execute();
            
            // Save to versions
            $stmt = $db->prepare("INSERT INTO versions (feature_id, field_name, old_value, new_value, user) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $field, SQLITE3_TEXT);
            $stmt->bindValue(3, $oldValue, SQLITE3_TEXT);
            $stmt->bindValue(4, $value, SQLITE3_TEXT);
            $stmt->bindValue(5, $user, SQLITE3_TEXT);
            $stmt->execute();
            
            // Save to audit
            $stmt = $db->prepare("INSERT INTO audit (feature_id, action, field_name, old_value, new_value, user) VALUES (?, 'UPDATE', ?, ?, ?, ?)");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $field, SQLITE3_TEXT);
            $stmt->bindValue(3, $oldValue, SQLITE3_TEXT);
            $stmt->bindValue(4, $value, SQLITE3_TEXT);
            $stmt->bindValue(5, $user, SQLITE3_TEXT);
            $stmt->execute();
            
            // Send email notifications to tracked users
            if ($oldValue !== $value) {
                $trackResult = $db->query("SELECT email FROM feature_tracking WHERE feature_id = $id");
                while ($trackRow = $trackResult->fetchArray(SQLITE3_ASSOC)) {
                    $email = $trackRow['email'];
                    $stmt = $db->prepare("SELECT feature, category FROM features WHERE id = ?");
                    $stmt->bindValue(1, $id, SQLITE3_INTEGER);
                    $featResult = $stmt->execute();
                    $featRow = $featResult->fetchArray(SQLITE3_ASSOC);
                    $featureName = $featRow['feature'] ?? 'Unknown';
                    $category = $featRow['category'] ?? '';
                    
                    $subject = "עדכון פיצ'ר: {$category} - {$featureName}";
                    sendEmailNotification($email, $subject, '', $id, $field, $oldValue, $value, $user);
                }
                
                // Create notification for all users about the change
                $stmt = $db->prepare("SELECT DISTINCT username FROM online_users WHERE username != ?");
                $stmt->bindValue(1, $user, SQLITE3_TEXT);
                $usersResult = $stmt->execute();
                $stmt = $db->prepare("SELECT feature, category FROM features WHERE id = ?");
                $stmt->bindValue(1, $id, SQLITE3_INTEGER);
                $featResult = $stmt->execute();
                $featRow = $featResult->fetchArray(SQLITE3_ASSOC);
                $featureName = $featRow['feature'] ?? 'Unknown';
                
                while ($userRow = $usersResult->fetchArray(SQLITE3_ASSOC)) {
                    $notifStmt = $db->prepare("INSERT INTO notifications (user, type, title, message, link) VALUES (?, 'change', ?, ?, ?)");
                    $notifStmt->bindValue(1, $userRow['username'], SQLITE3_TEXT);
                    $notifStmt->bindValue(2, "עדכון פיצ'ר", SQLITE3_TEXT);
                    $notifStmt->bindValue(3, "{$user} עדכן את {$featureName}: {$field} - {$oldValue} → {$value}", SQLITE3_TEXT);
                    $notifStmt->bindValue(4, "?page_id=" . intval($_POST['page_id'] ?? 1), SQLITE3_TEXT);
                    $notifStmt->execute();
                }
            }
            
            // Update user online status
            $stmt = $db->prepare("INSERT OR REPLACE INTO online_users (username, last_seen) VALUES (?, CURRENT_TIMESTAMP)");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Saved successfully']);
            exit;
            
        case 'add':
            $category = $_POST['category'] ?? '';
            $feature = $_POST['feature'] ?? '';
            $description = $_POST['description'] ?? '';
            $color = $_POST['color'] ?? '#3498db';
            $pageId = intval($_POST['page_id'] ?? $currentPageId ?? 1);
            
            $stmt = $db->prepare("INSERT INTO features (category, feature, description, color, user, page_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bindValue(1, $category, SQLITE3_TEXT);
            $stmt->bindValue(2, $feature, SQLITE3_TEXT);
            $stmt->bindValue(3, $description, SQLITE3_TEXT);
            $stmt->bindValue(4, $color, SQLITE3_TEXT);
            $stmt->bindValue(5, $user, SQLITE3_TEXT);
            $stmt->bindValue(6, $pageId, SQLITE3_INTEGER);
            $stmt->execute();
            
            $newId = $db->lastInsertRowID();
            
            // Audit
            $stmt = $db->prepare("INSERT INTO audit (feature_id, action, user) VALUES (?, 'CREATE', ?)");
            $stmt->bindValue(1, $newId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $stmt->execute();
            
            // Create notification for all online users
            $stmt = $db->prepare("SELECT DISTINCT username FROM online_users WHERE username != ?");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $usersResult = $stmt->execute();
            while ($userRow = $usersResult->fetchArray(SQLITE3_ASSOC)) {
                $notifStmt = $db->prepare("INSERT INTO notifications (user, type, title, message, link) VALUES (?, 'create', ?, ?, ?)");
                $notifStmt->bindValue(1, $userRow['username'], SQLITE3_TEXT);
                $notifStmt->bindValue(2, "פיצ'ר חדש", SQLITE3_TEXT);
                $notifStmt->bindValue(3, "{$user} יצר פיצ'ר חדש: {$feature}", SQLITE3_TEXT);
                $notifStmt->bindValue(4, "?page_id={$pageId}", SQLITE3_TEXT);
                $notifStmt->execute();
            }
            
            // Update user online status
            $stmt = $db->prepare("INSERT OR REPLACE INTO online_users (username, last_seen) VALUES (?, CURRENT_TIMESTAMP)");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'id' => $newId]);
            exit;
            
        case 'delete':
            $id = intval($_POST['id']);
            
            // Audit
            $stmt = $db->prepare("INSERT INTO audit (feature_id, action, user) VALUES (?, 'DELETE', ?)");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $stmt->execute();
            
            $stmt = $db->prepare("DELETE FROM features WHERE id = ?");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_data':
            $pageId = intval($_POST['page_id'] ?? 1);
            $result = $db->query("SELECT * FROM features WHERE page_id = $pageId OR page_id IS NULL ORDER BY category, feature");
            $data = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
            }
            echo json_encode($data);
            exit;
            
        case 'get_stats':
            $result = $db->query("SELECT category, COUNT(*) as count FROM features GROUP BY category");
            $stats = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $stats[] = $row;
            }
            echo json_encode($stats);
            exit;
            
        case 'get_audit':
            $limit = intval($_POST['limit'] ?? 50);
            $result = $db->query("SELECT a.*, f.feature, f.category FROM audit a LEFT JOIN features f ON a.feature_id = f.id ORDER BY a.created_at DESC LIMIT $limit");
            $audit = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $audit[] = $row;
            }
            echo json_encode($audit);
            exit;
            
        case 'get_pages':
            $result = $db->query("SELECT * FROM pages ORDER BY page_order, id");
            $pages = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $pages[] = $row;
            }
            echo json_encode($pages);
            exit;
            
        case 'create_page':
            $title = trim($_POST['title'] ?? 'דף חדש');
            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'שם דף לא יכול להיות ריק']);
                exit;
            }
            
            try {
                // Get max page_order
                $maxOrderResult = $db->querySingle("SELECT COALESCE(MAX(page_order), 0) + 1 FROM pages");
                $maxOrder = $maxOrderResult ? intval($maxOrderResult) : 1;
                
                $stmt = $db->prepare("INSERT INTO pages (title, created_by, page_order) VALUES (?, ?, ?)");
                $stmt->bindValue(1, $title, SQLITE3_TEXT);
                $stmt->bindValue(2, $user, SQLITE3_TEXT);
                $stmt->bindValue(3, $maxOrder, SQLITE3_INTEGER);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to execute INSERT query');
                }
                
                $pageId = $db->lastInsertRowID();
                if (!$pageId) {
                    throw new Exception('Failed to get last insert ID');
                }
                
                echo json_encode(['success' => true, 'id' => $pageId, 'title' => $title]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת הדף: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_page_title':
            $pageId = intval($_POST['page_id']);
            $title = $_POST['title'] ?? '';
            $stmt = $db->prepare("UPDATE pages SET title = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bindValue(1, $title, SQLITE3_TEXT);
            $stmt->bindValue(2, $pageId, SQLITE3_INTEGER);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'toggle_page_lock':
            $pageId = intval($_POST['page_id']);
            $pin = $_POST['pin'] ?? '';
            $newLockState = intval($_POST['lock_state'] ?? 0);
            
            // Get current page state
            $stmt = $db->prepare("SELECT is_locked, lock_pin FROM pages WHERE id = ?");
            $stmt->bindValue(1, $pageId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $page = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$page) {
                echo json_encode(['success' => false, 'message' => 'דף לא נמצא']);
                exit;
            }
            
            $currentLockState = intval($page['is_locked']);
            $currentPin = $page['lock_pin'];
            
            // If locking: set pin if provided, or keep existing pin
            if ($newLockState == 1) {
                $pinToSet = !empty($pin) ? $pin : ($currentPin ?? '');
                $stmt = $db->prepare("UPDATE pages SET is_locked = 1, lock_pin = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bindValue(1, $pinToSet, SQLITE3_TEXT);
                $stmt->bindValue(2, $pageId, SQLITE3_INTEGER);
                $stmt->execute();
                echo json_encode(['success' => true, 'locked' => true]);
                exit;
            }
            
            // If unlocking: verify pin if page has a pin
            if ($newLockState == 0) {
                if (!empty($currentPin)) {
                    if ($pin !== $currentPin) {
                        echo json_encode(['success' => false, 'message' => 'קוד PIN שגוי']);
                        exit;
                    }
                }
                $stmt = $db->prepare("UPDATE pages SET is_locked = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bindValue(1, $pageId, SQLITE3_INTEGER);
                $stmt->execute();
                echo json_encode(['success' => true, 'locked' => false]);
                exit;
            }
            
            // Fallback: toggle lock state (legacy support)
            $stmt = $db->prepare("UPDATE pages SET is_locked = NOT is_locked, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bindValue(1, $pageId, SQLITE3_INTEGER);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'set_page_lock_pin':
            $pageId = intval($_POST['page_id']);
            $pin = $_POST['pin'] ?? '';
            $stmt = $db->prepare("UPDATE pages SET lock_pin = ? WHERE id = ?");
            $stmt->bindValue(1, $pin, SQLITE3_TEXT);
            $stmt->bindValue(2, $pageId, SQLITE3_INTEGER);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'delete_page':
            $pageId = intval($_POST['page_id']);
            $db->exec("DELETE FROM pages WHERE id = $pageId");
            $db->exec("UPDATE features SET page_id = 1 WHERE page_id = $pageId");
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_custom_columns':
            $pageId = intval($_POST['page_id'] ?? 1);
            $result = $db->query("SELECT * FROM custom_columns WHERE page_id = $pageId ORDER BY column_order");
            $columns = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row;
            }
            echo json_encode($columns);
            exit;
            
        case 'add_custom_column':
            $pageId = intval($_POST['page_id'] ?? 1);
            $columnName = trim($_POST['column_name'] ?? '');
            $columnType = $_POST['column_type'] ?? 'text';
            
            if (empty($columnName)) {
                echo json_encode(['success' => false, 'message' => 'שם עמודה לא יכול להיות ריק']);
                exit;
            }
            
            try {
                $maxOrder = $db->querySingle("SELECT MAX(column_order) FROM custom_columns WHERE page_id = $pageId") ?: 0;
                $stmt = $db->prepare("INSERT INTO custom_columns (page_id, column_name, column_type, column_order) VALUES (?, ?, ?, ?)");
                $stmt->bindValue(1, $pageId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $columnName, SQLITE3_TEXT);
                $stmt->bindValue(3, $columnType, SQLITE3_TEXT);
                $stmt->bindValue(4, $maxOrder + 1, SQLITE3_INTEGER);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to insert column');
                }
                
                $newColumnId = $db->lastInsertRowID();
                
                // Add column to features table dynamically
                $columnKey = 'custom_' . $newColumnId;
                try {
                    $db->exec("ALTER TABLE features ADD COLUMN `$columnKey` TEXT");
                } catch (Exception $e) {
                    // Column might already exist - that's okay
                    error_log("Column $columnKey might already exist: " . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'id' => $newColumnId, 'key' => $columnKey]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'שגיאה בהוספת העמודה: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_custom_column':
            $columnId = intval($_POST['column_id']);
            $result = $db->query("SELECT * FROM custom_columns WHERE id = $columnId");
            $column = $result->fetchArray(SQLITE3_ASSOC);
            if ($column) {
                $columnKey = 'custom_' . $columnId;
                try {
                    // SQLite doesn't support DROP COLUMN easily, so we'll just mark as hidden
                    $db->exec("UPDATE custom_columns SET is_visible = 0 WHERE id = $columnId");
                } catch (Exception $e) {
                    // Ignore
                }
            }
            $db->exec("DELETE FROM custom_columns WHERE id = $columnId");
            echo json_encode(['success' => true]);
            exit;
            
        case 'update_custom_column':
            $columnId = intval($_POST['column_id']);
            $columnName = $_POST['column_name'] ?? '';
            $columnType = $_POST['column_type'] ?? 'text';
            $stmt = $db->prepare("UPDATE custom_columns SET column_name = ?, column_type = ? WHERE id = ?");
            $stmt->bindValue(1, $columnName, SQLITE3_TEXT);
            $stmt->bindValue(2, $columnType, SQLITE3_TEXT);
            $stmt->bindValue(3, $columnId, SQLITE3_INTEGER);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_page_permissions':
            $pageId = intval($_POST['page_id'] ?? 1);
            $result = $db->query("SELECT * FROM page_permissions WHERE page_id = $pageId");
            $permissions = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $permissions[] = $row;
            }
            echo json_encode($permissions);
            exit;
            
        case 'set_page_permission':
            $pageId = intval($_POST['page_id']);
            $username = $_POST['username'] ?? '';
            $canView = intval($_POST['can_view'] ?? 1);
            $canEdit = intval($_POST['can_edit'] ?? 0);
            $stmt = $db->prepare("INSERT OR REPLACE INTO page_permissions (page_id, username, can_view, can_edit) VALUES (?, ?, ?, ?)");
            $stmt->bindValue(1, $pageId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $username, SQLITE3_TEXT);
            $stmt->bindValue(3, $canView, SQLITE3_INTEGER);
            $stmt->bindValue(4, $canEdit, SQLITE3_INTEGER);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'check_permission':
            $pageId = intval($_POST['page_id'] ?? 1);
            $permission = $_POST['permission'] ?? 'view'; // 'view' or 'edit'
            $result = $db->query("SELECT can_view, can_edit FROM page_permissions WHERE page_id = $pageId AND username = '$user'");
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row) {
                $allowed = $permission === 'view' ? $row['can_view'] : $row['can_edit'];
            } else {
                // Default: allow if no permission set
                $allowed = 1;
            }
            echo json_encode(['allowed' => $allowed]);
            exit;
            
        // Comments/Chat
        case 'add_comment':
            $featureId = intval($_POST['feature_id']);
            $comment = trim($_POST['comment'] ?? '');
            if (empty($comment)) {
                echo json_encode(['success' => false, 'message' => 'תגובה לא יכולה להיות ריקה']);
                exit;
            }
            $stmt = $db->prepare("INSERT INTO feature_comments (feature_id, user, comment) VALUES (?, ?, ?)");
            $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $stmt->bindValue(3, $comment, SQLITE3_TEXT);
            $stmt->execute();
            
            // Get feature info for notification
            $stmt = $db->prepare("SELECT feature, category FROM features WHERE id = ?");
            $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
            $featResult = $stmt->execute();
            $featRow = $featResult->fetchArray(SQLITE3_ASSOC);
            $featureName = $featRow['feature'] ?? 'Unknown';
            
            // Create notifications for all online users except the commenter
            $stmt = $db->prepare("SELECT DISTINCT username FROM online_users WHERE username != ?");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $usersResult = $stmt->execute();
            while ($userRow = $usersResult->fetchArray(SQLITE3_ASSOC)) {
                $notifStmt = $db->prepare("INSERT INTO notifications (user, type, title, message, link) VALUES (?, 'comment', ?, ?, ?)");
                $notifStmt->bindValue(1, $userRow['username'], SQLITE3_TEXT);
                $notifStmt->bindValue(2, "תגובה חדשה", SQLITE3_TEXT);
                $notifStmt->bindValue(3, "{$user} הגיב על {$featureName}", SQLITE3_TEXT);
                $notifStmt->bindValue(4, "?feature_id={$featureId}", SQLITE3_TEXT);
                $notifStmt->execute();
            }
            
            // Update user online status
            $stmt = $db->prepare("INSERT OR REPLACE INTO online_users (username, last_seen) VALUES (?, CURRENT_TIMESTAMP)");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'id' => $db->lastInsertRowID()]);
            exit;
            
        case 'get_comments':
            $featureId = intval($_POST['feature_id']);
            $result = $db->query("SELECT * FROM feature_comments WHERE feature_id = $featureId ORDER BY created_at DESC");
            $comments = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $comments[] = $row;
            }
            echo json_encode($comments);
            exit;
            
        // Likes/Dislikes
        case 'toggle_like':
            $featureId = intval($_POST['feature_id']);
            $type = $_POST['type'] ?? 'like'; // 'like' or 'dislike'
            
            // Check if user already liked/disliked
            $stmt = $db->prepare("SELECT id, type FROM feature_likes WHERE feature_id = ? AND user = ?");
            $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $result = $stmt->execute();
            $existing = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($existing) {
                if ($existing['type'] === $type) {
                    // Remove like/dislike
                    $stmt = $db->prepare("DELETE FROM feature_likes WHERE id = ?");
                    $stmt->bindValue(1, $existing['id'], SQLITE3_INTEGER);
                    $stmt->execute();
                    echo json_encode(['success' => true, 'action' => 'removed']);
                } else {
                    // Change type
                    $stmt = $db->prepare("UPDATE feature_likes SET type = ? WHERE id = ?");
                    $stmt->bindValue(1, $type, SQLITE3_TEXT);
                    $stmt->bindValue(2, $existing['id'], SQLITE3_INTEGER);
                    $stmt->execute();
                    echo json_encode(['success' => true, 'action' => 'changed']);
                }
            } else {
                // Add new like/dislike
                $stmt = $db->prepare("INSERT INTO feature_likes (feature_id, user, type) VALUES (?, ?, ?)");
                $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $user, SQLITE3_TEXT);
                $stmt->bindValue(3, $type, SQLITE3_TEXT);
                $stmt->execute();
                echo json_encode(['success' => true, 'action' => 'added']);
            }
            exit;
            
        case 'get_likes':
            $featureId = intval($_POST['feature_id']);
            $result = $db->query("SELECT type, COUNT(*) as count FROM feature_likes WHERE feature_id = $featureId GROUP BY type");
            $likes = ['like' => 0, 'dislike' => 0, 'user_like' => null];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $likes[$row['type']] = intval($row['count']);
            }
            // Get user's like status
            $stmt = $db->prepare("SELECT type FROM feature_likes WHERE feature_id = ? AND user = ?");
            $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $result = $stmt->execute();
            $userLike = $result->fetchArray(SQLITE3_ASSOC);
            if ($userLike) {
                $likes['user_like'] = $userLike['type'];
            }
            echo json_encode($likes);
            exit;
            
        // Share
        case 'share_feature':
            $featureId = intval($_POST['feature_id']);
            $sharedWith = trim($_POST['shared_with'] ?? '');
            $stmt = $db->prepare("INSERT INTO feature_shares (feature_id, user, shared_with) VALUES (?, ?, ?)");
            $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $stmt->bindValue(3, $sharedWith, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        // Attachments (Images and Files)
        case 'upload_attachment':
            $featureId = intval($_POST['feature_id'] ?? 0);
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'שגיאה בהעלאת הקובץ']);
                exit;
            }
            
            $file = $_FILES['file'];
            $uploadsDir = __DIR__ . '/uploads';
            $fileName = time() . '_' . basename($file['name']);
            $filePath = $uploadsDir . '/' . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $fileType = strpos($file['type'], 'image/') === 0 ? 'image' : 'file';
                $stmt = $db->prepare("INSERT INTO feature_attachments (feature_id, user, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $user, SQLITE3_TEXT);
                $stmt->bindValue(3, $file['name'], SQLITE3_TEXT);
                $stmt->bindValue(4, 'uploads/' . $fileName, SQLITE3_TEXT);
                $stmt->bindValue(5, $fileType, SQLITE3_TEXT);
                $stmt->bindValue(6, $file['size'], SQLITE3_INTEGER);
                $stmt->execute();
                echo json_encode(['success' => true, 'id' => $db->lastInsertRowID(), 'path' => 'uploads/' . $fileName]);
            } else {
                echo json_encode(['success' => false, 'message' => 'שגיאה בשמירת הקובץ']);
            }
            exit;
            
        case 'get_attachments':
            $featureId = intval($_POST['feature_id']);
            $result = $db->query("SELECT * FROM feature_attachments WHERE feature_id = $featureId ORDER BY created_at DESC");
            $attachments = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $attachments[] = $row;
            }
            echo json_encode($attachments);
            exit;
            
        case 'delete_attachment':
            $attachmentId = intval($_POST['attachment_id']);
            $result = $db->query("SELECT file_path FROM feature_attachments WHERE id = $attachmentId");
            $attachment = $result->fetchArray(SQLITE3_ASSOC);
            if ($attachment) {
                $filePath = __DIR__ . '/' . $attachment['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $db->exec("DELETE FROM feature_attachments WHERE id = $attachmentId");
            echo json_encode(['success' => true]);
            exit;
            
        // Feature Connections
        case 'add_connection':
            $featureId1 = intval($_POST['feature_id_1']);
            $featureId2 = intval($_POST['feature_id_2']);
            $connectionType = $_POST['connection_type'] ?? 'related';
            if ($featureId1 == $featureId2) {
                echo json_encode(['success' => false, 'message' => 'לא ניתן לחבר פיצ\'ר לעצמו']);
                exit;
            }
            $stmt = $db->prepare("INSERT OR IGNORE INTO feature_connections (feature_id_1, feature_id_2, user, connection_type) VALUES (?, ?, ?, ?)");
            $stmt->bindValue(1, $featureId1, SQLITE3_INTEGER);
            $stmt->bindValue(2, $featureId2, SQLITE3_INTEGER);
            $stmt->bindValue(3, $user, SQLITE3_TEXT);
            $stmt->bindValue(4, $connectionType, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_connections':
            $featureId = intval($_POST['feature_id']);
            $result = $db->query("SELECT fc.*, f1.feature as feature1_name, f2.feature as feature2_name 
                FROM feature_connections fc
                LEFT JOIN features f1 ON fc.feature_id_1 = f1.id
                LEFT JOIN features f2 ON fc.feature_id_2 = f2.id
                WHERE fc.feature_id_1 = $featureId OR fc.feature_id_2 = $featureId");
            $connections = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $connections[] = $row;
            }
            echo json_encode($connections);
            exit;
            
        case 'delete_connection':
            $connectionId = intval($_POST['connection_id']);
            $db->exec("DELETE FROM feature_connections WHERE id = $connectionId");
            echo json_encode(['success' => true]);
            exit;
            
        // Tags
        case 'add_tag':
            $featureId = intval($_POST['feature_id']);
            $tag = trim($_POST['tag'] ?? '');
            if (empty($tag)) {
                echo json_encode(['success' => false, 'message' => 'תג לא יכול להיות ריק']);
                exit;
            }
            $stmt = $db->prepare("INSERT OR IGNORE INTO feature_tags (feature_id, tag, user) VALUES (?, ?, ?)");
            $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $tag, SQLITE3_TEXT);
            $stmt->bindValue(3, $user, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_tags':
            $featureId = intval($_POST['feature_id']);
            $result = $db->query("SELECT * FROM feature_tags WHERE feature_id = $featureId ORDER BY tag");
            $tags = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $tags[] = $row;
            }
            echo json_encode($tags);
            exit;
            
        case 'delete_tag':
            $tagId = intval($_POST['tag_id']);
            $db->exec("DELETE FROM feature_tags WHERE id = $tagId");
            echo json_encode(['success' => true]);
            exit;
            
        // Email tracking
        case 'add_tracking':
            $featureId = intval($_POST['feature_id']);
            $email = trim($_POST['email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'כתובת אימייל לא תקינה']);
                exit;
            }
            $stmt = $db->prepare("INSERT OR IGNORE INTO feature_tracking (feature_id, email, user) VALUES (?, ?, ?)");
            $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $email, SQLITE3_TEXT);
            $stmt->bindValue(3, $user, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'remove_tracking':
            $featureId = intval($_POST['feature_id']);
            $email = trim($_POST['email'] ?? '');
            $stmt = $db->prepare("DELETE FROM feature_tracking WHERE feature_id = ? AND email = ?");
            $stmt->bindValue(1, $featureId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $email, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_tracking':
            $featureId = intval($_POST['feature_id']);
            $result = $db->query("SELECT * FROM feature_tracking WHERE feature_id = $featureId ORDER BY created_at DESC");
            $tracking = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $tracking[] = $row;
            }
            echo json_encode($tracking);
            exit;
            
        // Move feature between pages
        case 'move_feature':
            $featureId = intval($_POST['feature_id']);
            $newPageId = intval($_POST['new_page_id']);
            $stmt = $db->prepare("UPDATE features SET page_id = ? WHERE id = ?");
            $stmt->bindValue(1, $newPageId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $featureId, SQLITE3_INTEGER);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        // Notifications
        case 'get_notifications':
            $stmt = $db->prepare("SELECT * FROM notifications WHERE user = ? ORDER BY created_at DESC LIMIT 50");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $result = $stmt->execute();
            $notifications = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $notifications[] = $row;
            }
            echo json_encode($notifications);
            exit;
            
        case 'mark_notification_read':
            $notifId = intval($_POST['notification_id']);
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user = ?");
            $stmt->bindValue(1, $notifId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'mark_all_notifications_read':
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user = ?");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        // Online users
        case 'update_online_status':
            $stmt = $db->prepare("INSERT OR REPLACE INTO online_users (username, last_seen) VALUES (?, CURRENT_TIMESTAMP)");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_online_users':
            // Get users who were active in last 5 minutes
            $stmt = $db->query("SELECT username, last_seen FROM online_users WHERE datetime(last_seen) > datetime('now', '-5 minutes') ORDER BY last_seen DESC");
            $users = [];
            while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
                $users[] = $row;
            }
            echo json_encode($users);
            exit;
            
        // Shared board
        case 'get_shared_board_items':
            $pageId = intval($_POST['page_id'] ?? $currentPageId ?? 1);
            $stmt = $db->prepare("SELECT * FROM shared_board_items WHERE page_id = ? ORDER BY created_at DESC");
            $stmt->bindValue(1, $pageId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $items = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $items[] = $row;
            }
            echo json_encode($items);
            exit;
            
        case 'save_shared_board_item':
            $pageId = intval($_POST['page_id'] ?? $currentPageId ?? 1);
            $itemId = intval($_POST['item_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $positionX = floatval($_POST['position_x'] ?? 0);
            $positionY = floatval($_POST['position_y'] ?? 0);
            $color = $_POST['color'] ?? '#ffffff';
            
            if ($itemId > 0) {
                $stmt = $db->prepare("UPDATE shared_board_items SET content = ?, position_x = ?, position_y = ?, color = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user = ?");
                $stmt->bindValue(1, $content, SQLITE3_TEXT);
                $stmt->bindValue(2, $positionX, SQLITE3_FLOAT);
                $stmt->bindValue(3, $positionY, SQLITE3_FLOAT);
                $stmt->bindValue(4, $color, SQLITE3_TEXT);
                $stmt->bindValue(5, $itemId, SQLITE3_INTEGER);
                $stmt->bindValue(6, $user, SQLITE3_TEXT);
                $stmt->execute();
            } else {
                $stmt = $db->prepare("INSERT INTO shared_board_items (page_id, user, content, position_x, position_y, color) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bindValue(1, $pageId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $user, SQLITE3_TEXT);
                $stmt->bindValue(3, $content, SQLITE3_TEXT);
                $stmt->bindValue(4, $positionX, SQLITE3_FLOAT);
                $stmt->bindValue(5, $positionY, SQLITE3_FLOAT);
                $stmt->bindValue(6, $color, SQLITE3_TEXT);
                $stmt->execute();
                $itemId = $db->lastInsertRowID();
            }
            
            // Update user online status
            $stmt = $db->prepare("INSERT OR REPLACE INTO online_users (username, last_seen) VALUES (?, CURRENT_TIMESTAMP)");
            $stmt->bindValue(1, $user, SQLITE3_TEXT);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'id' => $itemId]);
            exit;
            
        case 'delete_shared_board_item':
            $itemId = intval($_POST['item_id']);
            $stmt = $db->prepare("DELETE FROM shared_board_items WHERE id = ? AND user = ?");
            $stmt->bindValue(1, $itemId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
            
        // Download Report
        case 'download_report':
            $pageId = intval($_POST['page_id'] ?? $currentPageId ?? 1);
            $result = $db->query("SELECT f.*, 
                (SELECT COUNT(*) FROM feature_comments WHERE feature_id = f.id) as comments_count,
                (SELECT COUNT(*) FROM feature_likes WHERE feature_id = f.id AND type = 'like') as likes_count,
                (SELECT COUNT(*) FROM feature_likes WHERE feature_id = f.id AND type = 'dislike') as dislikes_count,
                (SELECT GROUP_CONCAT(tag, ', ') FROM feature_tags WHERE feature_id = f.id) as tags
                FROM features f 
                WHERE f.page_id = $pageId OR (f.page_id IS NULL AND $pageId = 1)
                ORDER BY f.category, f.feature");
            
            $report = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $report[] = $row;
            }
            echo json_encode($report);
            exit;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . ($_POST['action'] ?? 'none')]);
            exit;
    }
} else {
    // Not a POST request with action - this file was included for functions only
    // Do nothing, just provide the functions
}
?>

