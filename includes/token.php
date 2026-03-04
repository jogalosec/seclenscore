<?php

use \Firebase\JWT\JWT;

const DATE_FORMAT = 'Y-m-d H:i:s';

class ConfigFileException extends \Exception {}
class TokenConfig
{
    private $frase = null;
    private $algorithm = null;
    private $aeskey = null;
    private $aesiv = null;

    public function __construct(string $configFilePath = __DIR__ . "/config.ini")
    {
        if (!file_exists($configFilePath)) {
            throw new ConfigFileException("The config file does not exist at the specified path '{$configFilePath}'.");
        }

        if (!is_readable($configFilePath)) {
            throw new ConfigFileException("The config file '{$configFilePath}' is not readable.");
        }

        $settings = parse_ini_file($configFilePath, true, INI_SCANNER_TYPED);
        if ($settings === false) {
            throw new ConfigFileException("Failed to parse the config file '{$configFilePath}'. Please check the file format.");
        }

        if (!array_key_exists("ssl", $settings)) {
            throw new ConfigFileException("The 'ssl' section is missing in the config file '{$configFilePath}'.");
        }

        $sslSettings = $settings["ssl"];
        if (!array_key_exists("frase", $sslSettings)) {
            throw new ConfigFileException("The 'frase' key is missing in the 'ssl' section of the config file '{$configFilePath}'.");
        }

        if (!array_key_exists("algorithm", $sslSettings)) {
            throw new ConfigFileException("The 'algorithm' key is missing in the 'ssl' section of the config file '{$configFilePath}'.");
        }

        $this->frase = $settings["ssl"]["frase"];
        $this->algorithm = $sslSettings["algorithm"];
        $this->aeskey = $settings["ssl"]["jtikey"];
        $this->aesiv = $settings["ssl"]["jtiiv"];
    }

    public function getPrivateKey()
    {
        $frase = hex2bin($this->getFrase());
        $file = file_get_contents('../includes/privatekey.pem');
        return openssl_pkey_get_private(
            $file,
            $frase
        );
    }

    public function getFrase(): string
    {
        return $this->frase;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getAesKey(): string
    {
        return $this->aeskey;
    }

    public function getAesIv(): string
    {
        return $this->aesiv;
    }
}

class TokenEncryptorException extends \Exception {}

class TokenEncryptor
{
    public function encrypt(string $plaintext, string $algorithm, string $jti): string
    {
        if ($algorithm === "AES-256-CBC") {
            $db = new DbOperations();
            $tokensaved = $db->getTokenbyJti($jti);
            if (isset($tokensaved[0])) {
                $iv = hex2bin($tokensaved[0]["iv"]);
                $key = hex2bin($tokensaved[0]["phrase"]);
            } else {
                $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-256-CBC"));
                $key = random_bytes(32);
                $jti = bin2hex(random_bytes(16));
            }
            $ciphertext = openssl_encrypt($plaintext, "AES-256-CBC", $key, 0, $iv);

            $openssl = new TokenConfig();
            $algorithmtoken = $openssl->getAlgorithm();
            $secretKey = $openssl->getPrivateKey();
            $data = array("data" => $ciphertext, "jti" => $jti);
            $data = $this->encryptCookie($data);
            $newToken = JWT::encode(array($data), $secretKey, $algorithmtoken);
            $this->setDataToken($plaintext, $iv, $key, $jti);
            if (isset($_SERVER['HTTP_X_ORIGINAL_HOST'])) {
                $domain = rawurlencode($_SERVER['HTTP_X_ORIGINAL_HOST']);
            } elseif (isset($_SERVER['SERVER_NAME'])) {
                $domain = rawurlencode($_SERVER['SERVER_NAME']);
            }
            $options = [
                "expires" => time() + 3600,
                'samesite' => 'Strict',
                'path' => '/',
                'domain' => $domain,
                'secure' => true,
                'httponly' => true,
            ];
            $this->createSensitiveCookie($newToken, $options, "sst");
            return $newToken;
        } else {
            throw new TokenEncryptorException("Unsupported algorithm '{$algorithm}'.");
        }
    }

    private function encryptCookie($data)
    {
        $aes = new TokenConfig();
        $secretKey = hex2bin($aes->getAesKey());
        $iv = hex2bin($aes->getAesIv());
        $data = json_encode($data);
        return openssl_encrypt($data, "aes-256-cbc", $secretKey, 0, $iv);
    }

    private function decryptPayload($data)
    {
        $aes = new TokenConfig();
        $secretKey = hex2bin($aes->getAesKey());
        $iv = hex2bin($aes->getAesIv());
        $decryptedMessage = openssl_decrypt($data->{0}, "aes-256-cbc", $secretKey, 0, $iv);
        return json_decode($decryptedMessage, true);
    }

    public function createSensitiveCookie($data, $option, $name)
    {
        setcookie($name, $data, $option);
    }

    public function decrypt($data)
    {
        $db = new DbOperations();
        if (isset($data[0])) {
            $data = $this->decryptPayload($data[0]);
            $jti = $data["jti"];
        }
        $datasaved = $db->getTokenbyJti($jti);
        if (isset($datasaved[0])) {
            $datasaved = $datasaved[0];
            try {
                $original_plaintext = openssl_decrypt($data["data"], "AES-256-CBC", hex2bin($datasaved["phrase"]), 0, hex2bin($datasaved["iv"]));
                $datajti = json_decode($original_plaintext, true);
                $data["saved"] = $datasaved;
                $data["new"] = $datajti;
                return $data;
            } catch (Exception $e) {
                error_log($e->getMessage(), 0);
                throw new TokenEncryptorException('Error decrypting data', 0, $e);
            }
        }
        return false;
    }

    private function setDataToken($data, $iv, $phrase, $jti)
    {
        $db = new DbOperations();
        $jtisaved = $db->getTokenbyJti($jti);
        $data = json_decode($data, true);
        if (isset($jtisaved[0])) {
            $this->updateDataToken($jti, $data, true);
        } else {
            $hash = $jti;
            $created = date(DATE_FORMAT, $data["iat"]);
            $expired = date(DATE_FORMAT, $data["exp"]);
            $user = $data["data"];
            $userdata = $data["aud"];
            $db->setToken($hash, $created, $expired, bin2hex($iv), bin2hex($phrase), $user, $userdata);
        }
    }

    private function updateDataToken($jti, $data,  $suma = false)
    {
        $data["iat"] = date(DATE_FORMAT, $data["iat"]);
        $data["exp"] = date(DATE_FORMAT, $data["exp"]);
        $db = new DbOperations();
        $db->updateToken($jti, $data, $suma);
    }
}

class TokenBearerEncryptor extends TokenEncryptor
{
    public function encryptBearer(string $token)
    {
        $aes = new TokenConfig();
        $secretKey = hex2bin($aes->getAesKey());
        $iv = hex2bin($aes->getAesIv());
        $data = $token;
        return openssl_encrypt($data, "aes-256-cbc", $secretKey, 0, $iv);
    }

    public function decryptBearer($data)
    {
        $aes = new TokenConfig();
        $secretKey = hex2bin($aes->getAesKey());
        $iv = hex2bin($aes->getAesIv());
        return openssl_decrypt($data, "aes-256-cbc", $secretKey, 0, $iv);
    }
}
