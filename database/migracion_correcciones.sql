-- =====================================================================
-- Migración de correcciones para la base de datos `sistema_asistencias`
-- =====================================================================

USE sistema_asistencias;

-- 1. Corregir tipos de datos que causaban truncamiento o inconsistencias
ALTER TABLE docentes
    MODIFY COLUMN telefono VARCHAR(20) NOT NULL;

ALTER TABLE representantes
    MODIFY COLUMN cedula VARCHAR(20) NOT NULL;

-- 2. Corregir tabla inasistencias: añadir columna `motivo` y reparar filas corruptas ('')
ALTER TABLE inasistencias
    ADD COLUMN IF NOT EXISTS motivo VARCHAR(150) DEFAULT NULL AFTER estado;

UPDATE inasistencias
SET estado = 'Justificado',
    motivo = 'Justificado (registro anterior)'
WHERE estado = '';

-- 3. Alinear secciones existentes con el período académico activo actual
UPDATE secciones
SET id_periodo = (SELECT id_periodo FROM periodos_academicos WHERE activo = 1 LIMIT 1)
WHERE (SELECT COUNT(*) FROM periodos_academicos WHERE activo = 1) > 0;

-- 4. Permitir NULL en estudiantes.id_seccion e id_representante para limpiar huérfanos sin perder alumnos
ALTER TABLE estudiantes
    MODIFY COLUMN id_seccion INT(11) DEFAULT NULL,
    MODIFY COLUMN id_representante INT(11) DEFAULT NULL;

UPDATE estudiantes
SET id_seccion = NULL
WHERE id_seccion IS NOT NULL
  AND id_seccion NOT IN (SELECT id_seccion FROM secciones);

UPDATE estudiantes
SET id_representante = NULL
WHERE id_representante IS NOT NULL
  AND id_representante NOT IN (SELECT id_representante FROM representantes);

-- 5. Agregar claves foráneas faltantes para garantizar integridad referencial
ALTER TABLE docentes
    ADD CONSTRAINT fk_docentes_institucion
        FOREIGN KEY (id_institucion) REFERENCES instituciones (id_institucion)
        ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE materias
    ADD CONSTRAINT fk_materias_docente
        FOREIGN KEY (id_docente) REFERENCES docentes (id_docente)
        ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE secciones
    ADD CONSTRAINT fk_secciones_institucion
        FOREIGN KEY (id_institucion) REFERENCES instituciones (id_institucion)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_secciones_docente
        FOREIGN KEY (id_docente) REFERENCES docentes (id_docente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_secciones_materia
        FOREIGN KEY (id_materia) REFERENCES materias (id_materia)
        ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE estudiantes
    ADD CONSTRAINT fk_estudiantes_seccion
        FOREIGN KEY (id_seccion) REFERENCES secciones (id_seccion)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_estudiantes_representante
        FOREIGN KEY (id_representante) REFERENCES representantes (id_representante)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_estudiantes_periodo
        FOREIGN KEY (id_periodo) REFERENCES periodos_academicos (id_periodo)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- 6. Eliminar tabla vacía sin uso (`usuarios`); el sistema usa `registro`
DROP TABLE IF EXISTS usuarios;
