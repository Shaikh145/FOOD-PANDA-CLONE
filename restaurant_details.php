<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Get restaurant ID from URL
$restaurantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get restaurant details
$restaurant = getRow("SELECT * FROM restaurants WHERE id = $restaurantId AND status = 'active'");

// If restaurant doesn't exist, redirect to homepage
if (!$restaurant) {
    header("Location: index.php");
    exit;
}

// Get menu categories for this restaurant
$categories = getRows("SELECT * FROM menu_categories WHERE restaurant_id = $restaurantId ORDER BY display_order ASC");

// Get menu items for each category
$menuItems = [];
foreach ($categories as $category) {
    $categoryId = $category['id'];
    $items = getRows("SELECT * FROM menu_items WHERE category_id = $categoryId AND status = 'available' ORDER BY name ASC");
    $menuItems[$categoryId] = $items;
}

// Check if the item is added to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $itemId = (int)$_POST['item_id'];
    $itemName = sanitize($_POST['item_name']);
    $itemPrice = (float)$_POST['item_price'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity < 1) $quantity = 1;
    
    // Initialize cart if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [
            'restaurant_id' => $restaurantId,
            'restaurant_name' => $restaurant['name'],
            'items' => []
        ];
    }
    
    // Check if adding from a different restaurant
    if ($_SESSION['cart']['restaurant_id'] != $restaurantId) {
        $replaceCart = isset($_POST['replace_cart']) && $_POST['replace_cart'] == 'yes';
        
        // If user didn't confirm to replace cart yet, show confirm message
        if (!$replaceCart) {
            $_SESSION['cart_restaurant_conflict'] = true;
            $_SESSION['new_item'] = [
                'item_id' => $itemId,
                'item_name' => $itemName,
                'item_price' => $itemPrice,
                'quantity' => $quantity,
                'restaurant_id' => $restaurantId,
                'restaurant_name' => $restaurant['name']
            ];
            header("Location: restaurant_details.php?id=$restaurantId&conflict=1");
            exit;
        } else {
            // Clear existing cart and create new one with new restaurant
            $_SESSION['cart'] = [
                'restaurant_id' => $restaurantId,
                'restaurant_name' => $restaurant['name'],
                'items' => []
            ];
            unset($_SESSION['cart_restaurant_conflict']);
            unset($_SESSION['new_item']);
        }
    }
    
    // Check if item already exists in cart
    $itemExists = false;
    foreach ($_SESSION['cart']['items'] as &$cartItem) {
        if ($cartItem['item_id'] == $itemId) {
            $cartItem['quantity'] += $quantity;
            $cartItem['total'] = $cartItem['item_price'] * $cartItem['quantity'];
            $itemExists = true;
            break;
        }
    }
    
    // If item doesn't exist in cart, add it
    if (!$itemExists) {
        $_SESSION['cart']['items'][] = [
            'item_id' => $itemId,
            'item_name' => $itemName,
            'item_price' => $itemPrice,
            'quantity' => $quantity,
            'total' => $itemPrice * $quantity
        ];
    }
    
    // Redirect to prevent form resubmission
    header("Location: restaurant_details.php?id=$restaurantId&added=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($restaurant['name']) ?> - FoodPanda Clone</title>
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
        
        /* Restaurant Hero Section */
        .restaurant-hero {
            background-color: white;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        .restaurant-cover {
            height: 300px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .restaurant-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            padding: 30px;
            color: white;
        }
        .restaurant-info {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .restaurant-details h1 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        .restaurant-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            color: #666;
        }
        .restaurant-meta-item {
            display: flex;
            align-items: center;
        }
        .restaurant-meta-item i {
            margin-right: 5px;
            color: var(--primary-color);
        }
        .restaurant-description {
            color: #666;
            max-width: 600px;
        }
        .restaurant-actions {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
        }
        
        /* Menu Section */
        .menu-container {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
        }
        .menu-sidebar {
            width: 200px;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            position: sticky;
            top: 80px;
            height: fit-content;
        }
        .menu-sidebar h3 {
            color: var(--dark-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .category-list {
            list-style-type: none;
        }
        .category-list li {
            margin-bottom: 10px;
        }
        .category-list a {
            text-decoration: none;
            color: #666;
            display: block;
            padding: 8px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .category-list a:hover,
        .category-list a.active {
            background-color: var(--light-color);
            color: var(--primary-color);
        }
        .menu-content {
            flex: 1;
        }
        .menu-category {
            margin-bottom: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .category-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .category-header h2 {
            color: var(--dark-color);
            font-size: 20px;
        }
        .menu-items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .menu-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            transition: transform 0.3s ease;
        }
        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }
        .menu-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .menu-item-name {
            font-weight: 500;
            color: var(--dark-color);
        }
        .menu-item-price {
            color: var(--primary-color);
            font-weight: 500;
        }
        .menu-item-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            height: 40px;
            overflow: hidden;
        }
        .menu-item-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        .add-to-cart {
            flex: 1;
            margin-left: 10px;
        }
        
        /* Notification */
        .notification {
            position: fixed;
            top: 80px;
            right: 20px;
            max-width: 300px;
            background-color: white;
            border-left: 4px solid var(--primary-color);
            border-radius: 4px;
            box-shadow: var(--shadow);
            padding: 15px;
            z-index: 1000;
            animation: slideIn 0.3s forwards, fadeOut 0.3s 3s forwards;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }
        
        /* Cart Conflict Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-container {
            background-color: white;
            border-radius: 8px;
            max-width: 400px;
            width: 100%;
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .modal-header h3 {
            color: var(--dark-color);
        }
        .modal-close {
            background: none;
            border: none;
            color: #666;
            font-size: 20px;
            cursor: pointer;
        }
        .modal-body {
            margin-bottom: 20px;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Cart Summary */
        .cart-summary {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            position: sticky;
            top: 80px;
            height: fit-content;
        }
        .cart-summary h3 {
            color: var(--dark-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .cart-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .cart-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-weight: 500;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 15px 0;
            text-align: center;
            font-size: 14px;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .menu-container {
                flex-direction: column;
            }
            .menu-sidebar {
                width: 100%;
                position: static;
            }
            .category-list {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .category-list li {
                margin-bottom: 0;
            }
        }
        @media (max-width: 768px) {
            .navigation {
                display: none;
            }
            .menu-items {
                grid-template-columns: 1fr;
            }
            .restaurant-cover {
                height: 200px;
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
            </nav>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="cart.php" class="btn btn-outline">
                        <i class="fas fa-shopping-cart"></i> 
                        Cart
                        <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart']['items'])): ?>
                            (<?= count($_SESSION['cart']['items']) ?>)
                        <?php endif; ?>
                    </a>
                    <a href="dashboard.php" class="btn btn-outline">My Account</a>
                    <a href="logout.php" class="btn btn-primary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">Login</a>
                    <a href="register.php" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Notification for added item -->
    <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
        <div class="notification">
            <i class="fas fa-check-circle" style="color: var(--primary-color); margin-right: 10px;"></i>
            Item added to cart successfully!
        </div>
    <?php endif; ?>

    <!-- Cart Conflict Modal -->
    <?php if (isset($_SESSION['cart_restaurant_conflict']) && isset($_GET['conflict']) && $_GET['conflict'] == 1): ?>
        <div class="modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <h3>Replace Cart?</h3>
                    <button class="modal-close" onclick="location.href='restaurant_details.php?id=<?= $restaurantId ?>';">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Your cart contains items from <strong><?= $_SESSION['cart']['restaurant_name'] ?></strong>.</p>
                    <p>Adding items from <strong><?= $restaurant['name'] ?></strong> will replace your current cart.</p>
                    <p>Do you want to continue?</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="location.href='restaurant_details.php?id=<?= $restaurantId ?>';">Cancel</button>
                    <form action="restaurant_details.php?id=<?= $restaurantId ?>" method="POST">
                        <input type="hidden" name="add_to_cart" value="1">
                        <input type="hidden" name="replace_cart" value="yes">
                        <input type="hidden" name="item_id" value="<?= $_SESSION['new_item']['item_id'] ?>">
                        <input type="hidden" name="item_name" value="<?= $_SESSION['new_item']['item_name'] ?>">
                        <input type="hidden" name="item_price" value="<?= $_SESSION['new_item']['item_price'] ?>">
                        <input type="hidden" name="quantity" value="<?= $_SESSION['new_item']['quantity'] ?>">
                        <button type="submit" class="btn btn-primary">Replace Cart</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Restaurant Hero Section -->
    <section class="restaurant-hero">
        <div class="restaurant-cover" style="background-image: url('https://source.unsplash.com/random/1200x300/?food,<?= urlencode($restaurant['cuisine_type']) ?>');">
            <div class="restaurant-overlay">
                <div class="container">
                    <h1><?= htmlspecialchars($restaurant['name']) ?></h1>
                    <p><?= htmlspecialchars($restaurant['cuisine_type']) ?></p>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="restaurant-info">
                <div class="restaurant-details">
                    <h1><?= htmlspecialchars($restaurant['name']) ?></h1>
                    <div class="restaurant-meta">
                        <div class="restaurant-meta-item">
                            <i class="fas fa-utensils"></i>
                            <?= htmlspecialchars($restaurant['cuisine_type']) ?>
                        </div>
                        <div class="restaurant-meta-item">
                            <i class="fas fa-star"></i>
                            <?= number_format($restaurant['rating'], 1) ?> Rating
                        </div>
                        <div class="restaurant-meta-item">
                            <i class="fas fa-clock"></i>
                            <?= htmlspecialchars($restaurant['delivery_time']) ?> Delivery
                        </div>
                        <div class="restaurant-meta-item">
                            <i class="fas fa-dollar-sign"></i>
                            Min Order: $<?= number_format($restaurant['minimum_order'], 2) ?>
                        </div>
                    </div>
                    <p class="restaurant-description"><?= htmlspecialchars($restaurant['description']) ?></p>
                </div>
                <div class="restaurant-actions">
                    <div class="restaurant-meta-item">
                        <i class="fas fa-phone"></i>
                        <?= htmlspecialchars($restaurant['phone']) ?>
                    </div>
                    <div class="restaurant-meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($restaurant['address']) ?>
                    </div>
                    <div class="restaurant-meta-item">
                        <i class="fas fa-clock"></i>
                        <?= htmlspecialchars($restaurant['opening_hours']) ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section class="container">
        <div class="menu-container">
            <!-- Menu Sidebar -->
            <div class="menu-sidebar">
                <h3>Menu Categories</h3>
                <ul class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="#category-<?= $category['id'] ?>" class="category-link">
                                <?= htmlspecialchars($category['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart']['items']) && $_SESSION['cart']['restaurant_id'] == $restaurantId): ?>
                    <div class="cart-summary">
                        <h3>Your Cart</h3>
                        <?php 
                        $cartTotal = 0;
                        foreach ($_SESSION['cart']['items'] as $item): 
                            $cartTotal += $item['total'];
                        ?>
                            <div class="cart-summary-item">
                                <div><?= $item['quantity'] ?> x <?= htmlspecialchars($item['item_name']) ?></div>
                                <div>$<?= number_format($item['total'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-total">
                            <div>Total:</div>
                            <div>$<?= number_format($cartTotal, 2) ?></div>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <a href="cart.php" class="btn btn-primary" style="width: 100%;">View Cart</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Menu Content -->
            <div class="menu-content">
                <?php foreach ($categories as $category): ?>
                    <div class="menu-category" id="category-<?= $category['id'] ?>">
                        <div class="category-header">
                            <h2><?= htmlspecialchars($category['name']) ?></h2>
                            <p><?= htmlspecialchars($category['description']) ?></p>
                        </div>
                        
                        <div class="menu-items">
                            <?php 
                            if (isset($menuItems[$category['id']])) {
                                foreach ($menuItems[$category['id']] as $item): 
                            ?>
                                <div class="menu-item">
                                    <div class="menu-item-header">
                                        <div class="menu-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="menu-item-price">$<?= number_format($item['price'], 2) ?></div>
                                    </div>
                                    <div class="menu-item-description"><?= htmlspecialchars($item['description']) ?></div>
                                    <form action="restaurant_details.php?id=<?= $restaurantId ?>" method="POST" class="menu-item-actions">
                                        <div class="quantity-control">
                                            <button type="button" class="quantity-btn minus" onclick="decrementQuantity(this)">-</button>
                                            <input type="number" name="quantity" value="1" min="1" class="quantity-input">
                                            <button type="button" class="quantity-btn plus" onclick="incrementQuantity(this)">+</button>
                                        </div>
                                        <input type="hidden" name="add_to_cart" value="1">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name']) ?>">
                                        <input type="hidden" name="item_price" value="<?= $item['price'] ?>">
                                        <button type="submit" class="btn btn-primary add-to-cart">Add to Cart</button>
                                    </form>
                                </div>
                            <?php 
                                endforeach;
                            } else {
                                echo '<p>No items available in this category.</p>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

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
            }
        }
        
        function incrementQuantity(button) {
            const input = button.previousElementSibling;
            let value = parseInt(input.value);
            input.value = value + 1;
        }
        
        // Highlight active category in sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const categoryLinks = document.querySelectorAll('.category-link');
            
            function highlightActiveCategory() {
                const scrollPosition = window.scrollY;
                
                document.querySelectorAll('.menu-category').forEach(category => {
                    const categoryTop = category.offsetTop - 100;
                    const categoryBottom = categoryTop + category.offsetHeight;
                    const categoryId = category.getAttribute('id');
                    
                    if (scrollPosition >= categoryTop && scrollPosition < categoryBottom) {
                        categoryLinks.forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href') === '#' + categoryId) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }
            
            window.addEventListener('scroll', highlightActiveCategory);
            highlightActiveCategory();
            
            // Smooth scroll to category
            categoryLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                });
            });
        });
    </script>
</body>
</html>
