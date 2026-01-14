<?php
/**
 * Plantilla de Documento de Solicitud de Vacaciones
 * Basada en el formato oficial de Anacondaweb S.A.
 * 
 * Variables disponibles:
 * $solicitud->id
 * $solicitud->tipo (Vacaciones, Licencia, Permiso)
 * $solicitud->fecha_inicio
 * $solicitud->fecha_fin
 * $solicitud->motivo
 * $solicitud->estado (PENDIENTE, APROBADO, RECHAZADO)
 * $solicitud->rut
 * $solicitud->nombres
 * $solicitud->apellidos
 * $solicitud->cargo
 * $solicitud->departamento
 * $solicitud->created_at
 */

define('IFRAME_REQUEST', true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Vacaciones - Anacondaweb</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Franklin Gothic Book', 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            font-size: 10pt;
        }

        .page {
            background: white;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* HEADER con logo y datos de la empresa */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .header-logo {
            background: #006666;
            padding: 10px 15px;
            color: white;
            vertical-align: middle;
        }

        .logo-text {
            font-size: 20pt;
            font-weight: bold;
            margin: 0;
        }

        .logo-text .green {
            color: #05FF12;
        }

        .company-info {
            font-size: 11pt;
            margin-top: 5px;
        }

        .header-img {
            background: #006666;
            text-align: center;
            padding: 10px;
        }

        .logo-img {
            max-height: 50px;
        }

        /* TÍTULO PRINCIPAL */
        .main-title {
            text-align: center;
            font-size: 24pt;
            font-weight: bold;
            margin: 20px 0 30px 0;
            color: #000;
            text-transform: uppercase;
        }

        /* SECCIONES */
        .section-title {
            background: #FEDE00;
            padding: 8px 0;
            margin: 20px 0 10px 0;
            border-bottom: 2.25pt solid #FEDE00;
        }

        .section-title h1 {
            font-size: 12pt;
            color: #006666;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }

        /* TABLAS */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th {
            background: #006666;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            border: 1.5pt solid white;
        }

        td {
            background: #E5EAEE;
            padding: 8px;
            font-size: 11pt;
            font-weight: bold;
            color: black;
            border: 1.5pt solid white;
        }

        td.center {
            text-align: center;
        }

        /* TEXTO LEGAL */
        .legal-text {
            font-size: 12pt;
            line-height: 1.6;
            margin: 15px 0;
            text-align: justify;
        }

        /* FIRMA */
        .signature-area {
            margin: 40px 0;
        }

        .signature-line {
            margin-top: 50px;
            padding-top: 5px;
        }

        .signature-line::before {
            content: '';
            display: block;
            border-top: 1px solid #000;
            width: 300px;
            margin-bottom: 5px;
        }

        .signature-label {
            font-size: 10pt;
            font-style: italic;
            color: #006666;
        }

        .signature-name {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 5px;
        }

        /* BOTÓN IMPRIMIR */
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .btn-print {
            background: #006666;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .btn-print:hover {
            background: #008080;
        }

        /* CHECKBOX para aprobación */
        .checkbox {
            display: inline-block;
            width: 13pt;
            height: 13pt;
            border: 1pt solid black;
            background: white;
            border-radius: 2pt;
            vertical-align: middle;
            margin-right: 5pt;
            text-align: center;
            line-height: 13pt;
            font-size: 11pt;
        }

        .checkbox.checked::after {
            content: '☑';
            color: #006666;
            font-weight: bold;
        }

        /* IMPRESIÓN */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .page {
                box-shadow: none;
                margin: 0;
                padding: 15mm;
            }

            .no-print {
                display: none !important;
            }
        }

        @page {
            size: A4;
            margin: 15mm;
        }
    </style>
</head>
<body>
    <!-- Botón de impresión -->
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ Descargar PDF</button>
    </div>

    <div class="page">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td class="header-logo" style="width: 70%;">
                    <p class="logo-text">
                        <span style="text-transform: capitalize;">A</span>naconda<span class="green">web</span> <span style="font-size: 16pt;">s.a</span>
                    </p>
                    <p class="company-info">
                        Av. Bernardo O'higgins 485 | +56 2 2583 7070
                    </p>
                </td>
                <td class="header-img" style="width: 30%;">
                    <img src="<?php echo content_url('/uploads/2025/12/anaconda.png'); ?>" 
                         alt="Anacondaweb" 
                         class="logo-img">
                </td>
            </tr>
        </table>

        <!-- TÍTULO PRINCIPAL -->
        <div class="main-title">
            SOLICITUD DE <?php echo strtoupper($solicitud->tipo ?? 'VACACIONES'); ?>
        </div>

        <!-- SECCIÓN 1: DATOS DEL TRABAJADOR -->
        <div class="section-title">
            <h1>DATOS DEL TRABAJADOR</h1>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">FECHA</th>
                    <th style="width: 35%;">NOMBRE DEL TRABAJADOR</th>
                    <th style="width: 20%;">RUT</th>
                    <th style="width: 25%;">CARGO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center">
                        <?php echo date('d/m/Y', strtotime($solicitud->created_at)); ?>
                    </td>
                    <td>
                        <?php echo esc_html(($solicitud->nombres ?? '') . ' ' . ($solicitud->apellidos ?? '')); ?>
                    </td>
                    <td class="center">
                        <?php echo esc_html($solicitud->rut ?? 'N/A'); ?>
                    </td>
                    <td>
                        <?php echo esc_html(($solicitud->cargo ?? 'Cargo') . ' - ' . ($solicitud->departamento ?? 'Unidad')); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- SECCIÓN 2: SOLICITUD -->
        <div class="section-title">
            <h1>SOLICITUD</h1>
        </div>

        <div class="legal-text">
            <?php if (($solicitud->tipo ?? '') == 'Vacaciones'): ?>
                Por medio de la presente, solicito formalmente la autorización para hacer uso de <strong>mis días de vacaciones</strong> correspondientes al período laboral <?php echo date('Y'); ?>.
            <?php elseif (($solicitud->tipo ?? '') == 'Licencia'): ?>
                Informo la presentación de Licencia Médica adjunta para los fines correspondientes.
            <?php else: ?>
                <?php echo esc_html($solicitud->motivo ?? 'Solicito permiso administrativo'); ?>
            <?php endif; ?>
        </div>

        <!-- SECCIÓN 3: PERIODO DE VACACIONES -->
        <div class="section-title">
            <h1>PERIODO DE VACACIONES</h1>
        </div>

        <?php
        $inicio = new DateTime($solicitud->fecha_inicio);
        $fin = new DateTime($solicitud->fecha_fin);
        $diff = $inicio->diff($fin);
        $dias = $diff->days + 1;
        
        $reincorp = clone $fin;
        $reincorp->modify('+1 day');
        ?>

        <table>
            <thead>
                <tr>
                    <th>FECHA DE INICIO</th>
                    <th>FECHA DE TÉRMINO</th>
                    <th>FECHA DE REINCORPORACIÓN</th>
                    <th>TOTAL DE DÍAS SOLICITADOS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center"><?php echo $inicio->format('d/m/Y'); ?></td>
                    <td class="center"><?php echo $fin->format('d/m/Y'); ?></td>
                    <td class="center"><?php echo $reincorp->format('d/m/Y'); ?></td>
                    <td class="center"><strong><?php echo $dias; ?></strong></td>
                </tr>
            </tbody>
        </table>

        <!-- TEXTO LEGAL -->
        <div class="legal-text">
            Quedo atento(a) a la <strong>confirmación y aprobación de esta solicitud</strong>. 
            Me comprometo a dejar mis tareas debidamente coordinadas con mi jefatura directa antes de mi ausencia.
        </div>

        <div class="legal-text">
            Sin otro particular, Saluda atentamente,
        </div>

        <!-- FIRMA DEL TRABAJADOR -->
        <div class="signature-area">
            <div class="signature-line">
                <span class="signature-label">Firma del trabajador:</span>
                <div class="signature-name">
                    <?php echo esc_html(($solicitud->nombres ?? '') . ' ' . ($solicitud->apellidos ?? '')); ?>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 4: RECURSOS HUMANOS / JEFATURA -->
        <div class="section-title">
            <h1>RECURSOS HUMANOS / JEFATURA DIRECTA:</h1>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 25%;">RESPUESTA POSITIVA</th>
                    <th style="width: 25%;">RESPUESTA NEGATIVA</th>
                    <th style="width: 30%;">NOMBRE JEFE</th>
                    <th style="width: 20%;">FECHA</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center">
                        <span class="checkbox <?php echo ($solicitud->estado === 'APROBADO') ? 'checked' : ''; ?>"></span>
                        <strong>ACEPTADO</strong>
                    </td>
                    <td class="center">
                        <span class="checkbox <?php echo ($solicitud->estado === 'RECHAZADO') ? 'checked' : ''; ?>"></span>
                        <strong>RECHAZADO</strong>
                    </td>
                    <td>
                        <?php
                        $approver_name = 'Nombre';
                        if (isset($solicitud->aprobado_por_id) && $solicitud->aprobado_por_id) {
                            $user_info = get_userdata($solicitud->aprobado_por_id);
                            if ($user_info) {
                                $approver_name = $user_info->display_name;
                            }
                        }
                        echo esc_html($approver_name);
                        ?>
                    </td>
                    <td class="center">
                        <?php
                        echo isset($solicitud->fecha_resolucion) && $solicitud->fecha_resolucion
                            ? date('d/m/Y', strtotime($solicitud->fecha_resolucion))
                            : 'dd/mm/aaaa';
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Espacio para firma de RR.HH. -->
        <div class="signature-area">
            <div class="signature-line">
                <span class="signature-label">Firma de RR.HH / Jefatura</span>
            </div>
        </div>

        <!-- Observaciones -->
        <div style="margin-top: 20px; font-size: 11pt;">
            <strong>Observaciones (Opcional):</strong>
            <div style="border-bottom: 1px solid #ccc; min-height: 60px; margin-top: 10px;"></div>
        </div>
    </div>
</body>
</html>
