<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if (!defined('BASE_URL')) {
	require_once __DIR__ . '/../config/constants.php';
}

?>
	</main>
	<footer style="text-align:center; padding:18px 0; color:#666;">
		<div style="max-width:1100px;margin:0 auto;padding:0 16px;">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></div>
	</footer>
	<script>
		// simple DOM helper for future scripts
		window.appBaseUrl = '<?php echo BASE_URL; ?>';
	</script>
</body>
</html>

<?php
