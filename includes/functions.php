<?php

// FUNCIONES RELACIONADA CON ACTIVOS

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Arabic;

const UNIDENTIFIED = "Sin identificar";
const NO_EVALUATIONS = "No evaluations";
const NO_EVALUADO = "No evaluado";

class SessionException extends Exception {}

function servicioTieneConf($servicio)
{
    $db = new Activos(DB_SERV);
    $bia = $db->getBia($servicio["id"]);
    if (isset($bia[0]["meta_value"])) {
        $preguntas = json_decode($bia[0]["meta_value"], true);
        return $preguntas["2"] == "1";
    } else {
        return false;
    }
}

function getRevisionName($cloudID, $revision)
{
    $db = new Revision(DB_SERV);
    $activo = $db->getActivoBySusId($cloudID);

    return "Review de " . $activo[0]["nombre"] . " - " . $revision["Fecha_inicio"];
}

function getPagination($parametros)
{
    if (isset($parametros["total"])) {
        $total = $parametros["total"];
    } else {
        $total = 10;
    }
    if (isset($parametros["start"])) {
        $start = $parametros["start"];
    } else {
        $start = 0;
    }

    return array("total" => $total, "start" => $start);
}

function obtenerProductosPrivacidad($productos)
{
    $uniqueProductos = [];

    foreach ($productos as $producto) {
        $key = $producto['id']; // Asumiendo que cada producto tiene un identificador único 'id'

        if (!isset($uniqueProductos[$key])) {
            $uniqueProductos[$key] = $producto;
        } else {
            // Priorizar productos con ambos valores a true
            if ($producto['charPersonal'] && $producto['biaPersonal']) {
                $uniqueProductos[$key] = $producto;
            } elseif ($producto['charPersonal'] || $producto['biaPersonal']) {
                if (!$uniqueProductos[$key]['charPersonal'] && !$uniqueProductos[$key]['biaPersonal']) {
                    $uniqueProductos[$key] = $producto;
                }
            }
        }
    }
    return $uniqueProductos;
}
function processlogs($logs)
{
    $db_users = new Usuarios("octopus_users");
    $db = new Activos(DB_SERV);
    $logsProcessed = array(
        "relation_changes" => array(),
        "new_activos" => array(),
        "deleted_activos" => array(),
        "modified_activos" => array()
    );
    foreach ($logs["relation_changes"] as $log) {
        $user = $db_users->getUser($log["id_usuario"]);
        if (isset($user[0])) {
            $user = $user[0]["email"];
        } else {
            $user = UNIDENTIFIED;
        }
        $id_activo = $db->getActivo($log["id_activo"]);
        $old_padre = $db->getActivo($log["old_padre"]);
        $new_padre = $db->getActivo($log["new_padre"]);
        $logsProcessed["relation_changes"][] = "<b>" . $user . "</b> | <b>" . $log["fecha"] . "</b> |  El activo <b>" . $id_activo[0]["nombre"] . "</b> ha cambiado su relación de <b>" . $old_padre[0]["nombre"] . "</b> a <b>" . $new_padre[0]["nombre"] . "</b>";
    }
    foreach ($logs["new_activos"] as $log) {
        $user = $db_users->getUser($log["id_usuario"]);
        if (isset($user[0])) {
            $user = $user[0]["email"];
        } else {
            $user = UNIDENTIFIED;
        }
        $id_activo = $db->getActivo($log["id_activo"]);
        $logsProcessed["new_activos"][] = "<b>" . $user . "</b> | <b>" . $log["fecha"] . "</b> |  Se ha creado el activo <b>" . $id_activo[0]["nombre"] . "</b> con ID <b>" . $id_activo[0]["id"] . "</b>";
    }
    foreach ($logs["deleted_activos"] as $log) {
        $user = $db_users->getUser($log["id_usuario"]);
        if (isset($user[0])) {
            $user = $user[0]["email"];
        } else {
            $user = UNIDENTIFIED;
        }
        $logsProcessed["deleted_activos"][] = "<b>" . $user . " </b> | <b>" . $log["fecha"] . "</b> |  Se ha eliminado el activo <b>" . $log["activo_nombre"] . "</b> con el ID antiguo <b>" . $log["activo_id"] . "</b>";
    }
    foreach ($logs["modified_activos"] as $log) {
        $user = $db_users->getUser($log["id_usuario"]);
        if (isset($user[0])) {
            $user = $user[0]["email"];
        } else {
            $user = UNIDENTIFIED;
        }
        $id_activo = $db->getActivo($log["id_activo"]);
        $logsProcessed["modified_activos"][] = "<b>" . $user . "</b> | <b>" . $log["fecha"] . "</b> |  El activo <b>" . $id_activo[0]["nombre"] . "</b> con ID <b>" . $id_activo[0]["id"] . "</b> ha realizado un <b>" . $log["tipo_modificacion"] . "</b> de <b>" . $log["antiguo_valor"] . "</b> a </b>" . $log["nuevo_valor"] . "</b>";
    }
    return $logsProcessed;
}

function refineVulnsUnicas($info_vulns, $vulnerabilidades)
{
    foreach ($vulnerabilidades as $index => $vulnerabilidad) {
        $issueKey = $vulnerabilidad["vulnerabilidad"];
        if (isset($info_vulns[$issueKey])) {
            $vulnerabilidades[$index]["issue_info"] = $info_vulns[$issueKey];
            $vulnerabilidades[$index]["issue_info"]["pruebaInfo"]["id_prueba"] = $vulnerabilidad["id_prueba"];
            $vulnerabilidades[$index]["issue_info"]["pruebaInfo"]["nombre_prueba"] = $vulnerabilidad["nombre_prueba"];
            $vulnerabilidades[$index]["issue_info"]["pruebaInfo"]["tipo_prueba"] = $vulnerabilidad["tipo_prueba"];
        } else {
            $vulnerabilidades[$index]["issue_info"] = null;
        }
    }

    $array_activos = array();
    foreach ($vulnerabilidades as $index => $vulnerabilidad) {
        $idActivo = $vulnerabilidad["id_activo"];
        $array_activos[$idActivo]["nombre"] = $vulnerabilidad["nombre"];
        $array_activos[$idActivo]["id_activo"] = $idActivo;
        $array_activos[$idActivo]["vulns"][] = $vulnerabilidad["issue_info"];
    }

    return $array_activos;
}

function getVulnsUnicas($vulnerabilidades)
{
    $jira = new Jira();

    $info_vulns = array();
    $array_issues = array();
    $valor_inicial = 49;
    foreach ($vulnerabilidades as $index => $vulnerabilidad) {
        $array_issues[] = $vulnerabilidad["vulnerabilidad"];
        if ($index == $valor_inicial) {
            $vulns = $jira->obtenerIssuesInfoEspecifica($array_issues);
            $valor_inicial += 50;
            $array_issues = array();
            $info_vulns = array_merge($info_vulns, $vulns["issues"]);
        }
    }
    $vulns = $jira->obtenerIssuesInfoEspecifica($array_issues);
    $info_vulns = array_merge($info_vulns, $vulns["issues"]);
    $issues_dict = array();
    foreach ($info_vulns as $issue) {
        $issues_dict[$issue["key"]] = $issue;
    }

    return $issues_dict;
}

function generarEvaluacionTrasPrueba($usfsAbiertos, $usfsCerrados, $activo, $tipo)
{
    $db_activos = new Activos(DB_SERV);
    $db = new Pentest(DB_SERV);

    $activo_tipo = $db_activos->obtenerTipoActivo($activo["id_activo"]);
    if ($activo_tipo[0]["activo_id"] == 33) {
        $fecha = $db->getFechaEvaluaciones($activo["id_activo"], true);
        if (!isset($fecha[0])) {
            return NO_EVALUATIONS;
        }
        if ($fecha[0]["tipo_tabla"] == "evaluaciones_versiones") {
            $preguntas = $db->getPreguntasVersionByFecha($fecha[0]["id"]);
        } else {
            $preguntas = $db->getPreguntasEvaluacionByFecha($fecha[0]["id"]);
        }
        if (isset($preguntas[0])) {
            $preguntas = json_decode($preguntas[0]["preguntas"], true);
        } else {
            return NO_EVALUATIONS;
        }
        foreach ($usfsCerrados as $index => $usf) {
            foreach ($usf as $preguntasUSF) {
                $preguntas[$preguntasUSF["id_preguntas"]] = "1";
            }
        }
        foreach ($usfsAbiertos as $index => $usf) {
            foreach ($usf as $preguntasUSF) {
                $preguntas[$preguntasUSF["id_preguntas"]] = "0";
            }
        }
        $index = array_search("evaluaciones", array_column($fecha, "tipo_tabla"));
        if ($index !== false) {
            if ($fecha[0]["tipo_tabla"] == "evaluaciones_versiones") {
                $id_version = $fecha[0]["id"];
            } else {
                $id_version = null;
            }

            $db->editEval($fecha[$index]["id"], $id_version, $preguntas, "Evaluacion de " . $tipo);
        } else {
            return NO_EVALUATIONS;
        }
    }
}

function checkForAdditionalAccess($roles, $apiRoute)
{
    $db = new Usuarios(DB_USER);
    foreach ($roles as $role) {
        $role = $db->getRoleByName($role);
        if ($role["additional_access"] == 1) {
            $addedEndpoints = $db->getEndpointsByRole($role["id"], false);
            foreach ($addedEndpoints as $endpoint) {
                if ($endpoint["route"] == $apiRoute) {
                    return true;
                }
            }
        }
    }
    return false;
}

function setPaginacion($parametros)
{
    $paginacion = array();
    if (isset($parametros["total"])) {
        $paginacion["total"] = $parametros["total"];
    } else {
        $paginacion["total"] = 10;
    }
    if (isset($parametros["start"])) {
        $paginacion["start"] = $parametros["start"];
    } else {
        $paginacion["start"] = 0;
    }
    return $paginacion;
}

function getPadresFromArray($padres, &$activo)
{
    foreach ($padres as $padre) {
        if (!isset($padre)) {
            break;
        }
        if ($padre["tipo"] == "Producto") {
            $activo["producto"] = $padre["nombre"];
        } elseif ($padre["tipo"] == "Dirección") {
            $activo["direccion"] = $padre["nombre"];
        } elseif ($padre["tipo"] == "Área") {
            $activo["area"] = $padre["nombre"];
        } elseif ($padre["tipo"] == "Organización") {
            // if (isset($activo["organizacion"]))
            if ($padre["nombre"] == "Telefónica Innovación Digital") {
                $activo["organizacion"] = $padre["nombre"];
            } else {
                if (!isset($activo["organizacion"])) {
                    $activo["organizacion"] = $padre["nombre"];
                }
            }
        }
    }
    if (!isset($activo["producto"])) {
        $activo["producto"] = "Sin producto";
    }
    if (!isset($activo["direccion"])) {
        $activo["direccion"] = "Sin dirección";
    }
    if (!isset($activo["area"])) {
        $activo["area"] = "Sin área";
    }
}
function hijosCon3PS($servicio, $db)
{
    $hijos = $db->getHijosTipo($servicio["id"], "Sistema de Información");
    foreach ($hijos as $hijo) {
        $evaluaciones = $db->getEvaluaciones3PSById($hijo["id"]);
        if (isset($evaluaciones[0])) {
            return true;
        }
    }
    return false;
}

function hijosConEvalNorm($servicio, $db)
{
    $hijos = $db->getHijosTipo($servicio["id"], "Sistema de Información");
    foreach ($hijos as $hijo) {
        $hijo = $db->getActivo($hijo["id"])[0];
        if ($hijo["archivado"] == 1) {
            continue;
        }
        $evaluaciones = $db->getFechaEvaluaciones($hijo["id"]);
        if (isset($evaluaciones[0])) {
            return true;
        }
    }
    return false;
}

function getSistemas3PS()
{
    $db = new Activos(DB_SERV);
    $evaluaciones = $db->getEvaluaciones3PS();

    $activos = [];
    foreach ($evaluaciones as $evaluacion) {
        $activo = [];
        $activoRaw = $db->getActivo($evaluacion['activo_id']);
        if ($activoRaw[0]["archivado"] == 1) {
            continue;
        }
        $padres = $db->getFathersNew($activoRaw[0]["id"]);
        $activo["sistema"] = $activoRaw[0]["nombre"];
        $activo["fecha"] = $evaluacion["fecha"];
        $fatherCount = 0;
        foreach ($padres as $index => $padre) {
            if ($padre["tipo"] == "Producto") {
                $added = false;
                for ($i = 0; $i < $index; $i++) {
                    if ($padres[$i]["nombre"] == $padres[$index]["nombre"]) {
                        $added = true;
                    }
                }
                if (!$added) {
                    $activo["servicio"] = $padre["nombre"];
                    $activos[] = $activo;
                    $fatherCount++;
                }
            }
        }
        if ($fatherCount == 0) {
            $activo["padre"] = "Sin servicio padre";
            $activos[] = $activo;
        }
    }
    return $activos;
}

