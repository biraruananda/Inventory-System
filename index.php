 <?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

// Check user role from session
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// File upload configuration
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Allowed image types
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Function to create database and tables
function initializeDatabase($host, $username, $password, $dbname) {
    try {
        // Connect without selecting a database
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
        $pdo->exec("USE $dbname");
        
        // Create categories table
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create products table
        $sql = "CREATE TABLE IF NOT EXISTS products (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            quantity INT(11) NOT NULL,
            category_id INT(11),
            image_url VARCHAR(500),
            sku VARCHAR(100) UNIQUE,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        )";
        $pdo->exec($sql);
        
        // Check and add missing columns
        $columns_to_check = [
            'sku' => "ALTER TABLE products ADD COLUMN sku VARCHAR(100) UNIQUE AFTER category_id",
            'status' => "ALTER TABLE products ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER sku",
            'updated_at' => "ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        ];
        
        foreach ($columns_to_check as $column => $alter_sql) {
            $checkColumn = $pdo->query("SHOW COLUMNS FROM products LIKE '$column'");
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec($alter_sql);
            }
        }
        
        // Insert sample categories if they don't exist
        $checkCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        if ($checkCategories == 0) {
            $sampleCategories = [
                ['Electronics', 'Electronic devices and accessories'],
                ['Clothing', 'Apparel and fashion items'],
                ['Books', 'Books and publications'],
                ['Home & Garden', 'Home and garden supplies'],
                ['Sports', 'Sports equipment and accessories']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            foreach ($sampleCategories as $category) {
                $stmt->execute($category);
            }
        }
        
        // Insert sample products with images if none exist
        $checkProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        if ($checkProducts == 0) {
            $sampleProducts = [
                ['SM001', 'Smartphone', 'Latest smartphone with high-resolution camera and long battery life', 599.99, 25, 1, 'active', 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'],
                ['LT002', 'Laptop', 'Powerful laptop with fast processor and large storage capacity', 1299.99, 15, 1, 'active', 'https://images.unsplash.com/photo-1587614382346-4ec70e388b28?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'],
                ['TS003', 'T-Shirt', 'Comfortable cotton t-shirt available in multiple colors', 24.99, 100, 2, 'active', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'],
                ['NV004', 'Novel', 'Bestselling fiction novel by acclaimed author', 14.99, 50, 3, 'active', 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'],
                ['GT005', 'Garden Tools', 'Set of durable gardening tools for home use', 49.99, 30, 4, 'active', 'https://images.unsplash.com/photo-1572981779307-38f8b8849d15?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'],
                ['BB006', 'Basketball', 'Professional quality basketball for indoor and outdoor use', 29.99, 45, 5, 'active', 'https://images.unsplash.com/photo-1546519638-68e109498ffc?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO products (sku, name, description, price, quantity, category_id, status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($sampleProducts as $product) {
                $stmt->execute($product);
            }
        }
        
        return $pdo;
    } catch(PDOException $e) {
        die("ERROR: Could not initialize database. " . $e->getMessage());
    }
}

// Initialize database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check and add missing columns
    $columns_to_check = [
        'sku' => "ALTER TABLE products ADD COLUMN sku VARCHAR(100) UNIQUE AFTER category_id",
        'status' => "ALTER TABLE products ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER sku",
        'updated_at' => "ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
    ];
    
    foreach ($columns_to_check as $column => $alter_sql) {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM products LIKE '$column'");
        if ($checkColumn->rowCount() == 0) {
            $pdo->exec($alter_sql);
        }
    }
} catch(PDOException $e) {
    // If database doesn't exist, create it
    if ($e->getCode() == 1049) {
        $pdo = initializeDatabase($host, $username, $password, $dbname);
    } else {
        die("ERROR: Could not connect. " . $e->getMessage());
    }
}

// Start session for messages
session_start();

// Initialize variables
$name = $description = $price = $quantity = $category_id = $image_url = $sku = $status = '';
$update = false;
$id = 0;

// Search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category_filter']) ? $_GET['category_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$stock_filter = isset($_GET['stock_filter']) ? $_GET['stock_filter'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Handle file upload
$uploaded_file = '';
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
    $file_type = $_FILES['product_image']['type'];
    $file_size = $_FILES['product_image']['size'];
    
    if (in_array($file_type, $allowed_types) && $file_size < 5000000) { // 5MB limit
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
            $uploaded_file = $file_path;
        }
    }
}

// Create or Update operation
if (isset($_POST['save'])) {
    // Check if user is admin
    if (!$is_admin) {
        $_SESSION['message'] = "Access denied. Admin privileges required.";
        $_SESSION['msg_type'] = "danger";
        header('location: '.$_SERVER['PHP_SELF']);
        exit();
    }
    
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $category_id = $_POST['category_id'];
    $sku = $_POST['sku'];
    $status = $_POST['status'];
    $image_url = $_POST['image_url'];
    
    // Use uploaded file if available, otherwise use URL
    $final_image = !empty($uploaded_file) ? $uploaded_file : $image_url;
    
    if (!empty($name) && is_numeric($price) && is_numeric($quantity)) {
        $sql = "INSERT INTO products (name, description, price, quantity, category_id, sku, status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $description, $price, $quantity, $category_id, $sku, $status, $final_image]);
        
        $_SESSION['message'] = "Product added successfully";
        $_SESSION['msg_type'] = "success";
        
        header('location: '.$_SERVER['PHP_SELF']);
        exit();
    }
}

if (isset($_POST['update'])) {
    // Check if user is admin
    if (!$is_admin) {
        $_SESSION['message'] = "Access denied. Admin privileges required.";
        $_SESSION['msg_type'] = "danger";
        header('location: '.$_SERVER['PHP_SELF']);
        exit();
    }
    
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $category_id = $_POST['category_id'];
    $sku = $_POST['sku'];
    $status = $_POST['status'];
    $image_url = $_POST['image_url'];
    
    // Use uploaded file if available, otherwise use existing image or URL
    $final_image = !empty($uploaded_file) ? $uploaded_file : $image_url;
    
    if (!empty($name) && is_numeric($price) && is_numeric($quantity)) {
        $sql = "UPDATE products SET name=?, description=?, price=?, quantity=?, category_id=?, sku=?, status=?, image_url=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $description, $price, $quantity, $category_id, $sku, $status, $final_image, $id]);
        
        $_SESSION['message'] = "Product updated successfully";
        $_SESSION['msg_type'] = "success";
        
        header('location: '.$_SERVER['PHP_SELF']);
        exit();
    }
}

// Delete operation
if (isset($_GET['delete'])) {
    // Check if user is admin
    if (!$is_admin) {
        $_SESSION['message'] = "Access denied. Admin privileges required.";
        $_SESSION['msg_type'] = "danger";
        header('location: '.$_SERVER['PHP_SELF']);
        exit();
    }
    
    $id = $_GET['delete'];
    
    $sql = "DELETE FROM products WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    $_SESSION['message'] = "Product deleted successfully";
    $_SESSION['msg_type'] = "danger";
    
    header('location: '.$_SERVER['PHP_SELF']);
    exit();
}

// Toggle status operation
if (isset($_GET['toggle_status'])) {
    // Check if user is admin
    if (!$is_admin) {
        $_SESSION['message'] = "Access denied. Admin privileges required.";
        $_SESSION['msg_type'] = "danger";
        header('location: '.$_SERVER['PHP_SELF']);
        exit();
    }
    
    $id = $_GET['toggle_status'];
    
    $sql = "UPDATE products SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    $_SESSION['message'] = "Product status updated successfully";
    $_SESSION['msg_type'] = "success";
    
    header('location: '.$_SERVER['PHP_SELF']);
    exit();
}

// Read operation - for editing
if (isset($_GET['edit'])) {
    // Check if user is admin
    if (!$is_admin) {
        $_SESSION['message'] = "Access denied. Admin privileges required.";
        $_SESSION['msg_type'] = "danger";
        header('location: '.$_SERVER['PHP_SELF']);
        exit();
    }
    
    $id = $_GET['edit'];
    $update = true;
    
    $sql = "SELECT * FROM products WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $id = $row['id'];
        $name = $row['name'];
        $description = $row['description'];
        $price = $row['price'];
        $quantity = $row['quantity'];
        $category_id = $row['category_id'];
        $sku = $row['sku'];
        $status = $row['status'];
        $image_url = $row['image_url'];
    }
}

