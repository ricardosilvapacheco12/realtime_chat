<?php
session_start();
require '../../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../chat/index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$firstname || !$lastname || !$email || !$password || !$confirmPassword) {
        $errors[] = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare('INSERT INTO users (firstname, lastname, email, password) VALUES (?, ?, ?, ?)');
            $insert->bind_param('ssss', $firstname, $lastname, $email, $hash);
            if ($insert->execute()) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $insert->insert_id;
                $_SESSION['user_name'] = "$firstname $lastname";
                header('Location: ../chat/index.php');
                exit;
            } else {
                $errors[] = 'Unable to create account. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Realtime Chat</title>
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>

<body>
    <div class="page-wrapper">
        <form class="form" action="" method="post">
            <p class="title">Create account</p>
            <p class="message">Register and start chatting about programming with other developers.</p>

            <?php if ($errors): ?>
                <div class="alert error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
            <?php endif; ?>

            <div class="flex">
                <label>
                    <input class="input" name="firstname" type="text" placeholder=" " value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>" required>
                    <span>Firstname</span>
                </label>

                <label>
                    <input class="input" name="lastname" type="text" placeholder=" " value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>" required>
                    <span>Lastname</span>
                </label>
            </div>

            <label>
                <input class="input" name="email" type="email" placeholder=" " value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <span>Email</span>
            </label>

            <label>
                <input class="input" name="password" type="password" placeholder=" " required>
                <span>Password</span>
            </label>
            <label>
                <input class="input" name="confirm_password" type="password" placeholder=" " required>
                <span>Confirm password</span>
            </label>
            <button class="submit" type="submit">Register</button>
            <p class="signin">Already have an account? <a href="login.php">Sign in</a></p>
        </form>
    </div>
</body>

</html>