function checkdates($logs, $parametros)
{
    if ($parametros["fecha_inicio"] != "all") {
        $fecha_inicio = new DateTime($parametros["fecha_inicio"]);
    } else {
        $fecha_inicio = new DateTime("0000-00-00");
    }
    if ($parametros["fecha_final"] != "all") {
        $fecha_fin = new DateTime($parametros["fecha_final"]);
    } else {
        $fecha_fin = new DateTime("9999-12-31");
    }

    foreach ($logs["relation_changes"] as $index => $log) {
        $fecha_log = new DateTime($log["fecha"]);
        if ($fecha_log < $fecha_inicio || $fecha_log > $fecha_fin) {
            unset($logs["relation_changes"][$index]);
        }
    }
    foreach ($logs["new_activos"] as $index => $log) {
        $fecha_log = new DateTime($log["fecha"]);
        if ($fecha_log < $fecha_inicio || $fecha_log > $fecha_fin) {
            unset($logs["new_activos"][$index]);
        }
    }
    foreach ($logs["deleted_activos"] as $index => $log) {
        $fecha_log = new DateTime($log["fecha"]);
        if ($fecha_log < $fecha_inicio || $fecha_log > $fecha_fin) {
            unset($logs["deleted_activos"][$index]);
        }
    }
    foreach ($logs["modified_activos"] as $index => $log) {
        $fecha_log = new DateTime($log["fecha"]);
        if ($fecha_log < $fecha_inicio || $fecha_log > $fecha_fin) {
            unset($logs["modified_activos"][$index]);
        }
    }

    return $logs;
}

function obtainAllLogs($logs)
{
    foreach ($logs["relation_changes"] as $index => $log) {
        $logs["relation_changes"][$index]["tipo"] = "Cambio_relacion";
    }
    foreach ($logs["new_activos"] as $index => $log) {
        $logs["new_activos"][$index]["tipo"] = "Nuevo_activo";
    }
    foreach ($logs["deleted_activos"] as $index => $log) {
        $logs["deleted_activos"][$index]["tipo"] = "Activo_eliminado";
    }
    foreach ($logs["modified_activos"] as $index => $log) {
        $logs["modified_activos"][$index]["tipo"] = "Activo_moodificado";
    }
    return $logs;
}

function editActivosPentest($idPentest, $producto)
{
    $db_pentest = new Pentest("octopus_serv");
    $pentest = $db_pentest->obtainPentestFromId($idPentest);
    $pentest = $pentest[0];
    if ($pentest["status"] == 1 || $pentest["status"] == 2) {
        $db_pentest->eliminarActivosPentest($idPentest);
        $db_pentest->insertActivosPentest($idPentest, $producto);
        return true;
    } else {
        return false;
    }
}

function createLogsNewActivo($nombreActivo, $token)
{
    $db = new Activos(DB_SERV);
    $logs = new Logs("octopus_logs");
    $db_users = new Usuarios(DB_USER);
    $user = $db_users->getUser($token['data']);
    $user = $user[0]["id"];
    $activo = $db->getActivoByNombre($nombreActivo);
    $id = $activo[sizeof($activo) - 1]["id"];
    $logs->addNewActivoLogs($id, $user);
}

function getActivosUnicos($activos)
{
    $activosnombre = array_column($activos, 'nombre');
    $unicos = array_unique($activosnombre);
    $acunicos = array();
    $count = 0;
    foreach ($unicos as $activo) {
        $cosa = array_search($activo, $activosnombre);
        $acunicos[$count] = $activos[$cosa];
        $count++;
    }
    return $acunicos;
}

function checkChangesActivo($oldActivo, $nombre, $desc, $archivado, $expuesto, $token)
{
    $logs = new Logs("octopus_logs");
    $db_users = new Usuarios(DB_USER);
    $user = $db_users->getUser($token['data']);
    $user = $user[0]["id"];

    if ($oldActivo[0]["nombre"] != $nombre) {
        $logs->addEditActivoLogs($oldActivo[0]["id"], "Cambio nombre", $nombre, $oldActivo[0]["nombre"], $user);
    }
    if ($oldActivo[0]["descripcion"] != $desc) {
        $logs->addEditActivoLogs($oldActivo[0]["id"], "Cambio descripcion", $desc, $oldActivo[0]["descripcion"], $user);
    }
    if ($oldActivo[0]["archivado"] != $archivado) {
        $logs->addEditActivoLogs($oldActivo[0]["id"], "Cambio estado archivado", $archivado, $oldActivo[0]["archivado"], $user);
    }
    if ($oldActivo[0]["expuesto"] != $expuesto) {
        $logs->addEditActivoLogs($oldActivo[0]["id"], "Cambio estado exposicion", $expuesto, $oldActivo[0]["expuesto"], $user);
    }
}

function setActivosRevision($parametros, $cloudID)
{
    $db = new Revision(DB_SERV);
    $db_activos = new Activos(DB_SERV);

    $activo = $db->getActivoBySusId($cloudID);
    if (isset($activo[0])) {
        $id = $db->obtainRevisionID($parametros["Nombre"]);
        $db->insertActivosRevision($id, $activo[0]["id"]);
        if ($activo[0]["activo_id"] == 42) {
            $producto = $db_activos->getFathersNewByTipo($activo[0]["id"], "Producto");
            if (isset($producto[0])) {
                $db->insertActivosRevision($id, $producto[0]["id"]);
            }
        }
        return false;
    } else {
        return true;
    }
}

function obtenerProductosUnicos($array_productos)
{
    $productos_sin_repetir = array();

    // Iterar sobre el array original
    foreach ($array_productos as $producto) {
        // Verificar si el nombre del producto ya existe en el nuevo array
        if (!array_key_exists($producto['nombre_producto'], $productos_sin_repetir)) {
            // Si no existe, agregar el producto al nuevo array
            $productos_sin_repetir[$producto['nombre_producto']] = $producto["nombre_producto"];
        }
    }

    $db = new Activos(DB_SERV);
    $productos = $db->getActivosByTipo(67, null);
    return array(
        "Productos_eval" => $productos_sin_repetir,
        "Productos_eval_num" => count($productos_sin_repetir),
        "Productos_total" => count($productos),
        "Productos_no_eval_num" => count($productos) - count($productos_sin_repetir)
    );
}

function getActivosObligatoriosSinRepetir($activos)
{
    $db = new Activos();
    $activosObligatorios = $db->getClaseActivosObligatorios();
    if (isset($activos[0]["tipo_id"])) {
        $tipos = array_column($activos, 'tipo_id');
    } else {
        $tipos = array_column($activos, 'tipo');
    }
    $tipos = array_unique($tipos);
    foreach ($activosObligatorios as $key => $activo) {

        if (array_search($activo['tipo'], $tipos) !== false) {
            unset($activosObligatorios[$key]);
        }
    }
    return $activosObligatorios;
}

function getCompararNormativa($db, $tipo, $activos, $preguntas)
{
    return $db->getCompararNormativa($tipo, $activos, $preguntas);
}

function oderprioiso($a, $b)
{
    $order = ["iso27001", "27002(2022)"];
    $pos_a = array_search($a, $order);
    $pos_b = array_search($b, $order);
    if ($pos_a === false) {
        $pos_a = count($order);
    }
    if ($pos_b === false) {
        $pos_b = count($order);
    }
    return $pos_a - $pos_b;
}

/**
 *Orders the keys of questions.
 * @category Internal
 * @param mixed $a The first key to compare.
 * @param mixed $b The second key to compare.
 * @return int Returns 0 if the keys are equal, -1 if $a is less than $b, and 1 if $a is greater than $b.
 */
function ordenarKeyPreguntas($a, $b)
{
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}

function obtenerNombreActivos($array)
{
    $devolver = array();
    $db = new Activos();
    foreach ($array as $activo) {
        $cosa = $db->getActivoByTipo($activo["tipo"]);
        array_push($devolver, array($cosa[0]['nombre'], intval($activo["num"])));
    }
    return $devolver;
}

function getActivoByTipo($activos, $tipo, $index = "tipo")
{
    return array_filter($activos, function ($item) use ($tipo, $index) {
        return isset($item[$index]) && strval($item[$index]) === strval($tipo);
    });
}

function search($var, $pacfin)
{
    foreach ($var as $item) {
        $resultado = array_search($item, $pacfin);
        if ($resultado !== false) {
            return true;
        }
    }
    return false;
}

function prepararPreguntas($preguntas, $id)
{
    $db = new Activos(DB_SERV);
    $pacfin = array_column($db->getSeguimientoByActivoId($id, "Finalizado"), "codpac");
    $pacnoaplica = array_column($db->getSeguimientoByActivoId($id, "No Aplica"), "codpac");
    $pacfin = array_merge($pacfin, $pacnoaplica);
    if (isset($pacfin[0])) {
        foreach ($preguntas as $pregunta => $respuesta) {
            $infpreg = $db->getUsfPacByPregunta($pregunta);
            $busqueda = search(array_column($infpreg, "proyecto"), $pacfin);
            if ($busqueda) {
                $preguntas[$pregunta] = "1";
            }
        }
    }
    return $preguntas;
}

function getAmenazasActivos($activos)
{
    foreach ($activos as $key => $activo) {
        $db = new Activos();
        if (isset($activo['tipo_id'])) {
            $activos[$key][AMENAZAS] = $db->getAmenazasByActivoId($activo['tipo_id']);
        } else {
            $activos[$key][AMENAZAS] = $db->getAmenazasByActivoId($activo['tipo']);
        }
    }
    return $activos;
}

function getUsfAmenazasActivos($activos, $control)
{
    foreach ($activos as $key => $activo) {
        if (!isset($activos[$key][AMENAZAS])) {
            $activos[$key] = getAmenazasActivos(array($activo))[0];
        }
        if ($control == 'usf') {
            $activos[$key][AMENAZAS] = obtenerUsfAmenazas($activos[$key][AMENAZAS]);
        } else {
            $activos[$key][AMENAZAS] = obtener3psAmenazas($activos[$key][AMENAZAS]);
        }
    }
    return $activos;
}

function updateEvaluacionCache($hash_activos, $hash_preguntas, $respuesta)
{
    $db_cache = new Activos(DB_CACHE);
    if (isset($hash_activos) && isset($hash_preguntas)) {
        $db_cache->updateEvaluacionCache($hash_activos, $hash_preguntas, $respuesta);
        return true;
    }
    return false;
}

function updateBiaCache($hash_bia, $respuesta)
{
    $db_cache = new Activos(DB_CACHE);
    if (isset($hash_bia)) {
        $db_cache->updateBiaCache($hash_bia, $respuesta);
        return true;
    }
    return false;
}

function getEvaluacionCache($hash_activos, $hash_preguntas)
{
    $db_cache = new Activos(DB_CACHE);
    if (isset($hash_activos) && isset($hash_preguntas)) {
        $respuesta = $db_cache->getEvaluacionCache($hash_activos, $hash_preguntas);
        if (isset($respuesta[0])) {
            $db_cache->updateEvaluacionCache($hash_activos, $hash_preguntas, json_decode($respuesta[0]["resultados_json"]));
            return json_decode($respuesta[0]['resultados_json'], true);
        }
    }
    return false;
}

function getBiaCache($hash_bia)
{
    $db_cache = new Activos(DB_CACHE);
    if (isset($hash_bia)) {
        $respuesta = $db_cache->getBiaCache($hash_bia);
        if (isset($respuesta[0])) {
            $db_cache->updateBiaCache($hash_bia, json_decode($respuesta[0]["resultados_json"]));
            return json_decode($respuesta[0]['resultados_json'], true);
        }
    }
    return false;
}


function updatePrioridadInCache($pac_id, $prioridad)
{
    $db_cache = new Activos(DB_CACHE);
    $pdo = $db_cache->con;
    $sql = "SELECT id, resultados_json FROM cache_evaluaciones";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($resultados as $row) {
        $json = json_decode($row['resultados_json'], true);
        $updated = false;
        if (isset($json['pac']) && is_array($json['pac'])) {
            foreach ($json['pac'] as $cod => &$pac) {
                if (isset($cod) && strval($cod) === strval($pac_id)) {
                    $pac['prioridad'] = $prioridad;
                    $updated = true;
                }
            }
            if ($updated) {
                $update_sql = "UPDATE cache_evaluaciones SET resultados_json = :json_data WHERE id = :id";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->bindValue(':json_data', json_encode($json));
                $update_stmt->bindValue(':id', $row['id']);
                $update_stmt->execute();
                $updated = true;
            }
        }
    }
    return $updated;
}


