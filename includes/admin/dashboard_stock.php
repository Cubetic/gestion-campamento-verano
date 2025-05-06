<?php
// Evitar acceso directo    
if (!defined('ABSPATH')) {
    exit;
}



function pagina_admin_gestion_campamento()
{
    global $wpdb;
    $tabla_semanas = $wpdb->prefix . 'semanas_campamento';
    $tabla_horarios = $wpdb->prefix . 'horarios_semana';

    // Procesar formulario de actualización si se envió
    if (isset($_POST['actualizar_semanas']) && check_admin_referer('actualizar_semanas_nonce')) {
        foreach ($_POST['semana'] as $id => $datos) {
            // Calcular plazas totales como suma de mañana y completo
            $plazas_totales = intval($datos['plazas_manana']) + intval($datos['plazas_completo']);

            // Actualizar plazas totales
            $wpdb->update(
                $tabla_semanas,
                ['plazas_totales' => $plazas_totales],
                ['id' => $id]
            );

            // Actualizar plazas horario mañana
            $wpdb->update(
                $tabla_horarios,
                ['plazas' => intval($datos['plazas_manana'])],
                [
                    'semana_id' => $id,
                    'tipo_horario' => 'mañana'
                ]
            );

            // Actualizar plazas horario completo
            $wpdb->update(
                $tabla_horarios,
                ['plazas' => intval($datos['plazas_completo'])],
                [
                    'semana_id' => $id,
                    'tipo_horario' => 'completo'
                ]
            );
        }

        echo '<div class="notice notice-success is-dismissible"><p>Plazas actualizadas correctamente.</p></div>';
    }

    // Obtener semanas existentes con sus horarios
    $semanas = $wpdb->get_results("
            SELECT s.*,
                (SELECT plazas FROM $tabla_horarios WHERE semana_id = s.id AND tipo_horario = 'mañana') as plazas_manana,
                (SELECT plazas FROM $tabla_horarios WHERE semana_id = s.id AND tipo_horario = 'completo') as plazas_completo,
                (SELECT plazas_reservadas FROM $tabla_horarios WHERE semana_id = s.id AND tipo_horario = 'mañana') as reservas_manana,
                (SELECT plazas_reservadas FROM $tabla_horarios WHERE semana_id = s.id AND tipo_horario = 'completo') as reservas_completo
            FROM $tabla_semanas s
            ORDER BY s.id
        ");

    ?>
    <div class="wrap">
        <h1>Gestión de Plazas por Semanas y Horarios</h1>

        <form method="post" action="">
            <?php wp_nonce_field('actualizar_semanas_nonce'); ?>
            <input type="hidden" name="actualizar_semanas" value="1">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Plazas Totales</th>
                        <th>Diponibles de 9:00 a 14:30</th>
                        <th>Reservas</th>
                        <th>Disponibles de 9:00 a 17:00</th>
                        <th>Reservas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($semanas as $semana): ?>
                        <tr>
                            <td><?php echo esc_html($semana->semana); ?></td>
                            <td>
                                <input type="text" value="<?php echo esc_attr(($semana->plazas_manana + $semana->reservas_manana + $semana->plazas_completo + $semana->reservas_completo)); ?>" readonly
                                    class="small-text" style="background-color: #f0f0f0;">
                            </td>
                            <td>
                                <input type="number" name="semana[<?php echo $semana->id; ?>][plazas_manana]"
                                    value="<?php echo esc_attr($semana->plazas_manana); ?>" min="0" class="small-text"
                                    data-semana-id="<?php echo $semana->id; ?>"
                                    onchange="actualizarPlazasTotales(<?php echo $semana->id; ?>)">
                            </td>
                            <td>
                                <strong><?php echo intval($semana->reservas_manana); ?></strong>
                            </td>
                            <td>
                                <input type="number" name="semana[<?php echo $semana->id; ?>][plazas_completo]"
                                    value="<?php echo esc_attr($semana->plazas_completo); ?>" min="0" class="small-text"
                                    data-semana-id="<?php echo $semana->id; ?>"
                                    onchange="actualizarPlazasTotales(<?php echo $semana->id; ?>)">
                            </td>
                            <td>
                                <strong><?php echo intval($semana->reservas_completo); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar Cambios">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Verifica si una semana y horario están completos
 * 
 * @param string $semana La semana a verificar
 * @param string $tipo_horario El tipo de horario ('mañana' o 'completo')
 * @return bool True si está completo, false si hay plazas disponibles
 */
function esta_completo($semana, $tipo_horario = 'mañana')
{
    $plazas = obtener_plazas_disponibles($semana, $tipo_horario);
    return ($plazas !== false && $plazas <= 0);
}