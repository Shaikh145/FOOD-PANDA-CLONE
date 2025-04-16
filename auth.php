<?php
session_start();
require_once 'db.php';

/**
 * Register a new user
 * @param string $name - User's full name
 * @param string $email - User's email
 * @param string $password - User's password
 * @param string $phone - User's phone number
 * @param string $address - User's address
 * @param int $role - User's role (1 = Customer, 2 = Restaurant Owner)
 * @return array - Result with status and message
 */
function registerUser($name, $email, $password, $phone, $address, $role = 1) {
    global $conn;
    
    // Sanitize inputs
    $name = sanitize($name);
    $email = sanitize($email);
    $phone = sanitize($phone);
    $address = sanitize($address);
    $role = (int)$role;
    
    // Check if email already exists
    $checkEmail = getRow("SELECT id FROM users WHERE email = '$email'");
    if ($checkEmail) {
        return ['status' => false, 'message' => 'Email already registered'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user data
    $userData = [
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'phone' => $phone,
        'address' => $address,
        'role' => $role,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $userId = insertData('users', $userData);
    
    if ($userId) {
        return ['status' => true, 'message' => 'Registration successful', 'user_id' => $userId];
    } else {
        return ['status' => false, 'message' => 'Registration failed, please try again'];
    }
}

/**
 * Login a user
 * @param string $email - User's email
 * @param string $password - User's password
 * @return array - Result with status and message
 */
function loginUser($email, $password) {
    global $conn;
    
    // Sanitize inputs
    $email = sanitize($email);
    
    // Get user data
    $user = getRow("SELECT * FROM users WHERE email = '$email'");
    
    if (!$user) {
        return ['status' => false, 'message' => 'Email not registered'];
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        
        // Update last login
        updateData('users', ['last_login' => date('Y-m-d H:i:s')], "id = {$user['id']}");
        
        return [
            'status' => true, 
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
    } else {
        return ['status' => false, 'message' => 'Invalid password'];
    }
}

/**
 * Check if user is logged in
 * @return bool - True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is a restaurant owner
 * @return bool - True if restaurant owner, false otherwise
 */
function isRestaurantOwner() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 2;
}

/**
 * Get current user data
 * @return array|bool - User data or false
 */
function getCurrentUser() {
    if (!isLoggedIn()) return false;
    
    $userId = $_SESSION['user_id'];
    return getRow("SELECT * FROM users WHERE id = $userId");
}

/**
 * Get restaurant information for the logged-in restaurant owner
 * @return array|bool - Restaurant data or false
 */
function getOwnerRestaurant() {
    if (!isRestaurantOwner()) return false;
    
    $userId = $_SESSION['user_id'];
    return getRow("SELECT * FROM restaurants WHERE owner_id = $userId");
}

/**
 * Register a new restaurant (for restaurant owners)
 * @param int $ownerId - Owner's user ID
 * @param string $name - Restaurant name
 * @param string $description - Restaurant description
 * @param string $address - Restaurant address
 * @param string $phone - Restaurant phone
 * @param string $cuisineType - Type of cuisine
 * @param string $openingHours - Opening hours
 * @return array - Result with status and message
 */
function registerRestaurant($ownerId, $name, $description, $address, $phone, $cuisineType, $openingHours) {
    // Sanitize inputs
    $ownerId = (int)$ownerId;
    $name = sanitize($name);
    $description = sanitize($description);
    $address = sanitize($address);
    $phone = sanitize($phone);
    $cuisineType = sanitize($cuisineType);
    $openingHours = sanitize($openingHours);
    
    // Check if owner already has a restaurant
    $existingRestaurant = getRow("SELECT id FROM restaurants WHERE owner_id = $ownerId");
    if ($existingRestaurant) {
        return ['status' => false, 'message' => 'You already have a registered restaurant'];
    }
    
    // Insert restaurant data
    $restaurantData = [
        'owner_id' => $ownerId,
        'name' => $name,
        'description' => $description,
        'address' => $address,
        'phone' => $phone,
        'cuisine_type' => $cuisineType,
        'opening_hours' => $openingHours,
        'rating' => 0,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $restaurantId = insertData('restaurants', $restaurantData);
    
    if ($restaurantId) {
        return [
            'status' => true, 
            'message' => 'Restaurant registered successfully', 
            'restaurant_id' => $restaurantId
        ];
    } else {
        return ['status' => false, 'message' => 'Registration failed, please try again'];
    }
}

/**
 * Redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Redirect to dashboard if not a restaurant owner
 */
function requireRestaurantOwner() {
    requireLogin();
    if (!isRestaurantOwner()) {
        header("Location: dashboard.php");
        exit;
    }
}
?>
