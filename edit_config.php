<?php
$savePath = sys_get_temp_dir();

// ตรวจสอบว่าโฟลเดอร์นี้มีจริง และเขียนได้หรือไม่
if (!is_dir($savePath) || !is_writable($savePath)) {
    // fallback ไปที่โฟลเดอร์ที่เราสร้างเอง
    $savePath = "C:/AppServ/tmp";
    if (!is_dir($savePath)) {
        mkdir($savePath, 0777, true);
    }
}

session_save_path($savePath);
session_start();

echo "Session path: " . session_save_path();
$configFile = 'config.php';
$config = include $configFile;

// ฟิลด์ที่ไม่แก้ไขและไม่แสดงในฟอร์ม
$excludedFields = ['nhso_user', 'nhso_password', 'app_name', 'version', 'dateversion'];

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        $value = trim($value);
        if ($key === 'db_port' && !ctype_digit($value)) {
            $errors[] = "db_port ต้องเป็นตัวเลขเท่านั้น";
        }
    }

    if (empty($errors)) {
        $newConfig = "<?php\nreturn array(\n";
        foreach ($config as $key => $oldValue) {
            if (in_array($key, $excludedFields)) {
                $val = addslashes($oldValue);
            } else {
                $val = isset($_POST[$key]) ? addslashes(trim($_POST[$key])) : addslashes($oldValue);
            }
            $newConfig .= "  '{$key}' => '{$val}',\n";
        }
        $newConfig .= ");\n?>";

        if (file_put_contents($configFile, $newConfig)) {
            $success = true;
            $config = include $configFile;
        } else {
            $errors[] = 'ไม่สามารถบันทึกไฟล์ config.php ได้';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Config - JHCISAUDIT</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body { font-family: 'Kanit', sans-serif; background-color: #f3f4f6; }
.gradient-btn { background: linear-gradient(90deg, #667eea, #764ba2); }
.gradient-btn:hover { background: linear-gradient(90deg, #764ba2, #667eea); }
.card { background: #fff; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 2rem; transition: all 0.3s ease; }
.card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
.alert-success { background: #e0f2fe; color: #0369a1; border-left: 4px solid #3b82f6; padding: 1rem; border-radius: 0.5rem; }
.alert-error { background: #fee2e2; color: #b91c1c; border-left: 4px solid #f43f5e; padding: 1rem; border-radius: 0.5rem; }
</style>
</head>
<body class="min-h-screen p-4 flex flex-col items-center">

<div class="max-w-4xl w-full mb-6 flex items-center justify-between">
  <a href="index.php" class="text-white gradient-btn px-4 py-2 rounded-lg flex items-center hover:opacity-90 transition">
    <i class="fas fa-arrow-left mr-2"></i> หน้าแรก
  </a>
  <h1 class="text-2xl font-bold text-gray-800">แก้ไขการเชื่อมต่อฐานข้อมูล</h1>
  <div style="width: 80px;"></div>
</div>

<div class="max-w-4xl w-full card">
  <?php if ($success): ?>
    <div class="mb-4 alert-success flex items-center">
      <i class="fas fa-check-circle mr-2"></i> ✅ บันทึกการตั้งค่าสำเร็จแล้ว
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="mb-4 alert-error">
      <ul class="list-disc list-inside">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
    <?php foreach ($config as $key => $value): ?>
      <?php if (in_array($key, $excludedFields)) continue; ?>
      <div>
        <label for="<?= htmlspecialchars($key) ?>" class="block text-gray-700 mb-1 font-semibold"><?= htmlspecialchars($key) ?></label>
        <input
          type="text"
          name="<?= htmlspecialchars($key) ?>"
          id="<?= htmlspecialchars($key) ?>"
          value="<?= htmlspecialchars($value) ?>"
          class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
          required
        />
      </div>
    <?php endforeach; ?>

    <div class="md:col-span-2 mt-6 text-right">
      <button type="submit" class="gradient-btn text-white font-bold px-6 py-2 rounded-lg flex items-center justify-center hover:opacity-90 transition">
        <i class="fas fa-save mr-2"></i> บันทึกการตั้งค่า
      </button>
    </div>
  </form>
</div>

<?php include 'footer.php'; ?>

</body>
</html>
