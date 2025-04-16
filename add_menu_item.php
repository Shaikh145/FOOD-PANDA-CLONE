<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Check if user is a restaurant owner
requireRestaurantOwner();

// Get owner information
$user = getCurrentUser();
$restaurant = getOwnerRestaurant();

// If restaurant is not registered yet, redirect to dashboard
if (!$restaurant) {
    header("Location: restaurant_dashboard.php");
    exit;
}

// Initialize error and success messages
$errors = [];
$success = '';

// Get all menu categories for this restaurant
$categories = getRows("SELECT * FROM menu_categories WHERE restaurant_id = {$restaurant['id']} ORDER BY display_order ASC");

// Handle category form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = isset($_POST['category_name']) ? sanitize($_POST['category_name']) : '';
    $categoryDescription = isset($_POST['category_description']) ? sanitize($_POST['category_description']) : '';
    $displayOrder = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    
    if (empty($categoryName)) {
        $errors[] = "Category name is required";
    } else {
        // Insert new category
        $categoryData = [
            'restaurant_id' => $restaurant['id'],
            'name' => $categoryName,
            'description' => $categoryDescription,
            'display_order' => $displayOrder,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $categoryId = insertData('menu_categories', $categoryData);
        
        if ($categoryId) {
            $success = "Category added successfully!";
            // Refresh categories list
            $categories = getRows("SELECT * FROM menu_categories WHERE restaurant_id = {$restaurant['id']} ORDER BY display_order ASC");
        } else {
            $errors[] = "Failed to add category";
        }
    }
}

