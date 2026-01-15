-- Script SQL para agregar columna periodo_ausencia
-- Ejecutar en tu base de datos rrhhanacondaweb_wp

ALTER TABLE wp_rrhh_solicitudes_ausencia 
ADD COLUMN periodo_ausencia ENUM('completo', 'mañana', 'tarde') 
NOT NULL DEFAULT 'completo' 
AFTER estado;

-- Verificar que se agrego correctamente
DESCRIBE wp_rrhh_solicitudes_ausencia;
