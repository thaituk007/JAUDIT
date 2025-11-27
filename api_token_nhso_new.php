<?php
/**
 * NHSO UCWS API Client (PHP) - Windows / AppServ
 * - ค้นหา token.txt อัตโนมัติ
 * - ตรวจสอบสิทธิ (right-search)
 * - fund-safe
 * - refresh token อัตโนมัติและ update token.txt
 * - ค้นหา cacert.pem อัตโนมัติหลายโฟลเดอร์ + fallback
 */

// ---------------------------
// ค้นหา token.txt
// ---------------------------
function getTokenFilePath() {
    $folders = [
        "C:\\Program Files (x86)\\SRM Smart Card Single Sign-On",
        "C:\\Program Files\\SRM Smart Card Single Sign-On",
        "C:\\temp"
    ];

    foreach ($folders as $folder) {
        $file = $folder . "\\token.txt";
        if (file_exists($file)) {
            return $file;
        }
    }
    throw new Exception("ไม่พบไฟล์ token.txt ในโฟลเดอร์ที่กำหนด");
}

// ---------------------------
// โหลด access token
// ---------------------------
function loadAccessToken() {
    $file = getTokenFilePath();
    $token = trim(file_get_contents($file));
    if (empty($token)) {
        throw new Exception("ไฟล์ token.txt ว่างเปล่า");
    }
    return $token;
}

// ---------------------------
// บันทึก access token
// ---------------------------
function saveAccessToken($token) {
    $file = getTokenFilePath();
    if (file_put_contents($file, $token) === false) {
        throw new Exception("ไม่สามารถเขียนไฟล์ token.txt ได้");
    }
}

// ---------------------------
// ค้นหา cacert.pem อัตโนมัติ
// ---------------------------
function getCAFilePath() {
    $folders = [
        "C:/AppServ/php7/extras/ssl/cacert.pem",
        "C:/AppServ/php5/extras/ssl/cacert.pem",
        "C:/cert/cacert.pem"
    ];

    foreach ($folders as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return false; // ไม่เจอไฟล์
}

// ---------------------------
// Refresh token
// ---------------------------
function refreshAccessToken() {
    $url = "https://srm.nhso.go.th/api/ucws/v1/token-refresh";
    $clientId = "YOUR_CLIENT_ID";
    $clientSecret = "YOUR_CLIENT_SECRET";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            "client_id" => $clientId,
            "client_secret" => $clientSecret
        ]),
        CURLOPT_HTTPHEADER => [
            "Accept: application/json"
        ]
    ]);

    // ตรวจหา CA bundle
    $caPath = getCAFilePath();
    if ($caPath) {
        curl_setopt($ch, CURLOPT_CAINFO, $caPath);
    } else {
        echo "⚠️ Warning: ไม่พบไฟล์ CA bundle, จะใช้ CURLOPT_SSL_VERIFYPEER=false\n";
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error (refresh token): " . $error);
    }
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception("ไม่พบ access_token ใน response");
        }
        saveAccessToken($data['access_token']);
        return $data['access_token'];
    } else {
        throw new Exception("HTTP {$httpCode} (refresh token): " . $response);
    }
}

// ---------------------------
// เรียก API ตรวจสอบสิทธิ
// ---------------------------
function nhsoRightSearch($pid, $accessToken) {
    $url = "https://srm.nhso.go.th/api/ucws/v1/right-search?pid=" . urlencode($pid);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $accessToken,
            "Accept: application/json"
        ]
    ]);

    // ตรวจหา CA bundle
    $caPath = getCAFilePath();
    if ($caPath) {
        curl_setopt($ch, CURLOPT_CAINFO, $caPath);
    } else {
        echo "⚠️ Warning: ไม่พบไฟล์ CA bundle, จะใช้ CURLOPT_SSL_VERIFYPEER=false\n";
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: " . $error);
    }
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    } elseif ($httpCode === 401) {
        $newToken = refreshAccessToken();
        return nhsoRightSearch($pid, $newToken);
    } else {
        throw new Exception("HTTP {$httpCode}: " . $response);
    }
}

// ---------------------------
// ตัวอย่างการใช้งาน
// ---------------------------
try {
    $accessToken = loadAccessToken();
    $pid = "1101700234567";

    $result = nhsoRightSearch($pid, $accessToken);

    echo "ตรวจสอบสิทธิสำเร็จ\n";
    echo "ชื่อ: " . ($result['tname'] ?? '') . " " . ($result['fname'] ?? '') . " " . ($result['lname'] ?? '') . "\n";
    echo "วันเกิด: " . ($result['birthDate'] ?? '') . "\n";

    $fundCount = isset($result['funds']) && is_array($result['funds']) ? count($result['funds']) : 0;
    echo "สิทธิที่พบ: {$fundCount} กองทุน\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
