<?php
include('mycon.php');

session_start();

$message = '';
$message_type = '';
$valid_token = false;
$user_id = null;
// Keep token across form submit
$current_token = null;

// Check if token is provided
// Accept token from GET (initial) or POST (form submit)
if ((isset($_GET['token']) && !empty($_GET['token'])) || (isset($_POST['token']) && !empty($_POST['token']))) {
    $current_token = isset($_POST['token']) ? $_POST['token'] : $_GET['token'];
    $token = mysqli_real_escape_string($mysqli, $current_token);
    
    // Check if token is valid and not expired
    $query = "SELECT user_id, username FROM tbl_users WHERE reset_token = '$token' AND reset_token_expiry > NOW()";
    $result = mysqli_query($mysqli, $query);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        $valid_token = true;
        $user_id = $user['user_id'];
    } else {
        $message = "Invalid or expired reset link. Please request a new password reset.";
        $message_type = 'error';
    }
} else {
    $message = "Invalid reset link. Please request a new password reset.";
    $message_type = 'error';
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new-password-btn']) && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $message_type = 'error';
    } elseif (
        !preg_match('/[A-Z]/', $new_password) ||
        !preg_match('/[a-z]/', $new_password) ||
        (!preg_match('/\d/', $new_password) && !preg_match('/[^A-Za-z0-9]/', $new_password))
    ) {
        $message = "Password must include uppercase, lowercase, and at least one number or special character.";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = 'error';
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $update_query = "UPDATE tbl_users SET password = '$hashed_password', reset_token = NULL, reset_token_expiry = NULL WHERE user_id = $user_id";
        
        if (mysqli_query($mysqli, $update_query)) {
            $message = "Password reset successfully! You can now login with your new password.";
            $message_type = 'success';
            $valid_token = false; // Hide the form after successful reset
        } else {
            $message = "An error occurred. Please try again.";
            $message_type = 'error';
        }
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
        .reset-page {
            background: linear-gradient(135deg, #0F1939 0%, #253A82 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .reset-card {
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
        
        .reset-logo {
            width: 120px;
            height: auto;
            margin-bottom: 2rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        
        .reset-title {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .reset-subtitle {
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
    </style>

    <title>Reset Password</title>
</head>
<body>
    <div class="reset-page">
        <div class="reset-card">
            <img src="images/dh-logo.png" alt="DH AUTOCARE" class="reset-logo">
            <h3 class="reset-title">Reset Password</h3>
            <h6 class="reset-subtitle">Enter your new password below.</h6>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($valid_token): ?>
                <form action="" method='POST'>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($current_token); ?>">
                    <div class='input-container d-flex align-items-center gap-2 mb-3'>
                        <input type="password" name="new_password" id="new_password" placeholder='New Password' required>
                        <button type="button" id="toggle_new" class="btn btn-sm btn-link">Show</button>
                        <i class="fa-solid fa-lock"></i>
                    </div>

                    <div class='input-container d-flex align-items-center gap-2 mb-1'>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder='Confirm New Password' required>
                        <button type="button" id="toggle_confirm" class="btn btn-sm btn-link">Show</button>
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div id="pw_strength" class="text-start mt-2" style="font-size:0.85rem; color:#64748b;">Strength: -</div>
                    <div class="text-start" style="font-size:0.8rem; color:#475569;">
                        Requirements: at least 8 chars, include uppercase, lowercase, and at least one number or special character.
                    </div>
                    
                    <button class='reset-btn' name='new-password-btn' type='submit'>Reset Password</button>
                </form>
            <?php endif; ?>

            <div class="mt-3">
                <a href="login.php" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html> 
<script>
// Password show/hide
document.getElementById('toggle_new')?.addEventListener('click', function(){
  const f = document.getElementById('new_password');
  if (!f) return;
  f.type = (f.type === 'password') ? 'text' : 'password';
  this.textContent = (f.type === 'password') ? 'Show' : 'Hide';
});
document.getElementById('toggle_confirm')?.addEventListener('click', function(){
  const f = document.getElementById('confirm_password');
  if (!f) return;
  f.type = (f.type === 'password') ? 'text' : 'password';
  this.textContent = (f.type === 'password') ? 'Show' : 'Hide';
});

// Strength meter
function evaluateStrength(pw){
  let score = 0;
  if (pw.length >= 8) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[a-z]/.test(pw)) score++;
  if (/\d/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const labels = ['Very Weak','Weak','Fair','Good','Strong','Very Strong'];
  return labels[score];
}
const pw = document.getElementById('new_password');
const meter = document.getElementById('pw_strength');
pw?.addEventListener('input', function(){
  if (!meter) return;
  meter.textContent = 'Strength: ' + evaluateStrength(this.value);
});
</script>