function getEvaluacionActivos($activos, $preguntas)
{
    sort($activos);
    uasort($preguntas, 'ordenarKeyPreguntas');
    $hash_activos = hash('sha256', json_encode($activos));
    $hash_preguntas = hash('sha256', json_encode($preguntas));


    // Buscar en la tabla
    $cached_result = getEvaluacionCache($hash_activos, $hash_preguntas);

    if ($cached_result) {
        return $cached_result;
    } else {
        $count = 0;
        $usfeval = array();
        $amenazas = array();
        $usfpac = array();
        $db = new Activos(DB_SERV);
        if (!isset($preguntas['3ps'])) {
            $comparacion = $db->getCompararNormativa("all", $activos, $preguntas);
            $normativas = getNormativas($comparacion);
            $preguntas = getUsfByPreguntas($preguntas, $normativas);
            $control = "usf";
            $control_id = 'usf_id';
        } else {
            $preguntas = get3psById($preguntas);
            $control = "ps";
            $control_id = '3ps_cod';
        }

        $activos = getAmenazasActivos($activos);
        $activos = getUsfAmenazasActivos($activos, $control);


        $conValue = null;
        $intValue = null;
        $disValue = null;

        $db_serv = new Activos(DB_SERV);
        $activoServicio = null;
        foreach ($activos as $item) {
            if (isset($item['tipo_id']) && $item['tipo_id'] == 33 && isset($item['padre'])) {
                $activoServicio = $item['padre'];
                break;
            }
        }

        $bia = $db_serv->getBia($activoServicio);
        $resultadobia = calcularBia($bia);
        $conValue = $resultadobia['Con']['Max'];
        $intValue = $resultadobia['Int']['Max'];
        $disValue = $resultadobia['Dis']['Max'];


        foreach ($activos as $activo) {
            $db = new Activos();
            if (isset($activo['tipo_id'])) {
                $activos[$count]['familia'] = $db->getFamiliaActivo($activo['tipo_id'])[0]['familia'];
            } else {
                $activos[$count]['familia'] = $db->getFamiliaActivo($activo['tipo'])[0]['familia'];
            }
            $amenazacount = 0;
            foreach ($activos[$count][AMENAZAS] as &$amenaza) {
                $usfct['Proactivo'] = 0;
                $usfct['Reactivo'] = 0;
                $ctm_proactivos = [];
                $ctm_reactivos = [];
                if (count($amenaza[$control]) !== 0) {
                    foreach ($amenaza[$control] as $key => $usf) {
                        $defUsf = $usf;
                        $usf = $usf[$control_id];
                        $index = array_key_exists($usf, $usfeval);
                        if (!$index) {
                            $usfeval[$usf] = array(TOTAL => 0, 'ct' => 0, 'nc' => 0);
                        }
                        $total = array_column($preguntas, $usf);
                        if (count($total) !== 0) {
                            $usfeval[$usf][TOTAL] = count($total);
                            $existe = array_search('1', $total);
                            if ($existe !== false) {
                                $usfeval[$usf]['ct'] = (int) array_count_values($total)['1'];
                            } else {
                                $usfeval[$usf]['ct'] = 0;
                            }
                            $usfeval[$usf]['nc'] = $usfeval[$usf][TOTAL] - $usfeval[$usf]['ct'];
                            $usfeval[$usf]['ctm'] = $usfeval[$usf]['ct'] / $usfeval[$usf][TOTAL] * 100;
                            $usfct[$defUsf['tipo']]++;
                            $amenaza[$control][$usf] = $usfeval[$usf];
                            $amenaza[$control][$usf]["descripcion"] = $defUsf['descripcion'];
                            if ($control == 'usf') {
                                $amenaza[$control][$usf]["tipo"] = $defUsf['tipo'];
                                if ($defUsf['tipo'] === 'Proactivo') {
                                    $ctm_proactivos[] = $usfeval[$usf]['ctm'];
                                } elseif ($defUsf['tipo'] === 'Reactivo') {
                                    $ctm_reactivos[] = $usfeval[$usf]['ctm'];
                                }
                            }
                            $amenaza[$control][$usf]["dominio"] = $defUsf['dominio'];
                            if (isset($defUsf['id_proyecto'])) {
                                $proyecto = $db->getProyectoById($defUsf['id_proyecto'])[0];
                                $usfpac[$proyecto['cod']]['nombre'] = $proyecto['nombre'];
                                $usfpac[$proyecto['cod']]['descripcion'] = $proyecto['descripcion'];
                                $usfpac[$proyecto['cod']]['tareas'] = $proyecto['tareas'];
                                $usfpac[$proyecto['cod']]['proyecto_id'] = $proyecto['id'];
                                $usfpac[$proyecto['cod']][$control][$usf] =  $amenaza[$control][$usf];
                                $amenaza[$control][$usf]["proyecto"] = $proyecto['nombre'];
                            }

                            $usfunico[$usf] = $amenaza[$control][$usf];
                        }
                        unset($amenaza[$control][$key]);
                    }
                }

                if (!empty($ctm_proactivos)) {
                    $media_proactivos = array_sum($ctm_proactivos) / count($ctm_proactivos);
                } else {
                    $media_proactivos = 0;
                }
                if (!empty($ctm_reactivos)) {
                    $media_reactivos = array_sum($ctm_reactivos) / count($ctm_reactivos);
                } else {
                    $media_reactivos = 0;
                }
                $proactivos = array_column($amenaza[$control], 'tipo');
                $dato = array_count_values($proactivos);

                if ($media_proactivos <= 25) {
                    $resta = 0;
                } elseif ($media_proactivos <= 50) {
                    $resta = -1;
                } elseif ($media_proactivos <= 75) {
                    $resta = -2;
                } elseif ($media_proactivos <= 99) {
                    $resta = -3;
                } else {
                    $resta = -4;
                }

                if ($media_reactivos <= 25) {
                    $restay = 0;
                } elseif ($media_reactivos <= 50) {
                    $restay = -1;
                } elseif ($media_reactivos <= 75) {
                    $restay = -2;
                } elseif ($media_reactivos <= 99) {
                    $restay = -3;
                } else {
                    $restay = -4;
                }

                $amenaza['ajustada'] = array('y' => $restay, 'x' => $resta);
                $amenaza['tiposUsf'] = $dato;
                if (count($amenaza[$control]) == 0) {
                    unset($activos[$count][AMENAZAS][$amenazacount]);
                }
                $amenazacount++;
            }
            sort($activos[$count][AMENAZAS]);
            $amenazas = array_merge($amenazas, $activos[$count][AMENAZAS]);
            $count++;
        }
        $respuesta["amenazas"] = $amenazas;
        $respuesta[$control] = $usfunico;
        $respuesta["activos"] = $activos;
        if ($control !== '3ps') {
            foreach ($usfpac as &$proyecto) {
                if (isset($proyecto[$control])) {
                    $riesgoMasAlto = 0;
                    foreach ($proyecto[$control] as $usfKey => $usfData) {
                        if (isset($usfData['ctm'])) {
                            $riesgoActual = 0;
                            $riesgoSuma = 0;

                            $amenazasEncontradas = array_filter($amenazas, function ($amenaza) use ($control, $usfKey) {
                                return isset($amenaza[$control][$usfKey]);
                            });

                            foreach ($amenazasEncontradas as $amenaza) {
                                $riesgo = calcularRiesgo(
                                    $amenaza,
                                    $conValue,
                                    $intValue,
                                    $disValue
                                );

                                if ($riesgoSuma < $riesgo['valorNumerico']) {
                                    $riesgoActual = $riesgo['valorNumerico'];
                                }
                            }
                            $riesgoResidual = $riesgoActual * (1 - ($usfData['ctm'] / 100));
                            if ($riesgoMasAlto < $riesgoResidual) {
                                $riesgoMasAlto = $riesgoResidual;
                            }
                        }
                    }

                    if ($riesgoMasAlto <= 1) {
                        $prioridad = "Baja";
                    } elseif ($riesgoMasAlto <= 2) {
                        $prioridad = "Media";
                    } elseif ($riesgoMasAlto <= 3) {
                        $prioridad = "Alta";
                    } elseif ($riesgoMasAlto <= 4) {
                        $prioridad = "Crítica";
                    } else {
                        $prioridad = "Desconocida";
                    }

                    $proyecto['prioridad'] = $prioridad;
                    $proyecto['riesgoMasAlto'] = $riesgoMasAlto;
                }
            }
            unset($proyecto);

            $respuesta['pac'] = $usfpac;
        }
        updateEvaluacionCache($hash_activos, $hash_preguntas, $respuesta);
        return $respuesta;
    }
}

/**
 * Calcula el nivel de riesgo basado en amenazas y valores BIA
 *
 * @param array $amenaza La amenaza a evaluar
 * @param int $conValue El valor de confidencialidad
 * @param int $intValue El valor de integridad
 * @param int $disValue El valor de disponibilidad
 * @return array Información del riesgo calculado
 */
function calcularRiesgo($amenaza, $conValue, $intValue, $disValue)
{
    $ejeY = [4, 3, 2, 1, 0];
    $x = isset($amenaza['probabilidad']) ? intval($amenaza['probabilidad']) - 1 : 0;
    $xnow = $x + intval($amenaza['ajustada']['x']);

    if ($x < 0) {
        $x = 0;
    }
    if ($xnow < 0) {
        $xnow = 0;
    }
    if ($xnow > 4) {
        $xnow = 4;
    }

    $y = 0;
    if (isset($amenaza['confidencialidad']) && intval($amenaza['confidencialidad']) === 1) {
        $y = max(intval($conValue), $y);
    }

    if (isset($amenaza['integridad']) && intval($amenaza['integridad']) === 1) {
        $y = max(intval($intValue), $y);
    }

    if (isset($amenaza['disponibilidad']) && intval($amenaza['disponibilidad']) === 1) {
        $y = max(intval($disValue), $y);
    }

    $y = $ejeY[$y] - intval($amenaza['ajustada']['y']);
    if ($y > 4) {
        $y = 4;
    }
    if ($y < 0) {
        $y = 0;
    }

    $matrizRiesgos = [
        ["Moderado", "Alto", "Crítico", "Crítico", "Crítico"], // y=0
        ["Bajo", "Moderado", "Alto", "Crítico", "Crítico"],    // y=1
        ["Leve", "Bajo", "Moderado", "Alto", "Crítico"],       // y=2
        ["Leve", "Leve", "Bajo", "Moderado", "Alto"],          // y=3
        ["Leve", "Leve", "Leve", "Bajo", "Moderado"],          // y=4
    ];

    $nivelRiesgo = $matrizRiesgos[$y][$xnow];

    $valorNumerico = 0;
    switch ($nivelRiesgo) {
        case "Leve":
            $valorNumerico = 0;
            break;
        case "Bajo":
            $valorNumerico = 1;
            break;
        case "Moderado":
            $valorNumerico = 2;
            break;
        case "Alto":
            $valorNumerico = 3;
            break;
        case "Crítico":
            $valorNumerico = 4;
            break;
        default:
            $valorNumerico = 0;
            break;
    }

    return [
        'xnow' => $xnow,
        'y' => $y,
        'nivelRiesgo' => $nivelRiesgo,
        'valorNumerico' => $valorNumerico
    ];
}

function quote($item)
{
    if (is_numeric($item)) {
        return $item;
    } else {
        if ($item == 'si') {
            $item = 1;
        } elseif ($item == 'no') {
            $item = 0;
        } else {
            $item = "'" . $item . "'";
        }
        return $item;
    }
}

function comprobarRevisionExistente($id, $db, $token)
{
    $db_revision = new Revision(DB_SERV);
    $errores = [
        "error" => false,
        MESSAGE => ""
    ];
    $activo_padre = $db->getActivo($id);
    $hijos = $db->getTree($activo_padre[0], $token['data']);
    foreach ($hijos as $hijo) {
        $revisiones = $db_revision->obtenerRevisionByActivo($hijo["id"]);
        if (isset($revisiones[0])) {
            $revisiones = $db_revision->obtainRevisionFromId($revisiones[0]["id_revision"]);
            $errores["error"] = true;
            $errores[MESSAGE] = "No se puede eliminar el activo hijo <b>" . $hijo["nombre"] . "</b>, ya que tiene la revisión <b>" . $revisiones[0]["nombre"] . "</b> asociado. Gestiona esto para poder borrar el activo.";
            return $errores;
        }
    }
    $revisiones = $db_revision->obtenerRevisionByActivo($activo_padre[0]["id"]);
    if (isset($revisiones[0])) {
        $errores["error"] = true;
        $errores[MESSAGE] = "No se puede eliminar el activo <b>" . $activo_padre[0]["nombre"] . "</b>, ya que tiene la revisión <b>" . $revisiones[0]["nombre"] . "</b> asociado.  Gestiona esto para poder borrar el activo.";
        return $errores;
    }
    return $errores;
}

