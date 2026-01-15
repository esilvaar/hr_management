-- =====================================================
-- SCRIPT SQL: Agregar columna periodo_ausencia
-- =====================================================
-- Tabla: rrhhanacondaweb_wp.wp_rrhh_solicitudes_ausencia
-- Nueva columna: periodo_ausencia (ENUM: 'completo', 'mañana', 'tarde')
-- Descripción: Permite especificar si la ausencia es todo el día, solo mañana o solo tarde
-- =====================================================

-- 1. VERIFICAR SI LA COLUMNA YA EXISTE (OPCIONAL)
-- Si la columna ya existe, esta consulta devolverá 0
SELECT COUNT(*) as columna_existe FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'rrhhanacondaweb_wp' 
AND TABLE_NAME = 'wp_rrhh_solicitudes_ausencia' 
AND COLUMN_NAME = 'periodo_ausencia';

-- 2. AGREGAR LA COLUMNA (ejecutar solo si no existe)
-- Si la columna ya existe, usar ALTER TABLE con IF NOT EXISTS (MySQL 8.0+)
ALTER TABLE `wp_rrhh_solicitudes_ausencia` 
ADD COLUMN `periodo_ausencia` ENUM('completo', 'mañana', 'tarde') 
NOT NULL DEFAULT 'completo' 
AFTER `estado`
COMMENT 'Período de la ausencia: completo (todo el día), mañana o tarde';

-- 3. VERIFICAR QUE LA COLUMNA FUE AGREGADA
DESCRIBE `wp_rrhh_solicitudes_ausencia`;

-- 4. (OPCIONAL) Ver las solicitudes actuales con la nueva columna
SELECT 
    id_solicitud,
    id_empleado,
    fecha_inicio,
    fecha_fin,
    estado,
    periodo_ausencia,
    fecha_creacion
FROM `wp_rrhh_solicitudes_ausencia`
LIMIT 10;
