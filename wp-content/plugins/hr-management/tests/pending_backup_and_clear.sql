-- Backup and clear current PENDIENTE rows (store ids to restore later)
SELECT 'UPDATE Bu6K9_rrhh_solicitudes_ausencia SET estado=\'PENDIENTE\' WHERE id_solicitud IN (217,219);' AS restore_ausencia;
SELECT 'UPDATE Bu6K9_rrhh_solicitudes_medio_dia SET estado=\'PENDIENTE\' WHERE id_solicitud IN (11,12);' AS restore_medio_dia;

-- Now set them to APROBADA for testing start-from-zero
UPDATE Bu6K9_rrhh_solicitudes_ausencia SET estado='APROBADA', fecha_respuesta=CURDATE() WHERE estado='PENDIENTE';
UPDATE Bu6K9_rrhh_solicitudes_medio_dia SET estado='APROBADA', fecha_respuesta=CURDATE() WHERE estado='PENDIENTE';
