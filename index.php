<?php
/**
 * מערכת ניהול פיצ'רים משולבת
 * SysAid / SharePoint / Jira / ServiceNow Feature Management System
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
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
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

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $db = initDatabase();
    $user = getCurrentUser();
    
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
            
        case 'load_sysaid':
            // Auto-load SysAid features
            $sysaidFeatures = [
                ['category' => 'SysAid', 'feature' => 'Ticket Management', 'description' => 'ניהול כרטיסי תמיכה מלא - יצירה, עדכון, סגירה, העברת אחריות, מעקב סטטוס', 'color' => '#e74c3c'],
                ['category' => 'SysAid', 'feature' => 'Asset Management', 'description' => 'ניהול מלאי ציוד IT - מחשבים, מדפסות, שרתים, רישוי תוכנה, מעקב אחרי מיקום', 'color' => '#3498db'],
                ['category' => 'SysAid', 'feature' => 'CMDB', 'description' => 'Configuration Management Database - מאגר תצורות IT, קשרים בין פריטים, תלויות', 'color' => '#9b59b6'],
                ['category' => 'SysAid', 'feature' => 'Monitoring', 'description' => 'ניטור שרתים, רשתות, יישומים - התראות, דוחות ביצועים, זיהוי תקלות', 'color' => '#f39c12'],
                ['category' => 'SysAid', 'feature' => 'Workflow Engine', 'description' => 'מנוע זרימות עבודה - אוטומציה של תהליכים, אישורים, הקצאות אוטומטיות', 'color' => '#1abc9c'],
                ['category' => 'SysAid', 'feature' => 'NTLM / SSO', 'description' => 'אימות יחיד (Single Sign-On) עם Active Directory, NTLM, LDAP', 'color' => '#27ae60'],
                ['category' => 'SysAid', 'feature' => 'Knowledge Base', 'description' => 'מאגר ידע - מאמרים, פתרונות, מדריכים, חיפוש מתקדם', 'color' => '#16a085'],
                ['category' => 'SysAid', 'feature' => 'Service Level Management', 'description' => 'ניהול רמות שירות (SLA) - מעקב זמנים, התראות, דוחות ביצועים', 'color' => '#e67e22'],
                ['category' => 'SysAid', 'feature' => 'Change Management', 'description' => 'ניהול שינויים - בקשות שינוי, אישורים, תכנון, יישום, סגירה', 'color' => '#c0392b'],
                ['category' => 'SysAid', 'feature' => 'Problem Management', 'description' => 'ניהול בעיות - זיהוי שורש בעיה, פתרון, מניעת הישנות', 'color' => '#8e44ad'],
                ['category' => 'SysAid', 'feature' => 'Project Management', 'description' => 'ניהול פרויקטים - משימות, לוחות זמנים, משאבים, דוחות התקדמות', 'color' => '#34495e'],
                ['category' => 'SysAid', 'feature' => 'Mobile App', 'description' => 'אפליקציה ניידת - גישה מלאה ממכשירים ניידים, התראות Push', 'color' => '#95a5a6'],
                ['category' => 'SysAid', 'feature' => 'Reporting & Analytics', 'description' => 'דוחות ואנליטיקה - דוחות מותאמים, גרפים, ניתוח מגמות', 'color' => '#2c3e50'],
                ['category' => 'SysAid', 'feature' => 'Email Integration', 'description' => 'אינטגרציה עם דואר אלקטרוני - יצירת כרטיסים מאימייל, עדכונים', 'color' => '#7f8c8d'],
                ['category' => 'SysAid', 'feature' => 'Remote Control', 'description' => 'שלט רחוק - חיבור מרחוק למחשבים, תמיכה טכנית בזמן אמת', 'color' => '#d35400'],
                ['category' => 'SysAid', 'feature' => 'Software Distribution', 'description' => 'הפצת תוכנה - התקנה מרחוק, עדכונים אוטומטיים, ניהול רישיונות', 'color' => '#27ae60'],
                ['category' => 'SysAid', 'feature' => 'Patch Management', 'description' => 'ניהול תיקונים - עדכוני אבטחה, בדיקות, התקנה אוטומטית', 'color' => '#e74c3c'],
                ['category' => 'SysAid', 'feature' => 'Contract Management', 'description' => 'ניהול חוזים - מעקב אחרי חוזי תמיכה, רישיונות, תאריכי תפוגה', 'color' => '#3498db'],
                ['category' => 'SysAid', 'feature' => 'Self-Service Portal', 'description' => 'פורטל שירות עצמי - בקשות משתמשים, מאגר ידע, מעקב כרטיסים', 'color' => '#9b59b6'],
                ['category' => 'SysAid', 'feature' => 'ITSM Framework', 'description' => 'מסגרת ITSM מלאה - ITIL, תהליכים מובנים, שיטות עבודה מומלצות', 'color' => '#f39c12'],
            ];
            
            $added = 0;
            foreach ($sysaidFeatures as $feat) {
                // Check if exists
                $stmt = $db->prepare("SELECT id FROM features WHERE category = ? AND feature = ?");
                $stmt->bindValue(1, $feat['category'], SQLITE3_TEXT);
                $stmt->bindValue(2, $feat['feature'], SQLITE3_TEXT);
                $result = $stmt->execute();
                if (!$result->fetchArray()) {
                    $stmt = $db->prepare("INSERT INTO features (category, feature, description, color, user) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bindValue(1, $feat['category'], SQLITE3_TEXT);
                    $stmt->bindValue(2, $feat['feature'], SQLITE3_TEXT);
                    $stmt->bindValue(3, $feat['description'], SQLITE3_TEXT);
                    $stmt->bindValue(4, $feat['color'], SQLITE3_TEXT);
                    $stmt->bindValue(5, $user, SQLITE3_TEXT);
                    $stmt->execute();
                    $added++;
                }
            }
            
            echo json_encode(['success' => true, 'added' => $added]);
            exit;
            
        case 'load_sharepoint':
            // Auto-load SharePoint features
            $sharepointFeatures = [
                ['category' => 'SharePoint', 'feature' => 'Document Management', 'description' => 'ניהול מסמכים - שמירה, גרסאות, בדיקות, אישורים, חיפוש מתקדם', 'color' => '#0078d4'],
                ['category' => 'SharePoint', 'feature' => 'Team Sites', 'description' => 'אתרי צוות - שיתוף פעולה, מסמכים, רשימות, לוחות שנה, משימות', 'color' => '#106ebe'],
                ['category' => 'SharePoint', 'feature' => 'Lists & Libraries', 'description' => 'רשימות וספריות - רשימות מותאמות, מסמכים, תמונות, וידאו', 'color' => '#005a9e'],
                ['category' => 'SharePoint', 'feature' => 'Workflows', 'description' => 'זרימות עבודה - אוטומציה של תהליכים עסקיים, אישורים, התראות', 'color' => '#004578'],
                ['category' => 'SharePoint', 'feature' => 'Search', 'description' => 'חיפוש מתקדם - חיפוש בכל התוכן, מסננים, הצעות, חיפוש אנשים', 'color' => '#0078d4'],
                ['category' => 'SharePoint', 'feature' => 'PowerApps Integration', 'description' => 'אינטגרציה עם PowerApps - בניית אפליקציות ללא קוד', 'color' => '#742774'],
                ['category' => 'SharePoint', 'feature' => 'Power Automate', 'description' => 'אוטומציה - יצירת זרימות אוטומטיות בין שירותים', 'color' => '#0066ff'],
                ['category' => 'SharePoint', 'feature' => 'Microsoft Teams Integration', 'description' => 'אינטגרציה עם Teams - שיתוף קבצים, שיתוף פעולה', 'color' => '#6264a7'],
                ['category' => 'SharePoint', 'feature' => 'Version Control', 'description' => 'בקרת גרסאות - היסטוריית שינויים, שחזור, השוואת גרסאות', 'color' => '#0078d4'],
                ['category' => 'SharePoint', 'feature' => 'Permissions & Security', 'description' => 'הרשאות ואבטחה - ניהול גישה, קבוצות, הרשאות מותאמות', 'color' => '#d13438'],
                ['category' => 'SharePoint', 'feature' => 'Content Types', 'description' => 'סוגי תוכן - הגדרת תבניות תוכן, מטא-דאטה, זרימות עבודה', 'color' => '#0078d4'],
                ['category' => 'SharePoint', 'feature' => 'Metadata Management', 'description' => 'ניהול מטא-דאטה - תגיות, תכונות מותאמות, ניהול מונחים', 'color' => '#106ebe'],
                ['category' => 'SharePoint', 'feature' => 'Forms & Surveys', 'description' => 'טפסים וסקרים - יצירת טפסים, איסוף נתונים, ניתוח תוצאות', 'color' => '#005a9e'],
                ['category' => 'SharePoint', 'feature' => 'Business Intelligence', 'description' => 'בינה עסקית - דוחות Power BI, דשבורדים, ויזואליזציה', 'color' => '#f2c811'],
                ['category' => 'SharePoint', 'feature' => 'Social Features', 'description' => 'תכונות חברתיות - בלוגים, דיונים, חדשות, עדכונים', 'color' => '#0078d4'],
                ['category' => 'SharePoint', 'feature' => 'Mobile Access', 'description' => 'גישה ניידת - אפליקציה לנייד, גישה מכל מכשיר', 'color' => '#106ebe'],
                ['category' => 'SharePoint', 'feature' => 'External Sharing', 'description' => 'שיתוף חיצוני - שיתוף עם לקוחות, ספקים, שותפים', 'color' => '#005a9e'],
                ['category' => 'SharePoint', 'feature' => 'Records Management', 'description' => 'ניהול רשומות - שמירה ארוכת טווח, מדיניות שמירה, מחיקה', 'color' => '#004578'],
                ['category' => 'SharePoint', 'feature' => 'Compliance Center', 'description' => 'מרכז תאימות - תאימות רגולטורית, מדיניות, דוחות', 'color' => '#d13438'],
                ['category' => 'SharePoint', 'feature' => 'OneDrive Integration', 'description' => 'אינטגרציה עם OneDrive - סינכרון קבצים, גישה אישית', 'color' => '#0078d4'],
                ['category' => 'SharePoint', 'feature' => 'Yammer Integration', 'description' => 'אינטגרציה עם Yammer - רשת חברתית ארגונית', 'color' => '#106ebe'],
                ['category' => 'SharePoint', 'feature' => 'Custom Web Parts', 'description' => 'רכיבי ווב מותאמים - פיתוח רכיבים מותאמים, אינטגרציות', 'color' => '#005a9e'],
                ['category' => 'SharePoint', 'feature' => 'REST API', 'description' => 'REST API - גישה לתכנותית לתוכן, אינטגרציות מותאמות', 'color' => '#004578'],
            ];
            
            $added = 0;
            foreach ($sharepointFeatures as $feat) {
                $stmt = $db->prepare("SELECT id FROM features WHERE category = ? AND feature = ?");
                $stmt->bindValue(1, $feat['category'], SQLITE3_TEXT);
                $stmt->bindValue(2, $feat['feature'], SQLITE3_TEXT);
                $result = $stmt->execute();
                if (!$result->fetchArray()) {
                    $stmt = $db->prepare("INSERT INTO features (category, feature, description, color, user) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bindValue(1, $feat['category'], SQLITE3_TEXT);
                    $stmt->bindValue(2, $feat['feature'], SQLITE3_TEXT);
                    $stmt->bindValue(3, $feat['description'], SQLITE3_TEXT);
                    $stmt->bindValue(4, $feat['color'], SQLITE3_TEXT);
                    $stmt->bindValue(5, $user, SQLITE3_TEXT);
                    $stmt->execute();
                    $added++;
                }
            }
            
            echo json_encode(['success' => true, 'added' => $added]);
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
            $title = $_POST['title'] ?? 'דף חדש';
            $stmt = $db->prepare("INSERT INTO pages (title, created_by, page_order) VALUES (?, ?, (SELECT COALESCE(MAX(page_order), 0) + 1 FROM pages))");
            $stmt->bindValue(1, $title, SQLITE3_TEXT);
            $stmt->bindValue(2, $user, SQLITE3_TEXT);
            $stmt->execute();
            $pageId = $db->lastInsertRowID();
            echo json_encode(['success' => true, 'id' => $pageId]);
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
            $stmt = $db->prepare("UPDATE pages SET is_locked = NOT is_locked, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bindValue(1, $pageId, SQLITE3_INTEGER);
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
    }
}

// Initialize database on page load
$db = initDatabase();
$user = getCurrentUser();

// Get current page ID from request or default to 1
$currentPageId = isset($_GET['page_id']) ? intval($_GET['page_id']) : 1;

// Get page info
$pageResult = $db->query("SELECT * FROM pages WHERE id = $currentPageId");
$currentPage = $pageResult->fetchArray(SQLITE3_ASSOC);
if (!$currentPage) {
    $currentPage = ['id' => 1, 'title' => 'דף ראשי', 'is_locked' => 0];
}

// Get all features for current page
$result = $db->query("SELECT * FROM features WHERE page_id = $currentPageId OR (page_id IS NULL AND $currentPageId = 1) ORDER BY category, feature");
$features = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $features[] = $row;
}

// Get all pages
$pagesResult = $db->query("SELECT * FROM pages ORDER BY page_order, id");
$allPages = [];
while ($row = $pagesResult->fetchArray(SQLITE3_ASSOC)) {
    $allPages[] = $row;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <meta name="description" content="מערכת ניהול פיצ'רים משולבת - SysAid / SharePoint / Jira / ServiceNow">
    <title>מערכת ניהול פיצ'רים - SysAid / SharePoint / Jira / ServiceNow</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icon-192.png">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- vis-network -->
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --border-color: #e5e7eb;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            padding: 20px;
            direction: rtl;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: var(--bg-primary);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: 32px;
            position: relative;
        }
        
        h1 {
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 2.25rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: -0.025em;
        }
        
        .subtitle {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 24px;
            font-size: 1rem;
            font-weight: 400;
        }
        
        .user-info {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            text-align: center;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .search-container {
            margin-bottom: 24px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            background: var(--bg-primary);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .search-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
        }
        
        .controls {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            color: white;
            box-shadow: var(--shadow);
        }
        
        button:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        
        .btn-success {
            background: var(--success-color);
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: var(--danger-color);
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            margin-bottom: -1px;
        }
        
        .tab:hover {
            color: var(--text-primary);
            background: var(--bg-secondary);
        }
        
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th {
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 12px 16px;
            text-align: right;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid var(--border-color);
        }
        
        td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        
        tr:hover {
            background: var(--bg-secondary);
        }
        
        tr.saved {
            background: rgba(16, 185, 129, 0.1);
            transition: background 0.3s;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            background: var(--bg-primary);
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .color-input {
            width: 60px;
            height: 36px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .color-input:hover {
            border-color: var(--primary-color);
        }
        
        .delete-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .delete-btn:hover {
            background: #dc2626;
        }
        
        .add-row {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 24px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        
        .add-row:hover {
            background: #059669;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 25px;
        }
        
        .chart-container {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            padding: 24px;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .chart-container h3 {
            color: var(--text-primary);
            margin-bottom: 16px;
            font-weight: 600;
            font-size: 1.125rem;
        }
        
        .map-wrapper {
            position: relative;
            width: 100%;
            height: 700px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-top: 24px;
            overflow: hidden;
            background: var(--bg-primary);
        }
        
        .map-container {
            width: 100%;
            height: 100%;
        }
        
        .map-controls {
            position: absolute;
            top: 16px;
            left: 16px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .map-control-btn {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-primary);
            box-shadow: var(--shadow);
            transition: all 0.2s;
        }
        
        .map-control-btn:hover {
            background: var(--bg-secondary);
            box-shadow: var(--shadow-md);
        }
        
        .audit-log {
            max-height: 600px;
            overflow-y: auto;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            padding: 16px;
            border-radius: 8px;
            margin-top: 24px;
        }
        
        .audit-log::-webkit-scrollbar {
            width: 8px;
        }
        
        .audit-log::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }
        
        .audit-log::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        
        .audit-log::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
        
        .audit-item {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            background: var(--bg-primary);
            transition: background 0.2s;
        }
        
        .audit-item:hover {
            background: var(--bg-secondary);
        }
        
        .audit-item:last-child {
            border-bottom: none;
        }
        
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .container {
                padding: 20px;
            }
            
            .controls {
                flex-direction: column;
            }
            
            button {
                width: 100%;
            }
        }
        
        /* Notification animations */
        @keyframes slideIn {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(-100%);
                opacity: 0;
            }
        }
        
        /* New styles for multi-page and features */
        .title-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .title-container h1 {
            margin: 0;
            flex: 1;
            text-align: center;
        }
        
        .title-container h1:hover {
            border-bottom-color: var(--border-color);
        }
        
        .title-container h1:focus {
            outline: none;
            border-bottom-color: var(--primary-color);
        }
        
        .btn-icon {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }
        
        .page-tabs-container {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            padding: 12px;
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            align-items: center;
            overflow-x: auto;
        }
        
        .page-tabs {
            display: flex;
            gap: 8px;
            flex: 1;
            overflow-x: auto;
        }
        
        .page-tab {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .page-tab:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }
        
        .page-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .page-tab.locked::before {
            content: '🔒';
            margin-left: 4px;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        .map-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 16px;
            margin-top: 24px;
        }
        
        .map-sidebar {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            height: fit-content;
        }
        
        .map-sidebar.hidden {
            display: none;
        }
        
        .map-sidebar h3 {
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .map-sidebar .map-control-btn {
            width: 100%;
            margin-bottom: 8px;
        }
        
        @media (max-width: 1200px) {
            .map-layout {
                grid-template-columns: 1fr;
            }
            
            .map-sidebar {
                order: 2;
            }
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10002;
            direction: rtl;
        }
        
        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
        }
        
        .close-btn:hover {
            color: var(--text-primary);
        }
        
        /* Action buttons styles */
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            color: var(--text-primary);
        }
        
        .action-btn:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
            transform: scale(1.1);
        }
        
        .action-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .like-btn.active {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .dislike-btn.active {
            background: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
        }
        
        /* Feature actions modal */
        .feature-actions-modal {
            max-width: 800px;
        }
        
        .feature-actions-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .feature-actions-tab {
            padding: 8px 16px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--text-secondary);
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        
        .feature-actions-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .comment-item {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 8px;
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .tag-item {
            display: inline-block;
            background: var(--bg-secondary);
            padding: 4px 8px;
            border-radius: 4px;
            margin: 4px;
            font-size: 12px;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 8px;
        }
        
        .attachment-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
        }
        
        /* SVG Icons */
        .icon {
            width: 18px;
            height: 18px;
            display: inline-block;
            vertical-align: middle;
            fill: currentColor;
        }
        
        .icon-large {
            width: 24px;
            height: 24px;
        }
        
        .icon-small {
            width: 14px;
            height: 14px;
        }
        
        /* Footer */
        .app-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 8px;
            font-size: 10px;
            color: rgba(107, 114, 128, 0.15);
            background: transparent;
            pointer-events: none;
            z-index: 1;
            filter: blur(2px);
            user-select: none;
        }
        
        /* Clickable links */
        .clickable-link {
            color: var(--primary-color);
            text-decoration: underline;
            cursor: pointer;
        }
        
        .clickable-link:hover {
            color: var(--primary-hover);
        }
        
        /* Auto-link styles */
        .auto-link {
            color: var(--primary-color);
            text-decoration: underline;
            cursor: pointer;
        }
        
        .auto-link:hover {
            color: var(--primary-hover);
        }
    </style>
    
    <!-- SVG Icons Sprite -->
    <svg style="display: none;">
        <defs>
            <symbol id="icon-settings" viewBox="0 0 24 24">
                <path d="M12 15.5A3.5 3.5 0 0 1 8.5 12A3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5a3.5 3.5 0 0 1-3.5 3.5m7.43-2.53c.04-.32.07-.64.07-.97c0-.33-.03-.66-.07-1l2.11-1.63c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.31-.61-.22l-2.49 1c-.52-.4-1.06-.73-1.69-.98l-.37-2.65A.506.506 0 0 0 14 2h-4c-.25 0-.46.18-.5.42l-.37 2.65c-.63.25-1.17.59-1.69.98l-2.49-1c-.22-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64L4.57 11c-.04.34-.07.67-.07 1c0 .33.03.65.07.97l-2.11 1.66c-.19.15-.25.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1.01c.52.4 1.06.74 1.69.99l.37 2.65c.04.24.25.42.5.42h4c.25 0 .46-.18.5-.42l.37-2.65c.63-.26 1.17-.59 1.69-.99l2.49 1.01c.22.08.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.66Z"/>
            </symbol>
            <symbol id="icon-like" viewBox="0 0 24 24">
                <path d="M23 10c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2zM1 21h4V9H1v12z"/>
            </symbol>
            <symbol id="icon-dislike" viewBox="0 0 24 24">
                <path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/>
            </symbol>
            <symbol id="icon-comment" viewBox="0 0 24 24">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
            </symbol>
            <symbol id="icon-share" viewBox="0 0 24 24">
                <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.23-.09.46-.09.7 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/>
            </symbol>
            <symbol id="icon-attachment" viewBox="0 0 24 24">
                <path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>
            </symbol>
            <symbol id="icon-link" viewBox="0 0 24 24">
                <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
            </symbol>
            <symbol id="icon-tag" viewBox="0 0 24 24">
                <path d="M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7.01v9.98c0 1.11.9 2.01 2 2.01L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z"/>
            </symbol>
            <symbol id="icon-page" viewBox="0 0 24 24">
                <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
            </symbol>
            <symbol id="icon-delete" viewBox="0 0 24 24">
                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
            </symbol>
            <symbol id="icon-refresh" viewBox="0 0 24 24">
                <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
            </symbol>
            <symbol id="icon-add" viewBox="0 0 24 24">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
            </symbol>
            <symbol id="icon-download" viewBox="0 0 24 24">
                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
            </symbol>
            <symbol id="icon-lock" viewBox="0 0 24 24">
                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
            </symbol>
            <symbol id="icon-unlock" viewBox="0 0 24 24">
                <path d="M12 17c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6-9h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM8.9 6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2H8.9V6z"/>
            </symbol>
            <symbol id="icon-users" viewBox="0 0 24 24">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </symbol>
            <symbol id="icon-table" viewBox="0 0 24 24">
                <path d="M3 3h18v2H3V3zm0 4h18v2H3V7zm0 4h18v2H3v-2zm0 4h18v2H3v-2zm0 4h18v2H3v-2z"/>
            </symbol>
            <symbol id="icon-chart" viewBox="0 0 24 24">
                <path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/>
            </symbol>
            <symbol id="icon-map" viewBox="0 0 24 24">
                <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
            </symbol>
            <symbol id="icon-audit" viewBox="0 0 24 24">
                <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
            </symbol>
            <symbol id="icon-search" viewBox="0 0 24 24">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </symbol>
        </defs>
    </svg>