function comprobarPentestsExistente($id, $db, $token)
{
    $db_pentest = new Pentest(DB_SERV);
    $errores = [
        "error" => false,
        MESSAGE => ""
    ];
    $activo_padre = $db->getActivo($id);
    $hijos = $db->getTree($activo_padre[0], $token['data']);
    foreach ($hijos as $hijo) {
        $pentests = $db_pentest->obtenerPentestByActivo($hijo["id"]);
        if (isset($pentests[0])) {
            $pentests = $db_pentest->obtainPentestFromId($pentests[0]["id_pentest"]);
            $errores["error"] = true;
            $errores[MESSAGE] = "No se puede eliminar el activo hijo <b>" . $hijo["nombre"] . "</b>, ya que tiene el pentest <b>" . $pentests[0]["nombre"] . "</b> asociado. Gestiona esto para poder borrar el activo.";
            return $errores;
        }
    }
    $pentests = $db_pentest->obtenerPentestByActivo($activo_padre[0]["id"]);
    if (isset($pentests[0])) {
        $errores["error"] = true;
        $errores[MESSAGE] = "No se puede eliminar el activo <b>" . $activo_padre[0]["nombre"] . "</b>, ya que tiene el pentest <b>" . $pentests[0]["nombre"] . "</b> asociado.  Gestiona esto para poder borrar el activo.";
        return $errores;
    }
    return $errores;
}

function getActivosParaEvaluacion($id)
{
    $db = new Activos(DB_SERV);
    $activo = $db->getActivo($id);
    if (isset($activo[0]["id"])) {
        $activos = $db->getHijos($activo[0]["id"]);
    }
    $activos = array_merge($activos, $activo);
    $activos = getActivosUnicos($activos);
    $activosObligatorios = getActivosObligatoriosSinRepetir($activos);
    $activos = array_merge($activos, $activosObligatorios);
    return $activos;
}
// FUNCIONES RELACIONADA CON SERVICIOS
function obtenerServiciosBia($list = null)
{
    $db = new Activos(DB_SERV);
    if ($list) {
        $serviciosarchivados = $db->getActivosByTipo(42, null, true);
        $serviciossinarchivados = $db->getActivosByTipo(42, null, false);
        $servicios = array_merge($serviciosarchivados, $serviciossinarchivados);
        $conbia = $db->getNumServiciosBia();
        $conbiakey = array_column($conbia, 'activo');
        foreach ($servicios as $key => $servicio) {
            $servicios[$key]['fechabia'] = ' ';
            $index = array_search($servicio['id'], $conbiakey);
            if (array_search($servicio['id'], $conbiakey) !== false) {
                $servicios[$key]["fechabia"] = $conbia[$index]["fecha"];
                $servicios[$key]['bia'] = 1;
            } else {
                $servicios[$key]['bia'] = 0;
            }
        }
        return $servicios;
    } else {
        $conbia = count($db->getNumServiciosBia());
        $bia = array("CON", $conbia);
        $servicios = array("SIN", count($db->getNumActivos(42)) - $conbia);
        return array($bia, $servicios);
    }
}

function obtenerServiciosSinBia()
{
    $db = new Activos(DB_SERV);
    return $db->getServiciosSinBia();
}

function obtenerServiciosEcr()
{
    $db = new Activos(DB_SERV);
    $conecr = count($db->getNumSistemasEcr());
    $ecr = array("CON", $conecr);
    $servicios = array("SIN", count($db->getNumActivos(33)) - $conecr);
    return array($ecr, $servicios);
}

// FUNCIONES RELACIONADA CON SISTEMAS
function obtenerSistemasEcr()
{
    $db = new Activos(DB_SERV);
    $sistemas = $db->getActivosByTipo(33, null, false);
    $evals = array();
    $comprobar = array();
    foreach ($sistemas as $key => $sistema) {
        $padres = $db->getFathers($sistema);
        if (isset($sistema["id"])) {
            $activos = $db->getHijos($sistema["id"]);
            $activos = getActivosUnicos($activos);
            $activosObligatorios = getActivosObligatoriosSinRepetir($activos);
            $activos = array_merge($activos, $activosObligatorios, array($sistema));
            $fecha = $db->getFechaEvaluaciones($sistema["id"], true);
            if (isset($fecha[0])) {
                if ($fecha[0]["tipo_tabla"] == "evaluaciones") {
                    $preguntas = $db->getPreguntasEvaluacionByFecha($fecha[0]["id"]);
                } else {
                    $preguntas = $db->getPreguntasVersionByFecha($fecha[0]["id"]);
                }
                $preguntas_decode = json_decode($preguntas[0][PREGUNTAS], true);
                if (!isset($preguntas_decode["3ps"])) {
                    if (!array_search($preguntas[0][PREGUNTAS], $evals)) {
                        $comparacion = $db->getCompararNormativa("27002(2022),iso27001", $activos, $preguntas_decode);
                        $total = array_sum(array_column($comparacion['iso27001'], 'total'));
                        $cumple = array_sum(array_column($comparacion['iso27001'], 'yes'));
                        $comparacion = $cumple * 100 / $total;
                        $comparacion = round($comparacion, 2);
                        $sistemas[$key]['ecr'] = $comparacion;
                        array_push($evals, $preguntas[0][PREGUNTAS]);
                        array_push($comprobar, array(count($activos), $comparacion));
                    } else {
                        $index = array_search($preguntas[0][PREGUNTAS], $evals);
                        if (count($activos) == $comprobar[$index][0]) {
                            $sistemas[$key]['ecr'] = $comprobar[$index][1];
                        } else {
                            $comparacion = $db->getCompararNormativa("27002(2022),iso27001", $activos, $preguntas_decode);
                            $total = array_sum(array_column($comparacion['iso27001'], 'total'));
                            $cumple = array_sum(array_column($comparacion['iso27001'], 'yes'));
                            $comparacion = $cumple * 100 / $total;
                            $sistemas[$key]['ecr'] = round($comparacion, 2);
                        }
                    }
                } else {
                    $sistemas[$key]['ecr'] = "3PS";
                }
            } else {
                $sistemas[$key]['ecr'] = "SIN ECR";
            }
        }
        $sistemas[$key]['padres'] = $padres;
    }
    return $sistemas;
}

function getMediaSistemasECR($servicios): mixed
{
    $db = new Activos(DB_SERV);
    $sistemas = $db->getActivosByTipo(tipo: 33);
    $sistemas_servicios = getServiciobySistemaId($sistemas);
    $sistemas_servicios = sistemahasecr($sistemas_servicios);
    $sistemas_ecr = array_column($sistemas_servicios, "padres");
    foreach ($sistemas_ecr as $key => $sistema_ecr) {
        foreach ($sistema_ecr as $padre) {
            $index = array_search($padre["id"], array_column($servicios, "id"));
            if ($index !== false) {
                if (!isset($servicios[$index]["total"])) {
                    $servicios[$index]["total"] = 0;
                    $servicios[$index]["ecr"] = 0;
                }
                $servicios[$index]["total"] += 1;
                $servicios[$index]["ecr"] += $sistemas_servicios[$key]["ecr"];
            }
        }
    }
    return $servicios;
}

function sistemahasecr($sistemas)
{
    $db = new Activos(DB_SERV);
    foreach ($sistemas as $key => $sistema) {
        $ecr = $db->getFechaEvaluaciones($sistema["id"]);
        if (isset($ecr[0])) {
            $sistemas[$key]["ecr"] = 1;
        } else {
            $sistemas[$key]["ecr"] = 0;
        }
    }
    return $sistemas;
}

function getServiciobySistemaId($sistemas)
{
    $fathers = array();
    $db = new Activos(DB_SERV);

    foreach ($sistemas as $sistema) {
        if (!is_array($sistema)) {
            $sistema = array("id" => $sistema);
        }

        $padres = $db->getFathers($sistema);

        if (isset($padres[0])) {
            $sistema["padres"] = array_filter($padres, function ($element) {
                return $element["tipo"] == "42";
            });

            if (count($sistema["padres"]) !== 0) {
                $fathers[] = $sistema;
            } else {
                $fathers = array_merge($fathers, getServiciobySistemaId($padres));
            }
        }
    }

    if (!empty($fathers) && isset($fathers[0]["padres"])) {
        sort($fathers[0]["padres"]);
    }

    return $fathers;
}

function fechaActualizada($fecha, $fechaActual)
{
    if (isset($fecha)) {
        $fecha = DateTime::createFromFormat('Y-m-d H:i:s', $fecha);
        $diferencia = $fechaActual->diff($fecha);
        $diferenciayear = $diferencia->y;
        if ($diferenciayear < 1) {
            return 1;
        }
    }
    return 0;
}

function fechaRecienteActualizada($db, $id, $fechaActual)
{
    $fechas = $db->getFechasRecientes($id);
    if (isset($fechas)) {
        foreach ($fechas as $fecha) {
            if (fechaActualizada($fecha["fecha"], $fechaActual) == 1) {
                return 1;
            }
        }
    }
    return 0;
}

function getLastfecha($db, $id)
{
    $fecha = $db->getFechaEvaluaciones($id);
    if (isset($fecha[0])) {
        $fecha = $fecha[0];
        $masReciente = $fecha;
        $fechas = $db->getFechasRecientes($fecha["id"]);
        if (isset($fechas[0]["fecha"])) {
            foreach ($fechas as $fecha) {
                $fechaMasReciente = DateTime::createFromFormat('Y-m-d H:i:s', $masReciente["fecha"]);
                $sigFecha = DateTime::createFromFormat('Y-m-d H:i:s', $fecha["fecha"]);
                if ($fechaMasReciente < $sigFecha) {
                    $masReciente = $fecha;
                }
            }
        }
    } else {
        $fecha["fecha"] = NO_EVALUADO;
        return $fecha;
    }
    return $masReciente;
}

function insertarCriticidad($db)
{
    $user["rol"] = "admin";
    $servicios = $db->getActivosByTipo(42, $user, false);
    $servicios2 = $db->getActivosByTipo(42, $user, true);
    $servicios = array_merge($servicios, $servicios2);
    foreach ($servicios as $servicio) {
        $bia = $db->getBia($servicio["id"]);
        if (
            isset($bia[0], $bia[0]["meta_value"]) &&
            ($biacalculado = calcularBia($bia)) &&
            (
                $biacalculado["Con"]["Max"] >= 2 ||
                $biacalculado["Int"]["Max"] >= 2 ||
                $biacalculado["Dis"]["Max"] >= 2
            )
        ) {
            $db->editCriticidad($servicio["id"], 1);
            continue;
        }
        $db->editCriticidad($servicio["id"], 0);
    }
}

function editHijosCriticos($db, $servicio, $criticidad)
{
    $padre_critico = false;
    if ($criticidad == 0) {
        $hijos = $db->getTree($servicio);
    }
    $db->editCriticidad($servicio["id"], 0);
    if (isset($hijos)) {
        foreach ($hijos as $hijo) {
            $fathers = $db->getFathers($hijo);
            foreach ($fathers as $father) {
                if ($father["critico"] == 1) {
                    $padre_critico = true;
                }
                break;
            }
            if (!$padre_critico) {
                editHijosCriticos($db, $hijo, $criticidad);
            } else {
                break;
            }
        }
    } else {
        $hijos = $db->getTree($servicio);
        $db->editCriticidad($servicio["id"], 1);
        if (isset($hijos)) {
            foreach ($hijos as $hijo) {
                editHijosCriticos($db, $hijo, $criticidad);
            }
        }
    }
}

function changeEstructura(&$estructura, $padre)
{
    if ($padre["tipo"] == "Producto") {
        $estructura["Producto"] = $padre["nombre"];
    }
    if ($padre["tipo"] == "Unidad") {
        $estructura["Unidad"] = $padre["nombre"];
    }
    if ($padre["tipo"] == "Área") {
        $estructura["Area"] = $padre["nombre"];
    }
    if ($padre["tipo"] == "Dirección") {
        $estructura["Direccion"] = $padre["nombre"];
    }
    if ($padre["tipo"] == "Organización") {
        $estructura["Organizacion"] = $padre["nombre"];
    }
}

function obtenerIssues()
{
    $jira = new Jira();
    $issues_array = array();
    $issues = $jira->mostrarIssues(0, 50);
    foreach ($issues["issues"] as $issue) {
        $issues_array[] = $issue;
    }
    $results = $issues["maxResults"];
    while ($issues["total"] > $results) {
        $issues = $jira->mostrarIssues($results, 50);
        $results += $issues["maxResults"];
        foreach ($issues["issues"] as $issue) {
            $issues_array[] = $issue;
        }
    }
    return $issues_array;
}

