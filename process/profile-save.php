<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../models/Profile.php';

$currentUser = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/profile/index.php');
}

$payload = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'title' => trim((string) ($_POST['title'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'timezone' => trim((string) ($_POST['timezone'] ?? '')),
    'bio' => trim((string) ($_POST['bio'] ?? '')),
    'daily_brief' => isset($_POST['daily_brief']) ? '1' : '0',
    'payment_alerts' => isset($_POST['payment_alerts']) ? '1' : '0',
    'inventory_alerts' => isset($_POST['inventory_alerts']) ? '1' : '0',
    'marketing_updates' => isset($_POST['marketing_updates']) ? '1' : '0',
];

$errors = Profile::validate($payload);

if ($errors !== []) {
    flash_set('profile_errors', $errors);
    remember_old_input($payload);
    redirect_to('/profile/index.php');
}

Profile::save($currentUser, $payload);
clear_old_input();
flash_set('profile_success', 'Profile updated successfully.');

redirect_to('/profile/index.php');
