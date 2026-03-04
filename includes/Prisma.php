<?php
const PRISMA_AUTH_HEADER = 'x-redlock-auth: ';
const ACCEPT_JSON = 'Accept: application/json';

/**
 * Custom exception for Prisma Cloud authentication errors.
 */
class PrismaCloudAuthException extends Exception {}

/**
 * Class for interacting with Prisma Cloud API.
 */
class PrismaCloudAPI
{
    private static $prismaCloudUrl; // URL of the Prisma Cloud instance
    private static $user; // Username for authentication
    private static $password; // Password for authentication
    private static $token; // Stores the authentication token
    private static $tokenCreatedDate; // Stores the date when the token was created

    /**
     * Constructor for PrismaCloudAPI class.
     * @param string $file Path to the configuration file.
     * @param string $encType Encoding type for the configuration file.
     * @throws IllegalArgumentException When unable to parse the configuration file.
     */
    public function __construct(string $file = "config.ini")
    {
        // Attempt to parse the configuration file
        if (!$settings = parse_ini_file($file, true)) {
            // Throw an exception if unable to parse the file
            throw new InvalidArgumentException(sprintf("Unable to parse the configuration file: %s", $file));
        }
        // Assign configuration values
        self::$user = $settings['prisma']['userprisma'];
        self::$password = $settings['prisma']['passprisma'];
        self::$prismaCloudUrl = $settings['prisma']['cloudurl'];

        // Get and store the token upon object creation
        self::$token = $this->getToken();
    }

    /**
     * Retrieves the authentication token from Prisma Cloud API.
     * @return string The authentication token.
     * @throws Exception When unable to retrieve the token.
     */
    private static function getToken()
    {
        // Prepare data for authentication
        $data = json_encode(array(
            "username" => self::$user,
            "password" => self::$password
        ));

        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/login');
        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(CONTENT_TYPE_JSON));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        // Execute cURL session
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        // Decode response
        $responseData = json_decode($response, true);

