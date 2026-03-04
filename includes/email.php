<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailConfigException extends \Exception {}

class Email
{
    private $file = null;
    private $mail = null;

    public function __construct(string $file = "config.ini", string $encType = "UTF-8")
    {
        if (!$settings = parse_ini_file($file, true)) {
            throw new EmailConfigException(sprintf("Failed to parse config file: %s", $file));
        }
        $this->file = $file;
        $this->mail = new PHPMailer(true);
        $this->mail->Host = $settings["email"]["hostmail"];
        $this->mail->Username = $settings["email"]["usernamemail"];
        $this->mail->Password =  $settings["email"]["passmail"];
        $this->mail->SMTPSecure = 'tls';
        $this->mail->Port       = 587;
        $this->mail->SMTPDebug = 0;
        $this->mail->CharSet = $encType;
        $this->mail->SMTPAuth   = true;
        $this->mail->isSMTP();
        $this->mail->setFrom($settings["email"]["usernamemail"], '11CertTools');
    }

    public function sendmail($to, $asunto, $body, $alternbody)
    {
        try {
            $responsedata[ERROR] = false;
            $this->mail->addAddress($to);
            $this->mail->isHTML(true);
            $this->mail->Subject = $asunto;
            $this->mail->Body    = $body;
            $this->mail->AltBody = $alternbody;
            $this->mail->send();
            $responsedata[MESSAGE] = 'Correo enviado exitosamente.';
        } catch (Exception $e) {
            $responsedata[ERROR] = true;
            $responsedata[MESSAGE] = $this->mail->ErrorInfo;
        }
        return $responsedata;
    }

    public function sendmailEvs($to, $cc = null, $asunto, $body, $alternbody)
    {
        try {
            $responsedata[ERROR] = false;
            $this->mail->addAddress($to);
            if (!empty($cc)) {
                if (is_array($cc)) {
                    foreach ($cc as $ccAddress) {
                        // Evita añadir CC vacío por si hubiera nulos
                        if (!empty($ccAddress)) {
                            $this->mail->addCC($ccAddress);
                        }
                    }
                } else {
                    $this->mail->addCC($cc);
                }
            }
            $this->mail->isHTML(true);
            $this->mail->Subject = $asunto;
            $this->mail->Body    = $body;
            $this->mail->AltBody = $alternbody;
            $this->mail->send();
            $responsedata[MESSAGE] = 'Correo enviado exitosamente.';
        } catch (Exception $e) {
            $responsedata[ERROR] = true;
            $responsedata[MESSAGE] = $this->mail->ErrorInfo;
        }
        return $responsedata;
    }

    public function getFile()
    {
        return $this->file;
    }
}
