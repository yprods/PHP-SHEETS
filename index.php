<?php
/**
 * מערכת ניהול פיצ'רים משולבת
 * Feature Management System
 * 
 * @author System
 * @version 1.0
 */

// Include API for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/api.php';
            exit;
}

// Include API functions for page load (only functions, not the POST handler)
require_once __DIR__ . '/api.php';

// Note: All database functions (initDatabase, getCurrentUser, sendEmailNotification) are now in api.php

// Initialize database on page load
$db = initDatabase();
$user = getCurrentUser();
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
    <meta name="description" content="מערכת ניהול פיצ'רים">
    <title>מערכת ניהול פיצ'רים</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icon-192.svg">
    <link rel="icon" type="image/svg+xml" href="icon-192.svg">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Chart.js -->
    <script src="chart.umd.min.js" onerror="loadChartJSFromCDN()"></script>
    <script>
        function loadChartJSFromCDN() {
            if (typeof Chart === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
                script.onerror = function() {
                    console.error('Failed to load Chart.js from CDN');
                };
                document.head.appendChild(script);
            }
        }
    </script>
    
    <!-- vis-network -->
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <script>
        // Fallback if CDN fails
        window.addEventListener('error', function(e) {
            if (e.target && e.target.src && e.target.src.includes('vis-network')) {
                console.error('vis-network CDN failed, trying local file...');
                const script = document.createElement('script');
                script.src = 'vis-network.min.js';
                script.onerror = function() {
                    console.error('Both CDN and local vis-network failed');
                };
                document.head.appendChild(script);
            }
        }, true);
        
        // Ensure vis-network is available
        function ensureVisNetwork(callback) {
            let attempts = 0;
            const maxAttempts = 30; // Increased for better reliability
            const checkInterval = setInterval(function() {
                attempts++;
                if (typeof vis !== 'undefined' && vis.Network && vis.DataSet) {
                    clearInterval(checkInterval);
                    console.log('vis-network is ready (Network + DataSet) after', attempts * 100, 'ms');
                    if (callback) callback(true);
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    console.error('vis-network failed to load after', maxAttempts * 100, 'ms');
                    if (callback) callback(false);
                }
            }, 100);
        }
        
        // Pre-load vis-network
        ensureVisNetwork(function(success) {
            if (success) {
                console.log('vis-network pre-loaded successfully');
            }
        });
    </script>
    
    <style>
        /* Using system fonts instead of external font */
        
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
        
        :root.dark-mode {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --success-color: #10b981;
            --danger-color: #f87171;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --bg-primary: #1f2937;
            --bg-secondary: #111827;
            --border-color: #374151;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3), 0 1px 2px 0 rgba(0, 0, 0, 0.2);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Arial Hebrew', 'David', sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            padding: 20px;
            direction: rtl;
            transition: background 0.3s ease;
        }
        
        :root.dark-mode body {
            background: #111827;
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
            height: 1200px;
            min-height: 800px;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            margin-left: 8px;
        }
        
        .btn-icon:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-icon .fas, .btn-icon .far, .btn-icon .fal {
            font-size: 18px;
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
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 12px;
            background: var(--bg-primary);
            box-shadow: var(--shadow);
            transition: all 0.2s;
        }
        
        .comment-item:hover {
            box-shadow: var(--shadow-md);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .comment-user {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
        }
        
        .comment-date {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .comment-body {
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 14px;
        }
        
        .comment-input-container {
            background: var(--bg-secondary);
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
        }
        
        .comment-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.2s;
        }
        
        .comment-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
        
        /* Font Awesome Icons */
        .icon, .fas, .far, .fal {
            font-size: 16px;
            line-height: 1;
            display: inline-block;
            vertical-align: middle;
        }
        
        .icon-large, .fas.icon-large, .far.icon-large, .fal.icon-large {
            font-size: 24px;
        }
        
        .icon-small, .fas.icon-small, .far.icon-small, .fal.icon-small {
            font-size: 14px;
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
        
        /* Notifications */
        .notifications-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            width: 400px;
            max-height: 500px;
            z-index: 1000;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .notifications-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-secondary);
        }
        
        .notifications-header h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
        }
        
        .notifications-list {
            max-height: 450px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .notification-item:hover {
            background: var(--bg-secondary);
        }
        
        .notification-item.unread {
            background: #eff6ff;
            font-weight: 500;
        }
        
        .notification-item .notification-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .notification-item .notification-message {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .notification-item .notification-time {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        /* Online users */
        .online-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .online-users-list {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            box-shadow: var(--shadow-md);
            min-width: 200px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
        }
        
        #online-users-indicator {
            position: relative;
        }
        
        .online-user-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            font-size: 13px;
        }
        
        /* Shared board */
        .shared-board-container {
            position: relative;
            width: 100%;
            height: 600px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-md);
        }
        
        .board-item {
            position: absolute;
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            min-width: 180px;
            min-height: 120px;
            max-width: 400px;
            cursor: move;
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.2s;
            user-select: none;
        }
        
        .board-item:hover {
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
            z-index: 10;
            transform: translateY(-2px);
        }
        
        .board-item:active {
            cursor: grabbing;
        }
        
        .board-item.dragging {
            opacity: 0.8;
            z-index: 100;
        }
        
        .board-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
            cursor: grab;
        }
        
        .board-item-header:active {
            cursor: grabbing;
        }
        
        .board-item-user {
            font-size: 11px;
            color: var(--primary-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .board-item-user::before {
            content: '👤';
            font-size: 12px;
        }
        
        .board-item-content {
            font-size: 14px;
            color: var(--text-primary);
            min-height: 60px;
            line-height: 1.6;
            padding: 8px;
            border: 1px dashed transparent;
            border-radius: 4px;
            outline: none;
            word-wrap: break-word;
            resize: vertical;
            overflow-y: auto;
        }
        
        .board-item-content:focus {
            border-color: var(--primary-color);
            background: var(--bg-secondary);
        }
        
        .board-item-actions {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        
        .board-item-actions button {
            padding: 4px 8px;
            font-size: 11px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background: var(--bg-secondary);
            transition: all 0.2s;
        }
        
        .board-item-actions button:hover {
            background: var(--danger-color);
            color: white;
        }
        
        .board-item-actions input[type="color"] {
            width: 28px;
            height: 28px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            padding: 0;
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
            <symbol id="icon-sun" viewBox="0 0 24 24">
                <path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0 .39-.39.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41.39.39 1.03.39 1.41 0l1.06-1.06zM7.05 18.36c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41.39.39 1.03.39 1.41 0l1.06-1.06z"/>
            </symbol>
            <symbol id="icon-moon" viewBox="0 0 24 24">
                <path d="M12.34 2.02C6.59 1.82 2 6.42 2 12c0 5.52 4.48 10 10 10 3.71 0 6.93-2.02 8.66-5.02-7.51-.25-13.09-6.8-13.09-14.38 0-.78.07-1.53.23-2.26z"/>
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
            <symbol id="icon-bell" viewBox="0 0 24 24">
                <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
            </symbol>
        </defs>
    </svg>
</head>
<body>
    <div class="container">
        <!-- Editable Title -->
        <div class="title-container">
            <h1 id="page-title" contenteditable="true" onblur="updatePageTitle()" style="cursor: text; border-bottom: 2px dashed transparent; padding-bottom: 4px;"><?php echo htmlspecialchars($currentPage['title']); ?></h1>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div id="online-users-indicator" onclick="toggleOnlineUsersList()" style="display: flex; align-items: center; gap: 6px; color: var(--text-secondary); font-size: 12px; cursor: pointer; padding: 6px 10px; border-radius: 6px; transition: all 0.2s; border: 1px solid var(--border-color); position: relative;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'">
                    <span class="online-dot"></span>
                    <span id="online-users-count">0</span> מחוברים
                    <div id="online-users-list" class="online-users-list" style="display: none;">
                        <div style="font-weight: 600; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">משתמשים מחוברים</div>
                        <div id="online-users-list-content"></div>
                    </div>
                </div>
                <div id="notifications-container" style="position: relative;">
                    <button class="btn-icon" onclick="toggleNotifications(); event.stopPropagation();" id="notifications-btn" title="התראות">
                        <i class="fas fa-bell"></i>
                        <span id="notifications-badge" style="display: none; position: absolute; top: -4px; left: -4px; background: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; line-height: 18px; text-align: center; font-weight: bold;">0</span>
                    </button>
                    <div id="notifications-dropdown" class="notifications-dropdown" style="display: none;">
                        <div class="notifications-header">
                            <h4>התראות</h4>
                            <button onclick="markAllNotificationsRead()" style="font-size: 12px; padding: 4px 8px;">סמן הכל כקרוא</button>
                        </div>
                        <div id="notifications-list" class="notifications-list" onclick="event.stopPropagation();"></div>
                    </div>
                </div>
            <button class="btn-icon" onclick="togglePageLock()" id="lock-btn" title="נעל/פתח דף">
                    <i class="fas fa-<?php echo $currentPage['is_locked'] ? 'lock' : 'unlock'; ?>"></i>
            </button>
            <button class="btn-icon" onclick="showColumnManager()" title="נהל עמודות">
                    <i class="fas fa-cog"></i>
            </button>
            <button class="btn-icon" onclick="showPermissionsModal()" title="הרשאות">
                    <i class="fas fa-users"></i>
                </button>
                <button class="btn-icon" onclick="toggleDarkMode()" id="dark-mode-btn" title="מצב כהה/בהיר">
                    <i class="fas fa-sun" id="dark-mode-icon"></i>
            </button>
        </div>
        </div>
        <p class="subtitle">מערכת ניהול פיצ'רים</p>
        
        <div class="user-info" style="display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-user" style="margin-left: 4px;"></i>
            משתמש: <strong><?php echo htmlspecialchars($user); ?></strong>
        </div>
        
        <!-- Page Tabs (Bottom) -->
        <div class="page-tabs-container" id="page-tabs-container">
            <div class="page-tabs" id="page-tabs"></div>
            <button class="btn-success btn-small" onclick="createNewPage()">
                <i class="fas fa-plus" style="margin-left: 4px;"></i>
                דף חדש
            </button>
        </div>
        
        <div class="controls">
            <button class="btn-success" onclick="addNewRow()">
                <i class="fas fa-plus" style="margin-left: 4px;"></i>
                הוסף שורה חדשה
            </button>
            <button class="btn-primary" onclick="refreshData()">
                <i class="fas fa-sync-alt" style="margin-left: 4px;"></i>
                רענן נתונים
            </button>
            <button class="btn-success" onclick="downloadReport()">
                <i class="fas fa-download" style="margin-left: 4px;"></i>
                הורד דוח
            </button>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('table', event)">
                <i class="fas fa-table" style="margin-left: 4px;"></i>
                טבלת פיצ'רים
            </button>
            <button class="tab" onclick="showTab('dashboard', event)">
                <i class="fas fa-chart-bar" style="margin-left: 4px;"></i>
                דשבורד
            </button>
            <button class="tab" onclick="showTab('map', event)">
                <i class="fas fa-project-diagram" style="margin-left: 4px;"></i>
                מפת פיצ'רים
            </button>
            <button class="tab" onclick="showTab('audit', event)">
                <i class="fas fa-file-alt" style="margin-left: 4px;"></i>
                לוג Audit
            </button>
            <button class="tab" onclick="showTab('board', event)">
                <i class="fas fa-sticky-note" style="margin-left: 4px;"></i>
                לוח משותף
            </button>
        </div>
        
        <!-- Table Tab -->
        <div id="table-tab" class="tab-content active">
            <div class="search-container">
                <input type="text" id="search-input" class="search-input" placeholder="חפש פיצ'רים, קטגוריות, תיאורים..." onkeyup="filterTable()">
                <span class="search-icon">
                    <i class="fas fa-search"></i>
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
                                <option value="קטגוריה 1" <?php echo $feat['category'] === 'קטגוריה 1' ? 'selected' : ''; ?>>קטגוריה 1</option>
                                <option value="קטגוריה 2" <?php echo $feat['category'] === 'קטגוריה 2' ? 'selected' : ''; ?>>קטגוריה 2</option>
                                <option value="קטגוריה 3" <?php echo $feat['category'] === 'קטגוריה 3' ? 'selected' : ''; ?>>קטגוריה 3</option>
                                <option value="קטגוריה 4" <?php echo $feat['category'] === 'קטגוריה 4' ? 'selected' : ''; ?>>קטגוריה 4</option>
                            </select>
                        </td>
                        <td><input type="text" class="editable" data-field="feature" value="<?php echo htmlspecialchars($feat['feature']); ?>" onblur="saveField(this)" /></td>
                        <td>
                            <div class="description-wrapper" data-field="description" data-id="<?php echo $feat['id']; ?>">
                                <div class="description-display" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; min-height: 40px; cursor: text;" onclick="editDescription(this)"><?php echo htmlspecialchars($feat['description']); ?></div>
                                <textarea class="editable description-edit" data-field="description" onblur="saveFieldAndHide(this)" rows="2" style="display: none;"><?php echo htmlspecialchars($feat['description']); ?></textarea>
                            </div>
                        </td>
                        <td><input type="color" class="color-input editable" data-field="color" value="<?php echo htmlspecialchars($feat['color']); ?>" onchange="updateRowColorFromInput(this); saveField(this);" /></td>
                        <td><?php echo htmlspecialchars($feat['user']); ?></td>
                        <td><?php echo htmlspecialchars($feat['updated_at']); ?></td>
                        <td>
                            <div class="action-buttons" style="display: flex; gap: 4px; flex-wrap: wrap;">
                                <button class="action-btn" onclick="showFeatureActions(<?php echo $feat['id']; ?>)" title="פעולות">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <button class="action-btn like-btn" onclick="toggleLike(<?php echo $feat['id']; ?>, 'like')" id="like-btn-<?php echo $feat['id']; ?>" title="לייק">
                                    <i class="fas fa-thumbs-up"></i>
                                </button>
                                <button class="action-btn dislike-btn" onclick="toggleLike(<?php echo $feat['id']; ?>, 'dislike')" id="dislike-btn-<?php echo $feat['id']; ?>" title="דיסלייק">
                                    <i class="fas fa-thumbs-down"></i>
                                </button>
                                <button class="action-btn" onclick="showComments(<?php echo $feat['id']; ?>)" title="תגובות">
                                    <i class="fas fa-comment"></i>
                                </button>
                                <button class="action-btn" onclick="showShareModal(<?php echo $feat['id']; ?>)" title="שתף">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                                <button class="action-btn" onclick="showAttachments(<?php echo $feat['id']; ?>)" title="קבצים">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <button class="action-btn" onclick="showConnections(<?php echo $feat['id']; ?>)" title="חיבורים">
                                    <i class="fas fa-link"></i>
                                </button>
                                <button class="action-btn" onclick="showTags(<?php echo $feat['id']; ?>)" title="תגיות">
                                    <i class="fas fa-tag"></i>
                                </button>
                                <button class="action-btn" onclick="showEmailTracking(<?php echo $feat['id']; ?>)" title="מעקב אימייל">
                                    <i class="fas fa-cog"></i> 📧
                                </button>
                                <button class="action-btn" onclick="showMoveFeature(<?php echo $feat['id']; ?>)" title="העבר דף">
                                    <i class="fas fa-file"></i>
                                </button>
                                <button class="delete-btn" onclick="deleteRow(<?php echo $feat['id']; ?>)" title="מחק">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="add-row" onclick="addNewRow()">
                <i class="fas fa-plus" style="margin-left: 4px;"></i>
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
                    <button class="map-control-btn" onclick="toggleMapEditMode()" id="map-edit-btn">
                        <i class="fas fa-edit" style="margin-left: 6px;"></i>
                        מצב עריכה
                    </button>
                    <button class="map-control-btn" onclick="addMapNode()">
                        <i class="fas fa-plus-circle" style="margin-left: 6px;"></i>
                        הוסף צומת
                    </button>
                    <button class="map-control-btn" onclick="addMapEdge()">
                        <i class="fas fa-link" style="margin-left: 6px;"></i>
                        הוסף קישור
                    </button>
                    <button class="map-control-btn" onclick="deleteSelectedNode()">
                        <i class="fas fa-trash-alt" style="margin-left: 6px;"></i>
                        מחק נבחר
                    </button>
                </div>
                <div class="map-wrapper">
                    <div class="map-controls">
                        <button class="map-control-btn" onclick="fitMap()">
                            <i class="fas fa-expand-arrows-alt" style="margin-left: 6px;"></i>
                            התאם למסך
                        </button>
                        <button class="map-control-btn" onclick="centerMap()">
                            <i class="fas fa-crosshairs" style="margin-left: 6px;"></i>
                            מרכז
                        </button>
                        <button class="map-control-btn" onclick="resetMap()">
                            <i class="fas fa-redo" style="margin-left: 6px;"></i>
                            איפוס
                        </button>
                        <button class="map-control-btn" onclick="toggleMapSidebar()">
                            <i class="fas fa-bars" style="margin-left: 6px;"></i>
                            הצג/הסתר תפריט
                        </button>
                    </div>
                    <div class="map-container" id="feature-map"></div>
                </div>
            </div>
        </div>
        
        <!-- Audit Tab -->
        <div id="audit-tab" class="tab-content">
            <h3>לוג פעולות (Audit Log)</h3>
            <div class="search-container">
                <input type="text" id="audit-search-input" class="search-input" placeholder="חפש בלוג..." onkeyup="filterAudit()">
                <span class="search-icon">
                    <i class="fas fa-search"></i>
                </span>
            </div>
            <div class="audit-log" id="audit-log">
                <p>טוען...</p>
            </div>
        </div>
        
        <!-- Shared Board Tab -->
        <div id="board-tab" class="tab-content">
            <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; padding: 16px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white; box-shadow: var(--shadow-md);">
                <div>
                    <h3 style="margin: 0; color: white; font-size: 1.25rem;">לוח משותף - עבודה משותפת</h3>
                    <p style="margin: 4px 0 0 0; font-size: 12px; opacity: 0.9;">כולם יכולים להוסיף ולערוך פתקים כאן בזמן אמת</p>
                </div>
                <button class="btn-success" onclick="addBoardItem()" style="background: white; color: #667eea; border: none; font-weight: 600;">
                    <i class="fas fa-plus" style="margin-left: 4px;"></i>
                    הוסף פתק
                </button>
            </div>
            <div class="shared-board-container" id="shared-board"></div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="app-footer">מערכות מידע & yprods</div>
    
    <script>
        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'לפני רגע';
            if (diffMins < 60) return `לפני ${diffMins} דקות`;
            if (diffHours < 24) return `לפני ${diffHours} שעות`;
            if (diffDays < 7) return `לפני ${diffDays} ימים`;
            
            return date.toLocaleDateString('he-IL', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
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
            
            fetch('api.php', {
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
                // Unregister old service workers first
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for(let registration of registrations) {
                        registration.unregister();
                    }
                }).then(function() {
                    // Register new service worker
                    return navigator.serviceWorker.register('sw.js', { scope: './' });
                }).then(function(registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    
                    // Force update
                    registration.update();
                    
                    // Check for updates
                    registration.addEventListener('updatefound', function() {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', function() {
                            if (newWorker.state === 'installed') {
                                if (navigator.serviceWorker.controller) {
                                    console.log('New service worker available. Reload to update.');
                                } else {
                                    console.log('Service worker installed for the first time');
                                }
                            }
                            if (newWorker.state === 'activated') {
                                console.log('Service worker activated');
                                // Reload to ensure everything uses the new service worker
                                window.location.reload();
                            }
                        });
                    });
                    })
                    .catch(function(err) {
                    console.error('ServiceWorker registration failed: ', err);
                    });
            });
        }
        
        // Real-time polling for notifications and online users
        let pollingInterval = null;
        let lastNotificationCheck = 0;
        
        function startPolling() {
            // Update online status immediately
            updateOnlineStatus();
            loadOnlineUsers();
            loadNotifications();
            
            // Poll every 3 seconds for real-time updates
            pollingInterval = setInterval(() => {
                updateOnlineStatus();
                loadOnlineUsers();
                loadNotifications();
                
                // Refresh shared board if it's visible (but don't override user's current editing)
                const boardTab = document.getElementById('board-tab');
                if (boardTab && boardTab.classList.contains('active')) {
                    // Only refresh if no item is being dragged
                    if (!draggedItem) {
                        const formData = new FormData();
                        formData.append('action', 'get_shared_board_items');
                        formData.append('page_id', currentPageId);
                        
                        fetch('api.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(items => {
                            // Update existing items or add new ones
                            const container = document.getElementById('shared-board');
                            items.forEach(item => {
                                const existing = document.getElementById('board-item-' + item.id);
                                if (!existing && !boardItems[item.id]) {
                                    addBoardItemToDOM(item);
                                    boardItems[item.id] = item;
                                } else if (existing && !draggedItem) {
                                    // Update content if different and not being edited
                                    const contentEl = existing.querySelector('.board-item-content');
                                    if (contentEl && !contentEl.matches(':focus') && contentEl.textContent !== item.content) {
                                        contentEl.textContent = item.content;
                                        boardItems[item.id].content = item.content;
                                    }
                                    // Update position if different (only if not being dragged)
                                    if (Math.abs(parseFloat(existing.style.left) - item.position_x) > 5 || 
                                        Math.abs(parseFloat(existing.style.top) - item.position_y) > 5) {
                                        existing.style.left = item.position_x + 'px';
                                        existing.style.top = item.position_y + 'px';
                                        boardItems[item.id].position_x = item.position_x;
                                        boardItems[item.id].position_y = item.position_y;
                                    }
                                    // Update color if different
                                    if (existing.style.backgroundColor !== item.color) {
                                        existing.style.backgroundColor = item.color;
                                        existing.querySelector('input[type="color"]').value = item.color;
                                        boardItems[item.id].color = item.color;
                                    }
                                }
                            });
                            
                            // Remove items that no longer exist
                            Object.keys(boardItems).forEach(itemId => {
                                if (!items.find(i => i.id == itemId)) {
                                    const el = document.getElementById('board-item-' + itemId);
                                    if (el) el.remove();
                                    delete boardItems[itemId];
                                }
                            });
                        })
                        .catch(error => console.error('Error refreshing board:', error));
                    }
                }
            }, 3000);
        }
        
        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }
        
        // Notifications functions
        function toggleNotifications() {
            const dropdown = document.getElementById('notifications-dropdown');
            const isVisible = dropdown.style.display !== 'none';
            dropdown.style.display = isVisible ? 'none' : 'block';
            if (!isVisible) {
                loadNotifications();
            }
        }
        
        
        function loadNotifications() {
            const formData = new FormData();
            formData.append('action', 'get_notifications');
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(notifications => {
                const list = document.getElementById('notifications-list');
                const badge = document.getElementById('notifications-badge');
                const unreadCount = notifications.filter(n => !n.is_read).length;
                
                if (unreadCount > 0) {
                    badge.style.display = 'block';
                    badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                } else {
                    badge.style.display = 'none';
                }
                
                if (notifications.length === 0) {
                    list.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-secondary);">אין התראות</div>';
                } else {
                    list.innerHTML = notifications.map(notif => `
                        <div class="notification-item ${notif.is_read ? '' : 'unread'}" onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                            <div class="notification-title">${escapeHtml(notif.title)}</div>
                            <div class="notification-message">${escapeHtml(notif.message || '')}</div>
                            <div class="notification-time">${formatDate(notif.created_at)}</div>
                        </div>
                    `).join('');
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
        }
        
        function handleNotificationClick(notifId, link) {
            markNotificationRead(notifId);
            if (link) {
                window.location.href = link;
            }
        }
        
        function markNotificationRead(notifId) {
            const formData = new FormData();
            formData.append('action', 'mark_notification_read');
            formData.append('notification_id', notifId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(() => loadNotifications());
        }
        
        function markAllNotificationsRead() {
            const formData = new FormData();
            formData.append('action', 'mark_all_notifications_read');
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(() => loadNotifications());
        }
        
        // Online users functions
        function updateOnlineStatus() {
            const formData = new FormData();
            formData.append('action', 'update_online_status');
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .catch(error => console.error('Error updating online status:', error));
        }
        
        function loadOnlineUsers() {
            const formData = new FormData();
            formData.append('action', 'get_online_users');
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(users => {
                const count = users.length;
                const countEl = document.getElementById('online-users-count');
                if (countEl) countEl.textContent = count;
                
                const listContent = document.getElementById('online-users-list-content');
                if (listContent) {
                    if (users.length === 0) {
                        listContent.innerHTML = '<div style="padding: 8px; color: var(--text-secondary); font-size: 12px; text-align: center;">אין משתמשים מחוברים</div>';
                    } else {
                        listContent.innerHTML = users.map(user => `
                            <div class="online-user-item">
                                <span class="online-dot"></span>
                                <span>${escapeHtml(user.username)}</span>
                                <span style="margin-right: auto; font-size: 11px; color: var(--text-secondary);">${formatDate(user.last_seen)}</span>
                            </div>
                        `).join('');
                    }
                }
            })
            .catch(error => console.error('Error loading online users:', error));
        }
        
        function toggleOnlineUsersList() {
            const list = document.getElementById('online-users-list');
            if (list) {
                list.style.display = list.style.display === 'none' ? 'block' : 'none';
                if (list.style.display === 'block') {
                    loadOnlineUsers();
                }
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            // Close notifications
            const container = document.getElementById('notifications-container');
            const dropdown = document.getElementById('notifications-dropdown');
            if (container && dropdown && !container.contains(e.target)) {
                dropdown.style.display = 'none';
            }
            
            // Close online users list
            const indicator = document.getElementById('online-users-indicator');
            const list = document.getElementById('online-users-list');
            if (list && indicator && !indicator.contains(e.target) && !list.contains(e.target)) {
                list.style.display = 'none';
            }
        });
        
        // Shared board functions
        let boardItems = {};
        let draggedItem = null;
        
        function loadSharedBoard() {
            const container = document.getElementById('shared-board');
            if (!container) return;
            
            const formData = new FormData();
            formData.append('action', 'get_shared_board_items');
            formData.append('page_id', currentPageId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(items => {
                boardItems = {};
                container.innerHTML = '';
                
                items.forEach(item => {
                    addBoardItemToDOM(item);
                    boardItems[item.id] = item;
                });
            })
            .catch(error => console.error('Error loading board:', error));
        }
        
        function addBoardItem() {
            const item = {
                id: 0,
                user: '<?php echo htmlspecialchars($user); ?>',
                content: 'פתק חדש',
                position_x: Math.random() * 300,
                position_y: Math.random() * 200,
                color: '#ffffff'
            };
            
            addBoardItemToDOM(item);
            saveBoardItem(item);
        }
        
        function addBoardItemToDOM(item) {
            const container = document.getElementById('shared-board');
            const div = document.createElement('div');
            div.className = 'board-item';
            div.id = 'board-item-' + item.id;
            div.style.left = item.position_x + 'px';
            div.style.top = item.position_y + 'px';
            div.style.backgroundColor = item.color;
            div.draggable = true;
            
            div.innerHTML = `
                <div class="board-item-header">
                    <span class="board-item-user">${escapeHtml(item.user)}</span>
                    <div class="board-item-actions">
                        <input type="color" value="${item.color}" onchange="changeBoardItemColor(${item.id}, this.value)" title="שנה צבע">
                        <button onclick="deleteBoardItem(${item.id})" title="מחק פתק">🗑️</button>
                    </div>
                </div>
                <div class="board-item-content" contenteditable="true" onblur="saveBoardItemContent(${item.id}, this.textContent)" placeholder="כתוב כאן...">${escapeHtml(item.content)}</div>
            `;
            
            // Make draggable
            let isDragging = false;
            let dragOffset = { x: 0, y: 0 };
            
            div.addEventListener('mousedown', (e) => {
                if (e.target.closest('.board-item-header') || e.target.closest('.board-item-header *')) {
                    isDragging = true;
                    draggedItem = item.id;
                    div.classList.add('dragging');
                    const rect = div.getBoundingClientRect();
                    const containerRect = container.getBoundingClientRect();
                    dragOffset.x = e.clientX - rect.left;
                    dragOffset.y = e.clientY - rect.top;
                    e.preventDefault();
                }
            });
            
            document.addEventListener('mousemove', (e) => {
                if (isDragging && draggedItem === item.id) {
                    const containerRect = container.getBoundingClientRect();
                    let x = e.clientX - containerRect.left - dragOffset.x;
                    let y = e.clientY - containerRect.top - dragOffset.y;
                    
                    // Keep within bounds
                    x = Math.max(0, Math.min(x, containerRect.width - div.offsetWidth));
                    y = Math.max(0, Math.min(y, containerRect.height - div.offsetHeight));
                    
                    div.style.left = x + 'px';
                    div.style.top = y + 'px';
                }
            });
            
            document.addEventListener('mouseup', (e) => {
                if (isDragging && draggedItem === item.id) {
                    isDragging = false;
                    div.classList.remove('dragging');
                    const containerRect = container.getBoundingClientRect();
                    const x = parseFloat(div.style.left);
                    const y = parseFloat(div.style.top);
                    updateBoardItemPosition(draggedItem, x, y);
                    draggedItem = null;
                }
            });
            
            container.appendChild(div);
        }
        
        function saveBoardItem(item) {
            const formData = new FormData();
            formData.append('action', 'save_shared_board_item');
            formData.append('page_id', currentPageId);
            formData.append('item_id', item.id);
            formData.append('content', item.content);
            formData.append('position_x', item.position_x);
            formData.append('position_y', item.position_y);
            formData.append('color', item.color);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.id) {
                    item.id = data.id;
                    boardItems[data.id] = item;
                    document.getElementById('board-item-' + (item.id || 0)).id = 'board-item-' + data.id;
                }
            })
            .catch(error => console.error('Error saving board item:', error));
        }
        
        function updateBoardItemPosition(itemId, x, y) {
            const item = boardItems[itemId];
            if (item) {
                item.position_x = x;
                item.position_y = y;
                saveBoardItem(item);
            }
        }
        
        function saveBoardItemContent(itemId, content) {
            const item = boardItems[itemId];
            if (item) {
                item.content = content;
                saveBoardItem(item);
            }
        }
        
        function changeBoardItemColor(itemId, color) {
            const item = boardItems[itemId];
            if (item) {
                item.color = color;
                document.getElementById('board-item-' + itemId).style.backgroundColor = color;
                saveBoardItem(item);
            }
        }
        
        function deleteBoardItem(itemId) {
            if (!confirm('האם אתה בטוח שברצונך למחוק פתק זה?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_shared_board_item');
            formData.append('item_id', itemId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('board-item-' + itemId).remove();
                    delete boardItems[itemId];
                }
            })
            .catch(error => console.error('Error deleting board item:', error));
        }
        // Global variables
        let categoryChart = null;
        let updateChart = null;
        let network = null;
        
        // Tab switching
        function showTab(tabName, event) {
            // Remove active class from all tabs and tab contents
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            // Use currentTarget instead of target to get the button even if icon was clicked
            let clickedButton = null;
            if (event) {
                clickedButton = event.currentTarget || event.target.closest('.tab');
                if (clickedButton && clickedButton.classList.contains('tab')) {
                    clickedButton.classList.add('active');
                } else if (event.target && event.target.closest('.tab')) {
                    event.target.closest('.tab').classList.add('active');
                }
            }
            
            // Fallback: find tab button by tabName
            if (!clickedButton || !clickedButton.classList.contains('active')) {
                document.querySelectorAll('.tab').forEach(tab => {
                    if (tab.getAttribute('onclick') && tab.getAttribute('onclick').includes("'" + tabName + "'")) {
                        tab.classList.add('active');
                    }
                });
            }
            
            // Activate corresponding tab content
            const tabContent = document.getElementById(tabName + '-tab');
            if (tabContent) {
                tabContent.classList.add('active');
            }
            
            if (tabName === 'dashboard') {
                loadDashboard();
            } else if (tabName === 'map') {
                console.log('Map tab clicked, loading map...');
                // Small delay to ensure tab is visible
                setTimeout(() => {
                loadMap();
                }, 100);
            } else if (tabName === 'audit') {
                loadAudit();
            } else if (tabName === 'board') {
                loadSharedBoard();
            }
        }
        
        // Update row color when color input changes
        function updateRowColorFromInput(colorInput) {
            const row = colorInput.closest('tr');
            if (row && colorInput.value) {
                row.style.backgroundColor = colorInput.value + '20'; // 20 = 12.5% opacity in hex
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
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.classList.add('saved');
                    setTimeout(() => row.classList.remove('saved'), 2000);
                    
                    // Update row background color if color field changed
                    if (field === 'color' && value) {
                        row.style.backgroundColor = value + '20'; // 20 = 12.5% opacity in hex
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
                
                fetch('api.php', {
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
            if (!tbody) {
                showNotification('❌ לא נמצא טבלה', 'error');
                return;
            }
            const row = document.createElement('tr');
            const defaultColor = '#3498db';
            row.style.backgroundColor = defaultColor + '20'; // Set initial background color
            row.innerHTML = `
                <td>
                    <select class="editable" data-field="category" onchange="saveNewRow(this)">
                        <option value="קטגוריה 1">קטגוריה 1</option>
                        <option value="קטגוריה 2">קטגוריה 2</option>
                        <option value="קטגוריה 3">קטגוריה 3</option>
                        <option value="קטגוריה 4">קטגוריה 4</option>
                    </select>
                </td>
                <td><input type="text" class="editable" data-field="feature" onblur="saveNewRow(this)" placeholder="שם פיצ'ר" /></td>
                <td>
                    <div class="description-wrapper" data-field="description">
                        <div class="description-display" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; min-height: 40px; cursor: text; display: none;" onclick="editDescription(this)"></div>
                        <textarea class="editable description-edit" data-field="description" onblur="saveNewRow(this)" rows="2" placeholder="תיאור"></textarea>
                    </div>
                </td>
                <td><input type="color" class="color-input editable" data-field="color" value="#3498db" onchange="updateRowColorFromInput(this); saveNewRow(this);" /></td>
                <td>-</td>
                <td>-</td>
                <td><button class="delete-btn" onclick="this.closest('tr').remove()">
                    <i class="fas fa-trash-alt icon-small"></i>
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
        
        // Enhanced search functionality - searches all columns including inputs, selects, textareas
        function filterTable() {
            const input = document.getElementById('search-input');
            if (!input) return;
            const filter = input.value.toLowerCase().trim();
            const table = document.getElementById('features-table');
            if (!table) return;
            const tr = table.getElementsByTagName('tr');
            
            // If filter is empty, show all rows
            if (!filter) {
                for (let i = 1; i < tr.length; i++) {
                    tr[i].style.display = '';
                }
                return;
            }
            
            // Search in all columns including custom columns
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                // Search in all cells including inputs, selects, textareas
                for (let j = 0; j < td.length; j++) {
                    const cell = td[j];
                    let txtValue = '';
                    
                    // Get text from various input types
                    const textInput = cell.querySelector('input[type="text"], input[type="number"], input[type="date"]');
                    const select = cell.querySelector('select');
                    const textarea = cell.querySelector('textarea');
                    const colorInput = cell.querySelector('input[type="color"]');
                    
                    if (textInput) {
                        txtValue = (textInput.value || textInput.placeholder || '').toLowerCase();
                    } else if (select) {
                        txtValue = (select.options[select.selectedIndex]?.text || '').toLowerCase();
                    } else if (textarea) {
                        txtValue = (textarea.value || textarea.placeholder || '').toLowerCase();
                    } else if (colorInput) {
                        txtValue = colorInput.value.toLowerCase();
                    } else {
                        // Regular text content
                        txtValue = (cell.textContent || cell.innerText || '').toLowerCase();
                    }
                    
                    // Also check data attributes
                    const dataValue = (cell.getAttribute('data-value') || '').toLowerCase();
                    const allText = txtValue + ' ' + dataValue;
                    
                    if (allText.indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }
        
        function filterAudit() {
            const input = document.getElementById('audit-search-input');
            if (!input) return;
            const filter = input.value.toLowerCase();
            const items = document.querySelectorAll('#audit-log .audit-item');
            
            items.forEach(item => {
                const text = item.getAttribute('data-audit-text') || '';
                item.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
        }
        
        function filterPermissions() {
            const input = document.getElementById('permissions-search-input');
            if (!input) return;
            const filter = input.value.toLowerCase();
            const items = document.querySelectorAll('#permissions-list > div');
            
            items.forEach(item => {
                const text = (item.textContent || item.innerText || '').toLowerCase();
                item.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
        }
        
        // Load audit
        function loadAudit() {
            const formData = new FormData();
            formData.append('action', 'get_audit');
            formData.append('limit', 100);
            
            fetch('api.php', {
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
                    <div class="audit-item" data-audit-text="${(item.action + ' ' + (item.category || '') + ' ' + (item.feature || '') + ' ' + (item.field_name || '') + ' ' + item.user + ' ' + item.created_at + ' ' + (item.old_value || '') + ' ' + (item.new_value || '')).toLowerCase()}">
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            if (!title || !title.trim()) return;
            
            console.log('Creating new page with title:', title);
            
            const formData = new FormData();
            formData.append('action', 'create_page');
            formData.append('title', title.trim());
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status, response.statusText);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Response text:', text);
                        throw new Error('Network response was not ok: ' + response.status + ' - ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data && data.success) {
                    showNotification('✅ הדף נוצר בהצלחה!', 'success');
                    // Reload pages list
                    loadPages();
                    // Wait a bit for pages to load, then switch
                    setTimeout(() => {
                        if (data.id) {
                            console.log('Switching to page:', data.id);
                    switchPage(data.id);
                        } else {
                            console.log('No page ID, reloading...');
                            window.location.reload();
                        }
                    }, 500);
                } else {
                    const errorMsg = (data && data.message) ? data.message : 'שגיאה לא ידועה';
                    showNotification('❌ שגיאה ביצירת הדף: ' + errorMsg, 'error');
                    console.error('Create page error:', data);
                }
            })
            .catch(error => {
                console.error('Error creating page:', error);
                showNotification('❌ שגיאה ביצירת הדף: ' + error.message, 'error');
            });
        }
        
        function deletePage(pageId) {
            if (!confirm('האם אתה בטוח שברצונך למחוק דף זה?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_page');
            formData.append('page_id', pageId);
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            const page = pages.find(p => p.id == currentPageId);
            const currentLockState = page && page.is_locked ? 1 : 0;
            const newLockState = 1 - currentLockState;
            
            // If unlocking, ask for PIN
            if (newLockState === 0) {
                const pin = prompt('הזן קוד PIN לפתיחת הדף:');
                if (pin === null) return; // User cancelled
                
            const formData = new FormData();
            formData.append('action', 'toggle_page_lock');
            formData.append('page_id', currentPageId);
                formData.append('lock_state', newLockState);
                formData.append('pin', pin || '');
            
                fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPages();
                        const updatedPage = pages.find(p => p.id == currentPageId);
                        isPageLocked = updatedPage && updatedPage.is_locked;
                        const lockBtn = document.getElementById('lock-btn');
                        lockBtn.innerHTML = `<i class="fas fa-${isPageLocked ? 'lock' : 'unlock'}"></i>`;
                        
                        // Enable editing
                        document.querySelectorAll('.editable').forEach(el => {
                            el.disabled = false;
                            el.style.opacity = '1';
                        });
                        
                        showNotification('🔓 הדף נפתח', 'success');
                    } else {
                        showNotification('❌ ' + (data.message || 'שגיאה בפתיחת הדף'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('❌ שגיאה בפתיחת הדף', 'error');
                });
                return;
            }
            
            // If locking, ask for PIN (optional)
            const pin = prompt('הזן קוד PIN לנעילת הדף (אופציונלי, השאר ריק):');
            if (pin === null) return; // User cancelled
            
            const formData = new FormData();
            formData.append('action', 'toggle_page_lock');
            formData.append('page_id', currentPageId);
            formData.append('lock_state', newLockState);
            formData.append('pin', pin || '');
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPages();
                    const updatedPage = pages.find(p => p.id == currentPageId);
                    isPageLocked = updatedPage && updatedPage.is_locked;
                    const lockBtn = document.getElementById('lock-btn');
                    lockBtn.innerHTML = `<svg class="icon"><use href="#icon-${isPageLocked ? 'lock' : 'unlock'}"></use></svg>`;
                    
                    // Disable editing
                    document.querySelectorAll('.editable').forEach(el => {
                        el.disabled = true;
                        el.style.opacity = '0.6';
                    });
                    
                    showNotification('🔒 הדף ננעל' + (pin ? ' עם קוד PIN' : ''), 'success');
                } else {
                    showNotification('❌ ' + (data.message || 'שגיאה בנעילת הדף'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ שגיאה בנעילת הדף', 'error');
            });
        }
        
        // Column management
        function showColumnManager() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'column-manager-modal';
            
            const formData = new FormData();
            formData.append('action', 'get_custom_columns');
            formData.append('page_id', currentPageId);
            
            fetch('api.php', {
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
                                <button class="btn-success" onclick="addColumn(event); event.preventDefault(); event.stopPropagation(); return false;">➕ הוסף עמודה</button>
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
                            <div class="search-container" style="margin-bottom: 16px;">
                                <input type="text" id="permissions-search-input" class="search-input" placeholder="חפש משתמש..." onkeyup="filterPermissions()">
                                <span class="search-icon">
                                    <i class="fas fa-search"></i>
                                </span>
                            </div>
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
                                <button class="btn-success" onclick="addPermission(); event.preventDefault(); event.stopPropagation(); return false;">➕ הוסף הרשאה</button>
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
                
                return fetch('api.php', {
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
            if (btn) {
                btn.innerHTML = mapEditMode ? 
                    '<i class="fas fa-eye" style="margin-left: 6px;"></i> מצב צפייה' : 
                    '<i class="fas fa-edit" style="margin-left: 6px;"></i> מצב עריכה';
            btn.style.background = mapEditMode ? '#10b981' : '#2563eb';
            }
            
            if (network) {
                network.setOptions({
                    layout: {
                        hierarchical: {
                            enabled: !mapEditMode,
                            direction: 'UD',
                            sortMethod: 'directed',
                            levelSeparation: 300,
                            nodeSpacing: 350,
                            treeSpacing: 400,
                            blockShifting: true,
                            edgeMinimization: true,
                            parentCentralization: true
                        }
                    },
                    physics: {
                        enabled: true,
                        hierarchicalRepulsion: {
                            centralGravity: 0.0,
                            springLength: 100,
                            springConstant: 0.01,
                            nodeDistance: 350,
                            damping: 0.09
                        },
                        solver: mapEditMode ? 'forceAtlas2Based' : 'hierarchicalRepulsion',
                        stabilization: {
                            enabled: true,
                            iterations: 100,
                            updateInterval: 25
                        }
                    },
                    interaction: {
                        dragNodes: mapEditMode,
                        dragView: true,
                        zoomView: true
                    }
                });
                
                // Re-stabilize the network when switching modes
                network.stabilize();
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
                level: 1,
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
            console.log('loadMap() called, currentPageId:', currentPageId);
            const container = document.getElementById('feature-map');
            if (!container) {
                console.error('Map container not found');
                return;
            }
            
            // Show loading message
            container.innerHTML = '<div style="padding: 40px; text-align: center;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary-color); margin-bottom: 16px;"></i><p style="color: var(--text-secondary);">טוען מפה...</p></div>';
            
            // Use ensureVisNetwork to wait for vis-network to be ready
            ensureVisNetwork(function(success) {
                if (!success || typeof vis === 'undefined' || !vis.Network) {
                    console.error('vis-network not available after waiting');
                    container.innerHTML = '<div style="padding: 40px; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i><p>❌ שגיאה: לא ניתן לטעון את ספריית vis-network</p><button class="btn-primary" onclick="loadMap()" style="margin-top: 16px;">נסה שוב</button></div>';
                    return;
                }
                
                console.log('vis-network is ready, fetching map data...');
                loadMapData();
            });
        }
        
        function loadMapData() {
            const container = document.getElementById('feature-map');
            
            console.log('vis-network is loaded, fetching data...');
            const formData = new FormData();
            formData.append('action', 'get_data');
            formData.append('page_id', currentPageId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Map data response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Map data response error:', text);
                        throw new Error('Network response was not ok: ' + response.status);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Map data received:', data ? data.length + ' items' : 'null/empty');
                if (!data || data.length === 0) {
                    container.innerHTML = '<p style="padding: 20px; text-align: center; color: #6b7280;">אין נתונים להצגה במפה</p>';
                    return;
                }
                
                // Double-check vis-network is loaded
                if (typeof vis === 'undefined' || !vis.Network || !vis.DataSet) {
                    console.error('vis-network still not loaded after fetch, vis:', typeof vis, 'Network:', typeof vis !== 'undefined' ? typeof vis.Network : 'N/A', 'DataSet:', typeof vis !== 'undefined' ? typeof vis.DataSet : 'N/A');
                    container.innerHTML = '<div style="padding: 40px; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i><p>❌ שגיאה: ספריית vis-network לא נטענה</p><button class="btn-primary" onclick="loadMap()" style="margin-top: 16px;">נסה שוב</button></div>';
                    return;
                }
                
                const nodes = [];
                const edges = [];
                const categories = {};
                const categoryColors = {
                    'קטגוריה 1': '#2563eb',
                    'קטגוריה 2': '#10b981',
                    'קטגוריה 3': '#f59e0b',
                    'קטגוריה 4': '#8b5cf6'
                };
                
                data.forEach((item, index) => {
                    if (!item.category) return; // Skip items without category
                    
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
                        label: (item.feature || 'ללא שם').substring(0, 30) + ((item.feature || '').length > 30 ? '...' : ''),
                        group: item.category,
                        level: 1,
                        color: {
                            background: item.color || '#3498db',
                            border: categoryColors[item.category] || '#6b7280',
                            highlight: { background: item.color || '#3498db', border: '#000' }
                        },
                        title: item.description || item.feature || '',
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
                
                // Connect categories to features (for hierarchical layout)
                data.forEach(item => {
                    if (!item.category || !categories[item.category]) return;
                    const catId = categories[item.category].id;
                    edges.push({
                        from: 'cat_' + catId,
                        to: item.id,
                        arrows: { to: { enabled: true, scaleFactor: 0.8 } },
                        color: { color: '#9ca3af', highlight: '#000' },
                        width: 2,
                        smooth: { type: 'continuous', roundness: 0.5 }
                    });
                });
                
                console.log('Creating network with', nodes.length, 'nodes and', edges.length, 'edges');
                
                if (nodes.length === 0) {
                    container.innerHTML = '<p style="padding: 20px; text-align: center; color: #6b7280;">אין צמתים להצגה במפה</p>';
                    return;
                }
                
                // Ensure DataSets are used for vis-network
                const networkData = { 
                    nodes: new vis.DataSet(nodes), 
                    edges: new vis.DataSet(edges) 
                };
                const options = {
                    nodes: {
                        font: { size: 12, face: 'Arial' },
                        borderWidth: 2,
                        shadow: true,
                        margin: 20,
                        spacing: 50,
                        size: 30
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
                            levelSeparation: 300,
                            nodeSpacing: 350,
                            treeSpacing: 400,
                            blockShifting: true,
                            edgeMinimization: true,
                            parentCentralization: true
                        }
                    },
                    physics: {
                        enabled: true,
                        hierarchicalRepulsion: {
                            centralGravity: 0.0,
                            springLength: 100,
                            springConstant: 0.01,
                            nodeDistance: 350,
                            damping: 0.09
                        },
                        solver: mapEditMode ? 'forceAtlas2Based' : 'hierarchicalRepulsion',
                        stabilization: {
                            enabled: true,
                            iterations: 100,
                            updateInterval: 25
                        }
                    },
                    interaction: {
                        dragNodes: mapEditMode,
                        dragView: true,
                        zoomView: true,
                        navigationButtons: true,
                        keyboard: true
                    }
                };
                
                try {
                    console.log('Destroying old network if exists...');
                    if (network) {
                        try {
                            network.destroy();
                        } catch (e) {
                            console.warn('Error destroying old network:', e);
                        }
                        network = null;
                    }
                    
                    console.log('Creating new vis.Network...');
                    if (!vis || !vis.Network || !vis.DataSet) {
                        throw new Error('vis.Network or vis.DataSet is not available');
                    }
                    
                    // Ensure container is visible
                    const mapTab = document.getElementById('map-tab');
                    if (mapTab && !mapTab.classList.contains('active')) {
                        console.warn('Map tab is not active, network may not render correctly');
                    }
                    
                network = new vis.Network(container, networkData, options);
                    console.log('Network created successfully, waiting for stabilization...');
                
                // Node selection
                network.on('click', function(params) {
                    if (params.nodes.length > 0) {
                        selectedNodeId = params.nodes[0];
                            if (mapEditMode && network && network.body && network.body.data) {
                                const nodeData = network.body.data.nodes.get(selectedNodeId);
                                if (nodeData) {
                                    showNotification('צומת נבחר: ' + nodeData.label, 'success');
                                }
                        }
                    }
                });
                
                    // Handle errors
                    network.on('error', function(error) {
                        console.error('Network error:', error);
                    });
                    
                    // Wait for stabilization then fit to screen
                    network.once('stabilizationEnd', function() {
                setTimeout(() => {
                    if (network) {
                                try {
                        network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
                                } catch (e) {
                                    console.error('Error fitting network:', e);
                                    try {
                                        network.fit();
                                    } catch (e2) {
                                        console.error('Error fitting network (fallback):', e2);
                                    }
                                }
                    }
                }, 100);
                    });
                    
                    // Also listen for stabilizationIterationsDone as fallback
                    network.once('stabilizationIterationsDone', function() {
                        setTimeout(() => {
                            if (network && !network.isStabilizing()) {
                                try {
                                    network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
                                } catch (e) {
                                    console.error('Error fitting network (iterations done):', e);
                                    try {
                                        network.fit();
                                    } catch (e2) {
                                        console.error('Error fitting network (iterations done fallback):', e2);
                                    }
                                }
                            }
                        }, 100);
                    });
                    
                    // Fallback: fit after a delay even if stabilization event doesn't fire
                    setTimeout(() => {
                        if (network) {
                            try {
                                if (!network.isStabilizing()) {
                                    network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
                                } else {
                                    // Wait a bit more if still stabilizing
                                    setTimeout(() => {
                                        if (network) {
                                            try {
                                                network.fit();
                                            } catch (e) {
                                                console.error('Error fitting network (timeout):', e);
                                            }
                                        }
                                    }, 500);
                                }
                            } catch (e) {
                                console.error('Error fitting network:', e);
                                try {
                                    network.fit();
                                } catch (e2) {
                                    console.error('Error fitting network (fallback):', e2);
                                }
                            }
                        }
                    }, 1500);
                } catch (error) {
                    console.error('Error creating network:', error);
                    container.innerHTML = '<div style="padding: 40px; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i><p>❌ שגיאה ביצירת המפה: ' + error.message + '</p><button class="btn-primary" onclick="loadMap()" style="margin-top: 16px;">נסה שוב</button></div>';
                }
            })
            .catch(error => {
                console.error('Error loading map:', error);
                const container = document.getElementById('feature-map');
                if (container) {
                    container.innerHTML = '<div style="padding: 40px; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i><p>❌ שגיאה בטעינת המפה: ' + error.message + '</p><button class="btn-primary" onclick="loadMap()" style="margin-top: 16px;">נסה שוב</button></div>';
                }
            });
        }
        
        function refreshTable(data) {
            // Reload the entire page to ensure all data including custom columns is refreshed
            // This is the most reliable way to ensure consistency
            location.reload();
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
                        <textarea id="new-comment" class="comment-textarea" placeholder="כתוב תגובה..." rows="4"></textarea>
                        <button class="btn-primary" onclick="addComment(${featureId})" style="margin-top: 8px;">📤 שלח תגובה</button>
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
                        <textarea id="new-comment" class="comment-textarea" placeholder="כתוב תגובה..." rows="4"></textarea>
                        <button class="btn-primary" onclick="addComment(${featureId})" style="margin-top: 8px;">📤 שלח תגובה</button>
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
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(comments => {
                if (!container) container = document.getElementById('comments-list');
                if (!container) {
                    console.error('Comments container not found');
                    return;
                }
                if (!Array.isArray(comments)) {
                    console.error('Invalid comments data:', comments);
                    container.innerHTML = '<p style="text-align: center; color: var(--error-color); padding: 20px;">❌ שגיאה בטעינת התגובות</p>';
                    return;
                }
                if (comments.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">אין תגובות עדיין</p>';
                } else {
                    container.innerHTML = comments.map(comment => `
                        <div class="comment-item">
                            <div class="comment-header">
                                <span class="comment-user">${escapeHtml(comment.user || 'Unknown')}</span>
                                <span class="comment-date">${formatDate(comment.created_at || '')}</span>
                            </div>
                            <div class="comment-body">${autoLink(escapeHtml(comment.comment || ''))}</div>
                        </div>
                    `).join('');
                }
            })
            .catch(error => {
                console.error('Error loading comments:', error);
                if (container) {
                    container.innerHTML = '<p style="text-align: center; color: var(--error-color); padding: 20px;">❌ שגיאה בטעינת התגובות</p>';
                }
            });
        }
        
        function addComment(featureId) {
            const commentInput = document.getElementById('new-comment');
            const comment = commentInput.value.trim();
            if (!comment) return;
            
            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('feature_id', featureId);
            formData.append('comment', comment);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    commentInput.value = '';
                    const container = document.getElementById('comments-list');
                    if (container) {
                        loadComments(featureId, container);
                    } else {
                        loadComments(featureId);
                    }
                    showNotification('✅ התגובה נוספה', 'success');
                } else {
                    showNotification('❌ שגיאה בהוספת התגובה: ' + (data.message || 'שגיאה לא ידועה'), 'error');
                }
            })
            .catch(error => {
                console.error('Error adding comment:', error);
                showNotification('❌ שגיאה בהוספת התגובה: ' + error.message, 'error');
            });
        }
        
        // Likes/Dislikes
        function toggleLike(featureId, type) {
            const formData = new FormData();
            formData.append('action', 'toggle_like');
            formData.append('feature_id', featureId);
            formData.append('type', type);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateLikeButtons(featureId);
                } else {
                    showNotification('❌ שגיאה: ' + (data.message || 'שגיאה לא ידועה'), 'error');
                }
            })
            .catch(error => {
                console.error('Error toggling like:', error);
                showNotification('❌ שגיאה: ' + error.message, 'error');
            });
        }
        
        function updateLikeButtons(featureId) {
            const formData = new FormData();
            formData.append('action', 'get_likes');
            formData.append('feature_id', featureId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(likes => {
                if (!likes || typeof likes !== 'object') {
                    console.error('Invalid likes data:', likes);
                    return;
                }
                const likeBtn = document.getElementById('like-btn-' + featureId);
                const dislikeBtn = document.getElementById('dislike-btn-' + featureId);
                
                if (likeBtn) {
                    likeBtn.classList.toggle('active', likes.user_like === 'like');
                    likeBtn.innerHTML = `<i class="fas fa-thumbs-up"></i> ${likes.like || 0}`;
                }
                if (dislikeBtn) {
                    dislikeBtn.classList.toggle('active', likes.user_like === 'dislike');
                    dislikeBtn.innerHTML = `<i class="fas fa-thumbs-down"></i> ${likes.dislike || 0}`;
                }
            })
            .catch(error => {
                console.error('Error loading likes:', error);
            });
        }
        
        // Share
        function showShareModal(featureId) {
            const sharedWith = prompt('הזן שם משתמש או אימייל לשיתוף:');
            if (!sharedWith) return;
            
            const formData = new FormData();
            formData.append('action', 'share_feature');
            formData.append('feature_id', featureId);
            formData.append('shared_with', sharedWith);
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
        // Email tracking
        function showEmailTracking(featureId) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'email-tracking-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>מעקב אימייל - פיצ'ר #${featureId}</h3>
                        <button class="close-btn" onclick="closeModal('email-tracking-modal')">×</button>
                    </div>
                    <div id="tracking-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 16px;"></div>
                    <div class="comment-input-container">
                        <input type="email" id="tracking-email" placeholder="הזן כתובת אימייל למעקב..." style="width: 100%; padding: 12px; margin-bottom: 8px; border: 2px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                        <button class="btn-primary" onclick="addEmailTracking(${featureId})">➕ הוסף מעקב</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            loadEmailTracking(featureId);
        }
        
        function loadEmailTracking(featureId) {
            const formData = new FormData();
            formData.append('action', 'get_tracking');
            formData.append('feature_id', featureId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(tracking => {
                const container = document.getElementById('tracking-list');
                if (tracking.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">אין כתובות אימייל במעקב</p>';
                } else {
                    container.innerHTML = tracking.map(item => `
                        <div class="comment-item">
                            <div class="comment-header">
                                <span class="comment-user">${escapeHtml(item.email)}</span>
                                <button class="delete-btn" onclick="removeEmailTracking(${featureId}, '${escapeHtml(item.email)}')" style="padding: 4px 8px; font-size: 12px;">🗑️ הסר</button>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary);">נוסף על ידי: ${escapeHtml(item.user)} | ${formatDate(item.created_at)}</div>
                        </div>
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function addEmailTracking(featureId) {
            const emailInput = document.getElementById('tracking-email');
            const email = emailInput.value.trim();
            if (!email) {
                showNotification('❌ אנא הזן כתובת אימייל', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_tracking');
            formData.append('feature_id', featureId);
            formData.append('email', email);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ מעקב אימייל נוסף בהצלחה!', 'success');
                    emailInput.value = '';
                    loadEmailTracking(featureId);
                } else {
                    showNotification('❌ ' + (data.message || 'שגיאה בהוספת מעקב'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ שגיאה בהוספת מעקב', 'error');
            });
        }
        
        function removeEmailTracking(featureId, email) {
            const formData = new FormData();
            formData.append('action', 'remove_tracking');
            formData.append('feature_id', featureId);
            formData.append('email', email);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ מעקב אימייל הוסר', 'success');
                    loadEmailTracking(featureId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
            
            fetch('api.php', {
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
        // Dark mode toggle
        function toggleDarkMode() {
            const root = document.documentElement;
            const isDark = root.classList.contains('dark-mode');
            const icon = document.getElementById('dark-mode-icon');
            
            if (isDark) {
                root.classList.remove('dark-mode');
                icon.className = 'fas fa-moon';
                localStorage.setItem('darkMode', 'false');
            } else {
                root.classList.add('dark-mode');
                icon.className = 'fas fa-sun';
                localStorage.setItem('darkMode', 'true');
            }
        }
        
        // Initialize dark mode from localStorage
        window.addEventListener('load', function() {
            const darkMode = localStorage.getItem('darkMode');
            const root = document.documentElement;
            const icon = document.getElementById('dark-mode-icon');
            
            if (darkMode === 'true') {
                root.classList.add('dark-mode');
                if (icon) icon.className = 'fas fa-sun';
            } else {
                root.classList.remove('dark-mode');
                if (icon) icon.className = 'fas fa-moon';
            }
            
            renderPageTabs();
            
            // Apply auto-linking to all text content
            applyAutoLinks();
            
            // Pre-load vis-network if map tab might be opened
            setTimeout(() => {
                if (typeof vis === 'undefined' || !vis.Network) {
                    console.log('Pre-loading vis-network...');
                    loadVisNetworkFromCDN();
                }
            }, 1000);
            
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
            
            // Start real-time polling
            startPolling();
            
            // Stop polling when page is hidden
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopPolling();
                } else {
                    startPolling();
                }
            });
            
            // Clean up on page unload
            window.addEventListener('beforeunload', () => {
                stopPolling();
            });
            
        });
    </script>
</body>
</html>