        // Check if token exists in the response
        if (isset($responseData['token'])) {
            self::$tokenCreatedDate = date('Y-m-d H:i:s');
            return $responseData['token'];
        } else {
            throw new PrismaCloudAuthException('Failed to retrieve token from Prisma Cloud API.');
        }
    }

    /**
     * Gets data from a account.
     * @return string The response from the API.
     */
    public function getAccountInfo($id, $cloud)
    {
        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/cloud/' . $cloud . '/' . $id); //Tengo que modificar para poder poner el tipo de cloud
        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(PRISMA_AUTH_HEADER . self::$token));

        // Execute cURL session
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        // Return the response from the API
        return $response;
    }

    /**
     * Calls the specified Prisma Cloud Compute API.
     * @return string The response from the API.
     */
    public function getPrismaCloud()
    {
        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/cloud');
        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(PRISMA_AUTH_HEADER . self::$token));

        // Execute cURL session
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        // Return the response from the API
        return $response;
    }

    /**
     * Calls the specified Prisma Cloud Compute API.
     * @return string The response from the API.
     */
    public function getPrismaCloudsFromTenant($tenantID)
    {
        //Obtenemos los hijos del tenant específico.
        $ch = curl_init(self::$prismaCloudUrl . '/cloud/azure/' . $tenantID . "/project");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON, // Indica que el cuerpo es JSON
            PRISMA_AUTH_HEADER . self::$token // Token de autenticación
        ));

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * Get Prisma Account groups list.
     * @return string The response from the API.
     */
    public function getPrismaAccountGroups()
    {
        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/cloud/group');
        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(PRISMA_AUTH_HEADER . self::$token));

        // Execute cURL session
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        // Return the response from the API
        return $response;
    }

    /**
     * Dismisses a Prisma Cloud alert.
     * @param array $alertId The ID of the alert to dismiss.
     * @param string $note The note to include with the dismissal.
     * @return string The response from the Prisma Cloud Compute API.
     */
    public function dismissPrismaAlert($alertId, $note = "Alerta descartada desde 11Cert.")
    {
        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/alert/dismiss');

        $data = json_encode(array(
            "alerts" => $alertId,
            "dismissalNote" => $note,
            "filter" => array(
                "timeRange" => null,
                "filters" => null
            )
        ));

        // Set cURL options
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            PRISMA_AUTH_HEADER . self::$token
        ));

        $response = curl_exec($ch);

        // Close cURL session
        curl_close($ch);

        // Return the response from the API
        return $response;
    }

    /**
     * Reopen Prisma Cloud alert.
     * @param array $alertId The ID of the alert to reopen.
     * @param string $note The note to include with the reopening.
     * @return string The response from the Prisma Cloud Compute API.
     */
    public function reopenPrismaAlert($alertId, $note = "Alerta reabierta desde 11Cert.")
    {
        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/alert/reopen');

        $data = json_encode(array(
            "alerts" => $alertId,
            "dismissalNote" => $note,
            "filter" => array(
                "timeRange" => null,
                "filters" => null
            )
        ));

        // Set cURL options
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            PRISMA_AUTH_HEADER . self::$token
        ));

        $response = curl_exec($ch);

        // Close cURL session
        curl_close($ch);

        // Return the response from the API
        return $response;
    }

    /**
     * Retrieves Prisma Cloud alerts filtered by cloud account.
     * @param string $cloud The cloud account for filtering alerts.
     * @return string The response from the Prisma Cloud Compute API.
     */
    public function getPrismaAlertsByCloud($cloud, $filterby = "cloud.account", $status = "open")
    {
        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/v2/alert');
        // Add payload to the request
        $data = json_encode(array(
            "detailed" => false,
            "fields" => array(
                "alert.time",
                "alert.status",
                "policy.severity",
                "policy.type",
                "policy.name",
                "resource.id",
                "resource.name"
            ),
            "filters" => [
                array(
                    "name" => $filterby,
                    "operator" => "=",
                    "value" => $cloud
                ),
                array(
                    "name" => "alert.status",
                    "operator" => "=",
                    "value" => $status
                )
            ]
        ));

        // Set cURL options
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(ACCEPT_JSON, PRISMA_AUTH_HEADER . self::$token, CONTENT_TYPE_JSON));

        $response = curl_exec($ch);

        // Close cURL session
        curl_close($ch);

        // Return the response from the API
        return json_decode($response, true);
    }

    /**
     * Get information about a specific Prisma Cloud alert.
     * @param string $alertId The ID of the alert to retrieve.
     * @return string The response from the Prisma Cloud Compute API.
     */
    public function getPrismaAlertInfo($alertId, $detailed = true)
    {
        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/alert/' . $alertId . "?detailed=$detailed");
        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            PRISMA_AUTH_HEADER . self::$token
        ));

        // Execute cURL session
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        // Return the response from the API
        return $response;
    }

    public function getPrismaAlertInfoV2($alerts, $detailed = false)
    {
        // Initialize cURL session
        $ch = curl_init(self::$prismaCloudUrl . '/v2/alert/');
        // Set cURL options
        if (is_array($alerts)) {
            $itemsFilters = array();
            foreach ($alerts as $alertId) {
                if (isset($alertId["id_alert"])) {
                    $itemsFilters[] = array(
                        "name" => "alert.id",
                        "operator" => "=",
                        "value" => $alertId["id_alert"]
                    );
                } else {
                    $itemsFilters[] = array(
                        "name" => "alert.id",
                        "operator" => "=",
                        "value" => $alertId
                    );
                }
            }
        }
        // if $detailed is true then not set the fields
        $fields = $detailed ? array() : array(
            "alert.time",
            "alert.status",
            "policy.severity",
            "policy.type",
            "policy.name",
            "resource.id",
            "resource.name"
        );
        $data = json_encode(array(
            "detailed" => $detailed,
            "fields" => $fields,
            "filters" => $itemsFilters
        ));

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(ACCEPT_JSON, PRISMA_AUTH_HEADER . self::$token, CONTENT_TYPE_JSON));

        // Execute cURL session
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        // Return the response from the API
        return $response;
    }

    public function getPrismaPoliciesByCloud($cloud, $filterby = "cloud.account", $status = "open")
    {
        try {
            $ch = curl_init(self::$prismaCloudUrl . '/alert/policy');

            $data = json_encode([
                "detailed" => true,
                "filters"  => [
                    [
                        "name"     => $filterby,
                        "operator" => "=",
                        "value"    => $cloud
                    ],
                    [
                        "name"     => "alert.status",
                        "operator" => "=",
                        "value"    => $status
                    ]
                ]
            ]);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                ACCEPT_JSON,
                PRISMA_AUTH_HEADER . self::$token,
                CONTENT_TYPE_JSON
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new PrismaCloudAuthException('cURL error: ' . curl_error($ch));
            }

            curl_close($ch);

            return $response;
        } catch (Exception $e) {
            error_log('Error in getPrismaPolicyByCloud: ' . $e->getMessage());
            return json_encode([
                "error"   => true,
                "message" => "Error in getPrismaPolicyByCloud: " . $e->getMessage()
            ]);
        }
    }

    public function getPrismaAlertsByPolicy($policyId, $cloudName, $status = "open")
    {
        try {
            $body = [
                "detailed" => false,
                "filters" => [
                    [
                        "name" => "policy.id",
                        "operator" => "=",
                        "value" => $policyId
                    ],
                    [
                        "name" => "alert.status",
                        "operator" => "=",
                        "value" => $status
                    ],
                    [
                        "name" => "cloud.accountId",
                        "operator" => "=",
                        "value" => $cloudName
                    ]
                ],
                "timeRange" => [
                    "type" => "to_now",
                    "value" => "epoch"
                ],
                "limit" => 100,
                "pageToken" => "",
                "webClient" => true
            ];

            $url = self::$prismaCloudUrl . '/alert';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                CONTENT_TYPE_JSON,
                PRISMA_AUTH_HEADER . self::$token
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new PrismaCloudAuthException('cURL error: ' . curl_error($ch));
            }

            curl_close($ch);
            return $response;
        } catch (Exception $e) {
            error_log('Error in getPrismaAlertsByPolicy: ' . $e->getMessage());
            return json_encode([
                "error"   => true,
                "message" => "Error in getPrismaAlertsByPolicy: " . $e->getMessage()
            ]);
        }
    }
}
