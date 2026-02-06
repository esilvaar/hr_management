<?php
// 1. CARGAR LIBRERIA DOMPDF
// Como estamos en 'includes', buscamos 'lib' directamente aqui.
require_once plugin_dir_path( __FILE__ ) . 'lib/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function hrm_generar_pdf_solicitud( $id_solicitud, $empleado, $fecha_inicio, $fecha_fin, $total_dias ) {
    if ( empty( $empleado ) ) {
        return '';
    }

    // --- BLOQUE CORREGIDO: MAPEADO DE DATOS ---
    $nom = ! empty( $empleado->nombre ) ? $empleado->nombre : ( $empleado->first_name ?? '' );
    $ape = ! empty( $empleado->apellido ) ? $empleado->apellido : ( $empleado->last_name ?? '' );
    $nombre_completo = trim( $nom . ' ' . $ape );

    if ( empty( $nombre_completo ) && ! empty( $empleado->user_login ) ) {
        $nombre_completo = $empleado->user_login;
    }

    $rut = ! empty( $empleado->rut ) ? $empleado->rut : '';
    if ( empty( $rut ) && isset( $empleado->ID ) ) {
        $rut = get_user_meta( $empleado->ID, 'rut', true );
    }
    $rut = $rut ?: 'Sin RUT Registrado';

    $cargo = ! empty( $empleado->cargo ) ? $empleado->cargo : '';
    if ( empty( $cargo ) && isset( $empleado->ID ) ) {
        $cargo = get_user_meta( $empleado->ID, 'cargo', true );
    }
    $cargo = $cargo ?: 'Colaborador';

    $unidad = ! empty( $empleado->departamento ) ? $empleado->departamento : '';
    if ( empty( $unidad ) && isset( $empleado->ID ) ) {
        $unidad = get_user_meta( $empleado->ID, 'departamento', true );
    }
    $unidad = $unidad ?: 'General';
    // --- FIN BLOQUE CORREGIDO ---

    $f_inicio = date( 'd/m/Y', strtotime( $fecha_inicio ) );
    $f_fin = date( 'd/m/Y', strtotime( $fecha_fin ) );
    $hoy = date( 'd/m/Y' );

    $fecha_reinc_obj = new DateTime( $fecha_fin );
    $fecha_reinc_obj->modify( '+1 day' );
    $f_reincorporacion = $fecha_reinc_obj->format( 'd/m/Y' );

    $path_logo = dirname( plugin_dir_path( __FILE__ ) ) . '/assets/images/logo.png';
    $logo_base64 = '';

    if ( file_exists( $path_logo ) ) {
        $type = pathinfo( $path_logo, PATHINFO_EXTENSION );
        $data = file_get_contents( $path_logo );
        $logo_base64 = 'data:image/' . $type . ';base64,' . base64_encode( $data );
    }

    $html = '
    <html>
    <head>
        <style>
            body { font-family: "Helvetica", sans-serif; font-size: 10pt; color: #595959; margin: 0; padding: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            .header-bg { background-color: #006666; color: white; padding: 15px; }
            .title-main { font-size: 20pt; font-weight: bold; text-transform: uppercase; margin: 0; }
            .title-green { color: #05FF12; }
            .subtitle { font-size: 10pt; margin-top: 5px; }
            .section-title { border-bottom: 3px solid #FEDE00; color: #006666; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin-bottom: 10px; padding-bottom: 5px; }
            .th-cell { background-color: #006666; color: white; text-transform: uppercase; font-weight: bold; padding: 8px; border: 1px solid white; font-size: 9pt; }
            .td-cell { background-color: #E5EAEE; color: black; padding: 10px; border: 1px solid white; font-weight: bold; text-align: center; }
            .legal-text { margin: 20px 0; font-size: 11pt; line-height: 1.4; color: #333; }
            .signature-box { margin-top: 50px; }
            .signature-line { border-top: 1px solid #333; width: 60%; display: inline-block; margin-top: 40px; }
            .checkbox { display: inline-block; width: 15px; height: 15px; border: 1px solid #333; margin-right: 5px; }
        </style>
    </head>
    <body>
        <table style="margin-bottom: 30px;">
            <tr>
                <td class="header-bg" width="70%">
                    <div class="title-main">Anaconda<span class="title-green">web</span> s.a</div>
                    <div class="subtitle">Av. Bernardo O\'higgins 485 | +56 2 2583 7070</div>
                </td>
                <td class="header-bg" width="30%" align="center">' . ( $logo_base64 ? '<img src="' . $logo_base64 . '" width="150">' : 'LOGOTIPO' ) . '</td>
            </tr>
        </table>

        <div style="text-align: center; font-size: 24pt; font-weight: bold; color: #333; margin-bottom: 20px;">
            SOLICITUD DE VACACIONES
        </div>

        <div class="section-title">Datos del Trabajador</div>
        <table>
            <tr>
                <td class="th-cell" width="15%">FECHA</td>
                <td class="th-cell" width="40%">NOMBRE DEL TRABAJADOR</td>
                <td class="th-cell" width="20%">RUT</td>
                <td class="th-cell" width="25%">CARGO / UNIDAD</td>
            </tr>
            <tr>
                <td class="td-cell">' . $hoy . '</td>
                <td class="td-cell">' . strtoupper( $nombre_completo ) . '</td>
                <td class="td-cell">' . $rut . '</td>
                <td class="td-cell">' . strtoupper( $cargo . ' - ' . $unidad ) . '</td>
            </tr>
        </table>

        <div class="section-title" style="margin-top: 20px;">Periodo de Vacaciones</div>
        <p class="legal-text">
            Por medio de la presente, solicito formalmente la autorizacion para hacer uso de <b>mis dias de vacaciones</b> correspondientes al periodo laboral vigente.
        </p>

        <table>
            <tr>
                <td class="th-cell">FECHA DE INICIO</td>
                <td class="th-cell">FECHA DE TERMINO</td>
                <td class="th-cell">FECHA DE REINCORPORACION</td>
                <td class="th-cell">TOTAL DIAS</td>
            </tr>
            <tr>
                <td class="td-cell">' . $f_inicio . '</td>
                <td class="td-cell">' . $f_fin . '</td>
                <td class="td-cell">' . $f_reincorporacion . '</td>
                <td class="td-cell" style="font-size: 14pt;">' . $total_dias . '</td>
            </tr>
        </table>

        <p class="legal-text">
            Quedo atento(a) a la <b>confirmacion y aprobacion de esta solicitud</b>. Me comprometo a dejar mis tareas debidamente coordinadas con mi jefatura directa antes de mi ausencia.
        </p>

        <div class="signature-box">
            <i>Firma del trabajador:</i> <span class="signature-line"></span><br>
            <b>' . strtoupper( $nombre_completo ) . '</b>
        </div>

        <div class="section-title" style="margin-top: 40px;">Recursos Humanos / Jefatura Directa</div>
        <table>
            <tr>
                <td class="th-cell">RESPUESTA POSITIVA</td>
                <td class="th-cell">RESPUESTA NEGATIVA</td>
                <td class="th-cell">NOMBRE JEFE</td>
                <td class="th-cell">FECHA</td>
            </tr>
            <tr>
                <td class="td-cell"><div class="checkbox"></div> ACEPTADO</td>
                <td class="td-cell"><div class="checkbox"></div> RECHAZADO</td>
                <td class="td-cell" style="height: 30px;"></td>
                <td class="td-cell"></td>
            </tr>
        </table>

        <div style="margin-top: 30px;">
            <i>Firma de RR. HH / Jefatura:</i> ____________________________
        </div>

        <div style="margin-top: 20px; border-top: 1px dotted #999; padding-top: 10px;">
            Observaciones: __________________________________________________________________
        </div>
    </body>
    </html>
    ';

    $options = new Options();
    $options->set( 'isRemoteEnabled', true );

    $dompdf = new Dompdf( $options );
    $dompdf->loadHtml( $html );
    $dompdf->setPaper( 'A4', 'portrait' );
    $dompdf->render();

    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/hrm_temp';

    if ( ! file_exists( $temp_dir ) ) {
        wp_mkdir_p( $temp_dir );
    }

    $filename = 'Solicitud_Vacaciones_' . sanitize_title( $nombre_completo ) . '_' . date( 'Ymd_His' ) . '.pdf';
    $filepath = $temp_dir . '/' . $filename;

    file_put_contents( $filepath, $dompdf->output() );

    return $filepath;
}