<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pantalla de administracion para gestionar escuelas.
 */
function skc_admin_escuelas_page(): void
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos para acceder a esta pagina.', 'textdomain'));
    }

    global $wpdb;
    $tabla_escuelas = $wpdb->prefix . 'skc_escuelas';
    $tabla_semanas = $wpdb->prefix . 'semanas_campamento';
    $tabla_horarios = $wpdb->prefix . 'horarios_semana';

    $escuelas_existe = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tabla_escuelas)) === $tabla_escuelas;

    if (!$escuelas_existe) {
        echo '<div class="wrap">';
        echo '<h1>Escuelas</h1>';
        echo '<div class="notice notice-error"><p>No existe la tabla de escuelas. Ejecuta la Fase 1 y recarga esta pagina.</p></div>';
        echo '</div>';
        return;
    }
    if (isset($_POST['skc_guardar_escuela']) && check_admin_referer('skc_guardar_escuela_nonce')) {
        $escuela_id = isset($_POST['escuela_id']) ? absint($_POST['escuela_id']) : 0;
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $nombre_ca = sanitize_text_field($_POST['nombre_ca'] ?? '');
        $slug_input = sanitize_title($_POST['slug'] ?? '');
        $producto_id = isset($_POST['producto_id']) && $_POST['producto_id'] !== '' ? absint($_POST['producto_id']) : null;
        $activa = isset($_POST['activa']) ? 1 : 0;

        if ($nombre === '') {
            echo '<div class="notice notice-error is-dismissible"><p>El nombre de la escuela es obligatorio.</p></div>';
        } else {
            $slug = $slug_input !== '' ? $slug_input : sanitize_title($nombre);
            $data = [
                'nombre' => $nombre,
                'nombre_ca' => $nombre_ca !== '' ? $nombre_ca : $nombre,
                'slug' => $slug,
                'producto_id' => $producto_id,
                'activa' => $activa,
            ];

            $format = ['%s', '%s', '%s', '%d', '%d'];

            if ($escuela_id > 0) {
                $resultado = $wpdb->update($tabla_escuelas, $data, ['id' => $escuela_id], $format, ['%d']);
                if ($resultado === false) {
                    echo '<div class="notice notice-error is-dismissible"><p>No se pudo actualizar la escuela. Revisa que el slug no este repetido.</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>Escuela actualizada correctamente.</p></div>';
                }
            } else {
                $resultado = $wpdb->insert($tabla_escuelas, $data, $format);
                if ($resultado === false) {
                    echo '<div class="notice notice-error is-dismissible"><p>No se pudo crear la escuela. Revisa que el slug no este repetido.</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>Escuela creada correctamente.</p></div>';
                }
            }
        }
    }

    if (isset($_POST['skc_eliminar_escuela']) && check_admin_referer('skc_eliminar_escuela_nonce')) {
        $escuela_id = absint($_POST['escuela_id'] ?? 0);

        if ($escuela_id > 0) {
            $semanas_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tabla_semanas} WHERE escuela_id = %d",
                    $escuela_id
                )
            );

            if ($semanas_count > 0) {
                echo '<div class="notice notice-warning is-dismissible"><p>No se puede eliminar la escuela porque tiene semanas asociadas.</p></div>';
            } else {
                $resultado = $wpdb->delete($tabla_escuelas, ['id' => $escuela_id], ['%d']);
                if ($resultado === false) {
                    echo '<div class="notice notice-error is-dismissible"><p>No se pudo eliminar la escuela.</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>Escuela eliminada correctamente.</p></div>';
                }
            }
        }
    }

    // Gestion de semanas/horarios por escuela.
    if (isset($_POST['skc_guardar_semana']) && check_admin_referer('skc_guardar_semana_nonce')) {
        $semana_id = absint($_POST['semana_id'] ?? 0);
        $escuela_id_semana = absint($_POST['escuela_id_semana'] ?? 0);
        $semana_es = sanitize_text_field($_POST['semana'] ?? '');
        $semana_ca = sanitize_text_field($_POST['semana_ca'] ?? '');
        $nombre_horario_manana = sanitize_text_field($_POST['nombre_horario_manana'] ?? '9:00h a 14:30h');
        $nombre_horario_completo = sanitize_text_field($_POST['nombre_horario_completo'] ?? '9:00h a 17:00h');
        $plazas_manana = max(0, absint($_POST['plazas_manana'] ?? 0));
        $plazas_completo = max(0, absint($_POST['plazas_completo'] ?? 0));
        $plazas_totales = $plazas_manana + $plazas_completo;

        if ($escuela_id_semana <= 0 || $semana_es === '') {
            echo '<div class="notice notice-error is-dismissible"><p>Debes indicar escuela y semana (ES).</p></div>';
        } elseif ($nombre_horario_manana === '' || $nombre_horario_completo === '') {
            echo '<div class="notice notice-error is-dismissible"><p>Debes indicar la franja de mañana y la franja de completo.</p></div>';
        } elseif (strcasecmp($nombre_horario_manana, $nombre_horario_completo) === 0) {
            echo '<div class="notice notice-error is-dismissible"><p>Los horarios de mañana y completo no pueden tener exactamente el mismo texto.</p></div>';
        } else {
            if ($semana_id > 0) {
                $resultado = $wpdb->update(
                    $tabla_semanas,
                    [
                        'escuela_id' => $escuela_id_semana,
                        'semana' => $semana_es,
                        'semana_ca' => $semana_ca,
                        'plazas_totales' => $plazas_totales,
                    ],
                    ['id' => $semana_id],
                    ['%d', '%s', '%s', '%d'],
                    ['%d']
                );

                if ($resultado === false) {
                    echo '<div class="notice notice-error is-dismissible"><p>No se pudo actualizar la semana. Revisa duplicados o formato.</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>Semana actualizada correctamente.</p></div>';
                }
            } else {
                $resultado = $wpdb->insert(
                    $tabla_semanas,
                    [
                        'escuela_id' => $escuela_id_semana,
                        'semana' => $semana_es,
                        'semana_ca' => $semana_ca,
                        'plazas_totales' => $plazas_totales,
                    ],
                    ['%d', '%s', '%s', '%d']
                );

                if ($resultado === false) {
                    echo '<div class="notice notice-error is-dismissible"><p>No se pudo crear la semana. Revisa duplicados o formato.</p></div>';
                } else {
                    $semana_id = (int) $wpdb->insert_id;
                    echo '<div class="notice notice-success is-dismissible"><p>Semana creada correctamente.</p></div>';
                }
            }

            if ($semana_id > 0) {
                // Mantiene modelo actual: dos horarios base para compatibilidad con el plugin.
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$tabla_horarios} (semana_id, tipo_horario, tipo_horario_ca, nombre_horario, plazas, plazas_reservadas)
                     VALUES (%d, 'mañana', 'mati', %s, %d, 0)
                     ON DUPLICATE KEY UPDATE nombre_horario = VALUES(nombre_horario), plazas = VALUES(plazas)",
                    $semana_id,
                    $nombre_horario_manana,
                    $plazas_manana
                ));

                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$tabla_horarios} (semana_id, tipo_horario, tipo_horario_ca, nombre_horario, plazas, plazas_reservadas)
                     VALUES (%d, 'completo', 'complet', %s, %d, 0)
                     ON DUPLICATE KEY UPDATE nombre_horario = VALUES(nombre_horario), plazas = VALUES(plazas)",
                    $semana_id,
                    $nombre_horario_completo,
                    $plazas_completo
                ));
            }
        }
    }

    if (isset($_POST['skc_eliminar_semana']) && check_admin_referer('skc_eliminar_semana_nonce')) {
        $semana_id = absint($_POST['semana_id'] ?? 0);

        if ($semana_id > 0) {
            $reservadas = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(plazas_reservadas),0) FROM {$tabla_horarios} WHERE semana_id = %d",
                    $semana_id
                )
            );

            if ($reservadas > 0) {
                echo '<div class="notice notice-warning is-dismissible"><p>No se puede eliminar la semana porque tiene plazas reservadas.</p></div>';
            } else {
                $wpdb->delete($tabla_horarios, ['semana_id' => $semana_id], ['%d']);
                $resultado = $wpdb->delete($tabla_semanas, ['id' => $semana_id], ['%d']);

                if ($resultado === false) {
                    echo '<div class="notice notice-error is-dismissible"><p>No se pudo eliminar la semana.</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>Semana eliminada correctamente.</p></div>';
                }
            }
        }
    }

    $editar_id = isset($_GET['editar_escuela']) ? absint($_GET['editar_escuela']) : 0;
    $escuela_editar = null;

    if ($editar_id > 0) {
        $escuela_editar = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$tabla_escuelas} WHERE id = %d",
                $editar_id
            )
        );
    }

    $escuelas = $wpdb->get_results("SELECT * FROM {$tabla_escuelas} ORDER BY id ASC");

    $escuela_gestion_id = isset($_GET['escuela_gestion_id']) ? absint($_GET['escuela_gestion_id']) : 0;
    if ($escuela_gestion_id <= 0 && !empty($escuelas)) {
        $escuela_gestion_id = (int) $escuelas[0]->id;
    }

    $editar_semana_id = isset($_GET['editar_semana']) ? absint($_GET['editar_semana']) : 0;
    $semana_editar = null;

    if ($editar_semana_id > 0) {
        $semana_editar = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*,
                        (SELECT nombre_horario FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'mañana') AS nombre_horario_manana,
                        (SELECT plazas FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'mañana') AS plazas_manana,
                        (SELECT nombre_horario FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'completo') AS nombre_horario_completo,
                        (SELECT plazas FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'completo') AS plazas_completo
                 FROM {$tabla_semanas} s
                 WHERE s.id = %d",
                $editar_semana_id
            )
        );
    }

    if ($semana_editar && $escuela_gestion_id !== (int) $semana_editar->escuela_id) {
        $escuela_gestion_id = (int) $semana_editar->escuela_id;
    }

    $semanas_escuela = [];
    if ($escuela_gestion_id > 0) {
        $semanas_escuela = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*,
                        (SELECT nombre_horario FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'mañana') AS nombre_horario_manana,
                        (SELECT plazas FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'mañana') AS plazas_manana,
                        (SELECT nombre_horario FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'completo') AS nombre_horario_completo,
                        (SELECT plazas FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'completo') AS plazas_completo,
                        (SELECT plazas_reservadas FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'mañana') AS reservadas_manana,
                        (SELECT plazas_reservadas FROM {$tabla_horarios} WHERE semana_id = s.id AND tipo_horario = 'completo') AS reservadas_completo
                 FROM {$tabla_semanas} s
                 WHERE s.escuela_id = %d
                 ORDER BY s.id ASC",
                $escuela_gestion_id
            )
        );
    }

    ?>
    <div class="wrap">
        <h1>Escuelas</h1>
        <p>Gestiona las escuelas disponibles. En esta fase se gestiona el catalogo base de escuelas para vincular semanas y productos.</p>
        <h2>Listado de escuelas</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre (ES)</th>
                    <th>Nombre (CA)</th>
                    <th>Slug</th>
                    <th>Producto ID</th>
                    <th>Activa</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($escuelas)): ?>
                    <?php foreach ($escuelas as $escuela): ?>
                        <tr>
                            <td><?php echo esc_html($escuela->id); ?></td>
                            <td><?php echo esc_html($escuela->nombre); ?></td>
                            <td><?php echo esc_html($escuela->nombre_ca); ?></td>
                            <td><code><?php echo esc_html($escuela->slug); ?></code></td>
                            <td><?php echo $escuela->producto_id ? esc_html($escuela->producto_id) : '-'; ?></td>
                            <td><?php echo (int) $escuela->activa === 1 ? 'Si' : 'No'; ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'escuelas', 'editar_escuela' => (int) $escuela->id], admin_url('admin.php'))); ?>">Editar</a>
                                <a class="button button-small" style="margin-left:6px;" href="<?php echo esc_url(add_query_arg(['page' => 'escuelas', 'escuela_gestion_id' => (int) $escuela->id], admin_url('admin.php'))); ?>">Gestionar semanas</a>
                                <form method="post" action="" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('¿Seguro que deseas eliminar esta escuela?');">
                                    <?php wp_nonce_field('skc_eliminar_escuela_nonce'); ?>
                                    <input type="hidden" name="skc_eliminar_escuela" value="1">
                                    <input type="hidden" name="escuela_id" value="<?php echo esc_attr($escuela->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No hay escuelas registradas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php
        $mostrar_form_escuela = (bool) $escuela_editar || isset($_POST['skc_guardar_escuela']);
        ?>
        <p style="margin-top:14px;">
            <button
                type="button"
                class="button button-primary"
                id="skc-toggle-form-escuela"
                data-show-text="Nueva escuela"
                data-hide-text="Ocultar formulario de escuela"
            >
                <?php echo $mostrar_form_escuela ? 'Ocultar formulario de escuela' : 'Nueva escuela'; ?>
            </button>
        </p>

        <div id="skc-form-escuela" style="display: <?php echo $mostrar_form_escuela ? 'block' : 'none'; ?>; margin-top:10px;">
            <h3><?php echo $escuela_editar ? 'Editar escuela' : 'Nueva escuela'; ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field('skc_guardar_escuela_nonce'); ?>
                <input type="hidden" name="skc_guardar_escuela" value="1">
                <input type="hidden" name="escuela_id" value="<?php echo esc_attr($escuela_editar->id ?? 0); ?>">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="nombre">Nombre (ES)</label></th>
                            <td><input type="text" name="nombre" id="nombre" class="regular-text" required value="<?php echo esc_attr($escuela_editar->nombre ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="nombre_ca">Nombre (CA)</label></th>
                            <td><input type="text" name="nombre_ca" id="nombre_ca" class="regular-text" value="<?php echo esc_attr($escuela_editar->nombre_ca ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="slug">Slug</label></th>
                            <td><input type="text" name="slug" id="slug" class="regular-text" value="<?php echo esc_attr($escuela_editar->slug ?? ''); ?>"><p class="description">Si lo dejas vacio, se genera automaticamente desde el nombre.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="producto_id">Producto WooCommerce (ID)</label></th>
                            <td><input type="number" min="1" name="producto_id" id="producto_id" class="small-text" value="<?php echo esc_attr($escuela_editar->producto_id ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Activa</th>
                            <td><label><input type="checkbox" name="activa" value="1" <?php checked((int) ($escuela_editar->activa ?? 1), 1); ?>> Escuela activa</label></td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button($escuela_editar ? 'Actualizar escuela' : 'Crear escuela'); ?>
            </form>
        </div>

        <hr>
        <h2>Semanas y horarios por escuela</h2>
        <form method="get" action="" style="margin-bottom:12px;">
            <input type="hidden" name="page" value="escuelas">
            <label for="escuela_gestion_id"><strong>Escuela a gestionar:</strong></label>
            <select name="escuela_gestion_id" id="escuela_gestion_id" style="min-width:260px; margin-left:8px;">
                <?php foreach ($escuelas as $escuela_option): ?>
                    <option value="<?php echo esc_attr($escuela_option->id); ?>" <?php selected((int) $escuela_gestion_id, (int) $escuela_option->id); ?>>
                        <?php echo esc_html($escuela_option->nombre . ' (#' . $escuela_option->id . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">Cargar</button>
        </form>

        <?php if ($escuela_gestion_id > 0): ?>
            <h3 style="margin-top:24px;">Listado de semanas</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Semana (ES)</th>
                        <th>Semana (CA)</th>
                        <th>Manana</th>
                        <th>Completo</th>
                        <th>Plazas totales</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($semanas_escuela)): ?>
                        <?php foreach ($semanas_escuela as $semana_row): ?>
                            <tr>
                                <td><?php echo esc_html($semana_row->id); ?></td>
                                <td><?php echo esc_html($semana_row->semana); ?></td>
                                <td><?php echo esc_html($semana_row->semana_ca ?: '-'); ?></td>
                                <td>
                                    <?php echo esc_html((int) ($semana_row->plazas_manana ?? 0)); ?> / res. <?php echo esc_html((int) ($semana_row->reservadas_manana ?? 0)); ?>
                                    <div style="margin-top:4px; color:#50575e; font-size:12px;"><?php echo esc_html($semana_row->nombre_horario_manana ?: '9:00h a 14:30h'); ?></div>
                                </td>
                                <td>
                                    <?php echo esc_html((int) ($semana_row->plazas_completo ?? 0)); ?> / res. <?php echo esc_html((int) ($semana_row->reservadas_completo ?? 0)); ?>
                                    <div style="margin-top:4px; color:#50575e; font-size:12px;"><?php echo esc_html($semana_row->nombre_horario_completo ?: '9:00h a 17:00h'); ?></div>
                                </td>
                                <td><?php echo esc_html((int) $semana_row->plazas_totales); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'escuelas', 'escuela_gestion_id' => (int) $escuela_gestion_id, 'editar_semana' => (int) $semana_row->id], admin_url('admin.php'))); ?>">Editar</a>
                                    <form method="post" action="" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Seguro que deseas eliminar esta semana?');">
                                        <?php wp_nonce_field('skc_eliminar_semana_nonce'); ?>
                                        <input type="hidden" name="skc_eliminar_semana" value="1">
                                        <input type="hidden" name="semana_id" value="<?php echo esc_attr($semana_row->id); ?>">
                                        <button type="submit" class="button button-small button-link-delete">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">No hay semanas registradas para esta escuela.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $mostrar_form_semana = (bool) $semana_editar || isset($_POST['skc_guardar_semana']);
            ?>
            <p style="margin-top:14px;">
                <button
                    type="button"
                    class="button button-primary"
                    id="skc-toggle-form-semana"
                    data-show-text="Nueva semana"
                    data-hide-text="Ocultar formulario de semana"
                >
                    <?php echo $mostrar_form_semana ? 'Ocultar formulario de semana' : 'Nueva semana'; ?>
                </button>
            </p>

            <div id="skc-form-semana" style="display: <?php echo $mostrar_form_semana ? 'block' : 'none'; ?>; margin-top:10px;">
                <h3><?php echo $semana_editar ? 'Editar semana' : 'Nueva semana'; ?></h3>
                <form method="post" action="">
                    <?php wp_nonce_field('skc_guardar_semana_nonce'); ?>
                    <input type="hidden" name="skc_guardar_semana" value="1">
                    <input type="hidden" name="semana_id" value="<?php echo esc_attr($semana_editar->id ?? 0); ?>">

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="escuela_id_semana">Escuela</label></th>
                                <td>
                                    <select name="escuela_id_semana" id="escuela_id_semana" required>
                                        <?php foreach ($escuelas as $escuela_option): ?>
                                            <option value="<?php echo esc_attr($escuela_option->id); ?>" <?php selected((int) ($semana_editar->escuela_id ?? $escuela_gestion_id), (int) $escuela_option->id); ?>>
                                                <?php echo esc_html($escuela_option->nombre . ' (#' . $escuela_option->id . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="semana">Semana (ES)</label></th>
                                <td><input type="text" name="semana" id="semana" class="regular-text" required value="<?php echo esc_attr($semana_editar->semana ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="semana_ca">Semana (CA)</label></th>
                                <td><input type="text" name="semana_ca" id="semana_ca" class="regular-text" value="<?php echo esc_attr($semana_editar->semana_ca ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="nombre_horario_manana">Franja manana</label></th>
                                <td><input type="text" name="nombre_horario_manana" id="nombre_horario_manana" class="regular-text" required value="<?php echo esc_attr($semana_editar->nombre_horario_manana ?? '9:00h a 14:30h'); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="plazas_manana">Plazas manana</label></th>
                                <td><input type="number" min="0" name="plazas_manana" id="plazas_manana" class="small-text" required value="<?php echo esc_attr((int) ($semana_editar->plazas_manana ?? 0)); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="nombre_horario_completo">Franja completo</label></th>
                                <td><input type="text" name="nombre_horario_completo" id="nombre_horario_completo" class="regular-text" required value="<?php echo esc_attr($semana_editar->nombre_horario_completo ?? '9:00h a 17:00h'); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="plazas_completo">Plazas completo</label></th>
                                <td><input type="number" min="0" name="plazas_completo" id="plazas_completo" class="small-text" required value="<?php echo esc_attr((int) ($semana_editar->plazas_completo ?? 0)); ?>"></td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button($semana_editar ? 'Actualizar semana' : 'Crear semana'); ?>
                    <?php if ($semana_editar): ?>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'escuelas', 'escuela_gestion_id' => (int) $escuela_gestion_id], admin_url('admin.php'))); ?>">Cancelar edicion</a>
                    <?php endif; ?>
                </form>
            </div>
        <?php else: ?>
            <div class="notice notice-warning inline"><p>Primero crea al menos una escuela para poder gestionar semanas.</p></div>
        <?php endif; ?>

        <script>
            (function () {
                function activarToggle(buttonId, panelId) {
                    var button = document.getElementById(buttonId);
                    var panel = document.getElementById(panelId);
                    if (!button || !panel) {
                        return;
                    }

                    var showText = button.getAttribute('data-show-text') || 'Mostrar formulario';
                    var hideText = button.getAttribute('data-hide-text') || 'Ocultar formulario';

                    button.addEventListener('click', function () {
                        var visible = panel.style.display !== 'none';
                        panel.style.display = visible ? 'none' : 'block';
                        button.textContent = visible ? showText : hideText;
                    });
                }

                activarToggle('skc-toggle-form-escuela', 'skc-form-escuela');
                activarToggle('skc-toggle-form-semana', 'skc-form-semana');
            })();
        </script>
    </div>
    <?php
}