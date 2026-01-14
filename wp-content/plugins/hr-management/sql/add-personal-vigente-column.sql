-- =====================================================
-- AGREGAR COLUMNA: personal_vigente
-- =====================================================
-- Añade una columna para trackear el personal actual
-- en cada departamento

ALTER TABLE `Bu6K9_rrhh_departamentos` 
ADD COLUMN `personal_vigente` INT DEFAULT 0 AFTER `minimo_empleados`;

-- =====================================================
-- ACTUALIZAR CON VALORES RANDOM
-- =====================================================
-- Insertar valores random para cada departamento
-- (valores entre minimo_empleados y minimo_empleados + 5)

UPDATE `Bu6K9_rrhh_departamentos` 
SET `personal_vigente` = `minimo_empleados` + FLOOR(RAND() * 6)
WHERE `nombre_departamento` IN ('Soporte', 'Desarrollo', 'Administración', 'Sistemas', 'Ventas');

-- =====================================================
-- VERIFICACIÓN
-- =====================================================
-- SELECT * FROM Bu6K9_rrhh_departamentos;
