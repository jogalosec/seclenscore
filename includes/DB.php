<?php
class DB
{

    protected $link = null;
    private $file = null;

    public function __construct(string $dbname, string $file = "config.ini", string $encType = "utf8mb4")
    {
        if (!$settings = parse_ini_file($file, true)) {
            throw new InvalidArgumentException(sprintf("Failed to parse config file: %s", $file));
        }
        $this->file = $file;

        $driver = $settings["db"]["driver"];
        if (in_array($driver, PDO::getAvailableDrivers(), true)) {

            // Establecer el nombre de la base de datos predeterminada si no está establecido
            $dbname = $dbname ?? $settings['db']['database'];

            // Configurar opciones predeterminadas de PDO
            $opts = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_PERSISTENT => true
            ];

            // Configurar el puerto si está definido en la configuración
            $port = !empty($settings['db']['port']) ? (';port=' . $settings['db']['port']) : '';

            // Construir DSN
            $dsn = sprintf('%s:host=%s%s;dbname=%s', $driver, $settings['db']['host'], $port, $dbname);

            // Configurar opciones adicionales si SSL está habilitado
            if ($settings['db']['ssl']) {
                $opts[PDO::MYSQL_ATTR_SSL_CA] = 'certCaBD.pem';
                $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                // Intentar conectar a la base de datos
                $this->link = new PDO($dsn, $settings['db']['user'], $settings['db']['pass'], $opts);

                // Utilizar UTF8
                $this->link->exec("SET NAMES $encType");
            } catch (PDOException $e) {
                error_log($e->getMessage());

                // Volver a intentar con una nueva conexión si la conexión se ha cerrado
                if ($e->getCode() == 'HY000' && (strpos($e->getMessage(), 'server has gone away') !== false || strpos($e->getMessage(), 'Connection reset by peer') !== false)) {
                    // Crear una nueva conexión
                    $this->link = new PDO($dsn, $settings['db']['user'], $settings['db']['pass'], $opts);
                    $this->link->exec("SET NAMES $encType");
                } else {
                    throw $e;
                }
            }
        } else {
            throw new PDOException("Unable to find database driver: $driver", 001);
        }
    }

    /**
     * Returns the PDO object created
     *
     * @return PDO
     */
    public function getPDO()
    {
        return $this->link;
    }
    /**
     * Returns the file path that loads
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Insert data into given table using array[$key] = $value format
     * $key is the field name and $value is that fields value
     *
     * @param string $table
     * @param array $data
     * @return bool
     */
    public function insertIntoTable(array $data, string $table): bool
    {
        // Get columns and set pdo like insert values
        foreach ($data as $key => $value) {
            $keys[] = $key;
        }
        $columns = implode(", ", $keys);
        $pdoKeys = implode(", :", $keys);
        $pdoKeys = ":" . $pdoKeys;
        // Build Query
        $query = "INSERT INTO $table ($columns) VALUES ($pdoKeys)";
        try {
            $stmt = $this->link->prepare($query);
            $stmt->execute($data);
            return true;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw $e;
        }
        return false;
    }
    /**
     * Update values in table
     *
     * @param array $data
     * @param integer $whereID
     * @param string $table
     * @return boolean
     */
    public function updateTableFields(array $data, int $whereID, string $table): bool
    {
        // Get columns and set pdo like insert values
        foreach ($data as $key => $value) {
            $keys[] = $key . "=:" . $key;
        }
        $builtParams = implode(", ", $keys);
        // Build Query
        $query = "UPDATE $table SET $builtParams WHERE ID=$whereID";
        try {
            $stmt = $this->link->prepare($query);
            $stmt->execute($data);
            return true;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw $e;
        }
        return false;
    }
    /**
     * Returns true if it finds needle in the haystack.
     *
     * @param mixed $needle
     * @param mixed $haystack
     * @param string $table
     * @return mixed
     */
    public function checkIfExistsInTable($value, $column, string $table)
    {
        if (is_array($value) && is_array($column)) {
            if (count($value) == count($column)) {
                // Create dynamic query
                $query = "SELECT COUNT(*) FROM $table WHERE ";
                foreach ($column as $col) {
                    $query .= "$col = ? AND ";
                }
                $query = rtrim($query, 'AND ');
                // Prepare and execute dynamic query
                $stmt = $this->link->prepare($query);
                $stmt->execute($value);
            }
        } else {
            $query = "SELECT COUNT(*) FROM $table WHERE $column=:value";
            $stmt = $this->link->prepare($query);
            $stmt->bindParam(":value", $value);
            $stmt->execute();
        }
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        return false;
    }
    /**
     * Returns true if table exists else false.
     *
     * @param string $table
     * @return boolean
     */
    public function checkIfTableExists(string $table): bool
    {
        $result = $this->link->query("SHOW TABLES LIKE '$table'")->fetchObject();
        return empty($result) !== true;
    }
}
