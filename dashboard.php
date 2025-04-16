<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Check if user is logged in
requireLogin();

// Get user information
$user = getCurrentUser();

// Get user's order history (most recent first)
$orders = getRows("SELECT o.*, r.name as restaurant_name 
                  FROM orders o 
                  JOIN restaurants r ON o.restaurant_id = r.id 
                  WHERE o.user_id = {$user['id']} 
                  ORDER BY o.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FoodPanda Clone</title>
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
        .dashboard-header {
            margin-bottom: 30px;
        }
        .dashboard-header h1 {
            font-size: 24px;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        .dashboard-header p {
            color: #666;
        }
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .dashboard-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            text-align: center;
        }
        .dashboard-card i {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        .dashboard-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .dashboard-card p {
            color: #666;
        }
        .orders-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 40px;
        }
        .orders-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: var(--dark-color);
        }
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
        .no-orders {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        .no-orders i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        /* Profile Section */
        .profile-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .profile-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: var(--dark-color);
        }
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .profile-item {
            margin-bottom: 15px;
        }
        .profile-item label {
            display: block;
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .profile-item p {
            color: var(--dark-color);
            font-size: 16px;
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
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            .profile-info {
                grid-template-columns: 1fr;
            }
            .orders-table {
                font-size: 14px;
            }
            .orders-table th:nth-child(4),
            .orders-table td:nth-child(4) {
                display: none;
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
                <a href="index.php" class="nav-link">Home</a>
                <a href="#" class="nav-link">Restaurants</a>
                <a href="#" class="nav-link">Offers</a>
                <a href="dashboard.php" class="nav-link">My Account</a>
            </nav>
            <div class="auth-buttons">
                <a href="cart.php" class="btn btn-outline"><i class="fas fa-shopping-cart"></i> Cart</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome, <?= htmlspecialchars($user['name']) ?>!</h1>
                <p>Manage your orders and profile information here.</p>
            </div>
            
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <i class="fas fa-utensils"></i>
                    <h3>Order Food</h3>
                    <p>Browse restaurants and place new orders</p>
                    <a href="index.php" class="btn btn-outline" style="margin-top: 15px;">Explore Restaurants</a>
                </div>
                <div class="dashboard-card">
                    <i class="fas fa-history"></i>
                    <h3>Order History</h3>
                    <p>View details of your past orders</p>
                    <a href="order_history.php" class="btn btn-outline" style="margin-top: 15px;">View Orders</a>
                </div>
                <div class="dashboard-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Manage Address</h3>
                    <p>Update your delivery addresses</p>
                    <a href="#" class="btn btn-outline" style="margin-top: 15px;">Update Address</a>
                </div>
            </div>
            
            <div class="orders-section">
                <h2>Recent Orders</h2>
                
                <?php if (!$orders || count($orders) === 0): ?>
                    <div class="no-orders">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>No orders yet</h3>
                        <p>You haven't placed any orders yet.</p>
                        <a href="index.php" class="btn btn-primary" style="margin-top: 15px;">Order Now</a>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Order Number</th>
                                    <th>Restaurant</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['order_number']) ?></td>
                                        <td><?= htmlspecialchars($order['restaurant_name']) ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></td>
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
                                            <a href="order_tracking.php?id=<?= $order['id'] ?>" class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;">
                                                Track
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-section">
                <h2>Profile Information</h2>
                <div class="profile-info">
                    <div class="profile-item">
                        <label>Full Name</label>
                        <p><?= htmlspecialchars($user['name']) ?></p>
                    </div>
                    <div class="profile-item">
                        <label>Email Address</label>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                    <div class="profile-item">
                        <label>Phone Number</label>
                        <p><?= htmlspecialchars($user['phone']) ?></p>
                    </div>
                    <div class="profile-item">
                        <label>Address</label>
                        <p><?= htmlspecialchars($user['address']) ?></p>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <a href="#" class="btn btn-outline">Edit Profile</a>
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
</body>
</html>