function addIssueAndClose($pentests)
{
    $db = new Pentest(DB_SERV);
    $db_new = new Pentest("octopus_new");
    $issues = obtenerIssues();
    $jira = new JIRA();
    foreach ($pentests as $pentest) {
        $idPentest = $db->obtainPentestID($pentest[0]);
        foreach ($pentest[1] as $fecha) {
            foreach ($issues as $issue) {
                if (isset($issue["fields"]["customfield_24501"])) {
                    $proyecto = $issue["fields"]["customfield_24501"]["value"];
                    $fechaIssue = new DateTime($issue["fields"]["created"]);
                    $fechaIssue = $fechaIssue->format('Y-m-d');
                    $fechaPentest = new DateTime($fecha);
                    $fechaPentest = $fechaPentest->format('Y-m-d');
                    if ($proyecto == $pentest[9]) {
                        if ($fechaIssue == $fechaPentest) {
                            $vuln = $issue["fields"]["customfield_25704"]["child"]["value"];
                            if ($vuln == "Lack of Resources & Rate Limiting") {
                                $vuln = "Unrestricted Resource Consumption";
                            }
                            $vulnID = $db_new->obtainVulnID($vuln);
                            if (!isset($vulnID[0])) {
                                echo "Falta la vuln:" . $issue["fields"]["customfield_25704"]["child"]["value"] . "\n";
                            } else {
                                $db->insertVulnPentest($issue["key"], $idPentest[0]["id"], $vulnID[0]["id"]);
                            }
                        } else {
                            echo "No coincide";
                        }
                    }
                }
            }
        }
        $error = $jira->gestionarSistemas($idPentest[0]["id"]);
        if ($error != NO_EVALUATIONS) {
            $db->cerrarPentests($idPentest[0]["id"]);
        } else {
            return "Error";
        }
    }
}

function organizarPentest($arrayDatos)
{
    $pentestOrdenados = array();
    foreach ($arrayDatos as $index => $pentest) {
        $existe = false;
        if ($index != 0) {
            foreach ($pentestOrdenados as $pentestIndex => $pentAlmacenado) {
                if (
                    $pentAlmacenado[0] == $pentest[0]
                    && $pentAlmacenado[2] == $pentest[2]
                    && $pentAlmacenado[3] == $pentest[3]
                    && $pentAlmacenado[4] == $pentest[4]
                    && $pentAlmacenado[5] == $pentest[5]
                ) {
                    $fecha_objeto = $fecha_objeto = DateTime::createFromFormat('m/d/Y', $pentest[1]);
                    $fecha = $fecha_objeto->format('Y-m-d');
                    $pentestOrdenados[$pentestIndex][1][] = $fecha;
                    $existe = true;
                    break;
                }
            }
        }
        if (!$existe) {
            $fecha = $pentest[1];
            if ($index != 0) {
                $fecha_objeto = DateTime::createFromFormat('m/d/Y', $fecha);
                $fecha = $fecha_objeto->format('Y-m-d');
            }
            $pentest[1] = array();
            $pentest[1][] = $fecha;
            $pentestOrdenados[] = $pentest;
        }
    }
    return $pentestOrdenados;
}

function renombrarPentestDup($pentestOrganizados)
{
    foreach ($pentestOrganizados as $index => $pentest) {
        $pentestOrganizados[$index][] = $pentestOrganizados[$index][0];
        if ($pentestOrganizados[$index - 1][0] == "PEN_VIDEOCISO2023-01-19") {
            $pentestOrganizados[$index][0] = "PEN02_VIDEOCISO2023-01-19";
        } elseif ($pentestOrganizados[$index - 1][0] == "PEN02_VIDEOCISO2023-01-19") {
            $pentestOrganizados[$index][0] = "PEN03_VIDEOCISO2023-01-19";
        } elseif ($pentestOrganizados[$index - 1][0] == "PEN03_VIDEOCISO2023-01-19") {
            $pentestOrganizados[$index][0] = "PEN04_VIDEOCISO2023-01-19";
        } else {
            $pentestOrganizados[$index][0] = "PEN_" . $pentestOrganizados[$index][0] . $pentestOrganizados[$index][1][0];
        }
    }
    unset($pentestOrganizados[0]);
    return $pentestOrganizados;
}

// Función para obtener las métricas de un área
function obtenerMetricasArea($db, $area)
{
    $metricas = array();
    $hijos = $db->getTree($area[0]);
    $data = array(
        "Nombre" => $area[0]["nombre"],
        "CritNoExp" => 0,
        "Aplicaciones" => 0,
        "SisInformacion" => 0,
        "SisInformacionAct" => 0,
        "AppCriticaNoExp" => 0,
        "Criticos" => 0,
        "NoCriticos" => 0
    );

    foreach ($hijos as $index => $hijo) {
        foreach ($hijos as $key => $hijo2) {
            if ($hijo["nombre"] == $hijo2["nombre"] && $index != $key) {
                continue 2;
            } elseif ($key >= $index) {
                break;
            }
        }
        if ($hijo["critico"] == 1 && $hijo["expuesto"] == 0) {
            $data["CritNoExp"]++;
            $data["Criticos"]++;
            if ($hijo["tipo"] == 4) {
                $data["AppCriticaNoExp"]++;
            }
        } elseif ($hijo["critico"] != 1) {
            $data["NoCriticos"]++;
        }

        if ($hijo["tipo"] == 33) {
            $fecha = $db->getFechaEvaluaciones($hijo["id"], true);
            $data["SisInformacion"]++;
            if (isset($fecha[0])) {
                $fechaActual = new DateTime();
                if (fechaActualizada($fecha[0]["fecha"], $fechaActual) == 1 || fechaRecienteActualizada($db, $hijo["id"], $fechaActual) == 1) {
                    $data["SisInformacionAct"]++;
                }
            }
        } elseif ($hijo["tipo"] == 4) {
            $data["Aplicaciones"]++;
        }
    }

    $metricas[] = $data;
    return $metricas;
}

// Función para obtener las métricas de todas las áreas
function obtenerMetricasAreas($db, $areas)
{
    $metricas = array();
    foreach ($areas as $area) {
        $area = $db->getActivo($area);
        $metricas = array_merge($metricas, obtenerMetricasArea($db, $area));
    }
    return $metricas;
}

function obtenerTrimestre($fecha)
{
    // Convertir la fecha en timestamp
    $timestamp = strtotime($fecha);

    // Obtener el mes de la fecha proporcionada
    $mes = date('n', $timestamp);

    // Determinar el trimestre
    if ($mes >= 1 && $mes <= 3) {
        $trimestre = 'q1';
    } elseif ($mes >= 4 && $mes <= 6) {
        $trimestre = 'q2';
    } elseif ($mes >= 7 && $mes <= 9) {
        $trimestre = 'q3';
    } else {
        $trimestre = 'q4';
    }

    return $trimestre;
}

function estructurarFamilia($arboles)
{
    $familiaEstructurada = array();
    $estructura = array();
    foreach ($arboles as $arbol) {
        $estructura["Activo"] = $arbol["nombre"];
        $estructura["Tipo"] = $arbol["tipo"];
        if (isset($arbol["padre"])) {
            $padre = $arbol["padre"];
            while (true) {
                changeEstructura($estructura, $padre);
                if (isset($padre["padre"])) {
                    $padre = $padre["padre"];
                } else {
                    break;
                }
            }
            changeEstructura($estructura, $padre);
        }
        if (!isset($estructura["Producto"])) {
            $estructura["Producto"] = "Sin producto";
        }
        if (!isset($estructura["Unidad"])) {
            $estructura["Unidad"] = "Sin unidad";
        }
        if (!isset($estructura["Area"])) {
            $estructura["Area"] = "Sin área";
        }
        if (!isset($estructura["Direccion"])) {
            $estructura["Direccion"] = "Sin dirección";
        }

        $familiaEstructurada[] = $estructura;
    }
    return $familiaEstructurada;
}

function ordenarFamilia($familia, $id)
{
    $familia_arbol = array();
    foreach ($familia as $item) {
        if ($item["id"] == $id) {
            if ($item["padre"] != null) {
                $padres = ordenarFamilia($familia, $item["padre"]);
                foreach ($padres as $padre) {
                    $item["padre"] = $padre;
                    $familia_arbol[] = $item;
                }
            } else {
                $familia_arbol[] = $item;
            }
        }
    }
    return $familia_arbol;
}

function getServiciobyActivoId($activo, &$fathers = [])
{
    $db = new Activos(DB_SERV);

    $padres = $db->getFathers($activo);
    if ($padres) {
        foreach ($padres as $padre) {
            if ($padre["tipo"] == 42) {
                $fathers[] = $padre;
            } else {
                getServiciobyActivoId($padre, $fathers);
            }
        }
    }
    return $fathers;
}


function getParentescobySistemaId($sistemas, $parentesco)
{
    $db = new Activos(DB_SERV);
    foreach ($sistemas as $sistema) {
        if (!is_array($sistema)) {
            $sistema = array("id" => $sistema);
        }
        $padres = $db->getFathers($sistema);
        if (isset($padres[0])) {
            $sistema["padres"] = array_filter($padres, function ($i) use ($parentesco) {
                return $i["tipo"] == $parentesco;
            });
            if (count($sistema["padres"]) == 0) {
                $returnparentesco = getParentescobySistemaId($padres, $parentesco);
            } else {
                $returnparentesco = $sistema["padres"][0];
            }
        } else {
            $returnparentesco["nombre"] = "";
        }
    }
    return $returnparentesco;
}

function getcalculoamenazas($amenazas, $bia)
{
    $lvl = array('Leve', 'Medio', 'Moderado', 'Alto', 'Crítico');
    $ajustar = 0;
    $mapacalor = array(
        array("Leve", "Leve", "Leve", "Medio", "Moderado"),
        array("Leve", "Leve", "Medio", "Moderado", "Alto"),
        array("Leve", "Medio", "Moderado", "Alto", "Crítico"),
        array("Medio", "Moderado", "Alto", "Crítico", "Crítico"),
        array("Moderado", "Alto", "Crítico", "Crítico", "Crítico")
    );
    $resultado = array(
        "resumen" => array("Leve" => 0, "Medio" => 0, "Moderado" => 0, "Alto" => 0, "Crítico" => 0),
        "actual" => array_fill(0, 5, array_fill(0, 5, "")),
        "inherente" => array_fill(0, 5, array_fill(0, 5, "")),
        "residual" => array_fill(0, 5, array_fill(0, 5, ""))
    );
    $amenazaunicas = array();

    foreach ($amenazas as $amenaza) {
        if (!in_array($amenaza["id"], $amenazaunicas)) {
            $x = max(0, intval($amenaza["probabilidad"]) - 1);
            $xnow = max(0, $x + intval($amenaza["ajustada"]["x"]));
            $y = 0;

            if (isset($bia["Con"]["Max"]) && $amenaza["confidencialidad"] === 1) {
                $y = max($bia["Con"]["Max"], $y);
            }
            if (isset($bia["Int"]["Max"]) && $amenaza["integridad"] === 1) {
                $y = max($bia["Int"]["Max"], $y);
            }
            if (isset($bia["Dis"]["Max"]) && $amenaza["disponibilidad"] === 1) {
                $y = max($bia["Dis"]["Max"], $y);
            }

            $ynow = min(4, max(0, $y + intval($amenaza["ajustada"]["y"])));
            $xres = isset($amenaza["tiposUsf"]["Proactivo"]) ? 0 : $xnow;
            $yres = isset($amenaza["tiposUsf"]["Reactivo"]) ? 0 : $ynow;

            $riesgo = $mapacalor[$xnow][$ynow];
            $resultado["resumen"][$riesgo]++;
            $resultado["inherente"][$x][$y]++;
            $resultado["actual"][$xnow][$ynow]++;
            $resultado["residual"][$xres][$yres]++;
            $amenazaunicas[] = $amenaza["id"];
        }
    }

    $totalamenazas = array_sum($resultado["resumen"]);

    if ($totalamenazas != 0) {
        $a = ($resultado["resumen"]["Leve"] * 100) / $totalamenazas;
        $b = ($resultado["resumen"]["Medio"] * 100) / $totalamenazas;
        $ab = $a + $b;

        if ($ab <= 20) {
            $ajustar = 4;
        } elseif ($ab >= 90) {
            $ajustar = 0;
        } elseif ($ab > 20 && $ab <= 40) {
            $ajustar = 3;
        } elseif ($ab > 40 && $ab <= 80) {
            $ajustar = 2;
        } elseif ($ab > 80 && $ab <= 90) {
            $ajustar = 1;
        } else {
            $ajustar = 5;
        }
    }

    $resultado["riesgoa"] = $lvl[$ajustar];
    return $resultado;
}

