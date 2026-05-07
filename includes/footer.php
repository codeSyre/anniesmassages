<?php declare(strict_types=1);
?>
<dialog class="confirm-dialog" data-confirm-dialog>
    <div class="confirm-dialog-panel">
        <div class="confirm-dialog-copy">
            <p class="confirm-dialog-kicker">Please confirm</p>
            <h3 class="confirm-dialog-title" data-confirm-dialog-title>Delete item?</h3>
            <p class="confirm-dialog-message" data-confirm-dialog-message>This action cannot be undone.</p>
        </div>
        <div class="confirm-dialog-actions">
            <button class="topbar-link" type="button" data-confirm-dialog-cancel>Cancel</button>
            <button class="button-danger" type="button" data-confirm-dialog-submit>Delete</button>
        </div>
    </div>
</dialog>
<footer class="page-footer">
    <p>Built for calm daily operations across bookings, revenue, inventory, and staff.</p>
</footer>
<?php require __DIR__ . '/js.php'; ?>
</body>
</html>
