<?php

class KiuwanApiException extends Exception {}

class Kiuwan
{
    private $kiuwanUsername;
    private $kiuwanPassword;
    private $kiuwanDomainId;
    private $file;

    public function __construct(string $file = "config.ini")
    {
        if (!$settings = parse_ini_file($file, true)) {
            throw new InvalidArgumentException("Unable to parse the configuration file: " . $file);
        }

        $this->file = $file;
        $this->kiuwanUsername = $settings['Kiuwan']['kiuwanUsername'] ?? '';
        $this->kiuwanPassword = $settings['Kiuwan']['kiuwanPassword'] ?? '';
        $this->kiuwanDomainId = $settings['Kiuwan']['kiuwanDomainId'] ?? '';

        if (empty($this->kiuwanUsername) || empty($this->kiuwanPassword)) {
            throw new InvalidArgumentException("Username or password is missing in the configuration file.");
        }
    }

    private function encodeAuth()
    {
        $credentials = $this->kiuwanUsername . ':' . $this->kiuwanPassword;
        return base64_encode($credentials);
    }

    public function getApplications($endpoint)
    {
        try {
            $encoded_auth = $this->encodeAuth();
            $base_url = 'https://api.kiuwan.com' . $endpoint;

            $headers = [
                'Content-Type: application/json',
                'Authorization: Basic ' . $encoded_auth,
                'X-KW-CORPORATE-DOMAIN-ID: ' . $this->kiuwanDomainId
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $base_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new KiuwanApiException('Error connecting to Kiuwan API: ' . $httpCode);
            }

            $applications = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new KiuwanApiException('Error decoding JSON response from Kiuwan API: ' . json_last_error_msg());
            }

            if (is_array($applications) && count($applications) > 0) {
                return $applications;
            } else {
                throw new KiuwanApiException('No se encontraron aplicaciones en la respuesta de Kiuwan.');
            }
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function getLastAnalysis($endpoint)
    {
        return $this->executeMultiCurlRequests([$endpoint]);
    }

    public function executeMultiCurlRequests(array $endpoints)
    {
        $multiCurl = curl_multi_init();
        $curlArray = [];
        $responses = [];

        $encoded_auth = $this->encodeAuth();
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . $encoded_auth,
            'X-KW-CORPORATE-DOMAIN-ID: ' . $this->kiuwanDomainId
        ];

        // Iniciar las múltiples solicitudes cURL
        foreach ($endpoints as $key => $endpoint) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.kiuwan.com' . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_multi_add_handle($multiCurl, $ch);
            $curlArray[$key] = $ch;
        }

        // Ejecutar todas las solicitudes en paralelo
        $running = null;
        do {
            curl_multi_exec($multiCurl, $running);
        } while ($running);

        // Obtener las respuestas y cerrar los handles
        foreach ($curlArray as $key => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($multiCurl, $ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new KiuwanApiException('Error connecting to Kiuwan API: ' . $httpCode);
            }

            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new KiuwanApiException('Error decoding JSON response from Kiuwan API: ' . json_last_error_msg());
            }

            $responses[$key] = $decodedResponse;
        }

        curl_multi_close($multiCurl);

        return $responses;
    }
}
