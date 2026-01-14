-- =====================================================
-- AGREGAR COLUMNA: motivo_rechazo
-- VERSIÓN: GENÉRICA (Sin prefijo fijo)
-- =====================================================
-- Fecha: Enero 2026
-- Descripción: Agrega una columna para registrar el motivo 
--              cuando el admin rechaza una solicitud
-- 
-- IMPORTANTE: Reemplaza 'wp_' con tu prefijo de tabla real
-- =====================================================

ALTER TABLE wp_rrhh_solicitudes_ausencia 
ADD COLUMN `motivo_rechazo` LONGTEXT NULL AFTER `comentario_empleado`;

-- Crear índice para búsquedas optimizadas
CREATE INDEX `idx_estado_motivo` ON wp_rrhh_solicitudes_ausencia (`estado`, `motivo_rechazo`(50));

-- Comentario de la columna (opcional, para documentación)
ALTER TABLE wp_rrhh_solicitudes_ausencia 
MODIFY COLUMN `motivo_rechazo` LONGTEXT NULL COMMENT 'Motivo del rechazo registrado por el administrador';

-- =====================================================
-- VERIFICACIÓN: Ejecuta esto para validar los cambios
-- =====================================================
-- DESCRIBE wp_rrhh_solicitudes_ausencia;
-- SHOW INDEXES FROM wp_rrhh_solicitudes_ausencia;
