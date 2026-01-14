-- =====================================================
-- AGREGAR COLUMNA: correo_gerente
-- =====================================================
-- Tabla: Bu6K9_rrhh_gerencia_deptos
--
-- Esta columna almacena el correo del gerente que
-- tiene a cargo el departamento en la relación gerencia-departamento.
--
-- El correo se sincroniza cuando se crea/edita un empleado
-- y se marca como gerente de un área.

ALTER TABLE Bu6K9_rrhh_gerencia_deptos 
ADD COLUMN correo_gerente VARCHAR(255) NULL DEFAULT NULL 
COMMENT 'Correo del gerente a cargo de esta área gerencial';

-- =====================================================
-- NOTA: Este script debe ejecutarse manualmente
-- o incluirse en el proceso de activación del plugin
-- =====================================================
