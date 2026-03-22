<?php
include('mycon.php');

session_start();

$message = '';
$message_type = '';
$stage = 'start';
$security_questions = [];

// Helper: normalize security answer for hashing/verification
function normalize_answer($answer) {
    return trim(mb_strtolower($answer));
}

// Step 1: Handle identifier submission from forgot-password.php (username only for local flow)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identifier']) && !isset($_POST['answers'])) {
    $identifier = mysqli_real_escape_string($mysqli, trim($_POST['identifier']));

    // Look up user by username and load 3 security Q/As
    $query = "SELECT user_id,
                     security_question1, security_answer_hash1,
                     security_question2, security_answer_hash2,
                     security_question3, security_answer_hash3
              FROM tbl_users
              WHERE username = '$identifier'
              LIMIT 1";
    $result = mysqli_query($mysqli, $query);
    $user = $result ? mysqli_fetch_assoc($result) : null;

    if ($user
        && !empty($user['security_question1']) && !empty($user['security_answer_hash1'])
        && !empty($user['security_question2']) && !empty($user['security_answer_hash2'])
        && !empty($user['security_question3']) && !empty($user['security_answer_hash3'])) {
        // Store the user id temporarily for the challenge step
        $_SESSION['reset_user_id'] = (int)$user['user_id'];
        $stage = 'challenge';
        $security_questions = [
            ['q' => $user['security_question1'], 'k' => 'a1'],
            ['q' => $user['security_question2'], 'k' => 'a2'],
            ['q' => $user['security_question3'], 'k' => 'a3'],
        ];
    } else {
        $message = 'We could not start a reset for this account. Ensure the username is correct and all 3 security questions are set up.';
        $message_type = 'error';
        $stage = 'start';
    }
}

// Step 2: Handle 3 security answers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answers'])) {
    if (!isset($_SESSION['reset_user_id'])) {
        $message = 'Your reset session expired. Please try again.';
        $message_type = 'error';
        $stage = 'start';
    } else {
        $answers = $_POST['answers'];
        $a1 = normalize_answer($answers['a1'] ?? '');
        $a2 = normalize_answer($answers['a2'] ?? '');
        $a3 = normalize_answer($answers['a3'] ?? '');
        $user_id = (int)$_SESSION['reset_user_id'];

        $query = "SELECT user_id,
                         security_question1, security_answer_hash1,
                         security_question2, security_answer_hash2,
                         security_question3, security_answer_hash3
                  FROM tbl_users
                  WHERE user_id = $user_id
                  LIMIT 1";
        $result = mysqli_query($mysqli, $query);
        $user = $result ? mysqli_fetch_assoc($result) : null;

        if ($user
            && password_verify($a1, $user['security_answer_hash1'])
            && password_verify($a2, $user['security_answer_hash2'])
            && password_verify($a3, $user['security_answer_hash3'])) {
            // Generate secure token and expiry (1 hour)
            $token = bin2hex(random_bytes(32));
            // Use DB time for expiry to avoid timezone mismatch
            $update = "UPDATE tbl_users SET reset_token = '$token', reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = $user_id";
            if (mysqli_query($mysqli, $update)) {
                // Clear session reference
                unset($_SESSION['reset_user_id']);
                $stage = 'done';
                $message = 'Security check passed! Use the link below to reset your password.';
                $message_type = 'success';
                $reset_link = 'reset-password.php?token=' . urlencode($token);
            } else {
                $message = 'An error occurred creating the reset link. Please try again.';
                $message_type = 'error';
                $stage = 'challenge';
                $security_questions = $user ? [
                    ['q' => $user['security_question1'], 'k' => 'a1'],
                    ['q' => $user['security_question2'], 'k' => 'a2'],
                    ['q' => $user['security_question3'], 'k' => 'a3'],
                ] : [];
            }
        } else {
            // Slow down brute force attempts slightly
            usleep(600000); // 600ms
            $message = 'Incorrect answers. Please try again.';
            $message_type = 'error';
            $stage = 'challenge';
            $security_questions = $user ? [
                ['q' => $user['security_question1'], 'k' => 'a1'],
                ['q' => $user['security_question2'], 'k' => 'a2'],
                ['q' => $user['security_question3'], 'k' => 'a3'],
            ] : [];
        }
    }
}

