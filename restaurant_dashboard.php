<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Check if user is a restaurant owner
requireRestaurantOwner();

// Get owner information
$user = getCurrentUser();
$restaurant = getOwnerRestaurant();

// If restaurant is not registered yet, show registration form
if (!$restaurant) {
    // Handle restaurant registration form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
        $address = isset($_POST['address']) ? sanitize($_POST['address']) : '';
        $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
        $cuisineType = isset($_POST['cuisine_type']) ? sanitize($_POST['cuisine_type']) : '';
        $openingHours = isset($_POST['opening_hours']) ? sanitize($_POST['opening_hours']) : '';
        $deliveryTime = isset($_POST['delivery_time']) ? sanitize($_POST['delivery_time']) : '30-45 min';
        $minimumOrder = isset($_POST['minimum_order']) ? (float)$_POST['minimum_order'] : 0.00;
        $deliveryFee = isset($_POST['delivery_fee']) ? (float)$_POST['delivery_fee'] : 0.00;
        
        $result = registerRestaurant(
            $user['id'],
            $name,
            $description,
            $address,
            $phone,
            $cuisineType,
            $openingHours
        );
        
        if ($result['status']) {
            // Update additional restaurant details
            $restaurantId = $result['restaurant_id'];
            updateData('restaurants', [
                'delivery_time' => $deliveryTime,
                'minimum_order' => $minimumOrder,
                'delivery_fee' => $deliveryFee
            ], "id = $restaurantId");
            
            // Redirect to refresh the page
            header("Location: restaurant_dashboard.php");
            exit;
        } else {
            $registrationError = $result['message'];
        }
    }
} else {
    // Get restaurant stats
    $activeOrdersCount = getRow("SELECT COUNT(*) as count FROM orders WHERE restaurant_id = {$restaurant['id']} AND status != 'delivered' AND status != 'cancelled'")['count'];
    $totalOrdersCount = getRow("SELECT COUNT(*) as count FROM orders WHERE restaurant_id = {$restaurant['id']}")['count'];
    $menuItemsCount = getRow("SELECT COUNT(*) as count FROM menu_items WHERE restaurant_id = {$restaurant['id']}")['count'];
    $totalRevenue = getRow("SELECT SUM(total_amount) as total FROM orders WHERE restaurant_id = {$restaurant['id']} AND status = 'delivered'")['total'] ?? 0;
    
    // Get recent orders
    $recentOrders = getRows("SELECT o.*, u.name as customer_name 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.id 
                            WHERE o.restaurant_id = {$restaurant['id']} 
                            ORDER BY o.created_at DESC 
                            LIMIT 5");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Dashboard - FoodPanda Clone</title>
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
        
        /* Main Content */
        main {
            flex: 1;
            padding: 40px 0;
        }
        
        /* Restaurant Registration Form */
        .registration-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 30px;
        }
        .registration-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .registration-header h1 {
            font-size: 24px;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        .registration-header p {
            color: #666;
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
        .error-message {
            color: #d32f2f;
            margin-bottom: 15px;
        }
        
        /* Dashboard Overview */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .dashboard-title h1 {
            font-size: 24px;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        .dashboard-title p {
            color: #666;
        }
        .dashboard-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Stat Cards */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            text-align: center;
        }
        .stat-card i {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        /* Dashboard Sections */
        .dashboard-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .section-title {
            font-size: 18px;
            color: var(--dark-color);
            font-weight: 500;
        }
        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
        }
        .view-all:hover {
            text-decoration: underline;
        }
        
        /* Orders Table */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        .orders-table th,
        .orders-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .orders-table th {
            background-color: var(--light-color);
            color: var(--primary-color);
            font-weight: 500;
        }
        .orders-table tr:hover {
            background-color: #f9f9f9;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        .status-confirmed {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .status-preparing {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .status-out-for-delivery {
            background-color: #ede7f6;
            color: #5e35b1;
        }
        .status-delivered {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .status-cancelled {
            background-color: #ffebee;
            color: #d32f2f;
        }
        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            background-color: var(--light-color);
            color: var(--primary-color);
        }
        .action-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Restaurant Info */
        .restaurant-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .info-item {
            margin-bottom: 15px;
        }
        .info-item label {
            display: block;
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .info-item p {
            color: var(--dark-color);
            font-size: 16px;
        }
        
        /* No Orders */
        .no-orders {
            text-align: center;
            padding: 30px;
        }
        .no-orders i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        .no-orders h3 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        .no-orders p {
            color: #666;
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
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .dashboard-actions {
                width: 100%;
            }
            .orders-table {
                font-size: 14px;
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
                <?php if ($restaurant): ?>
                    <a href="restaurant_dashboard.php" class="nav-link">Dashboard</a>
                    <a href="manage_orders.php" class="nav-link">Orders</a>
                    <a href="add_menu_item.php" class="nav-link">Menu</a>
                <?php endif; ?>
            </nav>
            <div class="auth-buttons">
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <?php if (!$restaurant): ?>
                <!-- Restaurant Registration Form -->
                <div class="registration-container">
                    <div class="registration-header">
                        <h1>Register Your Restaurant</h1>
                        <p>Join our platform and reach more customers</p>
                    </div>
                    
                    <?php if (isset($registrationError)): ?>
                        <div class="error-message"><?= htmlspecialchars($registrationError) ?></div>
                    <?php endif; ?>
                    
                    <form action="restaurant_dashboard.php" method="POST">
                        <div class="form-group">
                            <label for="name">Restaurant Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" placeholder="Tell customers about your restaurant, cuisine, specialties, etc." required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="2" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="cuisine_type">Cuisine Type</label>
                                <select id="cuisine_type" name="cuisine_type" class="form-control" required>
                                    <option value="">Select Cuisine</option>
                                    <option value="Italian">Italian</option>
                                    <option value="Chinese">Chinese</option>
                                    <option value="Japanese">Japanese</option>
                                    <option value="Mexican">Mexican</option>
                                    <option value="Indian">Indian</option>
                                    <option value="Thai">Thai</option>
                                    <option value="American">American</option>
                                    <option value="Fast Food">Fast Food</option>
                                    <option value="Desserts">Desserts</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="opening_hours">Opening Hours</label>
                            <input type="text" id="opening_hours" name="opening_hours" class="form-control" placeholder="e.g., Mon-Fri: 9AM-10PM, Sat-Sun: 10AM-11PM" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="delivery_time">Average Delivery Time</label>
                                <select id="delivery_time" name="delivery_time" class="form-control">
                                    <option value="15-30 min">15-30 minutes</option>
                                    <option value="30-45 min" selected>30-45 minutes</option>
                                    <option value="45-60 min">45-60 minutes</option>
                                    <option value="60-90 min">60-90 minutes</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="minimum_order">Minimum Order Amount ($)</label>
                                <input type="number" id="minimum_order" name="minimum_order" class="form-control" min="0" step="0.01" value="0.00">
                            </div>
                            
                            <div class="form-group">
                                <label for="delivery_fee">Delivery Fee ($)</label>
                                <input type="number" id="delivery_fee" name="delivery_fee" class="form-control" min="0" step="0.01" value="0.00">
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">Register Restaurant</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Restaurant Dashboard -->
                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h1><?= htmlspecialchars($restaurant['name']) ?></h1>
                        <p>Welcome back to your restaurant dashboard</p>
                    </div>
                    <div class="dashboard-actions">
                        <a href="add_menu_item.php" class="btn btn-primary">Add Menu Item</a>
                        <a href="manage_orders.php" class="btn btn-outline">Manage Orders</a>
                    </div>
                </div>
                
                <!-- Stats Overview -->
                <div class="stat-cards">
                    <div class="stat-card">
                        <i class="fas fa-utensils"></i>
                        <div class="stat-value"><?= $activeOrdersCount ?></div>
                        <div class="stat-label">Active Orders</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-shopping-bag"></i>
                        <div class="stat-value"><?= $totalOrdersCount ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-list"></i>
                        <div class="stat-value"><?= $menuItemsCount ?></div>
                        <div class="stat-label">Menu Items</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-dollar-sign"></i>
                        <div class="stat-value">$<?= number_format($totalRevenue, 2) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Recent Orders</h3>
                        <a href="manage_orders.php" class="view-all">View All Orders</a>
                    </div>
                    
                    <?php if (empty($recentOrders)): ?>
                        <div class="no-orders">
                            <i class="fas fa-receipt"></i>
                            <h3>No orders yet</h3>
                            <p>When customers place orders, they will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Time</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['order_number']) ?></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td><?= date('M d, h:i A', strtotime($order['created_at'])) ?></td>
                                            <td>$<?= number_format($order['grand_total'], 2) ?></td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                switch ($order['status']) {
                                                    case 'pending':
                                                        $statusClass = 'status-pending';
                                                        break;
                                                    case 'confirmed':
                                                        $statusClass = 'status-confirmed';
                                                        break;
                                                    case 'preparing':
                                                        $statusClass = 'status-preparing';
                                                        break;
                                                    case 'out_for_delivery':
                                                        $statusClass = 'status-out-for-delivery';
                                                        break;
                                                    case 'delivered':
                                                        $statusClass = 'status-delivered';
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'status-cancelled';
                                                        break;
                                                }
                                                ?>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="manage_orders.php?view=<?= $order['id'] ?>" class="action-btn">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Restaurant Information -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Restaurant Information</h3>
                        <a href="#" class="view-all">Edit</a>
                    </div>
                    
                    <div class="restaurant-info">
                        <div class="info-item">
                            <label>Restaurant Name</label>
                            <p><?= htmlspecialchars($restaurant['name']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Cuisine Type</label>
                            <p><?= htmlspecialchars($restaurant['cuisine_type']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Address</label>
                            <p><?= htmlspecialchars($restaurant['address']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Phone</label>
                            <p><?= htmlspecialchars($restaurant['phone']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Opening Hours</label>
                            <p><?= htmlspecialchars($restaurant['opening_hours']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Delivery Time</label>
                            <p><?= htmlspecialchars($restaurant['delivery_time']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Minimum Order</label>
                            <p>$<?= number_format($restaurant['minimum_order'], 2) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Delivery Fee</label>
                            <p>$<?= number_format($restaurant['delivery_fee'], 2) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Status</label>
                            <p><?= ucfirst($restaurant['status']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Rating</label>
                            <p><?= number_format($restaurant['rating'], 1) ?> <i class="fas fa-star" style="color: #ffb100;"></i></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> FoodPanda Clone. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
