<?php
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

// Get filter parameters
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$dateFilter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Build query based on filters
$query = "SELECT o.*, u.name as customer_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.restaurant_id = {$restaurant['id']} ";

if (!empty($statusFilter)) {
    $query .= " AND o.status = '$statusFilter' ";
}

if (!empty($dateFilter)) {
    switch($dateFilter) {
        case 'today':
            $query .= " AND DATE(o.created_at) = CURDATE() ";
            break;
        case 'yesterday':
            $query .= " AND DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) ";
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

// Handle order status update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['status']) ? sanitize($_POST['status']) : '';
    
    if ($orderId > 0 && !empty($newStatus)) {
        // Validate order belongs to this restaurant
        $orderCheck = getRow("SELECT id FROM orders WHERE id = $orderId AND restaurant_id = {$restaurant['id']}");
        
        if ($orderCheck) {
            $updateData = [
                'status' => $newStatus
            ];
            
            // If status is out_for_delivery, set estimated delivery time
            if ($newStatus == 'out_for_delivery') {
                $updateData['estimated_delivery_time'] = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            }
            
            // If status is delivered, set actual delivery time
            if ($newStatus == 'delivered') {
                $updateData['actual_delivery_time'] = date('Y-m-d H:i:s');
            }
            
            $result = updateData('orders', $updateData, "id = $orderId");
            
            if ($result) {
                $success = "Order status updated successfully";
                
                // Refresh orders
                $orders = getRows($query);
            } else {
                $error = "Failed to update order status";
            }
        } else {
            $error = "Invalid order";
        }
    } else {
        $error = "Invalid request";
    }
}

// View order details
$viewOrder = null;
$orderItems = [];

if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $viewId = (int)$_GET['view'];
    $viewOrder = getRow("SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.id 
                        WHERE o.id = $viewId AND o.restaurant_id = {$restaurant['id']}");
    
    if ($viewOrder) {
        $orderItems = getRows("SELECT oi.*, mi.name as item_name 
                              FROM order_items oi 
                              JOIN menu_items mi ON oi.menu_item_id = mi.id 
                              WHERE oi.order_id = $viewId");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - FoodPanda Clone</title>
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
            margin-bottom: 30px;
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
        
        /* Order Details */
        .order-details {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        .order-info {
            flex: 2;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .order-items {
            flex: 3;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
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
        .status-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .status-form label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: var(--dark-color);
        }
        .status-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        /* Item Table in Order Details */
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
        
        /* Order Summary */
        .order-summary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-weight: 500;
            font-size: 18px;
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
            .order-details {
                flex-direction: column;
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
                <a href="restaurant_dashboard.php" class="nav-link">Dashboard</a>
                <a href="manage_orders.php" class="nav-link active">Orders</a>
                <a href="add_menu_item.php" class="nav-link">Menu</a>
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
                <h1>Manage Orders</h1>
                <p>View and update the status of customer orders</p>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($viewOrder): ?>
                <!-- Order Details View -->
                <div style="margin-bottom: 20px;">
                    <a href="manage_orders.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left" style="margin-right: 5px;"></i>
                        Back to Orders
                    </a>
                </div>
                
                <div class="order-details">
                    <div class="order-info">
                        <h3 class="section-title">Order Information</h3>
                        
                        <div class="info-item">
                            <label>Order Number</label>
                            <p><?= htmlspecialchars($viewOrder['order_number']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Date & Time</label>
                            <p><?= date('F d, Y h:i A', strtotime($viewOrder['created_at'])) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Status</label>
                            <p>
                                <?php
                                $statusClass = '';
                                switch ($viewOrder['status']) {
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
                                    <?= ucfirst(str_replace('_', ' ', $viewOrder['status'])) ?>
                                </span>
                            </p>
                        </div>
                        <div class="info-item">
                            <label>Payment Method</label>
                            <p><?= ucfirst($viewOrder['payment_method']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Payment Status</label>
                            <p><?= ucfirst($viewOrder['payment_status']) ?></p>
                        </div>
                        
                        <h3 class="section-title" style="margin-top: 30px;">Customer Information</h3>
                        
                        <div class="info-item">
                            <label>Customer Name</label>
                            <p><?= htmlspecialchars($viewOrder['customer_name']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Phone Number</label>
                            <p><?= htmlspecialchars($viewOrder['contact_phone']) ?></p>
                        </div>
                        <div class="info-item">
                            <label>Delivery Address</label>
                            <p><?= htmlspecialchars($viewOrder['delivery_address']) ?></p>
                        </div>
                        <?php if (!empty($viewOrder['delivery_instructions'])): ?>
                            <div class="info-item">
                                <label>Delivery Instructions</label>
                                <p><?= htmlspecialchars($viewOrder['delivery_instructions']) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($viewOrder['status'] != 'delivered' && $viewOrder['status'] != 'cancelled'): ?>
                            <form action="manage_orders.php?view=<?= $viewOrder['id'] ?>" method="POST" class="status-form">
                                <label for="status">Update Order Status</label>
                                <select name="status" id="status">
                                    <?php if ($viewOrder['status'] == 'pending'): ?>
                                        <option value="confirmed">Confirm Order</option>
                                    <?php endif; ?>
                                    
                                    <?php if ($viewOrder['status'] == 'pending' || $viewOrder['status'] == 'confirmed'): ?>
                                        <option value="preparing">Start Preparing</option>
                                    <?php endif; ?>
                                    
                                    <?php if ($viewOrder['status'] == 'confirmed' || $viewOrder['status'] == 'preparing'): ?>
                                        <option value="out_for_delivery">Out for Delivery</option>
                                    <?php endif; ?>
                                    
                                    <?php if ($viewOrder['status'] == 'out_for_delivery'): ?>
                                        <option value="delivered">Mark as Delivered</option>
                                    <?php endif; ?>
                                    
                                    <?php if ($viewOrder['status'] != 'cancelled' && $viewOrder['status'] != 'delivered'): ?>
                                        <option value="cancelled">Cancel Order</option>
                                    <?php endif; ?>
                                </select>
                                <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
                                <button type="submit" name="update_status" class="btn btn-primary" style="width: 100%;">Update Status</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-items">
                        <h3 class="section-title">Order Items</h3>
                        
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td>$<?= number_format($item['unit_price'], 2) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>$<?= number_format($item['total_price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="order-summary">
                            <div class="summary-row">
                                <div>Subtotal</div>
                                <div>$<?= number_format($viewOrder['total_amount'], 2) ?></div>
                            </div>
                            <div class="summary-row">
                                <div>Delivery Fee</div>
                                <div>$<?= number_format($viewOrder['delivery_fee'], 2) ?></div>
                            </div>
                            <div class="summary-row">
                                <div>Tax</div>
                                <div>$<?= number_format($viewOrder['tax'], 2) ?></div>
                            </div>
                            <div class="summary-total">
                                <div>Total</div>
                                <div>$<?= number_format($viewOrder['grand_total'], 2) ?></div>
                            </div>
                        </div>
                        
                        <?php if ($viewOrder['status'] == 'delivered'): ?>
                            <div style="margin-top: 20px; padding: 15px; background-color: #e8f5e9; border-radius: 8px; text-align: center;">
                                <i class="fas fa-check-circle" style="color: #388e3c; font-size: 24px; margin-bottom: 10px;"></i>
                                <h3 style="color: #388e3c; margin-bottom: 5px;">Order Completed</h3>
                                <p>This order was delivered on <?= date('F d, Y h:i A', strtotime($viewOrder['actual_delivery_time'])) ?></p>
                            </div>
                        <?php elseif ($viewOrder['status'] == 'cancelled'): ?>
                            <div style="margin-top: 20px; padding: 15px; background-color: #ffebee; border-radius: 8px; text-align: center;">
                                <i class="fas fa-times-circle" style="color: #d32f2f; font-size: 24px; margin-bottom: 10px;"></i>
                                <h3 style="color: #d32f2f; margin-bottom: 5px;">Order Cancelled</h3>
                                <p>This order was cancelled and will not be processed.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Orders List View -->
                <div class="filter-bar">
                    <form action="manage_orders.php" method="GET" id="filter-form">
                        <div class="filter-group">
                            <label for="status">Status:</label>
                            <select name="status" id="status" class="filter-select" onchange="document.getElementById('filter-form').submit()">
                                <option value="">All Orders</option>
                                <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $statusFilter == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="preparing" <?= $statusFilter == 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                <option value="out_for_delivery" <?= $statusFilter == 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                                <option value="delivered" <?= $statusFilter == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group" style="margin-left: 20px;">
                            <label for="date">Date:</label>
                            <select name="date" id="date" class="filter-select" onchange="document.getElementById('filter-form').submit()">
                                <option value="">All Time</option>
                                <option value="today" <?= $dateFilter == 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="yesterday" <?= $dateFilter == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                                <option value="week" <?= $dateFilter == 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                                <option value="month" <?= $dateFilter == 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <a href="manage_orders.php" class="btn btn-outline">Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <div class="orders-container">
                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h3>No orders found</h3>
                            <p>There are no orders matching your filter criteria.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
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
                                                
                                                <?php if ($order['status'] == 'pending'): ?>
                                                    <form action="manage_orders.php" method="POST" style="display: inline;">
                                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                        <input type="hidden" name="status" value="confirmed">
                                                        <button type="submit" name="update_status" class="action-btn" style="border: none; cursor: pointer; margin-left: 5px;">
                                                            Confirm
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
