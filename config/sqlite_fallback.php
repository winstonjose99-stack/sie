<?php
/**
 * Adaptador compatible con mysqli sobre PDO SQLite (/tmp/sistema_asistencias.sqlite).
 * Permite que el proyecto funcione automáticamente en Vercel Serverless sin servidor MySQL externo,
 * precargando los mismos datos de la base de datos local de XAMPP.
 */

class SQLiteResultCompat {
    private array $rows = [];
    private int $pos = 0;
    public int $num_rows = 0;

    public function __construct(array $rows) {
        $this->rows     = array_values($rows);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc(): ?array {
        if ($this->pos >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->pos++];
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array {
        return $this->rows;
    }
}

class SQLiteStmtCompat {
    private PDO $pdo;
    private string $sql;
    private array $params = [];
    private ?SQLiteResultCompat $lastResult = null;
    private array $boundResultVars = [];
    private $onWriteCallback = null;
    public int $num_rows = 0;
    public int $insert_id = 0;
    public int $errno = 0;
    public string $error = '';

    public function __construct(PDO $pdo, string $sql, ?callable $onWriteCallback = null) {
        $this->pdo = $pdo;
        $this->sql = $sql;
        $this->onWriteCallback = $onWriteCallback;
    }

    public function bind_param(string $types, &...$vars): bool {
        $this->params = [];
        foreach ($vars as $v) {
            $this->params[] = $v;
        }
        return true;
    }

    public function execute(): bool {
        $this->errno = 0;
        $this->error = '';
        try {
            $stmt = $this->pdo->prepare($this->sql);
            $ok = $stmt->execute($this->params);
            if (!$ok) {
                $info = $stmt->errorInfo();
                $this->errno = (int) ($info[1] ?? 1);
                $this->error = (string) ($info[2] ?? 'Error en consulta SQLite');
                return false;
            }

            if (preg_match('/^\s*(SELECT|PRAGMA)/i', $this->sql)) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->lastResult = new SQLiteResultCompat($rows ?: []);
                $this->num_rows   = $this->lastResult->num_rows;
            } else {
                $this->insert_id  = (int) $this->pdo->lastInsertId();
                $this->lastResult = new SQLiteResultCompat([]);
                $this->num_rows   = 0;
                if ($this->onWriteCallback !== null) {
                    ($this->onWriteCallback)($this->sql, $this->params);
                }
            }
            return true;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $this->error = $msg;
            if (stripos($msg, 'UNIQUE constraint failed') !== false) {
                $this->errno = 1062;
            } elseif (stripos($msg, 'FOREIGN KEY constraint failed') !== false) {
                $this->errno = 1451;
            } else {
                $this->errno = (int) $e->getCode() ?: 1;
            }
            return false;
        }
    }

    public function get_result(): SQLiteResultCompat {
        return $this->lastResult ?? new SQLiteResultCompat([]);
    }

    public function store_result(): bool {
        if ($this->lastResult !== null) {
            $this->num_rows = $this->lastResult->num_rows;
        }
        return true;
    }

    public function bind_result(&...$vars): bool {
        $this->boundResultVars = &$vars;
        return true;
    }

    public function fetch(): ?bool {
        if ($this->lastResult === null) {
            return null;
        }
        $row = $this->lastResult->fetch_assoc();
        if ($row === null) {
            return null;
        }
        $values = array_values($row);
        foreach ($this->boundResultVars as $i => &$varRef) {
            $varRef = $values[$i] ?? null;
        }
        return true;
    }

    public function close(): bool {
        return true;
    }
}

class SQLiteMysqliCompat {
    private PDO $pdo;
    private array $journal = [];
    public ?string $connect_error = null;
    public int $errno = 0;
    public string $error = '';
    public int $insert_id = 0;

    public function __construct(string $dbPath) {
        $isNew = !file_exists($dbPath) || filesize($dbPath) === 0;
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = OFF;');

        if ($isNew) {
            $this->initializeSchemaAndSeed();
        }
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS _sync_meta (id TEXT PRIMARY KEY);');
        $this->replayCookieJournal();
    }

