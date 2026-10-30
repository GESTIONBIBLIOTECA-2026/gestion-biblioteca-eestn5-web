<?php
/* ==========================================================================
   db.php - Conexión a MySQL (Aiven) con PDO
   Los Models usan consultar(sql, [valores]) y ejecutar(sql, [valores]).
   Es el reemplazo de mysql2: mismos comodines "?" (sentencias preparadas).

   ¿Y el pool? En Node el servidor queda prendido y reparte un pool de
   conexiones entre todos los usuarios. En PHP cada petición es un proceso
   corto: abre UNA conexión la primera vez que la necesita y la cierra sola
   cuando termina la respuesta.
   ========================================================================== */

require_once __DIR__ . '/../config/config.php';

// Constantes de PDO para MySQL (PHP 8.4+ las tiene en Pdo\Mysql; antes, en PDO::MYSQL_*)
function atributoMysql(string $nombre): int
{
    return defined("Pdo\\Mysql::$nombre") ? constant("Pdo\\Mysql::$nombre") : constant("PDO::MYSQL_$nombre");
}

// 1. La conexión (se crea una sola vez por petición y se reutiliza)
function conexion(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // los errores de SQL lanzan excepción (→ catch del Controller)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // cada fila es ['columna' => valor]
        PDO::ATTR_EMULATE_PREPARES => false,                // sentencias preparadas REALES en MySQL
        atributoMysql('ATTR_FOUND_ROWS') => true            // "filas afectadas" = filas encontradas (igual que mysql2)
    ];

    // 2. SSL: con el ca.pem de Aiven se valida el servidor; sin él, va cifrado igual
    if (DB['ssl']) {
        $tieneCa = DB['caPath'] !== '' && is_file(DB['caPath']);
        $opciones[atributoMysql('ATTR_SSL_CA')] = $tieneCa ? DB['caPath'] : '';
        if (!$tieneCa) $opciones[atributoMysql('ATTR_SSL_VERIFY_SERVER_CERT')] = false;
    }

    // 3. Los datos salen SOLO del .env
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB['host'], DB['port'], DB['database']);
    $pdo = new PDO($dsn, DB['user'], DB['password'], $opciones);

    // 4. La conexión usa la hora de Argentina (NOW(), CURRENT_DATE)
    $pdo->prepare('SET time_zone = ?')->execute([DB['zonaHoraria']]);

    return $pdo;
}

// Prepara la sentencia y engancha cada "?" con su valor, en orden (como mysql2)
function preparar(string $sql, array $valores): PDOStatement
{
    $sentencia = conexion()->prepare($sql);

    foreach (array_values($valores) as $i => $valor) {
        if (is_bool($valor)) $valor = (int) $valor;            // TRUE / FALSE → 1 / 0
        $tipo = match (true) {
            $valor === null => PDO::PARAM_NULL,
            is_int($valor) => PDO::PARAM_INT,                  // los números van como número (LIMIT ?, OFFSET ?)
            default => PDO::PARAM_STR
        };
        $sentencia->bindValue($i + 1, is_float($valor) ? (string) $valor : $valor, $tipo);
    }

    $sentencia->execute();
    return $sentencia;
}

// 5. SELECT → lista de filas. SUM() llega como número (no como texto "3")
function consultar(string $sql, array $valores = []): array
{
    $sentencia = preparar($sql, $valores);

    // Columnas DECIMAL (SUM, COALESCE de SUM): PDO las da como texto, se pasan a número
    $decimales = [];
    for ($i = 0; $i < $sentencia->columnCount(); $i++) {
        $meta = $sentencia->getColumnMeta($i);
        if (($meta['native_type'] ?? '') === 'NEWDECIMAL') $decimales[] = $meta['name'];
    }

    $filas = $sentencia->fetchAll();
    if ($decimales) {
        foreach ($filas as &$fila) {
            foreach ($decimales as $columna) {
                if (is_string($fila[$columna])) $fila[$columna] = $fila[$columna] + 0;
            }
        }
    }
    return $filas;
}

// 6. INSERT / UPDATE / DELETE → ['insertId' => ..., 'affectedRows' => ...] (mismos nombres que mysql2)
function ejecutar(string $sql, array $valores = []): array
{
    $sentencia = preparar($sql, $valores);
    return ['insertId' => (int) conexion()->lastInsertId(), 'affectedRows' => $sentencia->rowCount()];
}

// 7. Transacción: varias consultas que se guardan TODAS juntas o NINGUNA (préstamos, stock).
//    Como la petición usa una sola conexión, todo lo que hace $trabajo entra en la transacción.
function conTransaccion(callable $trabajo): mixed
{
    $pdo = conexion();
    $pdo->beginTransaction();
    try {
        $resultado = $trabajo();
        $pdo->commit();
        return $resultado;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
