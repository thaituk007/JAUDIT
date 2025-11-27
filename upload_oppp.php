<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>อัปโหลดไฟล์ Excel OPPP</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
    <style>
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Prompt', sans-serif;
            background: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .upload-container {
            background: white;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            width: 360px;
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
            color: #333;
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input[type="file"]:focus {
            outline: none;
            border-color: #4caf50;
        }
        button {
            margin-top: 25px;
            background-color: #4caf50;
            border: none;
            color: white;
            padding: 12px 24px;
            font-size: 1.1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #43a047;
        }
        footer {
            margin-top: 15px;
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <h2>📤 อัปโหลดไฟล์ Excel (.xls / .xlsx)</h2>
        <form action="process_oppp.php" method="post" enctype="multipart/form-data" novalidate>
            <input type="file" name="excel_file" accept=".xls,.xlsx" required />
            <button type="submit">▶ ดำเนินการนำเข้า</button>
        </form>
        <footer>ใช้ไฟล์ Excel ที่มีข้อมูลแถวที่ 4 เป็นหัวข้อ และแถวที่ 5 เป็นต้นไปเป็นข้อมูล</footer>
    </div>
</body>
</html>
