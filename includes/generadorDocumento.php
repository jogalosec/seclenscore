<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'prisma.php';
require_once 'JIRA.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class GeneradorDocumentoException extends Exception {}

abstract class GeneradorDocumento
{

    protected $templatePath;

    public function __construct($templatePath)
    {
        if (!file_exists($templatePath)) {
            throw new GeneradorDocumentoException("La plantilla no existe en la ruta proporcionada.");
        }
        $this->templatePath = $templatePath;
    }

    /**
     * Método principal para generar el documento Word a partir de una plantilla y datos proporcionados.
     *
     * @param array $valores Claves y valores para reemplazar en la plantilla.
     * @param string $nombreArchivo Nombre del archivo de salida.
     * @return void
     */
    public function generarDocumento($templateProcessor, $nombreArchivo)
    {

        $temp_file = tempnam(sys_get_temp_dir(), 'PHPWord');
        $templateProcessor->saveAs($temp_file);

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename=' . $nombreArchivo);

        readfile($temp_file);
        unlink($temp_file);
        exit;
    }
}

class GeneradorDocumentoRevision extends GeneradorDocumento
{
    public function obtenerDatos($id, $alertasAsignadas = null)
    {
        $db = new Revision("octopus_serv");
        $activos = new activos("octopus_serv");
        $users = new Usuarios("octopus_users");

        $revision = $db->obtainRevisionFromId($id);
        $activosRevision = $db->obtenerActivosRevision($id);
        $productoId = $activosRevision[0]['id_activo'];
        $nombreProducto = getParentescobySistemaId(array($productoId), 67);

        if ($nombreProducto['nombre'] == "") {
            $activosServicio = $activos->getActivo($productoId, "admin");
            if ($activosServicio && isset($activosServicio[0])) {
                $nombreProducto["nombre"] = $activosServicio[0]["nombre"];
            } else {
                $nombreProducto["nombre"] = "Sin producto asociado";
            }
        }

        $suscriptionName = $db->getSuscriptionNameBySusId($revision[0]['cloudId']);
        $user_data = $users->getUser($revision[0]['user_id']);
        $arquitecto = $user_data[0]['email'] ?? null;

        if (is_array($revision) && isset($revision[0])) {
            $revision = $revision[0];
        }
        if (!$revision || empty($revision['cloudId'])) {
            throw new GeneradorDocumentoException("Revisión no encontrada o cloudId ausente.");
        }

        $revision['service_name'] = $nombreProducto["nombre"];
        $revision['suscription_name'] = $suscriptionName[0]['suscription_name'] ?? 'Sin suscripción asociada';
        $revision['arquitecto'] = $arquitecto ?? 'Sin arquitecto asignado';
        $revision['cloudId'] = $revision['cloudId'] ?? 'Sin cloudId';

        if ($alertasAsignadas !== null) {
            if (!is_array($alertasAsignadas) || empty($alertasAsignadas)) {
                throw new GeneradorDocumentoException("Debe proporcionar un array de alertas asignadas válido.");
            }

            $issues = $db->getIssueKeyByRevisionId($id, $alertasAsignadas);

            $jira = new Jira();
            $datos_issues = [];

            foreach ($issues as $issueKey) {
                $issueData = $jira->obtenerIssue($issueKey);

                if (isset($issueData['errorMessages'])) {
                    throw new GeneradorDocumentoException(
                        "Error al obtener los datos de la issue $issueKey: "
                            . implode(", ", $issueData['errorMessages'])
                    );
                }

                $fields = $issueData['issues'][0]['fields'];

                $datos_issues[$issueKey] = [
                    'summary' => $fields['summary'] ?? null,
                    'description' => $fields['description'] ?? null,
                    'customfield_25603_value' => $fields['customfield_25603']['value'] ?? null,
                    'childIssue' => $fields['issuelinks'][0]['inwardIssue']['key'] ?? null
                ];
            }

            $revision['issues'] = $datos_issues;
        }

        return $revision;
    }

