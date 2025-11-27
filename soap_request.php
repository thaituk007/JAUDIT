require_once "config.php";

$xml = "<?xml version=\"1.0\"?>
<Envelope xmlns=\"http://schemas.xmlsoap.org/soap/envelope/\">
  <Body>
    <seacrhByPid xmlns=\"http://rightsearch.nhso.go.th/\">
      <pid>$pid</pid>
      <userName>$nhso_user</userName>
      <password>$nhso_password</password>
    </seacrhByPid>
  </Body>
</Envelope>";
