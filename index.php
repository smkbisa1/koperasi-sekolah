<?php
session_start();

if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header("Location: dashboard.php");
    exit();
} else {
    // User not logged in, redirect to login
    header("Location: login.php");
    exit();
}
