<?php
include('mycon.php');

session_start();
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
        .forgot-page {
            background: linear-gradient(135deg, #0F1939 0%, #253A82 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .forgot-card {
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
        
        .forgot-logo {
            width: 120px;
            height: auto;
            margin-bottom: 2rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        
        .forgot-title {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .forgot-subtitle {
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
        
        .reset-btn {
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
        
        .reset-btn:hover {
            background: linear-gradient(135deg, #516AC8 0%, #516AC8 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .back-link {
            color: #64748b;
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #2563eb;
        }
        
        .message {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .message.success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .message.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .message.info {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
    </style>

    <title>Forgot Password</title>
</head>
<body>
    <div class="forgot-page">
        <div class="forgot-card">
            <img src="images/dh-logo.png" alt="DH AUTOCARE" class="forgot-logo">
            <h3 class="forgot-title">Forgot Password?</h3>
            <h6 class="forgot-subtitle">Enter your username to reset your password.</h6>
            
                <form action="send_password_reset.php" method='POST'>
                    <div class='input-container d-flex align-items-center gap-2 mb-3'>
                        <input type="text" name="identifier" id="identifier" placeholder='Username' required>
                        <i class="fa-solid fa-user"></i>
                    </div>
                    
                    <button class='reset-btn' name='reset-btn' type='submit'>Continue</button>
                </form>

            <div class="mt-3">
                <a href="login.php" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html> 