// FUNCIONES DE CÁLCULOS
function calcularBia($bia)
{
    if (isset($bia[0])) {
        $bia = json_decode($bia[0]["meta_value"], true);
        unset($bia["id"]);
        $hash_bia = hash('sha256', json_encode($bia));
        // Buscar en la tabla
        $cached_result = getBiaCache($hash_bia);
        if ($cached_result) {
            return $cached_result;
        } else {

            $matriz['Con']['Max'] = 0;
            $matriz['Int']['Max'] = 0;
            $matriz['Dis']['Max'] = 0;

            $matriz['retencion'] = 30;

            // rellenar con "" los valores faltantes de la matriz
            $bia = array_replace(array_fill(1, 46, ""), $bia);

            if ($bia[34] || $bia[37]) {
                $matriz['retencion'] += 15;
            }
            if ($bia[3] || $bia[2]) {
                $matriz['retencion'] += 15;
            }
            if ($bia[34]) {
                $matriz['retencion'] += 15;
            }
            if ($bia[20]) {
                $matriz['retencion'] += 15;
            }

            // BLOQUE  CONFinancial
            $finConf = 0;
            if ($bia[1] > 0 || $bia[2] > 0 || $bia[3] > 0) {
                $finConf += 2;
            }
            if ($bia[17] > 0) {
                $finConf += 1;
            }
            if ($bia[18] > 0) {
                $finConf += 1;
            }
            $finConf = min($finConf, 4);
            $matriz['Con']['Fin'] = $finConf;
            $matriz['Con']['Max'] = $matriz['Con']['Fin'];

            // BLOQUE CONOperacional
            $opeConf = 0;
            if ($bia[1] > 0 || $bia[2] > 0 || $bia[3] > 0) {
                $opeConf += 2;
            }
            if ($bia[27] > 0) {
                $opeConf += 1;
            }
            $opeConf = min($opeConf, 4);
            $matriz['Con']['Op'] = $opeConf;
            $matriz['Con']['Max'] = max($matriz['Con']['Max'], $matriz['Con']['Op']);

            // BLOQUE CONLegal
            $legConf = 0;
            if ($bia[1] > 0 || $bia[2] > 0 || $bia[3] > 0) {
                $legConf += 2;
            }
            if ($bia[19] > 0) {
                $legConf += 1;
            }
            if ($bia[37] > 0) {
                $legConf += 1;
            }
            $legConf = min($legConf, 4);
            $matriz['Con']['Le'] = $legConf;
            $matriz['Con']['Max'] = max($matriz['Con']['Max'], $matriz['Con']['Le']);

            // BLOQUE CON Reputación
            $repConf = 0;
            if ($bia[1] > 0 || $bia[2] > 0 || $bia[3] > 0) {
                $repConf += 2;
            }
            if ($bia[31] > 0) {
                $repConf += 1;
            }
            if ($bia[46] > 0) {
                $repConf += 1;
            }
            $repConf = min($repConf, 4);
            $matriz['Con']['Rep'] = $repConf;
            $matriz['Con']['Max'] = max($matriz['Con']['Max'], $matriz['Con']['Rep']);

            // BLOQUE CONF Health
            $salConf = 0;
            if ($bia[33] > 0) {
                $salConf += 2;
                if ($bia[1] > 0 || $bia[2] > 0 || $bia[3] > 0) {
                    $salConf += 2;
                }
            }
            $salConf = min($salConf, 4);
            $matriz['Con']['Sal'] = $salConf;
            $matriz['Con']['Max'] = max($matriz['Con']['Max'], $matriz['Con']['Sal']);

            // BLOQUE CON Privacidad
            $privConf = 0;
            if (isset($bia[43]) && $bia[43] > 0) {
                $privConf = 4;
            }
            if ($bia[1] > 0 || $bia[2] > 0 || $bia[3] > 0) {
                $privConf += 2;
            }
            $privConf = min($privConf, 4);
            $matriz['Con']['Pri'] = $privConf;
            $matriz['Con']['Max'] = max($matriz['Con']['Max'], $matriz['Con']['Pri']);

            // BLOQUE INT Financiero
            $matriz['Int']['Fin'] = 0;
            if ($bia[6] > 0) {
                $matriz['Int']['Fin'] = $matriz['Con']['Fin'];
            } else {
                $matriz['Int']['Fin'] = max(0, $matriz['Con']['Fin'] - 1);
            }
            $matriz['Int']['Fin'] = min($matriz['Int']['Fin'], 4);
            $matriz['Int']['Max'] = max($matriz['Int']['Max'], $matriz['Int']['Fin']);

            // BLOQUE INTOperacional
            if ($bia[6] > 0) {
                $matriz['Int']['Op'] = $matriz['Con']['Op'];
            } else {
                $matriz['Int']['Op'] = max(0, $matriz['Con']['Op'] - 1);
            }
            $matriz['Int']['Op'] = min($matriz['Int']['Op'], 4);
            $matriz['Int']['Max'] = max($matriz['Int']['Max'], $matriz['Int']['Op']);

            // BLOQUE INTLegal
            $matriz['Int']['Le'] = 0;
            if ($bia[6] > 0) {
                $matriz['Int']['Le'] = $matriz['Con']['Le'];
            } else {
                $matriz['Int']['Le'] = max(0, $matriz['Con']['Le'] - 1);
            }
            $matriz['Int']['Le'] = min($matriz['Int']['Le'], 4);
            $matriz['Int']['Max'] = max($matriz['Int']['Max'], $matriz['Int']['Le']);

            // BLOQUE INTRep
            $matriz['Int']['Rep'] = 0;
            if ($bia[6] > 0) {
                $matriz['Int']['Rep'] = $matriz['Con']['Rep'];
            } else {
                $matriz['Int']['Rep'] = max(0, $matriz['Con']['Rep'] - 1);
            }
            $matriz['Int']['Rep'] = min($matriz['Int']['Rep'], 4);
            $matriz['Int']['Max'] = max($matriz['Int']['Max'], $matriz['Int']['Rep']);

            // BLOQUE IntLHealth
            $matriz['Int']['Sal'] = 0;
            if ($bia[6] > 0) {
                $matriz['Int']['Sal'] = $matriz['Con']['Sal'];
            } else {
                $matriz['Int']['Sal'] = max(0, $matriz['Con']['Sal'] - 1);
            }
            $matriz['Int']['Sal'] = min($matriz['Int']['Sal'], 4);
            $matriz['Int']['Max'] = max($matriz['Int']['Max'], $matriz['Int']['Sal']);

            // BLOQUE INTPrivacidad
            if ($bia[6] > 0) {
                $matriz['Int']['Pri'] = $matriz['Con']['Pri'];
            } else {
                $matriz['Int']['Pri'] = max(0, $matriz['Con']['Pri'] - 1);
            }
            $matriz['Int']['Pri'] = min($matriz['Int']['Pri'], 4);
            $matriz['Int']['Max'] = max($matriz['Int']['Max'], $matriz['Int']['Pri']);

            // BLOQUE DISFinanciero
            $finAva = 0;
            if ($bia[8] == 0 || $bia[8] == 1 || $bia[8] == 2) {
                $finAva += 1;
            } elseif ($bia[8] == 3 || $bia[8] == 4) {
                $finAva += 0;
            }

            if ($bia[9] == 0) {
                $finAva += 4;
            } elseif ($bia[9] == 1) {
                $finAva += 3;
            } elseif ($bia[9] == 2) {
                $finAva += 2;
            } elseif ($bia[9] == 3) {
                $finAva += 1;
            } elseif ($bia[9] == 4) {
                $finAva += 0;
            }

            if ($bia[14] > 0 || $bia[13] > 0) {
                $finAva += 1;
            }
            if ($bia[17] > 0 || $bia[18] > 0) {
                $finAva += 1;
            }
            $finAva = min($finAva, 4);
            $matriz['Dis']['Fin'] = $finAva;
            $matriz['Dis']['Max'] = max($matriz['Dis']['Max'], $matriz['Dis']['Fin']);

            // BLOQUE DISOperacional
            $opeAva = 0;
            if ($bia[8] == 0 || $bia[8] == 1 || $bia[8] == 2) {
                $opeAva += 1;
            } elseif ($bia[8] == 3 || $bia[8] == 4) {
                $opeAva += 0;
            }

            if ($bia[9] == 0) {
                $opeAva += 4;
            } elseif ($bia[9] == 1) {
                $opeAva += 3;
            } elseif ($bia[9] == 2) {
                $opeAva += 2;
            } elseif ($bia[9] == 3) {
                $opeAva += 1;
            } elseif ($bia[9] == 4) {
                $opeAva += 0;
            }

            if ($bia[14] > 0 || $bia[13] > 0) {
                $opeAva += 1;
            }
            if ($bia[27] > 0) {
                $opeAva += 1;
            }
            $opeAva = min($opeAva, 4);
            $matriz['Dis']['Op'] = $opeAva;
            $matriz['Dis']['Max'] = max($matriz['Dis']['Max'], $matriz['Dis']['Op']);

            // BLOQUE DISLegal
            $legAva = 0;
            if ($bia[8] == 0 || $bia[8] == 1 || $bia[8] == 2) {
                $legAva += 1;
            } elseif ($bia[8] == 3 || $bia[8] == 4) {
                $legAva += 0;
            }

            if ($bia[9] == 0) {
                $legAva += 4;
            } elseif ($bia[9] == 1) {
                $legAva += 3;
            } elseif ($bia[9] == 2) {
                $legAva += 2;
            } elseif ($bia[9] == 3) {
                $legAva += 1;
            } elseif ($bia[9] == 4) {
                $legAva += 0;
            }

            if ($bia[14] > 0 || $bia[13] > 0) {
                $legAva += 1;
            }
            if ($bia[19] > 0 || $bia[37] > 0) {
                $legAva += 1;
            }
            $legAva = min($legAva, 4);
            $matriz['Dis']['Le'] = $legAva;
            $matriz['Dis']['Max'] = max($matriz['Dis']['Max'], $matriz['Dis']['Le']);

            // BLOQUE DISRep
            $repAva = 0;
            if ($bia[8] == 0 || $bia[8] == 1 || $bia[8] == 2) {
                $repAva += 1;
            } elseif ($bia[8] == 3 || $bia[8] == 4) {
                $repAva += 0;
            }

            if ($bia[9] == 0) {
                $repAva += 4;
            } elseif ($bia[9] == 1) {
                $repAva += 3;
            } elseif ($bia[9] == 2) {
                $repAva += 2;
            } elseif ($bia[9] == 3) {
                $repAva += 1;
            } elseif ($bia[9] == 4) {
                $repAva += 0;
            }

            if ($bia[13] > 0 || $bia[14] > 0) {
                $repAva += 1;
            }
            if ($bia[31] > 0) {
                $repAva += 1;
            }
            if ($bia[46] > 0) {
                $repAva += 1;
            }
            $repAva = min($repAva, 4);
            $matriz['Dis']['Rep'] = $repAva;
            $matriz['Dis']['Max'] = max($matriz['Dis']['Max'], $matriz['Dis']['Rep']);

            // BLOQUE DIS Health
            $matriz['Dis']['Sal'] = 0;
            $salAva = 0;
            if ($bia[33] > 0) {
                if ($bia[8] == 0 || $bia[8] == 1 || $bia[8] == 2) {
                    $salAva += 1;
                } elseif ($bia[8] == 3 || $bia[8] == 4) {
                    $salAva += 0;
                }

                if ($bia[9] == 0) {
                    $salAva += 4;
                } elseif ($bia[9] == 1) {
                    $salAva += 3;
                } elseif ($bia[9] == 2) {
                    $salAva += 2;
                } elseif ($bia[9] == 3) {
                    $salAva += 1;
                } elseif ($bia[9] == 4) {
                    $salAva += 0;
                }
            }
            if ($bia[13] > 0 || $bia[14] > 0) {
                    $salAva += 1;
            }
            $salAva = min($salAva, 4);
            $matriz['Dis']['Sal'] = $salAva;
            $matriz['Dis']['Max'] = max($matriz['Dis']['Max'], $matriz['Dis']['Sal']);

            // BLOQUE DisPrivacidad
            $privAva = 0;
            if ($bia[8] == 0 || $bia[8] == 1 || $bia[8] == 2) {
                $privAva += 1;
            } elseif ($bia[8] == 3 || $bia[8] == 4) {
                $privAva += 0;
            }

            if ($bia[9] == 0) {
                $privAva += 4;
            } elseif ($bia[9] == 1) {
                $privAva += 3;
            } elseif ($bia[9] == 2) {
                $privAva += 2;
            } elseif ($bia[9] == 3) {
                $privAva += 1;
            } elseif ($bia[9] == 4) {
                $privAva += 0;
            }

            if ($bia[13] > 0 || $bia[14] > 0) {
                $privAva += 1;
            }
            if ($bia[43] > 0) {
                $privAva += 1;
            }
            $privAva = min($privAva, 4);
            $matriz['Dis']['Pri'] = $privAva;
            $matriz['Dis']['Max'] = max($matriz['Dis']['Max'], $matriz['Dis']['Pri']);
            updateBiaCache($hash_bia, $matriz);
            return $matriz;
        }
    }
}

