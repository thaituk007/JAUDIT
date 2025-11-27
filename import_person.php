<?php
set_time_limit(0);
ini_set("memory_limit", "1024M");
date_default_timezone_set('Asia/Bangkok');
$config = include('config.php');

// กำหนดคีย์สำหรับการเข้ารหัส
$encryption_key = 'my-secret-key';
$encryption_method = 'AES-256-CBC';
$iv = substr(hash('sha256', 'my-secret-iv'), 0, 16);

// ฟังก์ชันเข้ารหัส CID
function encrypt_cid($cid, $key, $method, $iv) {
    return openssl_encrypt($cid, $method, $key, 0, $iv);
}

// เชื่อมต่อ PDO
try {
    $pdo = new PDO(
        "mysql:host=" . $config['db_host'] . ";dbname=" . $config['db_name'] . ";charset=utf8",
        $config['db_user'],
        $config['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// เริ่มประมวลผลเมื่อมีการอัปโหลด
if (!empty($_FILES['person_file']['tmp_name'])) {
    $handle = fopen($_FILES['person_file']['tmp_name'], "r");
    $header = null;
    $rows = array();
    $count = 0;

    while (($line = fgets($handle)) !== false) {
        $data = explode('|', trim($line));
        if (count($data) < 33) continue;

        $cid_encrypted = encrypt_cid($data[1], $encryption_key, $encryption_method, $iv);

        $rows[] = array(
            'hospcode' => $data[0],
            'cid' => $cid_encrypted,
            'pid' => $data[2],
            'hid' => $data[3],
            'prename' => $data[4],
            'name' => $data[5],
            'lname' => $data[6],
            'hn' => $data[7],
            'sex' => $data[8],
            'birth' => format_date($data[9]),
            'mstatus' => $data[10],
            'occupation_old' => $data[11],
            'occupation_new' => $data[12],
            'race' => $data[13],
            'nation' => $data[14],
            'religion' => $data[15],
            'education' => $data[16],
            'fstatus' => $data[17],
            'father' => $data[18],
            'mother' => $data[19],
            'couple' => $data[20],
            'vstatus' => $data[21],
            'movein' => format_date($data[22]),
            'discharge' => $data[23],
            'ddischarge' => format_date($data[24]),
            'abogroup' => $data[25],
            'rhgroup' => $data[26],
            'labor' => $data[27],
            'passport' => $data[28],
            'typearea' => $data[29],
            'd_update' => format_datetime($data[30]),
            'telephone' => $data[31],
            'mobile' => $data[32]
        );
        $count++;
    }

    fclose($handle);

    // ล้างข้อมูลเดิม
    $pdo->exec("DELETE FROM pcperson");

    // เตรียม statement
    $sql = "INSERT INTO pcperson (
        hospcode, cid, pid, hid, prename, name, lname, hn, sex, birth,
        mstatus, occupation_old, occupation_new, race, nation, religion, education, fstatus,
        father, mother, couple, vstatus, movein, discharge, ddischarge, abogroup, rhgroup,
        labor, passport, typearea, d_update, telephone, mobile
    ) VALUES (
        :hospcode, :cid, :pid, :hid, :prename, :name, :lname, :hn, :sex, :birth,
        :mstatus, :occupation_old, :occupation_new, :race, :nation, :religion, :education, :fstatus,
        :father, :mother, :couple, :vstatus, :movein, :discharge, :ddischarge, :abogroup, :rhgroup,
        :labor, :passport, :typearea, :d_update, :telephone, :mobile
    )";

    $stmt = $pdo->prepare($sql);
    foreach ($rows as $row) {
        $stmt->execute($row);
    }

    $message = "✅ นำเข้าข้อมูล $count รายการเรียบร้อยแล้ว";
}

// ฟังก์ชันแปลงวันที่
function format_date($str) {
    if (!$str || strlen($str) < 8) return null;
    return substr($str, 0, 4) . "-" . substr($str, 4, 2) . "-" . substr($str, 6, 2);
}

function format_datetime($str) {
    if (!$str || strlen($str) < 14) return null;
    return substr($str, 0, 4) . "-" . substr($str, 4, 2) . "-" . substr($str, 6, 2) . " " . substr($str, 8, 2) . ":" . substr($str, 10, 2) . ":" . substr($str, 12, 2);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>นำเข้าข้อมูล PERSON</title>
    <link href="https://fonts.googleapis.com/css?family=Prompt&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; padding: 20px; background-color: #f9f9f9; }
        h2 { color: #333; }
        form { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 8px rgba(0,0,0,0.1); }
        input[type="file"] { padding: 5px; }
        input[type="submit"] { padding: 10px 20px; margin-top: 10px; }
        .message { margin-top: 15px; color: green; }
    </style>
</head>
<body>
    <h2>นำเข้าข้อมูล PERSON</h2>
    <form method="post" enctype="multipart/form-data">
        <label>เลือกไฟล์ข้อมูล PERSON (.txt): </label>
        <input type="file" name="person_file" required>
        <br>
        <input type="submit" value="นำเข้าข้อมูล">
    </form>

    <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <br>
    <a href="create_pcperson_table.php">🔧 สร้างตาราง PCPERSON</a>

    <br><br>
    <?php include 'footer.php'; ?>
</body>
</html>