</head>
<body>
    <div class="container">
        <!-- Editable Title -->
        <div class="title-container">
            <h1 id="page-title" contenteditable="true" onblur="updatePageTitle()" style="cursor: text; border-bottom: 2px dashed transparent; padding-bottom: 4px;"><?php echo htmlspecialchars($currentPage['title']); ?></h1>
            <button class="btn-icon" onclick="togglePageLock()" id="lock-btn" title="נעל/פתח דף">
                <svg class="icon"><use href="#icon-<?php echo $currentPage['is_locked'] ? 'lock' : 'unlock'; ?>"></use></svg>
            </button>
            <button class="btn-icon" onclick="showColumnManager()" title="נהל עמודות">
                <svg class="icon"><use href="#icon-settings"></use></svg>
            </button>
            <button class="btn-icon" onclick="showPermissionsModal()" title="הרשאות">
                <svg class="icon"><use href="#icon-users"></use></svg>
            </button>
        </div>
        <p class="subtitle">SysAid / SharePoint / Jira / ServiceNow</p>
        
        <div class="user-info">
            <svg class="icon" style="margin-left: 4px;"><use href="#icon-users"></use></svg>
            משתמש: <strong><?php echo htmlspecialchars($user); ?></strong>
        </div>
        
        <!-- Page Tabs (Bottom) -->
        <div class="page-tabs-container" id="page-tabs-container">
            <div class="page-tabs" id="page-tabs"></div>
            <button class="btn-success btn-small" onclick="createNewPage()">
                <svg class="icon icon-small" style="margin-left: 4px;"><use href="#icon-add"></use></svg>
                דף חדש
            </button>
        </div>
        
        <div class="controls">
            <button class="btn-primary" onclick="loadSysAid()">
                <svg class="icon" style="margin-left: 4px;"><use href="#icon-refresh"></use></svg>
                טען פיצ'רי SysAid
            </button>
            <button class="btn-primary" onclick="loadSharePoint()">
                <svg class="icon" style="margin-left: 4px;"><use href="#icon-refresh"></use></svg>
                טען פיצ'רי SharePoint
            </button>
            <button class="btn-success" onclick="addNewRow()">
                <svg class="icon" style="margin-left: 4px;"><use href="#icon-add"></use></svg>
                הוסף שורה חדשה
            </button>
            <button class="btn-primary" onclick="refreshData()">
                <svg class="icon" style="margin-left: 4px;"><use href="#icon-refresh"></use></svg>
                רענן נתונים
            </button>
            <button class="btn-success" onclick="downloadReport()">
                <svg class="icon" style="margin-left: 4px;"><use href="#icon-download"></use></svg>
                הורד דוח
            </button>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('table')">
                <svg class="icon icon-small" style="margin-left: 4px;"><use href="#icon-table"></use></svg>
                טבלת פיצ'רים
            </button>
            <button class="tab" onclick="showTab('dashboard')">
                <svg class="icon icon-small" style="margin-left: 4px;"><use href="#icon-chart"></use></svg>
                דשבורד
            </button>
            <button class="tab" onclick="showTab('map')">
                <svg class="icon icon-small" style="margin-left: 4px;"><use href="#icon-map"></use></svg>
                מפת פיצ'רים
            </button>
            <button class="tab" onclick="showTab('audit')">
                <svg class="icon icon-small" style="margin-left: 4px;"><use href="#icon-audit"></use></svg>
                לוג Audit
            </button>
        </div>
        
        <!-- Table Tab -->
        <div id="table-tab" class="tab-content active">
            <div class="search-container">
                <input type="text" id="search-input" class="search-input" placeholder="חפש פיצ'רים, קטגוריות, תיאורים..." onkeyup="filterTable()">
                <span class="search-icon">
                    <svg class="icon"><use href="#icon-search"></use></svg>
                </span>
            </div>
            <table id="features-table">
                <thead>
                    <tr>
                        <th>קטגוריה</th>
                        <th>שם פיצ'ר</th>
                        <th>תיאור</th>
                        <th>צבע</th>
                        <th>משתמש</th>
                        <th>עודכן</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody id="features-tbody">
                    <?php foreach ($features as $feat): ?>
                    <tr data-id="<?php echo $feat['id']; ?>" style="background-color: <?php echo $feat['color']; ?>20;">
                        <td>
                            <select class="editable" data-field="category" onchange="saveField(this)">
                                <option value="SysAid" <?php echo $feat['category'] === 'SysAid' ? 'selected' : ''; ?>>SysAid</option>
                                <option value="SharePoint" <?php echo $feat['category'] === 'SharePoint' ? 'selected' : ''; ?>>SharePoint</option>
                                <option value="Jira" <?php echo $feat['category'] === 'Jira' ? 'selected' : ''; ?>>Jira</option>
                                <option value="ServiceNow" <?php echo $feat['category'] === 'ServiceNow' ? 'selected' : ''; ?>>ServiceNow</option>
                            </select>
                        </td>
                        <td><input type="text" class="editable" data-field="feature" value="<?php echo htmlspecialchars($feat['feature']); ?>" onblur="saveField(this)" /></td>
                        <td>
                            <div class="description-wrapper" data-field="description" data-id="<?php echo $feat['id']; ?>">
                                <div class="description-display" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; min-height: 40px; cursor: text;" onclick="editDescription(this)"><?php echo htmlspecialchars($feat['description']); ?></div>
                                <textarea class="editable description-edit" data-field="description" onblur="saveFieldAndHide(this)" rows="2" style="display: none;"><?php echo htmlspecialchars($feat['description']); ?></textarea>
                            </div>
                        </td>
                        <td><input type="color" class="color-input editable" data-field="color" value="<?php echo htmlspecialchars($feat['color']); ?>" onchange="saveField(this)" /></td>
                        <td><?php echo htmlspecialchars($feat['user']); ?></td>
                        <td><?php echo htmlspecialchars($feat['updated_at']); ?></td>
                        <td>
                            <div class="action-buttons" style="display: flex; gap: 4px; flex-wrap: wrap;">
                                <button class="action-btn" onclick="showFeatureActions(<?php echo $feat['id']; ?>)" title="פעולות">
                                    <svg class="icon icon-small"><use href="#icon-settings"></use></svg>
                                </button>
                                <button class="action-btn like-btn" onclick="toggleLike(<?php echo $feat['id']; ?>, 'like')" id="like-btn-<?php echo $feat['id']; ?>" title="לייק">
                                    <svg class="icon icon-small"><use href="#icon-like"></use></svg>
                                </button>
                                <button class="action-btn dislike-btn" onclick="toggleLike(<?php echo $feat['id']; ?>, 'dislike')" id="dislike-btn-<?php echo $feat['id']; ?>" title="דיסלייק">
                                    <svg class="icon icon-small"><use href="#icon-dislike"></use></svg>
                                </button>
                                <button class="action-btn" onclick="showComments(<?php echo $feat['id']; ?>)" title="תגובות">
                                    <svg class="icon icon-small"><use href="#icon-comment"></use></svg>
                                </button>
                                <button class="action-btn" onclick="showShareModal(<?php echo $feat['id']; ?>)" title="שתף">
                                    <svg class="icon icon-small"><use href="#icon-share"></use></svg>
                                </button>
                                <button class="action-btn" onclick="showAttachments(<?php echo $feat['id']; ?>)" title="קבצים">
                                    <svg class="icon icon-small"><use href="#icon-attachment"></use></svg>
                                </button>
                                <button class="action-btn" onclick="showConnections(<?php echo $feat['id']; ?>)" title="חיבורים">
                                    <svg class="icon icon-small"><use href="#icon-link"></use></svg>
                                </button>
                                <button class="action-btn" onclick="showTags(<?php echo $feat['id']; ?>)" title="תגיות">
                                    <svg class="icon icon-small"><use href="#icon-tag"></use></svg>
                                </button>
                                <button class="action-btn" onclick="showMoveFeature(<?php echo $feat['id']; ?>)" title="העבר דף">
                                    <svg class="icon icon-small"><use href="#icon-page"></use></svg>
                                </button>
                                <button class="delete-btn" onclick="deleteRow(<?php echo $feat['id']; ?>)" title="מחק">
                                    <svg class="icon icon-small"><use href="#icon-delete"></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="add-row" onclick="addNewRow()">
                <svg class="icon" style="margin-left: 4px;"><use href="#icon-add"></use></svg>
                הוסף שורה חדשה
            </button>
        </div>
        
        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-content">
            <div class="dashboard">
                <div class="chart-container">
                    <h3>חלוקה לפי קטגוריות</h3>
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>מגמות עדכון</h3>
                    <canvas id="updateChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Map Tab -->
        <div id="map-tab" class="tab-content">
            <div class="map-layout">
                <div class="map-sidebar" id="map-sidebar">
                    <h3>עריכת מפה</h3>
                    <button class="map-control-btn" onclick="toggleMapEditMode()" id="map-edit-btn">✏️ מצב עריכה</button>
                    <button class="map-control-btn" onclick="addMapNode()">➕ הוסף צומת</button>
                    <button class="map-control-btn" onclick="addMapEdge()">🔗 הוסף קישור</button>
                    <button class="map-control-btn" onclick="deleteSelectedNode()">🗑️ מחק נבחר</button>
                </div>
                <div class="map-wrapper">
                    <div class="map-controls">
                        <button class="map-control-btn" onclick="fitMap()">📐 התאם למסך</button>
                        <button class="map-control-btn" onclick="centerMap()">🎯 מרכז</button>
                        <button class="map-control-btn" onclick="resetMap()">🔄 איפוס</button>
                        <button class="map-control-btn" onclick="toggleMapSidebar()">📋 הצג/הסתר תפריט</button>
                    </div>
                    <div class="map-container" id="feature-map"></div>
                </div>
            </div>
        </div>
        
        <!-- Audit Tab -->
        <div id="audit-tab" class="tab-content">
            <h3>לוג פעולות (Audit Log)</h3>
            <div class="audit-log" id="audit-log">
                <p>טוען...</p>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="app-footer">מערכות מידע & yprods</div>
    
    <script>
        // Auto-link function - makes phone numbers, emails, and URLs clickable
        function autoLink(text) {
            if (!text) return text;
            
            // Phone numbers (Israeli format: 05X-XXXXXXX or 0X-XXXXXXX)
            text = text.replace(/(\b0[2-9]\d{1,2}[-]?\d{7}\b)/g, '<a href="tel:$1" class="auto-link" onclick="event.stopPropagation()">$1</a>');
            
            // Email addresses
            text = text.replace(/(\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b)/g, '<a href="mailto:$1" class="auto-link" onclick="event.stopPropagation()">$1</a>');
            
            // URLs (http, https, ftp)
            text = text.replace(/(https?:\/\/[^\s]+)/gi, '<a href="$1" target="_blank" class="auto-link" onclick="event.stopPropagation()">$1</a>');
            
            // Windows file paths (C:\... or \\server\...)
            text = text.replace(/([A-Z]:\\[^\s]+|\\\\[^\s]+)/gi, function(match) {
                return '<a href="file:///' + match.replace(/\\/g, '/') + '" class="auto-link" onclick="event.stopPropagation()">' + match + '</a>';
            });
            
            return text;
        }
        
        // Edit description - switch from display to edit mode
        function editDescription(displayEl) {
            if (isPageLocked) {
                showNotification('❌ הדף נעול - לא ניתן לערוך', 'error');
                return;
            }
            
            const wrapper = displayEl.closest('.description-wrapper');
            const textarea = wrapper.querySelector('.description-edit');
            const display = wrapper.querySelector('.description-display');
            
            if (!textarea || !display) return;
            
            // Get text content (strip HTML from links)
            const textContent = display.textContent || display.innerText || '';
            
            display.style.display = 'none';
            textarea.style.display = 'block';
            textarea.value = textContent;
            textarea.focus();
        }
        
        // Save field and switch back to display mode
        function saveFieldAndHide(element) {
            const wrapper = element.closest('.description-wrapper');
            const display = wrapper.querySelector('.description-display');
            const newValue = element.value;
            const row = element.closest('tr');
            
            // Get the ID from row or wrapper
            const id = row ? row.dataset.id : (wrapper ? wrapper.dataset.id : null);
            if (!id) {
                // New row - just update display
                if (display) {
                    display.innerHTML = autoLink(newValue);
                    display.style.display = 'block';
                    element.style.display = 'none';
                }
                return;
            }
            
            // Save the field
            if (!checkEditPermission()) {
                return;
            }
            
            // Add loading state
            element.style.opacity = '0.6';
            element.style.pointerEvents = 'none';
            
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('id', id);
            formData.append('field', 'description');
            formData.append('value', newValue);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (row) {
                        row.classList.add('saved');
                        setTimeout(() => row.classList.remove('saved'), 2000);
                    }
                    
                    // Update display with auto-linked content
                    if (display) {
                        display.innerHTML = autoLink(newValue);
                        display.style.display = 'block';
                        element.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ שגיאה בשמירה', 'error');
            })
            .finally(() => {
                element.style.opacity = '1';
                element.style.pointerEvents = 'auto';
            });
        }
        
        // Apply auto-linking to all text content on page load
        function applyAutoLinks() {
            // Apply to description display fields
            document.querySelectorAll('.description-display').forEach(el => {
                if (el.textContent && !el.dataset.linked) {
                    el.innerHTML = autoLink(el.textContent);
                    el.dataset.linked = 'true';
                }
            });
            
            // Apply to comment items
            document.querySelectorAll('.comment-item div:not(.comment-header)').forEach(el => {
                if (el.textContent && !el.dataset.linked) {
                    el.innerHTML = autoLink(el.textContent);
                    el.dataset.linked = 'true';
                }
            });
        }
        
        // Service Worker Registration for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
        // Global variables
        let categoryChart = null;
        let updateChart = null;
        let network = null;
        
        // Tab switching
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
            
            if (tabName === 'dashboard') {
                loadDashboard();
            } else if (tabName === 'map') {
                loadMap();
            } else if (tabName === 'audit') {
                loadAudit();
            }
        }
        
        // Save field
        function saveField(element) {
            if (!checkEditPermission()) {
                if (element.value !== undefined) {
                    element.value = element.defaultValue || '';
                }
                return;
            }
            
            const row = element.closest('tr');
            const id = row.dataset.id;
            const field = element.dataset.field;
            const value = element.value || element.textContent || '';
            
            // Add loading state
            if (element.style) {
                element.style.opacity = '0.6';
                element.style.pointerEvents = 'none';
            }
            
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('id', id);
            formData.append('field', field);
            formData.append('value', value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.classList.add('saved');
                    setTimeout(() => row.classList.remove('saved'), 2000);
                    
                    // Update row background color if color field changed
                    if (field === 'color') {
                        row.style.backgroundColor = value + '20';
                    }
                    
                    // Show success indicator
                    const indicator = document.createElement('span');
                    indicator.innerHTML = '✓';
                    indicator.style.cssText = `
                        position: absolute;
                        color: #4caf50;
                        font-weight: bold;
                        font-size: 20px;
                        animation: fadeInOut 1s ease;
                    `;
                    const parent = element.parentElement || element.closest('td');
                    if (parent) {
                        parent.style.position = 'relative';
                        parent.appendChild(indicator);
                        setTimeout(() => indicator.remove(), 1000);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ שגיאה בשמירה', 'error');
            })
            .finally(() => {
                if (element.style) {
                    element.style.opacity = '1';
                    element.style.pointerEvents = 'auto';
                }
            });
        }
        
        // Store callback globally
        let pinCallback = null;
        
        // Show PIN modal
        function showPinModal(callback) {
            pinCallback = callback;
            const modal = document.createElement('div');
            modal.id = 'pin-modal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10001;
                direction: rtl;
            `;
            
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                padding: 32px;
                border-radius: 8px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                max-width: 400px;
                width: 90%;
            `;
            
            modalContent.innerHTML = `
                <h3 style="margin-bottom: 16px; color: #1f2937; font-size: 1.25rem; font-weight: 600;">אימות PIN</h3>
                <p style="margin-bottom: 16px; color: #6b7280; font-size: 0.875rem;">אנא הזן קוד PIN למחיקה:</p>
                <input type="password" id="pin-input" placeholder="הזן PIN" style="width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 16px; text-align: center; letter-spacing: 4px; margin-bottom: 16px;" autofocus>
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button id="pin-cancel-btn" style="padding: 10px 20px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-weight: 500;">ביטול</button>
                    <button id="pin-submit-btn" style="padding: 10px 20px; border: none; background: #ef4444; color: white; border-radius: 6px; cursor: pointer; font-weight: 500;">אישור</button>
                </div>
            `;
            
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            
            // Focus on input
            setTimeout(() => {
                document.getElementById('pin-input').focus();
            }, 100);
            
            // Close on ESC
            modal.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePinModal();
                }
            });
            
            // Submit on Enter
            document.getElementById('pin-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    verifyPin();
                }
            });
            
            // Button handlers
            document.getElementById('pin-cancel-btn').addEventListener('click', closePinModal);
            document.getElementById('pin-submit-btn').addEventListener('click', verifyPin);
        }
        
        // Verify PIN
        function verifyPin() {
            const pinInput = document.getElementById('pin-input');
            const pin = pinInput.value;
            const correctPin = '4231';
            
            if (pin === correctPin) {
                closePinModal();
                if (pinCallback) {
                    pinCallback();
                    pinCallback = null;
                }
            } else {
                pinInput.style.borderColor = '#ef4444';
                pinInput.value = '';
                pinInput.placeholder = 'PIN שגוי - נסה שוב';
                setTimeout(() => {
                    pinInput.style.borderColor = '#e5e7eb';
                    pinInput.placeholder = 'הזן PIN';
                }, 2000);
            }
        }
        
        // Close PIN modal
        function closePinModal() {
            const modal = document.getElementById('pin-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Delete row
        function deleteRow(id) {
            showPinModal(() => {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-100px)';
                
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setTimeout(() => {
                            row.remove();
                            showNotification('✅ השורה נמחקה בהצלחה', 'success');
                        }, 500);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                    showNotification('❌ שגיאה במחיקה', 'error');
                });
            });
        }
        
        // Add new row
        function addNewRow() {
            const tbody = document.getElementById('features-tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <select class="editable" data-field="category" onchange="saveNewRow(this)">
                        <option value="SysAid">SysAid</option>
                        <option value="SharePoint">SharePoint</option>
                        <option value="Jira">Jira</option>
                        <option value="ServiceNow">ServiceNow</option>
                    </select>
                </td>
                <td><input type="text" class="editable" data-field="feature" onblur="saveNewRow(this)" placeholder="שם פיצ'ר" /></td>
                <td>
                    <div class="description-wrapper" data-field="description">
                        <div class="description-display" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; min-height: 40px; cursor: text; display: none;" onclick="editDescription(this)"></div>
                        <textarea class="editable description-edit" data-field="description" onblur="saveNewRow(this)" rows="2" placeholder="תיאור"></textarea>
                    </div>
                </td>
                <td><input type="color" class="color-input editable" data-field="color" value="#3498db" onchange="saveNewRow(this)" /></td>
                <td>-</td>
                <td>-</td>
                <td><button class="delete-btn" onclick="this.closest('tr').remove()">
                    <svg class="icon icon-small"><use href="#icon-delete"></use></svg>
                    ביטול
                </button></td>
            `;
            tbody.appendChild(row);
            row.querySelector('input[data-field="feature"]').focus();
        }
        
        // Save new row
        function saveNewRow(element) {
            if (isPageLocked) {
                showNotification('❌ הדף נעול - לא ניתן לערוך', 'error');
                return;
            }
            
            const row = element.closest('tr');
            if (row.dataset.id) return; // Already saved
            
            const category = row.querySelector('[data-field="category"]').value;
            const feature = row.querySelector('[data-field="feature"]').value;
            const descriptionEl = row.querySelector('[data-field="description"]');
            const description = descriptionEl ? (descriptionEl.querySelector('.description-edit')?.value || descriptionEl.querySelector('textarea')?.value || '') : '';
            const color = row.querySelector('[data-field="color"]').value;
            
            if (!feature.trim()) return;
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('category', category);
            formData.append('feature', feature);
            formData.append('description', description);
            formData.append('color', color);
            formData.append('page_id', currentPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.dataset.id = data.id;
                    row.querySelector('td:nth-child(5)').textContent = '-';
                    row.querySelector('td:nth-child(6)').textContent = new Date().toLocaleString('he-IL');
                    row.style.backgroundColor = color + '20';
                    row.classList.add('saved');
                    setTimeout(() => row.classList.remove('saved'), 2000);
                    
                    // Update description display if exists
                    const descWrapper = row.querySelector('.description-wrapper');
                    if (descWrapper) {
                        const display = descWrapper.querySelector('.description-display');
                        const textarea = descWrapper.querySelector('.description-edit');
                        if (display && textarea) {
                            display.innerHTML = autoLink(description);
                            display.style.display = 'block';
                            textarea.style.display = 'none';
                        }
                    }
                    
                    showNotification('✅ השורה נוספה בהצלחה!', 'success');
                    setTimeout(() => refreshData(), 1000);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Check permissions before actions
        function checkEditPermission() {
            if (isPageLocked) {
                showNotification('❌ הדף נעול - לא ניתן לערוך', 'error');
                return false;
            }
            return true;
        }
        
        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                left: 20px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                padding: 12px 20px;
                border-radius: 6px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                z-index: 10000;
                font-weight: 500;
                font-size: 14px;
                animation: slideIn 0.3s ease;
                direction: rtl;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Load SysAid features
        function loadSysAid() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ טוען...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'load_sysaid');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`✅ נטענו ${data.added} פיצ'רים חדשים של SysAid!`, 'success');
                    setTimeout(() => refreshData(), 1000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ שגיאה בטעינת הנתונים', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Load SharePoint features
        function loadSharePoint() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ טוען...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'load_sharepoint');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`✅ נטענו ${data.added} פיצ'רים חדשים של SharePoint!`, 'success');
                    setTimeout(() => refreshData(), 1000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ שגיאה בטעינת הנתונים', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Refresh data
        function refreshData() {
            location.reload();
        }
        
        // Load dashboard
        function loadDashboard() {
            const formData = new FormData();
            formData.append('action', 'get_stats');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const labels = data.map(item => item.category);
                const counts = data.map(item => parseInt(item.count));
                const colors = [
                    'rgba(37, 99, 235, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(107, 114, 128, 0.8)'
                ];
                const borderColors = [
                    '#2563eb',
                    '#10b981',
                    '#f59e0b',
                    '#8b5cf6',
                    '#ef4444',
                    '#6b7280'
                ];
                
                // Category chart
                const ctx1 = document.getElementById('categoryChart');
                if (categoryChart) categoryChart.destroy();
                categoryChart = new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: counts,
                            backgroundColor: colors.slice(0, labels.length),
                            borderColor: borderColors.slice(0, labels.length),
                            borderWidth: 3,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 14,
                                        weight: '600',
                                        family: 'Poppins'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 15,
                                titleFont: {
                                    size: 16,
                                    weight: '700',
                                    family: 'Poppins'
                                },
                                bodyFont: {
                                    size: 14,
                                    family: 'Poppins'
                                },
                                cornerRadius: 10
                            }
                        },
                        animation: {
                            animateRotate: true,
                            animateScale: true,
                            duration: 1500
                        }
                    }
                });
                
                // Update chart (simplified - would need date data)
                const ctx2 = document.getElementById('updateChart');
                if (updateChart) updateChart.destroy();
                updateChart = new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'מספר פיצ\'רים',
                            data: counts,
                            backgroundColor: colors.slice(0, labels.length),
                            borderColor: borderColors.slice(0, labels.length),
                            borderWidth: 2,
                            borderRadius: 10,
                            borderSkipped: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 15,
                                titleFont: {
                                    size: 16,
                                    weight: '700',
                                    family: 'Poppins'
                                },
                                bodyFont: {
                                    size: 14,
                                    family: 'Poppins'
                                },
                                cornerRadius: 10
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        family: 'Poppins',
                                        size: 12,
                                        weight: '600'
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        family: 'Poppins',
                                        size: 12,
                                        weight: '600'
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        animation: {
                            duration: 1500,
                            easing: 'easeInOutQuart'
                        }
                    }
                });
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Map control functions
        function fitMap() {
            if (network) {
                network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
            }
        }
        
        function centerMap() {
            if (network) {
                network.moveTo({ position: { x: 0, y: 0 }, scale: 1, animation: { duration: 500 } });
            }
        }
        
        function resetMap() {
            if (network) {
                network.setOptions({
                    layout: {
                        hierarchical: {
                            enabled: true,
                            direction: 'UD',
                            sortMethod: 'directed'
                        }
                    }
                });
                setTimeout(() => {
                    network.fit({ animation: { duration: 500 } });
                }, 100);
            }
        }
        
        // Search functionality
        function filterTable() {
            const input = document.getElementById('search-input');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('features-table');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < td.length; j++) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }
        
        // Load audit
        function loadAudit() {
            const formData = new FormData();
            formData.append('action', 'get_audit');
            formData.append('limit', 100);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const logDiv = document.getElementById('audit-log');
                if (data.length === 0) {
                    logDiv.innerHTML = '<p>אין רשומות audit</p>';
                    return;
                }
                
                logDiv.innerHTML = data.map(item => `
                    <div class="audit-item">
                        <strong>${item.action}</strong> | 
                        ${item.category ? item.category + ' - ' + item.feature : 'N/A'} | 
                        ${item.field_name || ''} | 
                        משתמש: ${item.user} | 
                        ${item.created_at}
                        ${item.old_value ? '<br><small>ישן: ' + item.old_value + ' → חדש: ' + item.new_value + '</small>' : ''}
                    </div>
                `).join('');
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Global variables for new features
        let currentPageId = <?php echo $currentPageId; ?>;
        let mapEditMode = false;
        let selectedNodeId = null;
        let pages = <?php echo json_encode($allPages); ?>;
        let isPageLocked = <?php echo $currentPage['is_locked'] ? 'true' : 'false'; ?>;
        
        // Page management functions
        function loadPages() {
            const formData = new FormData();
            formData.append('action', 'get_pages');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                pages = data;
                renderPageTabs();
                if (pages.length > 0 && !currentPageId) {
                    currentPageId = pages[0].id;
                    loadPageData(currentPageId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function renderPageTabs() {
            const container = document.getElementById('page-tabs');
            container.innerHTML = '';
            
            pages.forEach(page => {
                const tab = document.createElement('div');
                tab.className = 'page-tab' + (page.id == currentPageId ? ' active' : '') + (page.is_locked ? ' locked' : '');
                tab.innerHTML = `<span>${page.title}</span>`;
                if (page.id != currentPageId) {
                    const closeBtn = document.createElement('span');
                    closeBtn.innerHTML = '×';
                    closeBtn.style.cssText = 'margin-right: 8px; cursor: pointer; font-size: 18px;';
                    closeBtn.onclick = (e) => {
                        e.stopPropagation();
                        deletePage(page.id);
                    };
                    tab.appendChild(closeBtn);
                }
                tab.onclick = () => switchPage(page.id);
                container.appendChild(tab);
            });
        }
        
        function switchPage(pageId) {
            currentPageId = pageId;
            window.location.href = '?page_id=' + pageId;
        }
        
        function loadPageData(pageId) {
            const formData = new FormData();
            formData.append('action', 'get_data');
            formData.append('page_id', pageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Filter data by page_id
                const pageData = data.filter(item => (item.page_id || 1) == pageId);
                refreshTable(pageData);
            })
            .catch(error => console.error('Error:', error));
        }
        
        function createNewPage() {
            const title = prompt('הזן שם לדף החדש:', 'דף חדש');
            if (!title) return;
            
            const formData = new FormData();
            formData.append('action', 'create_page');
            formData.append('title', title);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ הדף נוצר בהצלחה!', 'success');
                    loadPages();
                    switchPage(data.id);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function deletePage(pageId) {
            if (!confirm('האם אתה בטוח שברצונך למחוק דף זה?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_page');
            formData.append('page_id', pageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ הדף נמחק בהצלחה', 'success');
                    loadPages();
                    if (currentPageId == pageId && pages.length > 0) {
                        switchPage(pages[0].id);
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function updatePageTitle() {
            const titleElement = document.getElementById('page-title');
            const title = titleElement.textContent.trim();
            
            const formData = new FormData();
            formData.append('action', 'update_page_title');
            formData.append('page_id', currentPageId);
            formData.append('title', title);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPages();
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function togglePageLock() {
            const formData = new FormData();
            formData.append('action', 'toggle_page_lock');
            formData.append('page_id', currentPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPages();
                    const page = pages.find(p => p.id == currentPageId);
                    isPageLocked = page && page.is_locked;
                    const lockBtn = document.getElementById('lock-btn');
                    lockBtn.innerHTML = `<svg class="icon"><use href="#icon-${isPageLocked ? 'lock' : 'unlock'}"></use></svg>`;
                    
                    // Disable/enable editing based on lock
                    document.querySelectorAll('.editable').forEach(el => {
                        el.disabled = isPageLocked;
                        el.style.opacity = isPageLocked ? '0.6' : '1';
                    });
                    
                    showNotification(isPageLocked ? '🔒 הדף ננעל' : '🔓 הדף נפתח', 'success');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Column management
        function showColumnManager() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'column-manager-modal';
            
            const formData = new FormData();
            formData.append('action', 'get_custom_columns');
            formData.append('page_id', currentPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(columns => {
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>ניהול עמודות</h3>
                            <button class="close-btn" onclick="closeModal('column-manager-modal')">×</button>
                        </div>
                        <div>
                            <h4>עמודות מותאמות</h4>
                            <div id="columns-list">
                                ${columns.map(col => `
                                    <div style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
                                        <input type="text" value="${col.column_name}" style="flex: 1; padding: 8px;" onchange="updateColumnName(${col.id}, this.value)">
                                        <select onchange="updateColumnType(${col.id}, this.value)" style="padding: 8px;">
                                            <option value="text" ${col.column_type === 'text' ? 'selected' : ''}>טקסט</option>
                                            <option value="number" ${col.column_type === 'number' ? 'selected' : ''}>מספר</option>
                                            <option value="date" ${col.column_type === 'date' ? 'selected' : ''}>תאריך</option>
                                        </select>
                                        <button class="delete-btn" onclick="deleteColumn(${col.id})">🗑️</button>
                                    </div>
                                `).join('')}
                            </div>
                            <div style="margin-top: 16px;">
                                <input type="text" id="new-column-name" placeholder="שם עמודה חדשה" style="padding: 8px; width: 200px; margin-left: 8px;">
                                <select id="new-column-type" style="padding: 8px; margin-left: 8px;">
                                    <option value="text">טקסט</option>
                                    <option value="number">מספר</option>
                                    <option value="date">תאריך</option>
                                </select>
                                <button class="btn-success" onclick="addColumn(event)">➕ הוסף עמודה</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            })
            .catch(error => console.error('Error:', error));
        }
        
        function addColumn(event) {
            // Find the modal first
            const modal = document.getElementById('column-manager-modal');
            if (!modal) {
                showNotification('❌ שגיאה: לא נמצא חלון ניהול עמודות', 'error');
                return;
            }
            
            // Find inputs within the modal
            const nameInput = modal.querySelector('#new-column-name');
            const typeSelect = modal.querySelector('#new-column-type');
            
            if (!nameInput || !typeSelect) {
                showNotification('❌ שגיאה: לא נמצאו שדות הטופס', 'error');
                return;
            }
            
            const name = nameInput.value.trim();
            const type = typeSelect.value;
            
            if (!name) {
                showNotification('❌ אנא הזן שם לעמודה', 'error');
                nameInput.focus();
                return;
            }
            
            // Disable button during request
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ מוסיף...';
            
            const formData = new FormData();
            formData.append('action', 'add_custom_column');
            formData.append('page_id', currentPageId);
            formData.append('column_name', name);
            formData.append('column_type', type);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('✅ העמודה נוספה בהצלחה!', 'success');
                    nameInput.value = ''; // Clear input
                    // Reload column manager
                    closeModal('column-manager-modal');
                    setTimeout(() => {
                        showColumnManager();
                        refreshData();
                    }, 500);
                } else {
                    showNotification('❌ שגיאה בהוספת העמודה: ' + (data.message || 'שגיאה לא ידועה'), 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error adding column:', error);
                showNotification('❌ שגיאה בהוספת העמודה: ' + error.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
        
        function deleteColumn(columnId) {
            const formData = new FormData();
            formData.append('action', 'delete_custom_column');
            formData.append('column_id', columnId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ העמודה נמחקה', 'success');
                    closeModal('column-manager-modal');
                    showColumnManager();
                    refreshData();
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function updateColumnName(columnId, name) {
            const formData = new FormData();
            formData.append('action', 'update_custom_column');
            formData.append('column_id', columnId);
            formData.append('column_name', name);
            formData.append('column_type', document.querySelector(`select[onchange*="${columnId}"]`).value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ שם העמודה עודכן', 'success');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function updateColumnType(columnId, type) {
            const formData = new FormData();
            formData.append('action', 'update_custom_column');
            formData.append('column_id', columnId);
            formData.append('column_type', type);
            formData.append('column_name', document.querySelector(`input[onchange*="${columnId}"]`).value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ סוג העמודה עודכן', 'success');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Permissions management
        function showPermissionsModal() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'permissions-modal';
            
            const formData = new FormData();
            formData.append('action', 'get_page_permissions');
            formData.append('page_id', currentPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(permissions => {
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>ניהול הרשאות</h3>
                            <button class="close-btn" onclick="closeModal('permissions-modal')">×</button>
                        </div>
                        <div>
                            <h4>הרשאות משתמשים</h4>
                            <div id="permissions-list">
                                ${permissions.map(perm => `
                                    <div style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
                                        <span style="flex: 1;">${perm.username}</span>
                                        <label><input type="checkbox" ${perm.can_view ? 'checked' : ''} onchange="updatePermission('${perm.username}', 'view', this.checked)"> צפייה</label>
                                        <label><input type="checkbox" ${perm.can_edit ? 'checked' : ''} onchange="updatePermission('${perm.username}', 'edit', this.checked)"> עריכה</label>
                                    </div>
                                `).join('')}
                            </div>
                            <div style="margin-top: 16px;">
                                <input type="text" id="new-user-name" placeholder="שם משתמש" style="padding: 8px; width: 200px; margin-left: 8px;">
                                <label><input type="checkbox" id="new-user-view" checked> צפייה</label>
                                <label><input type="checkbox" id="new-user-edit"> עריכה</label>
                                <button class="btn-success" onclick="addPermission()">➕ הוסף הרשאה</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            })
            .catch(error => console.error('Error:', error));
        }
        
        function addPermission() {
            const username = document.getElementById('new-user-name').value;
            const canView = document.getElementById('new-user-view').checked ? 1 : 0;
            const canEdit = document.getElementById('new-user-edit').checked ? 1 : 0;
            if (!username.trim()) return;
            
            const formData = new FormData();
            formData.append('action', 'set_page_permission');
            formData.append('page_id', currentPageId);
            formData.append('username', username);
            formData.append('can_view', canView);
            formData.append('can_edit', canEdit);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ ההרשאה נוספה בהצלחה!', 'success');
                    closeModal('permissions-modal');
                    showPermissionsModal();
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function updatePermission(username, type, value) {
            const formData = new FormData();
            formData.append('action', 'get_page_permissions');
            formData.append('page_id', currentPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(permissions => {
                const perm = permissions.find(p => p.username === username);
                const canView = type === 'view' ? (value ? 1 : 0) : (perm ? perm.can_view : 1);
                const canEdit = type === 'edit' ? (value ? 1 : 0) : (perm ? perm.can_edit : 0);
                
                const updateFormData = new FormData();
                updateFormData.append('action', 'set_page_permission');
                updateFormData.append('page_id', currentPageId);
                updateFormData.append('username', username);
                updateFormData.append('can_view', canView);
                updateFormData.append('can_edit', canEdit);
                
                return fetch('', {
                    method: 'POST',
                    body: updateFormData
                });
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ ההרשאה עודכנה', 'success');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.remove();
        }
        
        // Map edit mode functions
        function toggleMapEditMode() {
            mapEditMode = !mapEditMode;
            const btn = document.getElementById('map-edit-btn');
            btn.textContent = mapEditMode ? '👁️ מצב צפייה' : '✏️ מצב עריכה';
            btn.style.background = mapEditMode ? '#10b981' : '#2563eb';
            
            if (network) {
                network.setOptions({
                    interaction: {
                        dragNodes: mapEditMode,
                        dragView: true,
                        zoomView: true
                    }
                });
            }
        }
        
        function toggleMapSidebar() {
            const sidebar = document.getElementById('map-sidebar');
            sidebar.classList.toggle('hidden');
        }
        
        function addMapNode() {
            if (!network) return;
            const label = prompt('הזן שם לצומת:');
            if (!label) return;
            
            const nodes = network.body.data.nodes;
            const newId = 'new_' + Date.now();
            nodes.add({
                id: newId,
                label: label,
                color: { background: '#2563eb', border: '#1d4ed8' },
                shape: 'dot',
                size: 20
            });
            showNotification('✅ הצומת נוסף', 'success');
        }
        
        function addMapEdge() {
            if (!network || !selectedNodeId) {
                showNotification('אנא בחר צומת ראשון', 'error');
                return;
            }
            const fromId = selectedNodeId;
            selectedNodeId = null;
            
            if (network) {
                network.once('click', function(params) {
                    if (params.nodes.length > 0) {
                        const toId = params.nodes[0];
                        const edges = network.body.data.edges;
                        edges.add({ from: fromId, to: toId, arrows: 'to' });
                        showNotification('✅ הקישור נוסף', 'success');
                    }
                });
                showNotification('בחר צומת שני לחיבור', 'success');
            }
        }
        
        function deleteSelectedNode() {
            if (!network || !selectedNodeId) {
                showNotification('אנא בחר צומת למחיקה', 'error');
                return;
            }
            if (confirm('האם אתה בטוח שברצונך למחוק צומת זה?')) {
                const nodes = network.body.data.nodes;
                nodes.remove({ id: selectedNodeId });
                selectedNodeId = null;
                showNotification('✅ הצומת נמחק', 'success');
            }
        }
        
        // Load map with edit mode and node selection support
        function loadMap() {
            const container = document.getElementById('feature-map');
            if (!container) {
                console.error('Map container not found');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'get_data');
            formData.append('page_id', currentPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (!data || data.length === 0) {
                    container.innerHTML = '<p style="padding: 20px; text-align: center; color: #6b7280;">אין נתונים להצגה במפה</p>';
                    return;
                }
                const nodes = [];
                const edges = [];
                const categories = {};
                const categoryColors = {
                    'SysAid': '#2563eb',
                    'SharePoint': '#10b981',
                    'Jira': '#f59e0b',
                    'ServiceNow': '#8b5cf6'
                };
                
                data.forEach((item, index) => {
                    if (!categories[item.category]) {
                        categories[item.category] = {
                            id: Object.keys(categories).length,
                            label: item.category,
                            group: item.category,
                            color: categoryColors[item.category] || '#6b7280'
                        };
                    }
                    
                    nodes.push({
                        id: item.id,
                        label: item.feature.substring(0, 30) + (item.feature.length > 30 ? '...' : ''),
                        group: item.category,
                        color: {
                            background: item.color,
                            border: categoryColors[item.category] || '#6b7280',
                            highlight: { background: item.color, border: '#000' }
                        },
                        title: item.description || item.feature,
                        font: { size: 12, face: 'Arial' },
                        shape: 'dot',
                        size: 20
                    });
                });
                
                // Add category nodes
                Object.values(categories).forEach(cat => {
                    nodes.push({
                        id: 'cat_' + cat.id,
                        label: cat.label,
                        group: cat.label,
                        shape: 'box',
                        color: {
                            background: cat.color,
                            border: cat.color,
                            highlight: { background: cat.color, border: '#000' }
                        },
                        font: { size: 16, bold: true, face: 'Arial' },
                        size: 30,
                        level: 0
                    });
                });
                
                // Connect features to categories
                data.forEach(item => {
                    const catId = categories[item.category].id;
                    edges.push({
                        from: item.id,
                        to: 'cat_' + catId,
                        arrows: { to: { enabled: true, scaleFactor: 0.8 } },
                        color: { color: '#9ca3af', highlight: '#000' },
                        width: 2,
                        smooth: { type: 'continuous', roundness: 0.5 }
                    });
                });
                
                const container = document.getElementById('feature-map');
                const networkData = { nodes: nodes, edges: edges };
                const options = {
                    nodes: {
                        font: { size: 12, face: 'Arial' },
                        borderWidth: 2,
                        shadow: true
                    },
                    edges: {
                        smooth: { type: 'continuous', roundness: 0.5 },
                        color: { color: '#9ca3af', highlight: '#000' },
                        width: 2,
                        arrows: { to: { enabled: true, scaleFactor: 0.8 } }
                    },
                    layout: {
                        hierarchical: {
                            enabled: !mapEditMode,
                            direction: 'UD',
                            sortMethod: 'directed',
                            levelSeparation: 150,
                            nodeSpacing: 200,
                            treeSpacing: 200,
                            blockShifting: true,
                            edgeMinimization: true,
                            parentCentralization: true
                        }
                    },
                    physics: {
                        enabled: mapEditMode
                    },
                    interaction: {
                        dragNodes: mapEditMode,
                        dragView: true,
                        zoomView: true,
                        navigationButtons: true,
                        keyboard: true
                    }
                };
                
                if (network) network.destroy();
                network = new vis.Network(container, networkData, options);
                
                // Node selection
                network.on('click', function(params) {
                    if (params.nodes.length > 0) {
                        selectedNodeId = params.nodes[0];
                        if (mapEditMode) {
                            showNotification('צומת נבחר: ' + network.body.data.nodes.get(selectedNodeId).label, 'success');
                        }
                    }
                });
                
                // Fit to screen on load
                setTimeout(() => {
                    if (network) {
                        network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
                    }
                }, 100);
            })
            .catch(error => {
                console.error('Error loading map:', error);
                const container = document.getElementById('feature-map');
                if (container) {
                    container.innerHTML = '<p style="padding: 20px; text-align: center; color: #ef4444;">❌ שגיאה בטעינת המפה: ' + error.message + '</p>';
                }
            });
        }
        
        function refreshTable(data) {
            const tbody = document.getElementById('features-tbody');
            tbody.innerHTML = '';
            // This would need to be implemented to rebuild the table
            // For now, just reload
            refreshData();
        }
        
        // New Feature Actions Functions
        
        // Show feature actions modal (all-in-one)
        function showFeatureActions(featureId) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'feature-actions-modal';
            modal.innerHTML = `
                <div class="modal-content feature-actions-modal">
                    <div class="modal-header">
                        <h3>פעולות על פיצ'ר #${featureId}</h3>
                        <button class="close-btn" onclick="closeModal('feature-actions-modal')">×</button>
                    </div>
                    <div class="feature-actions-tabs">
                        <button class="feature-actions-tab active" onclick="switchFeatureTab('comments', ${featureId}); this.classList.add('active'); document.querySelectorAll('.feature-actions-tab').forEach(t => { if(t !== this) t.classList.remove('active'); });">💬 תגובות</button>
                        <button class="feature-actions-tab" onclick="switchFeatureTab('attachments', ${featureId}); this.classList.add('active'); document.querySelectorAll('.feature-actions-tab').forEach(t => { if(t !== this) t.classList.remove('active'); });">📎 קבצים</button>
                        <button class="feature-actions-tab" onclick="switchFeatureTab('connections', ${featureId}); this.classList.add('active'); document.querySelectorAll('.feature-actions-tab').forEach(t => { if(t !== this) t.classList.remove('active'); });">🔗 חיבורים</button>
                        <button class="feature-actions-tab" onclick="switchFeatureTab('tags', ${featureId}); this.classList.add('active'); document.querySelectorAll('.feature-actions-tab').forEach(t => { if(t !== this) t.classList.remove('active'); });">🏷️ תגיות</button>
                    </div>
                    <div id="feature-actions-content"></div>
                </div>
            `;
            document.body.appendChild(modal);
            switchFeatureTab('comments', featureId);
        }
        
        function switchFeatureTab(tab, featureId) {
            const tabLabels = {
                'comments': 'תגובות',
                'attachments': 'קבצים',
                'connections': 'חיבורים',
                'tags': 'תגיות'
            };
            
            document.querySelectorAll('.feature-actions-tab').forEach(t => {
                t.classList.remove('active');
                if (t.textContent.includes(tabLabels[tab])) {
                    t.classList.add('active');
                }
            });
            const content = document.getElementById('feature-actions-content');
            
            if (tab === 'comments') {
                content.innerHTML = `
                    <div id="comments-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px;"></div>
                    <div>
                        <textarea id="new-comment" placeholder="כתוב תגובה..." style="width: 100%; padding: 12px; margin-bottom: 8px; min-height: 80px;"></textarea>
                        <button class="btn-primary" onclick="addComment(${featureId})">📤 שלח תגובה</button>
                    </div>
                `;
                loadComments(featureId, document.getElementById('comments-list'));
            } else if (tab === 'attachments') {
                content.innerHTML = `
                    <div id="attachments-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px;"></div>
                    <div>
                        <input type="file" id="file-upload" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" style="margin-bottom: 8px;">
                        <button class="btn-primary" onclick="uploadAttachment(${featureId})">📤 העלה קובץ</button>
                    </div>
                `;
                loadAttachments(featureId, document.getElementById('attachments-list'));
            } else if (tab === 'connections') {
                content.innerHTML = `
                    <div id="connections-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px;"></div>
                    <div>
                        <select id="connect-feature-select" style="width: 200px; margin-left: 8px;">
                            <option value="">בחר פיצ'ר לחיבור...</option>
                        </select>
                        <button class="btn-primary" onclick="addConnection(${featureId})">🔗 חבר</button>
                    </div>
                `;
                loadConnections(featureId, document.getElementById('connections-list'));
                loadFeaturesForConnection(featureId);
            } else if (tab === 'tags') {
                content.innerHTML = `
                    <div id="tags-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px; min-height: 100px;"></div>
                    <div>
                        <input type="text" id="new-tag" placeholder="הזן תגית חדשה..." style="width: 200px; margin-left: 8px;">
                        <button class="btn-primary" onclick="addTag(${featureId})">🏷️ הוסף תגית</button>
                    </div>
                `;
                loadTags(featureId, document.getElementById('tags-list'));
            }
        }
        
        // Comments/Chat
        function showComments(featureId) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'comments-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>תגובות על פיצ'ר #${featureId}</h3>
                        <button class="close-btn" onclick="closeModal('comments-modal')">×</button>
                    </div>
                    <div id="comments-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px;"></div>
                    <div>
                        <textarea id="new-comment" placeholder="כתוב תגובה..." style="width: 100%; padding: 12px; margin-bottom: 8px; min-height: 80px;"></textarea>
                        <button class="btn-primary" onclick="addComment(${featureId})">📤 שלח תגובה</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            loadComments(featureId, document.getElementById('comments-list'));
        }
        
        function loadComments(featureId, container) {
            const formData = new FormData();
            formData.append('action', 'get_comments');
            formData.append('feature_id', featureId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(comments => {
                if (!container) container = document.getElementById('comments-list');
                if (comments.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">אין תגובות עדיין</p>';
                } else {
                    container.innerHTML = comments.map(comment => `
                        <div class="comment-item">
                            <div class="comment-header">
                                <span><strong>${comment.user}</strong></span>
                                <span>${comment.created_at}</span>
                            </div>
                            <div>${autoLink(comment.comment)}</div>
                        </div>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function addComment(featureId) {
            const commentInput = document.getElementById('new-comment');
            const comment = commentInput.value.trim();
            if (!comment) return;
            
            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('feature_id', featureId);
            formData.append('comment', comment);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    commentInput.value = '';
                    loadComments(featureId);
                    showNotification('✅ התגובה נוספה', 'success');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Likes/Dislikes
        function toggleLike(featureId, type) {
            const formData = new FormData();
            formData.append('action', 'toggle_like');
            formData.append('feature_id', featureId);
            formData.append('type', type);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateLikeButtons(featureId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function updateLikeButtons(featureId) {
            const formData = new FormData();
            formData.append('action', 'get_likes');
            formData.append('feature_id', featureId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(likes => {
                const likeBtn = document.getElementById('like-btn-' + featureId);
                const dislikeBtn = document.getElementById('dislike-btn-' + featureId);
                
                if (likeBtn) {
                    likeBtn.classList.toggle('active', likes.user_like === 'like');
                    likeBtn.innerHTML = `👍 ${likes.like}`;
                }
                if (dislikeBtn) {
                    dislikeBtn.classList.toggle('active', likes.user_like === 'dislike');
                    dislikeBtn.innerHTML = `👎 ${likes.dislike}`;
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Share
        function showShareModal(featureId) {
            const sharedWith = prompt('הזן שם משתמש או אימייל לשיתוף:');
            if (!sharedWith) return;
            
            const formData = new FormData();
            formData.append('action', 'share_feature');
            formData.append('feature_id', featureId);
            formData.append('shared_with', sharedWith);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ הפיצ\'ר שותף בהצלחה!', 'success');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Attachments (Images and Files)
        function showAttachments(featureId) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'attachments-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>קבצים מצורפים - פיצ'ר #${featureId}</h3>
                        <button class="close-btn" onclick="closeModal('attachments-modal')">×</button>
                    </div>
                    <div id="attachments-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px;"></div>
                    <div>
                        <input type="file" id="file-upload" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" style="margin-bottom: 8px;">
                        <button class="btn-primary" onclick="uploadAttachment(${featureId})">📤 העלה קובץ</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            loadAttachments(featureId, document.getElementById('attachments-list'));
        }
        
        function loadAttachments(featureId, container) {
            const formData = new FormData();
            formData.append('action', 'get_attachments');
            formData.append('feature_id', featureId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(attachments => {
                if (!container) container = document.getElementById('attachments-list');
                if (attachments.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">אין קבצים מצורפים</p>';
                } else {
                    container.innerHTML = attachments.map(att => `
                        <div class="attachment-item">
                            ${att.file_type === 'image' ? `<img src="${att.file_path}" class="attachment-preview" alt="${att.file_name}">` : ''}
                            <div style="flex: 1;">
                                <div><strong>${att.file_name}</strong></div>
                                <div style="font-size: 12px; color: var(--text-secondary);">${(att.file_size / 1024).toFixed(2)} KB | ${att.created_at}</div>
                            </div>
                            <a href="${att.file_path}" download class="btn-primary" style="text-decoration: none; padding: 6px 12px;">⬇️ הורד</a>
                            <button class="delete-btn" onclick="deleteAttachment(${att.id}, ${featureId})">🗑️</button>
                        </div>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function uploadAttachment(featureId) {
            const fileInput = document.getElementById('file-upload');
            if (!fileInput.files.length) {
                showNotification('❌ אנא בחר קובץ', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_attachment');
            formData.append('feature_id', featureId);
            formData.append('file', fileInput.files[0]);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ הקובץ הועלה בהצלחה!', 'success');
                    fileInput.value = '';
                    loadAttachments(featureId);
                } else {
                    showNotification('❌ שגיאה בהעלאת הקובץ: ' + (data.message || ''), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ שגיאה בהעלאת הקובץ', 'error');
            });
        }
        
        function deleteAttachment(attachmentId, featureId) {
            if (!confirm('האם אתה בטוח שברצונך למחוק קובץ זה?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_attachment');
            formData.append('attachment_id', attachmentId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ הקובץ נמחק', 'success');
                    loadAttachments(featureId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Connections
        function showConnections(featureId) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'connections-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>חיבורים - פיצ'ר #${featureId}</h3>
                        <button class="close-btn" onclick="closeModal('connections-modal')">×</button>
                    </div>
                    <div id="connections-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px;"></div>
                    <div>
                        <select id="connect-feature-select" style="width: 200px; margin-left: 8px;">
                            <option value="">בחר פיצ'ר לחיבור...</option>
                        </select>
                        <button class="btn-primary" onclick="addConnection(${featureId})">🔗 חבר</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            loadConnections(featureId, document.getElementById('connections-list'));
            loadFeaturesForConnection(featureId);
        }
        
        function loadConnections(featureId, container) {
            const formData = new FormData();
            formData.append('action', 'get_connections');
            formData.append('feature_id', featureId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(connections => {
                if (!container) container = document.getElementById('connections-list');
                if (connections.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">אין חיבורים</p>';
                } else {
                    container.innerHTML = connections.map(conn => {
                        const otherFeature = conn.feature_id_1 == featureId ? conn.feature2_name : conn.feature1_name;
                        return `
                            <div class="attachment-item">
                                <div style="flex: 1;">
                                    <div><strong>${otherFeature}</strong></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">${conn.connection_type} | ${conn.created_at}</div>
                                </div>
                                <button class="delete-btn" onclick="deleteConnection(${conn.id}, ${featureId})">🗑️</button>
                            </div>
                        `;
                    }).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function loadFeaturesForConnection(currentFeatureId) {
            const formData = new FormData();
            formData.append('action', 'get_data');
            formData.append('page_id', currentPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(features => {
                const select = document.getElementById('connect-feature-select');
                if (select) {
                    select.innerHTML = '<option value="">בחר פיצ\'ר לחיבור...</option>' +
                        features.filter(f => f.id != currentFeatureId).map(f => 
                            `<option value="${f.id}">${f.feature} (${f.category})</option>`
                        ).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function addConnection(featureId) {
            const select = document.getElementById('connect-feature-select');
            const otherFeatureId = select.value;
            if (!otherFeatureId) {
                showNotification('❌ אנא בחר פיצ\'ר לחיבור', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_connection');
            formData.append('feature_id_1', featureId);
            formData.append('feature_id_2', otherFeatureId);
            formData.append('connection_type', 'related');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ החיבור נוסף בהצלחה!', 'success');
                    loadConnections(featureId);
                    select.value = '';
                } else {
                    showNotification('❌ ' + (data.message || 'שגיאה בהוספת החיבור'), 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function deleteConnection(connectionId, featureId) {
            const formData = new FormData();
            formData.append('action', 'delete_connection');
            formData.append('connection_id', connectionId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ החיבור נמחק', 'success');
                    loadConnections(featureId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Tags
        function showTags(featureId) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'tags-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>תגיות - פיצ'ר #${featureId}</h3>
                        <button class="close-btn" onclick="closeModal('tags-modal')">×</button>
                    </div>
                    <div id="tags-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px; min-height: 100px;"></div>
                    <div>
                        <input type="text" id="new-tag" placeholder="הזן תגית חדשה..." style="width: 200px; margin-left: 8px;">
                        <button class="btn-primary" onclick="addTag(${featureId})">🏷️ הוסף תגית</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            loadTags(featureId, document.getElementById('tags-list'));
        }
        
        function loadTags(featureId, container) {
            const formData = new FormData();
            formData.append('action', 'get_tags');
            formData.append('feature_id', featureId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(tags => {
                if (!container) container = document.getElementById('tags-list');
                if (tags.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">אין תגיות</p>';
                } else {
                    container.innerHTML = tags.map(tag => `
                        <span class="tag-item">
                            ${tag.tag}
                            <button onclick="deleteTag(${tag.id}, ${featureId})" style="background: none; border: none; cursor: pointer; margin-right: 4px;">×</button>
                        </span>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function addTag(featureId) {
            const tagInput = document.getElementById('new-tag');
            const tag = tagInput.value.trim();
            if (!tag) return;
            
            const formData = new FormData();
            formData.append('action', 'add_tag');
            formData.append('feature_id', featureId);
            formData.append('tag', tag);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tagInput.value = '';
                    loadTags(featureId);
                    showNotification('✅ התגית נוספה', 'success');
                } else {
                    showNotification('❌ ' + (data.message || 'שגיאה בהוספת התגית'), 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function deleteTag(tagId, featureId) {
            const formData = new FormData();
            formData.append('action', 'delete_tag');
            formData.append('tag_id', tagId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadTags(featureId);
                    showNotification('✅ התגית נמחקה', 'success');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Move feature between pages
        function showMoveFeature(featureId) {
            const pageOptions = pages.map(p => `<option value="${p.id}">${p.title}</option>`).join('');
            const targetPage = prompt(`העבר פיצ'ר #${featureId} לדף:\n${pages.map(p => `${p.id}. ${p.title}`).join('\n')}\n\nהזן מספר דף:`, currentPageId);
            if (!targetPage) return;
            
            const newPageId = parseInt(targetPage);
            if (isNaN(newPageId)) {
                showNotification('❌ מספר דף לא תקין', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'move_feature');
            formData.append('feature_id', featureId);
            formData.append('new_page_id', newPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ הפיצ\'ר הועבר בהצלחה!', 'success');
                    setTimeout(() => refreshData(), 1000);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Download Report
        function downloadReport() {
            const formData = new FormData();
            formData.append('action', 'download_report');
            formData.append('page_id', currentPageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Create CSV content
                let csv = 'קטגוריה,שם פיצ\'ר,תיאור,משתמש,עודכן,תגובות,לייקים,דיסלייקים,תגיות\n';
                data.forEach(f => {
                    csv += `"${f.category}","${f.feature}","${f.description || ''}","${f.user || ''}","${f.updated_at || ''}","${f.comments_count || 0}","${f.likes_count || 0}","${f.dislikes_count || 0}","${f.tags || ''}"\n`;
                });
                
                // Download
                const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `report_page_${currentPageId}_${new Date().toISOString().split('T')[0]}.csv`;
                link.click();
                showNotification('✅ הדוח הורד בהצלחה!', 'success');
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ שגיאה בהורדת הדוח', 'error');
            });
        }
        
        // Initialize on page load
        window.addEventListener('load', function() {
            renderPageTabs();
            
            // Apply auto-linking to all text content
            applyAutoLinks();
            
            // Load likes for all features
            document.querySelectorAll('[id^="like-btn-"]').forEach(btn => {
                const featureId = btn.id.replace('like-btn-', '');
                updateLikeButtons(featureId);
            });
            
            // Disable editing if page is locked
            if (isPageLocked) {
                document.querySelectorAll('.editable').forEach(el => {
                    el.disabled = true;
                    el.style.opacity = '0.6';
                });
            }
            
            // Auto-load SysAid features only on first page
            if (currentPageId == 1) {
                setTimeout(() => {
                    loadSysAid();
                }, 500);
                
                setTimeout(() => {
                    loadSharePoint();
                }, 1500);
            }
        });
    </script>
</body>
</html>

