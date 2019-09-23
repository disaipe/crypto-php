<?php

/**
 * Wrapper for CPCertficate object
 */
class CryptoCertificate {
	private $original;
	private $pin;

	public $Subject;
	public $Issuer;
	public $Version;
	public $SerialNumber;
	public $Thumbprint;
	public $ValidFrom;
	public $ValidTo;
	public $HasPrivate;
	public $IsValid;

	function __construct($certificate) {
		$this->original = $certificate;
		$this->Subject = $this->parseDN($certificate->get_SubjectName());
		$this->Issuer = $this->parseDN($certificate->get_IssuerName());
		$this->Version = $certificate->get_Version();
		$this->SerialNumber = $certificate->get_SerialNumber();
		$this->Thumbprint = $certificate->get_Thumbprint();
		$this->ValidFrom = $certificate->get_ValidFromDate();
		$this->ValidTo = $certificate->get_ValidToDate();
		$this->HasPrivate = $certificate->HasPrivateKey();
		$this->IsValid = $certificate->IsValid()->get_Result();
	}

	function GetCertificate() {
		return $this->original;
	}

	function SetPin($pin) {
		$this->pin = $pin;
	}

	function GetPin() {
		return $this->pin;
	}

	private function parseDN($dn) {
		$tags = [
			'CN' => 'Name',
			'S' => 'Region',
			'STREET' => 'Address',
			'O' => 'Company',
			'OU' => 'PostType',
			'T' => 'Post',
			'ОГРН' => 'Ogrn',
			'СНИЛС' => 'Snils',
			'ИНН' => 'Inn',
			'E' => 'Email',
			'L' => 'City'
		];

		preg_match_all('/\w+=/u', $dn, $matches);

		$buf = $dn;
		$i = 0;

		$fields = array_reduce(array_reverse($matches[0]), function($acc, $cur) use (&$buf, &$i, $tags) {
			$pos = mb_strpos($buf, $cur);
			//$pos = strpos($buf, $cur);

			$v = substr($buf, $pos);
			$v = str_replace($cur, '', $v);
			$v = preg_replace('/\s*"?(.*?)"?,?\s?$/', '$1', $v);
			$v = preg_replace('/""/', '"', $v);

			$tag = str_replace('=', '', $cur);

			if (array_key_exists($tag, $tags)) {
				$acc[$tags[$tag]] = $v;
			}

			$buf = substr($buf, 0, $pos);

			$i++;

			return $acc;
		}, []);

		return (object)$fields;
	}

}

/**
 * Crypto Helper class
 */
class CryptoHelper {
	const SIGN_DETACHED = true;

    /**
     * Get valid certificates from store
     * @param integer $location - https://docs.microsoft.com/ru-ru/windows/win32/seccrypto/capicom-store-location
	 * @param string $store
     * @return CryptoCertificate[]
     */
	public function GetCertificates($location = null, $store = null) {
		$store = $this->getStore($location, $store);
		$certs = $store->get_Certificates();

		$certs->Find(CERTIFICATE_FIND_TIME_VALID, "", 0);

		$certsArr = [];

		for ($i = 1; $i <= $certs->Count(); $i++) {
			$cert = new CryptoCertificate($certs->Item($i));
			$certsArr []= $cert;
		}

		return $certsArr;
	}

    /**
     * Sign string data type by chosen certificate
     *
     * @param CryptoCertificate $certificate
     * @param string $data
     * @param boolean $toBase64
     * @return string|boolean
     */
	public function Sign($certificate, $data, $toBase64 = true) {
		$signer = new CPSigner();
		$signer->set_Certificate($certificate->GetCertificate());
		$signer->set_Options(CERTIFICATE_INCLUDE_WHOLE_CHAIN);

		if ($certificate->GetPin()) {
			$signer->set_KeyPin($certificate->GetPin());
		}

		$signedData = new CPSignedData();
		$signedData->set_ContentEncoding(BASE64_TO_BINARY);
		$signedData->set_Content($toBase64 ? base64_encode($data) :  $data);
		
		try {
			$signedMessage = $signedData->SignCades($signer, CADES_BES, self::SIGN_DETACHED, ENCODE_BASE64);
			return $signedMessage;
		} catch (Exception $e) {
			return false;
		}
    }
    
    /**
     * Sign file by chosen certificate
     *
     * @param CryptoCertificate $certificate
     * @param string $dataFilePath
	 * @param string $signFilePath
     * @return string|boolean
     */
    public function SignFile($certificate, $dataFilePath, $signFilePath = null) {
		$data = file_get_contents($dataFilePath);
		$sign = $this->Sign($certificate, $data, true);

		if ($signFilePath) {
			file_put_contents($signFilePath, $sign);
		}

		return $sign;
    }

    /**
     * Verify data sign
     *
     * @param string $data
     * @param string $sign
	 * @param boolean $toBase64
     * @return array|boolean
     */
	public function Verify($data, $sign, $toBase64 = true) {
		$signedData = new CPSignedData();
		$signedData->set_ContentEncoding(BASE64_TO_BINARY);
		$signedData->set_Content($toBase64 ? base64_encode($data) : $data);

		try {
				$signedData->VerifyCades($sign, CADES_BES, self::SIGN_DETACHED);
				$signers = $signedData->get_Signers();
                $signs = [];
                
				for ($i = 1; $i <= $signedData->get_Signers(); $i += 1) {
					$signer = $signers->get_Item($i);
					$cert = $signer->get_Certificate();

					$signs []= (object)[
						'ts' => $signer->get_SigningTime(),
						'cert' => new CryptoCertificate($cert)
					];
				}

				return $signs;
			} catch (Exception $e) {
				return false;    
		}
    }
    
    /**
     * Verify file sign
     *
     * @param string $dataFilePath
     * @param string $signFilePath
     * @return array|boolean
     */
    public function VerifyFile($dataFilePath, $signFilePath) {
        $data = file_get_contents($dataFilePath);
        $sign = file_get_contents($signFilePath);
        return $this->Verify($data, $sign, true);
    }

    /**
     * Create and open store
     *
     * @param integer $location
     * @param string $name
     * @param integer $mode
     * @return CPStore
     */
	private function getStore($location = null, $name = null, $mode = null) {
		$location = $location ?: CURRENT_USER_STORE;
		$name = $name ?: 'My';
		$mode = $mode ?: STORE_OPEN_READ_ONLY;

		$store = new CPStore();
		$store->Open($location, $name, $mode);
		return $store;
	}
}