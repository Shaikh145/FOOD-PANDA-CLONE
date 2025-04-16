<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Check if user is logged in
requireLogin();

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get user information
$user = getCurrentUser();

// Get order details
$order = getRow("SELECT o.*, r.name as restaurant_name, r.address as restaurant_address, r.phone as restaurant_phone 
                 FROM orders o 
                 JOIN restaurants r ON o.restaurant_id = r.id 
                 WHERE o.id = $orderId AND o.user_id = {$user['id']}");

// If order doesn't exist or doesn't belong to user, redirect to dashboard
if (!$order) {
    header("Location: dashboard.php");
    exit;
}

// Get order items
$orderItems = getRows("SELECT oi.*, mi.name as item_name 
                      FROM order_items oi 
                      JOIN menu_items mi ON oi.menu_item_id = mi.id 
                      WHERE oi.order_id = $orderId");

// Calculate order progression based on status
$statusProgress = 0;
switch ($order['status']) {
    case 'pending':
        $statusProgress = 20;
        break;
    case 'confirmed':
        $statusProgress = 40;
        break;
    case 'preparing':
        $statusProgress = 60;
        break;
    case 'out_for_delivery':
        $statusProgress = 80;
        break;
    case 'delivered':
        $statusProgress = 100;
        break;
    case 'cancelled':
        $statusProgress = 0;
        break;
}

// Calculate remaining time for delivery
$remainingTime = "";
if ($order['status'] != 'delivered' && $order['status'] != 'cancelled') {
    $estimatedTime = strtotime($order['estimated_delivery_time']);
    $now = time();
    
    if ($estimatedTime > $now) {
        $minutesRemaining = ceil(($estimatedTime - $now) / 60);
        $remainingTime = $minutesRemaining . " minutes";
    } else {
        $remainingTime = "Any moment now";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order #<?= $order['order_number'] ?> - FoodPanda Clone</title>
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
        
        /* Success Message */
        .success-message {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        .success-message i {
            font-size: 24px;
            color: #4caf50;
            margin-right: 15px;
        }
        
        /* Tracking Container */
        .tracking-container {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
        }
        .tracking-info {
            flex: 3;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .order-details {
            flex: 2;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        
        /* Tracking Progress */
        .tracking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .order-number {
            font-size: 18px;
            color: var(--dark-color);
            font-weight: 500;
        }
        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
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
        .progress-container {
            margin-bottom: 30px;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background-color: #eee;
            border-radius: 3px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .progress-fill {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        .progress-step {
            text-align: center;
            width: 20%;
            position: relative;
        }
        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #999;
            font-size: 16px;
            position: relative;
            z-index: 2;
        }
        .step-active .step-icon {
            background-color: var(--primary-color);
            color: white;
        }
        .step-name {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .step-active .step-name {
            color: var(--primary-color);
            font-weight: 500;
        }
        .step-cancelled .step-icon {
            background-color: #f44336;
            color: white;
        }
        .step-cancelled .step-name {
            color: #f44336;
        }
        
        /* Estimated Delivery */
        .estimated-delivery {
            background-color: var(--light-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .estimated-delivery h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .estimated-delivery h4 i {
            margin-right: 8px;
        }
        .estimated-time {
            font-size: 24px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        /* Restaurant Info */
        .restaurant-info {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .restaurant-name {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        .restaurant-name i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        .info-item {
            display: flex;
            margin-bottom: 10px;
            color: #666;
        }
        .info-item i {
            margin-right: 8px;
            color: #999;
            width: 16px;
        }
        
        /* Order Items */
        .order-items {
            margin-bottom: 20px;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #eee;
        }
        .order-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .item-name {
            display: flex;
            color: #666;
        }
        .item-name .quantity {
            margin-right: 8px;
            color: var(--primary-color);
            font-weight: 500;
        }
        .item-price {
            color: #666;
        }
        
        /* Order Summary */
        .order-summary {
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-weight: 500;
            color: var(--dark-color);
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
            .tracking-container {
                flex-direction: column;
            }
            .progress-steps {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            .progress-step {
                width: 100%;
                display: flex;
                align-items: center;
            }
            .step-icon {
                margin: 0 15px 0 0;
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
                <h1>Track Your Order</h1>
                <p>Check the status and estimated delivery time of your order</p>
            </div>
            
            <?php if (isset($_SESSION['order_success']) && $_SESSION['order_success']['order_id'] == $orderId): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <h3>Order Successfully Placed!</h3>
                        <p>Your order #<?= $_SESSION['order_success']['order_number'] ?> has been received and is being processed.</p>
                    </div>
                </div>
                <?php unset($_SESSION['order_success']); ?>
            <?php endif; ?>
            
            <div class="tracking-container">
                <div class="tracking-info">
                    <div class="tracking-header">
                        <div class="order-number">Order #<?= htmlspecialchars($order['order_number']) ?></div>
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
                        <div class="order-status <?= $statusClass ?>">
                            <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <?php if ($order['status'] != 'cancelled'): ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $statusProgress ?>%;"></div>
                            </div>
                            
                            <div class="progress-steps">
                                <div class="progress-step <?= $statusProgress >= 20 ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div class="step-name">Order Placed</div>
                                </div>
                                <div class="progress-step <?= $statusProgress >= 40 ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="step-name">Confirmed</div>
                                </div>
                                <div class="progress-step <?= $statusProgress >= 60 ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <div class="step-name">Preparing</div>
                                </div>
                                <div class="progress-step <?= $statusProgress >= 80 ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-motorcycle"></i>
                                    </div>
                                    <div class="step-name">Out for Delivery</div>
                                </div>
                                <div class="progress-step <?= $statusProgress >= 100 ? 'step-active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div class="step-name">Delivered</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="progress-step step-cancelled">
                                <div class="step-icon">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div class="step-name">Order Cancelled</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                        <div class="estimated-delivery">
                            <h4><i class="fas fa-clock"></i> Estimated Delivery Time</h4>
                            <div class="estimated-time"><?= date('h:i A', strtotime($order['estimated_delivery_time'])) ?></div>
                            <p>Arriving in approximately <?= $remainingTime ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="restaurant-info">
                        <h3 class="section-title">Restaurant Information</h3>
                        <div class="restaurant-name">
                            <i class="fas fa-utensils"></i>
                            <?= htmlspecialchars($order['restaurant_name']) ?>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div><?= htmlspecialchars($order['restaurant_address']) ?></div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <div><?= htmlspecialchars($order['restaurant_phone']) ?></div>
                        </div>
                    </div>
                    
                    <div class="delivery-info">
                        <h3 class="section-title">Delivery Information</h3>
                        <div class="info-item">
                            <i class="fas fa-user"></i>
                            <div><?= htmlspecialchars($user['name']) ?></div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div><?= htmlspecialchars($order['delivery_address']) ?></div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <div><?= htmlspecialchars($order['contact_phone']) ?></div>
                        </div>
                        <?php if (!empty($order['delivery_instructions'])): ?>
                            <div class="info-item">
                                <i class="fas fa-info-circle"></i>
                                <div><?= htmlspecialchars($order['delivery_instructions']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="order-details">
                    <h3 class="section-title">Order Details</h3>
                    <div class="order-items">
                        <?php foreach ($orderItems as $item): ?>
                            <div class="order-item">
                                <div class="item-name">
                                    <span class="quantity"><?= $item['quantity'] ?>x</span>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </div>
                                <div class="item-price">$<?= number_format($item['total_price'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-summary">
                        <div class="summary-row">
                            <div>Subtotal</div>
                            <div>$<?= number_format($order['total_amount'], 2) ?></div>
                        </div>
                        <div class="summary-row">
                            <div>Delivery Fee</div>
                            <div>$<?= number_format($order['delivery_fee'], 2) ?></div>
                        </div>
                        <div class="summary-row">
                            <div>Tax</div>
                            <div>$<?= number_format($order['tax'], 2) ?></div>
                        </div>
                        <div class="summary-total">
                            <div>Total</div>
                            <div>$<?= number_format($order['grand_total'], 2) ?></div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <div class="info-item">
                            <i class="fas fa-calendar-alt"></i>
                            <div>Ordered on <?= date('F d, Y h:i A', strtotime($order['created_at'])) ?></div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-credit-card"></i>
                            <div>Payment Method: <?= ucfirst($order['payment_method']) ?></div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <div>Payment Status: <?= ucfirst($order['payment_status']) ?></div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
                    </div>
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
        // Auto refresh page every 30 seconds if order is not delivered or cancelled
        <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
            setTimeout(function() {
                location.reload();
            }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>
