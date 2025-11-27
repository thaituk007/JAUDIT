<?php
session_start();
require __DIR__ . '/upload_handler.php';

$error = isset($_SESSION['uploadError']) ? $_SESSION['uploadError'] : '';
$deleteMessage = isset($_SESSION['deleteMessage']) ? $_SESSION['deleteMessage'] : '';
unset($_SESSION['uploadError'], $_SESSION['deleteMessage']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<title>อัปโหลดไฟล์ person.txt</title>
<!-- ฟอนต์ Prompt -->
<link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
<style>
  /* จัดกลางหน้าและกล่อง */
  body, html {
    height: 100%;
    margin: 0;
    font-family: 'Prompt', sans-serif;
    background: #f0f4f8;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .container {
    background: white;
    padding: 30px 40px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    width: 100%;
    max-width: 500px;
    text-align: center;
  }
  h2 {
    margin-bottom: 15px;
    color: #333;
  }
  #current-datetime {
    margin-bottom: 20px;
    color: #666;
    font-size: 14px;
    font-weight: 500;
  }
  input[type="file"] {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1.5px solid #ccc;
    font-size: 16px;
    transition: border-color 0.3s ease;
    cursor: pointer;
  }
  input[type="file"]:focus {
    outline: none;
    border-color: #3f51b5;
  }
  button {
    margin-top: 20px;
    padding: 12px 28px;
    background-color: #3f51b5;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(63,81,181,0.4);
    transition: background-color 0.3s ease;
  }
  button:hover {
    background-color: #2c387e;
  }
  .message {
    margin-top: 20px;
    font-weight: 600;
    color: #4caf50;
  }
  .error {
    margin-top: 20px;
    color: #d32f2f;
    font-weight: 700;
  }
  .alert-message {
    margin-top: 10px;
    color: #d32f2f;
    font-weight: 700;
  }
  form {
    margin-top: 15px;
  }
  #progress-container {
    display: none;
    margin-top: 25px;
    background: #eee;
    border-radius: 8px;
  }
  #progress-bar {
    height: 26px;
    width: 0;
    background-color: #3f51b5;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    line-height: 26px;
    transition: width 0.3s ease;
  }
  a.home-link {
    display: inline-block;
    margin-top: 25px;
    padding: 12px 28px;
    background-color: #4caf50;
    color: white;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(76,175,80,0.4);
    transition: background-color 0.3s ease;
  }
  a.home-link:hover {
    background-color: #388e3c;
  }
  button.delete-btn {
    background-color: #d32f2f;
    box-shadow: 0 4px 12px rgba(211,47,47,0.4);
  }
  button.delete-btn:hover {
    background-color: #9a2424;
  }
</style>
</head>
<body>
  <div class="container">
    <h2>📤 อัปโหลดไฟล์ person.txt</h2>
    <div id="current-datetime"></div>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($deleteMessage): ?>
      <div class="message"><?= htmlspecialchars($deleteMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['upload_file'])): ?>
      <p>✅ พบไฟล์อัปโหลด รอดำเนินการนำเข้า</p>
      <button id="start-import">เริ่มนำเข้า</button>
      <form method="post"><button type="submit" name="clear_session" class="delete-btn">🧹 ล้างไฟล์ชั่วคราว</button></form>
      <div id="progress-container">
        <div id="progress-bar">0%</div>
      </div>
      <div id="result" class="message"></div>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data">
        <input type="file" name="person_file" accept=".txt" required />
        <button type="submit">อัปโหลด</button>
      </form>
    <?php endif; ?>

    <form method="post" onsubmit="return confirm('ลบข้อมูลที่ nation ≠ 099 ใช่หรือไม่?');">
      <button type="submit" name="delete_nation_not_099" class="delete-btn">🗑️ ลบ nation ≠ 099</button>
    </form>

    <a href="index.php" class="home-link">🏠 กลับหน้าแรก</a>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // แสดงวันเวลาปัจจุบัน ภาษาไทย ปี พ.ศ.
  function updateDateTime() {
    var now = new Date();
    var days = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    var months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

    var dayName = days[now.getDay()];
    var dayNum = now.getDate();
    var monthName = months[now.getMonth()];
    var year = now.getFullYear() + 543; // ปีพ.ศ.

    var hours = now.getHours();
    var minutes = now.getMinutes();
    var seconds = now.getSeconds();

    function pad(n) { return n < 10 ? '0' + n : n; }

    var timeStr = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
    var dateStr = 'วัน' + dayName + ' ที่ ' + dayNum + ' ' + monthName + ' ' + year;

    document.getElementById('current-datetime').textContent = dateStr + ' เวลา ' + timeStr;
  }
  updateDateTime();
  setInterval(updateDateTime, 1000);

  // นำเข้าข้อมูลแบบ batch
  var btn = document.getElementById('start-import');
  if (!btn) return;
  var progressBar = document.getElementById('progress-bar');
  var progressContainer = document.getElementById('progress-container');
  var resultDiv = document.getElementById('result');

  function importChunk(offset) {
    var formData = new FormData();
    formData.append('action', 'import_chunk');
    formData.append('offset', offset);

    fetch(window.location.href, {
      method: 'POST',
      body: formData
    })
    .then(function(response) {
      return response.json();
    })
    .then(function(data) {
      if (data.error) {
        throw data.error;
      }
      progressContainer.style.display = 'block';

      var percent = Math.round(data.progress * 100);
      progressBar.style.width = percent + '%';
      progressBar.textContent = percent + '%';

      if (data.done) {
        var msg = '✅ เสร็จสิ้น: เพิ่ม ' + data.inserted + ' | ข้าม ' + data.skipped;
        if (data.alert) {
          msg += '<br><span class="alert-message">' + data.alert + '</span>';
        }
        resultDiv.innerHTML = msg;
        btn.disabled = false;
        btn.textContent = 'นำเข้าอีกครั้ง';
      } else {
        importChunk(data.next_offset);
      }
    })
    .catch(function(err) {
      progressBar.style.backgroundColor = 'red';
      resultDiv.textContent = 'เกิดข้อผิดพลาด: ' + err;
    });
  }

  btn.addEventListener('click', function() {
    btn.disabled = true;
    resultDiv.textContent = '';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    importChunk(0);
  });
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>
