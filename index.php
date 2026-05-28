<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: pages/chat/index.php');
} else {
    header('Location: pages/auth/login.php');
}
exit;
