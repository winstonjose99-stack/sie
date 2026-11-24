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
    private bool $journalDirty = false;
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

    private function readCompressedSyncCookie(): string {
        $combined = (string) ($_COOKIE['SIE_DB_SYNC'] ?? '');
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($_COOKIE['SIE_DB_SYNC_' . $i])) {
                $combined .= (string) $_COOKIE['SIE_DB_SYNC_' . $i];
            }
        }
        return $combined;
    }

    private function replayCookieJournal(): void {
        $combined = $this->readCompressedSyncCookie();
        if ($combined === '') {
            return;
        }
        $raw = @base64_decode($combined, true);
        if (!$raw) {
            return;
        }
        $json = @gzinflate($raw);
        if (!$json) {
            return;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return;
        }

        // Soportar tanto formato compacto deduplicado ['s' => [...], 'e' => [...]] como formato legado
        $entries = [];
        if (isset($decoded['s'], $decoded['e']) && is_array($decoded['s']) && is_array($decoded['e'])) {
            $sqlPool = $decoded['s'];
            foreach ($decoded['e'] as $item) {
                if (!is_array($item) || count($item) < 3) {
                    continue;
                }
                $sqlText = $sqlPool[$item[1]] ?? '';
                if ($sqlText !== '') {
                    $entries[] = [
                        'id'     => (string) $item[0],
                        'sql'    => (string) $sqlText,
                        'params' => is_array($item[2]) ? $item[2] : [],
                    ];
                }
            }
        } else {
            $entries = $decoded;
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

    private function encodeJournalCompact(array $entries): string {
        $sqlPool = [];
        $sqlIndexMap = [];
        $compactEntries = [];
        foreach ($entries as $entry) {
            $sql = (string) ($entry['sql'] ?? '');
            if (!isset($sqlIndexMap[$sql])) {
                $sqlIndexMap[$sql] = count($sqlPool);
                $sqlPool[] = $sql;
            }
            $compactEntries[] = [
                (string) ($entry['id'] ?? ''),
                $sqlIndexMap[$sql],
                $entry['params'] ?? [],
            ];
        }
        $payload = json_encode(['s' => $sqlPool, 'e' => $compactEntries], JSON_UNESCAPED_UNICODE);
        return base64_encode((string) gzdeflate((string) $payload, 9));
    }

    public function recordWrite(string $sql, array $params = []): void {
        $id = substr(md5($sql . serialize($params) . microtime(true) . mt_rand()), 0, 12);
        try {
            $ins = $this->pdo->prepare('INSERT OR IGNORE INTO _sync_meta (id) VALUES (?)');
            $ins->execute([$id]);
        } catch (Throwable $e) {}

        $this->journal[] = [
            'id'     => $id,
            'sql'    => $sql,
            'params' => $params,
        ];
        if (count($this->journal) > 120) {
            $this->journal = array_slice($this->journal, -120);
        }
        $this->journalDirty = true;
    }

    public function flushSyncCookies(): void {
        if (!$this->journalDirty || headers_sent()) {
            return;
        }
        $maxTotalBytes = 14000; // 4 cookies x 3500 bytes
        $chunkSize     = 3500;

        // Recortar entradas más antiguas si supera el límite, garantizando que las escrituras más recientes SIEMPRE se guarden
        $compressed = $this->encodeJournalCompact($this->journal);
        while (strlen($compressed) > $maxTotalBytes && count($this->journal) > 1) {
            array_shift($this->journal);
            $compressed = $this->encodeJournalCompact($this->journal);
        }

        $chunks = str_split($compressed, $chunkSize);
        $opts = [
            'expires'  => time() + 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        $optsExpire = [
            'expires'  => time() - 42000,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        for ($i = 0; $i < 4; $i++) {
            $cookieName = $i === 0 ? 'SIE_DB_SYNC' : 'SIE_DB_SYNC_' . $i;
            if (isset($chunks[$i]) && $chunks[$i] !== '') {
                setcookie($cookieName, $chunks[$i], $opts);
                $_COOKIE[$cookieName] = $chunks[$i];
            } else {
                if (isset($_COOKIE[$cookieName])) {
                    setcookie($cookieName, '', $optsExpire);
                    unset($_COOKIE[$cookieName]);
                }
            }
        }
        $this->journalDirty = false;
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
            $this->insert_id = (int) $this->pdo->lastInsertId();
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
        $this->flushSyncCookies();
        return true;
    }

    public function __destruct() {
        $this->flushSyncCookies();
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

CREATE TABLE IF NOT EXISTS notificaciones_representante (
    id_notificacion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_representante INTEGER NOT NULL,
    id_estudiante INTEGER DEFAULT NULL,
    tipo TEXT NOT NULL DEFAULT 'asistencia',
    titulo TEXT NOT NULL,
    mensaje TEXT NOT NULL,
    leida INTEGER NOT NULL DEFAULT 0,
    fecha_creacion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS evaluaciones_plan (
    id_evaluacion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_seccion INTEGER NOT NULL,
    id_materia INTEGER NOT NULL,
    id_docente INTEGER NOT NULL,
    id_periodo INTEGER DEFAULT NULL,
    lapso TEXT NOT NULL DEFAULT '1er Lapso',
    nombre_actividad TEXT NOT NULL,
    ponderacion REAL NOT NULL DEFAULT 25.00,
    fecha_evaluacion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS notas_academicas (
    id_nota INTEGER PRIMARY KEY AUTOINCREMENT,
    id_estudiante INTEGER NOT NULL,
    id_materia INTEGER NOT NULL,
    id_docente INTEGER DEFAULT NULL,
    id_periodo INTEGER DEFAULT NULL,
    id_evaluacion INTEGER DEFAULT NULL,
    lapso TEXT NOT NULL DEFAULT '1er Lapso',
    evaluacion TEXT NOT NULL,
    ponderacion REAL NOT NULL DEFAULT 25.00,
    calificacion REAL NOT NULL,
    observacion TEXT DEFAULT NULL,
    fecha_registro TEXT NOT NULL
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

INSERT INTO evaluaciones_plan (id_evaluacion, id_seccion, id_materia, id_docente, id_periodo, lapso, nombre_actividad, ponderacion, fecha_evaluacion) VALUES
(1,8,13,4,4,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,'2026-11-10'),
(2,8,13,4,4,'1er Lapso','Actividad 2: Taller de Lógica y Algoritmos',25.00,'2026-11-18');

INSERT INTO notas_academicas (id_nota, id_estudiante, id_materia, id_docente, id_periodo, id_evaluacion, lapso, evaluacion, ponderacion, calificacion, observacion, fecha_registro) VALUES
(1,7,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,18.50,'Excelente dominio en hojas de cálculo','2026-11-10'),
(2,7,13,4,4,2,'1er Lapso','Actividad 2: Taller de Lógica y Algoritmos',25.00,16.00,'Buen desempeño y participación','2026-11-18'),
(3,8,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,17.00,'Cumplió con todos los objetivos','2026-11-10'),
(4,8,13,4,4,2,'1er Lapso','Actividad 2: Taller de Lógica y Algoritmos',25.00,19.00,'Sobresaliente resolución de problemas','2026-11-18'),
(5,20,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,19.50,'Trabajo impecable y puntual','2026-11-10'),
(6,18,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,18.00,'Muy buena presentación','2026-11-10'),
(7,24,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,16.50,'Buen progreso en clase','2026-11-10'),
(8,19,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,14.00,'Debe reforzar asistencia para próximas prácticas','2026-11-10'),
(9,25,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,17.50,'Participación constante','2026-11-10'),
(10,21,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,15.50,'Aprobado, mejorar orden de entrega','2026-11-10'),
(11,22,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,18.00,'Excelente actitud en el laboratorio','2026-11-10'),
(12,26,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,19.00,'Destacada en programación básica','2026-11-10'),
(13,23,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,13.50,'Requiere repasar temas de la clase perdida','2026-11-10'),
(14,27,13,4,4,1,'1er Lapso','Actividad 1: Evaluación Práctica de Ofimática',25.00,17.00,'Buen rendimiento general','2026-11-10');

INSERT INTO notificaciones_representante (id_notificacion, id_representante, id_estudiante, tipo, titulo, mensaje, leida, fecha_creacion) VALUES
(1,9,7,'asistencia','Asistencia confirmada — Informatica','Giuseppe Salas asistió puntualmente a la clase de Informatica (Sección 1A) el 2026-11-18.',0,'2026-11-18 08:30:00'),
(2,9,7,'nota','Nueva nota académica — Informatica','Se registró 16.00 / 20 para Giuseppe Salas en Taller de Lógica y Algoritmos (1er Lapso).',0,'2026-11-18 10:15:00'),
(3,9,8,'nota','Nueva nota académica — Informatica','Se registró 19.00 / 20 para Giuseppe Pinto en Taller de Lógica y Algoritmos (1er Lapso).',0,'2026-11-18 10:16:00'),
(4,8,19,'ausencia','Inasistencia registrada — Informatica','Santiago Rivas no asistió a la clase de Informatica (Sección 1A) el día 2026-11-18. Estado: Ausente.',0,'2026-11-18 08:35:00'),
(5,8,25,'justificado','Inasistencia justificada — Informatica','Sebastián Escalona presentó inasistencia justificada en Informatica (Sección 1A) el 2026-11-18. Motivo: Cita Medica.',0,'2026-11-18 08:36:00'),
(6,12,23,'ausencia','Inasistencia registrada — Informatica','Alejandro Perdomo no asistió a la clase de Informatica (Sección 1A) el día 2026-11-18. Estado: Ausente.',0,'2026-11-18 08:37:00'),
(7,11,22,'ausencia','Inasistencia registrada — Informatica','Mariana Zambrano no asistió a la clase de Informatica (Sección 1A) el día 2026-11-17. Estado: Ausente.',0,'2026-11-17 08:40:00'),
(8,3,18,'asistencia','Asistencia confirmada — Informatica','Valeria Mendoza asistió a la clase de Informatica (Sección 1A) el 2026-11-18.',0,'2026-11-18 08:30:00'),
(9,3,18,'nota','Nueva nota académica — Informatica','Se registró 18.00 / 20 para Valeria Mendoza en Evaluación Práctica de Ofimática (1er Lapso).',0,'2026-11-10 11:00:00'),
(10,10,21,'nota','Nueva nota académica — Informatica','Se registró 15.50 / 20 para Diego Urdaneta en Evaluación Práctica de Ofimática (1er Lapso).',0,'2026-11-10 11:05:00');
SQL;
        $this->pdo->exec($ddl);
    }
}
?>
