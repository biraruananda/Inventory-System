<?php
session_start();
require_once 'config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user is admin (optional access control)
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_user = isset($_SESSION['role']) && $_SESSION['role'] === 'user';

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_settings'])) {
        try {
            // Update settings in database
            $settings = [
                'company_name' => $_POST['company_name'],
                'admin_email' => $_POST['admin_email'],
                'items_per_page' => $_POST['items_per_page'],
                'low_stock_threshold' => $_POST['low_stock_threshold'],
                'theme' => $_POST['theme'],
                'currency' => $_POST['currency'],
                'enable_notifications' => isset($_POST['enable_notifications']) ? 1 : 0
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
            
            $success = "Settings saved successfully!";
        } catch (PDOException $e) {
            $error = "Error saving settings: " . $e->getMessage();
        }
    }
    
    // Handle backup request
    if (isset($_POST['create_backup'])) {
        $backup_result = createDatabaseBackup($pdo);
        if ($backup_result['success']) {
            $success = "Backup created successfully: " . $backup_result['file'];
        } else {
            $error = "Backup failed: " . $backup_result['error'];
        }
    }
}

// Function to create database backup
function createDatabaseBackup($pdo) {
    $backup_dir = 'backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    try {
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $output = "";
        foreach ($tables as $table) {
            // Table structure
            $output .= "-- Table structure for table `$table`\n";
            $create_table = $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_ASSOC);
            $output .= $create_table['Create Table'] . ";\n\n";
            
            // Table data
            $output .= "-- Dumping data for table `$table`\n";
            $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                $output .= "INSERT INTO `$table` VALUES ";
                $values = [];
                foreach ($rows as $row) {
                    $row_values = array_map(function($value) use ($pdo) {
                        if ($value === null) return 'NULL';
                        return $pdo->quote($value);
                    }, $row);
                    $values[] = "(" . implode(', ', $row_values) . ")";
                }
                $output .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        // Write to file
        if (file_put_contents($backup_file, $output)) {
            return ['success' => true, 'file' => $backup_file];
        } else {
            return ['success' => false, 'error' => 'Could not write to file'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $error = "Error loading settings: " . $e->getMessage();
}

// Set default values if settings don't exist
$default_settings = [
    'company_name' => 'Inventory System',
    'admin_email' => 'admin@example.com',
    'items_per_page' => '20',
    'low_stock_threshold' => '10',
    'theme' => 'light',
    'currency' => 'USD',
    'enable_notifications' => '1'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --accent: #7209b7;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --danger: #f72585;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e7ec 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 1rem;
        }
        
        .settings-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 15px;
        }
        
        .settings-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .settings-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .settings-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }
        
        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 0.75rem 1.5rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="fas fa-box-open me-2"></i>
                <strong>Inventory System</strong>
            </a>
            
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
    <a class="nav-link" href="profile.php">
        <i class="fas fa-user me-1"></i> 
        <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?>
        <span class="badge bg-<?php echo $is_admin ? 'warning' : 'info'; ?> ms-1">
            <?php echo $is_admin ? 'Admin' : 'User'; ?>
        </span>
    </a>
</li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php"><i class="fas fa-cog me-1"></i> Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="settings-container">
        <!-- Notification messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="settings-card">
            <div class="settings-header">
                <h2><i class="fas fa-cog me-2"></i>System Settings</h2>
                <p>Manage your inventory system preferences</p>
            </div>
            
            <div class="settings-body">
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                            <i class="fas fa-sliders-h me-2"></i>General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab" aria-controls="appearance" aria-selected="false">
                            <i class="fas fa-paint-brush me-2"></i>Appearance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab" aria-controls="backup" aria-selected="false">
                            <i class="fas fa-database me-2"></i>Backup
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabsContent">
                    <!-- General Settings Tab -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                        <form method="POST">
                            <input type="hidden" name="save_settings" value="1">
                            
                            <div class="card">
                                <div class="card-header">Company Information</div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Admin Email</label>
                                            <input type="email" class="form-control" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">Inventory Settings</div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Items Per Page</label>
                                            <input type="number" class="form-control" name="items_per_page" value="<?php echo htmlspecialchars($settings['items_per_page']); ?>" min="5" max="100" required>
                                            <div class="form-text">Number of items to display per page</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Low Stock Threshold</label>
                                            <input type="number" class="form-control" name="low_stock_threshold" value="<?php echo htmlspecialchars($settings['low_stock_threshold']); ?>" min="1" required>
                                            <div class="form-text">Alert when stock falls below this number</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="enable_notifications" id="enable_notifications" <?php echo $settings['enable_notifications'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_notifications">Enable Email Notifications</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </form>
                    </div>
                    
                    <!-- Appearance Settings Tab -->
                    <div class="tab-pane fade" id="appearance" role="tabpanel" aria-labelledby="appearance-tab">
                        <form method="POST">
                            <input type="hidden" name="save_settings" value="1">
                            
                            <div class="card">
                                <div class="card-header">Theme Settings</div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Theme</label>
                                            <select class="form-select" name="theme">
                                                <option value="light" <?php echo $settings['theme'] == 'light' ? 'selected' : ''; ?>>Light</option>
                                                <option value="dark" <?php echo $settings['theme'] == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                                <option value="blue" <?php echo $settings['theme'] == 'blue' ? 'selected' : ''; ?>>Blue</option>
                                                <option value="green" <?php echo $settings['theme'] == 'green' ? 'selected' : ''; ?>>Green</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Currency</label>
                                            <select class="form-select" name="currency">
                                                <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                                <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                                <option value="GBP" <?php echo $settings['currency'] == 'GBP' ? 'selected' : ''; ?>>British Pound (£)</option>
                                                <option value="JPY" <?php echo $settings['currency'] == 'JPY' ? 'selected' : ''; ?>>Japanese Yen (¥)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Appearance Settings</button>
                        </form>
                    </div>
                    
                    <!-- Backup Tab -->
                    <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                        <div class="card">
                            <div class="card-header">Database Backup</div>
                            <div class="card-body">
                                <p>Create a backup of your database. This will save all your products, categories, and settings.</p>
                                
                                <form method="POST">
                                    <input type="hidden" name="create_backup" value="1">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-download me-2"></i>Create Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">System Information</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                                        <p><strong>Database:</strong> MySQL</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                                        <p><strong>System Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable Bootstrap tabs
        const triggerTabList = document.querySelectorAll('#settingsTabs button');
        triggerTabList.forEach(triggerEl => {
            new bootstrap.Tab(triggerEl);
        });
        
        // Show confirmation before creating backup
        const backupForm = document.querySelector('form[action*="create_backup"]');
        if (backupForm) {
            backupForm.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to create a database backup?')) {
                    e.preventDefault();
                }
            });
        }
    </script>
</body>
</html>