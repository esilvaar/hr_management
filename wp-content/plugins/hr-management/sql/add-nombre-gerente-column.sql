-- =====================================================
-- AGREGAR COLUMNA: nombre_gerente
-- =====================================================
-- Tabla: Bu6K9_rrhh_gerencia_deptos
--
-- Esta columna almacena el nombre del gerente que
-- tiene a cargo el departamento en la relación gerencia-departamento.
--
-- El nombre se sincroniza cuando se crea/edita un empleado
-- y se marca como gerente de un área.

ALTER TABLE Bu6K9_rrhh_gerencia_deptos 
ADD COLUMN nombre_gerente VARCHAR(255) NULL DEFAULT NULL 
COMMENT 'Nombre del gerente a cargo de esta área gerencial';

-- =====================================================
-- NOTA: Este script debe ejecutarse manualmente
-- o incluirse en el proceso de activación del plugin
-- =====================================================
