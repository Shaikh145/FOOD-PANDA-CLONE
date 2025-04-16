<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Check if user is logged in
requireLogin();

// Get user information
$user = getCurrentUser();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$dateFilter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Build query based on filters
$query = "SELECT o.*, r.name as restaurant_name 
          FROM orders o 
          JOIN restaurants r ON o.restaurant_id = r.id 
          WHERE o.user_id = {$user['id']} ";

if (!empty($status)) {
    $query .= " AND o.status = '$status' ";
}

if (!empty($dateFilter)) {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(o.created_at) = CURDATE() ";
            break;
        case 'week':
            $query .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";
            break;
        case 'month':
            $query .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ";
            break;
    }
}

$query .= " ORDER BY o.created_at DESC";

// Get orders
$orders = getRows($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - FoodPanda Clone</title>
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
        
        /* Filter Bar */
        .filter-bar {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
        }
        .filter-group {
            display: flex;
            align-items: center;
        }
        .filter-group label {
            margin-right: 10px;
            font-weight: 500;
            color: var(--dark-color);
        }
        .filter-select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 14px;
            min-width: 150px;
        }
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .filter-actions {
            margin-left: auto;
        }
        
        /* Orders Table */
        .orders-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 40px;
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
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .track-btn {
            background-color: var(--light-color);
            color: var(--primary-color);
        }
        .track-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }
        .reorder-btn {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .reorder-btn:hover {
            background-color: #388e3c;
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 0;
        }
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        .pagination-link {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            background-color: white;
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .pagination-link:hover {
            background-color: var(--light-color);
            color: var(--primary-color);
        }
        .pagination-link.active {
            background-color: var(--primary-color);
            color: white;
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
            .filter-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-actions {
                margin-left: 0;
                width: 100%;
            }
            .orders-table {
                font-size: 14px;
            }
            .orders-table th:nth-child(3),
            .orders-table td:nth-child(3) {
                display: none;
            }
            .action-buttons {
                flex-direction: column;
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
                <a href="dashboard.php" class="btn btn-outline">My Account</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="page-title">
                <h1>Order History</h1>
                <p>View and track all your past orders</p>
            </div>
            
            <div class="filter-bar">
                <form action="order_history.php" method="GET" id="filter-form">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" class="filter-select" onchange="document.getElementById('filter-form').submit()">
                            <option value="">All Orders</option>
                            <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="preparing" <?= $status == 'preparing' ? 'selected' : '' ?>>Preparing</option>
                            <option value="out_for_delivery" <?= $status == 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                            <option value="delivered" <?= $status == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="margin-left: 20px;">
                        <label for="date">Date:</label>
                        <select name="date" id="date" class="filter-select" onchange="document.getElementById('filter-form').submit()">
                            <option value="">All Time</option>
                            <option value="today" <?= $dateFilter == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= $dateFilter == 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="month" <?= $dateFilter == 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <a href="order_history.php" class="btn btn-outline">Clear Filters</a>
                    </div>
                </form>
            </div>
            
            <div class="orders-container">
                <?php if (!$orders || count($orders) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No orders found</h3>
                        <p>You haven't placed any orders matching your filters.</p>
                        <a href="index.php" class="btn btn-primary">Browse Restaurants</a>
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
                                    <th>Actions</th>
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
                                            <div class="action-buttons">
                                                <a href="order_tracking.php?id=<?= $order['id'] ?>" class="action-btn track-btn">
                                                    <i class="fas fa-truck"></i> Track
                                                </a>
                                                <?php if ($order['status'] == 'delivered'): ?>
                                                    <a href="restaurant_details.php?id=<?= $order['restaurant_id'] ?>" class="action-btn reorder-btn">
                                                        <i class="fas fa-redo"></i> Reorder
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Simple pagination - would be dynamic in a real application -->
                    <div class="pagination">
                        <a href="#" class="pagination-link active">1</a>
                        <a href="#" class="pagination-link">2</a>
                        <a href="#" class="pagination-link">3</a>
                        <a href="#" class="pagination-link">Next &raquo;</a>
                    </div>
                <?php endif; ?>
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