    // Hacer la plantilla del mail par enviarlo, modal antes de enviar pidiendo observaciones y mails, y cerrar la revisión después de enviar el mail
    public function refillEasDocument($id, $alertasAsignadas = null)
    {
        $revision = $this->obtenerDatos($id, $alertasAsignadas);

        if ($revision) {
            $templateProcessor = new TemplateProcessor($this->templatePath);
            $templateProcessor->setMacroChars('{', '}');

            // Valores generales
            $valores = [
                'service_name' => $revision['service_name'] ?? 'Sin producto asociado',
                'fecha_final' => $revision['fecha_final'] ?? 'Sin fecha final prevista',
                'arquitecto' => $revision['arquitecto'] ?? 'Sin arquitecto asignado',
                'suscription_name' => $revision['suscription_name'] ?? 'Sin suscription asignada',
                'observaciones' => $revision['observaciones'] ?? 'No hay comentarios adicionales.',
                'cloudId' => $revision['cloudId'] ?? 'Sin cloudId'
            ];

            foreach ($valores as $clave => $valor) {
                $templateProcessor->setValue($clave, $valor);
            }

            $issues = $revision['issues'] ?? [];
            $totalVuln = count($issues);
            $templateProcessor->setValue('n', $totalVuln);

            $severityMap = [
                'Critical'    => 'Crítica',
                'Major'       => 'Alta',
                'Medium'      => 'Media',
                'Low'         => 'Baja'
            ];

            $counts = ['Crítica' => 0, 'Alta' => 0, 'Media' => 0, 'Baja' => 0];

            foreach ($issues as $data) {
                $sevEng = $data['customfield_25603_value'] ?? '';
                $sev    = $severityMap[$sevEng] ?? $sevEng;
                if (isset($counts[$sev])) {
                    $counts[$sev]++;
                }
            }

            $templateProcessor->setValue('n_crit', $counts['Crítica']);
            $templateProcessor->setValue('n_high', $counts['Alta']);
            $templateProcessor->setValue('n_mid',  $counts['Media']);
            $templateProcessor->setValue('n_low',  $counts['Baja']);

            if ($totalVuln > 0) {
                $templateProcessor->cloneRow('issue', $totalVuln);
                $i = 1;
                foreach ($issues as $data) {
                    $sevEng = $data['customfield_25603_value'] ?? '';
                    $sev    = $severityMap[$sevEng] ?? $sevEng;
                    $childIssue = $data['childIssue'] ?? null;

                    $templateProcessor->setValue("severity#{$i}",    $sev);
                    $templateProcessor->setValue("nombre_vuln#{$i}", $data['summary'] ?? '');
                    $templateProcessor->setValue("issue#{$i}",       'https://jira.tid.es/browse/' . $childIssue);
                    $templateProcessor->setValue("vuln_desc#{$i}", $data['description'] ?? '', true);
                    $i++;
                }
            }

            $nombreArchivo = 'CLOUD_CDCO-11cert_' . $revision["service_name"] . '_L2_03-EAS_v2.5.docx';
            $this->generarDocumento($templateProcessor, $nombreArchivo);
        } else {
            throw new GeneradorDocumentoException('Revisión no encontrada o sin datos de Prisma');
        }
    }

