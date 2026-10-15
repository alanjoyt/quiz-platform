<?php
// Cheranalloor Faces — Logout Handler

require_once __DIR__ . '/auth_helper.php';
logoutUser();

session_start();
setFlashMessage("Logged out successfully.", "success");
header("Location: " . BASE_URI);
exit;
?>
