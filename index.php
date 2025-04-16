<?php
session_start();
require_once 'db.php';

// Fetch all active restaurants
$restaurants = getRows("SELECT * FROM restaurants WHERE status = 'active' ORDER BY rating DESC");

// Get cuisine types for filtering
$cuisineTypes = getRows("SELECT DISTINCT cuisine_type FROM restaurants WHERE status = 'active'");

// Handle filtering
$filterCuisine = isset($_GET['cuisine']) ? sanitize($_GET['cuisine']) : '';
$filterPrice = isset($_GET['price']) ? sanitize($_GET['price']) : '';
$filterTime = isset($_GET['time']) ? sanitize($_GET['time']) : '';

// Apply filters if set
$filterQuery = "SELECT * FROM restaurants WHERE status = 'active'";
if (!empty($filterCuisine)) {
    $filterQuery .= " AND cuisine_type = '$filterCuisine'";
}
if (!empty($filterPrice)) {
    switch($filterPrice) {
        case 'low':
            $filterQuery .= " AND minimum_order <= 10";
            break;
        case 'medium':
            $filterQuery .= " AND minimum_order > 10 AND minimum_order <= 20";
            break;
        case 'high':
            $filterQuery .= " AND minimum_order > 20";
            break;
    }
}
if (!empty($filterTime)) {
    switch($filterTime) {
        case 'fast':
            $filterQuery .= " AND delivery_time LIKE '%30%'";
            break;
        case 'normal':
            $filterQuery .= " AND delivery_time LIKE '%45%'";
            break;
        case 'slow':
            $filterQuery .= " AND delivery_time LIKE '%60%'";
            break;
    }
}
$filterQuery .= " ORDER BY rating DESC";