// If page accessed directly without POST, send back to forgot-password
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $stage === 'start') {
    header('Location: forgot-password.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">
    <script src="js/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/jquery-ui.css">
    <script src="js/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="css/interfont.css">
    <link rel="stylesheet" href="css/style.css">
    <title>Password Reset Verification</title>
    <style>
        .verify-page { background: linear-gradient(135deg, #0F1939 0%, #253A82 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .verify-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); padding: 3rem; max-width: 520px; width: 100%; text-align: center; }
        .verify-logo { width: 120px; height: auto; margin-bottom: 2rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
        .verify-title { color: #1e293b; font-weight: 700; margin-bottom: 0.5rem; }
        .verify-subtitle { color: #64748b; margin-bottom: 2rem; }
        .input-container { border: 2px solid #e2e8f0; border-radius: 12px; padding: 0.75rem 1rem; transition: all 0.3s ease; background: #f8fafc; width: 100%; }
        .input-container:focus-within { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .input-container input { border: none; background: transparent; width: calc(100% - 30px); font-size: 1rem; color: #1e293b; }
        .message { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; }
        .message.success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .message.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .action-btn { background: linear-gradient(135deg, #0F1939 0%, #253A82 100%); border: none; border-radius: 12px; padding: 0.875rem 2rem; font-weight: 600; font-size: 1rem; color: #fff; transition: all 0.3s ease; width: 100%; margin-top: 1rem; }
        .action-btn:hover { background: linear-gradient(135deg, #516AC8 0%, #516AC8 100%); transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .back-link { color: #64748b; text-decoration: none; font-size: 0.875rem; transition: color 0.3s ease; }
        .back-link:hover { color: #2563eb; }
        .reset-link { word-break: break-all; }
    </style>
</head>
<body>
    <div class="verify-page">
        <div class="verify-card">
            <img src="images/dh-logo.png" alt="DH AUTOCARE" class="verify-logo">
            <h3 class="verify-title">Verify Your Identity</h3>
            <h6 class="verify-subtitle">Answer all 3 security questions to continue.</h6>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($stage === 'challenge'): ?>
                <form action="" method="POST">
                    <?php foreach ($security_questions as $sq): ?>
                        <div class="mb-2 text-start" style="color:#334155; font-weight:600;">Security Question</div>
                        <div class="input-container d-flex align-items-center gap-2 mb-3">
                            <input type="text" value="<?php echo htmlspecialchars($sq['q']); ?>" readonly>
                            <i class="fa-solid fa-circle-question"></i>
                        </div>

                        <div class="mb-2 text-start" style="color:#334155; font-weight:600;">Your Answer</div>
                        <div class="input-container d-flex align-items-center gap-2 mb-3">
                            <input type="text" name="answers[<?php echo htmlspecialchars($sq['k']); ?>]" placeholder="Type your answer" required>
                            <i class="fa-solid fa-key"></i>
                        </div>
                    <?php endforeach; ?>

                    <button class="action-btn" type="submit">Verify and Continue</button>
                </form>
            <?php elseif ($stage === 'done' && isset($reset_link)): ?>
                <div class="mb-3" style="color:#334155;">Click the link below to reset your password. This link expires in 1 hour.</div>
                <a class="reset-link" href="<?php echo htmlspecialchars($reset_link); ?>"><?php echo htmlspecialchars($reset_link); ?></a>
                <div class="mt-3"><a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Login</a></div>
            <?php else: ?>
                <div class="mt-3"><a href="forgot-password.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back</a></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