// FUNCIONES CON EXCEL
function crearActivosExcel($sheetData, $user)
{
    $check = 0;
    $c = function ($v) {
        return array_filter($v) != array();
    };
    $error = false;
    $sheetData = array_filter($sheetData, $c);
    $activos = array();
    while ($check < 1) {
        $linea = 1;
        foreach ($sheetData as $row) {
            if ($linea > 1) {
                if ($row['A'] == $row['C']) {
                    $error = array(ERROR => true, MESSAGE => "Error en la linea $linea ($row[A]), ese activo no puede estar relacionado consigo mismo por que seria un bucle infinito. No se ha cargado ningún activo.");
                    break;
                }
                $db = new Activos();
                $tipo = $db->getClaseActivoByTipo($row['B']);
                if (!isset($tipo[0])) {
                    $error = array(ERROR => true, MESSAGE => "Error en la linea $linea ($row[B]), esa clase de activo no existe en el sistema. No se ha cargado ningún activo.");
                    break;
                }

                $activos = array_merge($activos, array($row["A"]));

                $db = new Activos(DB_SERV);
                if ($row['C'] === null) {
                    $padre[0]['id'] = 'undefined';
                } else {
                    $padre = $db->getActivoByNombre($row['C']);
                    if (!isset($padre[0]) && !in_array($row['C'], $activos)) {
                        $error = array(ERROR => true, MESSAGE => "Error en la linea $linea ($row[C]), el activo padre no existe. No se ha cargado ningún activo.");
                        break;
                    }
                }
            }
            $linea++;
        }
        if ($error) {
            return $error;
        }
        $check++;
    }

    $linea = 1;
    foreach ($sheetData as $row) {
        if ($linea > 1) {
            $db = new Activos();
            $tipo = $db->getClaseActivoByTipo($row['B'])[0];
            $db = new Activos(DB_SERV);
            if ($row['C'] === null) {
                $padre[0]['id'] = 'undefined';
            } else {
                $padre = $db->getActivoByNombre($row['C']);
            }
            if (isset($padre[0])) {
                $result = $db->newActivo($row['A'], $tipo['id'], $padre[0]['id'], $user);
            }
            if ($result == '') {
                return array(ERROR => true, MESSAGE => "Error en la linea $linea ($row[A]), ese activo ya existe. Los activos anteriores se han creado.");
            }
        }
        $linea++;
    }
    return array(ERROR => false, MESSAGE => 'Activos creados correctamente.');
}

// FUNCIONES VARIADAS
function calculateEtag($data)
{
    return crc32(json_encode($data));
}

function obtenerMedia($arrayRiesgo)
{
    $lvl = array(NO_EVALUADO, 'Leve', 'Medio', 'Moderado', 'Alto', 'Crítico');
    $totalRiesgo = 0;
    $key = 0;
    foreach ($arrayRiesgo as $key => $riesgo) {
        $i = 0;
        while ($i < 6) {
            if ($riesgo == $lvl[$i]) {
                $totalRiesgo += $i;
                if ($i == 0) {
                    return "Faltan evaluaciones";
                }
            }
            $i += 1;
        }
    }
    $totalRiesgo = $totalRiesgo / ($key + 1);
    $mediaRiesgo = round($totalRiesgo);
    return $lvl[$mediaRiesgo];
}

function sameYear($fecha)
{
    $year_actual = date('Y');

    $year_fecha = date('Y', strtotime($fecha));

    return $year_actual == $year_fecha;
}

function reportedInThisQ($activoName, $trimestreActual)
{
    $db = new Activos(DB_KPMS);

    $reportes = $db->getReportActivoTrimestre($activoName, $trimestreActual);

    if (isset($reportes[0]) && sameYear($reportes[0]["fecha"])) {
        return true;
    }

    return false;
}
function mediaEval($id)
{
    $db = new Activos(DB_SERV);
    $hijos = $db->getChild($id);
    $arrayRiesgo = array();
    $arrayRiesgo = [];
    foreach ($hijos as $hijo) {
        if ($hijo["tipo"] == 33) {
            $fecha = getLastfecha($db, $hijo["id"]);
            $activos = getActivosParaEvaluacion($hijo["id"]);
            if ($fecha["fecha"] != NO_EVALUADO) {
                $preguntas = $db->getPreguntasVersionByFecha($fecha["id"]);
                if (!isset($preguntas[0])) {
                    $preguntas = $db->getPreguntasEvaluacionByFecha($fecha["id"]);
                }
                if (isset($preguntas[0][PREGUNTAS])) {
                    $preguntas = json_decode($preguntas[0][PREGUNTAS], true);
                    $preguntas = prepararPreguntas($preguntas, $hijo["id"]);
                }
                $eval = getEvaluacionActivos($activos, $preguntas);
                $bia = $db->getBia($id);
                if (isset($bia)) {
                    $resultadobia = calcularBia($bia);
                }
                $amenazas = getcalculoamenazas($eval["amenazas"], $resultadobia);
                $arrayRiesgo[] = $amenazas["riesgoa"];
            } else {
                $amenazas["riesgoa"] = NO_EVALUADO;
                $arrayRiesgo[] = $amenazas["riesgoa"];
            }
        }
    }
    return obtenerMedia($arrayRiesgo);
}


function refreshToken($token)
{
    $error["error"] = false;
    $tokenclass = new TokenEncryptor();
    // Validación de input
    if (empty($token)) {
        return false;
    }

    $aud = '';

    // Recopilación de la dirección IP y el agente de usuario
    $aud .= rawurlencode($_SERVER['HTTP_USER_AGENT']);
    $aud .= gethostname();

    $time = time();
    $decriptToken = $tokenclass->decrypt($token);
    if (!$decriptToken) {
        throw new SessionException("Sesión no encontrada.", 404);
    }
    if ($decriptToken["new"]["exp"] < $time) {
        throw new SessionException("La sesión ha expirado.", 401);
    }

    if ($decriptToken["saved"]["userdata"] != $aud) {
        throw new SessionException("Sesión iniciada desde otro dispositivo.", 401);
    }
    $claims = [
        "iss" => rawurlencode($_SERVER['REQUEST_URI']),
        "aud" => $aud,
        "iat" => $time,
        "exp" => $time + 3600,
        'samesite' => 'Strict',
        "data" => $decriptToken["new"]["data"]
    ];
    $tokenclass->encrypt(json_encode($claims), "AES-256-CBC", $decriptToken["saved"]["hash"]);
    return $claims;
}

function cambiarEvaluacionPac($idPac, $tipo)
{
    $db = new Activos(DB_SERV);
    $db_other = new Pentest("octopus_new");
    $db_serv = new Activos(DB_SERV);
    $proyecto = $db->getProyectoPacSys($idPac)[0]["proyecto_id"];
    $defaultNormativeId = $db->getDefaultNormative()[0]["id"];
    $usfs = array_map(function ($usf) use ($db_other, $defaultNormativeId) {
        return $db_other->getPreguntasByUSF($usf["id"], $defaultNormativeId);
    }, $db->getUSFProyectoPac($proyecto));
    $codPac = $db_serv->getProyectoById($proyecto)[0]["cod"];
    $activoId = $db->getActivoByPacId($idPac)[0]["activo_id"];
    $fecha = $db->getFechaEvaluaciones($activoId, true);
    if (!isset($fecha[0])) {
        return NO_EVALUATIONS;
    }
    if ($fecha[0]["tipo_tabla"] == "evaluaciones_versiones") {
        $preguntas = $db->getPreguntasVersionByFecha($fecha[0]["id"]);
    } else {
        $preguntas = $db->getPreguntasEvaluacionByFecha($fecha[0]["id"]);
    }

    if (!isset($preguntas[0])) {
        return NO_EVALUATIONS;
    }

    $preguntas = json_decode($preguntas[0]["preguntas"], true);

    foreach ($usfs as $usf) {
        foreach ($usf as $preguntasUSF) {
            if (isset($preguntas[$preguntasUSF["id_preguntas"]])) {
                $preguntas[$preguntasUSF["id_preguntas"]] = $tipo == 1 ? "1" : "0";
            }
        }
    }

    $index = array_search("evaluaciones", array_column($fecha, "tipo_tabla"));
    if ($index !== false) {
        $descripcion = $tipo == 1 ? "$codPac cerrado" : "$codPac abierto";
        $db->editEval($fecha[$index]["id"], $fecha[0]["id"], $preguntas, $descripcion);
    } else {
        return NO_EVALUATIONS;
    }
}

function eliminarPreguntasExistentes($nuevas, $originales)
{
    foreach ($nuevas as $key => $value) {
        if (array_key_exists($value["id"], $originales)) {
            unset($nuevas[$key]);
        }
    }
    sort($nuevas);
    return $nuevas;
}

function invalidateToken($token)
{
    $tokenclass = new TokenEncryptor();
    if (empty($token)) {
        return false;
    }

    $aud = '';

    // Recopilación de la dirección IP y el agente de usuario
    $aud .= rawurlencode($_SERVER['HTTP_USER_AGENT']);
    $aud .= gethostname();

    $time = time();
    $decriptToken = $tokenclass->decrypt($token);
    if ($decriptToken !== false && $decriptToken["aud"] === $aud) {
        $claims = [
            "iss" => rawurlencode($_SERVER['REQUEST_URI']),
            "aud" => $aud,
            "iat" => $time,
            "exp" => $time - 3600,
            'samesite' => 'Strict',
            "data" => $decriptToken["data"]
        ];
        $tokenclass->encrypt(json_encode($claims), "AES-256-CBC", $decriptToken["jti"]);
        return $claims;
    } else {
        return false;
    }
}

// Función para manejar la respuesta del captcha
function handleCaptchaResponse($response, $error, $message, $additionalData = [])
{
    $response_data = array_merge([ERROR => $error, MESSAGE => $message], $additionalData);
    $response->getBody()->write(json_encode($response_data));
    return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
}


function isHuman($token, $isV2 = false)
{
    $recaptcha_secret = $isV2 ? '6LcCdE4qAAAAAAgkZJXILTSYG_uIwMRp8d08Qyez' : '6LcM73oaAAAAAG1ib9JW4t3uhUq9Iws2fvoE7hHE';
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $result = 'captcha_error'; // Valor predeterminado

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Establecer un tiempo de espera máximo de 10 segundos
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'secret' => $recaptcha_secret,
        'response' => $token
    ]);

    $recaptcha = curl_exec($ch);
    curl_close($ch);

    if ($recaptcha !== false) {
        $recaptcha = json_decode($recaptcha, true);

        if ($recaptcha["success"] === true) {
            if ($isV2 || $recaptcha["score"] >= 0.7) {
                $result = 'human';
            } else {
                $result = 'suspicious';
            }
        } else {
            $result = 'captcha_failed';
        }
    }

    return $result;
}

function randomPassword()
{
    return bin2hex(random_bytes(6));
}

function getEvaluationDatesPac($id)
{
    $db = new Activos(DB_SERV);
    return $db->getFechaEvaluaciones($id, true);
}

function updateListPac($id)
{
    $db = new Activos(DB_SERV);
    $activos = getActivosParaEvaluacion($id);
    $activo = $db->getActivo($id);
    $eval = $db->getFechaEvaluaciones($id, true);
    if (isset($eval[0]) && isset($activo[0])) {
        if ($eval[0]["tipo_tabla"] == "evaluaciones") {
            $preguntas = $db->getPreguntasEvaluacionByFecha($eval[0]["id"]);
        } else {
            $preguntas = $db->getPreguntasVersionByFecha($eval[0]["id"]);
        }
        if (isset($preguntas[0][PREGUNTAS])) {
            $preguntas = json_decode($preguntas[0][PREGUNTAS], true);
            if (!isset($preguntas["3ps"])) {
                $resultado = getEvaluacionActivos($activos, $preguntas);
                if (isset($resultado['pac'])) {
                    foreach ($resultado['pac'] as $pac) {
                        $eval = getEvaluationDatesPac($id);
                        $pactotal = array_sum(array_column($pac['usf'], 'total'));
                        $pacct = array_sum(array_column($pac['usf'], 'ct'));
                        $pac = $db->getProyectoByNombre($pac['nombre']);
                        $total = count($db->findPac($pac[0]["id"], $id));
                        if ($pacct / $pactotal <= 0.80 && $total < 1) {
                            if ($eval[0]["tipo_tabla"] == "evaluaciones") {
                                $evalid = $db->getEvalById($eval[0]["id"]);
                                $evalid = $evalid[0]["id"];
                            } else {
                                $evalid = $db->getVersionById($eval[0]["id"]);
                                $evalid = $evalid[0]["evaluacion_id"];
                            }
                            $db->newPacSeguimiento($activo[0]["id"], $pac[0]["id"], $evalid);
                        }
                    }
                }
            }
        }
    }
}

