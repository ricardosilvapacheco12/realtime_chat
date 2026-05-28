<?php
session_start();
require '../../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../chat/index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $stmt = $conn->prepare('SELECT id, firstname, lastname, password FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $hash = $row['password'];
            if (password_verify($password, $hash)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['firstname'] . ' ' . $row['lastname'];
                header('Location: ../chat/index.php');
                exit;
            }
        }

        $errors[] = 'Invalid email or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Realtime Chat</title>
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>

<body>
    <div class="page-wrapper">
        <form class="form" action="" method="post">
            <p class="title">Welcome back</p>
            <p class="message">Login to continue and join the programming chat.</p>

            <?php if ($errors): ?>
                <div class="alert error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
            <?php endif; ?>

            <label>
                <input class="input" name="email" type="email" placeholder=" " value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <span>Email</span>
            </label>

            <label>
                <input class="input" name="password" type="password" placeholder=" " required>
                <span>Password</span>
            </label>

            <button class="submit" type="submit">Login</button>
            <p class="signin">Don’t have an account? <a href="register.php">Register</a></p>
        </form>
    </div>
</body>

</html>