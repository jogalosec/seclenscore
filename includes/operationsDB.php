<?php

const TEST = 'test';

class DbOperationsException extends \Exception {}

class DbOperations
{
    const NOMBRE = 'nombre';
    const PUNTO_NOMBRE = ':nombre';
    const DOMINIO = 'dominio';
    const NO_PARAM = 'NO_PARAM';
    const COMA_FECHA = ',fecha';
    const PUNTO_FECHA = ':fecha';
    const OCTOPUS_SRV = 'octopus_serv';
    const UNDEFINED = 'undefined';
    const NORMATIVAS = 'normativas';
    const TOTAL = 'total';
    const NOT_PROD = 'NOPROPIETARIO';
    const PUNTO_TIPO = ':tipo';

    public $con;

    public function __construct($dbname = null)
    {
        require_once dirname(__FILE__) . '/DB.php';
        if (!isset($dbname) || $dbname == null) {
            $db = new DB("octopus_new");
        } else {
            $db = new DB($dbname);
        }
        $this->con = $db->getPDO();
    }

    public function getPreguntasBIA()
    {
        $sql = "SELECT id,duda,respuestas,ambito FROM bia where enabled = 1";
        $this->__construct("octopus_new");
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntasBIAespecificas()
    {
        // Filtrar solo las preguntas 8, 9 y 10
        $sql = "SELECT id, duda, respuestas, ambito FROM bia WHERE id IN (8, 9, 10)";
        $this->__construct("octopus_new");
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getVulnID($idVul)
    {
        $sql = "SELECT * FROM vulnerabilidades WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $idVul);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getMetodologia($idVul)
    {
        $sql = "SELECT nombre, nombre_corto, descripcion, pruebas FROM metodologias where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($idVul));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getDefaultNormative()
    {
        $sql = "SELECT id,nombre FROM normativas WHERE predeterminado = 1";
        $this->__construct("octopus_new");
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getNormativas()
    {
        $sql = "SELECT id,nombre FROM normativas order by nombre asc";
        $this->__construct("octopus_new");
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getNormativaByNombre($nombre)
    {
        $sql = "SELECT id,nombre FROM normativas where nombre = :nombre";
        $this->__construct("octopus_new");
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('nombre', $this->sanetize($nombre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getUsfByPreguntas($id, $normativas)
    {
        $sql = "SELECT usf.cod as USF FROM preguntas
        INNER JOIN marco ON marco.id_preguntas = preguntas.id
        INNER JOIN ctrls on ctrls.id = marco.id_ctrls
        INNER JOIN usf ON usf.id = marco.id_usf
        WHERE preguntas.id = :id ";
        if (isset($normativas[0])) {
            $sql .= "AND (";
            $normas = $this->getNormativas();
            $count = count($normativas);
            foreach ($normativas as $key => $norm) {
                $index = array_search($norm, array_column($normas, 'nombre'));
                $sql .= "ctrls.id_normativa = " . $normas[$index]["id"];
                if ($count > $key + 1) {
                    $sql .= " OR ";
                }
            }
            $sql .= ")";
        }
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getUsfBySaw($idSaw)
    {
        $sql = "SELECT s.usf_id
        FROM saw_has_usf s
        INNER JOIN saw sa ON sa.id = s.saw_id
        INNER JOIN usf ON s.usf_id = usf.id
        WHERE s.saw_id = :idSaw";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('idSaw', $this->sanetize($idSaw));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getUsfById($idUsf)
    {
        $sql = "SELECT u.id, u.cod
        FROM usf u
        INNER JOIN saw_has_usf s ON s.usf_id = u.id
        WHERE u.id = :idUsf";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('idUsf', $this->sanetize($idUsf));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function get3psByID($id)
    {
        $sql = "SELECT 3ps.cod as 3ps FROM 3ps WHERE 3ps.id = :id ";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntasById($id)
    {
        $this->__construct('octopus_new');
        $sql = "SELECT id,duda FROM preguntas WHERE id = :id ";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntasByActivosProyecto($activos, $proyecto)
    {
        $activosString = implode(',', array_column($activos, "tipo_id"));
        $sql = "SELECT marco.*
        FROM activos
        INNER JOIN activo_has_amenaza ON activos.id = activo_has_amenaza.activo_id
        INNER JOIN amenazas ON amenazas.id = activo_has_amenaza.amenaza_id
        INNER JOIN mitigaciones ON mitigaciones.id_amenazas = amenazas.id
        INNER JOIN usf ON usf.id = mitigaciones.id_usf
        INNER JOIN marco ON marco.id_usf = usf.id
        WHERE usf.id_proyecto IN ($proyecto) AND activos.id IN ($activosString);";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntasByCtrls($ctrls, $group = true)
    {
        if (!empty($ctrls) && is_array($ctrls)) {
            $all_keys = array_column($ctrls, "ctrls");
            $all_keys = array_reduce($all_keys, function ($carry, $item) {
                return array_merge($carry, array_keys($item));
            }, array());
            $unique_keys = array_unique($all_keys);

            // Creamos una cadena de marcadores de posición para los controles
            $placeholders = implode(', ', array_fill(0, count($unique_keys), '?'));

            $sql = "FROM preguntas
            INNER JOIN marco ON marco.id_preguntas = preguntas.id
            INNER JOIN ctrls ON ctrls.id = marco.id_ctrls
            INNER JOIN usf ON usf.id = marco.id_usf
            WHERE marco.id_ctrls IN ($placeholders)";

            if ($group) {
                $sqlselect = "SELECT preguntas.id, MAX(usf.dominio) as dominio, MAX(preguntas.duda) as duda, MAX(usf.cod) as cod, MAX(ctrls.cod) as cod_ctrls, MAX(ctrls.dominio) as dominio_ctrls ";
                $sql = $sqlselect . $sql . " GROUP BY preguntas.id;";
            } else {
                $sqlselect = "SELECT preguntas.id, usf.dominio as dominio, preguntas.duda as duda, usf.cod as cod, ctrls.cod as cod_ctrls, ctrls.dominio as dominio_ctrls ";
                $sql = $sqlselect . $sql . ";";
            }

            $stmt = $this->con->prepare($sql);

            // Pasamos los valores como un array al método execute
            $stmt->execute(array_values($unique_keys));

            return $this->respuesta($stmt);
        } else {
            return array();
        }
    }

    public function getFamiliaActivo($id)
    {
        $sql = 'SELECT familia from activos where id = :id';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtainAllTypeActivos()
    {
        $sql = 'SELECT * from activos order by nombre asc';
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getTodo($id)
    {
        $sql = 'SELECT preguntas.id,amenazas.nombre as amenaza,usf.cod as usf FROM activos
            INNER JOIN activo_has_amenaza ON activo_has_amenaza.activo_id = activos.id
            INNER JOIN amenazas ON amenazas.id = activo_has_amenaza.amenaza_id
            INNER JOIN mitigaciones ON mitigaciones.id_amenazas = amenazas.id
            INNER JOIN usf ON usf.id = mitigaciones.id_usf
            INNER JOIN marco ON marco.id_usf = usf.id
            INNER JOIN ctrls ON ctrls.id = marco.id_ctrls
            INNER JOIN preguntas ON preguntas.id = marco.id_preguntas
            WHERE activos.id = :id';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    /**
     * Retrieves the ID of a 3PS based on its code.
     *
     * @param int $cod The code of the 3PS.
     * @return array|null Returns an array containing the ID of the 3PS if the code is set and valid, otherwise returns null.
     */
    public function getId3PSbyCod($cod)
    {
        $this->__construct('octopus_new');
        $sql = "SELECT id FROM 3ps WHERE cod = :cod;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('cod', $this->sanetize($cod));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    /**
     * Retrieves USF and project information based on a given question ID.
     * @category USF/PAC
     * @param int $id The ID of the question.
     * @return array|null Returns an array containing project and USF information if the ID is set and valid, otherwise returns null.
     */
    public function getUsfPacByPregunta($id)
    {
        $this->__construct("octopus_new");
        if (isset($id)) {
            $sql = "SELECT proyectos.cod AS proyecto, usf.cod AS USF FROM preguntas
                INNER JOIN marco ON marco.id_preguntas = preguntas.id
                INNER JOIN usf ON marco.id_usf = usf.id
                LEFT JOIN proyectos ON proyectos.id = usf.id_proyecto
                WHERE preguntas.id = :id GROUP BY proyectos.cod, usf.cod;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue('id', $this->sanetize($id));
            $stmt->execute();
            return $this->respuesta($stmt);
        }
    }

    public function getCtrlsByUsf($id, $normativa)
    {
        if (isset($id, $normativa)) {
            $sql = "SELECT ctrls.id AS ctrls_id FROM ctrls
                INNER JOIN marco ON marco.id_ctrls = ctrls.id
                INNER JOIN normativas AS N ON N.id = ctrls.id_normativa
                WHERE marco.id_usf = :id AND N.nombre = :normativa";
            if ($normativa === 'pbs') {
                $sql .= " AND ctrls.nivel = 1";
            }
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue('id', $id, PDO::PARAM_INT);
            $stmt->bindValue('normativa', $normativa, PDO::PARAM_STR);
            $stmt->execute();
            return $this->respuesta($stmt);
        }
    }

    public function getUsfByAmenaza($id)
    {
        if (isset($id)) {
            $sql = "SELECT usf.id as usf_id FROM mitigaciones
            INNER JOIN amenazas ON amenazas.id = mitigaciones.id_amenazas
            INNER JOIN usf ON usf.id = mitigaciones.id_usf
            WHERE amenazas.id = :id";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(':id', $this->sanetize($id));
            $stmt->execute();
            return $this->respuesta($stmt);
        }
    }

    public function getCountActivos()
    {
        $sql = "SELECT activo_id as tipo, count(*) as num FROM `activos` GROUP BY activo_id";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getCodUsfByAmenaza($id)
    {
        if (isset($id)) {
            $sql = "SELECT usf.cod as usf_id,usf.dominio,usf.descripcion,usf.id_proyecto,usf.tipo FROM mitigaciones
            INNER JOIN amenazas ON amenazas.id = mitigaciones.id_amenazas
            INNER JOIN usf ON usf.id = mitigaciones.id_usf
            WHERE amenazas.id = :id";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(':id', $this->sanetize($id));
            $stmt->execute();
            return $this->respuesta($stmt);
        }
    }

    public function getCod3psByAmenaza($id)
    {
        if (isset($id)) {
            $sql = "SELECT 3ps.id as 3ps_id,3ps.cod as 3ps_cod,3ps.dominio,3ps.descripcion,3ps.id_proyecto FROM mitigaciones_3ps
            INNER JOIN amenazas ON amenazas.id = mitigaciones_3ps.id_amenazas
            INNER JOIN 3ps ON 3ps.id = mitigaciones_3ps.id_3ps
            WHERE amenazas.id = :id";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(':id', $this->sanetize($id));
            $stmt->execute();
            return $this->respuesta($stmt);
        }
    }

    public function newParentesco($activo, $padre)
    {
        $sql = "INSERT INTO parentesco (activo_id,padre_id) VALUES (:activo,:parentesco)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":activo", $this->sanetize($activo));
        $stmt->bindValue(":parentesco", $this->sanetize($padre));
        $stmt->execute();
    }

    public function getDependenciasActivo($id)
    {
        $sql = "SELECT id, activo_id, padre_id FROM parentesco WHERE activo_id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getChild($id)
    {
        if (!isset($id)) {
            return self::NO_PARAM;
        }
        $sql = "SELECT S.nombre, S.activo_id as tipo, P.activo_id as id, P.padre_id as padre, archivado, expuesto, critico
        FROM activos as S
        INNER JOIN parentesco as P ON S.id = P.activo_id
        WHERE P.padre_id = :id;";
        $this->__construct("octopus_serv");
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getOsaEvalByRevision($id)
    {
        $sql = "SELECT * FROM revisiones_has_osa WHERE revision_id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function saveEvalOsa($data)
    {
        $this->con->beginTransaction();
        try {
            $columns = implode(", ", array_map(function ($col) {
                return "`$col`";
            }, array_keys($data)));
            $placeholders = ":" . implode(", :", array_map(function ($col) {
                return str_replace('-', '_', $col);
            }, array_keys($data)));
            $updateColumns = implode(", ", array_map(function ($col) {
                str_replace('-', '_', $col);
                return "`$col` = VALUES(`$col`)";
            }, array_keys($data)));
            $sql = "INSERT INTO revisiones_has_osa ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateColumns";
            $stmt = $this->con->prepare($sql);
            foreach ($data as $key => $value) {
                $sanitizedKey = str_replace('-', '_', $key);
                $stmt->bindValue(":$sanitizedKey", $this->sanetize($value));
            }
            $stmt->execute();
            $this->con->commit();
        } catch (PDOException $e) {
            $this->con->rollBack();
            throw new DbOperationsException("Error al guardar la evaluación OSA: " . $e->getCode(), 0, $e);
        }
    }

    public function getOsaByType($type)
    {
        $sql = "SELECT id,cod,name,description,type,ciso_value,possible_values,saw_id FROM osa WHERE type = :tipo";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_TIPO, $this->sanetize($type));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getBiaExpuestoActivo($id)
    {
        $sql = "SELECT * FROM evaluaciones WHERE meta_key = 'bia' AND meta_value LIKE '%\"41\":\"0\"%' AND activo_id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getBiaExpuesto($tipo = 1)
    {
        if ($tipo == 1) {
            $sql = "SELECT * FROM evaluaciones WHERE meta_key = 'bia' AND meta_value LIKE '%\"41\":\"0\"%'";
        } else {
            $sql = "SELECT * FROM evaluaciones WHERE meta_key = 'bia' AND meta_value NOT LIKE '%\"41\":\"0\"%'";
        }
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getAllBia()
    {
        $sql = "SELECT id,activo_id,meta_value,fecha FROM evaluaciones WHERE meta_key = 'bia' ORDER BY fecha DESC;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getBia($id)
    {
        if (isset($id)) {
            $sql = "SELECT evaluaciones.id,activo_id,meta_value,fecha,users.email FROM evaluaciones
            LEFT JOIN octopus_users.users on octopus_users.users.id = evaluaciones.user_id
            WHERE activo_id = :id AND meta_key = 'bia' ORDER BY fecha DESC LIMIT 1;";
        } else {
            return self::NO_PARAM;
        }
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getFechasRecientes($id)
    {
        if (isset($id)) {
            $sql = "SELECT id,evaluacion_id,fecha FROM evaluaciones_versiones WHERE evaluacion_id = :id ORDER BY fecha DESC;";
        } else {
            return self::NO_PARAM;
        }
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getFechaEvaluacionesById($id, $all = null)
    {
        if (isset($id)) {
            $sql = "SELECT id, tipo_tabla, fecha
            FROM (
                SELECT id, 'evaluaciones' AS tipo_tabla, fecha
                FROM evaluaciones
                WHERE meta_key = 'preguntas' AND id = :id
                UNION ALL
                SELECT ev.id, 'evaluaciones_versiones' AS tipo_tabla, ev.fecha
                FROM evaluaciones e
                LEFT JOIN evaluaciones_versiones ev ON e.id = ev.evaluacion_id
                WHERE e.meta_key = 'preguntas' AND e.id = :id
            ) AS merged_data
            ORDER BY fecha DESC;";
            if ($all == null) {
                $sql = "SELECT * from evaluaciones
                WHERE meta_key = 'preguntas' AND id = :id
                ORDER BY fecha DESC;";
            }
        } else {
            return self::NO_PARAM;
        }

        $id = $this->sanetize($id);
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return $this->respuesta($stmt);
    }

    public function getFechaEvaluaciones($id, $all = null)
    {
        if (isset($id)) {
            $sql = "SELECT id, tipo_tabla, fecha
            FROM (
                SELECT id, 'evaluaciones' AS tipo_tabla, fecha
                FROM evaluaciones
                WHERE meta_key = 'preguntas' AND activo_id = :idactivo
                UNION ALL
                SELECT ev.id, 'evaluaciones_versiones' AS tipo_tabla, ev.fecha
                FROM evaluaciones e
                LEFT JOIN evaluaciones_versiones ev ON e.id = ev.evaluacion_id
                WHERE e.meta_key = 'preguntas' AND e.activo_id = :idactivo
            ) AS merged_data
            ORDER BY fecha DESC;";
            if ($all == null) {
                $sql = "SELECT * from evaluaciones
                WHERE meta_key = 'preguntas' AND activo_id = :idactivo
                ORDER BY fecha DESC;";
            }
        } else {
            return self::NO_PARAM;
        }

        $id = $this->sanetize($id);
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idactivo', $id);
        $stmt->execute();

        return $this->respuesta($stmt);
    }

    public function getPreguntasEvaluacionByFecha($fecha)
    {
        if (!isset($fecha) || empty($fecha)) {
            return self::NO_PARAM;
        }
        $sql = "SELECT meta_value as preguntas FROM evaluaciones WHERE id = :fecha AND meta_key = 'preguntas';";
        $this->__construct('octopus_serv');
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_FECHA, $this->sanetize($fecha));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivosEvalPentest()
    {
        $sql = "SELECT evaluaciones.activo_id, MAX(evaluaciones.fecha) AS fecha
                FROM evaluaciones_versiones
                JOIN evaluaciones ON evaluaciones_versiones.evaluacion_id = evaluaciones.id
                WHERE evaluaciones_versiones.nombre = 'Pentest cerrado'
                GROUP BY evaluaciones.activo_id;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntasVersionByFecha($id)
    {
        if (isset($id)) {
            $sql = "SELECT meta_value as preguntas FROM evaluaciones_versiones WHERE id = :id;";
        } else {
            return self::NO_PARAM;
        }
        $this->__construct('octopus_serv');
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }


    public function getIdActivoEvaluacionByFecha($fecha)
    {
        if (isset($fecha)) {
            $sql = "SELECT activo_id as id FROM evaluaciones WHERE id = :fecha AND meta_key = 'preguntas';";
        } else {
            return self::NO_PARAM;
        }
        $this->__construct('octopus_serv');
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_FECHA, $this->sanetize($fecha));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getComentariosEvaluacionById($id)
    {
        if (isset($id)) {
            $sql = "SELECT meta_value as comentarios FROM evaluaciones WHERE id = :id AND meta_key = 'comentarios';";
        } else {
            return self::NO_PARAM;
        }
        $this->__construct('octopus_serv');
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function savekpms($id_usuario, $datos)
    {
        if (!isset($id_usuario, $datos) || empty($datos)) {
            return self::NO_PARAM;
        }
        $tipo = null;

        if (isset($datos["KPM04"])) {
            $tipo = 'metricas';
        } elseif (isset($datos["KPM59A"])) {
            $tipo = 'csirt';
            $datos["fecha"] = date("Y-m-d H:i:s");
        } else {
            $tipo = 'madurez';
        }

        $datos = array_map(function ($value) {
            return $value === "" ? null : $value;
        }, $datos);

        $this->__construct("octopus_kpms");

        try {
            $this->con->beginTransaction();

            $param = implode(", ", array_keys($datos));
            $values = implode(", ", array_fill(0, count($datos), '?'));

            $stmt = $this->con->prepare("INSERT INTO $tipo (usuario_id, $param) VALUES (?, $values)");
            $params = array_merge([$id_usuario], array_values($datos));

            foreach ($params as $key => $value) {
                $stmt->bindValue($key + 1, $value === null ? null : $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }

            $stmt->execute();

            $this->con->commit();
        } catch (\PDOException $e) {
            $this->con->rollBack();
            throw $e;
        }
    }

    public function setMetaValue($id, $datos, $meta_key, $user = null)
    {
        if (isset($id, $datos, $meta_key)) {
            $sql  = "INSERT INTO evaluaciones (activo_id,meta_key,meta_value,user_id) VALUES (:id,:meta_key,:datos,:user);";
        } else {
            return self::NO_PARAM;
        }
        $this->__construct("octopus_serv");
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->bindValue(':datos', json_encode($datos));
        $stmt->bindValue(':meta_key', $this->sanetize($meta_key));
        if ($user) {
            $stmt->bindValue(':user', $this->sanetize($user));
        } else {
            $stmt->bindValue(':user', null, PDO::PARAM_NULL);
        }
        $stmt->execute();
    }

    public function clearBIA($id)
    {
        if (isset($id)) {
            $sql = 'DELETE FROM evaluaciones where activo_id = :id AND meta_key = "bia"';
        } else {
            return self::NO_PARAM;
        }
        $this->__construct("octopus_serv");
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
    }

    public function getEvalByActivoId($id, $tipo = null)
    {
        $sql = "SELECT id,meta_key,meta_value,fecha FROM evaluaciones where activo_id = :id";
        if ($tipo) {
            $sql .= " AND meta_key = :tipo";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue("tipo", $this->sanetize($tipo));
        } else {
            $stmt = $this->con->prepare($sql);
        }
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getEvalById($id)
    {
        $sql = "SELECT id,meta_key,meta_value,fecha FROM evaluaciones where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getVersionById($id)
    {
        $sql = "SELECT id,meta_key,meta_value,fecha,evaluacion_id FROM evaluaciones_versiones where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getVersionByEvalId($id)
    {
        $sql = "SELECT id,meta_key,meta_value,fecha,evaluacion_id FROM evaluaciones_versiones where evaluacion_id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getNumVersionEval($id)
    {
        $sql = "SELECT count(*) as version FROM evaluaciones_versiones where evaluacion_id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getVersionesEvaluacion($id)
    {
        $sql = "SELECT id,version,nombre,fecha FROM evaluaciones_versiones where evaluacion_id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function insertVersionEvaluacion($evaluacion_id, $version, $nombre, $meta_key, $meta_value)
    {
        $sql = "INSERT evaluaciones_versiones (evaluacion_id,version,nombre,meta_key,meta_value) VALUES (:id,:version,:nombre,:meta_key,:meta_value)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($evaluacion_id));
        $stmt->bindValue(":version", $this->sanetize($version));
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->bindValue(":meta_key", $this->sanetize($meta_key));
        $stmt->bindValue(":meta_value", $meta_value);
        $stmt->execute();
        return $this->respuesta($stmt);
    }


    public function editEval($id_eval, $id_version, $datos, $nombre = 'Edición')
    {
        $eval = $this->getEvalById($id_eval);
        if ($id_version !== null) {
            $eval = $this->getVersionById($id_version);
        }

        if (isset($eval[0]["evaluacion_id"])) {
            $id_eval = $eval[0]["evaluacion_id"];
        }

        $version = $this->getNumVersionEval($id_eval);
        $sql = "INSERT evaluaciones_versiones (evaluacion_id,version,nombre,meta_key,meta_value) VALUES (:id,:version,:nombre,:meta_key,:meta_value)";


        $this->con->beginTransaction();

        try {
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id_eval));
            $stmt->bindValue(":version", $this->sanetize($version[0]["version"] + 1));
            $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
            $stmt->bindValue(":meta_key", $this->sanetize($eval[0]["meta_key"]));
            $stmt->bindValue(":meta_value", json_encode($datos));
            $stmt->execute();

            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollBack();
        }
    }

    public function findPac($pac_id, $proyect_id)
    {
        $sql = "SELECT id FROM `seguimientopac` where activo_id = :proyect_id AND proyecto_id = :pac_id";
        $this->__construct("octopus_serv");
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':proyect_id', $this->sanetize($proyect_id));
        $stmt->bindValue(':pac_id', $this->sanetize($pac_id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getAmenazasByActivoId($id)
    {
        $sql = "SELECT A.id,A.cod,A.tipo,A.nombre,A.padre,A.probabilidad,A.confidencialidad,A.integridad,A.disponibilidad
            FROM activos as CA
            INNER JOIN activo_has_amenaza as CAA
            ON CAA.activo_id = CA.ID
            INNER JOIN amenazas as A
            ON A.id = CAA.amenaza_id WHERE CA.ID = :id;";
        $this->__construct("octopus_new");
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function setToken($hash, $created, $expired, $iv, $phrase, $user, $userdata)
    {
        $this->__construct("octopus_users");
        $sql = 'INSERT INTO jwt_sessions (hash,created,expired,iv,phrase,user_id,userdata) VALUES (:hash,:created,:expired,:iv,:phrase,:user,:userdata)';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':hash', $this->sanetize($hash));
        $stmt->bindValue(':created', $this->sanetize($created));
        $stmt->bindValue(':expired', $this->sanetize($expired));
        $stmt->bindValue(':iv', $this->sanetize($iv));
        $stmt->bindValue(':phrase', $this->sanetize($phrase));
        $stmt->bindValue(':user', $this->sanetize($user));
        $stmt->bindValue(':userdata', $this->sanetize($userdata));
        $stmt->execute();
    }

    public function getTokenbyJti($jti)
    {
        $this->__construct("octopus_users");
        $sql = "SELECT id,hash,phrase,iv,expired,user_id,userdata,used from jwt_sessions where hash = :jti;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':jti', $this->sanetize($jti));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function updateToken($jti, $data, $suma)
    {
        $usos = $this->getTokenbyJti($jti);
        if (isset($usos[0])) {
            $usos = $suma ? $usos[0]["used"] + 1 : $usos[0]["used"];
        } else {
            $usos = 0;
        }
        $sql = "UPDATE jwt_sessions SET used = :used, expired = :exp where hash = :jti;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':jti', $this->sanetize($jti));
        $stmt->bindValue(':used', $usos);
        $stmt->bindValue(':exp', $this->sanetize($data["exp"]));
        $stmt->execute();
    }

    public function getSaveEval($id)
    {
        $sql = "SELECT meta_value from evaluaciones where meta_key = 'save_eval' AND activo_id = :id ORDER BY fecha DESC LIMIT 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getStandarDomainByNormativa($normativa)
    {
        $sql = "SELECT standard_domains from normativas where nombre like :normativa;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':normativa', $this->sanetize($normativa));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getEvaluacion($normativas, $activos, $group = true)
    {
        if (empty($activos) || empty($normativas)) {
            return self::NO_PARAM;
        }
        if (!is_array($normativas)) {
            $normativas = [self::NORMATIVAS => explode(";", $normativas)];
        }
        $amenazas = array();
        foreach ($activos as $activo) {
            if (!isset($activo["amenazas"])) {
                if (isset($activo["tipo_id"])) {
                    $amenazas = array_merge($amenazas, $this->getAmenazasByActivoId($activo["tipo_id"]));
                } else {
                    $amenazas = array_merge($amenazas, $this->getAmenazasByActivoId($activo["tipo"]));
                }
            } else {
                $amenazas = array_merge($amenazas, $activo["amenazas"]);
            }
        }
        $amenazas = array_column($amenazas, 'id');
        $amenazas = array_count_values($amenazas);
        $usf = [];
        foreach ($amenazas as $amenaza => $veces) {
            $codOrUsf = ($normativas[self::NORMATIVAS][0] == "3ps")
                ? $this->getCod3psByAmenaza($amenaza)
                : $this->getUsfByAmenaza($amenaza);

            for ($i = 0; $i < $veces; $i++) {
                $usf = array_merge($usf, $codOrUsf);
            }
        }
        $idCol = ($normativas[self::NORMATIVAS][0] == "3ps")
            ? '3ps_id'
            : 'usf_id';
        $usf = array_column($usf, $idCol);
        $usf = array_count_values($usf);

        foreach ($usf as $item => $veces) {
            $ctrls = [];
            foreach ($normativas[self::NORMATIVAS] as $norm) {
                $ctrls = array_merge($ctrls, array_column($this->getCtrlsByUsf($item, $norm), 'ctrls_id'));
            }
            if (!empty($ctrls)) {
                $ctrls = array_count_values($ctrls);
                $usf[$item] = ['usf' => $veces, 'ctrls' => $ctrls];
            } else {
                unset($usf[$item]);
            }
        }
        return $this->getPreguntasByCtrls($usf, $group);
    }

    /**
     * Retrieves a comparison of normative data.
     * @category ECR
     * @param string|array $normativa The normative(s) to compare against. If 'all', compares against all available normatives.
     * @param array $activos The active items for comparison.
     * @param array $preguntas The questions to compare against.
     * @return array The comparison result of normatives against the provided questions.
     */
    public function getCompararNormativa($normativa, $activos, $preguntas)
    {
        if (!isset($normativa, $activos, $preguntas)) {
            return self::NO_PARAM;
        }

        sort($activos);
        uasort($preguntas, 'ordenarKeyPreguntas');

        $hash_activos = hash('sha256', json_encode($activos));
        $hash_preguntas = hash('sha256', json_encode($preguntas));

        // Buscar en la tabla
        $cached_result = getEvaluacionCache($hash_activos, $hash_preguntas);

        if ($cached_result) {
            $activos = $cached_result['activos'];
        }

        $respuesta = [];
        $normativas = [];

        if ($normativa === 'all') {
            $normativas = $this->getNormativas();
        } else {
            $normas = explode(",", $normativa);
            $normativas = array_map([$this, 'getNormativaByNombre'], $normas);
            $normativas = array_reduce($normativas, 'array_merge', []);
        }

        foreach ($normativas as $norm) {
            $respuesta[$norm[NOMBRE]] = $this->getEvaluacion($norm[NOMBRE], $activos, false);
        }
        return $this->compararPreguntas($respuesta, $preguntas);
    }

    private function compararPreguntas($data, $preguntas)
    {
        uksort($data, "oderprioiso");
        $respuesta = [];

        foreach ($data as $key => $value) {
            $sctrlTotal = [];
            foreach ($value as $pregunta) {
                $indice = $this->buscarOAgregarPregunta($sctrlTotal, $pregunta, $key);

                if (isset($preguntas[$pregunta["id"]])) {
                    $sctrlTotal[$indice]["yes"] += $preguntas[$pregunta["id"]];
                    $sctrlTotal[$indice][self::TOTAL]++;
                } else {
                    $this->procesarPreguntaNoEncontrada($data, $preguntas, $sctrlTotal, $pregunta, $key, $indice);
                }

                $totalEvaluado = $sctrlTotal[$indice][self::TOTAL] - $sctrlTotal[$indice]["NE"];
                $sctrlTotal[$indice]["mne"] = $sctrlTotal[$indice]["NE"] / $sctrlTotal[$indice][self::TOTAL] * 100;
                $sctrlTotal[$indice]["mct"] = $totalEvaluado > 0 ? $sctrlTotal[$indice]["yes"] / $totalEvaluado * 100 : 0;
            }
            $respuesta[$key] = $sctrlTotal;
        }

        foreach ($respuesta as &$sctrlTotal) {
            sort($sctrlTotal);
        }

        return $respuesta;
    }

    private function buscarOAgregarPregunta(&$sctrlTotal, $pregunta, $key)
    {
        $sctrlIndex = array_column($sctrlTotal, "cod");
        $indice = array_search($pregunta["cod"], $sctrlIndex);

        $standarDomain = $this->getStandarDomainByNormativa($key);

        if ($indice === false) {
            if (isset($standarDomain[0]["standard_domains"]) && $standarDomain[0]["standard_domains"] == 1) {
                $sctrlTotal[] = [
                    self::DOMINIO => $pregunta["dominio_ctrls"],
                    "cod" => $pregunta["cod"],
                    self::TOTAL => 0,
                    "yes" => 0,
                    "NE" => 0
                ];
            } else {
                $sctrlTotal[] = [
                    self::DOMINIO => $pregunta[self::DOMINIO],
                    "cod" => $pregunta["cod"],
                    self::TOTAL => 0,
                    "yes" => 0,
                    "NE" => 0
                ];
            }
            if ($key == "NIS") {
                $sctrlTotal[] = [
                    self::DOMINIO => $pregunta["dominio_ctrls"],
                    "cod" => $pregunta["cod"],
                    self::TOTAL => 0,
                    "yes" => 0,
                    "NE" => 0
                ];
            }
            $indice = count($sctrlTotal) - 1;
        }

        return $indice;
    }

    private function procesarPreguntaNoEncontrada($data, &$preguntas, &$sctrlTotal, $pregunta, $key, $indice)
    {
        if ($key == "iso27001") {
            $conversionKey = "27002(2022)";
        } elseif ($key == "27002(2022)") {
            $conversionKey = "iso27001";
        } else {
            $conversionKey = false;
        }

        if ($conversionKey && isset($data[$conversionKey])) {
            $conversionIndex = array_keys(array_column($data[$conversionKey], "cod"), $pregunta["cod"]);
            if (isset($conversionIndex[0])) {
                foreach ($conversionIndex as $keysearch) {
                    $sctrlTotal[$indice][self::TOTAL]++;
                    $idpreguntanewiso = $data[$conversionKey][$keysearch]["id"];
                    if (isset($preguntas[$idpreguntanewiso])) {
                        $sctrlTotal[$indice]["yes"] += $preguntas[$idpreguntanewiso];
                        $preguntas[$pregunta["id"]] = $preguntas[$idpreguntanewiso];
                    } else {
                        $sctrlTotal[$indice]["NE"]++;
                    }
                }
            } else {
                $sctrlTotal[$indice][self::TOTAL]++;
                $sctrlTotal[$indice]["NE"]++;
            }
        } else {
            $sctrlTotal[$indice][self::TOTAL]++;
            $sctrlTotal[$indice]["NE"]++;
        }
    }

    public function respuesta($consulta)
    {
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sanetize($datos)
    {
        return htmlspecialchars($datos, ENT_QUOTES, 'UTF-8');
    }

    public function sanetizeJson($datos)
    {
        if (is_array($datos)) {
            return array_map([$this, 'sanetizeJson'], $datos);
        } elseif (is_string($datos)) {
            return htmlspecialchars($datos, ENT_QUOTES, 'UTF-8');
        } else {
            return $datos;
        }
    }
}

class Revision extends DbOperations
{
    public function assignPrismaAlertToReview($alertId, $revisionId, $policyId, $resourceId, $resourceName)
    {
        if (!isset($alertId, $revisionId, $policyId, $resourceId, $resourceName)) {
            throw new DbOperationsException("Missing parameters for assigning Prisma alert to review.");
        }
        $sqlInsert = 'INSERT IGNORE INTO revisiones_has_vuln
                    (id_revision, id_alert, id_policy, resource_id, resource_name)
                    VALUES (:id_revision, :id_alert, :id_policy, :resource_id, :resource_name);';
        $stmtInsert = $this->con->prepare($sqlInsert);
        $stmtInsert->bindValue(':id_revision', $this->sanetize($revisionId));
        $stmtInsert->bindValue(':id_alert', $this->sanetize($alertId));
        $stmtInsert->bindValue(':id_policy', $this->sanetize($policyId));
        $stmtInsert->bindValue(':resource_id', $this->sanetize($resourceId));
        $stmtInsert->bindValue(':resource_name', $this->sanetize($resourceName));
        $stmtInsert->execute();
    }

    public function unassignPrismaAlertToReview($alertId, $revisionId)
    {
        $sql = 'DELETE FROM revisiones_has_vuln WHERE id_alert = :id_alert AND id_revision = :id_revision';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_alert", $this->sanetize($alertId));
        $stmt->bindValue(":id_revision", $this->sanetize($revisionId));
        $stmt->execute();
    }

    public function getRevisionesIssues($id_issue)
    {
        $sql = "SELECT rhv.id, rhv.id_revision, rhv.id_alert, rhv.id_policy, rhv.id_issue, r.status FROM revisiones r
        INNER JOIN revisiones_has_vuln rhv ON r.id = rhv.id_revision
        WHERE rhv.id_issue = :id_issue";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_issue", $this->sanetize($id_issue));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtenerRevisionByActivo($idActivo)
    {
        $sql = "SELECT * FROM revisiones_has_activos where id_activo = :id_activo";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_activo", $this->sanetize($idActivo));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtainRevisionFromId($id)
    {
        $sql = "SELECT id, cloudId, nombre, descripcion, resp_revision, resp_proyecto, fecha_inicio, fecha_final, status, proyecto, tipo, user_id, informe_enviado FROM revisiones WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function insertarID($id_revision, $arrayID)
    {
        foreach ($arrayID as $hijo) {
            $sql = 'INSERT INTO revisiones_has_activos (id_activo, id_revision) VALUES (:id_activo, :id_revision)';
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id_activo", $this->sanetize($hijo));
            $stmt->bindValue(":id_revision", $this->sanetize($id_revision));
            $stmt->execute();
        }
    }

    public function getRevisiones()
    {
        $sql = "SELECT * FROM revisiones";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function eliminarRevision($id)
    {
        $sql = "SELECT * FROM revisiones_has_vuln WHERE id_revision = :id_revision";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_revision", $this->sanetize($id));
        $stmt->execute();
        $respuesta = $this->respuesta($stmt);

        if (!isset($respuesta[0])) {
            $sql = "DELETE FROM revisiones WHERE id = :id";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
            $stmt->execute();
            return $this->respuesta($stmt);
        } else {
            $sql = "DELETE FROM revisiones_has_vuln WHERE id_revision = :id_revision";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id_revision", $this->sanetize($id));
            $stmt->execute();

            $sql = "DELETE FROM revisiones WHERE id = :id";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
            $stmt->execute();
            return $this->respuesta($stmt);
        }
    }

    public function cambiarStatusRevision($id, $valor)
    {
        $fecha = date("Y-m-d");
        $sql = 'UPDATE revisiones SET status = :valor, fecha_final = :fecha where id = :id';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->bindValue(":valor", $this->sanetize($valor));
        $stmt->bindValue(self::PUNTO_FECHA, $this->sanetize($fecha));
        $stmt->execute();
    }
    public function insertActivosRevision($id_revision, $id_activo)
    {
        if (isset($id_revision[0]["id"])) {
            $id_revision = $id_revision[0]["id"];
        }
        $sql = 'INSERT INTO revisiones_has_activos (id_revision,id_activo) VALUES (:id_revision, :id_activo)';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_revision", $this->sanetize($id_revision));
        $stmt->bindValue(":id_activo", $this->sanetize($id_activo));
        $stmt->execute();
    }
    public function obtainRevisionIDByCloudId($cloudId)
    {
        $sql = "SELECT id FROM revisiones WHERE cloudId = :cloudId";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":cloudId", $this->sanetize($cloudId));
        $stmt->execute();
        return $this->respuesta($stmt);
    }
    public function obtainRevisionID($nombre)
    {
        $sql = "SELECT id FROM revisiones WHERE nombre = :nombre";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getOsaReview($idReview)
    {
        $sql = "SELECT id,revision_id,osa_id,valor,created,updated FROM revisiones_has_osa WHERE revision_id = :id_revision";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_revision", $this->sanetize($idReview));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtenerVulnsRevisionReportadas($idRevision)
    {
        $sql = "SELECT
                    rhv.id_alert,
                    rhv.id_policy,
                    rhv.resource_id,
                    rhv.resource_name,
                    rhv.reportada,
                    rhv.id_issue,
                    p.recommendation,
                    p.description,
                    p.name,
                    p.severity,
                    p.uuid AS policy_uuid,
                    p.sectionId
                FROM revisiones_has_vuln rhv
                LEFT JOIN octopus_new.policies p ON rhv.id_policy = p.id
                WHERE rhv.id_revision = :id_revision
                AND rhv.reportada = 1;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_revision", $this->sanetize($idRevision));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtenerVulnsRevision($idRevision)
    {
        $sql = "SELECT
                rhv.id_alert,
                rhv.id_policy,
                rhv.resource_id,
                rhv.resource_name,
                rhv.reportada,
                p.recommendation,
                p.description,
                p.name,
                p.severity,
                p.uuid AS policy_uuid,
                p.sectionId
            FROM revisiones_has_vuln rhv
            LEFT JOIN octopus_new.policies p ON rhv.id_policy = p.id
            WHERE rhv.id_revision = :id_revision;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_revision", $this->sanetize($idRevision));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getVulnInfoByAlertIds(array $alertIDs)
    {
        if (empty($alertIDs)) {
            return [];
        }
        // Sanitizar y preparar placeholders
        $placeholders = [];
        $params = [];
        foreach ($alertIDs as $idx => $id) {
            $ph = ':alert_id_' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = $this->sanetize($id);
        }
        $inClause = implode(',', $placeholders);
        $sql = "SELECT rhv.id_alert, p.name, rhv.resource_name, rhv.resource_id
                FROM revisiones_has_vuln rhv
                LEFT JOIN octopus_new.policies p ON rhv.id_policy = p.id
                WHERE rhv.id_alert IN ($inClause);";
        $stmt = $this->con->prepare($sql);
        foreach ($params as $ph => $val) {
            $stmt->bindValue($ph, $val);
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getSawAlert($alertID, $revisionID)
    {
        $sql = "SELECT s.* FROM octopus_new.saw s
        INNER JOIN octopus_new.policies p ON s.id = p.saw_id
        INNER JOIN octopus_serv.revisiones_has_vuln rv ON p.id = rv.id_policy
        WHERE rv.id_alert = :alert_id and id_revision = :revision_id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":alert_id", $this->sanetize($alertID));
        $stmt->bindValue(":revision_id", $this->sanetize($revisionID));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtenerActivosRevision($idRevision)
    {
        $sql = "SELECT * FROM revisiones_has_activos where id_revision = :id_revision";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_revision", $this->sanetize($idRevision));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function createRevision($parameters, $cloudId)
    {
        $db = new Revision(DB_SERV);
        $activo = $db->getActivoBySusId($cloudId);

        if (isset($activo[0])) {
            $sql = "INSERT INTO revisiones (cloudId, user_id, nombre, descripcion, resp_proyecto, fecha_inicio, fecha_final, status, proyecto)
                VALUES (:cloud_id, :user_id, :nombre, :descripcion, :resp_proyecto, :fecha_inicio, :fecha_final, 1, :proyecto);";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":cloud_id", $this->sanetize($cloudId));
            $stmt->bindValue(":user_id", $this->sanetize($parameters["user_id"]));
            $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($parameters["Nombre"]));
            $stmt->bindValue(":descripcion", $this->sanetize($parameters["Descripcion"]));
            $stmt->bindValue(":resp_proyecto", $this->sanetize($parameters["ResponsableProy"]));
            $stmt->bindValue(":fecha_inicio", $this->sanetize($parameters["Fecha_inicio"]));
            $stmt->bindValue(":fecha_final", null, PDO::PARAM_NULL);
            $stmt->bindValue(":proyecto", $this->sanetize($parameters["AreaServ"]));
            $stmt->execute();

            $lastInsertId = $this->con->lastInsertId();
            $sqlSelect = "SELECT * FROM revisiones WHERE id = :id";
            $stmtSelect = $this->con->prepare($sqlSelect);
            $stmtSelect->bindValue(":id", $lastInsertId, PDO::PARAM_INT);
            $stmtSelect->execute();

            return $stmtSelect->fetch(PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    }

    public function createRevisionWithoutActivos($parameters, $cloudId)
    {
        $sql = "INSERT INTO revisiones (cloudId, user_id, nombre, descripcion, resp_proyecto, fecha_inicio, fecha_final, status, proyecto)
            VALUES (:cloud_id, :user_id, :nombre, :descripcion, :resp_proyecto, :fecha_inicio, :fecha_final, 1, :proyecto);";
        $stmt = $this->con->prepare($sql);

        $cloudIdValue = ($cloudId !== null) ? $this->sanetize($cloudId) : '';
        $stmt->bindValue(":cloud_id", $cloudIdValue);
        $stmt->bindValue(":user_id", $this->sanetize($parameters["user_id"]));
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($parameters["Nombre"]));
        $stmt->bindValue(":descripcion", $this->sanetize($parameters["Descripcion"]));
        $stmt->bindValue(":resp_proyecto", $this->sanetize($parameters["ResponsableProy"]));
        $stmt->bindValue(":fecha_inicio", $this->sanetize($parameters["Fecha_inicio"]));
        $stmt->bindValue(":fecha_final", null, PDO::PARAM_NULL);
        $stmt->bindValue(":proyecto", $this->sanetize($parameters["AreaServ"]));
        $stmt->execute();

        $lastInsertId = $this->con->lastInsertId();
        $sqlSelect = "SELECT * FROM revisiones WHERE id = :id";
        $stmtSelect = $this->con->prepare($sqlSelect);
        $stmtSelect->bindValue(":id", $lastInsertId, PDO::PARAM_INT);
        $stmtSelect->execute();

        return $stmtSelect->fetch(PDO::FETCH_ASSOC);
    }

    public function getRevisions()
    {
        $sql = "SELECT revisiones.id, revisiones.nombre, revisiones.cloudId, revisiones.descripcion, revisiones.resp_proyecto, revisiones.fecha_inicio, revisiones.fecha_final, revisiones.status, revisiones.proyecto, GROUP_CONCAT(revisiones_has_osa.id) as osa, revisiones.user_id,
                (SELECT GROUP_CONCAT(s.suscription_name) FROM activos_has_suscripciones s WHERE s.suscription_id = revisiones.cloudId) as cloudName
                FROM revisiones
                LEFT JOIN revisiones_has_osa ON revisiones.id = revisiones_has_osa.revision_id
                GROUP BY revisiones.id";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function editDateStart($id, $date)
    {
        $sql = "UPDATE revisiones SET fecha_inicio = :date where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":date", $this->sanetize($date));
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function editDateEnd($id, $date)
    {
        $sql = "UPDATE revisiones SET fecha_final = :date where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":date", $this->sanetize($date));
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function setAlertaReportada($idAlerta)
    {
        $sql = "UPDATE revisiones_has_vuln SET reportada = 1 WHERE id_alert = :id_alert";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_alert", $this->sanetize($idAlerta));
        $stmt->execute();
    }

    public function checkAlertaReportada($idAlerta)
    {
        $sql = "SELECT reportada FROM revisiones_has_vuln WHERE id_alert = :id_alert";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_alert", $this->sanetize($idAlerta));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getRevisionStatus($revisionId)
    {
        $sql = "SELECT status FROM revisiones WHERE id = :revisionId";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":revisionId", $this->sanetize($revisionId));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function checkRevisionHasReportedAlerts($revisionId)
    {
        $sql = "SELECT id,id_revision,id_alert,id_policy,resource_id,resource_name,reportada,id_issue
                FROM revisiones_has_vuln
                WHERE id_revision = :revisionId
                AND reportada = 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":revisionId", $this->sanetize($revisionId));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function checkAlertaAsignadaARevision($alertId, $revisionId)
    {
        $sql = "SELECT COUNT(*) AS total
                  FROM revisiones_has_vuln
                 WHERE id_alert = :alertId
                   AND id_revision = :revisionId";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":alertId", $this->sanetize($alertId));
        $stmt->bindValue(":revisionId", $this->sanetize($revisionId));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function insertVulnIdByRevision($idRevision, array $alertIds, $idIssue)
    {
        $placeholders = rtrim(str_repeat('?,', count($alertIds)), ',');
        $sql = "UPDATE revisiones_has_vuln
                   SET id_issue = ?
                 WHERE id_revision = ?
                   AND id_alert IN ($placeholders)";
        $stmt = $this->con->prepare($sql);

        $params = array_merge(
            [$this->sanetize($idIssue), $this->sanetize($idRevision)],
            array_map([$this, 'sanetize'], $alertIds)
        );

        $stmt->execute($params);
    }

    public function getReviewNameByIssueKey($issueKey)
    {
        $sql = "SELECT r.nombre AS reviewName
                FROM revisiones_has_vuln rhv
                INNER JOIN revisiones r ON r.id = rhv.id_revision
                WHERE rhv.id_issue = :id_issue
                LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_issue", $this->sanetize($issueKey));
        $stmt->execute();

        $result = $this->respuesta($stmt);
        if (isset($result[0]["reviewName"])) {
            return $result[0]["reviewName"];
        }
        return null;
    }

    public function getAlertsByIssueKey($issueKey)
    {
        $sql = "SELECT id_alert
                FROM revisiones_has_vuln
                WHERE id_issue = :id_issue";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_issue", $this->sanetize($issueKey));
        $stmt->execute();
        $result = $this->respuesta($stmt);

        if (empty($result)) {
            throw new DbOperationsException("No se encontraron alertas asociadas al issue $issueKey.");
        }

        $alerts = array_column($result, 'id_alert');
        if (empty($alerts)) {
            throw new DbOperationsException("No hay alertas asociadas al issue $issueKey.");
        }

        return ['alertIds' => $alerts];
    }

    public function delIssueFromRevision($issueKey, $revisionId)
    {
        $sql = "UPDATE revisiones_has_vuln SET id_issue = NULL WHERE id_issue = :id_issue AND id_revision = :id_revision;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_issue", $this->sanetize($issueKey));
        $stmt->bindValue(":id_revision", $this->sanetize($revisionId));
        $stmt->execute();
    }

    public function checkIssueKeyExists($issueKey)
    {
        $sql = "SELECT id_revision FROM revisiones_has_vuln WHERE id_issue = :issueKey;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":issueKey", $this->sanetize($issueKey));
        $stmt->execute();
        $result = $this->respuesta($stmt);
        return isset($result[0]['id_revision']) ? $result[0]['id_revision'] : null;
    }

    public function getIssueKeyByRevisionId($revisionId, $alertasAsignadas)
    {
        $sql = "SELECT id_alert, id_issue
                FROM revisiones_has_vuln
                WHERE id_revision = :revisionId";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":revisionId", $this->sanetize($revisionId));
        $stmt->execute();
        $rows = $this->respuesta($stmt);

        if (empty($rows)) {
            throw new DbOperationsException("No hay vulnerabilidades registradas para la revisión $revisionId.");
        }

        $validAlerts = array_map(function ($r) {
            return $r['id_alert'];
        }, $rows);

        foreach ($alertasAsignadas as $alertId) {
            if (!in_array($alertId, $validAlerts, true)) {
                throw new DbOperationsException("La alerta $alertId no corresponde a la revisión $revisionId.");
            }
        }

        $issues = [];
        foreach ($rows as $r) {
            if (!empty($r['id_issue'])) {
                $issues[] = $r['id_issue'];
            }
        }
        $issues = array_values(array_unique($issues));

        if (empty($issues)) {
            throw new DbOperationsException("No hay issues asociadas a las alertas de la revisión $revisionId.");
        }

        return $issues;
    }

    public function getActivoBySusId($suscriptionId)
    {
        $sql = "SELECT a.id, a.nombre, a.activo_id
            FROM activos a
            INNER JOIN activos_has_suscripciones ahs ON a.id = ahs.id_activo
            WHERE ahs.suscription_id = :suscription_id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":suscription_id", $this->sanetize($suscriptionId));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getSuscriptionNameBySusId($suscriptionId)
    {
        $sql = "SELECT suscription_name FROM activos_has_suscripciones WHERE suscription_id = :suscription_id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":suscription_id", $this->sanetize($suscriptionId));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function insertSuscriptionRelation($parametros)
    {
        $parametros = $this->sanetizeJson($parametros);

        $subscriptions = $parametros['subscriptions'] ?? [];
        $subscriptionNames = $parametros['subscriptionNames'] ?? [];

        if (!is_array($subscriptions)) {
            $subscriptions = explode(',', $subscriptions);
        }
        if (!is_array($subscriptionNames)) {
            $subscriptionNames = explode(',', $subscriptionNames);
        }

        $results = [];

        foreach ($subscriptions as $index => $suscriptionId) {
            $suscriptionName = isset($subscriptionNames[$index]) ? $subscriptionNames[$index] : '';

            $sqlCheck = "SELECT COUNT(*)
                FROM activos_has_suscripciones
                WHERE id_activo = :id_activo
                  AND suscription_id = :suscription_id;";
            $stmtCheck = $this->con->prepare($sqlCheck);
            $stmtCheck->bindValue(":id_activo", $parametros["id_activo"]);
            $stmtCheck->bindValue(":suscription_id", $suscriptionId);
            $stmtCheck->execute();
            $exists = $stmtCheck->fetchColumn();

            if ($exists > 0) {
                $results[] = [
                    "suscription_id" => $suscriptionId,
                    "error" => true,
                    "message" => "La relación ya existe."
                ];
                continue;
            }

            $sql = "INSERT INTO activos_has_suscripciones (id_activo, suscription_id, suscription_name)
                VALUES (:id_activo, :suscription_id, :suscription_name);";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id_activo", $parametros["id_activo"]);
            $stmt->bindValue(":suscription_id", $suscriptionId);
            $stmt->bindValue(":suscription_name", $suscriptionName);
            $stmt->execute();

            $respuestaStmt = $this->respuesta($stmt);
            if (isset($respuestaStmt["error"]) && $respuestaStmt["error"] === true) {
                $results[] = [
                    "suscription_id" => $suscriptionId,
                    "error" => true,
                    "message" => "Error al insertar la relación."
                ];
            } else {
                $results[] = [
                    "suscription_id" => $suscriptionId,
                    "error" => false,
                    "message" => "Relación creada correctamente."
                ];
            }
        }

        return $results;
    }

    public function checkSuscriptionHasActivos($suscriptionId)
    {
        $sql = "SELECT COUNT(*)
            FROM activos_has_suscripciones
            WHERE suscription_id = :suscription_id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":suscription_id", $suscriptionId);
        $stmt->execute();
        $exists = $stmt->fetchColumn();

        if ($exists > 0) {
            return [
                "error" => true,
                "message" => "La suscripción tiene activos asociados.",
                "asociacion" => true
            ];
        }
        return [
            "error" => false,
            "message" => "La suscripción no tiene activos asociados.",
            "asociacion" => false
        ];
    }

    public function existsReviewForSuscription($suscriptionId)
    {
        $sql = "SELECT 1
              FROM revisiones
             WHERE cloudId = :suscription_id
             LIMIT 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":suscription_id", $this->sanetize($suscriptionId));
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    public function getSuscriptionRelations()
    {
        $sql = "SELECT a.id AS id_activo,
                       a.nombre AS nombre_activo,
                       a.activo_id AS tipo_activo,
                       r.id AS id,
                       r.suscription_name,
                       r.suscription_id
                FROM activos_has_suscripciones r
                INNER JOIN activos a ON r.id_activo = a.id;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $activosObj = new Activos("octopus_serv");

        foreach ($result as &$row) {
            $idActivo = $row['id_activo'];
            $parentResult = $activosObj->getParentesco($idActivo);
            $padreNombre = "Sin padre";

            if (isset($parentResult[0]['padre_id'])) {
                $parentId = $parentResult[0]['padre_id'];
                $sqlParent = "SELECT nombre FROM activos WHERE id = :id";
                $stmtParent = $this->con->prepare($sqlParent);
                $stmtParent->bindValue(":id", $this->sanetize($parentId));
                $stmtParent->execute();
                $parentRow = $stmtParent->fetch(PDO::FETCH_ASSOC);
                if ($parentRow) {
                    $padreNombre = $parentRow['nombre'];
                } else {
                    $padreNombre = "No se encontró";
                }
            }
            $row['Padre'] = $padreNombre;
        }
        return $result;
    }

    public function deleteSuscriptionRelation($suscriptionId)
    {
        try {
            $sql = "DELETE FROM activos_has_suscripciones WHERE suscription_id = :suscription_id;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":suscription_id", $this->sanetize($suscriptionId));
            $stmt->execute();

            return [
                "error"   => false,
                "message" => "Relación eliminada correctamente",
                "rowsAffected" => $stmt->rowCount()
            ];
        } catch (Exception $e) {
            return [
                "error"   => true,
                "message" => "Error al eliminar la relación: " . $e->getMessage()
            ];
        }
    }

    public function updateSuscriptionRelation($suscriptionId, $activoId)
    {
        try {
            $sql = "UPDATE activos_has_suscripciones
                   SET id_activo = :id_activo
                 WHERE suscription_id = :suscription_id;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(':id_activo',       $this->sanetize($activoId));
            $stmt->bindValue(':suscription_id',  $this->sanetize($suscriptionId));
            $stmt->execute();

            return [
                'error'        => false,
                'message'      => 'Relación actualizada correctamente',
                'rowsAffected' => $stmt->rowCount()
            ];
        } catch (Exception $e) {
            return [
                'error'   => true,
                'message' => 'Error al actualizar la relación: ' . $e->getMessage()
            ];
        }
    }

    public function setInformAsSent($id)
    {
        $sql = "UPDATE revisiones SET informe_enviado = 1 WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function checkInformeEnviado($id)
    {
        $sql = "SELECT informe_enviado FROM revisiones WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();

        $informeEnviado = $stmt->fetchColumn();

        return ($informeEnviado !== false) ? (int) $informeEnviado : null;
    }

    public function getResponsableProyecto($id)
    {
        $sql = "SELECT resp_proyecto FROM revisiones WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getArquitectoByRevisionID($id)
    {
        $sql = "SELECT user_id FROM revisiones WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }
}

class KPMs extends DbOperations
{
    public function crearReporterKPMs($userId, $idActivo)
    {
        $sql = "INSERT INTO reportes (usuario_id, activo_id) VALUES (:usuario_id, :activo_id);";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":usuario_id", $this->sanetize($userId));
        $stmt->bindValue(":activo_id", $this->sanetize($idActivo));
        $stmt->execute();
    }

    public function deleteRelacionReporter($idRelacion)
    {
        $sql = "DELETE FROM reportes WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($idRelacion));
        $stmt->execute();
    }

    public function getReportersKPMs($additional_access, $user_id)
    {
        $sql = "SELECT reportes.*, users.email as email_usuario, activos.nombre as nombre_activo
        FROM reportes
        INNER JOIN octopus_users.users ON reportes.usuario_id = users.id
        INNER JOIN octopus_serv.activos ON reportes.activo_id = activos.id;";
        if ($additional_access) {
            $stmt = $this->con->prepare($sql);
        } else {
            $sql = $sql . " WHERE usuario_id = :reporter_id";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":reporter_id", $this->sanetize($user_id));
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function editKPM($id, $nombre, $descripcion_larga, $descripcion_corta, $grupo)
    {

        $sql = "UPDATE all_metricas SET nombre = :nombre, descripcion_larga = :descripcion_larga, descripcion_corta = :descripcion_corta, grupo = :grupo WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->bindValue(":descripcion_larga", $this->sanetize($descripcion_larga));
        $stmt->bindValue(":descripcion_corta", $this->sanetize($descripcion_corta));
        $stmt->bindValue(":grupo", $this->sanetize($grupo));
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function deleteKPM($id)
    {
        $sql = "DELETE FROM all_metricas WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function newKPMs($parametros)
    {
        $sql = "INSERT into all_metricas (nombre, descripcion_corta, descripcion_larga, grupo) values (:nombre, :descripcion_corta, :descripcion_larga, :grupo);";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($parametros["numeroKPM"]));
        $stmt->bindValue(":descripcion_corta", $this->sanetize($parametros["descripcionCortaKPM"]));
        $stmt->bindValue(":descripcion_larga", $this->sanetize($parametros["descripcionLargaKPM"]));
        $stmt->bindValue(":grupo", $this->sanetize($parametros["grupoKPM"]));
        $stmt->execute();
    }
}

class Saws extends DbOperations
{
    public function getOsaStructure()
    {
        $sql = "SELECT
                    saw.id AS saw_id,
                    saw.cod AS saw_cod,
                    saw.name AS saw_name,
                    osa.id AS osa_id,
                    osa.name AS osa_name
                FROM osa
                INNER JOIN saw ON osa.saw_id = saw.id
                WHERE osa.type = 'Cloud'
                ORDER BY saw.id, osa.id;";

        $stmt = $this->con->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sawRelations = [];
        foreach ($rows as $row) {
            $sawId = $row['saw_cod'];
            $sawName = $row['saw_name'];
            $policyName = $row['policy_name'];

            $key = "{$sawId} - {$sawName}";

            if (!isset($sawRelations[$key])) {
                $sawRelations[$key] = [];
            }

            $sawRelations[$key][$policyName] = [];
        }

        return $sawRelations;
    }

    public function getPolicyIdByUUID($policy)
    {
        $sql = "SELECT id FROM octopus_new.policies WHERE uuid = :uuid;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':uuid', $this->sanetize($policy['policyId']));

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $result = $this->addPolicy($policy);
        }

        return $result['id'] ?? null;
    }

    private function getSawByCod($cod)
    {
        $sql = "SELECT id FROM saw WHERE cod = :cod";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':cod', $this->sanetize($cod));

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['id'] ?? null;
    }

    private function addPolicy($policy)
    {
        $requiredFields = ['policyId', 'name', 'recommendation', 'severity'];
        foreach ($requiredFields as $field) {
            if (!isset($policy[$field]) || empty($policy[$field])) {
                throw new DbOperationsException("Campo requerido faltante en política: {$field}");
            }
        }

        $index = false;

        if (isset($policy["complianceMetadata"]) && is_array($policy["complianceMetadata"])) {
            $index = array_search("11CERT_KPI_Nist80053_rev4", array_column($policy["complianceMetadata"], "standardName"));
        }

        if ($index !== false) {
            $policy['saw_id'] = $policy["complianceMetadata"][$index]["requirementId"];
            $policy['sectionId'] = $policy["complianceMetadata"][$index]["sectionId"];
        } else {
            throw new DbOperationsException("No se encontró el estándar de cumplimiento para la política");
        }

        $policy['saw_id'] = $this->getSawByCod($policy['saw_id']);
        if ($policy['saw_id'] == null) {
            throw new DbOperationsException("No se encontró el SAW para la política");
        }

        try {
            $sql = "INSERT INTO octopus_new.policies (uuid, name, recommendation, severity, sectionId, saw_id) VALUES (:uuid, :name, :recommendation, :severity, :sectionId, :saw_id)";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(':uuid', $this->sanetize($policy['policyId']));
            $stmt->bindValue(':name', $this->sanetize($policy['name']));
            $stmt->bindValue(':recommendation', $this->sanetize($policy['recommendation']));
            $stmt->bindValue(':severity', $this->sanetize($policy['severity']));
            $stmt->bindValue(':sectionId', $this->sanetize($policy['sectionId']));
            $stmt->bindValue(':saw_id', $this->sanetize($policy['saw_id']));

            $stmt->execute();
            return $this->con->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error al insertar política: ' . $e->getMessage());
            throw new DbOperationsException("Error al insertar la política.", 0, $e);
        }
    }

    public function getOsas()
    {
        $sql = "SELECT osa.id as osa_id, osa.cod as osa_cod, osa.type, osa.ciso_value,saw.id as saw_id,saw.cod as saw_cod
        FROM osa
        inner join saw on saw.id = osa.saw_id;";
        $stmt = $this->con->prepare($sql);
        try {
            $stmt->execute();
            return $this->respuesta($stmt);
        } catch (Exception $e) {
            error_log('Error al obtener los OSAs: ' . $e->getMessage());
        }
    }
}

class Pentest extends DbOperations
{
    public function editPentestName($newName, $id)
    {
        $sql = "UPDATE pentest SET nombre = :newName where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":newName", $this->sanetize($newName));
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function editDateStart($id, $date)
    {
        $sql = "UPDATE pentest SET fecha_inicio = :date where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":date", $this->sanetize($date));
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function editDateEnd($id, $date)
    {
        $sql = "UPDATE pentest SET fecha_final = :date where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":date", $this->sanetize($date));
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function eliminarIssuePentest($key)
    {
        $sql = "DELETE FROM pentest_has_vuln WHERE id_issue = :key";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":key", $this->sanetize($key));
        $stmt->execute();
    }

    public function obtenerPentestIssue($key)
    {
        $sql = "SELECT nombre from pentest where id = (SELECT id_pentest from pentest_has_vuln where id_issue = :id)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($key));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function cambiarStatusPentest($id, $valor)
    {
        $sql = 'UPDATE pentest SET status = :valor where id = :id';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->bindValue(":valor", $this->sanetize($valor));
        $stmt->execute();
    }

    public function reabrirPentest($id)
    {
        $sql = 'UPDATE pentest SET status = 1 where id = :id';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function eliminarAppSDLC($id, $kiuwan_id = null, $app)
    {
        // Eliminar el registro de la tabla SDLC
        $sqlDelete = 'DELETE FROM sdlc WHERE id = :id';
        $stmtDelete = $this->con->prepare($sqlDelete);
        $stmtDelete->bindValue(":id", $this->sanetize($id));
        $stmtDelete->execute();

        if (strtolower($app) === 'kiuwan' && !empty($kiuwan_id)) {
            $sqlUpdateKiuwan = 'UPDATE kiuwan SET registrada = 0 WHERE id = :kiuwan_id';
            $stmtUpdate = $this->con->prepare($sqlUpdateKiuwan);
            $stmtUpdate->bindValue(':kiuwan_id', $this->sanetize($kiuwan_id));
            $stmtUpdate->execute();
        }
    }

    public function modificarAppSDLC($parametros)
    {
        $sql = 'UPDATE sdlc SET';

        if (!empty($parametros["Comentarios"])) {
            $sql .= ' comentarios = :comentarios,';
        }

        if ($parametros["CMM"] != "Ninguno") {
            $sql .= ' CMM = :CMM,';
        }

        if (!empty($parametros["url_sonar"])) {
            $sql .= ' url_sonar = :url_sonar,';
        }

        $sql = rtrim($sql, ',');

        $sql .= ' WHERE id = :id';

        $stmt = $this->con->prepare($sql);

        if (!empty($parametros["Comentarios"])) {
            $stmt->bindValue(":comentarios", $this->sanetize($parametros["Comentarios"]));
        }

        if ($parametros["CMM"] != "Ninguno") {
            $stmt->bindValue(":CMM", $this->sanetize($parametros["CMM"]));
        }

        if (!empty($parametros["url_sonar"])) {
            $stmt->bindValue(":url_sonar", $this->sanetize($parametros["url_sonar"]));
        }

        $stmt->bindValue(":id", $this->sanetize($parametros["id"]));

        $stmt->execute();
    }

    public function modificarSDLC($parametros)
    {
        $sql = "UPDATE sdlc
                SET direccion_id = :direccion_id, area_id = :area_id, producto_id = :producto_id,
                    CMM = :CMM, analisis = :analisis, comentarios = :comentarios,
                    url_sonar = :url_sonar, fecha_analisis_kiuwan = :fecha_analisis_kiuwan
                WHERE kiuwan_id = :kiuwan_id;";

        $stmt = $this->con->prepare($sql);

        $stmt->bindValue(":direccion_id", $parametros["direccion_id"]);
        $stmt->bindValue(":area_id", $parametros["area_id"]);
        $stmt->bindValue(":producto_id", $parametros["producto_id"]);
        $stmt->bindValue(":CMM", $parametros["CMM"]);
        $stmt->bindValue(":analisis", $parametros["analisis"]);
        $stmt->bindValue(":comentarios", $parametros["comentarios"]);
        $stmt->bindValue(":url_sonar", $parametros["url_sonar"]);
        $stmt->bindValue(":fecha_analisis_kiuwan", $parametros["fecha_analisis_kiuwan"]);
        $stmt->bindValue(":kiuwan_id", $parametros["kiuwan_id"]);

        $stmt->execute();

        return $this->respuesta($stmt);
    }

    public function addSdlc($parametros)
    {
        $parametrosSanitizados = $this->sanetizeJson($parametros);

        $sql = 'INSERT INTO sdlc (direccion_id, area_id, producto_id, kiuwan_id, CMM, analisis, comentarios, url_sonar, fecha_analisis_kiuwan, slot_sonarqube, fecha_analisis_sonar, app)
                VALUES (:direccion_id, :area_id, :producto_id, :kiuwan_id, :CMM, :analisis, :comentarios, :url_sonar, :fecha_analisis_kiuwan, :slot_sonarqube, :fecha_analisis_sonar, :app);';

        $stmt = $this->con->prepare($sql);

        $stmt->bindValue(":direccion_id", $parametrosSanitizados["Direccion"]);
        $stmt->bindValue(":area_id", $parametrosSanitizados["Area"]);
        $stmt->bindValue(":producto_id", $parametrosSanitizados["Producto"]);

        $kiuwanId = $parametrosSanitizados["app"] === "Sonarqube" ? null : $parametrosSanitizados["kiuwan_id"];
        if ($parametrosSanitizados["app"] === "Sonarqube") {
            $fechaAnalisisKiuwan = null;
        } else {
            $fechaAnalisisKiuwan = !empty($parametrosSanitizados["fecha_analisis_kiuwan"]) ? $parametrosSanitizados["fecha_analisis_kiuwan"] : null;
        }

        $slotSonarqube = $parametrosSanitizados["app"] === "Kiuwan" ? null : $parametrosSanitizados["sonarqube_slot"];
        $fechaAnalisisSonar = $parametrosSanitizados["app"] === "Kiuwan" ? null : $parametrosSanitizados["fecha_analisis_sonarqube"];

        $stmt->bindValue(":kiuwan_id", $kiuwanId);
        $stmt->bindValue(":fecha_analisis_kiuwan", $fechaAnalisisKiuwan);
        $stmt->bindValue(":slot_sonarqube", $slotSonarqube);
        $stmt->bindValue(":fecha_analisis_sonar", $fechaAnalisisSonar);

        $stmt->bindValue(":CMM", $parametrosSanitizados["CMM"]);
        $stmt->bindValue(":analisis", $parametrosSanitizados["Analisis"]);
        $stmt->bindValue(":comentarios", $parametrosSanitizados["Comentarios"]);
        $stmt->bindValue(":url_sonar", $parametrosSanitizados["url_sonar"]);
        $stmt->bindValue(":app", $parametrosSanitizados["app"]);

        $stmt->execute();

        if ($parametrosSanitizados["app"] === "Kiuwan" && !is_null($parametrosSanitizados["kiuwan_id"])) {
            $sqlUpdate = 'UPDATE kiuwan SET registrada = 1 WHERE id = :kiuwan_id';
            $stmtUpdate = $this->con->prepare($sqlUpdate);
            $stmtUpdate->bindValue(":kiuwan_id", $parametrosSanitizados["kiuwan_id"]);
            $stmtUpdate->execute();
        }

        return $this->respuesta($stmt);
    }

    public function obtenerAplicacionesSDLC($parametros = [])
    {
        $sql = "SELECT id, direccion_id, area_id, producto_id, CMM, analisis, comentarios, app, url_sonar, fecha_analisis_kiuwan, kiuwan_id, slot_sonarqube, fecha_analisis_sonar, cumple_kpm_sonar
                FROM sdlc";

        if (!empty($parametros['app'])) {
            $sql .= " WHERE app = :app";
        }

        $stmt = $this->con->prepare($sql);

        if (!empty($parametros['app'])) {
            $stmt->bindValue(':app', $this->sanetize($parametros['app']));
        }

        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntasByUSF($id_usf, $normative = false)
    {
        if ($normative) {
            $sql = "SELECT marco.id_usf, marco.id_ctrls, marco.id_preguntas FROM marco
            INNER JOIN ctrls on ctrls.id = marco.id_ctrls
            WHERE id_usf = :id_usf AND ctrls.id_normativa = :normative;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id_usf", $this->sanetize($id_usf));
            $stmt->bindValue(":normative", $this->sanetize($normative));
        } else {
            $sql = "SELECT * FROM marco WHERE id_usf = :id_usf;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id_usf", $this->sanetize($id_usf));
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtainVulnID($nombre)
    {
        $sql = "SELECT id FROM vulnerabilidades WHERE nombre = :nombre";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $nombre);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function insertarServicio($id_pentest, $hijos)
    {
        foreach ($hijos as $hijo) {
            if ($hijo["tipo"] == 33) {
                $sql = 'INSERT INTO pentest_has_activos (id_activo,id_pentest) VALUES (:id_activo, :id_pentest)';
                $stmt = $this->con->prepare($sql);
                $stmt->bindValue(":id_activo", $this->sanetize($hijo["id"]));
                $stmt->bindValue(":id_pentest", $this->sanetize($id_pentest));
                $stmt->execute();
            }
        }
    }

    public function insertarID($id_pentest, $arrayID)
    {
        foreach ($arrayID as $hijo) {
            $sql = 'INSERT INTO pentest_has_activos (id_activo,id_pentest) VALUES (:id_activo, :id_pentest)';
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id_activo", $this->sanetize($hijo));
            $stmt->bindValue(":id_pentest", $this->sanetize($id_pentest));
            $stmt->execute();
        }
    }

    public function insertVulnPentest($id_issue, $id_pentest, $id_vuln)
    {
        $sql = 'INSERT INTO pentest_has_vuln (id_vul,id_pentest,id_issue) VALUES (:id_vuln, :id_pentest, :id_issue)';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_vuln", $this->sanetize($id_vuln));
        $stmt->bindValue(":id_pentest", $this->sanetize($id_pentest));
        $stmt->bindValue(":id_issue", $this->sanetize($id_issue));
        $stmt->execute();
    }

    public function obtainPentestID($nombre)
    {
        $sql = "SELECT id FROM pentest WHERE nombre = :nombre";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtainPentestFromId($id)
    {
        $sql = "SELECT * FROM pentest WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtainAllPentest($nombre)
    {
        $sql = "SELECT * FROM pentest WHERE nombre = :nombre";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function eliminarissueTabla($key)
    {
        $sql = "DELETE FROM pentest_has_vuln WHERE id_issue = :id_issue";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_issue", $this->sanetize($key));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function eliminarPentests($id)
    {
        $sql = "SELECT * FROM pentest_has_vuln where id_pentest = :id_pentest";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_pentest", $this->sanetize($id));
        $stmt->execute();
        $respuesta = $this->respuesta($stmt);
        if (!isset($respuesta[0])) {
            $sql = "DELETE FROM pentest WHERE id = :id";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
            $stmt->execute();
            return $this->respuesta($stmt);
        } else {
            return "Error";
        }
    }

    public function obtenerUsfPentest($vuls)
    {
        $usf = array();
        foreach ($vuls as $vuln) {
            $sql = "SELECT usf_id FROM vulnerabilidades_has_usf where vulnerabilidad_id = :id_vuln";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id_vuln", $this->sanetize($vuln["id_vul"]));
            $stmt->execute();
            $usf = array_merge($usf, $this->respuesta($stmt));
        }
        return $usf;
    }

    public function getDocumentacionByPentestId($id)
    {
        $sql = "SELECT pr.id, pr.documentacion, p.solicitud_id
        FROM pentest_request pr
        INNER JOIN pentest p ON pr.id = p.solicitud_id
        WHERE p.id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPentestProducts()
    {
        $sql = "SELECT * FROM `pentest_has_activos` JOIN activos ON
                pentest_has_activos.id_activo = activos.id where activo_id = 67;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function insertActivosPentest($id_pentest, $id_activo)
    {
        if (isset($id_pentest[0]["id"])) {
            $id_pentest = $id_pentest[0]["id"];
        }
        $sql = 'INSERT INTO pentest_has_activos (id_pentest,id_activo) VALUES (:id_pentest, :id_activo)';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_pentest", $this->sanetize($id_pentest));
        $stmt->bindValue(":id_activo", $this->sanetize($id_activo));
        $stmt->execute();
    }

    public function eliminarActivosPentest($idPentest)
    {
        $sql = "DELETE FROM pentest_has_activos WHERE id_pentest = :id_pentest";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_pentest", $this->sanetize($idPentest));
        $stmt->execute();
    }

    public function obtenerActivosPentest($idPentest)
    {
        $sql = "SELECT * FROM pentest_has_activos where id_pentest = :id_pentest";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_pentest", $this->sanetize($idPentest));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtenerPentestByActivo($idActivo)
    {
        $sql = "SELECT * FROM pentest_has_activos where id_activo = :id_activo";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_activo", $this->sanetize($idActivo));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function obtenerVulnsPentest($idPentest)
    {
        $sql = "SELECT * FROM pentest_has_vuln where id_pentest = :id_pentest";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_pentest", $this->sanetize($idPentest));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function cerrarPentests($id)
    {
        $sql = "UPDATE pentest SET status = 0 WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getAllIssues()
    {
        $sql = "SELECT id_issue FROM pentest_has_vuln";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPentestsIssues($id_issue)
    {
        $sql = "SELECT * FROM pentest
        INNER JOIN pentest_has_vuln ON pentest.id = pentest_has_vuln.id_pentest
        WHERE pentest_has_vuln.id_issue = :id_issue";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_issue", $this->sanetize($id_issue));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPentests()
    {
        $sql = "SELECT * FROM pentest";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function crearPentest($parametros, $tipo = null)
    {
        if ($tipo == null) {
            $status = 1;
            $tipo = "Pentest";
        } else {
            $status = 5;
            $tipo = "Pynt";
        }

        $sql = 'INSERT INTO pentest (nombre, descripcion, resp_proyecto, fecha_inicio, fecha_final, status, proyecto, tipo, solicitud_id, mail_soporte)
                VALUES (:nombre, :descripcion, :resp_proyecto, :fecha_inicio, :fecha_final, :status, :AreaServ, :tipo, :solicitud_id, :mail_soporte)';

        $stmt =  $this->con->prepare($sql);

        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($parametros["Nombre"]));
        $stmt->bindValue(":descripcion", $this->sanetize($parametros["Descripcion"]));
        $stmt->bindValue(":resp_proyecto", $this->sanetize($parametros["Responsable"]));
        $stmt->bindValue(":fecha_inicio", $this->sanetize($parametros["Fecha_inicio"]));
        $stmt->bindValue(":fecha_final", $this->sanetize($parametros["Fecha_final"]));
        $stmt->bindValue(":status", $this->sanetize($status));
        $stmt->bindValue(":AreaServ", $this->sanetize($parametros["AreaServ"]));
        $stmt->bindValue(self::PUNTO_TIPO, $this->sanetize($tipo));

        if (isset($parametros["solicitud_id"]) && $parametros["solicitud_id"] !== '') {
            $stmt->bindValue(":solicitud_id", $this->sanetize($parametros["solicitud_id"]), PDO::PARAM_INT);
        } else {
            $stmt->bindValue(":solicitud_id", null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(":mail_soporte", $this->sanetize($parametros["mail_soporte"]));

        return $stmt->execute();
    }

    public function asignPentester($parametros)
    {
        $sqlCheck = "SELECT resp_pentest FROM pentest WHERE id = :id";
        $stmtCheck = $this->con->prepare($sqlCheck);
        $stmtCheck->bindValue(":id", $this->sanetize($parametros["id"]));
        $stmtCheck->execute();
        $currentPentester = $stmtCheck->fetchColumn();

        if ($currentPentester) {
            return [
                "error" => true,
                "message" => "Este pentest ya ha sido asignado. Para cambiar el pentester, edite el pentest."
            ];
        }

        $sql = "UPDATE pentest SET resp_pentest = :resp_pentest WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":resp_pentest", $this->sanetize($parametros["resp_pentest"]));
        $stmt->bindValue(":id", $this->sanetize($parametros["id"]));
        $stmt->execute();

        return [
            "error" => false,
            "message" => "Pentester asignado exitosamente."
        ];
    }

    public function editPentester($id, $resp_pentest)
    {
        $sql = "UPDATE pentest SET resp_pentest = :resp_pentest where id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":resp_pentest", $this->sanetize($resp_pentest));
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function getKiuwanData()
    {
        $sql = 'SELECT id, app_name, creation_date, code, analysis_code, analysis_url, analysis_date, cumple_kpm, registrada
                FROM kiuwan;';

        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {
            return ['error' => true, 'message' => 'No se encontraron resultados para los parámetros proporcionados.'];
        }

        return $result;
    }

    public function insertKiuwanData($data)
    {
        try {
            $sql = 'INSERT INTO kiuwan (app_name, creation_date, code, analysis_code, analysis_url, analysis_date, cumple_kpm)
            VALUES (:app_name, :creation_date, :code, :analysis_code, :analysis_url, :analysis_date, :cumple_kpm)
            ON DUPLICATE KEY UPDATE
                code = IFNULL(VALUES(code), code),
                analysis_code = IFNULL(VALUES(analysis_code), analysis_code),
                analysis_url = IFNULL(VALUES(analysis_url), analysis_url),
                analysis_date = IFNULL(VALUES(analysis_date), analysis_date),
                cumple_kpm = IFNULL(VALUES(cumple_kpm), cumple_kpm);';

            $stmt = $this->con->prepare($sql);

            $app_name = $this->sanetize($data["app_name"]);
            $creation_date = isset($data["creation_date"]) && !empty($data["creation_date"]) ? $this->sanetize($data["creation_date"]) : null;
            $code = $this->sanetize($data["code"]);

            $analysis_code = isset($data["analysis_code"]) ? $this->sanetize($data["analysis_code"]) : null;
            $analysis_url = isset($data["analysis_url"]) ? $this->sanetize($data["analysis_url"]) : null;
            $analysis_date = isset($data["analysis_date"]) ? $this->sanetize($data["analysis_date"]) : null;
            $cumple_kpm = null;

            $stmt->bindValue(":app_name", $app_name);
            $stmt->bindValue(":creation_date", $creation_date, PDO::PARAM_STR);
            $stmt->bindValue(":code", $code);

            $stmt->bindValue(":analysis_code", $analysis_code);
            $stmt->bindValue(":analysis_url", $analysis_url);
            $stmt->bindValue(":analysis_date", $analysis_date);
            $stmt->bindValue(":cumple_kpm", $cumple_kpm);

            $stmt->execute();
        } catch (PDOException $e) {
            error_log('Database Insertion Error: ' . $e->getMessage());
            throw new DbOperationsException('Error inserting data into the database.', 0, $e);
        }
    }

    public function updateCumpleKpm($app_name, $cumple_kpm_value)
    {
        try {
            $sql = 'UPDATE kiuwan
                SET cumple_kpm = :cumple_kpm
                WHERE app_name = :app_name;';

            $stmt = $this->con->prepare($sql);

            $stmt->bindValue(":app_name", $this->sanetize($app_name));
            $stmt->bindValue(":cumple_kpm", $cumple_kpm_value);

            $stmt->execute();
        } catch (PDOException $e) {
            throw new DbOperationsException('Error updating cumple_kpm in the database.', 0, $e);
        }
    }

    public function updateSonarKPM($slot_sonarqube, $cumple_kpm_sonar_value)
    {
        try {
            $sql = 'UPDATE sdlc
                SET cumple_kpm_sonar = :cumple_kpm_sonar
                WHERE slot_sonarqube = :slot_sonarqube;';

            $stmt = $this->con->prepare($sql);

            $stmt->bindValue(":slot_sonarqube", $this->sanetize($slot_sonarqube));
            $stmt->bindValue(":cumple_kpm_sonar", $cumple_kpm_sonar_value);

            $stmt->execute();
        } catch (PDOException $e) {
            throw new DbOperationsException('Error updating cumple_kpm_sonar in the database.', 0, $e);
        }
    }

    public function getEvents()
    {
        $sql = "SELECT id, nombre, fecha_inicio, fecha_final FROM pentest;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function checkReqInforme($id)
    {
        $sql = "SELECT r.req_informe
            FROM pentest p
            INNER JOIN pentest_request r ON p.solicitud_id = r.id
            WHERE p.id = :id
            LIMIT 1;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $reqInforme = $stmt->fetchColumn();

        return ($reqInforme !== false) ? (int)$reqInforme : null;
    }

    public function getUserBySolID($id)
    {
        $sql = "SELECT r.user_id
            FROM pentest p
            INNER JOIN pentest_request r ON p.solicitud_id = r.id
            WHERE p.id = :id
            LIMIT 1;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $userId = $stmt->fetchColumn();

        return ($userId !== false) ? (int) $userId : null;
    }


    public function getResponsableProyecto($id)
    {
        $sql = "SELECT resp_proyecto FROM pentest WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function setInformAsSent($id)
    {
        $sql = "UPDATE pentest SET informe_enviado = 1 WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
    }

    public function checkInformeEnviado($id)
    {
        $sql = "SELECT informe_enviado FROM pentest WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();

        $informeEnviado = $stmt->fetchColumn();

        return ($informeEnviado !== false) ? (int) $informeEnviado : null;
    }

    // Aquí en el futuro habrá que considerar añadir los valores "fecha_modificacion" y "modificado_por" para tener un registro de auditoría
    public function insertComments($pentest_id, $comments, $autor, $fecha_creacion)
    {
        try {
            $sql = 'INSERT INTO pentest_has_comentarios (pentest_id, comentarios, autor, fecha_creacion)
                VALUES (:pentest_id, :comentarios, :autor, :fecha_creacion);';

            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":pentest_id", $this->sanetize($pentest_id));
            $stmt->bindValue(":comentarios", $this->sanetize($comments));
            $stmt->bindValue(":autor", $this->sanetize($autor));
            $stmt->bindValue(":fecha_creacion", $this->sanetize($fecha_creacion));
            $stmt->execute();

            return $this->respuesta($stmt);
        } catch (PDOException $e) {
            error_log('Database Insertion Error: ' . $e->getMessage());
            throw new DbOperationsException('Error inserting comments into the database.', 0, $e);
        }
    }

    public function getComments($pentest_id)
    {
        $sql = 'SELECT comentarios FROM pentest_has_comentarios WHERE pentest_id = :pentest_id';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":pentest_id", $this->sanetize($pentest_id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }
}

class Logs extends DbOperations
{
    public function addEditActivoLogs($activo_id, $tipo_mod, $nuevo_valor, $last_valor, $userId)
    {
        $fecha_actual = date("Y-m-d");
        $sql = 'INSERT INTO activo_modificado (id_activo, tipo_modificacion, nuevo_valor, antiguo_valor, id_usuario, fecha) VALUES (:id_activo, :tipo_mod, :nuevo_valor, :last_valor, :id_usuario, :fecha);';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_activo", $this->sanetize($activo_id));
        $stmt->bindValue(":tipo_mod", $this->sanetize($tipo_mod));
        $stmt->bindValue(":nuevo_valor", $this->sanetize($nuevo_valor));
        $stmt->bindValue(":last_valor", $this->sanetize($last_valor));
        $stmt->bindValue(":id_usuario", $this->sanetize($userId));
        $stmt->bindValue(self::PUNTO_FECHA, $fecha_actual);
        $stmt->execute();
    }

    public function addDeleteLog($activo, $user)
    {
        $fecha_actual = date("Y-m-d");
        $sql = 'INSERT INTO deleted_activos (activo_id, activo_nombre, id_usuario, fecha) VALUES (:id_activo, :activo_nombre, :id_usuario, :fecha);';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_activo", $this->sanetize($activo["id"]));
        $stmt->bindValue(":activo_nombre", $this->sanetize($activo["nombre"]));
        $stmt->bindValue(":id_usuario", $this->sanetize($user));
        $stmt->bindValue(self::PUNTO_FECHA, $fecha_actual);
        $stmt->execute();
    }

    public function addNewActivoLogs($activo_id, $user)
    {
        $fecha_actual = date("Y-m-d");
        $sql = 'INSERT INTO new_activos (id_activo, id_usuario, fecha) VALUES (:id_activo, :id_usuario, :fecha);';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_activo", $this->sanetize($activo_id));
        $stmt->bindValue(":id_usuario", $this->sanetize($user));
        $stmt->bindValue(self::PUNTO_FECHA, $fecha_actual);
        $stmt->execute();
    }

    public function addLogChangeRelation($parametros, $user)
    {
        $fecha_actual = date("Y-m-d");
        $sql = 'INSERT INTO relation_changes (id_activo, old_padre, new_padre, id_usuario, fecha) VALUES (:id_activo, :old_padre, :new_padre, :id_usuario, :fecha);';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id_activo", $this->sanetize($parametros["hijo"]));
        $stmt->bindValue(":old_padre", $this->sanetize($parametros["oldPadre"]));
        $stmt->bindValue(":new_padre", $this->sanetize($parametros["padre"]));
        $stmt->bindValue(":id_usuario", $this->sanetize($user));
        $stmt->bindValue(self::PUNTO_FECHA, $fecha_actual);
        $stmt->execute();
    }

    public function getLogsRelacion()
    {
        $logs = [];

        $sql = 'select * from relation_changes;';
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $logs["relation_changes"] = $this->respuesta($stmt);

        $sql = 'select * from new_activos;';
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $logs["new_activos"] = $this->respuesta($stmt);

        $sql = 'select * from deleted_activos;';
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $logs["deleted_activos"] = $this->respuesta($stmt);

        $sql = 'select * from activo_modificado;';
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $logs["modified_activos"] = $this->respuesta($stmt);

        return $logs;
    }

    public function getLastRouteUpdate()
    {
        $sql = "SELECT last_update FROM route_update_log ORDER BY last_update DESC LIMIT 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return ($result !== false) ? $result : null;
    }

    public function updateRouteLog()
    {
        $sql = "INSERT INTO route_update_log (last_update) VALUES (NOW());";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
    }
}

class PentestRequest extends DbOperations
{
    // Función para crear un nuevo formulario de pentest
    public function crearFormulario($parametros)
    {
        $parametros = $this->sanetizeJson($parametros);

        $sql = 'INSERT INTO pentest_request (user_id, servicio_a_pentest, id_activo, nombre_servicio, version_servicio, tipo_pentest, tipo_entorno, fecha_solicitud, fecha_inicio, fecha_fin, req_informe, franja_horaria, horas_pentest, proyecto_jira, resp_pentest, persona_soporte, aviso_incump, documentacion)
                VALUES (:user_id, :servicio_a_pentest, :id_activo, :nombre_servicio, :version_servicio, :tipo_pentest, :tipo_entorno, :fecha_solicitud, :fecha_inicio, :fecha_fin, :req_informe, :franja_horaria, :horas_pentest, :proyecto_jira, :resp_pentest, :persona_soporte, :aviso_incump, :documentacion);';

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':user_id', $parametros['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':servicio_a_pentest', $parametros['servicio_a_pentest']);
        $stmt->bindValue(':id_activo', $parametros['id_activo']);
        $stmt->bindValue(':nombre_servicio', $parametros['nombre_servicio']);
        $stmt->bindValue(':version_servicio', $parametros['version_servicio']);
        $stmt->bindValue(':tipo_pentest', $parametros['tipo_pentest']);
        $stmt->bindValue(':tipo_entorno', $parametros['tipo_entorno']);
        $stmt->bindValue(':fecha_solicitud', $parametros['fecha_solicitud']);
        $stmt->bindValue(':fecha_inicio', $parametros['fecha_inicio']);
        $stmt->bindValue(':fecha_fin', $parametros['fecha_fin']);
        $stmt->bindValue(':req_informe', $parametros['req_informe']);
        $stmt->bindValue(':franja_horaria', $parametros['franja_horaria']);
        $stmt->bindValue(':horas_pentest', $parametros['horas_pentest']);
        $stmt->bindValue(':proyecto_jira', $parametros['proyecto_jira']);
        $stmt->bindValue(':resp_pentest', $parametros['resp_pentest']);
        $stmt->bindValue(':persona_soporte', $parametros['persona_soporte']);
        $stmt->bindValue(':aviso_incump', $parametros['aviso_incump']);
        $stmt->bindValue(':documentacion', $parametros['documentacion']);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    // Función para ver todos los datos almacenados
    public function getDatosFormulario()
    {
        $sql = "SELECT * FROM pentest_request WHERE estado = 0;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function aceptarSolicitudPentest($id)
    {
        $sql = "UPDATE pentest_request SET estado = :estado WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id), PDO::PARAM_INT);
        $stmt->bindValue(':estado', $this->sanetize(1)); // 1 --> Aceptado
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function rechazarSolicitudPentest($id)
    {
        $sql = "SELECT nombre_servicio, fecha_inicio, fecha_fin, tipo_pentest, tipo_entorno, franja_horaria, horas_pentest, documentacion
                FROM pentest_request
                WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id), PDO::PARAM_INT);
        $stmt->execute();
        $solicitudData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Actualizamos el estado a rechazado (2)
        $sqlUpdate = "UPDATE pentest_request SET estado = :estado WHERE id = :id;";
        $stmtUpdate = $this->con->prepare($sqlUpdate);
        $stmtUpdate->bindValue(':id', $this->sanetize($id), PDO::PARAM_INT);
        $stmtUpdate->bindValue(':estado', $this->sanetize(2)); // 2 --> Rechazado
        $stmtUpdate->execute();

        return [
            "status" => $stmtUpdate->rowCount() > 0,
            "solicitudData" => $solicitudData,
            "error" => $stmtUpdate->rowCount() == 0 ? "Error al actualizar el estado de la solicitud." : null
        ];
    }
}

class Activos extends DbOperations
{
    public function getPreguntasEvaluacionesSistema($sistemaID)
    {
        $sql = "SELECT
                    e.activo_id AS activo_id,
                    e.meta_key as tipo_evaluacion,
                    e.id AS evaluacion_id,
                    e.meta_value AS preguntas_evaluacion,
                    ev.id AS version_id,
                    ev.meta_value AS preguntas_version
                FROM
                    evaluaciones e
                LEFT JOIN
                    evaluaciones_versiones ev
                ON
                    e.id = ev.evaluacion_id
                WHERE
                    e.activo_id = :sistemaID and e.meta_key = 'preguntas';";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($sistemaID));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getServiciosArchivados()
    {
        $sql = "SELECT * FROM activos WHERE activo_id = 42 and archivado = 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getEvaluaciones3PSById($id)
    {
        $sql = "SELECT * FROM `evaluaciones` where meta_value like '%3ps%' and activo_id = :id;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
        $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultados = [];
        foreach ($evaluaciones as $evaluacion) {
            $evaluacionId = $evaluacion['id'];
            $sqlVersiones = "SELECT * FROM evaluaciones_versiones WHERE evaluacion_id = :evaluacion_id ORDER BY version DESC;";
            $stmt = $this->con->prepare($sqlVersiones);
            $stmt->bindParam(':evaluacion_id', $evaluacionId, PDO::PARAM_INT);
            $stmt->execute();
            $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!isset($versiones[0])) {
                $resultados[] = $evaluacion;
            } else {
                $versiones[0]["activo_id"] = $evaluacion["activo_id"];
                $resultados[] = $versiones[0];
            }
        }
        return $resultados;
    }

    public function getEvaluaciones3PS()
    {
        $sql = "SELECT * FROM `evaluaciones` where meta_value like '%3ps%';";

        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultados = [];
        foreach ($evaluaciones as $evaluacion) {
            $evaluacionId = $evaluacion['id'];
            $sqlVersiones = "SELECT * FROM evaluaciones_versiones WHERE evaluacion_id = :evaluacion_id ORDER BY version DESC";
            $stmt = $this->con->prepare($sqlVersiones);
            $stmt->bindParam(':evaluacion_id', $evaluacionId, PDO::PARAM_INT);
            $stmt->execute();
            $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!isset($versiones[0])) {
                $resultados[] = $evaluacion;
            } else {
                $versiones[0]["activo_id"] = $evaluacion["activo_id"];
                $resultados[] = $versiones[0];
            }
        }
        return $resultados;
    }

    public function getPreguntasKpmsCsirt()
    {
        $sql = "SELECT *
        FROM octopus_kpms.all_metricas
        WHERE nombre
        IN(SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE COLUMN_NAME
        LIKE 'KPM%' AND TABLE_SCHEMA = 'octopus_kpms' AND TABLE_NAME = 'csirt');";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntasKpmsFormulario()
    {
        $sql = "SELECT * FROM all_metricas WHERE form_metricas = 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getAllPreguntasKpms()
    {
        $sql = "SELECT * FROM all_metricas";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function editarKPMFormulario($parametros, $valor)
    {
        $sql = "UPDATE all_metricas SET form_metricas = :valor WHERE nombre = :nombre;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("valor", $this->sanetize($valor));
        $stmt->bindValue("nombre", $this->sanetize($parametros["kpm"]));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getUSFProyectoPac($idProyecto)
    {
        $this->__construct('octopus_new');
        $sql = "SELECT * FROM usf WHERE id_proyecto = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($idProyecto));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getProyectoPacSys($idPac)
    {
        $sql = "SELECT proyecto_id FROM seguimientopac WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($idPac));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivoByPacId($idPac)
    {
        $this->__construct('octopus_serv');
        $sql = "SELECT activo_id FROM seguimientopac WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($idPac));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivosPermisos($id)
    {
        $sql = "SELECT activos.id,nombre,activos.activo_id AS tipo,user_id FROM `visibilidad`
        INNER JOIN activos ON activos.id = visibilidad.activo_id
        WHERE usuario_id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getAllActivos()
    {
        $sql = "SELECT tab1.id,tab1.nombre,tab1.descripcion,tab2.nombre as tipo,tab3.email,archivado FROM activos tab1
            INNER JOIN octopus_new.activos tab2 on tab1.activo_id = tab2.id
            INNER JOIN octopus_users.users tab3 on tab1.user_id = tab3.id
            WHERE tab1.activo_id <> 124 and tab1.activo_id <> 123 and tab1.activo_id <> 122
            ORDER BY nombre ASC;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function newPersonas($id)
    {
        if ($id !== null) {
            $sql = 'INSERT INTO personas (activo_id,product_owner,r_seguridad,r_config_puesto_trabajo,r_operaciones,r_desarrollo,r_legal,r_rrhh,r_kpms,consultor_ciso) VALUES (:id,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)';
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
            $stmt->execute();
            return $this->getPersonas($id);
        }
    }

    public function getPersonas($id = null)
    {
        if ($id !== null) {
            $sql = "SELECT id,activo_id,product_owner,r_seguridad,r_config_puesto_trabajo,r_operaciones,r_desarrollo,r_legal,r_rrhh,r_kpms,consultor_ciso FROM personas WHERE activo_id = :id;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
            $stmt->execute();
            $resultado = $this->respuesta($stmt);
            if (!isset($resultado[0])) {
                $resultado = $this->newPersonas($id);
            }
            return $resultado;
        }
    }

    public function getmetricasbyuser($id = null, $admin = false)
    {
        $sql = "SELECT metricas.*, users.email
                FROM metricas
                INNER JOIN octopus_users.users ON metricas.usuario_id = users.id";
        if (!$admin) {
            $sql .= " WHERE metricas.usuario_id = :id
                      OR EXISTS (
                            SELECT 1
                            FROM reportes r1
                            JOIN reportes r2 ON r1.activo_id = r2.activo_id
                            WHERE r1.usuario_id = :id
                              AND r2.usuario_id = metricas.usuario_id
                      )";
        }
        $stmt = $this->con->prepare($sql);
        if (!$admin) {
            $stmt->bindValue(":id", $this->sanetize($id));
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getmadurezbyuser($id = null, $admin = false)
    {
        $sql = "SELECT madurez.*, users.email
                FROM madurez
                INNER JOIN octopus_users.users ON madurez.usuario_id = users.id";
        if (!$admin) {
            $sql .= " WHERE madurez.usuario_id = :id
                      OR EXISTS (
                            SELECT 1
                            FROM reportes r1
                            INNER JOIN reportes r2 ON r1.activo_id = r2.activo_id
                            WHERE r1.usuario_id = :id
                              AND r2.usuario_id = madurez.usuario_id
                      )";
        }
        $stmt = $this->con->prepare($sql);
        if (!$admin) {
            $stmt->bindValue(":id", $this->sanetize($id));
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getMetricasCsirtByUser($id = null, $admin = false)
    {
        $sql = "SELECT csirt.id,csirt.usuario_id, csirt.fecha, csirt.actualizado, csirt.reporte, csirt.area, csirt.direccion, csirt.cuarto,csirt.KPM48, csirt.KPM50, csirt.KPM51, csirt.KPM52, csirt.KPM58, csirt.KPM59A, csirt.KPM59B, csirt.KPM60A, csirt.KPM60B,csirt.comentario, csirt.sugerencia, csirt.bloqueado, users.email
                FROM csirt
                INNER JOIN octopus_users.users ON csirt.usuario_id = users.id";
        if (!$admin) {
            $sql .= " WHERE csirt.usuario_id = :id";
        }
        $stmt = $this->con->prepare($sql);
        if (!$admin) {
            $stmt->bindValue(":id", $this->sanetize($id));
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getReportAsCsirt()
    {
        $sql = "SELECT activo_id FROM reportes GROUP BY activo_id;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getReportAs($id, $apiRoute)
    {
        $result = null;

        if (!isset($id)) {
            $result = [
                'error' => true,
                'message' => 'No se ha especificado el id del usuario.'
            ];
        } else {
            $id = intval($id);

            $db = new Usuarios(DB_USER);
            $user = $db->getUser($id);

            if (!$user || !isset($user[0])) {
                $result = [
                    'error' => true,
                    'message' => 'Usuario no encontrado.'
                ];
            } else {
                $additionalAccess = checkForAdditionalAccess($user[0]["roles"], $apiRoute);

                if ($additionalAccess) {
                    $sql = "SELECT activo_id FROM reportes GROUP BY activo_id;";
                    $stmt = $this->con->prepare($sql);
                } else {
                    $sql = "SELECT * FROM reportes WHERE usuario_id = :id;";
                    $stmt = $this->con->prepare($sql);
                    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                }

                // Execute the statement
                try {
                    $stmt->execute();
                    $result = $this->respuesta($stmt);
                } catch (PDOException $e) {
                    $result = [
                        'error' => true,
                        'message' => 'Error en la ejecución de la consulta: ' . $e->getMessage()
                    ];
                }
            }
        }

        return $result;
    }

    public function getReportActivoTrimestre($id, $trimestre)
    {
        $sql = "SELECT * FROM csirt where reporte = :id and cuarto = :trimestre order by fecha DESC";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $id);
        $stmt->bindValue(":trimestre", $this->sanetize($trimestre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getLastReportKpms($parametros, $id_usuario)
    {
        if (isset($parametros["tipo"]) && ($parametros["tipo"] == "madurez" || $parametros["tipo"] == "metricas" || $parametros["tipo"] == "csirt")) {
            $tipo = $parametros["tipo"];
            $sql = "SELECT * FROM $tipo where usuario_id = $id_usuario ORDER BY fecha DESC LIMIT 1;";
            return  $this->con->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return [
                ERROR => true,
                MESSAGE => 'No se ha especificado el tipo de reporte de kpms para ejecutar la consulta o el parámetro no era el esperado.'
            ];
        }
    }

    public function lockkpms($parametros = null)
    {
        if (!isset($parametros['tipo'], $parametros['id'])) {
            return [
                ERROR => true,
                MESSAGE => 'Faltan algunos parámetros para poder ejecutar la acción.'
            ];
        }

        $tipo = $parametros['tipo'];
        $ids = $parametros['id'];
        $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
        $sql = "UPDATE $tipo SET bloqueado = 1 WHERE id IN ($placeholders)";
        $stmt = $this->con->prepare($sql);

        foreach ($ids as $key => $id) {
            $stmt->bindValue(($key + 1), $id);
        }

        $stmt->execute();

        return [
            ERROR => false,
            MESSAGE => 'Reportes bloqueados correctamente.'
        ];
    }

    public function editkpm($parametros, $user)
    {
        // Check for required parameters
        if (!isset($parametros['tipo'], $parametros['id'])) {
            $result = [
                ERROR => true,
                MESSAGE => 'Faltan algunos parámetros para poder ejecutar la acción.'
            ];
        } else {
            $kpm = $this->getkpmsbyid($parametros["id"], $parametros["tipo"]);

            // Check if KPM exists and is not locked
            if (!isset($kpm[0]) || $kpm[0]["bloqueado"] != 0) {
                $result = [
                    ERROR => true,
                    MESSAGE => 'El reporte se encuentra bloqueado para su edición.'
                ];
            } else {
                // Check permissions
                $admin = $this->isAdmin($user[0]["id"]);
                $isOwner = ($user[0]["id"] == $kpm[0]["usuario_id"]);

                if (!$admin && !$isOwner) {
                    $result = [
                        ERROR => true,
                        MESSAGE => 'No puede editar el reporte por que no es del mismo usuario.'
                    ];
                } else {
                    // Process the edit
                    $reportes = array_keys($parametros);
                    $sql = "update " . $parametros["tipo"] . " metricas set ";
                    $len = count($reportes);

                    foreach ($reportes as $index => $reporte) {
                        if ($reporte != "id" && $reporte != "tipo") {
                            $sql .= $reporte . " = :" . $reporte;
                            if ($index != $len - 1) {
                                $sql .= ", ";
                            }
                        }
                    }

                    $sql .= " where id = :id;";
                    $this->__construct('octopus_kpms');
                    $stmt = $this->con->prepare($sql);
                    unset($parametros["tipo"]);

                    foreach ($reportes as $index => $reporte) {
                        if ($reporte != "tipo") {
                            if ($parametros[$reporte] == "") {
                                $stmt->bindValue(":$reporte", null);
                            } else {
                                $stmt->bindValue(":$reporte", $this->sanetize($parametros[$reporte]));
                            }
                        }
                    }

                    $stmt->execute();

                    $result = [
                        ERROR => false,
                        MESSAGE => 'Reporte editado correctamente.'
                    ];
                }
            }
        }

        return $result;
    }

    private function getkpmsbyid($id, $tipo)
    {
        $this->__construct('octopus_kpms');
        $sql = "SELECT id,usuario_id,fecha,bloqueado FROM $tipo where id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function unlockkpms($parametros = null)
    {
        if (!isset($parametros['tipo'], $parametros['id'])) {
            return [
                ERROR => true,
                MESSAGE => 'Faltan algunos parámetros para poder ejecutar la acción.'
            ];
        }

        $tipo = $parametros['tipo'];
        $ids = $parametros['id'];
        $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
        $sql = "UPDATE $tipo SET bloqueado = 0 WHERE id IN ($placeholders)";
        $stmt = $this->con->prepare($sql);
        $stmt->execute($ids);

        return [
            ERROR => false,
            MESSAGE => 'Reportes desbloqueados correctamente.'
        ];
    }

    public function delkpms($parametros = null)
    {
        if (!isset($parametros['tipo'], $parametros['id'])) {
            return [
                ERROR => true,
                MESSAGE => 'Faltan algunos parámetros para poder ejecutar la acción.'
            ];
        }

        $tipo = $parametros['tipo'];
        $ids = $parametros['id'];
        $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
        $sql = "DELETE FROM $tipo WHERE id IN ($placeholders)";
        $stmt = $this->con->prepare($sql);
        $stmt->execute($ids);

        return [
            ERROR => false,
            MESSAGE => 'Reporte borrado correctamente.'
        ];
    }

    public function getPadres($id)
    {
        $sql = "WITH RECURSIVE RecursivoPadres AS (
            SELECT id, nombre, padre, tipo, tipo_id
            FROM vistafamiliaactivos
            WHERE id = :id
            UNION ALL
            SELECT vp.id, vp.nombre, vp.padre, vp.tipo, vp.tipo_id
            FROM vistafamiliaactivos vp INNER JOIN RecursivoPadres rp ON vp.id = rp.padre
        )
        SELECT id, nombre, padre, tipo, tipo_id
        FROM RecursivoPadres;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getHijosTipo($id, $tipo)
    {
        $sql = "WITH RECURSIVE RecursivoHijos AS (
            SELECT id, nombre, padre, tipo, tipo_id, archivado FROM vistafamiliaactivos
            WHERE id = :id
            UNION ALL
            SELECT vp.id, vp.nombre, vp.padre, vp.tipo, vp.tipo_id, vp.archivado
            FROM vistafamiliaactivos vp INNER JOIN RecursivoHijos rh ON vp.padre = rh.id
        )
        SELECT padre, nombre, MIN(id) as id, MIN(tipo) as tipo, MIN(tipo_id) as tipo_id, MIN(archivado) as archivado
        FROM RecursivoHijos
        where tipo = :tipo
        GROUP BY padre, nombre;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->bindValue(self::PUNTO_TIPO, $this->sanetize($tipo));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getHijos($id)
    {
        $sql = "WITH RECURSIVE RecursivoHijos AS (
            SELECT id, nombre, padre, tipo, tipo_id, archivado FROM vistafamiliaactivos
            WHERE id = :id
            UNION ALL
            SELECT vp.id, vp.nombre, vp.padre, vp.tipo, vp.tipo_id, vp.archivado
            FROM vistafamiliaactivos vp INNER JOIN RecursivoHijos rh ON vp.padre = rh.id
        )
        SELECT padre, nombre, MIN(id) as id, MIN(tipo) as tipo, MIN(tipo_id) as tipo_id, archivado
        FROM RecursivoHijos
        GROUP BY padre, nombre, archivado;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivo($id = null, $user = null)
    {
        $bind = false;
        if ($id !== null) {
            if ($this->isPropietarioActivo($id, $user)) {
                $bind = true;
                $sql = "SELECT id,nombre,critico,expuesto,descripcion,activo_id as tipo,user_id,archivado FROM activos WHERE id = :id and user_id = :user ORDER BY nombre ASC;";
            } else {
                $sql = "SELECT id,nombre,critico,expuesto,descripcion,activo_id as tipo,user_id,archivado FROM activos WHERE id = :id ORDER BY nombre ASC;";
            }
        } else {
            if ($user == 'admin') {
                $sql = "SELECT id,nombre,critico,expuesto,descripcion,activo_id as tipo,user_id,archivado FROM activos WHERE activo_id = 42 ORDER BY nombre ASC;";
            } else {
                $sql = "SELECT id,nombre,critico,expuesto,descripcion,activo_id as tipo,user_id,archivado FROM activos WHERE activo_id = 42 and user_id = :user ORDER BY nombre ASC;";
            }

            $bind = true;
        }
        $stmt = $this->con->prepare($sql);
        if ($id !== null) {
            $stmt->bindValue(":id", $this->sanetize($id));
        }

        if ($bind) {
            $stmt->bindValue(":user", $user);
        }

        $stmt->execute();
        return $this->respuesta($stmt);
    }


    public function getActivos($ids = null, $admin = false, $user = null)
    {
        $bind = false;

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT id,nombre,critico,expuesto,descripcion,activo_id as tipo,user_id,archivado FROM activos WHERE id IN ($placeholders) ORDER BY nombre ASC;";
        } else {
            if ($admin) {
                $sql = "SELECT id,nombre,critico,expuesto,descripcion,activo_id as tipo,user_id,archivado FROM activos WHERE activo_id = 42 ORDER BY nombre ASC;";
            } else {
                $sql = "SELECT id,nombre,critico,expuesto,descripcion,activo_id as tipo,user_id,archivado FROM activos WHERE activo_id = 42 and user_id = :user ORDER BY nombre ASC;";
                $bind = true;
            }
        }

        $stmt = $this->con->prepare($sql);

        if (!empty($ids)) {
            foreach ($ids as $index => $id) {
                $stmt->bindValue(($index + 1), $this->sanetize($id));
            }
        }

        if ($bind) {
            $stmt->bindValue(":user", $user);
        }

        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivosByTipo($tipo, $user = null, $archivado = false, $externo = false, $additionalAccess = false)
    {
        if ($archivado) {
            if ($archivado == "All") {
                $archivado = "";
            } else {
                $archivado = " AND archivado = 1";
            }
        } else {
            $archivado = " AND archivado = 0";
        }

        if ($externo) {
            if ($externo == "All") {
                $externo = "";
            } else {
                $externo = " AND externo = 1";
            }
        } else {
            $externo = " AND externo = 0";
        }

        if ($user == null) {
            $sql = "SELECT id, nombre, activo_id as tipo, user_id, descripcion, archivado, externo, expuesto, critico FROM activos WHERE activo_id = :tipo $archivado $externo ORDER BY nombre ASC";
        } else {
            if ($additionalAccess || $tipo == "124" || $tipo == "123" || $tipo == "122") {
                $sql = "SELECT id, nombre, activo_id as tipo, user_id, descripcion, archivado, externo, expuesto, critico FROM activos WHERE activo_id = :tipo $archivado ORDER BY nombre ASC";
            } else {
                $sql = "SELECT id, nombre, activo_id as tipo, user_id, descripcion, archivado, externo, expuesto, critico FROM activos WHERE activo_id = :tipo AND user_id = :id  $archivado $externo ORDER BY nombre ASC";
            }
        }

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_TIPO, $this->sanetize($tipo));
        if ($user !== null && !$additionalAccess && $tipo !== "124" && $tipo !== "123" && $tipo !== "122") {
            $stmt->bindValue(':id', $this->sanetize($user["id"]));
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getEvaluacionCache($hash_activos, $hash_preguntas)
    {
        $sql = "SELECT * FROM cache_evaluaciones WHERE hash_activos = :hash_activos AND hash_preguntas = :hash_preguntas";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":hash_activos", $hash_activos);
        $stmt->bindValue(":hash_preguntas", $hash_preguntas);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getBiaCache($hash_bia)
    {
        $sql = "SELECT * FROM cache_bia WHERE hash_preguntas = :hash_bia";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":hash_bia", $hash_bia);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function updateEvaluacionCache($hash_activos, $hash_preguntas, $respuesta)
    {
        $sql = "INSERT INTO cache_evaluaciones (hash_activos, hash_preguntas, resultados_json, ultimo_acceso, num_accesos)
                VALUES (:hash_activos, :hash_preguntas, :respuesta, CURRENT_TIMESTAMP, 1)
                ON DUPLICATE KEY UPDATE resultados_json = :respuesta, ultimo_acceso = CURRENT_TIMESTAMP, num_accesos = num_accesos + 1;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":hash_activos", $hash_activos);
        $stmt->bindValue(":hash_preguntas", $hash_preguntas);
        $stmt->bindValue(":respuesta", json_encode($respuesta));
        $stmt->execute();

        return $this->respuesta($stmt);
    }

    public function updateBiaCache($hash_bia, $respuesta)
    {
        $sql = "INSERT INTO cache_bia (hash_preguntas, resultados_json, last_access, num_access)
                VALUES (:hash_preguntas, :resultados_json, CURRENT_TIMESTAMP, 1)
                ON DUPLICATE KEY UPDATE resultados_json = :resultados_json, last_access = CURRENT_TIMESTAMP, num_access = num_access + 1;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":hash_preguntas", $hash_bia);
        $stmt->bindValue(":resultados_json", json_encode($respuesta));
        $stmt->execute();

        return $this->respuesta($stmt);
    }

    public function getNumServiciosBia()
    {
        $sql = "SELECT evaluaciones.activo_id as activo,fecha FROM evaluaciones inner join activos on activos.id = evaluaciones.activo_id WHERE meta_key = 'bia' and activos.activo_id = 42 group by evaluaciones.activo_id,fecha;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getNumSistemasEcr()
    {
        $sql = "SELECT evaluaciones.activo_id as activo FROM evaluaciones
                INNER JOIN activos ON activos.id = evaluaciones.activo_id
                WHERE meta_key = 'preguntas' AND activos.activo_id = 33
                GROUP BY evaluaciones.activo_id, meta_key;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }


    public function getMediaEcrSistemas($sistemas)
    {
        if (isset($sistemas) && is_array($sistemas)) {
            $count = 0;
            $group = '';
            $cuenta = count($sistemas);
            foreach ($sistemas as $sistema) {
                $group .= "activo_id = $sistema";
                $count++;

                if ($count < $cuenta) {
                    $group .= " OR ";
                }
            }
            if ($count != 0) {
                $sql = "SELECT activo_id as activo FROM evaluaciones WHERE meta_key = 'preguntas' AND ($group) group by activo_id;";
                $stmt = $this->con->prepare($sql);
                $stmt->execute();
            }
        }
        if ($count != 0) {
            $conECR = $this->respuesta($stmt);
            $media = count($conECR) / $cuenta;
        } else {
            return 1;
        }
        return $media;
    }

    public function getServiciosSinBia()
    {
        $sql = "SELECT activos.id,activos.nombre FROM evaluaciones
        RIGHT JOIN activos ON evaluaciones.activo_id = activos.id
        WHERE activos.activo_id = 42 AND meta_key IS NULL;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getServiciosBiaDesactualizado()
    {
        $sql = "SELECT a.id,e.fecha
        FROM activos a
        JOIN evaluaciones e ON a.id = e.activo_id
        WHERE a.activo_id = 42 AND e.meta_key = 'bia' AND e.fecha < DATE_SUB(NOW(), INTERVAL 1 YEAR);";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getNumActivos($tipo)
    {
        $sql = "SELECT id FROM activos WHERE activo_id = :tipo;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_TIPO, $this->sanetize($tipo));
        $stmt->execute();
        return $this->respuesta($stmt);
    }
    public function obtenerTipoActivo($id)
    {
        $sql = "SELECT activo_id FROM activos WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }
    public function getActivoByTipo($id)
    {
        $sql = "SELECT id,nombre FROM activos WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivosExpuestos()
    {
        $sql = "SELECT id,nombre,expuesto FROM activos where expuesto = 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivosExposicion()
    {
        $sql = "SELECT id,nombre,expuesto,activo_id FROM activos;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivosMetricas()
    {
        $sql = "SELECT id,nombre,critico,expuesto FROM activos;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivosCriticos()
    {
        $sql = "SELECT id,nombre,critico FROM activos;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivoByNombre($nombre)
    {
        $sql = "SELECT id,nombre FROM activos WHERE nombre like :nombre limit 10;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getActivoIdByNombre($nombre)
    {
        $sql = "SELECT id FROM activos WHERE nombre like :nombre limit 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getClaseActivosObligatorios()
    {
        $sql = 'SELECT id as tipo,nombre FROM activos where obligatorio = 1;';
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getClaseActivos()
    {
        $sql = "SELECT id,tipo,nombre,capa,perspectiva FROM activos ORDER BY perspectiva,nombre ASC;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getClaseActivoByTipo($nombre)
    {
        $sql = "SELECT id,nombre,tipo,capa,perspectiva FROM activos WHERE nombre = :nombre LIMIT 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getClaseActivoById($id)
    {
        $this->__construct('octopus_new');
        $sql = "SELECT nombre FROM activos WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    /**
     * Crear activos
     *
     * @param string $nombre
     * @param int $clase
     * @param int $padre
     * @param int $user
     * @return array
     */
    public function newActivo($nombre, $clase, $padre = self::UNDEFINED, $user = null)
    {
        // Initialize result variable with default error
        $result = ['error' => true, 'message' => "No se ha podido crear el activo."];

        // Validaciones iniciales
        if (!isset($nombre)) {
            $result = ['error' => true, 'message' => "No has insertado el nombre del activo."];
        } elseif ($padre === self::UNDEFINED && ($clase != 124 && $clase != 42 && $clase != 94)) {
            $result = ['error' => true, 'message' => "Este tipo de activo no se puede crear sin padre asociado."];
        } else {
            try {
                $sql = "INSERT INTO activos (nombre,activo_id,user_id) VALUES (:nombre,:activo_id,:user_id)";
                $stmt = $this->con->prepare($sql);
                $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
                $stmt->bindValue(":activo_id", $this->sanetize($clase));
                $stmt->bindValue(":user_id", $this->sanetize($user));

                if ($stmt->execute()) {
                    $id_insert = $this->con->lastInsertId();
                    if ($padre !== self::UNDEFINED) {
                        $this->newParentesco($id_insert, $padre);
                    }
                    $result = ['error' => false, 'message' => "El activo se ha creado correctamente."];
                }
            } catch (Exception $e) {
                $activo = $this->getActivoByNombre($nombre)[0];
                $parentescos = $this->getParentesco($activo['id']);
                $parentescos = array_column($parentescos, 'padre_id');
                if ($padre !== self::UNDEFINED && !in_array($padre, $parentescos)) {
                    $this->newParentesco($activo['id'], $padre);
                    $result = ['error' => false, 'message' => "El activo que intenta crear ya existe por lo que se ha emparentado."];
                } else {
                    $result = ['error' => true, 'message' => "El activo ya existe y no se ha podido crear otro nuevo."];
                }
            }
        }

        return $result;
    }

    public function archivarActivos($activos, $archivar, $user)
    {
        if (!is_array($activos)) {
            return;
        }

        $admin = $this->isAdmin($user);
        $sql = "UPDATE activos SET archivado = :archivado WHERE id = :id";
        $this->__construct("octopus_serv");
        $stmt = $this->con->prepare($sql);

        foreach ($activos as $activo) {
            if (!$admin) {
                $propietario = $this->isPropietarioActivo($activo["id"], $user);
            }
            if ($admin || $propietario) {
                // Verificar si el activo tiene hijos
                $parentescos = $this->getFathersNewByTipo($activo["id"], "Servicio de Negocio");
                $total = count($parentescos);
                foreach ($parentescos as $parentesco) {
                    $parentesco_activo = $this->getActivo($parentesco["id"]);
                    if ($parentesco_activo[0]["archivado"] == 1) {
                        $total--;
                    }
                }

                if ($total < 1) {
                    $stmt->bindValue(':archivado', $this->sanetize($archivar));
                    $stmt->bindValue(':id', $this->sanetize($activo["id"]));
                    $stmt->execute();
                }
            }
        }
    }


    public function editActivo($id, $nombre, $user, $tipo = null, $descripcion = null, $archivado = 0, $externo = 0, $expuesto = 0)
    {
        if ($this->isAdmin($user)) {
            $this->__construct("octopus_serv");
            $sql = "UPDATE activos SET nombre = :dato WHERE id = :id";
            if ($tipo !== null && $descripcion !== null && $archivado !== null && $externo !== null) {
                $sql = "UPDATE activos SET nombre = :dato, activo_id = :tipo, descripcion = :descripcion, archivado = :archivado, externo = :externo, expuesto = :expuesto WHERE id = :id";
            }
        } else {
            if ($this->isPropietarioActivo($id, $user)) {
                if ($tipo !== null && $descripcion !== null && $archivado !== null && $externo !== null) {
                    $sql = "UPDATE activos SET nombre = :dato, activo_id = :tipo, descripcion = :descripcion, archivado = :archivado, externo = :externo, expuesto = :expuesto WHERE id = :id";
                }
            } else {
                return self::NOT_PROD;
            }
        }
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':dato', $this->sanetize($nombre));
        $stmt->bindValue(':id', $this->sanetize($id));
        if ($tipo !== null && $descripcion !== null && $archivado !== null && $externo !== null) {
            $stmt->bindValue(self::PUNTO_TIPO, $this->sanetize($tipo));
            $stmt->bindValue(':descripcion', $this->sanetize($descripcion));
            $stmt->bindValue(':archivado', $this->sanetize($archivado));
            $stmt->bindValue(':externo', $this->sanetize($externo));
            $stmt->bindValue(':expuesto', $this->sanetize($expuesto));
        }
        $stmt->execute();
    }

    public function editPersonasActivo($id, $user, $data)
    {
        if ($this->isAdmin($user)) {
            $this->__construct("octopus_serv");
            $sql = "UPDATE personas SET product_owner = :product_owner, r_seguridad = :r_seguridad, r_operaciones = :r_operaciones, r_desarrollo = :r_desarrollo, r_kpms = :r_kpms WHERE activo_id = :id;";
        } else {
            if ($this->isPropietarioActivo($id, $user)) {
                $sql = "UPDATE personas SET product_owner = :product_owner, r_seguridad = :r_seguridad, r_operaciones = :r_operaciones, r_desarrollo = :r_desarrollo, r_kpms = :r_kpms WHERE activo_id = :id;";
            } else {
                return self::NOT_PROD;
            }
        }
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->bindValue(':product_owner', $this->sanetize($data["product_owner"]));
        $stmt->bindValue(':r_desarrollo', $this->sanetize($data["r_desarrollo"]));
        $stmt->bindValue(':r_kpms', $this->sanetize($data["r_kpms"]));
        $stmt->bindValue(':r_operaciones', $this->sanetize($data["r_operaciones"]));
        $stmt->bindValue(':r_seguridad', $this->sanetize($data["r_seguridad"]));
        $stmt->execute();
    }

    public function delActivo($id, $user, $additional_access = false)
    {
        if (isset($id)) {
            if ($additional_access) {
                $this->__construct("octopus_serv");
                $sql = "DELETE FROM activos WHERE id = :id";
                $stmt = $this->con->prepare($sql);
                $stmt->bindValue(":id", $this->sanetize($id));
                $stmt->execute();
            } else {
                if ($this->isPropietarioActivo($id, $user)) {
                    $sql = "DELETE FROM activos WHERE id = :id";
                    $stmt = $this->con->prepare($sql);
                    $stmt->bindValue(":id", $this->sanetize($id));
                    $stmt->execute();
                } else {
                    return self::NOT_PROD;
                }
            }
        }
    }

    public function deleteRelacion($id, $padreID)
    {
        $this->__construct("octopus_serv");
        $sql = "DELETE FROM parentesco WHERE activo_id = :id AND padre_id = :padreID;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->bindValue(":padreID", $this->sanetize($padreID));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function addRelation($id, $padre)
    {
        $this->__construct("octopus_serv");
        $sql = "INSERT INTO parentesco (activo_id, padre_id) VALUES (:id, :padre);";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->bindValue(":padre", $this->sanetize($padre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getParentesco($id)
    {
        $this->__construct("octopus_serv");
        if (isset($id)) {
            $sql = "select padre_id FROM parentesco WHERE activo_id = :id;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
            $stmt->execute();
            return $this->respuesta($stmt);
        }
    }

    public function getSeguimientoByActivoId($id = null, $estado = null)
    {
        if ($estado == null) {
            $estado = "";
        } else {
            $estado = " AND seguimientopac.estado = '$estado'";
        }
        if ($id !== null) {
            $sql = "SELECT seguimientopac.id,activo_id,proyecto_id,evaluacion_id,responsable,estado,inicio,fin,creado,comentarios,adjuntos,octopus_new.proyectos.nombre as nombrepac,octopus_new.proyectos.cod as codpac FROM seguimientopac
            INNER JOIN octopus_new.proyectos on seguimientopac.proyecto_id = octopus_new.proyectos.id
            WHERE activo_id = :id $estado;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
        } else {
            $sql = "SELECT seguimientopac.id,seguimientopac.activo_id,proyecto_id,evaluacion_id,responsable,estado,inicio,fin,creado,comentarios,adjuntos,octopus_new.proyectos.nombre as nombrepac,octopus_new.proyectos.cod as codpac,activos.nombre as sistema FROM seguimientopac
            INNER JOIN octopus_new.proyectos on seguimientopac.proyecto_id = octopus_new.proyectos.id
            INNER JOIN octopus_serv.activos on octopus_serv.activos.id = seguimientopac.activo_id;";
            $stmt = $this->con->prepare($sql);
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getSeguimientoByPacId($id)
    {
        if ($id !== null) {
            $sql = "SELECT seguimientopac.id, seguimientopac.activo_id, seguimientopac.estado, seguimientopac.evaluacion_id, seguimientopac.comentarios, activos.id AS activo_id, activos.nombre AS activo_nombre
                    FROM seguimientopac
                    INNER JOIN activos ON seguimientopac.activo_id = activos.id
                    WHERE seguimientopac.proyecto_id = :id;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getSeguimientoById($id)
    {
        if ($id !== null) {
            $sql = 'SELECT seguimientopac.id,activo_id,proyecto_id,evaluacion_id,responsable,estado,inicio,fin,creado,comentarios,adjuntos,octopus_new.proyectos.nombre AS nombrepac FROM seguimientopac
            INNER JOIN octopus_new.proyectos on seguimientopac.proyecto_id = octopus_new.proyectos.id
            WHERE seguimientopac.id = :id;';
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
        }
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function editPacSeguimiento($id, $datos)
    {
        $this->__construct("octopus_serv");
        $sql = "UPDATE seguimientopac SET ";
        foreach ($datos as $key => $value) {
            $sql .= "$key = :$key,";
        }
        $sql = trim($sql, ',');
        $sql .= " WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        if (isset($datos["responsable"])) {
            $stmt->bindValue("responsable", $this->sanetize($datos["responsable"]));
        }

        if (isset($datos["inicio"])) {
            $stmt->bindValue("inicio", $this->sanetize($datos["inicio"]));
        }

        if (isset($datos["fin"])) {
            $stmt->bindValue("fin", $this->sanetize($datos["fin"]));
        }

        if (isset($datos["estado"])) {
            $stmt->bindValue("estado", $this->sanetize($datos["estado"]));
        }

        if (isset($datos["comentarios"])) {
            $stmt->bindValue("comentarios", $this->sanetize($datos["comentarios"]));
        }

        $stmt->bindValue("id", $this->sanetize($id));

        $stmt->execute();
        if ($datos["estado"] == "Finalizado") {
            cambiarEvaluacionPac($id, 1);
        } elseif ($datos["estado"] == "Iniciado") {
            cambiarEvaluacionPac($id, 0);
        }
    }

    public function newPacSeguimiento($activo_id, $proyecto_id, $evaluacion_id, $inicio = null, $fin = null, $comentarios = null, $responsable = null, $estado = 'No iniciado', $adjunto = null)
    {
        $this->__construct("octopus_serv");
        $inicio = ($inicio !== null && $inicio !== '') ? $this->sanetize($inicio) : null;
        $fin = ($fin !== null && $fin !== '') ? $this->sanetize($fin) : null;

        $sql = "INSERT INTO seguimientopac (activo_id, proyecto_id, evaluacion_id, responsable, estado, inicio, fin, comentarios, adjuntos)
                VALUES (:activo_id, :proyecto_id, :evaluacion_id, :responsable, :estado, :inicio, :fin, :comentarios, :adjuntos);";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("activo_id", $this->sanetize($activo_id));
        $stmt->bindValue("proyecto_id", $this->sanetize($proyecto_id));
        $stmt->bindValue("evaluacion_id", $this->sanetize($evaluacion_id));
        $stmt->bindValue("responsable", $this->sanetize($responsable));
        $stmt->bindValue("estado", $this->sanetize($estado));
        $stmt->bindValue("inicio", $inicio, PDO::PARAM_STR);
        $stmt->bindValue("fin", $fin, PDO::PARAM_STR);
        $stmt->bindValue("comentarios", $this->sanetize($comentarios));
        $stmt->bindValue("adjuntos", $this->sanetize($adjunto));
        $stmt->execute();

        // Obtener el ID del último proyecto insertado
        $id = $this->con->lastInsertId();

        // Llamar a editPacSeguimiento si el estado es 'Finalizado'
        $proyecto = $this->getProyectoById($proyecto_id);
        if (isset($proyecto[0]) && $proyecto[0]['default_status'] === 'Finalizado') {
            $date = date('Y-m-d H:i:s');
            $datos = [
                'inicio' => $date,
                'fin' => $date,
                'estado' => 'Finalizado',
            ];
            $this->editPacSeguimiento($id, $datos);
        }
    }


    public function delPacSeguimiento($id)
    {
        $sql = "DELETE FROM seguimientopac WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
    }

    public function delParentesco($id, $padre, $user, $additional_access = false)
    {
        if (isset($id)) {
            if ($additional_access) {
                $this->__construct("octopus_serv");
                $sql = "DELETE FROM parentesco WHERE activo_id = :id AND padre_id = :padre";
                $stmt = $this->con->prepare($sql);
                $stmt->bindValue(":id", $this->sanetize($id));
                $stmt->bindValue(":padre", $this->sanetize($padre));
                $stmt->execute();
            } else {
                if ($this->isPropietarioActivo($id, $user)) {
                    $sql = "DELETE FROM parentesco WHERE activo_id = :id AND padre_id = :padre";
                    $stmt = $this->con->prepare($sql);
                    $stmt->bindValue(":id", $this->sanetize($id));
                    $stmt->bindValue(":padre", $this->sanetize($padre));
                    $stmt->execute();
                } else {
                    return self::NOT_PROD;
                }
            }
        }
    }

    public function getProyectos()
    {
        $sql = 'SELECT id,cod,nombre,descripcion,tareas FROM proyectos ORDER BY cod ASC';
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getProyectoById($id)
    {
        $this->__construct("octopus_new");
        $sql = 'SELECT id,cod,nombre,descripcion,tareas,default_status FROM proyectos
        WHERE id = :id';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getProyectoByNombre($nombre)
    {
        $this->__construct('octopus_new');
        $sql = 'SELECT id,cod,nombre,descripcion,tareas FROM proyectos
        WHERE nombre = :nombre';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('nombre', $this->sanetize($nombre));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function isPropietarioActivo($id, $user)
    {
        if ($user !== null) {
            $this->__construct('octopus_serv');
            $sql = "SELECT id FROM activos WHERE id = :id AND user_id = :user";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
            $stmt->bindValue(":user", $user);
            $stmt->execute();
            if (isset($this->respuesta($stmt)[0])) {
                $respuesta = true;
            } else {
                $respuesta = false;
            }
            return $respuesta;
        } else {
            return false;
        }
    }

    public function getPlan()
    {
        $sql = "SELECT * from plan;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function delPlan($id)
    {
        if (isset($id)) {
            $sql = "DELETE FROM plan WHERE id = :id";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(":id", $this->sanetize($id));
            $stmt->execute();
        }
    }

    public function newPlan($datos)
    {
        $datosSanitizados = $this->sanetizeJson($datos);

        $sql = "INSERT INTO plan (direccion,area,unidad,criticidad,prioridad,servicio,estado,elevencert,eprivacy,JefeProyecto,SecretoEmpresarial,Entorno,Tenable,DOME9,UsuarioAcceso,Revisiones,q1,q2,q3,q4)
            VALUES (:direccion,:area,:unidad,:criticidad,:prioridad,:servicio,:estado,:elevencert,:eprivacy,:JefeProyecto,:SecretoEmpresarial,:Entorno,:Tenable,:DOME9,:UsuarioAcceso,:Revisiones,:q1,:q2,:q3,:q4);";

        $stmt = $this->con->prepare($sql);

        $stmt->bindValue("direccion", $datosSanitizados["direccion"]);
        $stmt->bindValue("area", $datosSanitizados["area"]);
        $stmt->bindValue("unidad", $datosSanitizados["unidad"]);
        $stmt->bindValue("criticidad", $datosSanitizados["criticidad"]);
        $stmt->bindValue("prioridad", $datosSanitizados["prioridad"]);
        $stmt->bindValue("servicio", $datosSanitizados["servicio"]);
        $stmt->bindValue("estado", $datosSanitizados["estado"]);
        $stmt->bindValue("elevencert", $datosSanitizados["elevencert"]);
        $stmt->bindValue("eprivacy", $datosSanitizados["eprivacy"]);
        $stmt->bindValue("JefeProyecto", $datosSanitizados["JefeProyecto"]);
        $stmt->bindValue("SecretoEmpresarial", $datosSanitizados["SecretoEmpresarial"]);
        $stmt->bindValue("Entorno", $datosSanitizados["Entorno"]);
        $stmt->bindValue("Tenable", $datosSanitizados["Tenable"]);
        $stmt->bindValue("DOME9", $datosSanitizados["DOME9"]);
        $stmt->bindValue("UsuarioAcceso", $datosSanitizados["UsuarioAcceso"]);
        $stmt->bindValue("Revisiones", $datosSanitizados["Revisiones"]);
        $stmt->bindValue("q1", $datosSanitizados["q1"]);
        $stmt->bindValue("q2", $datosSanitizados["q2"]);
        $stmt->bindValue("q3", $datosSanitizados["q3"]);
        $stmt->bindValue("q4", $datosSanitizados["q4"]);

        $stmt->execute();
    }

    public function editExposicion($id, $number)
    {
        $sql = "UPDATE activos SET expuesto = :num WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->bindValue("num", $this->sanetize($number));
        $stmt->execute();
    }


    public function editCriticidad($id, $number)
    {
        $sql = "UPDATE activos SET critico = :num WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->bindValue("num", $this->sanetize($number));
        $stmt->execute();
    }

    public function editPlan($datos)
    {
        $sql = "UPDATE plan SET direccion = :direccion,area = :area,unidad = :unidad,criticidad = :criticidad, prioridad = :prioridad,servicio = :servicio,estado = :estado,elevencert = :elevencert,eprivacy = :eprivacy,jefeproyecto = :jefeproyecto,secretoempresarial = :secretoempresarial,entorno = :entorno,tenable = :tenable,dome9 = :dome9,usuarioacceso = :usuarioacceso,revisiones = :revisiones,q1 = :q1,q2 = :q2,q3 = :q3,q4 = :q4
        WHERE id = :id";
        $stmt = $this->con->prepare($sql);
        $params = array_map(function ($value) {
            return $this->sanetize($value);
        }, $datos);
        $stmt->execute($params);
    }

    private $tree = array();
    private function getChildren($arr, $tipo = null)
    {
        if ($tipo !== null) {
            $arr['tipo'] = $this->getClaseActivoById($arr['tipo'])[0]['nombre'];
        }
        $numberChild = $this->getChild($arr['id']);
        if (count($numberChild) > 0) {
            foreach ($numberChild as $child) {
                if ($tipo !== null) {
                    $this->tree[] = $this->getChildren($child, 'nombre');
                } else {
                    $this->tree[] = $this->getChildren($child);
                }
            }
        }
        return $arr;
    }

    public function getTree($arr, $tipo = null)
    {
        $this->tree = array();
        if ($tipo == null) {
            $this->getChildren($arr);
        } else {
            $this->getChildren($arr, 'nombre');
        }
        return $this->tree;
    }

    public function getFathers($arr)
    {
        $fathers = $this->getParentesco($arr["id"]);

        if (is_array($fathers)) {
            $padresIds = array_column($fathers, 'padre_id');
            $padres = $this->getActivos($padresIds, false, null);
        }

        return $padres;
    }

    public function getVulnerabilidades()
    {
        $sql = "SELECT * from vulnerabilidades order by tipo_prueba;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getFathersNewByTipo($id, $tipo)
    {
        $sql = 'WITH RECURSIVE CTE_Padres AS (
                    SELECT id, nombre, padre, tipo
                    FROM vistafamiliaactivos
                    WHERE id = :id
                    UNION ALL
                    SELECT a.id, a.nombre, a.padre, a.tipo
                    FROM vistafamiliaactivos a
                    INNER JOIN CTE_Padres p ON a.id = p.padre
                )
                SELECT DISTINCT id, nombre, tipo
                FROM CTE_Padres
                WHERE tipo = :tipo;';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->bindValue('tipo', $this->sanetize($tipo));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getFathersNew($id)
    {
        $sql = 'WITH RECURSIVE CTE_Padres AS (
                    SELECT id, nombre, padre, tipo
                    FROM vistafamiliaactivos
                    WHERE id = :id
                    UNION ALL
                    SELECT a.id, a.nombre, a.padre, a.tipo
                    FROM vistafamiliaactivos a
                    INNER JOIN CTE_Padres p ON a.id = p.padre
                )
                SELECT DISTINCT id, nombre, padre, tipo FROM CTE_Padres;';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getBrothers($parentId, $excludeId)
    {
        try {
            $sql = "SELECT id, nombre, padre, tipo
                FROM vistafamiliaactivos
                WHERE padre = :parent_id
                  AND id <> :exclude_id
                ORDER BY nombre;";
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(':parent_id',  $this->sanetize($parentId));
            $stmt->bindValue(':exclude_id', $this->sanetize($excludeId));
            $stmt->execute();

            return $this->respuesta($stmt);
        } catch (Exception $e) {
            return [
                'error'   => true,
                'message' => 'Error al recuperar hermanos: ' . $e->getMessage()
            ];
        }
    }

    public function isAdmin($id)
    {
        $this->__construct("octopus_users");
        $sql = 'SELECT id,rol from users
        WHERE id = :id';
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($id));
        $stmt->execute();
        if ($this->respuesta($stmt)[0]['rol'] == "admin") {
            $respuesta = true;
        } else {
            $respuesta = false;
        }
        return $respuesta;
    }
}

class Normativas extends DbOperations
{
    public function getPreguntasNotUSF($idUSF)
    {
        $sql = "SELECT preguntas.id, preguntas.duda, preguntas.nivel
        FROM preguntas
        WHERE preguntas.id NOT IN (
            SELECT preguntas.id
            FROM preguntas
            LEFT JOIN marco ON preguntas.id = marco.id_preguntas
            WHERE marco.id_usf = :idUSF
        );";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idUSF', $this->sanetize($idUSF));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntasByUSF($idUSF)
    {
        $sql = "SELECT DISTINCT preguntas.id, preguntas.duda, preguntas.nivel
                FROM preguntas
                LEFT JOIN marco ON preguntas.id = marco.id_preguntas
                WHERE marco.id_usf = :idUSF;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idUSF', $this->sanetize($idUSF));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getUSFs()
    {
        $sql = "SELECT usf.*, proyectos.cod AS codigo_pac
                FROM usf
                JOIN proyectos ON usf.id_proyecto = proyectos.id;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPreguntas()
    {
        $sql = "SELECT * from preguntas;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function deleteUSF($idUSF)
    {
        $sql = "DELETE FROM usf WHERE id = :idUSF";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idUSF', $this->sanetize($idUSF));
        $stmt->execute();
    }

    public function getRelacionesPreguntaUSF($idPregunta)
    {
        $sql = "SELECT id_usf FROM marco where id_preguntas = :idPregunta group by id_usf;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idPregunta', $this->sanetize($idPregunta));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function newRelacionPreguntaControl($id_pregunta, $id_control, $id_usf)
    {
        $sql = "INSERT INTO marco (id_preguntas, id_ctrls, id_usf) VALUES (:id_pregunta, :id_control, :id_usf)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':id_pregunta', $this->sanetize($id_pregunta));
        $stmt->bindValue(':id_control', $this->sanetize($id_control));
        $stmt->bindValue(':id_usf', $this->sanetize($id_usf));
        $stmt->execute();
    }

    public function deletePregunta($idPregunta)
    {
        $sql = "DELETE FROM preguntas WHERE id = :idPregunta";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idPregunta', $this->sanetize($idPregunta));
        $stmt->execute();
    }

    public function deleteControl($idControl)
    {
        $sql = "DELETE FROM ctrls WHERE id = :idControl";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idControl', $this->sanetize($idControl));
        $stmt->execute();
    }

    public function deleteControlFromNormativa($idNormativa)
    {
        $sql = "DELETE FROM ctrls
                WHERE ctrls.id_normativa = :idNormativa;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idNormativa', $this->sanetize($idNormativa));
        $stmt->execute();
    }

    public function deleteNormativa($idNormativa)
    {
        $sql = "DELETE FROM normativas WHERE id = :idNormativa";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idNormativa', $this->sanetize($idNormativa));
        $stmt->execute();
    }

    public function deleteMarcoFromNormativa($idNormativa)
    {
        $sql = "DELETE FROM marco
                WHERE marco.id_ctrls IN (
                    SELECT id FROM ctrls WHERE ctrls.id_normativa = :idNormativa
                );";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idNormativa', $this->sanetize($idNormativa));
        $stmt->execute();
    }

    public function deleteRelacionMarcoNormativa($idRelacion)
    {
        $sql = "DELETE FROM marco WHERE id = :idRelacion";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':idRelacion', $this->sanetize($idRelacion));
        $stmt->execute();
    }

    public function getDominiosControlesUnicos()
    {
        $sql = "SELECT dominio FROM `ctrls` GROUP BY dominio ORDER BY dominio;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getUSFByCodigo($cod)
    {
        $sql = "SELECT usf.*, proyectos.cod AS codigo_pac
                FROM usf
                JOIN proyectos ON usf.id_proyecto = proyectos.id
                WHERE usf.cod = :cod;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':cod', $this->sanetize($cod));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function newUSF($codigo, $nombre, $descripcion, $dominio, $tipo, $idPAC)
    {
        $sql = "INSERT INTO usf (cod, nombre, descripcion, dominio, tipo, id_proyecto) VALUES (:codigo, :nombre, :descripcion, :dominio, :tipo, :id_proyecto)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':codigo', $this->sanetize($codigo));
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->bindValue(':descripcion', $this->sanetize($descripcion));
        $stmt->bindValue(':dominio', $this->sanetize($dominio));
        $stmt->bindValue(self::PUNTO_TIPO, $this->sanetize($tipo));
        $stmt->bindValue(':id_proyecto', $this->sanetize($idPAC));
        $stmt->execute();
    }

    public function newControl($codigo, $nombre, $descripcion, $dominio, $idNormativa)
    {
        $sql = "INSERT INTO ctrls (cod, nombre, descripcion, dominio, id_normativa) VALUES (:codigo, :nombre, :descripcion, :dominio, :idNormativa)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':codigo', $this->sanetize($codigo));
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->bindValue(':descripcion', $this->sanetize($descripcion));
        $stmt->bindValue(':dominio', $this->sanetize($dominio));
        $stmt->bindValue(':idNormativa', $this->sanetize($idNormativa));
        $stmt->execute();
    }

    public function getPreguntaByDuda($duda)
    {
        $sql = "SELECT * from preguntas WHERE duda = :duda";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':duda', $this->sanetize($duda));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function newPregunta($duda, $nivel)
    {
        $sql = "INSERT INTO preguntas (duda, nivel) VALUES (:duda, :nivel)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':duda', $this->sanetize($duda));
        $stmt->bindValue(':nivel', $this->sanetize($nivel));
        $stmt->execute();
    }

    public function editNormativa($nombreNormativa, $enabled, $idNormativa)
    {
        if ($enabled) {
            $enable = 1;
        } else {
            $enable = 0;
        }
        $sql = "UPDATE normativas SET nombre = :nombre, enabled = :enabled WHERE id = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombreNormativa));
        $stmt->bindValue(':enabled', $this->sanetize($enable));
        $stmt->bindValue(':id', $this->sanetize($idNormativa));
        $stmt->execute();
    }

    public function newNormativa($nombre, $version)
    {
        $sql = "INSERT INTO normativas (nombre, version) VALUES (:nombre, :version);";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
        $stmt->bindValue(':version', $this->sanetize($version));
        $stmt->execute();
    }

    public function getNormativas()
    {
        $sql = "SELECT * from normativas;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getControlesByNormID($normID)
    {
        $sql = "SELECT * from ctrls where id_normativa = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($normID));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getRelacionesControl($idControl)
    {
        $sql = "SELECT
                    ctrls.cod as codigo_control,
                    ctrls.nombre as nombre_control,
                    usf.cod as codigo_usf,
                    usf.nombre as nombre_usf,
                    preguntas.duda, marco.id,
                    marco.id_usf,
                    marco.id_ctrls,
                    marco.id_preguntas
                FROM marco
                join usf on marco.id_usf = usf.id
                join preguntas on marco.id_preguntas = preguntas.id
                join ctrls on marco.id_ctrls = ctrls.id
                where marco.id_ctrls = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($idControl));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getRelacionesPregunta($idPregunta)
    {
        $sql = "SELECT * FROM marco where id_preguntas = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($idPregunta));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getRelacionesUSF($idUSF)
    {
        $sql = "SELECT * FROM marco where id_usf = :id;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue('id', $this->sanetize($idUSF));
        $stmt->execute();
        return $this->respuesta($stmt);
    }
}

class Usuarios extends DbOperations
{
    public function setDateAccesoContinuidad($userId)
    {
        $fechaActual = gmdate('Y-m-d H:i:s');
        $sql = "INSERT INTO accesos (userid, continuidad_last_date)
                VALUES (:userId, :fechaActual)
                ON DUPLICATE KEY UPDATE
                    continuidad_last_date = :fechaActual;";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':userId', $this->sanetize($userId));
        $stmt->bindValue(':fechaActual', $this->sanetize($fechaActual));
        $stmt->execute();
    }

    public function getDateAcceso($userId)
    {
        $sql = "SELECT * from accesos where userid = :userId";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':userId', $this->sanetize($userId));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function authUser($email)
    {
        $stmt = $this->con->prepare("SELECT id,password, last_login from users WHERE email = :email;");
        $stmt->bindValue(':email', $this->sanetize($email));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getLastLoginByUser($id)
    {
        $stmt = $this->con->prepare("SELECT id,last_login from users WHERE id = :id;");
        $stmt->bindValue(':id', $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getUser($id)
    {
        $stmt = $this->con->prepare("SELECT
            users.id,
            users.email,
            GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ',') AS roles
        FROM users
        LEFT JOIN user_roles ON user_roles.user_id = users.id
        LEFT JOIN roles ON roles.id = user_roles.role_id
        WHERE users.id = :id
        GROUP BY users.id, users.email;");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['roles'] = $result['roles'] ? explode(',', $result['roles']) : null;
        }

        return array($result);
    }

    public function getTokensUser($id)
    {
        $stmt = $this->con->prepare("SELECT tokens.id,tokens.name,tokens.created, tokens.expired
                                    FROM tokens
                                    INNER JOIN user_tokens ON token_id = tokens.id
                                    INNER JOIN users ON user_id = users.id
                                    WHERE users.id = :id;");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function deleteTokenUser($user, $tokenId)
    {
        $stmt = $this->con->prepare("DELETE tokens FROM tokens
                                    INNER JOIN user_tokens ON token_id = tokens.id
                                    INNER JOIN users ON user_id = users.id
                                    WHERE users.id = :user AND tokens.id = :tokenId;");
        $stmt->bindValue(':user', $user, PDO::PARAM_INT);
        $stmt->bindValue(':tokenId', $tokenId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function createTokensUser($id, $name, $expired)
    {
        $randomBytes = random_bytes(26);
        $token = base64_encode($randomBytes);
        $token = str_replace(['+', '/', '='], '', $token);
        $tokenDecripted = "11_" . $token;
        $expired = date('Y-m-d H:i:s', $expired);
        $tokenclass = new TokenBearerEncryptor();
        $token = $tokenclass->encryptBearer($tokenDecripted);

        $sql = "INSERT INTO tokens (name, expired, hash) VALUES (:name, :expired, :hash)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':name', $this->sanetize($name));
        $stmt->bindValue(':expired', $this->sanetize($expired));
        $stmt->bindValue(':hash', $this->sanetize($token));
        $stmt->execute();
        $token_id = $this->con->lastInsertId();

        $sql = "INSERT INTO user_tokens (user_id, token_id) VALUES (:user_id, :token_id)";
        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':user_id', $this->sanetize($id));
        $stmt->bindValue(':token_id', $this->sanetize($token_id));
        $stmt->execute();
        return $tokenDecripted;
    }

    public function getUserByToken($token)
    {
        $tokenclass = new TokenBearerEncryptor();
        $token = $tokenclass->encryptBearer($token);
        $stmt = $this->con->prepare("SELECT users.id, users.email, tokens.expired
                                    FROM users
                                    INNER JOIN user_tokens ON user_tokens.user_id = users.id
                                    INNER JOIN tokens ON tokens.id = user_tokens.token_id
                                    WHERE tokens.hash = :token;");
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getUsers()
    {
        $stmt = $this->con->prepare("SELECT users.id, users.email, roles.id as role_id, roles.name as role, roles.color as role_color
                                     FROM users
                                     LEFT JOIN user_roles ON user_roles.user_id = users.id
                                     LEFT JOIN roles ON roles.id = user_roles.role_id
                                     ORDER BY users.id ASC;");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $users = [];
        foreach ($result as $row) {
            $userId = $row['id'];
            if (!isset($users[$userId])) {
                $users[$userId] = [
                    'id' => $userId,
                    'email' => $row['email'],
                    'roles' => []
                ];
            }
            $users[$userId]['roles'][] = [
                'id' => $row['role_id'],
                'name' => $row['role'],
                'color' => $row['role_color']
            ];
        }

        return array_values($users);
    }

    public function getEmailsByEndpoint($endpointName)
    {
        $sql = "SELECT DISTINCT u.email
        FROM endpoints e
        JOIN role_endpoints re ON re.endpoint_id = e.id
        JOIN roles r ON r.id = re.role_id
        JOIN user_roles ur ON ur.role_id = r.id
        JOIN users u ON u.id = ur.user_id
        WHERE e.route = :endpointName;";

        $stmt = $this->con->prepare($sql);
        $stmt->bindValue(':endpointName', $endpointName, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


    public function newUser($email, $auth_pass = null, $roles = [])
    {
        try {
            if ($auth_pass == null) {
                $auth_pass = randomPassword();
                $response["authPass"] = $auth_pass;
            }
            $response[ERROR] = false;
            $hash = password_hash($auth_pass, PASSWORD_BCRYPT, ["cost" => 11]);
            $stmt = $this->con->prepare("INSERT INTO users (email, password) VALUES (:email, :password);");
            $stmt->bindValue("email", $this->sanetize($email));
            $stmt->bindValue("password", $this->sanetize($hash));
            $stmt->execute();

            // Obtener el ID del usuario recién creado
            $userId = $this->con->lastInsertId();

            // Si no se pasan roles, buscar el ID del rol "Guest"
            if (empty($roles)) {
                $stmt = $this->con->prepare("SELECT id FROM roles WHERE name = 'Guest';");
                $stmt->execute();
                $guestRoleId = $stmt->fetchColumn();
                if ($guestRoleId) {
                    $roles[] = $guestRoleId;
                }
            }

            // Insertar cada rol en la tabla user_roles
            foreach ($roles as $roleId) {
                $stmt = $this->con->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id);");
                $stmt->bindValue("user_id", $this->sanetize($userId));
                $stmt->bindValue("role_id", $this->sanetize($roleId));
                $stmt->execute();
            }
        } catch (Exception $e) {
            $response[MESSAGE] = "Ha ocurrido un error inesperado, código: " . $e->getCode();
            if ($e->getCode() == '23000') {
                $response[MESSAGE] = "Ese correo ya está dado de alta.";
            }
            $response[ERROR] = true;
        }
        return $response;
    }

    public function newRol($nombre, $color, $additionalAccess)
    {
        if (!$additionalAccess) {
            $additionalAccess = 0;
        } else {
            $additionalAccess = 1;
        }
        $response = [];
        try {
            $stmt = $this->con->prepare("INSERT INTO roles (name, color, additional_access) VALUES (:nombre, :color, :additionalAccess);");
            $stmt->bindValue(self::PUNTO_NOMBRE, $this->sanetize($nombre));
            $stmt->bindValue(":color", $this->sanetize($color));
            $stmt->bindValue(":additionalAccess", $this->sanetize($additionalAccess));
            $stmt->execute();
            $response[ERROR] = false;
        } catch (Exception $e) {
            $response[ERROR] = true;
            if ($e->getCode() == '23000') {
                $response[MESSAGE] = "Ese rol ya existe.";
            } else {
                $response[MESSAGE] = $e->getMessage();
            }
        }
        return $response;
    }

    public function getCardsHomeByUser($id)
    {
        $stmt = $this->con->prepare("SELECT title,cards_home.description,position,img,endpoints.route AS url
                                    FROM octopus_new.cards_home
                                    INNER JOIN octopus_users.role_endpoints ON octopus_users.role_endpoints.endpoint_id = cards_home.endpoint_id
                                    INNER JOIN octopus_users.endpoints ON endpoints.id = cards_home.endpoint_id
                                    INNER JOIN octopus_users.user_roles ON user_roles.role_id = role_endpoints.role_id
                                    WHERE user_roles.user_id = :id
                                    GROUP BY url,img,position,description,title
                                    ORDER BY cards_home.position ASC;");
        $stmt->bindValue(":id", $id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function editRol($id, $additionalAccess, $nombre = null, $color = null)
    {
        $response = [];
        try {
            // Verificar si el rol es editable
            $rol = $this->getRoleById($id);

            if ($rol && $rol['editable'] == 1) {
                // Construir la sentencia SQL para actualizar el rol
                $sql = "UPDATE roles SET additional_access = :additionalAccess, ";
                $params = [];
                if ($nombre !== null) {
                    $sql .= "name = :nombre, ";
                    $params[self::PUNTO_NOMBRE] = $this->sanetize($nombre);
                }
                if ($color !== null) {
                    $sql .= "color = :color, ";
                    $params[':color'] = $this->sanetize($color);
                }
                $sql = rtrim($sql, ", ") . " WHERE id = :id";
                $params[':additionalAccess'] = $this->sanetize($additionalAccess);

                $params[':id'] = $this->sanetize($id);

                // Preparar y ejecutar la sentencia SQL
                $stmt = $this->con->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();

                $response[ERROR] = false;
                $response[MESSAGE] = "Rol actualizado exitosamente.";
            } else {
                $response[ERROR] = true;
                $response[MESSAGE] = "El rol no es editable.";
            }
        } catch (Exception $e) {
            $response[ERROR] = true;
            $response[MESSAGE] = $e->getMessage();
        }
        return $response;
    }

    public function deleteRol($id)
    {
        $response = [];
        try {
            // Verificar si el rol es eliminable
            $rol = $this->getRoleById($id);

            if ($rol && $rol['deletable'] == 1) {
                // Construir la sentencia SQL para eliminar el rol
                $stmt = $this->con->prepare("DELETE FROM roles WHERE id = :id");
                $stmt->bindValue(":id", $this->sanetize($id));
                $stmt->execute();

                $response[ERROR] = false;
                $response[MESSAGE] = "Rol eliminado exitosamente.";
            } else {
                $response[ERROR] = true;
                $response[MESSAGE] = "El rol no es eliminable.";
            }
        } catch (Exception $e) {
            $response[ERROR] = true;
            $response[MESSAGE] = $e->getMessage();
        }
        return $response;
    }

    public function editUser($id, $roles)
    {
        $usuario = $this->getUser($id);
        $columns = "";
        if (isset($usuario[0])) {
            // Convertir los elementos del array $roles a enteros
            $roles = array_map('intval', $roles);

            // Obtener roles actuales del usuario
            $stmt = $this->con->prepare("SELECT role_id FROM user_roles WHERE user_id = :id");
            $stmt->bindValue("id", $this->sanetize($id));
            $stmt->execute();
            $currentRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Roles a eliminar
            $rolesToDelete = array_diff($currentRoles, $roles);
            if (!empty($rolesToDelete)) {
                $stmt = $this->con->prepare("DELETE FROM user_roles WHERE user_id = :id AND role_id IN (" . implode(',', array_map('intval', $rolesToDelete)) . ")");
                $stmt->bindValue("id", $this->sanetize($id));
                $stmt->execute();
            }

            // Roles a añadir
            $rolesToAdd = array_diff($roles, $currentRoles);
            if (!empty($rolesToAdd)) {
                $placeholders = [];
                $params = ['id' => $this->sanetize($id)];
                foreach ($rolesToAdd as $index => $roleId) {
                    $placeholder = "(:id, :role_id_$index)";
                    $placeholders[] = $placeholder;
                    $params["role_id_$index"] = $roleId;
                }
                $stmt = $this->con->prepare("INSERT INTO user_roles (user_id, role_id) VALUES " . implode(',', $placeholders));
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                }
                $stmt->execute();
            }

            $columns = trim($columns, ',');
            if (!empty($columns)) {
                $stmt = $this->con->prepare("UPDATE users SET $columns WHERE id = :id;");
                $stmt->bindValue("id", $this->sanetize($id));
                $stmt->execute();
                return $this->respuesta($stmt);
            }
        }
    }

    public function editEndpointsByRole($rol, $endpoints, $allow)
    {
        $response = [];
        try {
            if (!$allow) {
                $stmt = $this->con->prepare("DELETE FROM role_endpoints WHERE role_id = :role AND endpoint_id IN (" . implode(',', array_map('intval', $endpoints)) . ")");
                $stmt->bindValue("role", $this->sanetize($rol));
                $stmt->execute();
            } else {
                // Insertar los nuevos endpoints asignados al rol
                $placeholders = [];
                $params = ['role_id' => $rol];
                foreach ($endpoints as $index => $endpoint) {
                    $placeholder = "(:role_id, :endpoint_id_$index)";
                    $placeholders[] = $placeholder;
                    $params["endpoint_id_$index"] = $endpoint;
                }

                // Usar INSERT IGNORE para evitar errores de duplicados
                $stmt = $this->con->prepare("INSERT IGNORE INTO role_endpoints (role_id, endpoint_id) VALUES " . implode(',', $placeholders));
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                }
                $stmt->execute();
            }
            $response[ERROR] = false;
            $response[MESSAGE] = "Endpoints actualizados exitosamente.";
        } catch (Exception $e) {
            $response[ERROR] = true;
            $response[MESSAGE] = $e->getMessage();
        }
        return $response;
    }

    public function delUser($id)
    {
        $stmt = $this->con->prepare("DELETE from users WHERE id = :id;");
        $stmt->bindValue("id", $this->sanetize($id));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getRolesByPath($route)
    {
        $stmt = $this->con->prepare("SELECT role_id,endpoint_id,route,method,description,name as role_name FROM `role_endpoints`
        inner join endpoints on endpoints.id = role_endpoints.endpoint_id
        inner join roles on roles.id = role_endpoints.role_id WHERE route = :path;");
        $stmt->bindValue("path", $this->sanetize($route));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getEndpointsByRole($role, $includeAll = false)
    {
        if ($includeAll) {
            // Consulta para obtener todos los endpoints y marcar los asignados al rol
            $query = "SELECT e.id as endpoint_id, e.route, e.method, e.description, e.tags,
                      CASE WHEN re.role_id IS NOT NULL THEN 1 ELSE 0 END AS assigned
                      FROM endpoints e
                      LEFT JOIN role_endpoints re ON e.id = re.endpoint_id AND re.role_id = :role
                      LEFT JOIN roles r ON r.id = re.role_id";
        } else {
            // Consulta para obtener solo los endpoints asignados al rol
            $query = "SELECT re.role_id, e.id as endpoint_id, e.route, e.method, e.description, e.tags
                      FROM role_endpoints re
                      INNER JOIN endpoints e ON e.id = re.endpoint_id
                      INNER JOIN roles r ON r.id = re.role_id
                      WHERE re.role_id = :role";
        }
        $stmt = $this->con->prepare($query);
        $stmt->bindValue("role", $this->sanetize($role));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getEndpoints()
    {
        $stmt = $this->con->prepare("SELECT id,route,method,description,tags, added FROM endpoints;");
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getRoles()
    {
        $sql = "SELECT id, name, color,deletable,editable,additional_access FROM roles";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoleById($id)
    {
        $stmt = $this->con->prepare("SELECT id, name, color,deletable,editable FROM roles WHERE id = :id");
        $stmt->bindValue(":id", $this->sanetize($id));
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getRoleByName($name)
    {
        $stmt = $this->con->prepare("SELECT id, name, color,deletable,editable, additional_access FROM roles WHERE name = :name");
        $stmt->bindValue(":name", $this->sanetize($name));
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getRolesByUser($user)
    {
        $stmt = $this->con->prepare("SELECT user_id,role_id,name as role FROM `user_roles`
        inner join roles on roles.id = user_roles.role_id WHERE user_id = :user;");
        $stmt->bindValue("user", $this->sanetize($user));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function insertOrUpdateRoute($route, $method, $description, $tags)
    {
        try {
            $stmt = $this->con->prepare("INSERT INTO endpoints (route, method, description, tags, added)
                                  VALUES (:route, :method, :description, :tags, NOW())
                                  ON DUPLICATE KEY UPDATE description = :description, tags = :tags;");
            $stmt->bindValue("route", $route);
            $stmt->bindValue("method", $method);
            $stmt->bindValue("description", $description);
            $stmt->bindValue("tags", $tags);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al insertar o actualizar ruta: " . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Error al insertar o actualizar la ruta en la base de datos: ' . $e->getCode()
            ];
        }
    }

    public function getEndpointsByRoute($route)
    {
        $stmt = $this->con->prepare("SELECT id,route,method,description,updated,added FROM `endpoints` WHERE route = :route;");
        $stmt->bindValue("route", $this->sanetize($route));
        $stmt->execute();
        return $this->respuesta($stmt);
    }

    public function getPathsByRole($roles)
    {
        // Generar una lista de placeholders para los roles
        $placeholders = implode(',', array_fill(0, count($roles), '?'));

        // Preparar la sentencia SQL
        $stmt = $this->con->prepare("SELECT *
            FROM `role_endpoints`
            INNER JOIN endpoints ON endpoints.id = role_endpoints.endpoint_id
            INNER JOIN roles ON roles.id = role_endpoints.role_id
            WHERE roles.id IN ($placeholders);");

        // Enlazar los parámetros
        foreach ($roles as $index => $role) {
            $stmt->bindValue($index + 1, $role, PDO::PARAM_INT);
        }

        // Ejecutar la sentencia
        $stmt->execute();
        return $this->respuesta($stmt);
    }
}
