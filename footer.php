<?php
// footer.php
if (!defined('FOOTER_INCLUDED')) {
    define('FOOTER_INCLUDED', true);
    // โหลด config.php (เส้นทางปรับตามจริง)
    if (file_exists(__DIR__ . '/config.php')) {
        $config = require __DIR__ . '/config.php';
    } else {
        // กำหนดค่า default กรณีไม่พบไฟล์ config
        $config = [
            'app_name' => 'ชื่อระบบ',
            'version' => '1.0.0',
            'dateversion' => date('Y-m-d'),
        ];
    }

    // เริ่ม session เพื่อดึง username (ถ้ายังไม่ start)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $username = isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : 'User';
    $today = date('Y-m-d');
}
?>
<footer style="
    position: fixed;
    bottom: 20px;
    right: 20px;
    font-size: 0.9rem;
    color: #6b7280;
    font-family: 'Prompt', sans-serif;
    background: rgba(255, 255, 255, 0.85);
    padding: 6px 12px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    z-index: 1000;
    text-align: right;
    min-width: 250px;
    max-width: 80vw;
">

<footer class="fixed-top-right">
  🧭 <strong><?= htmlspecialchars($config['app_name']) ?></strong> |
  👤 <?= htmlspecialchars($username) ?> |
  🛠️ Version <?= htmlspecialchars($config['version']) ?> | DateVersion <?= htmlspecialchars($config['dateversion']) ?> |
  💡 PHP <?= phpversion(); ?> |
  📅 <?= $today ?>
</footer>
