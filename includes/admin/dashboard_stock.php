<?php
// Evitar acceso directo    
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtiene el listado de escuelas para filtros de admin.
 */
function skc_obtener_escuelas_admin(): array
{
    global $wpdb;
    $tabla_escuelas = $wpdb->prefix . 'skc_escuelas';

    $existe_tabla = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tabla_escuelas)) === $tabla_escuelas;
    if (!$existe_tabla) {
        return [];
    }

    return $wpdb->get_results("SELECT id, nombre, activa FROM {$tabla_escuelas} ORDER BY id ASC", ARRAY_A) ?: [];
}



function pagina_admin_gestion_campamento()
{
    global $wpdb;
    $tabla_semanas = $wpdb->prefix . 'semanas_campamento';
    $tabla_horarios = $wpdb->prefix . 'horarios_semana';
    $escuelas = skc_obtener_escuelas_admin();

    $escuela_id = isset($_GET['escuela_id']) ? absint($_GET['escuela_id']) : 0;
    if ($escuela_id <= 0 && !empty($escuelas)) {
        $escuela_id = (int) $escuelas[0]['id'];
    }

    // Procesar formulario de actualización si se envió
    if (isset($_POST['actualizar_semanas']) && check_admin_referer('actualizar_semanas_nonce')) {
        $escuela_id = isset($_POST['escuela_id']) ? absint($_POST['escuela_id']) : $escuela_id;

        foreach ($_POST['semana'] as $id => $datos) {
            $semana_id = absint($id);

            // Seguridad: solo permite actualizar semanas de la escuela seleccionada.
            $semana_valida = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla_semanas} WHERE id = %d AND escuela_id = %d",
                $semana_id,
                $escuela_id
            ));

            if ($semana_valida !== 1) {
                continue;
            }

            // Calcular plazas totales como suma de mañana y completo
            $plazas_totales = intval($datos['plazas_manana']) + intval($datos['plazas_completo']);

            // Actualizar plazas totales
            $wpdb->update(
                $tabla_semanas,
                ['plazas_totales' => $plazas_totales],
                ['id' => $semana_id]
            );

            // Actualizar plazas horario mañana
            $wpdb->update(
                $tabla_horarios,
                ['plazas' => intval($datos['plazas_manana'])],
                [
                    'semana_id' => $semana_id,
                    'tipo_horario' => 'mañana'
                ]
            );

            // Actualizar plazas horario completo
            $wpdb->update(
                $tabla_horarios,
                ['plazas' => intval($datos['plazas_completo'])],
                [
                    'semana_id' => $semana_id,
                    'tipo_horario' => 'completo'
                ]
            );
        }

        echo '<div class="notice notice-success is-dismissible"><p>Plazas actualizadas correctamente.</p></div>';
    }

    // Obtener semanas existentes con sus horarios
    $semanas = $wpdb->get_results($wpdb->prepare("
            SELECT s.*,
                (SELECT plazas FROM $tabla_horarios WHERE semana_id = s.id AND tipo_horario = 'mañana') as plazas_manana,
                (SELECT plazas FROM $tabla_horarios WHERE semana_id = s.id AND tipo_horario = 'completo') as plazas_completo,
                (SELECT plazas_reservadas FROM $tabla_horarios WHERE semana_id = s.id AND tipo_horario = 'mañana') as reservas_manana,
                (SELECT plazas_reservadas FROM $tabla_horarios WHERE semana_id = s.id AND tipo_horario = 'completo') as reservas_completo
            FROM $tabla_semanas s
            WHERE s.escuela_id = %d
            ORDER BY s.id
        ", $escuela_id));

    ?>
    <div class="wrap">
        <h1>Gestión de Plazas por Semanas y Horarios</h1>

        <?php if (!empty($escuelas)): ?>
            <form method="get" action="" style="margin: 12px 0 18px 0;">
                <input type="hidden" name="page" value="horarios">
                <label for="escuela_id"><strong>Escuela:</strong></label>
                <select name="escuela_id" id="escuela_id">
                    <?php foreach ($escuelas as $escuela): ?>
                        <option value="<?php echo esc_attr($escuela['id']); ?>" <?php selected((int) $escuela_id, (int) $escuela['id']); ?>>
                            <?php echo esc_html($escuela['nombre']); ?><?php echo (int) $escuela['activa'] === 1 ? '' : ' (inactiva)'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit">Filtrar</button>
            </form>
        <?php else: ?>
            <div class="notice notice-warning"><p>No hay escuelas registradas. Crea una en SportyKidsCamp -> Escuelas.</p></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('actualizar_semanas_nonce'); ?>
            <input type="hidden" name="actualizar_semanas" value="1">
            <input type="hidden" name="escuela_id" value="<?php echo esc_attr($escuela_id); ?>">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Plazas Totales</th>
                        <th>Mañana</th>
                        <th>Reservas</th>
                        <th>Completo</th>
                        <th>Reservas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($semanas)): ?>
                        <?php foreach ($semanas as $semana): ?>
                            <tr>
                                <td><?php echo esc_html($semana->semana); ?></td>
                                <td>
                                    <input type="text" value="<?php echo esc_attr(((int) $semana->plazas_manana + (int) $semana->reservas_manana + (int) $semana->plazas_completo + (int) $semana->reservas_completo)); ?>" readonly
                                        class="small-text" style="background-color: #f0f0f0;">
                                </td>
                                <td>
                                    <input type="number" name="semana[<?php echo $semana->id; ?>][plazas_manana]"
                                        value="<?php echo esc_attr((int) $semana->plazas_manana); ?>" min="0" class="small-text"
                                        data-semana-id="<?php echo $semana->id; ?>"
                                        onchange="actualizarPlazasTotales(<?php echo $semana->id; ?>)">
                                </td>
                                <td>
                                    <strong><?php echo intval($semana->reservas_manana); ?></strong>
                                </td>
                                <td>
                                    <input type="number" name="semana[<?php echo $semana->id; ?>][plazas_completo]"
                                        value="<?php echo esc_attr((int) $semana->plazas_completo); ?>" min="0" class="small-text"
                                        data-semana-id="<?php echo $semana->id; ?>"
                                        onchange="actualizarPlazasTotales(<?php echo $semana->id; ?>)">
                                </td>
                                <td>
                                    <strong><?php echo intval($semana->reservas_completo); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No hay semanas registradas para esta escuela.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($semanas)): ?>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar Cambios">
                </p>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

