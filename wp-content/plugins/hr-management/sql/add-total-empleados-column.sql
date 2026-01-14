-- =====================================================
-- AGREGAR COLUMNA: total_empleados
-- =====================================================
-- Añade una columna para trackear el total de empleados
-- asignados a cada departamento

ALTER TABLE `Bu6K9_rrhh_departamentos` 
ADD COLUMN `total_empleados` INT DEFAULT 0 AFTER `personal_vigente`;

-- =====================================================
-- ACTUALIZAR CON COUNT DE EMPLEADOS
-- =====================================================
-- Llenar la columna con el conteo actual de empleados por departamento

UPDATE `Bu6K9_rrhh_departamentos` d
SET `total_empleados` = (
    SELECT COUNT(*) 
    FROM `Bu6K9_rrhh_empleados` e 
    WHERE e.`departamento` = d.`nombre_departamento` 
    AND e.`estado` = 'Activo'
);

-- =====================================================
-- VERIFICACIÓN
-- =====================================================
-- SELECT nombre_departamento, minimo_empleados, personal_vigente, total_empleados 
-- FROM Bu6K9_rrhh_departamentos;
