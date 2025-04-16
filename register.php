<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'role' => '1'
];

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'name' => isset($_POST['name']) ? trim($_POST['name']) : '',
        'email' => isset($_POST['email']) ? trim($_POST['email']) : '',
        'phone' => isset($_POST['phone']) ? trim($_POST['phone']) : '',
        'address' => isset($_POST['address']) ? trim($_POST['address']) : '',
        'role' => isset($_POST['role']) ? trim($_POST['role']) : '1'
    ];
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate input
    if (empty($formData['name'])) {
        $errors[] = "Full name is required";
    }
    
    if (empty($formData['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($formData['phone'])) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($formData['address'])) {
        $errors[] = "Address is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // If no validation errors, register the user
    if (empty($errors)) {
        $result = registerUser(
            $formData['name'],
            $formData['email'],
            $password,
            $formData['phone'],
            $formData['address'],
            $formData['role']
        );
        
        if ($result['status']) {
            // Set success message in session and redirect to login
            $_SESSION['registration_success'] = true;
            header("Location: login.php");
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FoodPanda Clone</title>
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
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
        
        /* Main Content */
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
        }
        .register-container {
            width: 100%;
            max-width: 600px;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 30px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 10px;
        }
        .register-header p {
            color: #666;
            font-size: 14px;
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
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .radio-group {
            display: flex;
            gap: 20px;
            padding: 10px 0;
        }
        .radio-option {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .radio-option input {
            margin-right: 8px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            border: none;
            transition: background-color 0.3s ease;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: #b30d55;
        }
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
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 15px 0;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">FoodPanda</a>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="register-container">
                <div class="register-header">
                    <h1>Create Your Account</h1>
                    <p>Join FoodPanda and start ordering your favorite food</p>
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
                
                <form action="register.php" method="POST">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($formData['name']) ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($formData['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($formData['phone']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3" required><?= htmlspecialchars($formData['address']) ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Register as</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="role" value="1" <?= $formData['role'] == '1' ? 'checked' : '' ?>> Customer
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="role" value="2" <?= $formData['role'] == '2' ? 'checked' : '' ?>> Restaurant Owner
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Sign in</a>
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
        // JavaScript for form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const address = document.getElementById('address').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            let error = '';
            
            if (!name) error += 'Full name is required\n';
            if (!email) error += 'Email is required\n';
            if (!phone) error += 'Phone number is required\n';
            if (!address) error += 'Address is required\n';
            if (!password) error += 'Password is required\n';
            else if (password.length < 6) error += 'Password must be at least 6 characters\n';
            if (password !== confirmPassword) error += 'Passwords do not match\n';
            
            if (error) {
                e.preventDefault();
                alert(error);
            }
        });
    </script>
</body>
</html>
