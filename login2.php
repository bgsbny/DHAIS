<?php
include('mycon.php');

session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login-btn'])) {
    $username = mysqli_real_escape_string($mysqli, trim($_POST['username']));
    $password = $_POST['password'];
    
    // Get user from database including role
    $query = "SELECT user_id, username, password, role FROM tbl_users WHERE username = '$username'";
    $result = mysqli_query($mysqli, $query);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        // Login successful
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
            
        header("Location: dashboard.php");
        exit();
    } else {
        $show_error = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- bootstrap -->
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <!-- for the icons -->
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">
    

    <!-- Local jQuery files -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/jquery-ui.css">
    <script src="js/jquery-ui.min.js"></script>

    <link rel="stylesheet" href="css/interfont.css">
    
    <link rel="stylesheet" href="css/style.css">

    <style>
        .login-page {
            background: linear-gradient(135deg, #0F1939 0%, #253A82 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
            text-align: center;
        }
        
        .login-logo {
            width: 120px;
            height: auto;
            margin-bottom: 2rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        
        .login-title {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: #64748b;
            margin-bottom: 2rem;
        }
        
        .input-container {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background: #f8fafc;
            width: 100%;
        }
        
        .input-container:focus-within {
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .input-container input {
            border: none;
            background: transparent;
            width: calc(100% - 30px);
            font-size: 1rem;
            color: #1e293b;
        }
        
        .input-container input::placeholder {
            color: #94a3b8;
        }
        
        .input-container i {
            color: #64748b;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .login-btn {
            background: linear-gradient(135deg, #0F1939 0%, #253A82 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }
        
        .login-btn:hover {
            background: linear-gradient(135deg, #516AC8 0%, #516AC8 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .invalid-text {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .forgot-password-link:hover {
            color: #2563eb !important;
        }
    </style>

    <title>Login Page</title>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <img src="images/dh-logo.png" alt="DH AUTOCARE" class="login-logo">
            <h3 class="login-title">Welcome to DH Autocare!</h3>
            <h6 class="login-subtitle">Please enter your credentials.</h6>
            
            <form action="" method='POST'>
                <div class='input-container d-flex align-items-center gap-2 mb-3'>
                    <input type="text" name="username" id="username" placeholder='Username' required>
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class='input-container d-flex align-items-center gap-2 mb-3'>
                    <input type="password" name="password" id="password" placeholder='Password' required>
                    <i class="fa-solid fa-lock"></i>
                </div>
                
                <?php if (isset($show_error) && $show_error): ?>
                    <div class="invalid-text">Invalid Username and Password!</div>
                <?php endif; ?>
                
                <button class='login-btn' name='login-btn' type='submit'>Log In</button>

                <div class="mt-2">
                    <a href="forgot-password.php" class="forgot-password-link" style="color: #64748b; text-decoration: none; font-size: 0.875rem; transition: color 0.3s ease;">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>