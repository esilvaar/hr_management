-- =====================================================
-- TABLA: HISTORIAL ANUAL DE VACACIONES
-- =====================================================
-- Registra el historial de vacaciones por año
-- Permite trackear acumulados y carryover

CREATE TABLE IF NOT EXISTS `Bu6K9_rrhh_vacaciones_anual` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_empleado` INT NOT NULL,
  `ano` INT NOT NULL,
  `dias_asignados` INT DEFAULT 15,
  `dias_usados` INT DEFAULT 0,
  `dias_disponibles` INT DEFAULT 15,
  `dias_carryover_anterior` INT DEFAULT 0,
  `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY `unique_empleado_ano` (`id_empleado`, `ano`),
  FOREIGN KEY (`id_empleado`) REFERENCES `Bu6K9_rrhh_empleados`(`id_empleado`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ÍNDICES PARA OPTIMIZACIÓN
-- =====================================================

CREATE INDEX `idx_empleado_ano` ON `Bu6K9_rrhh_vacaciones_anual` (`id_empleado`, `ano`);
CREATE INDEX `idx_ano` ON `Bu6K9_rrhh_vacaciones_anual` (`ano`);
