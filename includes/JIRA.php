<?php

use PhpParser\Node\Stmt\Const_;

const AUTHORIZATION_B = "Authorization: Bearer ";
const CONTENT_TYPE_JSON = "Content-Type: application/json";
const PRIO_LOW = '11678';
const PROJECT_ID = '54830';
const JIRA_API_BASE_URL = "https://jira.tid.es/rest/api";
const LATEST_ISSUE_URL = JIRA_API_BASE_URL . "/latest/issue";
const VERSION_ISSUE_URL = JIRA_API_BASE_URL . "/2/issue";
const EMAIL_DOMAIN = "@telefonica.com";

class InvalidSeverityLevelException extends \Exception {}

class JIRA
{
    private $file = null;
    private $apiToken = null;


    public function __construct(string $file = "config.ini")
    {
        if (!$settings = parse_ini_file($file, true)) {
            throw new InvalidArgumentException(sprintf("Failed to parse config file: %s", $file));
        }
        $this->file = $file;

        $this->apiToken = $settings['JIRA']['apiToken'];
    }

    public function getToken()
    {
        return $this->apiToken;
    }

    public function mostrarIssues($atStart, $maxResults, $projectKey = 'CISOCDCOIN')
    {
        $apiToken = $this->getToken();

        $jql = urlencode("project = $projectKey AND \"Analysis Type\" != \"Architecture Review\"");
        $apiUrl =  JIRA_API_BASE_URL . "/latest/search?jql=" . $jql
            . "&fields=issuetype,issuelinks,analysisType,status,customfield_12611,customfield_24501,customfield_25603,summary,reporter,created"
            . "&startAt=" . $atStart
            . "&maxResults=" . $maxResults;

        $headers = array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON,
        );

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function mostrarIssuesEas($atStart, $maxResults, $projectKey = 'CISOCDCOIN')
    {
        $apiToken = $this->getToken();
        $jql = urlencode("project = $projectKey AND cf[12611] = 'Architecture Review'");

        $allIssues = [];
        $totalIssues = 0;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON
        ]);


        do {
            $apiUrl = JIRA_API_BASE_URL . "/latest/search?jql=" . $jql
                . "&fields=issuetype,issuelinks,analysisType,status,customfield_12611,customfield_24501,customfield_25603,customfield_12609,summary,reporter,created"
                . "&startAt=" . $atStart
                . "&maxResults=" . $maxResults;

            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            $response = curl_exec($ch);

            $issues = json_decode($response, true);

            if (isset($issues["issues"]) && is_array($issues["issues"])) {
                $allIssues = array_merge($allIssues, $issues["issues"]);
            }

            $totalIssues = $issues["total"] ?? 0;
            $atStart += $maxResults;
        } while ($atStart < $totalIssues);

        curl_close($ch);

        return [
            "issues" => $allIssues,
            "total" => count($allIssues)
        ];
    }

    public function adjuntarArchivo($file, $key)
    {
        $apiToken = $this->getToken();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => VERSION_ISSUE_URL . $key . '/attachments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'file' => new CURLFILE($file["tmp_name"], $file["type"], $file["name"])
                // 'name' => $file["name"]
            ),
            CURLOPT_HTTPHEADER => array(
                'X-Atlassian-Token:  nocheck',
                'Accept:  application/json',
                'Authorization:  Bearer ' . $apiToken,
            ),
        ));

        curl_exec($curl);
        curl_close($curl);
    }

    public function adjuntarArchivoAClonadas($issueKey, $fileArray)
    {
        $apiToken = $this->getToken();
        $url = JIRA_API_BASE_URL . "/2/issue/" . $issueKey;
        $headers = array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if (!isset($data['fields']['issuelinks']) || !is_array($data['fields']['issuelinks'])) {
            return;
        }
        foreach ($data['fields']['issuelinks'] as $link) {
            // Buscar la issue clonada (outwardIssue o inwardIssue)
            if (isset($link['outwardIssue']['key'])) {
                $clonedKey = $link['outwardIssue']['key'];
            } elseif (isset($link['inwardIssue']['key'])) {
                $clonedKey = $link['inwardIssue']['key'];
            } else {
                continue;
            }

            $this->adjuntarArchivo($fileArray, $clonedKey);
        }
    }

    private function computeCisoPrio(string $impactoId): array
    {
        $impactoLabels = [
            '11675' => 'Critical',
            '11676' => 'High',
            '11677' => 'Medium',
            PRIO_LOW => 'Low',
        ];

        if (! isset($impactoLabels[$impactoId])) {
            $impactoLabel = 'Medium';
        } else {
            $impactoLabel = $impactoLabels[$impactoId];
        }

        $probabilidadLabel = 'medium';

        $matrizCiso = [
            'Critical' => [
                'low'    => 'Major',    // Impacto=Critical + Probabilidad=Low   → Major
                'medium' => 'Critical', // Impacto=Critical + Probabilidad=Medium→ Critical
                'high'   => 'Critical', // Impacto=Critical + Probabilidad=High  → Critical
            ],
            'High' => [
                'low'    => 'Medium',   // Impacto=High + Probabilidad=Low   → Medium
                'medium' => 'Major',    // Impacto=High + Probabilidad=Medium→ Major
                'high'   => 'Critical', // Impacto=High + Probabilidad=High  → Critical
            ],
            'Medium' => [
                'low'    => 'Low',      // Impacto=Medium + Probabilidad=Low   → Low
                'medium' => 'Medium',   // Impacto=Medium + Probabilidad=Medium→ Medium
                'high'   => 'Major',    // Impacto=Medium + Probabilidad=High  → Major
            ],
            'Low' => [
                'low'    => 'Low',      // Impacto=Low + Probabilidad=Low   → Low
                'medium' => 'Low',      // Impacto=Low + Probabilidad=Medium→ Low
                'high'   => 'Medium',   // Impacto=Low + Probabilidad=High  → Medium
            ],
        ];

        $probKey = strtolower($probabilidadLabel);

        if (! isset($matrizCiso[$impactoLabel]) || ! isset($matrizCiso[$impactoLabel][$probKey])) {
            $labelCiso = 'Medium';
        } else {
            $labelCiso = $matrizCiso[$impactoLabel][$probKey];
        }

        $cisoPriorityMap = [
            'Critical'   => '36966',
            'Major'      => '36967',
            'Medium'     => '36968',
            'Low'        => '36969',
            'Informative' => '44072',
        ];

        if (! isset($cisoPriorityMap[$labelCiso])) {
            return ['id' => '36968'];
        }

        return ['id' => $cisoPriorityMap[$labelCiso]];
    }

    public function generateExcel(array $alertIDs, $outputPath)
    {
        $db = new Revision(DB_SERV);
        $data = $db->getVulnInfoByAlertIds($alertIDs);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['ID Alerta', 'Nombre Política', 'Nombre Recurso', 'ID Recurso'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['id_alert'] ?? '');
            $sheet->setCellValue('B' . $row, $item['name'] ?? '');
            $sheet->setCellValue('C' . $row, $item['resource_name'] ?? '');
            $sheet->setCellValue('D' . $row, $item['resource_id'] ?? '');
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($outputPath);
        return $outputPath;
    }

    public function crearIssueArquitectura($parametros, $sawInfos, $alertIDsString)
    {
        $sawCods  = array();
        $sawNames = array();
        foreach ($sawInfos as $info) {
            $row = isset($info[0]) ? $info[0] : $info;
            if (!in_array($row['cod'],  $sawCods,  true)) {
                $sawCods[]  = $row['cod'];
            }
            if (!in_array($row['name'], $sawNames, true)) {
                $sawNames[] = $row['name'];
            }
        }
        $sawCodString  = implode(', ', $sawCods);
        $sawNameString = implode(', ', $sawNames);

        $lines = array(
            "Alert reported from [11CERTOOLS|https://11certools.cisocdo.com/]",
            "",
            $parametros["Nombre"],
            "",
            "- Suscription:" . $parametros["CloudId"],
            "- Alert ID: "   . $alertIDsString,
            "- Date: "       . $parametros["Fecha"],
            "- Status: "     . $parametros["Status"],
            "- Severity: "   . $parametros["Severity"],
            "",
            "- Description: " . $parametros["Descripcion"],
            "- Saw ID: "     . $sawCodString,
            "- Saw Name: "   . $sawNameString,
            "",
            "- Resolution:",
            $parametros["Resolucion"],
        );
        $description = implode("\n", $lines);

        $alertIDs = array_map('trim', explode(',', $alertIDsString));
        $tmpExcel = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alertas_' . uniqid() . '.xlsx';
        $this->generateExcel($alertIDs, $tmpExcel);

        $apiToken = $this->getToken();
        $apiUrl   = LATEST_ISSUE_URL;

        $severityMap = [
            'crítica' => '11675',
            'alto'     => '11676',
            'mediano'   => '11677',
            'bajo'      => PRIO_LOW,
            'información' => PRIO_LOW,
        ];
        $sev = $parametros['Severity'];
        if (!isset($severityMap[$sev])) {
            throw new InvalidSeverityLevelException("Invalid severity level: $sev");
        }
        $severityId = $severityMap[$sev];

        $cisoPriority = $this->computeCisoPrio($severityId);

        $issueData = array(
            "fields" => array(
                "project"           => array("key" => "CISOCDCOIN"),
                "summary"           => $parametros["Nombre"],
                "issuetype"         => array("id" => "36"),
                "customfield_25603" => $cisoPriority,
                "customfield_12609" => array("id" => $severityId),
                "reporter"          => array("name" => "CISOArqSeg"),
                "customfield_12611" => array("id" => "11686"),
                "customfield_12700" => array("id" => "11760"),
                "project"           => array("id" => PROJECT_ID),
                "customfield_24501" => array("value" => $parametros["Proyecto"]),
                "customfield_29100" => $alertIDsString,
                "customfield_25704" => array(
                    "value" => "OSA",
                    "child" => array("value" => $sawCodString)
                ),
                "description"       => $description
            )
        );

        if (!empty($parametros["tags"])) {
            $tags = explode(',', $parametros["tags"]);
            $tags = array_map('trim', $tags);
            $tags = array_filter($tags);

            if (!empty($tags)) {
                $issueData["fields"]["labels"] = $tags;
            }
        }


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,            $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON
        ));
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($issueData));
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $response_data = array();
        $response_data["error"]     = isset($response["errors"]);
        $response_data["Execution"] = $response["key"] ?? null;

        if (!$response_data["error"] && !empty($response_data["Execution"])) {
            $fileArray = array(
                "tmp_name" => $tmpExcel,
                "type"     => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                "name"     => basename($tmpExcel)
            );
            $this->adjuntarArchivo($fileArray, $response_data["Execution"]);
            $this->adjuntarArchivoAClonadas($response_data["Execution"], $fileArray);

            if (!empty($parametros["observaciones"])) {
                $this->enviarComentarios($response_data["Execution"], $parametros["observaciones"]);

                $apiToken = $this->getToken();
                $url = JIRA_API_BASE_URL . "/2/issue/" . $response_data["Execution"];
                $headers = array(
                    AUTHORIZATION_B . $apiToken,
                    CONTENT_TYPE_JSON
                );
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $responseIssue = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($responseIssue, true);
                if (isset($data['fields']['issuelinks']) && is_array($data['fields']['issuelinks'])) {
                    foreach ($data['fields']['issuelinks'] as $link) {
                        if (isset($link['outwardIssue']['key'])) {
                            $clonedKey = $link['outwardIssue']['key'];
                        } elseif (isset($link['inwardIssue']['key'])) {
                            $clonedKey = $link['inwardIssue']['key'];
                        } else {
                            continue;
                        }
                        $this->enviarComentarios($clonedKey, $parametros["observaciones"]);
                    }
                }
            }
        }
        @unlink($tmpExcel);

        return $response_data;
    }

    public function crearIssue($parametros)
    {
        $db_pentest = new Pentest(DB_SERV);
        $pentest = $db_pentest->obtainAllPentest($parametros["pentest"]);
        $apiToken = $this->getToken();
        $apiUrl = LATEST_ISSUE_URL;
        $issueData = array(
            "fields" => array(
                "project" => array(
                    "key" => "CISOCDCOIN"
                ),
                'summary' => $parametros["Resumen"],
                'issuetype' => array(
                    'id' => '36'
                ),
                'customfield_25603' => array(
                    'value' => $parametros["Prioridad"]
                ),
                'customfield_25704' => array(
                    'value' => $parametros["metodologia"],
                    'child' => array(
                        'value' => $parametros["Definicion"]
                    )
                ),
                'reporter' => array(
                    'name' => $parametros["Informador"],
                ),
                'customfield_12611' => array(
                    'value' => $parametros["radio"],
                ),
                'customfield_12609' => array(
                    'value' => $parametros["VulImpact"]
                ),
                'customfield_12610' => array(
                    'value' => $parametros["ExpProb"]
                ),
                'customfield_12700' => array(
                    'value' => $parametros["VulnStatus"]
                ),
                'customfield_12800' => $parametros["URL"],
                'project' => array(
                    'id' => PROJECT_ID
                ),
                'customfield_24501' => array(
                    'value' => $pentest[0]["proyecto"]
                ),
                'description' => $parametros["Descrip"]
            )
        );
        $headers = array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON,
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true); // Use POST method
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($issueData)); // Encode data as JSON
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $response_data = array();
        if (isset($response["errors"])) {
            $response_data["Error"] = true;
        } else {
            $response_data["Error"] = false;
        }
        $response_data["Execution"] = $response["key"];
        return $response_data;
    }

    public function createIncident($parametros)
    {
        $jiraIssueTypeId = "";
        $priorityId = "";

        if (isset($parametros["issueType"])) {
            if ($parametros["issueType"] === "Incidencia") {
                $priorityId = "3";
                $jiraIssueTypeId = "12800";
            } elseif ($parametros["issueType"] === "User story") {
                $priorityId = "4";
                $jiraIssueTypeId = "7";
            }
        }

        if (isset($parametros["user_mail"])) {
            $reporterId = $this->obtainReporterID($parametros["user_mail"]);
            if ($reporterId !== "Invalid") {
                $parametros["Informador"] = $reporterId;
            }
        }

        $apiToken = $this->getToken();
        $apiUrl = LATEST_ISSUE_URL;

        $reporterId = $this->obtainReporterID($parametros["user_mail"]);
        if ($reporterId !== "Invalid") {
            $parametros["user_mail"] = $reporterId;
        }

        $issueData = array(
            "fields" => array(
                "project" => array(
                    "key" => "ELEVENCERT"
                ),
                "summary" => $parametros["summary"],
                "issuetype" => array(
                    "id" => $jiraIssueTypeId
                ),
                "priority" => array(
                    "id" => $priorityId
                ),
                "reporter" => array(
                    "name" => $parametros["user_mail"]
                ),
                "description" => $parametros["description"]
            )
        );

        if (isset($parametros["issueType"]) && $parametros["issueType"] === "Incidencia") {
            $issueData["fields"]["customfield_11100"] = array("id" => "10860");
        }

        $headers = array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON,
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($issueData));

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $response_data = array();
        if (isset($response["errors"])) {
            $response_data["Error"] = true;
        } else {
            $response_data["Error"] = false;
        }
        $response_data["Execution"] = $response["key"];

        return $response_data;
    }

    public function compObligatorio($parametros)
    {
        $arrayCompletos = array(
            "Error" => false,
            "Pentest" => true,
            "Resumen" => true,
            "Prioridad" => true,
            "Metodologia" => true,
            "Definicion" => true,
            "Informer" => true,
            "AnalysisType" => true,
            "Impacto" => true,
            "ProbExplotacion" => true,
            "StatusVuln" => true,
            "URL" => true
        );
        if ($parametros["Resumen"] == "") {
            $arrayCompletos["Resumen"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["Prioridad"] == "Ninguno") {
            $arrayCompletos["Prioridad"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["metodologia"] == "Ninguna") {
            $arrayCompletos["Metodologia"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["Definicion"] == "Ninguna") {
            $arrayCompletos["Definicion"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["Informador"] == "" || $parametros["Informador"] == "Invalid") {
            $arrayCompletos["Informer"] = false;
            $arrayCompletos["Error"] = true;
        }
        if (!isset($parametros["radio"])) {
            $arrayCompletos["AnalysisType"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["VulImpact"] == "Ninguno") {
            $arrayCompletos["Impacto"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["ExpProb"] == "Ninguno") {
            $arrayCompletos["ProbExplotacion"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["VulnStatus"] == "Ninguno") {
            $arrayCompletos["StatusVuln"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["URL"] == "") {
            $arrayCompletos["URL"] = false;
            $arrayCompletos["Error"] = true;
        }

        return $arrayCompletos;
    }

    public function obtainUsers($proyecto)
    {
        $apiToken = $this->getToken();
        $baseUrl = JIRA_API_BASE_URL . '/2/user/assignable/search?project=' . $proyecto . '&maxResults=200';

        $url = $baseUrl;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            AUTHORIZATION_B . $apiToken // Usa el token de acceso en el encabezado de autorización
        ));

        $response = curl_exec($ch);

        curl_close($ch);
        return json_decode($response, true);
    }

    public function updateStatus($accion, $issueKey)
    {
        $apiToken = $this->getToken();

        if ($accion == "abrir") {
            $transitionId = 31;
        } else {
            $transitionId = 11;
        }
        $baseUrl = VERSION_ISSUE_URL;
        $url = $baseUrl . "/" . $issueKey . '/transitions';

        $data = array(
            'transition' => array('id' => $transitionId)
        );

        $headers = array(
            CONTENT_TYPE_JSON,
            AUTHORIZATION_B . $apiToken, // Reemplaza YOUR_TOKEN_HERE con tu token de autenticación
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode == 204) {
            return 'Issue transition successful.';
        } else {
            return 'Issue transition failed. HTTP Code: ' . $httpCode;
        }
    }

    public function updateClone($key, $parametros)
    {
        $db_pentest = new Pentest(DB_SERV);
        $jira = new JIRA();
        $pentest = $db_pentest->obtainAllPentest($parametros["pentest"]);
        $responsable = $jira->obtainReporterID($pentest[0]["resp_proyecto"]);
        $apiToken = $this->getToken();

        $issueId = $key;
        $baseUrl = LATEST_ISSUE_URL;
        $url = $baseUrl . $issueId;

        $headers = array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON
        );

        $data = array(
            "fields" => array(
                "project" => array(
                    "key" => "CISOCDCOIN"
                ),
                'assignee' => array(
                    'name' => $responsable
                )
            )
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return $httpCode;
    }


    public function updateIssueExterna($key, $parametros)
    {
        $apiToken = $this->getToken();

        $issueId = $key;
        $baseUrl = LATEST_ISSUE_URL;
        $url = $baseUrl . $issueId;

        $headers = array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON
        );

        $data = array(
            "fields" => array(
                "project" => array(
                    "key" => $parametros["AreaServ"]
                ),
                'summary' => "CISO COPY - " . $parametros["Resumen"],
                'issuetype' => array(
                    'id' => '36'
                ),
                'customfield_25603' => array(
                    'value' => $parametros["Prioridad"]
                ),
                'reporter' => array(
                    'name' => $parametros["Informador"],
                ),
                'customfield_12611' => array(
                    'value' => $parametros["radio"],
                ),
                'customfield_12609' => array(
                    'value' => $parametros["VulImpact"]
                ),
                'customfield_12610' => array(
                    'value' => $parametros["ExpProb"]
                ),
                'customfield_12700' => array(
                    'value' => $parametros["VulnStatus"]
                ),
                'customfield_12800' => $parametros["URL"],
                'project' => array(
                    'id' => PROJECT_ID
                ),
                'description' => $parametros["Descrip"]
            )
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode == 204) {
            return "Resumen actualizado con éxito.";
        } else {
            return "Error al actualizar el resumen. Código de respuesta: $httpCode";
        }
    }


    public function updateIssue($key, $parametros, $proyecto = "CISOCDCOIN")
    {
        $apiToken = $this->getToken();

        $issueId = $key;
        $baseUrl = LATEST_ISSUE_URL;
        $url = $baseUrl . $issueId;

        $headers = array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON
        );

        $data = array(
            "fields" => array(
                "project" => array(
                    "key" => $proyecto
                ),
                'summary' => $parametros["Resumen"],
                'issuetype' => array(
                    'id' => '36'
                ),
                'customfield_25603' => array(
                    'value' => $parametros["Prioridad"]
                ),
                'customfield_25704' => array(
                    'value' => $parametros["metodologia"],
                    'child' => array(
                        'value' => $parametros["Definicion"]
                    )
                ),
                'reporter' => array(
                    'name' => $parametros["Informador"],
                ),
                'customfield_12611' => array(
                    'value' => $parametros["radio"],
                ),
                'customfield_12609' => array(
                    'value' => $parametros["VulImpact"]
                ),
                'customfield_12610' => array(
                    'value' => $parametros["ExpProb"]
                ),
                'customfield_12700' => array(
                    'value' => $parametros["VulnStatus"]
                ),
                'customfield_12800' => $parametros["URL"],
                'project' => array(
                    'id' => PROJECT_ID
                ),
                'customfield_24501' => array(
                    'value' => $parametros["AreaServ"]
                ),
                'description' => $parametros["Descrip"]
            )
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode == 204) {
            return "Resumen actualizado con éxito.";
        } else {
            return "Error al actualizar el resumen. Código de respuesta: $httpCode";
        }
    }

    public function enviarComentarios($jiraKey, $comentario)
    {
        $apiToken = $this->getToken();

        $baseUrl = VERSION_ISSUE_URL . "/" . $jiraKey . "/comment";

        $data = array(
            "body" => $comentario
        );
        $ch = curl_init($baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            AUTHORIZATION_B . $apiToken // Usa el token de acceso en el encabezado de autorización
        ));

        curl_exec($ch);
        curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $comentario;
    }

    public function obtenerComentarios($jiraKey)
    {
        $apiToken = $this->getToken();
        $baseUrl = VERSION_ISSUE_URL . "/" . $jiraKey . '/comment';

        $url = $baseUrl;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            AUTHORIZATION_B . $apiToken // Usa el token de acceso en el encabezado de autorización
        ));

        $response = curl_exec($ch);
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        return json_decode($response, true);
    }

    public function eliminarIssue($key)
    {
        $apiToken = $this->getToken();
        $url = VERSION_ISSUE_URL . "/" . $key;
        $headers = array(
            AUTHORIZATION_B . $apiToken,
            CONTENT_TYPE_JSON
        );

        // Configuración de la solicitud DELETE
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Realizar la solicitud
        $response = curl_exec($ch);
        if ($response == "") {
            $db = new Pentest(DB_SERV);
            $db->eliminarIssueTabla($key);
        }
        return $response;
    }

    public function obtenerCampos()
    {
        $apiToken = $this->getToken();
        $baseUrl = VERSION_ISSUE_URL . '/createmeta/CISOCDCOIN/issuetypes/36';

        $url = $baseUrl;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            AUTHORIZATION_B . $apiToken // Usa el token de acceso en el encabezado de autorización
        ));

        $response = curl_exec($ch);
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        return json_decode($response, true);
    }

    public function gestionarPentest($parametros)
    {
        $db = new Pentest(DB_SERV);
        $db_new = new Pentest("octopus_new");
        $id_pentest = $db->obtainPentestID($parametros["pentest"]);
        $id_vuln = $db_new->obtainVulnID($parametros["vuln"]);
        //Especificar que no se ha podido meter la issue bien.
        $id_issue = $parametros["key"];
        $db->insertVulnPentest($id_issue, $id_pentest[0]["id"], $id_vuln[0]["id"]);
    }

    public function obtainReporterID($reporterName)
    {
        $apiToken = $this->getToken();
        $reporterName = urlencode($reporterName);
        $baseUrl = JIRA_API_BASE_URL . '/2/user/search?username="' . $reporterName . '"';

        $url = $baseUrl;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            AUTHORIZATION_B . $apiToken
        ));

        $response = curl_exec($ch);
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        $campos = json_decode($response, true);

        if (!is_array($campos) || count($campos) < 1) {
            return "Invalid";
        }

        foreach ($campos as $campo) {
            if (isset($campo['emailAddress']) && substr_compare($campo['emailAddress'], EMAIL_DOMAIN, -strlen(EMAIL_DOMAIN)) === 0) {
                return $campo['name'];
            }
        }

        return "Invalid";
    }

    public function obtainReporterMail($reporterName)
    {
        $apiToken = $this->getToken();
        $reporterName = urlencode($reporterName);
        $baseUrl = JIRA_API_BASE_URL . '/2/user/search?username="' . $reporterName . '"';

        $url = $baseUrl;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            AUTHORIZATION_B . $apiToken
        ));

        $response = curl_exec($ch);
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        $campos = json_decode($response, true);

        if (!is_array($campos) || count($campos) < 1) {
            return "Invalid";
        }

        foreach ($campos as $campo) {
            if (isset($campo['emailAddress']) && substr_compare($campo['emailAddress'], EMAIL_DOMAIN, -strlen(EMAIL_DOMAIN)) === 0) {
                return $campo['emailAddress'];
            }
        }

        return "Invalid";
    }

    public function comprobarPentest($parametros)
    {
        $arrayCompletos = array(
            "Error" => false,
            "Nombre" => true,
            "Nombre_repe" => true,
            "Responsable" => true,
            "Descripcion" => true,
            "Fecha_inicio" => true,
            "Fecha_final" => true,
            "Organizacion" => true,
            "Direccion" => true,
            "Area" => true,
            "Proyecto" => true,
            "AreaServicio" => true,
            "ResponsableProy" => true,
        );
        if ($parametros["Nombre"] == "") {
            $arrayCompletos["Nombre"] = false;
            $arrayCompletos["Error"] = true;
        } else {
            $db = new Pentest(DB_SERV);
            $resultados = $db->obtainPentestID($parametros["Nombre"]);
            if (count($resultados) != 0) {
                $arrayCompletos["Nombre_repe"] = false;
                $arrayCompletos["Error"] = true;
            }
        }
        if ($parametros["Responsable"] == "") {
            $arrayCompletos["Responsable"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["Descripcion"] == "") {
            $arrayCompletos["Descripcion"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["Fecha_inicio"] == "") {
            $arrayCompletos["Fecha_inicio"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["Fecha_final"] == "") {
            $arrayCompletos["Fecha_final"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["Organizacion"] == "Ninguno") {
            $arrayCompletos["Organizacion"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["Direccion"] == "Ninguno") {
            $arrayCompletos["Direccion"] = false;
        } elseif ($parametros["Area"] == "Ninguno") {
            $arrayCompletos["Area"] = false;
        }
        if ($parametros["Producto"] == "Ninguno" || $parametros["Producto"] == "") {
            $arrayCompletos["Producto"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["AreaServ"] == "Ninguno") {
            $arrayCompletos["AreaServicio"] = false;
            $arrayCompletos["Error"] = true;
        }
        if ($parametros["ResponsableProy"] == "") {
            $arrayCompletos["ResponsableProy"] = false;
            $arrayCompletos["Error"] = true;
        }

        return $arrayCompletos;
    }

    public function obtenerIssuesInfoEspecifica(array $jiraKeys)
    {
        $apiToken = $this->getToken();
        $url = "https://jira.tid.es/rest/api/2/search";

        $jqlKeys = implode('","', $jiraKeys);
        $jql = 'key in ("' . $jqlKeys . '")';

        $data = array(
            "jql" => $jql,
            "fields" => ["status", "customfield_25603", "summary", "issuelinks", "created"]
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken
        ));

        $response = curl_exec($ch);
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        return json_decode($response, true);
    }

    public function obtenerIssue($jiraKey)
    {
        $apiToken = $this->getToken();
        $url = JIRA_API_BASE_URL . "/2/search";

        $data = array(
            "jql" => 'key="' . $jiraKey . '"',
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            CONTENT_TYPE_JSON,
            AUTHORIZATION_B . $apiToken // Usa el token de acceso en el encabezado de autorización
        ));

        $response = curl_exec($ch);
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        return json_decode($response, true);
    }

    public function gestionarVulnerabilidades($vulnerabilidades, $estado)
    {
        foreach ($vulnerabilidades as $index => $vuln) {
            $issue = $this->obtenerIssue($vuln["id_issue"]);
            $issue = $issue["issues"][0]["fields"];
            $prioridad = $issue["customfield_25603"];
            $status = $issue["status"]["name"];
            if ($prioridad["value"] == "Low" || $status != $estado) {
                unset($vulnerabilidades[$index]);
            }
        }
        return $vulnerabilidades;
    }

    public function gestionarSistemasRevision($idRevision)
    {
        $db_new = new Pentest("octopus_new");
        $db_serv = new Revision(DB_SERV);
        $vulnerabilidades = $db_serv->obtenerVulnsRevisionReportadas($idRevision);
        $vulnsOpen = $this->gestionarVulnerabilidades($vulnerabilidades, "Abierta");
        $vulnsClose = $this->gestionarVulnerabilidades($vulnerabilidades, "Cerrada");
        $activos = $db_serv->obtenerActivosRevision($idRevision);

        $usfsAbiertos = [];
        $usfsCerrados = [];
        foreach ($vulnsOpen as $vuln) {
            $saw = $db_serv->getSawAlert($vuln["id_alert"], $idRevision);
            $usfs = $db_new->getUsfBySaw($saw[0]["id"]);

            foreach ($usfs as $usf) {
                $preguntas_USF = $db_new->getPreguntasByUSF($usf["usf_id"]);
                array_push($usfsAbiertos, $preguntas_USF);
            }
        }
        foreach ($vulnsClose as $vuln) {
            $saw = $db_serv->getSawAlert($vuln["id_alert"], $idRevision);
            $usfs = $db_new->getUsfBySaw($saw[0]["id"]);
            foreach ($usfs as $usf) {
                $preguntas_USF = $db_new->getPreguntasByUSF($usf["usf_id"]);
                array_push($usfsCerrados, $preguntas_USF);
            }
        }

        foreach ($activos as $activo) {
            generarEvaluacionTrasPrueba(
                $usfsAbiertos,
                $usfsCerrados,
                $activo,
                "Revision"
            );
        }
    }

    public function gestionarSistemas($idPentest)
    {
        $db = new Pentest(DB_SERV);
        $db_new = new Pentest("octopus_new");
        $db_activos = new Activos(DB_SERV);

        $vulnerabilidades = $db->obtenerVulnsPentest($idPentest);
        $vulnOpen = $this->gestionarVulnerabilidades($vulnerabilidades, "Abierta");
        $vulnClose = $this->gestionarVulnerabilidades($vulnerabilidades, "Cerrada");
        $activos = $db->obtenerActivosPentest($idPentest);

        $usfsAbiertos = $this->getPreguntasPorUsfs($db_new, $db_new->obtenerUsfPentest($vulnOpen));
        $usfsCerrados = $this->getPreguntasPorUsfs($db_new, $db_new->obtenerUsfPentest($vulnClose));

        foreach ($activos as $activo) {
            $result = $this->procesarActivoPentest($db, $db_activos, $activo, $usfsAbiertos, $usfsCerrados);
            if ($result !== true) {
                return $result;
            }
        }
    }

    private function getPreguntasPorUsfs($db_new, $usfs)
    {
        $result = [];
        foreach ($usfs as $index => $usf) {
            $result[$index] = $db_new->getPreguntasByUSF($usf["usf_id"]);
        }
        return $result;
    }

    private function procesarActivoPentest($db, $db_activos, $activo, $usfsAbiertos, $usfsCerrados)
    {
        $activo_tipo = $db_activos->obtenerTipoActivo($activo["id_activo"]);
        if ($activo_tipo[0]["activo_id"] != 33) {
            return true;
        }

        return $this->procesarActivoEspecial($db, $activo, $usfsAbiertos, $usfsCerrados);
    }

    private function procesarActivoEspecial($db, $activo, $usfsAbiertos, $usfsCerrados)
    {
        $fecha = $db->getFechaEvaluaciones($activo["id_activo"], true);
        if (!isset($fecha[0])) {
            return NO_EVALUATIONS;
        }

        $preguntas = $this->obtenerPreguntasPorFecha($db, $fecha[0]);
        if ($preguntas === null) {
            return NO_EVALUATIONS;
        }

        $preguntas = $this->actualizarPreguntasConUsfs($preguntas, $usfsCerrados, "1");
        $preguntas = $this->actualizarPreguntasConUsfs($preguntas, $usfsAbiertos, "0");

        return $this->guardarEvaluacion($db, $fecha, $preguntas);
    }

    private function guardarEvaluacion($db, $fecha, $preguntas)
    {
        $index = array_search("evaluaciones", array_column($fecha, "tipo_tabla"));
        if ($index === false) {
            return NO_EVALUATIONS;
        }

        $id_version = ($fecha[0]["tipo_tabla"] == "evaluaciones_versiones") ? $fecha[0]["id"] : null;
        $db->editEval($fecha[$index]["id"], $id_version, $preguntas, "Evaluacion de Pentest");
        return true;
    }

    private function obtenerPreguntasPorFecha($db, $fecha)
    {
        if ($fecha["tipo_tabla"] == "evaluaciones_versiones") {
            $preguntas = $db->getPreguntasVersionByFecha($fecha["id"]);
        } else {
            $preguntas = $db->getPreguntasEvaluacionByFecha($fecha["id"]);
        }
        if (isset($preguntas[0])) {
            return json_decode($preguntas[0]["preguntas"], true);
        }
        return null;
    }

    private function actualizarPreguntasConUsfs($preguntas, $usfs, $valor)
    {
        foreach ($usfs as $usf) {
            foreach ($usf as $preguntasUSF) {
                $preguntas[$preguntasUSF["id_preguntas"]] = $valor;
            }
        }
        return $preguntas;
    }

    public function activosPentest($parametros)
    {
        $db = new Pentest(DB_SERV);
        $id = $db->obtainPentestID($parametros["Nombre"]);
        $db->insertActivosPentest($id, $parametros["Producto"]);
    }
}