    public function sendMail($id, $alertasAsignadas, $observaciones = null, $user_mail, $mail_responsable_proyecto, $mails_copia = [])
    {
        $revision = $this->obtenerDatos($id, $alertasAsignadas);
        $mail = new Email();
        $to = $mail_responsable_proyecto;
        $cc = array_merge([$user_mail], $mails_copia);

        $asunto = 'Revisión de ' . $revision['service_name'];
        $body = file_get_contents('..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'easMail.phtml');
        $observaciones = $observaciones ?? $revision['observaciones'] ?? 'No hay comentarios adicionales.';

        $body = str_replace("{service_name}", $revision['service_name'], $body);
        $body = str_replace("{fecha_final}", $revision['fecha_final'] ?? 'Sin fecha final prevista', $body);
        $body = str_replace("{arquitecto}", $revision['arquitecto'], $body);
        $body = str_replace("{suscription_name}", $revision['suscription_name'], $body);
        $body = str_replace("{observaciones}", $observaciones, $body);
        $body = str_replace("{cloudId}", $revision['cloudId'], $body);

        $severityKeyMap = [
            'Critical' => 'critic',
            'Major'     => 'high',
            'Medium'   => 'mid',
            'Low'      => 'low',
        ];

        $severityNameMap = [
            'critic' => 'Crítica',
            'high'   => 'Alta',
            'mid'    => 'Media',
            'low'    => 'Baja',
        ];

        $vulnsBySeverity = [
            'critic' => [],
            'high'   => [],
            'mid'    => [],
            'low'    => [],
        ];

        foreach ($revision['issues'] ?? [] as $issueKey => $data) {
            $sevEng = $data['customfield_25603_value'] ?? '';
            $sevKey = $severityKeyMap[$sevEng] ?? null;
            if ($sevKey) {
                $vulnsBySeverity[$sevKey][] = [
                    'issue'       => $issueKey,
                    'summary'     => $data['summary']     ?? '',
                    'description' => $data['description'] ?? '',
                    'childIssue' => $data['childIssue'] ?? null,

                ];
            }
        }

        $total = array_sum(array_map('count', $vulnsBySeverity));
        $body = str_replace('{n}',      $total, $body);
        $body = str_replace('{n_crit}', count($vulnsBySeverity['critic']), $body);
        $body = str_replace('{n_high}', count($vulnsBySeverity['high']),   $body);
        $body = str_replace('{n_mid}',  count($vulnsBySeverity['mid']),    $body);
        $body = str_replace('{n_low}',  count($vulnsBySeverity['low']),    $body);

        foreach (['critic', 'high', 'mid', 'low'] as $severity) {
            $pattern = "/<!-- block_{$severity}_start -->(.*?)<!-- block_{$severity}_end -->/s";
            if (!preg_match($pattern, $body, $matches)) {
                continue;
            }
            $blockTemplate = $matches[1];
            $vuls = $vulnsBySeverity[$severity];
            if (empty($vuls)) {
                $body = preg_replace($pattern, '', $body);
                continue;
            }
            $filled = '';
            foreach ($vuls as $v) {
                $row = $blockTemplate;

                // 1) Severidad
                $row = str_replace(
                    '{' . $severity . '}',
                    $severityNameMap[$severity],
                    $row
                );

                $row = str_replace(
                    '{' . $severity . '_nombre_vuln}',
                    htmlspecialchars($v['summary']),
                    $row
                );

                $issueText = $v['childIssue'] . ' (https://jira.tid.es/browse/' . $v['childIssue'] . ')';
                $row = str_replace(
                    '{' . $severity . '_issue}',
                    $issueText,
                    $row
                );

                $row = str_replace(
                    '{' . $severity . '_descripcion}',
                    nl2br(htmlspecialchars($v['description'])),
                    $row
                );

                $filled .= $row;
            }

            $body = preg_replace($pattern, $filled, $body);
        }


        return $mail->sendmailEvs(
            $to,
            $cc,
            $asunto,
            $body,
            'Se ha enviado un informe de revisión'
        );
    }
}

class GeneradorDocumentoPentest extends GeneradorDocumento
{
    public function obtenerDatosPentest($id)
    {
        $db = new Pentest("octopus_serv");
        $activos = new activos("octopus_serv");
        $db_serv = new DbOperations("octopus_new");
        $pentest = $db->obtainPentestFromId($id);
        $activos_pentest = $db->obtenerActivosPentest($id);
        $productoId = $activos_pentest[0]['id_activo'];
        $nombreProducto = $activos->getActivoByTipo($productoId);

        if (is_array($pentest) && isset($pentest[0])) {
            $pentest = $pentest[0];
        }

        if (empty($pentest)) {
            throw new GeneradorDocumentoException("Pentest no encontrado.");
        }

        $pentest['nombre'] = $nombreProducto[0]["nombre"];

        $issues_pentest = $db->obtenerVulnsPentest($id);
        $idVulPentest = $issues_pentest[0]['id_vul'];
        $idVul = $db_serv->getVulnID($idVulPentest);


        if (empty($issues_pentest)) {
            $metodologias = $db_serv->getMetodologia(1);
            $metodologia = $metodologias[0]['nombre'];
            $nombreCorto = $metodologias[0]['nombre_corto'];
            $descrip = $metodologias[0]['descripcion'];
            $pruebas = $metodologias[0]['pruebas'];
        } else {
            $metodologias = $db_serv->getMetodologia($idVul[0]['metodologia_id']);
        }


        $jira = new Jira();
        $vulnerabilidades = [
            'critic' => [],
            'high' => [],
            'mid' => [],
            'low' => []
        ];

        $severityMap = [
            'critical' => 'critic',
            'high' => 'high',
            'medium' => 'mid',
            'low' => 'low'
        ];

        foreach ($issues_pentest as $issue) {
            $issueData = $jira->obtenerIssue($issue['id_issue']);

            if ($issueData['errorMessages'] ?? null) {
                error_log("Issue no disponible en Jira: " . $issue['id_issue']);
                continue;
            }

            $severity = strtolower($issueData['issues'][0]['fields']['customfield_12609']['value']);
            $nombre = $issueData['issues'][0]['fields']['summary'];
            $descripcion = $issueData['issues'][0]['fields']['description'];
            $descripcion = htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8');
            $metodologia = $metodologias[0]['nombre'];
            $nombreCorto = $metodologias[0]['nombre_corto'];
            $descrip = $metodologias[0]['descripcion'];
            $pruebas = $metodologias[0]['pruebas'];

            $issueKey = $issueData['issues'][0]['key'];
            $childIssue = $issueData['issues'][0]['fields']['issuelinks'][0]['inwardIssue']['key'] ?? null;
            $urlActivo = $issueData['issues'][0]['fields']['customfield_12800'];
            $urlActivo   = html_entity_decode($urlActivo,   ENT_QUOTES, 'UTF-8');
            $urlActivo   = htmlspecialchars($urlActivo,   ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $vulnerabilidad = [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'issue' => $issueKey,
                'childIssue' => $childIssue,
                'url_activo' => $urlActivo,
                'severity' => $severity
            ];

            if (isset($severityMap[$severity])) {
                $mappedSeverity = $severityMap[$severity];
                $vulnerabilidades[$mappedSeverity][] = $vulnerabilidad;
            } else {
                error_log("Severidad desconocida: $severity en la issue {$issue['id_issue']}");
            }
        }

        $pentest['vulnerabilidades'] = $vulnerabilidades;
        $pentest['nombreCorto'] = $nombreCorto;
        $pentest['descripcion'] = $descrip;
        $pentest['descripcion'] = preg_replace('/[ \t]+/', ' ', $pentest['descripcion']);
        $pentest['pruebas'] = $pruebas;
        $pentest['metodologia'] = $metodologia;

        return $pentest;
    }

    public function refillEvsDocument($id, $observaciones = null)
    {
        $pentest = $this->obtenerDatosPentest($id);

        if ($pentest) {
            $templateProcessor = new TemplateProcessor($this->templatePath);
            $templateProcessor->setMacroChars('{', '}');

            $pentest['observaciones'] = $observaciones ?? 'No hay comentarios adicionales.';

            // Generar tabla de pruebas
            $pruebas = explode("\n", $pentest['pruebas']);
            $columnaIzquierda = array_slice($pruebas, 0, ceil(count($pruebas) / 2));
            $columnaDerecha = array_slice($pruebas, ceil(count($pruebas) / 2));

            $tableXmlPruebas = '<w:tbl>';
            foreach (array_keys($columnaIzquierda + $columnaDerecha) as $i) {
                $tableXmlPruebas .= '<w:tr>';
                $tableXmlPruebas .= '<w:tc><w:p><w:pPr><w:spacing w:line="460" w:lineRule="auto"/></w:pPr><w:r><w:t>' . htmlspecialchars($columnaIzquierda[$i] ?? '') . '</w:t></w:r></w:p></w:tc>';
                $tableXmlPruebas .= '<w:tc><w:p><w:pPr><w:spacing w:line="460" w:lineRule="auto"/></w:pPr><w:r><w:t>' . htmlspecialchars($columnaDerecha[$i] ?? '') . '</w:t></w:r></w:p></w:tc>';
                $tableXmlPruebas .= '</w:tr>';
            }
            $tableXmlPruebas .= '</w:tbl>';

            if (!empty($tableXmlPruebas)) {
                $pentest['pruebas'] = $tableXmlPruebas;
            } else {
                $pentest['pruebas'] = 'No se pudo generar la tabla.';
            }

            // Generar tabla de vulnerabilidades
            $tablaVulnerabilidades = [];
            foreach (['critic', 'high', 'mid', 'low'] as $severity) {
                $vulnerabilidades = $pentest['vulnerabilidades'][$severity] ?? [];

                foreach ($vulnerabilidades as $vuln) {
                    $tablaVulnerabilidades[] = [
                        'url_activo' => $vuln['url_activo'] ?? '-',
                        'severity' => $vuln['severity'] ?? '-'
                    ];
                }
            }

            if (!empty($tablaVulnerabilidades)) {
                $templateProcessor->cloneRowAndSetValues('url_activo', $tablaVulnerabilidades);
            } else {
                $templateProcessor->setValue('url_activo', $pentest['nombre']);
                $templateProcessor->setValue('severity', '-');
            }

            // Reemplazar las tablas en la plantilla
            $templateProcessor->setValue('tabla_pruebas', $pentest['pruebas']);
            $valoresGenerales = [
                'nombre_corto' => $pentest['nombreCorto'] ?? 'Sin nombre corto',
                'metodologia' => $pentest['metodologia'] ?? 'Sin metodología',
                'descripcion' => $pentest['descripcion'] ?? 'Sin descripción',
                'pruebas' => $pentest['pruebas'] ?? 'Sin pruebas',
                'nombre' => $pentest['nombre'] ?? 'Sin nombre',
                'responsable' => $pentest['resp_pentest'] ?? 'Responsable no asignado',
                'fecha_inicio' => $pentest['fecha_inicio'] ?? 'Fecha no asignada',
                'fecha_fin' => $pentest['fecha_final'] ?? 'Sin fecha final prevista',
                'observaciones' => $pentest['observaciones'] ?? 'No hay comentarios adicionales.',
                'n' => array_reduce($pentest['vulnerabilidades'], function ($carry, $vulns) {
                    return $carry + count($vulns);
                }, 0),
                'n_crit' => count($pentest['vulnerabilidades']['critic'] ?? []),
                'n_high' => count($pentest['vulnerabilidades']['high'] ?? []),
                'n_mid' => count($pentest['vulnerabilidades']['mid'] ?? []),
                'n_low' => count($pentest['vulnerabilidades']['low'] ?? [])
            ];

            foreach ($valoresGenerales as $clave => $valor) {
                $templateProcessor->setValue($clave, $valor);
            }

            $allVulns = [];
            $severityMapping = [
                'critic' => 'Crítica',
                'high'   => 'Alta',
                'mid'    => 'Media',
                'low'    => 'Baja'
            ];

            foreach (['critic', 'high', 'mid', 'low'] as $sev) {
                $vuls = $pentest['vulnerabilidades'][$sev] ?? [];
                foreach ($vuls as $vuln) {
                    $issueKey = $vuln['childIssue'] ?? '-';
                    $linkIssue = "$issueKey (https://jira.tid.es/browse/{$issueKey})";

                    $allVulns[] = [
                        'severity' => $severityMapping[$sev] ?? ucfirst($sev),
                        'nombre_vuln' => $vuln['nombre'] ?? '-',
                        'issue' => $linkIssue,
                        'descripcion' => $vuln['descripcion'] ?? '-',
                    ];
                }
            }

            if (empty($allVulns)) {
                $templateProcessor->setValue('severity', 'No hay vulnerabilidades');
                $templateProcessor->setValue('nombre_vuln', 'No hay vulnerabilidades');
                $templateProcessor->setValue('issue', 'No hay issues');
                $templateProcessor->setValue('descripcion', 'No hay descripción');
            } else {
                $templateProcessor->cloneRowAndSetValues('severity', $allVulns);
            }

            $nombreArchivo = 'Resultados_pentest_' . $pentest["nombre"] . '.docx';
            $this->generarDocumento($templateProcessor, $nombreArchivo);
        } else {
            throw new GeneradorDocumentoException('Pentest no encontrado.');
        }
    }

    public function sendMail($id, $observaciones = null, $user_mail, $mail_responsable_proyecto, $mails_copia = [], $buzones)
    {
        $pentest = $this->obtenerDatosPentest($id);
        $mail = new Email();
        $to = $pentest['mail_soporte'];

        $cc = array_merge([$user_mail, $mail_responsable_proyecto], $mails_copia, $buzones);

        $asunto = 'Pentest de ' . $pentest['nombre'];
        $body = file_get_contents('..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'evsMail.phtml');
        $observaciones = $observaciones ?? $pentest['observaciones'] ?? 'No hay comentarios adicionales.';

        // Generar tabla de pruebas
        $pruebasItems = explode("\n", $pentest['pruebas']);
        $pruebasTable = '<div style="text-align:center"><table cellspacing="0" cellpadding="0" style="width:100%; border:4.5pt solid #f2f4ff; border-collapse:collapse">';
        for ($i = 0; $i < count($pruebasItems); $i += 2) {
            $pruebasTable .= '<tr style="height:14.4pt">';
            $pruebasTable .= '<td style="width:50%; border-right:4.5pt solid #f2f4ff; border-bottom:4.5pt solid #f2f4ff; padding:4.25pt 3.15pt; vertical-align:middle; background-color:#f2f4ff">';
            $pruebasTable .= '<p style="margin-top:0pt; margin-bottom:0pt; font-size:11pt; line-height:3em;">' . htmlspecialchars($pruebasItems[$i]) . '</p>';
            $pruebasTable .= '</td>';
            if (isset($pruebasItems[$i + 1])) {
                $pruebasTable .= '<td style="width:50%; border-left:4.5pt solid #f2f4ff; border-bottom:4.5pt solid #f2f4ff; padding:4.25pt 3.15pt; vertical-align:middle; background-color:#f2f4ff">';
                $pruebasTable .= '<p style="margin-top:0pt; margin-bottom:0pt; font-size:11pt; line-height:3em;">' . htmlspecialchars($pruebasItems[$i + 1]) . '</p>';
                $pruebasTable .= '</td>';
            } else {
                $pruebasTable .= '<td style="width:50%; border-left:4.5pt solid #f2f4ff; border-bottom:4.5pt solid #f2f4ff; padding:4.25pt 3.15pt; vertical-align:middle; background-color:#f2f4ff"></td>';
            }
            $pruebasTable .= '</tr>';
        }
        $pruebasTable .= '</table></div>';

        $body = str_replace("{nombre}", $pentest['nombre'], $body);
        $body = str_replace("{responsable}", $pentest['resp_pentest'], $body);
        $body = str_replace("{fecha_inicio}", $pentest['fecha_inicio'], $body);
        $body = str_replace("{fecha_fin}", $pentest['fecha_final'], $body);
        $body = str_replace("{metodologia}", $pentest['metodologia'], $body);
        $body = str_replace("{pruebas}", $pruebasTable, $body); // Reemplazar con la tabla generada
        $body = str_replace("{nombre_corto}", $pentest['nombreCorto'], $body);
        $descripcionConParrafos = implode('', array_map(function ($linea) {
            return '<p style="margin-top:0pt; margin-bottom:10pt; text-align:justify;">' . htmlspecialchars($linea) . '</p>';
        }, explode("\n", $pentest['descripcion'])));
        $body = str_replace("{descripcion}", $descripcionConParrafos, $body);
        $body = str_replace("{observaciones}", $observaciones, $body);

        $n_total = array_reduce($pentest['vulnerabilidades'], function ($carry, $vulns) {
            return $carry + count($vulns);
        }, 0);
        $n_crit = count($pentest['vulnerabilidades']['critic'] ?? []);
        $n_high = count($pentest['vulnerabilidades']['high'] ?? []);
        $n_mid = count($pentest['vulnerabilidades']['mid'] ?? []);
        $n_low = count($pentest['vulnerabilidades']['low'] ?? []);

        $body = str_replace("{n}", $n_total, $body);
        $body = str_replace("{n_crit}", $n_crit, $body);
        $body = str_replace("{n_high}", $n_high, $body);
        $body = str_replace("{n_mid}", $n_mid, $body);
        $body = str_replace("{n_low}", $n_low, $body);

        // Plantilla de fila para la tabla de activos
        $rowSnippet = '
        <tr>
            <td style="width:56.72%; border-top:0.75pt solid #0066ff; border-right:0.75pt solid #0066ff; padding:4.25pt 5.03pt;">
                <p style="margin:0; font-size:11pt">{url_activo}</p>
            </td>
            <td style="width:21.68%; border:0.75pt solid #0066ff; padding:4.25pt 5.03pt;">
                <p style="margin:0; font-size:11pt">Dominio</p>
            </td>
        </tr>';

        $vulnerabilidadesTotales = [];
        foreach (['critic', 'high', 'mid', 'low'] as $sev) {
            $vulnerabilidadesTotales = array_merge(
                $vulnerabilidadesTotales,
                $pentest['vulnerabilidades'][$sev] ?? []
            );
        }

        // Si no se han encontrado vulnerabilidades
        if (empty($vulnerabilidadesTotales)) {
            $filasActivos = '';
            $fila = $rowSnippet;
            $fila = str_replace('{url_activo}', $pentest['nombre'], $fila);
            $filasActivos .= $fila;
            $body = str_replace('{no_vulns}', 'No se han encontrado vulnerabilidades para este pentest', $body);
        } else {
            // Si hay vulnerabilidades, se crean las filas con los datos de cada activo
            $filasActivos = '';
            foreach ($vulnerabilidadesTotales as $vuln) {
                $fila = $rowSnippet;
                $fila = str_replace('{url_activo}', $vuln['url_activo'] ?? '-', $fila);
                $filasActivos .= $fila;
            }
            $body = str_replace('{no_vulns}', '', $body);
        }

        $body = str_replace('{tablaActivos}', $filasActivos, $body);

        $severityMapping = [
            'critic' => 'Critic',
            'high'   => 'Alta',
            'mid'    => 'Med',
            'low'    => 'Baja'
        ];

        // Procesamos la tabla de vulnerabilidades para cada nivel de severidad
        foreach (['critic', 'high', 'mid', 'low'] as $severity) {
            $pattern = "/<!-- block_{$severity}_start -->(.*?)<!-- block_{$severity}_end -->/s";

            if (!preg_match($pattern, $body, $matches)) {
                continue;
            }

            $originalBlock = $matches[1];
            $vulnerabilidades = $pentest['vulnerabilidades'][$severity] ?? [];

            if (empty($vulnerabilidades)) {
                // Si no hay vulnerabilidades de este nivel, se elimina el bloque
                $body = preg_replace($pattern, '', $body);
                continue;
            }

            $rowsHtml = '';
            foreach ($vulnerabilidades as $vuln) {
                $tempRow = $originalBlock;

                $severityName = $severityMapping[$severity] ?? ucfirst($severity);
                $tempRow = str_replace("{" . $severity . "}", $severityName, $tempRow);
                $nombre = $vuln['nombre'] ?? '-';
                $issueKey = $vuln['childIssue'] ?? '-';
                $descripcion = $vuln['descripcion'] ?? '-';
                $issueText = "$issueKey (https://jira.tid.es/browse/{$issueKey})";

                $tempRow = str_replace("{" . $severity . "_nombre_vuln}", $nombre, $tempRow);
                $tempRow = str_replace("{" . $severity . "_issue}", $issueText, $tempRow);
                $tempRow = str_replace("{" . $severity . "_descripcion}", $descripcion, $tempRow);

                $rowsHtml .= $tempRow;
            }

            $body = preg_replace($pattern, $rowsHtml, $body);
        }

        return $mail->sendmailEvs(
            $to,
            $cc,
            $asunto,
            $body,
            'Se ha enviado un informe de pentest'
        );
    }
}
