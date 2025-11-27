use Webauthn\PublicKeyCredentialLoader;
use Webauthn\AuthenticatorAssertionResponseValidator;

$loader = new PublicKeyCredentialLoader();
$credential = $loader->loadArray(json_decode(file_get_contents('php://input'), true));

// ดึง publicKey จากฐานข้อมูลที่บันทึกไว้ตอนลงทะเบียน
$storedPublicKey = ...;

$validator = new AuthenticatorAssertionResponseValidator(...);
$validCredential = $validator->check(
    $credential->getResponse(),
    $storedPublicKey,
    $serverRequest,  // PSR-7 request
    $challenge,
    $userHandle
);