function getNormativas($comparacion)
{
    $response = array();
    foreach ($comparacion as $norma => $valores) {
        $total = array_column($valores, 'total');
        $total = array_sum($total);
        $ne = array_column($valores, "NE");
        $ne = array_sum($ne);
        if ($total !== 0) {
            $media = $ne / $total * 100;
        } else {
            $media = 100;
        }

        if ($media < 30) {
            $response = array_merge($response, array($norma));
        }
    }
    return $response;
}

function getUsfByPreguntas($preguntas, $normativas)
{
    $db = new Activos();
    foreach ($preguntas as $key => $valor) {
        $newkey = $db->getUsfByPreguntas($key, $normativas);
        if (isset($newkey[0]['USF'])) {
            $usf = array_column($newkey, 'USF');
            $add = array_fill_keys($usf, $valor);
            $resultado[$key] = $add;
        }
    }
    return $resultado;
}

function contarCumplimiento($values)
{
    $cumplimiento["cumplimiento"] = array();
    $cumplimiento["total"] = array('ct' => 0, 'cp' => 0, 'nc' => 0, 'ne' => 0);
    $dominios = array();
    if (!is_null($values)) {
        foreach ($values as $value) {
            if (!in_array($value['dominio'], $dominios)) {
                array_push($dominios, $value['dominio']);
                array_push($cumplimiento["cumplimiento"], array('ct' => 0, 'cp' => 0, 'nc' => 0, 'ne' => 0));
            }
            $index = array_search($value['dominio'], $dominios);
            if ($value['mne'] >= 60) {
                $cumplimiento["cumplimiento"][$index]['ne']++;
                $cumplimiento["total"]['ne']++;
            } elseif ($value['mct'] < 50) {
                $cumplimiento["cumplimiento"][$index]['nc']++;
                $cumplimiento["total"]['nc']++;
            } elseif ($value['mct'] < 85) {
                $cumplimiento["cumplimiento"][$index]['cp']++;
                $cumplimiento["total"]['cp']++;
            } else {
                $cumplimiento["cumplimiento"][$index]['ct']++;
                $cumplimiento["total"]['ct']++;
            }
        }
    }
    $cumplimiento["dominios"] = $dominios;
    return $cumplimiento;
}

function get3psById($preguntas)
{
    $db = new Activos();
    foreach ($preguntas as $key => $valor) {
        if ($key !== '3ps') {
            $newkey = $db->get3psByID($key);
            if (isset($newkey[0]['3ps'])) {
                $usf = array_column($newkey, '3ps');
                $add = array_fill_keys($usf, $valor);
                $resultado[$key] = $add;
            }
        }
    }
    return $resultado;
}

function obtenerUsfAmenazas($amenazas)
{
    $count = 0;
    $db = new Activos();
    foreach ($amenazas as $amenaza) {
        $amenazas[$count]['usf'] = $db->getCodUsfByAmenaza($amenaza['id']);
        $count++;
    }
    return $amenazas;
}

function obtener3psAmenazas($amenazas)
{
    $count = 0;
    $db = new Activos();
    foreach ($amenazas as $amenaza) {
        $amenazas[$count]['ps'] = $db->getCod3psByAmenaza($amenaza['id']);
        $count++;
    }
    return $amenazas;
}

// FUNCIONES PARA CARGAR VISTAS HTML
function cargarVista($vista, $request, $response)
{
    global $error, $basePath;
    if ($vista === "eval") {
        $vista = 'eval.phtml';
    }
    if ($vista === "historial") {
        $vista = 'history.phtml';
    }
    if ($vista === "pac") {
        $vista = 'pac.phtml';
    }

    $parametros = $request->getQueryParams();

    if (isset($parametros['norma'])) {
        $id = "";
        $servicio = "";
        require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . $vista;
    }

    if (isset($parametros['id']) && !empty($parametros['id']) && is_numeric($parametros['id'])) {
        $id = $parametros['id'];
        $db = new Activos(DB_SERV);
        $servicio = $db->getActivo($id);
        if (count($servicio) !== 0) {
            require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . $vista;
        } else {
            return $response->withRedirect($basePath . "/" . $error->getUrlforCode(202));
        }
        return $response;
    }
}

// FUNCIONES CON PHP Y HTML FRONTAL
function getmenu($login = true, $menu = true)
{
?>
    <div class="top-menu">
        <div class="row d-flex align-items-center top-menu-container">
            <div class="col-2">
                <a href="./home"><img class="header-logo" alt='Telefonica' src="./img/telefonica_new.svg" /></a>
            </div>
            <?php if ($login) { ?>
                <div class="col-md-7 offset-md-2 text-end">
                    <div class='col-auto ms-auto'>
                        <a href="./profile"><img class='pointer icono' alt='perfil' src='./img/profile.svg' title='Perfil' /></a>
                        <span class="margin-right-20"></span>
                        <img class='issue icono pointer' alt='reportar' src='./img/atencion.svg' title='Reportar un problema' />
                        <span class="margin-right-20"></span>
                        <img class='pointer logout icono' alt='salir' src='./img/logout.svg' title='Salir' />
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php if ($menu) { ?>
        <div id="Sidenav" class="sidenav">
            <a class="closebtn closebtn-nav toggleNav pointer">
                <img class="icono" alt="icono desplegar" title="Desplegar" src="./img/collapse_blue.svg">
            </a>
            <a href="./app">
                <img class="icono" src="./img/servicios.svg" title="Servicios" alt="icono Servicios">
                <span class="menu-text">Servicios</span>
            </a>
            <a href="./evs">
                <img class="icono" src="./img/evs.svg" title="Módulo EVS" alt="icono Módulo EVS">
                <span class="menu-text">Módulo EVS</span>
            </a>
            <a href="./evalmanager">
                <img class="icono" src="./img/PGM.svg" title="Gestor ERS" alt="icono Gestor ERS">
                <span class="menu-text">Gestor PGM</span>
            </a>
            <a href="./normativas">
                <img class="icono" src="./img/Escudo.svg" title="Gestor ERS" alt="icono Gestor Normativas">
                <span class="menu-text">Gestión normativa</span>
            </a>
            <a href="./dashboard">
                <img class="icono" src="./img/dashboard.svg" title="Cuadro de mando" alt="icono Cuadro de mando">
                <span class="menu-text">Cuadro de mando</span>
            </a>
            <a href="./reportarkpms">
                <img class="icono" src="./img/kpi.svg" title="KPMS" alt="icono KPMS">
                <span class="menu-text">KPMS</span>
            </a>
            <a href="./plan">
                <img class="icono" src="./img/seguimiento.svg" title="Seguimiento" alt="icono Seguimiento">
                <span class="menu-text">Seguimiento</span>
            </a>
            <a href="./users">
                <img class="icono" src="./img/usuarios.svg" title="Usuarios" alt="icono Usuarios">
                <span class="menu-text">Usuarios</span>
            </a>
            <a href="./eas">
                <img class="icono" src="./img/eas.svg" title="Módulo EAS" alt="icono Módulo EAS">
                <span class="menu-text">Módulo EAS</span>
            </a>
            <a href="./solicitudes">
                <img class="icono" src="./img/formulario.svg" title="Apartado solicitudes" alt="icono Apartado solicitudes">
                <span class="menu-text">Formularios</span>
            </a>
            <a href="./continuidad">
                <img class="icono" src="./img/Recorrido.svg" title="Módulo continuidad" alt="icono Apartado continuidad">
                <span class="menu-text">Continuidad</span>
            </a>
            <a href="./repositorioVulns">
                <img class="icono" src="./img/repoVulns.svg" title="Módulo Repositorio Vulns" alt="icono Apartado Repositorio Vulns">
                <span class="menu-text">Repositorio Vulns</span>
            </a>
        </div>
    <?php } ?>
<?php
}

function traducirBia($array)
{
    $arrayCriticidad = array(
        0 => "Leve",
        1 => "Bajo",
        2 => "Moderado",
        3 => "Alto",
        4 => "Critico"
    );

    $valorMasAlto = -1;
    foreach ($array as $key => $value) {
        if ($array[$key] > $valorMasAlto) {
            $valorMasAlto = $value;
        }
    }
    return $arrayCriticidad[$valorMasAlto];
}

function getheader($titulo, $pro = true, $recaptcha = false)
{
?>
    <meta charset="utf-8" />
    <title><?php echo $titulo ?></title>
    <link rel="icon" href="./img/favicon.jpg" />
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/jquery.bootgrid.min.css">
    <script src="./js/vendor/jquery-3.7.1.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/jquery-3.7.1.min.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/vendor/popper.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/popper.min.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/vendor/bootstrap.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/bootstrap.min.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/modules/modalModule.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/modules/modalModule.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/api/api.js" type="module" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/api/api.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/vendor/es6-promise.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/es6-promise.min.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/vendor/jquery.bootgrid.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/jquery.bootgrid.min.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/vendor/xlsx.mini.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/xlsx.mini.min.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/vendor/jquery.bootgrid.fa.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/jquery.bootgrid.fa.min.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/vendor/jquery.bootgrid.setSelectedRows.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/jquery.bootgrid.setSelectedRows.js", true)) ?>" crossorigin="anonymous"></script>
    <script src="./js/vendor/jquery.bootgrid.resizeColumns.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/vendor/jquery.bootgrid.resizeColumns.js", true)) ?>" crossorigin="anonymous"></script>
    <?php
    if ($recaptcha) {
    ?>
        <script src="https://www.google.com/recaptcha/api.js?trustedtypes=true&render=6LcM73oaAAAAAODm1L60_a95HULhaEdRZFSZY7XF" type="text/javascript"></script>
        <script src="https://www.google.com/recaptcha/api.js?onload=showRecaptchaV2&render=explicit" async defer></script>
    <?php

    }

    if ($pro) {
    ?>
        <link rel="stylesheet" href="./css/main.min.css" />
        <script src="./js/main.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/main.min.js", true)) ?>" crossorigin="anonymous"></script>
        <script src="./js/misc.min.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/misc.min.js", true)) ?>" crossorigin="anonymous"></script>
    <?php
    } else {
    ?>
        <link rel="stylesheet" href="./css/main.css" />
        <script src="./js/main.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/main.js", true)) ?>" crossorigin="anonymous"></script>
        <script src="./js/modules/classLibrary.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/modules/classLibrary.js", true)) ?>" crossorigin="anonymous"></script>
        <script src="./js/misc.js" type="text/javascript" integrity="<?php echo "sha384-" . base64_encode(hash_file('sha384', "./js/misc.js", true)) ?>" crossorigin="anonymous"></script>
    <?php
    }
}

function getfooter()
{
    ?>
    <footer class="footer mt-auto py-3">
        <div class="row align-items-center justify-content-sm-center">
            <div class="col-auto">
                <a href="./app"><img class="footer-logo" title="Telefonica" alt='logotipo de Telefonica' src="./img/telefonica_new_blue.svg" /></a>
            </div>
            <div class="col-auto text-center">
                <a href="./terminosdeuso">Términos de uso</a>
            </div>
            <div class="col-auto text-center">
                <a href="./politicadeprivacidad">Políticas de Privacidad</a>
            </div>
            <div class="col-auto text-end">
                <div class="title">{versionmaster}</div>
            </div>
        </div>
    </footer>

<?php
}

function insertLoadingDinamic($title, $name, $espaciado = 'mt-3', $oculta = false)
{
    $mshide = '';
    if ($oculta) {
        $mshide = 'mshide';
    }
    echo "
    <div class='row justify-content-end  " . $espaciado . " " . $mshide . " DIV" . $name . "' >
      <div class='col-md-6 d-flex align-items-center'>
          <label class='minititle'>$title</label>
      </div>
      <div class='col-md-6 " . $name . " d-flex justify-content-end align-items-center'>
          <div class='spinner-animation' id='" . $name . "'>
              <svg class='spinner-a' height='60' role='img' viewBox='0 0 66 66' width='60'>
                  <circle class='spinner-circle' cx='33' cy='33' fill='none' r='30' role='presentation' stroke-width='3' stroke='#0d6efd'></circle>
              </svg>
          </div>
      </div>
    </div>
    ";
}
