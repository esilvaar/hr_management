-- =====================================================
-- AGREGAR COLUMNA: motivo_rechazo
-- =====================================================
-- Fecha: Enero 2026
-- Descripción: Agrega una columna para registrar el motivo 
--              cuando el admin rechaza una solicitud
-- =====================================================

ALTER TABLE `Bu6K9_rrhh_solicitudes_ausencia` 
ADD COLUMN `motivo_rechazo` LONGTEXT NULL AFTER `comentario_empleado`;

-- Crear índice para búsquedas optimizadas
CREATE INDEX `idx_estado_motivo` ON `Bu6K9_rrhh_solicitudes_ausencia` (`estado`, `motivo_rechazo`(50));

-- Comentario de la columna (opcional, para documentación)
ALTER TABLE `Bu6K9_rrhh_solicitudes_ausencia` 
MODIFY COLUMN `motivo_rechazo` LONGTEXT NULL COMMENT 'Motivo del rechazo registrado por el administrador';
