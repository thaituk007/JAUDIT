<?php
/**
 * PHP: JHCIS → NCD Registry API (Batch, Null-safe, Log, Resume, Config)
 */

// -------------------------------
// โหลด config.php ของคุณ
// -------------------------------
$configApp = require __DIR__ . '/config.php';

// -------------------------------
// Database Connection
// -------------------------------
$dsn = "mysql:host={$configApp['db_host']};port={$configApp['db_port']};dbname={$configApp['db_name']};charset=utf8mb4";
$username = $configApp['db_user'];
$password = $configApp['db_pass'];

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected to DB successfully.\n";
} catch (PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}

// -------------------------------
// NCD Registry API Class
// -------------------------------
class NCDRegistryAPI {
    private $clientId;
    private $clientSecret;
    private $providerClientId;
    private $providerSecret;
    private $redirectUri;
    private $apiMoph;
    private $apiProvider;
    private $apiNcd;

    public function __construct($config) {
        $this->clientId = $config['CLIENT_ID'];
        $this->clientSecret = $config['CLIENT_SECRET'];
        $this->providerClientId = $config['CLIENT_ID_PROVIDER'];
        $this->providerSecret = $config['SECRET_KEY_PROVIDER'];
        $this->redirectUri = $config['REDIRECT_URI'];
        $this->apiMoph = $config['API_MOPH'];
        $this->apiProvider = $config['API_PROVIDER'];
        $this->apiNcd = $config['API_NCD'];
    }

    private function httpPost($url, $data, $headers = []) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }
        curl_close($ch);
        return json_decode($response, true);
    }

    public function getMophToken($authCode) {
        $url = $this->apiMoph . "/api/v1/token";
        $data = [
            "grant_type" => "authorization_code",
            "code" => $authCode,
            "redirect_uri" => $this->redirectUri,
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret
        ];
        return $this->httpPost($url, $data, ['Content-Type: application/json']);
    }

    public function getProviderToken($mophAccessToken) {
        $url = $this->apiProvider . "/api/v1/services/token";
        $data = [
            "client_id" => $this->providerClientId,
            "secret_key" => $this->providerSecret,
            "token_by" => "Health ID",
            "token" => $mophAccessToken
        ];
        return $this->httpPost($url, $data, ['Content-Type: application/json']);
    }

    public function getMophAccessTokenIdp($providerToken) {
        $url = $this->apiProvider . "/api/v1/services/moph-idp/profile-staff";
        $data = [
            "client_id" => $this->providerClientId,
            "secret_key" => $this->providerSecret
        ];
        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer $providerToken"
        ];
        return $this->httpPost($url, $data, $headers);
    }

    public function sendToNcd($mophAccessTokenIdp, $payload) {
        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer $mophAccessTokenIdp"
        ];
        return $this->httpPost($this->apiNcd, $payload, $headers);
    }
}

// -------------------------------
// กำหนดค่า NCD API (แทนที่ค่าจริงของคุณ)
// -------------------------------
$ncdConfig = [
    "CLIENT_ID" => "YOUR_CLIENT_ID",
    "CLIENT_SECRET" => "YOUR_CLIENT_SECRET",
    "CLIENT_ID_PROVIDER" => "YOUR_CLIENT_ID_PROVIDER",
    "SECRET_KEY_PROVIDER" => "YOUR_SECRET_KEY_PROVIDER",
    "REDIRECT_URI" => "http://127.0.0.1:8081",
    "API_MOPH" => "https://moph.id.th",
    "API_PROVIDER" => "https://provider.id.th",
    "API_NCD" => "https://ncdreg.bmscloud.in.th/api/NCDRegister"
];

$api = new NCDRegistryAPI($ncdConfig);

// -------------------------------
// Auth Flow
// -------------------------------
$authCode = "AUTH_CODE_FROM_BROWSER";
$mophToken = $api->getMophToken($authCode);
$accessTokenMoph = $mophToken['data']['access_token'] ?? null;

$providerTokenResp = $api->getProviderToken($accessTokenMoph);
$accessTokenProvider = $providerTokenResp['data']['access_token'] ?? null;

$mophIdpResp = $api->getMophAccessTokenIdp($accessTokenProvider);
$mophAccessTokenIdp = $mophIdpResp['data']['organization'][0]['moph_access_token_idp'] ?? null;

// -------------------------------
// ดึงข้อมูลผู้ป่วย
// -------------------------------
$sql = "
SELECT
    p.pcucodeperson, p.pid, p.cid, p.fname, p.lname, p.sex, p.birth, p.marrystatus,
    c.chroniccode, c..datefirstdiag, c.datedischart,
    p.hno,v.villno moo, a.tmbpart, a.amppart, a.chwpart,v.postcodemoi
