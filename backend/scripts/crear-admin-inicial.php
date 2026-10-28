<?php
/* ==========================================================================
   crear-admin-inicial.php - Crea el PRIMER administrador (base vacía)
   Uso (desde backend/, en una terminal):  php scripts/crear-admin-inicial.php
   Las cuentas siguientes se crean desde la plataforma.
   ========================================================================== */

require_once __DIR__ . '/../src/database/db.php';
require_once __DIR__ . '/../src/middlewares/predicados.php';

function preguntarTexto(string $mensaje): string
{
    echo $mensaje;
    return trim((string) fgets(STDIN));
}

// Pide la contraseña sin mostrarla en pantalla
function preguntarSecreto(string $mensaje): string
{
    echo $mensaje;

    // Windows: PowerShell la lee oculta
    if (PHP_OS_FAMILY === 'Windows') {
        $comando = 'powershell -NoProfile -Command "$c = Read-Host -AsSecureString; '
            . '[Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($c))"';
        $clave = shell_exec($comando);
        if ($clave !== null && $clave !== false) return rtrim($clave, "\r\n");
    } else {
        // Linux / Mac: se apaga el eco de la terminal mientras se escribe
        shell_exec('stty -echo 2>/dev/null');
        $clave = trim((string) fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        echo "\n";
        return $clave;
    }

    echo "(no se pudo ocultar: la contraseña se va a ver mientras la escribís)\n";
    return trim((string) fgets(STDIN));
}

function validarDatos(array $d): string
{
    $dni = limpiarDni($d['dni']);
    if (!esDniValido($dni)) throw new RuntimeException('El DNI debe tener entre 7 y 8 números.');

    $largo = mb_strlen($d['nombre']);
    if ($largo < 3 || $largo > 120 || !esNombreValido($d['nombre'])) {
        throw new RuntimeException('Ingresá un nombre válido de hasta 120 caracteres.');
    }
    if (mb_strlen($d['correo']) > 120 || !esCorreoValido($d['correo'])) {
        throw new RuntimeException('Ingresá un correo electrónico válido de hasta 120 caracteres.');
    }
    if (mb_strlen($d['telefono']) > 30 || !preg_match('/^[\d\s()+-]+$/D', $d['telefono']) || strlen(preg_replace('/\D/', '', $d['telefono'])) < 6) {
        throw new RuntimeException('Ingresá un teléfono válido con al menos 6 números.');
    }
    $largo = mb_strlen($d['direccion']);
    if ($largo < 3 || $largo > 150) throw new RuntimeException('La dirección debe tener entre 3 y 150 caracteres.');

    $largo = mb_strlen($d['clave']);
    if ($largo < 12 || $largo > 72) throw new RuntimeException('La contraseña debe tener entre 12 y 72 caracteres.');

    return $dni;
}

try {
    $rolAdmin = consultar("SELECT id FROM roles WHERE nombre = 'administrador' LIMIT 1")[0]['id'] ?? null;
    if (!$rolAdmin) throw new RuntimeException('No existe el rol "administrador". Ejecutá primero: php scripts/inicializar-bd.php');

    $hayAdmin = consultar("SELECT id FROM usuarios WHERE rol_id = ? AND estado <> 'baja' LIMIT 1", [$rolAdmin]);
    if ($hayAdmin) throw new RuntimeException('Ya existe un administrador activo; creá las cuentas siguientes desde la plataforma.');

    $datos = [
        'dni' => preguntarTexto('DNI: '),
        'nombre' => preguntarTexto('Nombre completo: '),
        'correo' => mb_strtolower(preguntarTexto('Correo: ')),
        'telefono' => preguntarTexto('Teléfono: '),
        'direccion' => preguntarTexto('Dirección: ')
    ];

    $datos['clave'] = preguntarSecreto('Contraseña (12 a 72 caracteres): ');
    if (preguntarSecreto('Repetí la contraseña: ') !== $datos['clave']) {
        throw new RuntimeException('Las contraseñas no coinciden.');
    }

    $dni = validarDatos($datos);

    ejecutar(
        "INSERT INTO usuarios (dni, nombre_completo, correo, telefono, direccion, password_hash, rol_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$dni, $datos['nombre'], $datos['correo'], $datos['telefono'], $datos['direccion'],
         password_hash($datos['clave'], PASSWORD_BCRYPT, ['cost' => 12]), $rolAdmin]
    );

    echo "Administrador inicial creado. Ya podés iniciar sesión en la plataforma.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
