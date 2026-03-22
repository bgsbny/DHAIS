<?php
include('mycon.php');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = '';
$current_question = '';
$current_email = '';

// Helper: normalize answer for hashing
function normalize_answer($answer) {
    return trim(mb_strtolower($answer));
}

// Load current security question and email
$res = mysqli_query($mysqli, "SELECT security_question, email FROM tbl_users WHERE user_id = $user_id");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    if ($row) { 
        $current_question = $row['security_question'] ?? ''; 
        $current_email = $row['email'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($mysqli, trim($_POST['email'] ?? ''));
    $question = mysqli_real_escape_string($mysqli, trim($_POST['security_question'] ?? ''));
    $answer_raw = trim($_POST['security_answer'] ?? '');

    if ($email === '' || $question === '' || $answer_raw === '') {
        $message = 'Email, question and answer are all required.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } elseif (mb_strlen($question) < 8 || mb_strlen($answer_raw) < 3) {
        $message = 'Please provide a meaningful question (min 8 chars) and answer (min 3 chars).';
        $message_type = 'error';
    } else {
        $answer_norm = normalize_answer($answer_raw);
        $answer_hash = password_hash($answer_norm, PASSWORD_DEFAULT);
        $update = "UPDATE tbl_users SET email = '$email', security_question = '$question', security_answer_hash = '$answer_hash' WHERE user_id = $user_id";
        if (mysqli_query($mysqli, $update)) {
            $message = 'Security info updated successfully.';
            $message_type = 'success';
            $current_email = $email;
            $current_question = $question;
        } else {
            $message = 'Failed to update, please try again.';
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
    <title>Account Security</title>
    <style>
        .security-page { background: linear-gradient(135deg, #0F1939 0%, #253A82 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .security-card { background: rgba(255,255,255,0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); padding: 3rem; max-width: 600px; width: 100%; text-align: center; }
        .security-title { color: #1e293b; font-weight: 700; margin-bottom: 0.5rem; }
        .security-subtitle { color: #64748b; margin-bottom: 2rem; }
        .input-container { border: 2px solid #e2e8f0; border-radius: 12px; padding: 0.75rem 1rem; transition: all 0.3s ease; background: #f8fafc; width: 100%; }
        .input-container:focus-within { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .input-container input, .input-container textarea { border: none; background: transparent; width: calc(100% - 30px); font-size: 1rem; color: #1e293b; }
        .message { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; }
        .message.success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .message.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .save-btn { background: linear-gradient(135deg, #0F1939 0%, #253A82 100%); border: none; border-radius: 12px; padding: 0.875rem 2rem; font-weight: 600; font-size: 1rem; color: #fff; transition: all 0.3s ease; width: 100%; margin-top: 1rem; }
        .save-btn:hover { background: linear-gradient(135deg, #516AC8 0%, #516AC8 100%); transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .back-link { color: #64748b; text-decoration: none; font-size: 0.875rem; transition: color 0.3s ease; }
        .back-link:hover { color: #2563eb; }
    </style>
</head>
<body>
    <div class="security-page">
        <div class="security-card">
            <h3 class="security-title">Security Question</h3>
            <h6 class="security-subtitle">Set or update your security question and answer.</h6>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-2 text-start" style="color:#334155; font-weight:600;">Question</div>
                <div class="input-container d-flex align-items-center gap-2 mb-3">
                    <input type="text" name="security_question" id="security_question" placeholder="e.g. What is the name of your first pet?" value="<?php echo htmlspecialchars($current_question); ?>" required>
                </div>

                <div class="mb-2 text-start" style="color:#334155; font-weight:600;">Answer</div>
                <div class="input-container d-flex align-items-center gap-2 mb-3">
                    <input type="text" name="security_answer" id="security_answer" placeholder="Type your answer" required>
                </div>

                <button class="save-btn" type="submit">Save</button>
            </form>

            <div class="mt-3"><a href="dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a></div>
        </div>
    </div>
</body>
</html>


