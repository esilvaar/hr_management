-- =====================================================
-- Script para agregar columnas de respuesta de RRHH
-- a la tabla de solicitudes de ausencia
-- =====================================================

-- Agregar columna: nombre de jefe/RRHH que responde
ALTER TABLE `Bu6K9_rrhh_solicitudes_ausencia` 
ADD COLUMN `nombre_jefe` VARCHAR(150) NULL DEFAULT NULL 
AFTER `motivo_rechazo`,
COMMENT 'Nombre del jefe o persona de RRHH que responde la solicitud';

-- Agregar columna: fecha de respuesta de RRHH/Jefatura
ALTER TABLE `Bu6K9_rrhh_solicitudes_ausencia` 
ADD COLUMN `fecha_respuesta` DATE NULL DEFAULT NULL 
AFTER `nombre_jefe`,
COMMENT 'Fecha en que RRHH/Jefatura respondió la solicitud';

-- Agregar índices para mejorar búsquedas
ALTER TABLE `Bu6K9_rrhh_solicitudes_ausencia`
ADD INDEX `idx_nombre_jefe` (`nombre_jefe`),
ADD INDEX `idx_fecha_respuesta` (`fecha_respuesta`);

-- Verificar las nuevas columnas
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'Bu6K9_rrhh_solicitudes_ausencia' 
-- AND TABLE_SCHEMA = 'YOUR_DATABASE_NAME'
-- ORDER BY ORDINAL_POSITION;