FROM person p
JOIN personchronic c ON p.pcucodeperson=c.pcucodeperson AND p.pid=c.pid
LEFT JOIN house h ON p.hcode=h.hcode AND p.pcucodeperson=h.pcucode
LEFT JOIN village v ON v.villcode=h.villcode AND h.pcucode = v.pcucode
WHERE c.chroniccode IN ('E11','I10')
";
$stmt = $pdo->query($sql);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------
// Helper: remove nulls recursively
// -------------------------------
function removeNulls($arr) {
    foreach ($arr as $key => $val) {
        if (is_array($val)) $arr[$key] = removeNulls($val);
        if ($arr[$key] === null) unset($arr[$key]);
    }
    return $arr;
}

// -------------------------------
// Batch Send + Resume
// -------------------------------
$batchSize = 50;
$logFile = __DIR__ . "/ncd_log.txt";
$progressFile = __DIR__ . "/ncd_progress.txt";

$startIndex = 0;
if (file_exists($progressFile)) {
    $startIndex = (int)file_get_contents($progressFile);
}

$total = count($patients);
echo "Total patients: $total, starting from batch index: $startIndex\n";

for ($i = $startIndex; $i < $total; $i += $batchSize) {
    $batch = array_slice($patients, $i, $batchSize);
    $payloadBatch = [];

    foreach ($batch as $r) {
        $payload = [
            "managingOrganization" => [
                "type" => "Organization",
                "identifier" => [
                    "use" => "official",
                    "system" => "https://phr1.moph.go.th/api/CodingSystem?System=hospital",
                    "Value" => $r["pcucodeperson"]
                ],
                "display" => "หน่วยบริการตัวอย่าง",
                "agent" => "JHCIS"
            ],
            "Patient" => [
                "identifier" => [[
                    "use" => "official",
                    "system" => "https://www.dopa.go.th",
                    "type" => "CID",
                    "Value" => $r["cid"]
                ]],
                "active" => true,
                "name" => [[
                    "use" => "official",
                    "languageCode" => "TH",
                    "text" => trim($r["fname"] . " " . $r["lname"]),
                    "prefix" => [$r["sex"] == 1 ? "นาย" : "นาง/นางสาว"],
                    "given" => [$r["fname"]],
                    "family" => $r["lname"]
                ]],
                "gender" => ($r["sex"] == 1 ? "male" : "female"),
                "birthDate" => $r["birth"] ?: null,
                "address" => [[
                    "type" => "both",
                    "line" => array_filter([$r["hno"], $r["moo"] ? "หมู่ " . $r["moo"] : null]),
                    "city" => $r["amppart"] ?: null,
                    "state" => $r["chwpart"] ?: null,
                    "postalCode" => $r["postcode"] ?: null,
                    "country" => "TH"
                ]],
                "maritalStatus" => ["text" => $r["marrystatus"] ?: null]
            ],
            "ChronicDisease" => [[
                "resourceType" => "ChronicDisease",
                "id" => $r["pid"],
                "status" => [
                    "coding" => [[
                        "system" => "https://phr1.moph.go.th/api/CodingSystem?System=ncd-status",
                        "code" => "active",
                        "display" => "Active NCD"
                    ]]
                ],
                "ncdCode" => [
                    "coding" => [[
                        "system" => "https://phr1.moph.go.th/api/CodingSystem?System=ncd",
                        "code" => ($r["chroniccode"] == "E11" ? "001" : "002"),
                        "display" => ($r["chroniccode"] == "E11" ? "โรคเบาหวาน" : "โรคความดันโลหิตสูง")
                    ]]
                ],
                "clinicalStatus" => [
                    "coding" => [[
                        "system" => "https://phr1.moph.go.th/api/CodingSystem?System=ncd-status",
                        "code" => "3",
                        "display" => "ยังรักษาอยู่"
                    ]],
                    "text" => "ยังรักษาอยู่"
                ],
                "clinicalDiagnosis" => [[
                    "coding" => [[
                        "code" => $r["chroniccode"],
                        "display" => "ICD10: " . $r["chroniccode"]
                    ]],
                    "text" => "การวินิจฉัย " . $r["chroniccode"]
                ]],
                "registerDate" => $r["date_diag"] ?: null,
                "beginDate" => $r["date_diag"] ?: null,
                "dischargeDate" => $r["date_discharge"] ?: null
            ]]
        ];

        $payloadBatch[] = removeNulls($payload);
    }

    try {
        $resp = $api->sendToNcd($mophAccessTokenIdp, $payloadBatch);
        file_put_contents($logFile, date("Y-m-d H:i:s") . " | Batch " . ($i/$batchSize+1) . " | SUCCESS\n" . print_r($resp, true) . "\n", FILE_APPEND);
        echo "Batch " . ($i/$batchSize+1) . " sent successfully.\n";
        // อัปเดต progress
        file_put_contents($progressFile, $i + $batchSize);
    } catch (Exception $e) {
        file_put_contents($logFile, date("Y-m-d H:i:s") . " | Batch " . ($i/$batchSize+1) . " | ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        echo "Batch " . ($i/$batchSize+1) . " failed: " . $e->getMessage() . "\n";
        break; // หยุด script แต่ progress จะยังอยู่
    }
}

echo "All done.\n";
