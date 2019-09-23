<?
require_once('CryptoHelper.php');

$crypto = new CryptoHelper();

echo '<pre>';

// List certificates
$certificates = $crypto->GetCertificates();
print_r($certificates);

// Sign file
$certificates[0]->SetPin('123456');
$sign = $crypto->SignFile($certificates[0], '800.jpeg', '800.jpeg.sgn');
print_r($sign);

// Verify file with detached sign
$signInfo = $crypto->VerifyFile('800.jpeg', '800.jpeg.sgn');
if ($signInfo) {
	foreach ($signInfo as $sign) {
		echo "\nTimestamp: {$sign->ts}, Name: {$sign->cert->Subject->Name}\n";
	}
} else {
	print_r("\nSign is not valid\n");
}

echo '</pre>';