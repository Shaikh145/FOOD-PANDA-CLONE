<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Check if user is logged in
requireLogin();

// Get user information
$user = getCurrentUser();

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart']['items'])) {
    header("Location: cart.php");
    exit;
}

// Get cart data
$cart = $_SESSION['cart'];
$restaurantId = $cart['restaurant_id'];
$restaurant = getRow("SELECT * FROM restaurants WHERE id = $restaurantId");

// Calculate order totals
$subtotal = 0;
foreach ($cart['items'] as $item) {
    $subtotal += $item['total'];
}
$deliveryFee = 2.99;
$tax = $subtotal * 0.1; // 10% tax
$total = $subtotal + $deliveryFee + $tax;

// Process checkout form
$orderPlaced = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $paymentMethod = isset($_POST['payment_method']) ? sanitize($_POST['payment_method']) : '';
    $instructions = isset($_POST['instructions']) ? sanitize($_POST['instructions']) : '';
    
    if (empty($address)) {
        $errors[] = "Delivery address is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Contact phone is required";
    }
    
    if (empty($paymentMethod)) {
        $errors[] = "Payment method is required";
    }
    
    // If no errors, create order
    if (empty($errors)) {
        // Generate order number
        $orderNumber = 'ORD' . date('YmdHis') . rand(100, 999);
        
        // Set estimated delivery time (30-60 minutes from now)
        $estimatedDelivery = date('Y-m-d H:i:s', strtotime('+45 minutes'));
        
        // Create order in database
        $orderData = [
            'user_id' => $user['id'],
            'restaurant_id' => $restaurantId,
            'order_number' => $orderNumber,
            'status' => 'pending',
            'total_amount' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'grand_total' => $total,
            'payment_method' => $paymentMethod,
            'payment_status' => ($paymentMethod == 'cash') ? 'pending' : 'paid',
            'delivery_address' => $address,
            'contact_phone' => $phone,
            'delivery_instructions' => $instructions,
            'estimated_delivery_time' => $estimatedDelivery,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $orderId = insertData('orders', $orderData);
        
        if ($orderId) {
            // Add order items
            foreach ($cart['items'] as $item) {
                $orderItemData = [
                    'order_id' => $orderId,
                    'menu_item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['item_price'],
                    'total_price' => $item['total'],
                    'special_instructions' => ''
                ];
                
                insertData('order_items', $orderItemData);
            }
            
            // Clear cart
            unset($_SESSION['cart']);
            
            // Set success flag
            $orderPlaced = true;
            $_SESSION['order_success'] = [
                'order_id' => $orderId,
                'order_number' => $orderNumber
            ];
            
            // Redirect to order tracking page
            header("Location: order_tracking.php?id=$orderId");
            exit;
        } else {
            $errors[] = "Failed to place order. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FoodPanda Clone</title>
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
        
        /* Checkout Container */
        .checkout-container {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
        }
        .checkout-form {
            flex: 3;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .order-summary {
            flex: 2;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            height: fit-content;
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
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        .radio-option {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .radio-option input {
            margin-right: 8px;
        }
        
        /* Error Container */
        .error-container {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .error-container ul {
            list-style-type: none;
            padding-left: 10px;
        }
        
        /* Order Summary */
        .summary-title {
            font-size: 18px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .restaurant-info {
            margin-bottom: 20px;
        }
        .restaurant-name {
            font-size: 16px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        .restaurant-name i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        .restaurant-address {
            color: #666;
            font-size: 14px;
            margin-left: 24px;
        }
        .order-items {
            margin-bottom: 20px;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .item-name {
            display: flex;
            align-items: center;
            color: #666;
        }
        .item-name .quantity {
            background-color: var(--light-color);
            color: var(--primary-color);
            font-weight: 500;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 8px;
            font-size: 12px;
        }
        .item-price {
            color: #666;
        }
        .summary-divider {
            height: 1px;
            background-color: #eee;
            margin: 15px 0;
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
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px dashed #eee;
            font-weight: bold;
            color: var(--dark-color);
        }
        .place-order-btn {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            text-align: center;
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
            .checkout-container {
                flex-direction: column;
            }
            .radio-group {
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
                <h1>Checkout</h1>
                <p>Complete your order by providing delivery and payment information</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="error-container">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="checkout-container">
                <div class="checkout-form">
                    <h3 class="form-title">Delivery Information</h3>
                    <form action="checkout.php" method="POST">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="address">Delivery Address</label>
                            <textarea id="address" name="address" class="form-control" required><?= htmlspecialchars($user['address']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="phone">Contact Phone</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="instructions">Delivery Instructions (Optional)</label>
                            <textarea id="instructions" name="instructions" class="form-control" placeholder="E.g., Ring the doorbell, call when you arrive, etc."></textarea>
                        </div>
                        
                        <h3 class="form-title">Payment Method</h3>
                        <div class="form-group">
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="payment_method" value="cash" checked> Cash on Delivery
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="payment_method" value="card"> Credit/Debit Card
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="payment_method" value="wallet"> Digital Wallet
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary place-order-btn">Place Order</button>
                    </form>
                </div>
                
                <div class="order-summary">
                    <h3 class="summary-title">Order Summary</h3>
                    <div class="restaurant-info">
                        <div class="restaurant-name">
                            <i class="fas fa-utensils"></i>
                            <?= htmlspecialchars($restaurant['name']) ?>
                        </div>
                        <div class="restaurant-address"><?= htmlspecialchars($restaurant['address']) ?></div>
                    </div>
                    
                    <div class="order-items">
                        <?php foreach ($cart['items'] as $item): ?>
                            <div class="order-item">
                                <div class="item-name">
                                    <span class="quantity"><?= $item['quantity'] ?>x</span>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </div>
                                <div class="item-price">$<?= number_format($item['total'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row">
                        <div>Subtotal</div>
                        <div>$<?= number_format($subtotal, 2) ?></div>
                    </div>
                    <div class="summary-row">
                        <div>Delivery Fee</div>
                        <div>$<?= number_format($deliveryFee, 2) ?></div>
                    </div>
                    <div class="summary-row">
                        <div>Tax (10%)</div>
                        <div>$<?= number_format($tax, 2) ?></div>
                    </div>
                    
                    <div class="summary-total">
                        <div>Total</div>
                        <div>$<?= number_format($total, 2) ?></div>
                    </div>
                    
                    <a href="cart.php" class="btn btn-outline" style="width: 100%; margin-top: 20px;">Back to Cart</a>
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
        // Card payment fields toggle (simplified for demo)
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            
            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    // In a real application, this would show/hide additional payment fields
                    // based on the selected payment method
                    console.log("Payment method changed to: " + this.value);
                });
            });
        });
    </script>
</body>
</html>
