<?php if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) { exit; } ?>
    </main>
</div>
<script src="/assets/js/admin.js"></script>
<?php if (!empty($adminScripts)) foreach ($adminScripts as $s): ?>
<script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
