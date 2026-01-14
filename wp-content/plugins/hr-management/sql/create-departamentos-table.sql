-- =====================================================
-- TABLA: DEPARTAMENTOS
-- =====================================================
-- Descripción: Tabla para gestionar departamentos 
-- de la empresa con su personal mínimo requerido
-- =====================================================

CREATE TABLE IF NOT EXISTS `Bu6K9_rrhh_departamentos` (
  `id_departamento` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_departamento` VARCHAR(100) NOT NULL UNIQUE,
  `minimo_empleados` INT NOT NULL DEFAULT 1,
  `estado` VARCHAR(20) DEFAULT 'Activo' COMMENT 'Activo o Inactivo',
  `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX `idx_nombre` (`nombre_departamento`),
  INDEX `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERTAR DATOS INICIALES
-- =====================================================

INSERT INTO `Bu6K9_rrhh_departamentos` 
(`nombre_departamento`, `minimo_empleados`) 
VALUES 
('Soporte', 2),
('Desarrollo', 1),
('Administración', 2),
('Sistemas', 0),
('Ventas', 1);

-- =====================================================
-- VERIFICACIÓN (Ejecutar después de crear la tabla)
-- =====================================================
-- SELECT * FROM Bu6K9_rrhh_departamentos;
