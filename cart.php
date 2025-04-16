<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Check if user is logged in
requireLogin();

// Initialize variables
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : ['items' => []];
$cartEmpty = empty($cart['items']);
$subtotal = 0;
$deliveryFee = $cartEmpty ? 0 : 2.99;
$tax = 0;
$total = 0;

// Calculate cart totals
if (!$cartEmpty) {
    foreach ($cart['items'] as $item) {
        $subtotal += $item['total'];
    }
    $tax = $subtotal * 0.1; // 10% tax
    $total = $subtotal + $deliveryFee + $tax;
}

// Handle quantity update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($quantity < 1) $quantity = 1;
    
    foreach ($cart['items'] as &$item) {
        if ($item['item_id'] == $itemId) {
            $item['quantity'] = $quantity;
            $item['total'] = $item['item_price'] * $quantity;
            break;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: cart.php");
    exit;
}

// Handle item removal
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $removeId = (int)$_GET['remove'];
    
    foreach ($cart['items'] as $key => $item) {
        if ($item['item_id'] == $removeId) {
            unset($cart['items'][$key]);
            break;
        }
    }
    
    // Re-index the array
    $cart['items'] = array_values($cart['items']);
    
    // If cart is empty, remove restaurant info
    if (empty($cart['items'])) {
        unset($cart['restaurant_id']);
        unset($cart['restaurant_name']);
    }
    
    $_SESSION['cart'] = $cart;
    
    // Redirect to prevent bookmark issues
    header("Location: cart.php");
    exit;
}

// Handle clear cart
if (isset($_GET['clear'])) {
    unset($_SESSION['cart']);
    header("Location: cart.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - FoodPanda Clone</title>
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
        .btn-link {
            color: var(--primary-color);
            background: none;
            padding: 0;
        }
        .btn-link:hover {
            text-decoration: underline;
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
        
        /* Cart Container */
        .cart-container {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
        }
        .cart-items {
            flex: 2;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .cart-summary {
            flex: 1;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            height: fit-content;
        }
        
        /* Cart Item Styles */
        .restaurant-info {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .restaurant-name {
            font-size: 18px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        .clear-cart {
            margin-top: 10px;
            display: inline-block;
        }
        .cart-item {
            display: flex;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .cart-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .item-details {
            flex: 1;
        }
        .item-name {
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        .item-price {
            color: #666;
            font-size: 14px;
        }
        .item-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .quantity-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            background-color: var(--light-color);
            border: none;
            color: var(--primary-color);
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .quantity-btn:hover {
            background-color: var(--secondary-color);
            color: white;
        }
        .quantity-input {
            width: 40px;
            height: 30px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .item-total {
            font-weight: 500;
            color: var(--primary-color);
            min-width: 80px;
            text-align: right;
        }
        .remove-item {
            color: #dc3545;
            cursor: pointer;
            font-size: 14px;
        }
        .remove-item:hover {
            text-decoration: underline;
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 50px 0;
        }
        .empty-cart i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
        .empty-cart h2 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        .empty-cart p {
            color: #666;
            margin-bottom: 20px;
        }
        
        /* Cart Summary */
        .summary-title {
            font-size: 18px;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
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
        .checkout-button {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            text-align: center;
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
            .cart-container {
                flex-direction: column;
            }
            .item-actions {
                flex-direction: column;
                align-items: flex-start;
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
                <h1>Your Cart</h1>
                <p>Review your order before checkout</p>
            </div>
            
            <div class="cart-container">
                <div class="cart-items">
                    <?php if ($cartEmpty): ?>
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart"></i>
                            <h2>Your cart is empty</h2>
                            <p>Looks like you haven't added any items to your cart yet.</p>
                            <a href="index.php" class="btn btn-primary">Browse Restaurants</a>
                        </div>
                    <?php else: ?>
                        <div class="restaurant-info">
                            <div class="restaurant-name">
                                <i class="fas fa-utensils" style="margin-right: 10px; color: var(--primary-color);"></i>
                                <?= htmlspecialchars($cart['restaurant_name']) ?>
                            </div>
                            <a href="cart.php?clear=1" class="btn-link clear-cart">Clear Cart</a>
                        </div>
                        
                        <?php foreach ($cart['items'] as $item): ?>
                            <div class="cart-item">
                                <div class="item-details">
                                    <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <div class="item-price">$<?= number_format($item['item_price'], 2) ?> each</div>
                                </div>
                                <div class="item-actions">
                                    <form action="cart.php" method="POST" class="quantity-control">
                                        <button type="button" class="quantity-btn minus" onclick="decrementQuantity(this)">-</button>
                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" class="quantity-input" onchange="this.form.submit()">
                                        <button type="button" class="quantity-btn plus" onclick="incrementQuantity(this)">+</button>
                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                        <input type="hidden" name="update_quantity" value="1">
                                    </form>
                                    <a href="cart.php?remove=<?= $item['item_id'] ?>" class="remove-item">Remove</a>
                                </div>
                                <div class="item-total">$<?= number_format($item['total'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!$cartEmpty): ?>
                    <div class="cart-summary">
                        <h3 class="summary-title">Order Summary</h3>
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
                        <a href="checkout.php" class="btn btn-primary checkout-button">Proceed to Checkout</a>
                        <a href="restaurant_details.php?id=<?= $cart['restaurant_id'] ?>" class="btn btn-outline checkout-button" style="margin-top: 10px;">Add More Items</a>
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

    <script>
        // JavaScript for quantity controls
        function decrementQuantity(button) {
            const input = button.nextElementSibling;
            let value = parseInt(input.value);
            if (value > 1) {
                input.value = value - 1;
                input.form.submit();
            }
        }
        
        function incrementQuantity(button) {
            const input = button.previousElementSibling;
            let value = parseInt(input.value);
            input.value = value + 1;
            input.form.submit();
        }
    </script>
</body>
</html>