// Fetch all categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Build query for products with search and filter
$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter) && $category_filter != 'all') {
    $query .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter) && $status_filter != 'all') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($stock_filter)) {
    if ($stock_filter == 'low') {
        $query .= " AND p.quantity < 10";
    } elseif ($stock_filter == 'out') {
        $query .= " AND p.quantity = 0";
    } elseif ($stock_filter == 'in') {
        $query .= " AND p.quantity > 0";
    }
}

// Add sorting
$query .= " ORDER BY $sort_by $sort_order";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for dashboard
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$total_value = $pdo->query("SELECT SUM(price * quantity) FROM products WHERE status = 'active'")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity < 10 AND quantity > 0 AND status = 'active'")->fetchColumn();
$out_of_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity = 0 AND status = 'active'")->fetchColumn();
$active_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

// Get category distribution for chart
$category_stats = $pdo->query("
    SELECT c.name, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active' 
    GROUP BY c.id, c.name
")->fetchAll(PDO::FETCH_ASSOC);

// View mode (table or card)
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'table';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Product Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
            --light: #f8f9fa;
            --dark: #343a40;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            height: fit-content;
        }
        
        .sidebar .nav-link {
            color: #495057;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card {
            color: white;
            padding: 20px;
            border-radius: 10px;
        }
        
        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .stat-card.primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .stat-card.success { background: linear-gradient(135deg, var(--success) 0%, #20c997 100%); }
        .stat-card.warning { background: linear-gradient(135deg, var(--warning) 0%, #fd7e14 100%); }
        .stat-card.danger { background: linear-gradient(135deg, var(--danger) 0%, #e83e8c 100%); }
        .stat-card.info { background: linear-gradient(135deg, var(--info) 0%, #6f42c1 100%); }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .table th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 20px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: #6c757d;
        }
        
        .action-buttons .btn {
            margin-right: 5px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .description-truncate {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
        }
        
        .view-details-btn {
            cursor: pointer;
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .modal-img {
            width: 100%;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .product-card {
            height: 100%;
        }
        
        .product-card-img {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        .view-toggle {
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .view-toggle.active {
            color: var(--primary);
        }
        
        .sortable:hover {
            cursor: pointer;
            background-color: rgba(var(--primary-rgb), 0.1);
        }
        
        .export-btn {
            background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
            border: none;
        }
        
        .user-role-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 100;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-boxes me-2"></i>
                <strong>Advanced Inventory System</strong>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i> 
                            <?php echo isset($_SESSION['user']) ? $_SESSION['user'] : 'User'; ?>
                            <span class="badge bg-<?php echo $is_admin ? 'warning' : 'info'; ?> ms-1">
                                <?php echo $is_admin ? 'Admin' : 'User'; ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="setting.php"><i class="fas fa-cog me-1"></i> Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Notification messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
                <i class="fas <?php echo $_SESSION['msg_type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                    unset($_SESSION['msg_type']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar p-3 mb-4">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-box"></i> Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-tags"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Quick Stats -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Quick Stats</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Products:</span>
                            <strong><?php echo $total_products; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Active Products:</span>
                            <strong><?php echo $active_products; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Value:</span>
                            <strong>$<?php echo number_format($total_value, 2); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Low Stock:</span>
                            <strong class="text-warning"><?php echo $low_stock; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Out of Stock:</span>
                            <strong class="text-danger"><?php echo $out_of_stock; ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Category Distribution Chart -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Categories</h5>
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2>Advanced Inventory Dashboard</h2>
                            <p class="mb-0">Comprehensive product management system</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                            <?php if ($is_admin): ?>
                                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#productModal">
                                    <i class="fas fa-plus me-2"></i>Add Product
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card primary">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Products</h5>
                                    <h3><?php echo $total_products; ?></h3>
                                </div>
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card success">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Active</h5>
                                    <h3><?php echo $active_products; ?></h3>
                                </div>
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card warning">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Total Value</h5>
                                    <h3>$<?php echo number_format($total_value, 2); ?></h3>
                                </div>
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card danger">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Stock Issues</h5>
                                    <h3><?php echo $low_stock + $out_of_stock; ?></h3>
                                </div>
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" class="form-control" placeholder="Search products..." id="searchInput" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="categoryFilter">
                                    <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                       
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="statusFilter">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="stockFilter">
                                    <option value="all" <?php echo $stock_filter == 'all' ? 'selected' : ''; ?>>All Stock</option>
                                    <option value="low" <?php echo $stock_filter == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="in" <?php echo $stock_filter == 'in' ? 'selected' : ''; ?>>In Stock</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex justify-content-end">
                                    <div class="btn-group me-2">
                                        <a href="?view=table<?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>" class="btn btn-outline-primary <?php echo $view_mode == 'table' ? 'active' : ''; ?>">
                                            <i class="fas fa-table"></i>
                                        </a>
                                        <a href="?view=card<?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>" class="btn btn-outline-primary <?php echo $view_mode == 'card' ? 'active' : ''; ?>">
                                            <i class="fas fa-grip-horizontal"></i>
                                        </a>
                                    </div>
                                    <button class="btn btn-primary me-2" id="applyFilters">Apply</button>
                                    <a href="?" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Products Display -->
                <?php if ($view_mode == 'table'): ?>
                <!-- Table View -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            <span>Product List</span>
                            <div>
                                <small class="text-muted">Sort by: </small>
                                <div class="btn-group ms-2">
                                    <a href="?sort_by=name&sort_order=asc<?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>" class="btn btn-sm btn-outline-secondary sortable">Name A-Z</a>
                                    <a href="?sort_by=name&sort_order=desc<?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>" class="btn btn-sm btn-outline-secondary sortable">Name Z-A</a>
                                    <a href="?sort_by=price&sort_order=desc<?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>" class="btn btn-sm btn-outline-secondary sortable">Price ↑</a>
                                    <a href="?sort_by=price&sort_order=asc<?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>" class="btn btn-sm btn-outline-secondary sortable">Price ↓</a>
                                </div>
                            </div>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>SKU</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($products) > 0): ?>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($product['image_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img">
                                                    <?php else: ?>
                                                        <div class="product-img bg-light d-flex align-items-center justify-content-center">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td>
                                                    <div class="description-truncate">
                                                        <?php echo htmlspecialchars($product['description']); ?>
                                                    </div>
                                                    <?php if (strlen($product['description']) > 100): ?>
                                                        <span class="view-details-btn" data-bs-toggle="modal" data-bs-target="#descriptionModal" 
                                                              data-description="<?php echo htmlspecialchars($product['description']); ?>"
                                                              data-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                            Show more
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                <td>
                                                    <span class="<?php echo $product['quantity'] == 0 ? 'text-danger fw-bold' : ($product['quantity'] < 10 ? 'text-warning fw-bold' : ''); ?>">
                                                        <?php echo $product['quantity']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $product['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($product['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="action-buttons">
                                                    <a href="?toggle_status=<?php echo $product['id']; ?>" class="btn btn-sm btn-<?php echo $product['status'] == 'active' ? 'warning' : 'success'; ?>" title="<?php echo $product['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $product['status'] == 'active' ? 'times' : 'check'; ?>"></i>
                                                    </a>
                                                    <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this product?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                                                <h5>No products found</h5>
                                                <p class="text-muted">Add your first product using the button above</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Card View -->
                <div class="row">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card product-card">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="card-img-top product-card-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="card-img-top product-card-img bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-image fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span class="status-badge badge bg-<?php echo $product['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></h6>
                                        <p class="card-text description-truncate">
                                            <?php echo htmlspecialchars($product['description']); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="h5 text-primary">$<?php echo number_format($product['price'], 2); ?></span>
                                            <span class="badge bg-<?php echo $product['quantity'] == 0 ? 'danger' : ($product['quantity'] < 10 ? 'warning' : 'success'); ?>">
                                                <?php echo $product['quantity']; ?> in stock
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-info"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                            <div class="action-buttons">
                                                <a href="?toggle_status=<?php echo $product['id']; ?>" class="btn btn-sm btn-<?php echo $product['status'] == 'active' ? 'warning' : 'success'; ?>" title="<?php echo $product['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $product['status'] == 'active' ? 'times' : 'check'; ?>"></i>
                                                </a>
                                                <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this product?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                                    <h5>No products found</h5>
                                    <p class="text-muted">Add your first product using the button above</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $update ? 'Edit Product' : 'Add New Product'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sku" class="form-label">SKU *</label>
                                    <input type="text" class="form-control" id="sku" name="sku" 
                                        value="<?php echo htmlspecialchars($sku); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                        value="<?php echo htmlspecialchars($name); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                        echo htmlspecialchars($description); 
                                    ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="price" class="form-label">Price ($) *</label>
                                        <input type="number" step="0.01" class="form-control" id="price" name="price" 
                                            value="<?php echo htmlspecialchars($price); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="quantity" class="form-label">Quantity *</label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" 
                                            value="<?php echo htmlspecialchars($quantity); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="product_image" class="form-label">Upload Image</label>
                                    <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                                    <div class="form-text">Max size: 5MB (JPEG, PNG, GIF, WebP)</div>
                                </div>
                                <div class="mb-3">
                                    <label for="image_url" class="form-label">Or Image URL</label>
                                    <input type="url" class="form-control" id="image_url" name="image_url" 
                                        value="<?php echo htmlspecialchars($image_url); ?>" 
                                        placeholder="https://example.com/image.jpg">
                                </div>
                                <?php if (!empty($image_url)): ?>
                                    <div class="mb-3 text-center">
                                        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Product image" class="img-thumbnail" style="max-height: 200px;">
                                        <div class="form-text">Current image</div>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-3 text-center text-muted py-4 border rounded">
                                        <i class="fas fa-image fa-3x mb-2"></i>
                                        <p>No image available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-grid">
                            <?php if ($update): ?>
                                <button type="submit" class="btn btn-warning" name="update">Update Product</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary" name="save">Add Product</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" onclick="exportData('csv')">
                            <i class="fas fa-file-csv me-2"></i>Export as CSV
                        </button>
                        <button class="btn btn-primary" onclick="exportData('json')">
                            <i class="fas fa-file-code me-2"></i>Export as JSON
                        </button>
                        <button class="btn btn-danger" onclick="exportData('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>Export as PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Description Modal -->
    <div class="modal fade" id="descriptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="descriptionModalTitle">Product Description</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="fullDescription"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Open modal if in edit mode
        <?php if ($update): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var productModal = new bootstrap.Modal(document.getElementById('productModal'));
                productModal.show();
            });
        <?php endif; ?>
        
        // Apply search and filter
        document.getElementById('applyFilters').addEventListener('click', function() {
            var search = document.getElementById('searchInput').value;
            var categoryFilter = document.getElementById('categoryFilter').value;
            var statusFilter = document.getElementById('statusFilter').value;
            var stockFilter = document.getElementById('stockFilter').value;
            
            var url = new URL(window.location.href);
            url.searchParams.set('search', search);
            url.searchParams.set('category_filter', categoryFilter);
            url.searchParams.set('status_filter', statusFilter);
            url.searchParams.set('stock_filter', stockFilter);
            
            window.location.href = url.toString();
        });
        
        // Handle description modal
        var descriptionModal = document.getElementById('descriptionModal');
        descriptionModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var description = button.getAttribute('data-description');
            var name = button.getAttribute('data-name');
            
            var modalTitle = descriptionModal.querySelector('.modal-title');
            var modalBody = descriptionModal.querySelector('.modal-body p');
            
            modalTitle.textContent = name + ' Description';
            modalBody.textContent = description;
        });
        
        // Preview image when URL changes
        document.getElementById('image_url').addEventListener('input', function() {
            var url = this.value;
            var preview = document.querySelector('.modal-body .img-thumbnail');
            
            if (preview) {
                preview.src = url;
            }
        });
        
        // Export data function
        function exportData(format) {
            alert('Export functionality would generate a ' + format.toUpperCase() + ' file in a real application.');
            // In a real application, this would redirect to an export script
            // window.location.href = 'export.php?format=' + format;
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Category distribution chart
            var ctx = document.getElementById('categoryChart').getContext('2d');
            var categoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [<?php echo implode(',', array_map(function($cat) { return "'" . addslashes($cat['name']) . "'"; }, $category_stats)); ?>],
                    datasets: [{
                        data: [<?php echo implode(',', array_map(function($cat) { return $cat['product_count']; }, $category_stats)); ?>],
                        backgroundColor: [
                            '#6a11cb', '#2575fc', '#28a745', '#dc3545', '#ffc107', '#17a2b8',
                            '#6610f2', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        });
    </script>
</body>
</html