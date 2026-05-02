<?php declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($pageTitle ?? 'Dashboard') . ' | ' . app_config('app_name', "Annie's Massages Admin")) ?></title>
    <?php require __DIR__ . '/css.php'; ?>
</head>
<body>