    private function replayCookieJournal(): void {
        if (empty($_COOKIE['SIE_DB_SYNC'])) {
            return;
        }
        $raw = @base64_decode((string) $_COOKIE['SIE_DB_SYNC'], true);
        if (!$raw) {
            return;
        }
        $json = @gzinflate($raw);
        if (!$json) {
            return;
        }
        $entries = json_decode($json, true);
        if (!is_array($entries)) {
            return;
        }
        $this->journal = $entries;
        foreach ($entries as $entry) {
            if (empty($entry['id']) || empty($entry['sql'])) {
                continue;
            }
            $chk = $this->pdo->prepare('SELECT 1 FROM _sync_meta WHERE id = ?');
            $chk->execute([$entry['id']]);
            if ($chk->fetchColumn()) {
                continue;
            }
            try {
                $st = $this->pdo->prepare($entry['sql']);
                $st->execute($entry['params'] ?? []);
                $ins = $this->pdo->prepare('INSERT OR IGNORE INTO _sync_meta (id) VALUES (?)');
                $ins->execute([$entry['id']]);
            } catch (Throwable $e) {
                // Ignorar si ya fue aplicado
            }
        }
    }

    public function recordWrite(string $sql, array $params = []): void {
        $id = substr(md5($sql . serialize($params) . microtime(true)), 0, 12);
        try {
            $ins = $this->pdo->prepare('INSERT OR IGNORE INTO _sync_meta (id) VALUES (?)');
            $ins->execute([$id]);
        } catch (Throwable $e) {}

        $this->journal[] = [
            'id'     => $id,
            'sql'    => $sql,
            'params' => $params,
        ];
        if (count($this->journal) > 25) {
            $this->journal = array_slice($this->journal, -25);
        }
        if (!headers_sent()) {
            $compressed = base64_encode((string) gzdeflate((string) json_encode($this->journal, JSON_UNESCAPED_UNICODE), 9));
            if (strlen($compressed) < 3800) {
                setcookie('SIE_DB_SYNC', $compressed, [
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
    }

    public function set_charset(string $charset): bool {
        return true;
    }

    public function query(string $sql) {
        $this->errno = 0;
        $this->error = '';
        try {
            if (preg_match('/^\s*(SELECT|PRAGMA)/i', $sql)) {
                $stmt = $this->pdo->query($sql);
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                return new SQLiteResultCompat($rows ?: []);
            }
            $this->pdo->exec($sql);
            $this->insert_id = (int) $this->pdo->lastInsertId();
            $this->recordWrite($sql, []);
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = (int) $e->getCode() ?: 1;
            return false;
        }
    }

    public function prepare(string $sql): SQLiteStmtCompat {
        return new SQLiteStmtCompat($this->pdo, $sql, function (string $s, array $p): void {
            $this->recordWrite($s, $p);
        });
    }

    public function begin_transaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollback(): bool {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return false;
    }

    public function close(): bool {
        return true;
    }

    private function initializeSchemaAndSeed(): void {
        $ddl = <<<'SQL'
CREATE TABLE IF NOT EXISTS registro (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario TEXT NOT NULL UNIQUE,
    contrasena TEXT NOT NULL,
    correo TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS periodos_academicos (
    id_periodo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_periodo TEXT NOT NULL,
    activo INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS instituciones (
    id_institucion INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    direccion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS docentes (
    id_docente INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    apellido TEXT NOT NULL,
    cedula TEXT NOT NULL,
    correo TEXT NOT NULL,
    telefono TEXT NOT NULL,
    id_institucion INTEGER NOT NULL,
    FOREIGN KEY (id_institucion) REFERENCES instituciones(id_institucion) ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS materias (
    id_materia INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_materia TEXT NOT NULL,
    id_docente INTEGER NOT NULL,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS secciones (
    id_seccion INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_seccion TEXT NOT NULL,
    id_institucion INTEGER NOT NULL,
    id_docente INTEGER NOT NULL,
    id_materia INTEGER NOT NULL,
    id_periodo INTEGER DEFAULT 1,
    FOREIGN KEY (id_institucion) REFERENCES instituciones(id_institucion) ON UPDATE CASCADE,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON UPDATE CASCADE,
    FOREIGN KEY (id_materia) REFERENCES materias(id_materia) ON UPDATE CASCADE,
    FOREIGN KEY (id_periodo) REFERENCES periodos_academicos(id_periodo) ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS representantes (
    id_representante INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    apellido TEXT NOT NULL,
    cedula TEXT NOT NULL,
    telefono TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS estudiantes (
    id_estudiante INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    apellido TEXT NOT NULL,
    cedula TEXT NOT NULL,
    fecha_nacimiento TEXT DEFAULT NULL,
    sexo TEXT DEFAULT NULL,
    enfermedad_condicion TEXT DEFAULT NULL,
    id_seccion INTEGER DEFAULT NULL,
    id_representante INTEGER DEFAULT NULL,
    id_periodo INTEGER DEFAULT NULL,
    FOREIGN KEY (id_seccion) REFERENCES secciones(id_seccion) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_representante) REFERENCES representantes(id_representante) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_periodo) REFERENCES periodos_academicos(id_periodo) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS inasistencias (
    id_inasistencia INTEGER PRIMARY KEY AUTOINCREMENT,
    id_estudiante INTEGER NOT NULL,
    id_materia INTEGER NOT NULL,
    id_docente INTEGER NOT NULL,
    fecha TEXT NOT NULL,
    estado TEXT DEFAULT 'Presente',
    motivo TEXT DEFAULT NULL,
    FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante) ON DELETE CASCADE,
    FOREIGN KEY (id_materia) REFERENCES materias(id_materia) ON DELETE CASCADE,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS asistencias (
    id_asistencia INTEGER PRIMARY KEY AUTOINCREMENT,
    id_estudiante INTEGER NOT NULL,
    fecha TEXT NOT NULL,
    estado TEXT NOT NULL
);

INSERT INTO registro (id, usuario, contrasena, correo) VALUES
(4,'winston100','$2y$10$It3IXr3iQB.SlOygxeTYP.ITE9b2O7eLSuiaWc5WCjMYQLHtJJ.Xq','winstonjose99@gmail.com'),
(5,'winston1001','$2y$10$w/96h6/WGd/YXn.p3ri7d.QveJ1uQVlHrJCj1NRVTrD.z3fPwuTRu','winstonjose100@gmail.com');

INSERT INTO periodos_academicos (id_periodo, nombre_periodo, activo) VALUES
(1,'2025-2026',0),(2,'2025-2026',0),(3,'2026-2027',0),(4,'2025-2026',1);

INSERT INTO instituciones (id_institucion, nombre, direccion) VALUES
(2,'U E REPUBLICA DEL ECUADOR','av san martin'),
(3,'U E JARDIN FRANCISCANO','CHAPELLIN'),
(4,'El Archerpol','av sas martin');

INSERT INTO docentes (id_docente, nombre, apellido, cedula, correo, telefono, id_institucion) VALUES
(1,'maria','herrera','23413444','qwaseqwe@gmail.com','584124710360',2),
(2,'winston','herrera','27374153','qweqweqwe@gmail.com','584124710360',2),
(3,'winston','asdsadasd','27374153','asdaerwqe@gmail.com','584124710360',2),
(4,'Jose','Rodriguez','27374153','winston@gmail.com','584124710360',2),
(5,'Jose Villa','','27374153','winston100@gmail.com','883723',2),
(6,'Javier','Linares','27374153','javier@gmail.com','584124710360',2),
(7,'Victor','','27374153','winstonjose99@gmail.com','584124710360',2),
(8,'Marcelo','Contreras','27374153','marcelo@gmail.com','584124710360',2);

INSERT INTO materias (id_materia, nombre_materia, id_docente) VALUES
(9,'Matematicas',3),(10,'Ingles',1),(11,'Quimica',5),(12,'Fisica',6),(13,'Informatica',4),(14,'Quimica',7),(15,'Programacion',8);

INSERT INTO secciones (id_seccion, nombre_seccion, id_institucion, id_docente, id_materia, id_periodo) VALUES
(8,'1A',2,4,13,4),(9,'2 A',2,1,12,4),(10,'3 B',2,7,13,4),(11,'2 A',4,8,15,4);

INSERT INTO representantes (id_representante, nombre, apellido, cedula, telefono) VALUES
(3,'miladis','herrera','14965665','584124710360'),
(8,'Orlexy','Materano','27374153','584124710360'),
(9,'Zaida','Zenith','27374153','584124710360'),
(10,'Roberis','Torres','27374154','584124710360'),
(11,'Luis','Rodriguez','272734153','584124710360'),
(12,'Ana','Torres','27374153','584124710360');

INSERT INTO estudiantes (id_estudiante, nombre, apellido, cedula, fecha_nacimiento, sexo, enfermedad_condicion, id_seccion, id_representante, id_periodo) VALUES
(7,'Giuseppe','Salas','31450112','2011-04-15','Masculino','Ninguna',8,9,4),
(8,'Giuseppe','Pinto','31450118','2011-08-22','Masculino','Ninguna',8,9,4),
(15,'Carlos','Hernandez','3094942123','2016-01-22','Masculino','asma',9,10,4),
(16,'Luis','Torres','27374153','2009-06-17','Masculino','Asma',9,11,4),
(17,'Marcos','Torres','27374153','2016-01-07','Masculino','Asma',11,12,4),
(18,'Valeria','Mendoza','31890234','2011-02-10','Femenino','Ninguna',8,3,4),
(19,'Santiago','Rivas','31904512','2011-06-18','Masculino','Asma leve',8,8,4),
(20,'Camila','Colmenares','32015489','2011-11-03','Femenino','Ninguna',8,9,4),
(21,'Diego','Urdaneta','31782341','2011-01-25','Masculino','Ninguna',8,10,4),
(22,'Mariana','Zambrano','32110984','2011-09-14','Femenino','Alergia estacional',8,11,4),
(23,'Alejandro','Perdomo','31654902','2011-03-29','Masculino','Ninguna',8,12,4),
(24,'Gabriela','Quintero','32098711','2011-07-07','Femenino','Ninguna',8,3,4),
(25,'Sebastián','Escalona','31845670','2011-12-19','Masculino','Uso de lentes correctivos',8,8,4),
(26,'Daniela','Montilla','32145009','2011-05-11','Femenino','Ninguna',8,11,4),
(27,'Andrés','Villamizar','31998230','2011-10-30','Masculino','Ninguna',8,12,4);

INSERT INTO inasistencias (id_inasistencia, id_estudiante, id_materia, id_docente, fecha, estado, motivo) VALUES
(11,16,13,7,'2026-11-05','Justificado','Justificado (registro anterior)'),
(14,17,15,8,'2026-11-05','Justificado','Justificado (registro anterior)'),
(17,7,13,4,'2026-11-18','Presente','Asistió a clase'),
(18,8,13,4,'2026-11-18','Presente','Asistió a clase'),
(19,18,13,4,'2026-11-18','Presente','Asistió a clase'),
(20,19,13,4,'2026-11-18','Ausente',NULL),
(21,20,13,4,'2026-11-18','Presente','Asistió a clase'),
(22,21,13,4,'2026-11-18','Presente','Asistió a clase'),
(23,22,13,4,'2026-11-18','Presente','Asistió a clase'),
(24,23,13,4,'2026-11-18','Ausente',NULL),
(25,24,13,4,'2026-11-18','Presente','Asistió a clase'),
(26,25,13,4,'2026-11-18','Justificado','Cita Medica'),
(27,26,13,4,'2026-11-18','Presente','Asistió a clase'),
(28,27,13,4,'2026-11-18','Presente','Asistió a clase'),
(32,20,13,4,'2026-11-17','Presente','Asistió a clase'),
(33,25,13,4,'2026-11-17','Presente','Asistió a clase'),
(34,18,13,4,'2026-11-17','Presente','Asistió a clase'),
(35,26,13,4,'2026-11-17','Presente','Asistió a clase'),
(36,23,13,4,'2026-11-17','Presente','Asistió a clase'),
(37,8,13,4,'2026-11-17','Presente','Asistió a clase'),
(38,24,13,4,'2026-11-17','Presente','Asistió a clase'),
(39,19,13,4,'2026-11-17','Ausente',NULL),
(40,7,13,4,'2026-11-17','Presente','Asistió a clase'),
(41,21,13,4,'2026-11-17','Presente','Asistió a clase'),
(42,27,13,4,'2026-11-17','Presente','Asistió a clase'),
(43,22,13,4,'2026-11-17','Ausente',NULL);
SQL;
        $this->pdo->exec($ddl);
    }
}
?>
