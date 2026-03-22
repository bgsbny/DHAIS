<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Redirect if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$users = $mysqli->query("SELECT * FROM tbl_users");

// Helper: normalize answer for hashing
function normalize_answer($answer) {
    return trim(mb_strtolower($answer));
}

// Handle form submission for updating account credentials
// Handle credentials update form
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['user_id'])
    && isset($_POST['form_type'])
    && $_POST['form_type'] === 'credentials'
) {
    $user_id = (int)$_POST['user_id'];
    $username = trim($_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    // Validate required fields
    if (empty($username) || empty($current_password)) {
        $_SESSION['error'] = "Username and current password are required.";
        header('Location: account_management.php');
        exit();
    }
    
    // Get the user's current data
    $stmt = $mysqli->prepare("SELECT username, password FROM tbl_users WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header('Location: account_management.php');
        exit();
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['error'] = "Current password is incorrect.";
        header('Location: account_management.php');
        exit();
    }
    
    // Check if username already exists (excluding current user)
    if ($username !== $user['username']) {
        $stmt = $mysqli->prepare("SELECT user_id FROM tbl_users WHERE username = ? AND user_id != ?");
        $stmt->bind_param('si', $username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $_SESSION['error'] = "Username already exists.";
            header('Location: account_management.php');
            exit();
        }
        $stmt->close();
    }
    
    // Update user data
    if (!empty($new_password)) {
        // Enforce strong password rules
        if (
            strlen($new_password) < 8 ||
            !preg_match('/[A-Z]/', $new_password) ||
            !preg_match('/[a-z]/', $new_password) ||
            (!preg_match('/\\d/', $new_password) && !preg_match('/[^A-Za-z0-9]/', $new_password))
        ) {
            $_SESSION['error'] = "Password must be at least 8 characters and include uppercase, lowercase, and at least one number or special character.";
            header('Location: account_management.php');
            exit();
        }
        // Update username and password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE tbl_users SET username = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param('ssi', $username, $hashed_password, $user_id);
    } else {
        // Update username only
        $stmt = $mysqli->prepare("UPDATE tbl_users SET username = ? WHERE user_id = ?");
        $stmt->bind_param('si', $username, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Account credentials updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating account credentials.";
    }
    
    $stmt->close();
    header('Location: account_management.php');
    exit();
}

// Handle security info update form (email + security Q/A)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['user_id'])
    && isset($_POST['form_type'])
    && $_POST['form_type'] === 'security'
) {
    $user_id = (int)$_POST['user_id'];
    $email = trim($_POST['email'] ?? '');
    $security_question = trim($_POST['security_question'] ?? '');
    $security_answer_raw = trim($_POST['security_answer'] ?? '');

    // Check if we're using the new 3-question format
    if (isset($_POST['security_question1'])) {
        $q1 = trim($_POST['security_question1'] ?? '');
        $q2 = trim($_POST['security_question2'] ?? '');
        $q3 = trim($_POST['security_question3'] ?? '');
        $a1_raw = trim($_POST['security_answer1'] ?? '');
        $a2_raw = trim($_POST['security_answer2'] ?? '');
        $a3_raw = trim($_POST['security_answer3'] ?? '');

        if ($email === '' || $q1 === '' || $q2 === '' || $q3 === '' || $a1_raw === '' || $a2_raw === '' || $a3_raw === '') {
            $_SESSION['error'] = "Email and all 3 security questions and answers are required.";
            header('Location: account_management.php');
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Please enter a valid email address.";
            header('Location: account_management.php');
            exit();
        }
        if (mb_strlen($q1) < 8 || mb_strlen($q2) < 8 || mb_strlen($q3) < 8 || mb_strlen($a1_raw) < 3 || mb_strlen($a2_raw) < 3 || mb_strlen($a3_raw) < 3) {
            $_SESSION['error'] = "Each question must be at least 8 characters and each answer at least 3 characters.";
            header('Location: account_management.php');
            exit();
        }

        // Check duplicate email for other users
        $dup = $mysqli->prepare("SELECT user_id FROM tbl_users WHERE email = ? AND user_id != ? LIMIT 1");
        if ($dup === false) {
            $_SESSION['error'] = "Database error: Email column may not exist. Please run the SQL commands to add required columns.";
            header('Location: account_management.php');
            exit();
        }
        $dup->bind_param('si', $email, $user_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $dup->free_result();
            $dup->close();
            $_SESSION['error'] = "Email is already in use by another account.";
            header('Location: account_management.php');
            exit();
        }
        $dup->free_result();
        $dup->close();

        // Hash all three answers
        $a1_hash = password_hash(normalize_answer($a1_raw), PASSWORD_DEFAULT);
        $a2_hash = password_hash(normalize_answer($a2_raw), PASSWORD_DEFAULT);
        $a3_hash = password_hash(normalize_answer($a3_raw), PASSWORD_DEFAULT);

        // Check if the new columns exist before trying to update
        $checkColumns = $mysqli->query("SHOW COLUMNS FROM tbl_users LIKE 'security_question1'");
        if ($checkColumns && $checkColumns->num_rows > 0) {
            $update = "UPDATE tbl_users SET email = ?, security_question1 = ?, security_answer_hash1 = ?, security_question2 = ?, security_answer_hash2 = ?, security_question3 = ?, security_answer_hash3 = ? WHERE user_id = ?";
            $stmt = $mysqli->prepare($update);
            if ($stmt) {
                $stmt->bind_param('sssssssi', $email, $q1, $a1_hash, $q2, $a2_hash, $q3, $a3_hash, $user_id);
            } else {
                $_SESSION['error'] = "Database columns not found. Please run the SQL commands to add required columns.";
                header('Location: account_management.php');
                exit();
            }
        } else {
            $_SESSION['error'] = "Database columns not found. Please run the SQL commands to add required columns.";
            header('Location: account_management.php');
            exit();
        }
    } else {
        // Fallback to old single question format
        if ($email === '' || $security_question === '' || $security_answer_raw === '') {
            $_SESSION['error'] = "Email, security question, and answer are required.";
            header('Location: account_management.php');
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Please enter a valid email address.";
            header('Location: account_management.php');
            exit();
        }
        if (mb_strlen($security_question) < 8 || mb_strlen($security_answer_raw) < 3) {
            $_SESSION['error'] = "Please provide a meaningful question (min 8 chars) and answer (min 3 chars).";
            header('Location: account_management.php');
            exit();
        }

        // Check duplicate email for other users
        $dup = $mysqli->prepare("SELECT user_id FROM tbl_users WHERE email = ? AND user_id != ? LIMIT 1");
        if ($dup === false) {
            $_SESSION['error'] = "Database error: Email column may not exist. Please run the SQL commands to add required columns.";
            header('Location: account_management.php');
            exit();
        }
        $dup->bind_param('si', $email, $user_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $dup->free_result();
            $dup->close();
            $_SESSION['error'] = "Email is already in use by another account.";
            header('Location: account_management.php');
            exit();
        }
        $dup->free_result();
        $dup->close();

        $answer_norm = normalize_answer($security_answer_raw);
        $answer_hash = password_hash($answer_norm, PASSWORD_DEFAULT);
        $update = "UPDATE tbl_users SET email = ?, security_question = ?, security_answer_hash = ? WHERE user_id = ?";
        $stmt = $mysqli->prepare($update);
        $stmt->bind_param('sssi', $email, $security_question, $answer_hash, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Security info updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating security info: " . $stmt->error;
    }
    $stmt->close();
    header('Location: account_management.php');
    exit();
}

// Refresh users list after potential updates
$users = $mysqli->query("SELECT * FROM tbl_users");
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

    <!-- Google Fonts -->
    <link rel="stylesheet" href="css/interfont.css">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php $activePage = 'account_management'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Account Management</h1>
                    <p class="page-subtitle">Manage user accounts, roles, and system permissions.</p>
                </div>
            </div>
        </header>

        <div class="main-container">
            <div class="history">
                <div class="table-responsive">
                    <h6 class="mb-3">Manage Account Credentials</h6>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td class="text-center">
                                      <div class="d-inline-flex gap-2 align-items-center">
                                        <button class='d-flex justify-content-center align-items-center btn btn-primary edit-btn' 
                                          data-bs-toggle='modal' 
                                          data-bs-target='#edit-credentials' 
                                          data-user-id='<?php echo htmlspecialchars($user['user_id']); ?>'
                                          data-role='<?php echo htmlspecialchars($user['role']); ?>'
                                          data-username='<?php echo htmlspecialchars($user['username']); ?>'>
                                          <i class='fa-solid fa-pen-to-square'></i>
                                        </button>
                                        <button class='d-flex justify-content-center align-items-center btn btn-secondary edit-security-btn' 
                                          data-bs-toggle='modal' 
                                          data-bs-target='#edit-security' 
                                          data-user-id='<?php echo htmlspecialchars($user['user_id']); ?>'
                                          data-email='<?php echo htmlspecialchars($user['email'] ?? ""); ?>'
                                          data-question='<?php echo htmlspecialchars($user['security_question'] ?? ""); ?>'>
                                          <i class='fa-solid fa-shield-halved'></i>
                                        </button>
                                      </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        </div>
    </main>

    <!-- EDIT ACCOUNT CREDENTIALS MODAL SECTION -->
    <div class='modal fade' id='edit-credentials'>
        <div class='modal-dialog modal-md modal-dialog-centered'>
            <div class='modal-content shadow rounded-4'>
                <div class='modal-header text-dark rounded-top-4 d-flex align-items-center'>
                    <h5 class='modal-title'>Edit Account Credentials</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>

                <div class='modal-body p-3'>
                    <form action="account_management.php" method='POST' id="edit-form">
                        <input type="hidden" name="user_id" id="edit-user-id">
                        <input type="hidden" name="form_type" value="credentials">
                        
                        <div class='row g-3'>
                            <div class='col-md-12'>
                                <label for="edit-role" class='form-label'>Role</label>
                                <input type="text" id="edit-role" class='form-control' readonly>
                            </div>

                            <div class='col-md-12'>
                                <label for="edit-username" class='form-label'>Username <span class='text-danger'>*</span></label>
                                <input type="text" name='username' id="edit-username" class='form-control' required>
                            </div>

                            <div class='col-md-12'>
                                <label for="edit-current-password" class='form-label'>Current Password <span class='text-danger'>*</span></label>
                                <div class="input-group">
                                    <input type="password" name='current_password' id="edit-current-password" class='form-control' placeholder='Enter your current password' required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggle-current-password">
                                        <i class="fa-solid fa-eye" id="current-password-icon"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">You must enter your current password to make changes.</small>
                            </div>

                            <div class='col-md-12'>
                                <label for="edit-new-password" class='form-label'>New Password (optional)</label>
                                <div class="input-group">
                                     <input type="password" name='new_password' id="edit-new-password" class='form-control' placeholder='Enter your new password'>
                                    <button class="btn btn-outline-secondary" type="button" id="toggle-new-password">
                                        <i class="fa-solid fa-eye" id="new-password-icon"></i>
                                    </button>
                                </div>
                                 <small class="form-text text-muted">Click the eye icon to show/hide password. Leave blank if you don't want to change your password.
                                     Requirements: at least 8 chars, include uppercase, lowercase, and at least one number or special character.</small>
                            </div>

                            <div class='d-grid mt-3'>
                                <button type="submit" class='btn btn-primary'>Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div> <!-- END OF EDIT CREDENTIALS MODAL -->

    <!-- EDIT SECURITY INFO MODAL SECTION -->
    <div class='modal fade' id='edit-security'>
        <div class='modal-dialog modal-md modal-dialog-centered'>
            <div class='modal-content shadow rounded-4'>
                <div class='modal-header text-dark rounded-top-4 d-flex align-items-center'>
                    <h5 class='modal-title'>Edit Security Info</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>

                <div class='modal-body p-3'>
                    <form action="account_management.php" method='POST' id="edit-security-form">
                        <input type="hidden" name="user_id" id="security-user-id">
                        <input type="hidden" name="form_type" value="security">

                        <div class='row g-3'>
                            <div class='col-md-12'>
                                <label for="security-email" class='form-label'>Email <span class='text-danger'>*</span></label>
                                <input type="email" name='email' id="security-email" class='form-control' placeholder='user@example.com' required>
                            </div>

                            <div class='col-md-12'>
                                <label class='form-label'>Security Questions (3)</label>
                                <input type="text" name='security_question1' id="security-question1" class='form-control mb-2' placeholder='Question 1' required>
                                <input type="text" name='security_question2' id="security-question2" class='form-control mb-2' placeholder='Question 2' required>
                                <input type="text" name='security_question3' id="security-question3" class='form-control' placeholder='Question 3' required>
                            </div>

                            <div class='col-md-12'>
                                <label class='form-label'>Answers</label>
                                <input type="text" name='security_answer1' id="security-answer1" class='form-control mb-2' placeholder='Answer 1' required>
                                <input type="text" name='security_answer2' id="security-answer2" class='form-control mb-2' placeholder='Answer 2' required>
                                <input type="text" name='security_answer3' id="security-answer3" class='form-control' placeholder='Answer 3' required>
                                <small class="form-text text-muted">Answers are stored securely and compared case/space-insensitive.</small>
                            </div>

                            <div class='d-grid mt-3'>
                                <button type="submit" class='btn btn-primary'>Save Security Info</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div> <!-- END OF EDIT SECURITY MODAL -->

    <!-- MESSAGE POPUP -->
    <div id="messagePopup"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show success/error messages if they exist
            <?php if (isset($_SESSION['success'])): ?>
                showMessagePopup('<?php echo addslashes($_SESSION['success']); ?>', true);
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                showMessagePopup('<?php echo addslashes($_SESSION['error']); ?>', false);
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            // Get all edit buttons
            const editButtons = document.querySelectorAll('.edit-btn');
            const editSecurityButtons = document.querySelectorAll('.edit-security-btn');
                
            // Add click event listener to each edit button
            editButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    // Get data attributes from the clicked button
                    const userId = this.getAttribute('data-user-id');
                    const role = this.getAttribute('data-role');
                    const username = this.getAttribute('data-username');
                    
                    // Populate the modal form fields
                    document.getElementById('edit-user-id').value = userId;
                    document.getElementById('edit-role').value = role;
                    document.getElementById('edit-username').value = username;
                        
                    // Clear the password fields
                    document.getElementById('edit-current-password').value = '';
                    document.getElementById('edit-new-password').value = '';
                });
            });

            // Populate security modal
            editSecurityButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const email = this.getAttribute('data-email') || '';

                    document.getElementById('security-user-id').value = userId;
                    document.getElementById('security-email').value = email;
                    // Always clear questions/answers to force explicit admin input
                    document.getElementById('security-question1').value = '';
                    document.getElementById('security-question2').value = '';
                    document.getElementById('security-question3').value = '';
                    document.getElementById('security-answer1').value = '';
                    document.getElementById('security-answer2').value = '';
                    document.getElementById('security-answer3').value = '';
                });
            });

            // Toggle password visibility for current password
            document.getElementById('toggle-current-password').addEventListener('click', function() {
                const passwordField = document.getElementById('edit-current-password');
                const passwordIcon = document.getElementById('current-password-icon');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    passwordIcon.classList.remove('fa-eye');
                    passwordIcon.classList.add('fa-eye-slash');
                } else {
                    passwordField.type = 'password';
                    passwordIcon.classList.remove('fa-eye-slash');
                    passwordIcon.classList.add('fa-eye');
                }
            });

            // Toggle password visibility for new password
            document.getElementById('toggle-new-password').addEventListener('click', function() {
                const passwordField = document.getElementById('edit-new-password');
                const passwordIcon = document.getElementById('new-password-icon');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    passwordIcon.classList.remove('fa-eye');
                    passwordIcon.classList.add('fa-eye-slash');
                } else {
                    passwordField.type = 'password';
                    passwordIcon.classList.remove('fa-eye-slash');
                    passwordIcon.classList.add('fa-eye');
                }
            });

            // Form validation
            document.getElementById('edit-form').addEventListener('submit', function(e) {
                const username = document.getElementById('edit-username').value.trim();
                const currentPassword = document.getElementById('edit-current-password').value;
                
                if (!username) {
                    e.preventDefault();
                    alert('Username is required.');
                    return;
                }
                
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Current password is required to make changes.');
                    return;
                }
            });

            // Security form validation
            document.getElementById('edit-security-form').addEventListener('submit', function(e) {
                const email = document.getElementById('security-email').value.trim();
                const q1 = document.getElementById('security-question1').value.trim();
                const q2 = document.getElementById('security-question2').value.trim();
                const q3 = document.getElementById('security-question3').value.trim();
                const a1 = document.getElementById('security-answer1').value.trim();
                const a2 = document.getElementById('security-answer2').value.trim();
                const a3 = document.getElementById('security-answer3').value.trim();

                if (!email || !q1 || !q2 || !q3 || !a1 || !a2 || !a3) {
                    e.preventDefault();
                    alert('Email, 3 security questions, and 3 answers are required.');
                    return;
                }
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address.');
                    return;
                }
                const questions = [q1, q2, q3];
                const answers = [a1, a2, a3];
                if (questions.some(q => q.length < 8) || answers.some(a => a.length < 3)) {
                    e.preventDefault();
                    alert('Each question must be at least 8 chars and each answer at least 3 chars.');
                    return;
                }
            });
        });

        // Message popup function (similar to POS system)
        function showMessagePopup(message, isSuccess = true) {
            const $popup = $("#messagePopup");
            $popup.stop(true, true); // Stop any current animations
            $popup.text(message);
            $popup.removeClass("popup-success popup-error");
            $popup.addClass(isSuccess ? "popup-success" : "popup-error");
            $popup.fadeIn(200).delay(1800).fadeOut(400);
        }
    </script>


</body>
</html> 