// Execute filtered query if any filter is applied
if (!empty($filterCuisine) || !empty($filterPrice) || !empty($filterTime)) {
    $restaurants = getRows($filterQuery);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodPanda Clone - Food Delivery</title>
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
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1559329007-40df8a9345d8?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            height: 400px;
            display: flex;
            align-items: center;
            color: white;
            text-align: center;
            margin-bottom: 40px;
        }
        .hero-content {
            width: 100%;
        }
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        .hero p {
            font-size: 18px;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Filter Section */
        .filters {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .filter-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        /* Restaurant List */
        .restaurant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        .restaurant-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }
        .restaurant-card:hover {
            transform: translateY(-5px);
        }
        .restaurant-image {
            height: 180px;
            background-color: #ddd;
            background-size: cover;
            background-position: center;
        }
        .restaurant-info {
            padding: 20px;
        }
        .restaurant-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        .restaurant-cuisine {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .restaurant-meta {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 14px;
        }
        .restaurant-rating {
            display: flex;
            align-items: center;
            color: #ffb100;
        }
        .restaurant-rating i {
            margin-right: 5px;
        }
        .restaurant-delivery {
            color: #666;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 40px 0;
        }
        .footer-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 30px;
        }
        .footer-section {
            flex: 1;
            min-width: 200px;
        }
        .footer-section h3 {
            margin-bottom: 20px;
            font-size: 18px;
        }
        .footer-section ul {
            list-style-type: none;
        }
        .footer-section ul li {
            margin-bottom: 10px;
        }
        .footer-section ul li a {
            color: #ddd;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer-section ul li a:hover {
            color: white;
        }
        .footer-bottom {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #555;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 36px;
            }
            .restaurant-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            .navigation {
                display: none;
            }
        }
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 28px;
            }
            .hero p {
                font-size: 16px;
            }
            .restaurant-grid {
                grid-template-columns: 1fr;
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
                <a href="#" class="nav-link">Cuisines</a>
                <a href="#" class="nav-link">Offers</a>
            </nav>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-outline">My Account</a>
                    <a href="logout.php" class="btn btn-primary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">Login</a>
                    <a href="register.php" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-content">
            <h1>Food Delivery & Takeaway</h1>
            <p>Order from your favorite restaurants with the best selection of takeaway food</p>
            <a href="#restaurants" class="btn btn-primary">Order Now</a>
        </div>
    </section>

    <!-- Filter Section -->
    <section class="container">
        <div class="filters">
            <form action="index.php" method="GET" id="filter-form">
                <div class="filter-container">
                    <div class="filter-group">
                        <label for="cuisine">Cuisine Type</label>
                        <select name="cuisine" id="cuisine" onchange="document.getElementById('filter-form').submit()">
                            <option value="">All Cuisines</option>
                            <?php foreach ($cuisineTypes as $cuisine): ?>
                                <option value="<?= $cuisine['cuisine_type'] ?>" <?= ($filterCuisine == $cuisine['cuisine_type']) ? 'selected' : '' ?>>
                                    <?= $cuisine['cuisine_type'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="price">Price Range</label>
                        <select name="price" id="price" onchange="document.getElementById('filter-form').submit()">
                            <option value="">All Prices</option>
                            <option value="low" <?= ($filterPrice == 'low') ? 'selected' : '' ?>>$ (Under $10)</option>
                            <option value="medium" <?= ($filterPrice == 'medium') ? 'selected' : '' ?>>$$ ($10-$20)</option>
                            <option value="high" <?= ($filterPrice == 'high') ? 'selected' : '' ?>>$$$ (Over $20)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="time">Delivery Time</label>
                        <select name="time" id="time" onchange="document.getElementById('filter-form').submit()">
                            <option value="">Any Time</option>
                            <option value="fast" <?= ($filterTime == 'fast') ? 'selected' : '' ?>>Under 30 min</option>
                            <option value="normal" <?= ($filterTime == 'normal') ? 'selected' : '' ?>>30-45 min</option>
                            <option value="slow" <?= ($filterTime == 'slow') ? 'selected' : '' ?>>Over 45 min</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="index.php" class="btn btn-outline" style="margin-left: 10px;">Clear All</a>
                </div>
            </form>
        </div>
    </section>

    <!-- Restaurant Listing -->
    <section class="container" id="restaurants">
        <h2 style="margin-bottom: 20px; color: var(--dark-color);">Featured Restaurants</h2>
        
        <?php if (empty($restaurants)): ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-utensils" style="font-size: 48px; color: var(--primary-color); margin-bottom: 20px;"></i>
                <h3>No restaurants found</h3>
                <p>Try changing your filters or check back later for more options.</p>
            </div>
        <?php else: ?>
            <div class="restaurant-grid">
                <?php foreach ($restaurants as $restaurant): ?>
                    <div class="restaurant-card">
                        <div class="restaurant-image" style="background-image: url('https://source.unsplash.com/random/300x180/?food,<?= urlencode($restaurant['cuisine_type']) ?>');"></div>
                        <div class="restaurant-info">
                            <h3 class="restaurant-name"><?= htmlspecialchars($restaurant['name']) ?></h3>
                            <p class="restaurant-cuisine"><?= htmlspecialchars($restaurant['cuisine_type']) ?></p>
                            <div class="restaurant-meta">
                                <div class="restaurant-rating">
                                    <i class="fas fa-star"></i>
                                    <?= number_format($restaurant['rating'], 1) ?>
                                </div>
                                <div class="restaurant-delivery">
                                    <i class="fas fa-clock"></i>
                                    <?= htmlspecialchars($restaurant['delivery_time']) ?>
                                </div>
                            </div>
                            <div style="margin-top: 15px; text-align: center;">
                                <a href="restaurant_details.php?id=<?= $restaurant['id'] ?>" class="btn btn-outline" style="width: 100%;">View Menu</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container footer-container">
            <div class="footer-section">
                <h3>FoodPanda</h3>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>For Customers</h3>
                <ul>
                    <li><a href="#">Code of Conduct</a></li>
                    <li><a href="#">Community</a></li>
                    <li><a href="#">Blogger Help</a></li>
                    <li><a href="#">Mobile Apps</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>For Restaurants</h3>
                <ul>
                    <li><a href="#">Add Restaurant</a></li>
                    <li><a href="#">Business Blog</a></li>
                    <li><a href="#">Restaurant Widgets</a></li>
                    <li><a href="#">Products for Businesses</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Countries</h3>
                <ul>
                    <li><a href="#">India</a></li>
                    <li><a href="#">Australia</a></li>
                    <li><a href="#">USA</a></li>
                    <li><a href="#">United Kingdom</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom container">
            <p>&copy; <?= date('Y') ?> FoodPanda Clone. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // JavaScript for redirection
        function goToRestaurant(id) {
            window.location.href = 'restaurant_details.php?id=' + id;
        }
    </script>
</body>
</html>