// Handle menu item form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $itemName = isset($_POST['item_name']) ? sanitize($_POST['item_name']) : '';
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $itemDescription = isset($_POST['item_description']) ? sanitize($_POST['item_description']) : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $discountPrice = isset($_POST['discount_price']) && !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $isVegetarian = isset($_POST['is_vegetarian']) ? 1 : 0;
    $isSpicy = isset($_POST['is_spicy']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validate input
    if (empty($itemName)) {
        $errors[] = "Item name is required";
    }
    if ($categoryId <= 0 || !in_array($categoryId, array_column($categories, 'id'))) {
        $errors[] = "Valid category is required";
    }
    if ($price <= 0) {
        $errors[] = "Price must be greater than zero";
    }
    
    if (empty($errors)) {
        // Insert new menu item
        $itemData = [
            'restaurant_id' => $restaurant['id'],
            'category_id' => $categoryId,
            'name' => $itemName,
            'description' => $itemDescription,
            'price' => $price,
            'discount_price' => $discountPrice,
            'is_vegetarian' => $isVegetarian,
            'is_spicy' => $isSpicy,
            'is_featured' => $isFeatured,
            'status' => 'available',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $itemId = insertData('menu_items', $itemData);
        
        if ($itemId) {
            $success = "Menu item added successfully!";
        } else {
            $errors[] = "Failed to add menu item";
        }
    }
}

// Get all menu items for this restaurant
$menuItems = getRows("SELECT mi.*, mc.name as category_name 
                     FROM menu_items mi 
                     JOIN menu_categories mc ON mi.category_id = mc.id 
                     WHERE mi.restaurant_id = {$restaurant['id']} 
                     ORDER BY mc.display_order, mi.name ASC");

// Group menu items by category for display
$menuByCategory = [];
foreach ($menuItems as $item) {
    $categoryId = $item['category_id'];
    if (!isset($menuByCategory[$categoryId])) {
        $menuByCategory[$categoryId] = [
            'name' => $item['category_name'],
            'items' => []
        ];
    }
    $menuByCategory[$categoryId]['items'][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Menu - FoodPanda Clone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        :root {
            --primary-color: #d70f64;
            --secondary-color: #ff8fb2;
            --light-color: #fff0f5;
            --dark-color: #333;
            --gray-color: #f5f5f5;
            --shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        body {
            background-color: var(--gray-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }
        .navigation {
            display: flex;
            gap: 20px;
        }
        .nav-link {
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: var(--primary-color);
        }
        .nav-link.active {
            color: var(--primary-color);
        }
        .auth-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            border: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #b30d55;
        }
        .btn-outline {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background-color: transparent;
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        
        /* Main Content */
        main {
            flex: 1;
            padding: 40px 0;
        }
        .page-title {
            margin-bottom: 30px;
        }
        .page-title h1 {
            font-size: 24px;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        .page-title p {
            color: #666;
        }
        
        /* Tabs */
        .tab-container {
            margin-bottom: 30px;
        }
        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-link {
            padding: 10px 20px;
            background-color: white;
            border-radius: 4px;
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        .tab-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        .tab-link:hover:not(.active) {
            background-color: var(--light-color);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Form Styles */
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        .form-title {
            font-size: 18px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }
        .checkbox-group input {
            margin-right: 8px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background-color: #e8f5e9;
            color: #388e3c;
            border-left: 4px solid #4caf50;
        }
        .alert-danger {
            background-color: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #f44336;
        }
        .alert ul {
            margin: 5px 0 0 20px;
        }
        
        /* Menu Items Table */
        .menu-items-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        .category-header {
            font-size: 18px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .menu-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .menu-items-table th,
        .menu-items-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .menu-items-table th {
            background-color: var(--light-color);
            color: var(--primary-color);
            font-weight: 500;
        }
        .menu-items-table tr:hover {
            background-color: #f9f9f9;
        }
        .price-column {
            text-align: right;
        }
        .discount-price {
            color: var(--primary-color);
            font-weight: 500;
        }
        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 12px;
            margin-left: 5px;
        }
        .item-badges {
            display: flex;
            gap: 5px;
        }
        .item-badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .badge-vegetarian {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .badge-spicy {
            background-color: #ffebee;
            color: #d32f2f;
        }
        .badge-featured {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        .badge-unavailable {
            background-color: #eeeeee;
            color: #9e9e9e;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .edit-btn {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .edit-btn:hover {
            background-color: #1976d2;
            color: white;
        }
        .delete-btn {
            background-color: #ffebee;
            color: #d32f2f;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px;
        }
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 15px 0;
            text-align: center;
            font-size: 14px;
            margin-top: auto;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .navigation {
                display: none;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .menu-items-table th:nth-child(3),
            .menu-items-table td:nth-child(3) {
                display: none;
            }
            .tab-nav {
                flex-wrap: wrap;
            }
            .tab-link {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">FoodPanda</a>
            <nav class="navigation">
                <a href="restaurant_dashboard.php" class="nav-link">Dashboard</a>
                <a href="manage_orders.php" class="nav-link">Orders</a>
                <a href="add_menu_item.php" class="nav-link active">Menu</a>
            </nav>
            <div class="auth-buttons">
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="page-title">
                <h1>Manage Menu</h1>
                <p>Add and manage menu categories and items for <?= htmlspecialchars($restaurant['name']) ?></p>
            </div>
            
            <!-- Success and Error Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tab-container">
                <div class="tab-nav">
                    <a href="#" class="tab-link active" data-tab="add-item">Add Menu Item</a>
                    <a href="#" class="tab-link" data-tab="add-category">Add Category</a>
                    <a href="#" class="tab-link" data-tab="view-menu">View Menu</a>
                </div>
                
                <!-- Add Menu Item Tab -->
                <div class="tab-content active" id="add-item">
                    <div class="form-container">
                        <h3 class="form-title">Add New Menu Item</h3>
                        
                        <?php if (empty($categories)): ?>
                            <div class="alert alert-danger">
                                <strong>No categories found!</strong> Please add a category first before adding menu items.
                            </div>
                        <?php else: ?>
                            <form action="add_menu_item.php" method="POST">
                                <div class="form-group">
                                    <label for="item_name">Item Name</label>
                                    <input type="text" id="item_name" name="item_name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category_id">Category</label>
                                    <select id="category_id" name="category_id" class="form-control" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="item_description">Description</label>
                                    <textarea id="item_description" name="item_description" class="form-control" placeholder="Describe the item (ingredients, taste, etc.)"></textarea>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="price">Price ($)</label>
                                        <input type="number" id="price" name="price" class="form-control" min="0.01" step="0.01" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="discount_price">Discount Price ($) (Optional)</label>
                                        <input type="number" id="discount_price" name="discount_price" class="form-control" min="0.01" step="0.01">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_vegetarian" name="is_vegetarian">
                                        <label for="is_vegetarian">Vegetarian</label>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_spicy" name="is_spicy">
                                        <label for="is_spicy">Spicy</label>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_featured" name="is_featured">
                                        <label for="is_featured">Featured Item</label>
                                    </div>
                                </div>
                                
                                <div style="text-align: center; margin-top: 20px;">
                                    <input type="hidden" name="add_item" value="1">
                                    <button type="submit" class="btn btn-primary" style="padding: 10px 30px;">Add Item</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Add Category Tab -->
                <div class="tab-content" id="add-category">
                    <div class="form-container">
                        <h3 class="form-title">Add New Menu Category</h3>
                        <form action="add_menu_item.php" method="POST">
                            <div class="form-group">
                                <label for="category_name">Category Name</label>
                                <input type="text" id="category_name" name="category_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category_description">Description (Optional)</label>
                                <textarea id="category_description" name="category_description" class="form-control" placeholder="Brief description of the category"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="display_order">Display Order</label>
                                <input type="number" id="display_order" name="display_order" class="form-control" min="0" value="0">
                                <small style="color: #666; margin-top: 5px; display: block;">Lower numbers appear first</small>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <input type="hidden" name="add_category" value="1">
                                <button type="submit" class="btn btn-primary" style="padding: 10px 30px;">Add Category</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="menu-items-container">
                        <h3 class="form-title">Existing Categories</h3>
                        
                        <?php if (empty($categories)): ?>
                            <div class="empty-state">
                                <i class="fas fa-list"></i>
                                <h3>No categories yet</h3>
                                <p>Add your first menu category using the form above.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="menu-items-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Display Order</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($category['name']) ?></td>
                                                <td><?= htmlspecialchars($category['description'] ?: 'N/A') ?></td>
                                                <td><?= $category['display_order'] ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="#" class="action-btn edit-btn">Edit</a>
                                                        <a href="#" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this category? All menu items in this category will also be deleted.')">Delete</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- View Menu Tab -->
                <div class="tab-content" id="view-menu">
                    <?php if (empty($menuItems)): ?>
                        <div class="menu-items-container">
                            <div class="empty-state">
                                <i class="fas fa-utensils"></i>
                                <h3>No menu items yet</h3>
                                <p>Add your first menu item to get started.</p>
                                <button class="btn btn-primary tab-trigger" data-tab="add-item">Add Menu Item</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($menuByCategory as $categoryId => $category): ?>
                            <div class="menu-items-container">
                                <h3 class="category-header"><?= htmlspecialchars($category['name']) ?></h3>
                                
                                <div style="overflow-x: auto;">
                                    <table class="menu-items-table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($category['items'] as $item): ?>
                                                <tr>
                                                    <td>
                                                        <?= htmlspecialchars($item['name']) ?>
                                                        <div class="item-badges">
                                                            <?php if ($item['is_vegetarian']): ?>
                                                                <span class="item-badge badge-vegetarian">Veg</span>
                                                            <?php endif; ?>
                                                            <?php if ($item['is_spicy']): ?>
                                                                <span class="item-badge badge-spicy">Spicy</span>
                                                            <?php endif; ?>
                                                            <?php if ($item['is_featured']): ?>
                                                                <span class="item-badge badge-featured">Featured</span>
                                                            <?php endif; ?>
                                                            <?php if ($item['status'] == 'unavailable'): ?>
                                                                <span class="item-badge badge-unavailable">Unavailable</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($item['description'] ?: 'N/A') ?></td>
                                                    <td class="price-column">
                                                        <?php if ($item['discount_price']): ?>
                                                            <span class="discount-price">$<?= number_format($item['discount_price'], 2) ?></span>
                                                            <span class="original-price">$<?= number_format($item['price'], 2) ?></span>
                                                        <?php else: ?>
                                                            $<?= number_format($item['price'], 2) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($item['status'] == 'available'): ?>
                                                            <span style="color: #388e3c;">Available</span>
                                                        <?php else: ?>
                                                            <span style="color: #d32f2f;">Unavailable</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="#" class="action-btn edit-btn">Edit</a>
                                                            <a href="#" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> FoodPanda Clone. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            const tabTriggers = document.querySelectorAll('.tab-trigger');
            
            function activateTab(tabId) {
                // Hide all tab contents
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                // Deactivate all tab links
                tabLinks.forEach(link => {
                    link.classList.remove('active');
                });
                
                // Activate selected tab content
                document.getElementById(tabId).classList.add('active');
                
                // Activate corresponding tab link
                document.querySelector(`.tab-link[data-tab="${tabId}"]`).classList.add('active');
            }
            
            // Tab link click event<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Check if user is a restaurant owner
requireRestaurantOwner();

// Get owner and restaurant information
$user = getCurrentUser();
$restaurant = getOwnerRestaurant();

// If restaurant is not registered yet, redirect to restaurant dashboard
if (!$restaurant) {
    header("Location: restaurant_dashboard.php");
    exit;
}

// Initialize variables
$errors = [];
$success = false;
$itemId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$item = null;
$formAction = "add_menu_item.php";

// If editing, get the item details
if ($itemId) {
    $item = getRow("SELECT * FROM menu_items WHERE id = $itemId AND restaurant_id = {$restaurant['id']}");
    if (!$item) {
        header("Location: add_menu_item.php");
        exit;
    }
    $formAction = "add_menu_item.php?edit=$itemId";
}

// Get all menu categories for this restaurant
$categories = getRows("SELECT * FROM menu_categories WHERE restaurant_id = {$restaurant['id']} ORDER BY display_order ASC");

// If no categories exist, create default ones
if (!$categories) {
    $defaultCategories = [
        ['name' => 'Appetizers', 'description' => 'Start your meal with these delicious starters', 'display_order' => 1],
        ['name' => 'Main Course', 'description' => 'Hearty and filling main dishes', 'display_order' => 2],
        ['name' => 'Desserts', 'description' => 'Sweet treats to finish your meal', 'display_order' => 3],
        ['name' => 'Beverages', 'description' => 'Refreshing drinks', 'display_order' => 4]
    ];
    
    foreach ($defaultCategories as $category) {
        $categoryData = [
            'restaurant_id' => $restaurant['id'],
            'name' => $category['name'],
            'description' => $category['description'],
            'display_order' => $category['display_order']
        ];
        insertData('menu_categories', $categoryData);
    }
    
    // Fetch the newly created categories
    $categories = getRows("SELECT * FROM menu_categories WHERE restaurant_id = {$restaurant['id']} ORDER BY display_order ASC");
}

// Handle form submission for adding/editing menu item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $discountPrice = isset($_POST['discount_price']) && !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $isVegetarian = isset($_POST['is_vegetarian']) ? 1 : 0;
    $isSpicy = isset($_POST['is_spicy']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $status = isset($_POST['status']) ? sanitize($_POST['status']) : 'available';
    
    // Validate input
    if (empty($name)) {
        $errors[] = "Item name is required";
    }
    
    if ($categoryId === 0) {
        $errors[] = "Category is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than zero";
    }
    
    if ($discountPrice !== null && $discountPrice >= $price) {
        $errors[] = "Discount price must be less than regular price";
    }
    
    // If no errors, add or update the menu item
    if (empty($errors)) {
        $menuItemData = [
            'restaurant_id' => $restaurant['id'],
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'discount_price' => $discountPrice,
            'is_vegetarian' => $isVegetarian,
            'is_spicy' => $isSpicy,
            'is_featured' => $isFeatured,
            'status' => $status
        ];
        
        if ($itemId) {
            // Update existing item
            $result = updateData('menu_items', $menuItemData, "id = $itemId");
            if ($result) {
                $success = "Menu item updated successfully";
            } else {
                $errors[] = "Failed to update menu item";
            }
        } else {
            // Add new item
            $result = insertData('menu_items', $menuItemData);
            if ($result) {
                $success = "Menu item added successfully";
                // Clear form data
                $item = null;
            } else {
                $errors[] = "Failed to add menu item";
            }
        }
    }
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $deleteItem = getRow("SELECT * FROM menu_items WHERE id = $deleteId AND restaurant_id = {$restaurant['id']}");
    
    if ($deleteItem) {
        $result = deleteData('menu_items', "id = $deleteId");
        if ($result) {
            $success = "Menu item deleted successfully";
        } else {
            $errors[] = "Failed to delete menu item";
        }
    }
}

// Handle adding a new category
if (isset($_POST['add_category'])) {
    $categoryName = isset($_POST['category_name']) ? sanitize($_POST['category_name']) : '';
    $categoryDescription = isset($_POST['category_description']) ? sanitize($_POST['category_description']) : '';
    
    if (!empty($categoryName)) {
        $maxOrder = getRow("SELECT MAX(display_order) as max_order FROM menu_categories WHERE restaurant_id = {$restaurant['id']}")['max_order'] ?? 0;
        
        $categoryData = [
            'restaurant_id' => $restaurant['id'],
            'name' => $categoryName,
            'description' => $categoryDescription,
            'display_order' => $maxOrder + 1
        ];
        
        $result = insertData('menu_categories', $categoryData);
        if ($result) {
            $success = "Category added successfully";
            // Refresh categories
            $categories = getRows("SELECT * FROM menu_categories WHERE restaurant_id = {$restaurant['id']} ORDER BY display_order ASC");
        } else {
            $errors[] = "Failed to add category";
        }
    } else {
        $errors[] = "Category name is required";
    }
}

// Get all menu items for this restaurant
$menuItems = getRows("SELECT i.*, c.name as category_name 
                     FROM menu_items i 
                     JOIN menu_categories c ON i.category_id = c.id 
                     WHERE i.restaurant_id = {$restaurant['id']} 
                     ORDER BY c.display_order ASC, i.name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Menu - FoodPanda Clone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        :root {
            --primary-color: #d70f64;
            --secondary-color: #ff8fb2;
            --light-color: #fff0f5;
            --dark-color: #333;
            --gray-color: #f5f5f5;
            --shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        body {
            background-color: var(--gray-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }
        .navigation {
            display: flex;
            gap: 20px;
        }
        .nav-link {
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: var(--primary-color);
        }
        .nav-link.active {
            color: var(--primary-color);
            position: relative;
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -16px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary-color);
        }
        .auth-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            border: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #b30d55;
        }
        .btn-outline {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background-color: transparent;
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #bd2130;
        }
        
        /* Main Content */
        main {
            flex: 1;
            padding: 40px 0;
        }
        
        /* Page Title */
        .page-title {
            margin-bottom: 30px;
        }
        .page-title h1 {
            font-size: 24px;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        .page-title p {
            color: #666;
        }
        
        /* Menu Management */
        .menu-container {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        .menu-form {
            flex: 1;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .menu-list {
            flex: 1;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        
        /* Form Styles */
        .form-title {
            font-size: 18px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        .checkbox-option {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .checkbox-option input {
            margin-right: 8px;
        }
        
        /* Menu Items List */
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th,
        .items-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .items-table th {
            background-color: var(--light-color);
            color: var(--primary-color);
            font-weight: 500;
        }
        .items-table tr:hover {
            background-color: #f9f9f9;
        }
        .item-actions {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .edit-btn {
            background-color: var(--light-color);
            color: var(--primary-color);
        }
        .edit-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }
        .delete-btn {
            background-color: #ffebee;
            color: #d32f2f;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
            color: white;
        }
        
        /* Item Tags */
        .item-tag {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            margin-right: 5px;
        }
        .tag-vegetarian {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .tag-spicy {
            background-color: #ffebee;
            color: #d32f2f;
        }
        .tag-featured {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-available {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .status-unavailable {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        /* Add Category Form */
        .category-form {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        /* Messages */
        .success-message {
            background-color: #e8f5e9;
            color: #388e3c;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .success-message i {
            margin-right: 10px;
        }
        .error-message {
            background-color: #ffebee;
            color: #d32f2f;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .error-message i {
            margin-right: 10px;
        }
        .error-list {
            list-style-type: none;
            padding-left: 10px;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .navigation {
                display: none;
            }
            .menu-container {
                flex-direction: column;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .checkbox-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">FoodPanda</a>
            <nav class="navigation">
                <a href="restaurant_dashboard.php" class="nav-link">Dashboard</a>
                <a href="manage_orders.php" class="nav-link">Orders</a>
                <a href="add_menu_item.php" class="nav-link active">Menu</a>
            </nav>
            <div class="auth-buttons">
                <a href="restaurant_dashboard.php" class="btn btn-outline">Dashboard</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="page-title">
                <h1>Manage Menu</h1>
                <p>Add, edit and manage your restaurant's menu items</p>
            </div>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Add Category Form -->
            <div class="category-form">
                <h3 class="form-title">Add New Category</h3>
                <form action="add_menu_item.php" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_name">Category Name</label>
                            <input type="text" id="category_name" name="category_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category_description">Description (Optional)</label>
                            <input type="text" id="category_description" name="category_description" class="form-control">
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="menu-container">
                <!-- Add/Edit Menu Item Form -->
                <div class="menu-form">
                    <h3 class="form-title"><?= $itemId ? 'Edit' : 'Add New' ?> Menu Item</h3>
                    <form action="<?= $formAction ?>" method="POST">
                        <div class="form-group">
                            <label for="name">Item Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?= $item ? htmlspecialchars($item['name']) : '' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= ($item && $item['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control"><?= $item ? htmlspecialchars($item['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="price">Price ($)</label>
                                <input type="number" id="price" name="price" class="form-control" min="0.01" step="0.01" value="<?= $item ? number_format($item['price'], 2) : '' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount_price">Discount Price ($) (Optional)</label>
                                <input type="number" id="discount_price" name="discount_price" class="form-control" min="0.01" step="0.01" value="<?= ($item && $item['discount_price']) ? number_format($item['discount_price'], 2) : '' ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Item Properties</label>
                            <div class="checkbox-group">
                                <label class="checkbox-option">
                                    <input type="checkbox" name="is_vegetarian" value="1" <?= ($item && $item['is_vegetarian']) ? 'checked' : '' ?>>
                                    Vegetarian
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="is_spicy" value="1" <?= ($item && $item['is_spicy']) ? 'checked' : '' ?>>
                                    Spicy
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="is_featured" value="1" <?= ($item && $item['is_featured']) ? 'checked' : '' ?>>
                                    Featured Item
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Availability</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="available" <?= ($item && $item['status'] == 'available') ? 'selected' : '' ?>>Available</option>
                                <option value="unavailable" <?= ($item && $item['status'] == 'unavailable') ? 'selected' : '' ?>>Unavailable</option>
                            </select>
                        </div>
                        
                        <div style="text-align: center; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 40px;"><?= $itemId ? 'Update' : 'Add' ?> Item</button>
                            <?php if ($itemId): ?>
                                <a href="add_menu_item.php" class="btn btn-outline" style="margin-left: 10px;">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Menu Items List -->
                <div class="menu-list">
                    <h3 class="form-title">Current Menu Items</h3>
                    
                    <?php if (empty($menuItems)): ?>
                        <div style="text-align: center; padding: 30px 0;">
                            <i class="fas fa-utensils" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                            <h3 style="margin-bottom: 10px; color: var(--dark-color);">No menu items yet</h3>
                            <p style="color: #666;">Add your first menu item using the form.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($menuItems as $menuItem): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($menuItem['name']) ?>
                                                <div style="margin-top: 5px;">
                                                    <?php if ($menuItem['is_vegetarian']): ?>
                                                        <span class="item-tag tag-vegetarian">Veg</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($menuItem['is_spicy']): ?>
                                                        <span class="item-tag tag-spicy">Spicy</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($menuItem['is_featured']): ?>
                                                        <span class="item-tag tag-featured">Featured</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($menuItem['category_name']) ?></td>
                                            <td>
                                                <?php if ($menuItem['discount_price']): ?>
                                                    <span style="text-decoration: line-through; color: #999;">$<?= number_format($menuItem['price'], 2) ?></span>
                                                    <span style="color: var(--primary-color); font-weight: 500;">$<?= number_format($menuItem['discount_price'], 2) ?></span>
                                                <?php else: ?>
                                                    $<?= number_format($menuItem['price'], 2) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $menuItem['status'] == 'available' ? 'status-available' : 'status-unavailable' ?>">
                                                    <?= ucfirst($menuItem['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="item-actions">
                                                    <a href="add_menu_item.php?edit=<?= $menuItem['id'] ?>" class="action-btn edit-btn">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="add_menu_item.php?delete=<?= $menuItem['id'] ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this item?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="background-color: var(--dark-color); color: white; padding: 15px 0; text-align: center; font-size: 14px; margin-top: auto;">
        <div class="container">
            <p>&copy; <?= date('Y') ?> FoodPanda Clone. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
