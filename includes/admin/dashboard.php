<?php
/**
 * Template Name: Dashboard de Reservas
 */

// Evitar acceso directo    
if (!defined('ABSPATH')) {
    exit;
}

 
if (isset($_GET['exportar_csv_v2']) && $_GET['exportar_csv_v2'] === 'true') {
    error_log('Exportación CSV v2 iniciada.');
    if (current_user_can('manage_options')) {
        exportar_csv_funcion_v2();
    } else {
        wp_die('No tienes permisos para realizar esta acción.');
    }
}

if (isset($_GET['exportar_csv']) && $_GET['exportar_csv'] === 'true') {
    error_log('Exportación CSV iniciada.'); // Verifica que esta línea se ejecute
    // Verifica permisos
    if (current_user_can('manage_options')) {
        exportar_csv_funcion(); // Llama a la función que exporta el CSV
    } else {
        wp_die('No tienes permisos para realizar esta acción.');
    }
}


/**
 * Comprueba si el usuario tiene permisos para gestionar WooCommerce.
 * - Puedes cambiar 'manage_woocommerce' por otro capability o rol si lo prefieres.
 */
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('No tienes permisos para ver esta página.', 'textdomain'));
}

if (isset($_POST['guardar_cambios'])) {
    check_admin_referer('editar_pedido_nonce', 'editar_pedido_nonce_field');

    if (!empty($_POST['order_id'])) {
        $order_id = absint($_POST['order_id']);
        $pedido = wc_get_order($order_id);
        if ($pedido) {
            // Actualizar datos_alumno
            if ( isset($_POST['datos_alumno']) && is_array($_POST['datos_alumno']) ) {
                $datos_alumno_limpios = [];
    
                // Sanitizamos todos los valores recibidos
                foreach ( $_POST['datos_alumno'] as $key => $valor ) {
                    $datos_alumno_limpios[$key] = sanitize_text_field($valor);
                }
    
                // Actualizamos la meta con los valores limpios
                $pedido->update_meta_data('datos_alumno', $datos_alumno_limpios);
    
                // Actualizamos la variable $datos_alumno para que los cambios se vean de inmediato en el form
                //$datos_alumno = $datos_alumno_limpios;
            }
            // --- Actualizar plazas_reservadas ---
            if (isset($_POST['plazas_reservadas']) && is_array($_POST['plazas_reservadas'])) {
                // 0. Obtenemos el array antiguo guardado en el pedido para preservar contexto (escuela_id).
                $old_plazas = $pedido->get_meta('plazas_reservadas');
                if (!is_array($old_plazas)) {
                    $old_plazas = [];
                }

                // 1. Armamos el nuevo array con los datos enviados
                $nuevo_plazas = [];
                foreach ($_POST['plazas_reservadas'] as $semana_label => $datos_semana) {
                    // Usamos el select para el cambio de semana. Si el usuario no cambia nada, el select tiene como valor la misma semana.
                    $nuevo_label = isset($_POST['plazas_reservadas_nuevo_label'][$semana_label])
                        ? sanitize_text_field($_POST['plazas_reservadas_nuevo_label'][$semana_label])
                        : $semana_label;
                    if (empty($nuevo_label)) {
                        $nuevo_label = $semana_label;
                    }

                    $escuela_id_reserva = isset($old_plazas[$semana_label]['escuela_id'])
                        ? absint($old_plazas[$semana_label]['escuela_id'])
                        : 0;

                    // Guardamos los campos (horario, acogida y beca) de la reserva
                    $nuevo_plazas[$nuevo_label] = [
                        'horario' => sanitize_text_field($datos_semana['horario'] ?? ''),
                        'acogida' => sanitize_text_field($datos_semana['acogida'] ?? ''),
                        'beca' => sanitize_text_field($datos_semana['beca'] ?? ''),
                        'escuela_id' => $escuela_id_reserva,
                    ];
                }

                // 3. Calculamos qué semanas se han "removido" (cambiadas) y cuáles se han "agregado"
                $old_keys = array_keys($old_plazas);
                $new_keys = array_keys($nuevo_plazas);
                $keys_removed = array_diff($old_keys, $new_keys); // semanas antiguas que ya no están
                $keys_added = array_diff($new_keys, $old_keys);   // nuevas semanas que se han agregado

                global $wpdb;
                $tabla_horarios = $wpdb->prefix . 'horarios_semana';
                // 4. Para cada semana que se ha removido (cambio de semana), se reponen la plaza
                foreach ($keys_removed as $old_key) {
                    $old_datos = $old_plazas[$old_key] ?? [];
                    $escuela_id = isset($old_datos['escuela_id']) ? absint($old_datos['escuela_id']) : null;
                    $semana_id = skc_obtener_semana_id_por_nombre($old_key, $escuela_id);

                    if (!$semana_id) {
                        error_log("No se pudo resolver la semana antigua '$old_key' al editar el pedido.");
                        continue;
                    }

                    // Obtenemos el tipo de horario que estaba reservado en la semana antigua
                    $tipo = $old_datos['horario'];

                    $horario = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, plazas, plazas_reservadas FROM {$tabla_horarios} WHERE semana_id = %d AND tipo_horario = %s",
                        $semana_id,
                        $tipo
                    ));
                    if ($horario) {
                        // Reponer: incrementar plazas y decrementar plazas_reservadas
                        $nuevas_plazas = $horario->plazas + 1;
                        $nuevas_reservas = max(0, $horario->plazas_reservadas - 1);
                        $wpdb->update(
                            $tabla_horarios,
                            ['plazas' => $nuevas_plazas, 'plazas_reservadas' => $nuevas_reservas],
                            ['id' => $horario->id]
                        );
                        error_log("Repuesta plaza en semana '$old_key' (ID: {$horario->id}): Plazas: $nuevas_plazas, Reservadas: $nuevas_reservas");
                    }
                }

                // 5. Para cada nueva semana agregada, se descuenta la plaza
                foreach ($keys_added as $new_key) {
                    $new_datos = $nuevo_plazas[$new_key];
                    $escuela_id = isset($new_datos['escuela_id']) ? absint($new_datos['escuela_id']) : null;
                    $semana_id = skc_obtener_semana_id_por_nombre($new_key, $escuela_id);

                    if (!$semana_id) {
                        error_log("No se pudo resolver la semana nueva '$new_key' al editar el pedido.");
                        continue;
                    }

                    $tipo = $new_datos['horario'];

                    $horario = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, plazas, plazas_reservadas FROM {$tabla_horarios} WHERE semana_id = %d AND tipo_horario = %s",
                        $semana_id,
                        $tipo
                    ));
                    if ($horario) {
                        // Verificar disponibilidad (opcional)
                        if ($horario->plazas > 0) {
                            $nuevas_plazas = $horario->plazas - 1;
                            $nuevas_reservas = $horario->plazas_reservadas + 1;
                            $wpdb->update(
                                $tabla_horarios,
                                ['plazas' => $nuevas_plazas, 'plazas_reservadas' => $nuevas_reservas],
                                ['id' => $horario->id]
                            );
                            error_log("Descontada plaza en nueva semana '$new_key' (ID: {$horario->id}): Plazas: $nuevas_plazas, Reservadas: $nuevas_reservas");
                        } else {
                            error_log("No hay plazas disponibles en nueva semana '$new_key' (tipo: $tipo)");
                        }
                    }
                }

                // 6. Finalmente, actualizamos el meta con el nuevo array (ya con las claves actualizadas)
                $pedido->update_meta_data('plazas_reservadas', $nuevo_plazas);
            }

            $pedido->save();
            // Redirige para evitar reenvío de datos y forzar recarga limpia
            wp_safe_redirect(remove_query_arg(array('guardar_cambios'), $_SERVER['REQUEST_URI']));
            exit;
        }
    }
}


?>
<div class="wrap">
    <h1>Reservas de Sportu Kids Camp </h1>
<a href="<?php echo esc_url(add_query_arg('exportar_csv', 'true')); ?>" class="button">Exportar CSV</a>

<?php
// Selector de escuela para exportar CSV v2 por escuela
global $wpdb;
$escuelas_selector = $wpdb->get_results( "SELECT id, nombre FROM {$wpdb->prefix}skc_escuelas ORDER BY nombre ASC" );
if ( ! empty( $escuelas_selector ) ) :
    // Preservar parámetros admin actuales (page=...) en el form
    $current_page_param = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
?>
<form method="get" action="" style="display:inline-block; margin-left:12px;">
    <?php if ( $current_page_param ) : ?>
        <input type="hidden" name="page" value="<?php echo esc_attr( $current_page_param ); ?>">
    <?php endif; ?>
    <input type="hidden" name="exportar_csv_v2" value="true">
    <select name="escuela_id" style="vertical-align:middle;">
        <option value="">-- Selecciona escuela --</option>
        <?php foreach ( $escuelas_selector as $esc ) : ?>
            <option value="<?php echo esc_attr( $esc->id ); ?>"><?php echo esc_html( $esc->nombre ); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="button button-primary" style="vertical-align:middle;">Exportar CSV por escuela</button>
</form>
<?php endif; ?>
    <?php
    // Distinguimos entre "editar un pedido" o "listar pedidos"
    if (isset($_GET['editar_pedido']) && !empty($_GET['editar_pedido'])) {
        $order_id = absint($_GET['editar_pedido']);
        mostrar_formulario_edicion_pedido($order_id);
    } else {
        mostrar_tabla_pedidos();
    }
    ?>
</div>
<?php
/**
 * Muestra la tabla con todos los pedidos.
 */
function mostrar_tabla_pedidos()
{
    // Obtener todos los pedidos
    $args = [
    'status' => ['wc-processing', 'completed'],
    'limit'  => -1,
    'type'   => 'shop_order'
    ];
    $pedidos = wc_get_orders($args);

    // Preparamos la tabla
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>
            <tr>
                <th>#</th>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Nombre Alumno</th>
                <th>Apellido Alumno</th>
                <th>Fecha de nacimiento</th>
				<th>Total</th>
                <th>Redsys</th>
				<th>Pago</th>
                <th>Acciones</th>
            </tr>
          </thead>';
    echo '<tbody>';
    $contador = 1; // Inicializamos el contador
    foreach ($pedidos as $pedido) {
         // Aseguramos que se trata de un pedido normal
        if (! method_exists($pedido, 'get_billing_first_name')) {
            continue;
        }
        $order_id = $pedido->get_id();

        // Información del comprador (quien realizó el pedido)
        $datos_pedido = 'Num: ' . $order_id;
        $cliente = $pedido->get_billing_first_name() . ' ' . $pedido->get_billing_last_name();
		 // Total del pedido
        $total_pedido = $pedido->get_total(); // float

        // Pedido de Redsys (ajusta según tu meta)
        // Ejemplo: si guardas en '_redsys_order_number'
        $redsys_order_number = $pedido->get_meta('rgw_ds_order');

        // Información del alumno, obtenida de la meta 'datos_alumno'
        $datos = $pedido->get_meta('datos_alumno');
        $nombre_alumno = isset($datos['nombre_alumno']) ? esc_html($datos['nombre_alumno']) : '';
        $apellido_alumno = isset($datos['apellido_alumno']) ? esc_html($datos['apellido_alumno']) : '';
        $fecha_nacimiento = isset($datos['fecha_nacimiento']) ? esc_html($datos['fecha_nacimiento']) : '';
		
		// --- LÓGICA DE LA COLUMNA "Pago" ---
        $fracciones = $pedido->get_meta('fracciones'); // Array con info de pagos
        $estado_pago = 'Completo'; // Valor por defecto

        if (! empty($fracciones) && is_array($fracciones)) {
            // Filtrar fracciones con estado pendiente
            $pendientes = array_filter($fracciones, function($fraccion){
                return (isset($fraccion['estado']) && strtolower($fraccion['estado']) === 'pendiente');
            });

            if (! empty($pendientes)) {
                // Ordenamos por fecha para mostrar el siguiente pago pendiente
                usort($pendientes, function($a, $b){
                    return strtotime($a['fecha']) - strtotime($b['fecha']);
                });
                // Tomamos la primera fracción pendiente
                $prox = reset($pendientes);
                
                // Construimos el texto "DD/MM/YYYY - XXX €"
                $fecha_prox = date_i18n('d/m/Y', strtotime($prox['fecha']));
                $importe_prox = isset($prox['importe']) ? floatval($prox['importe']) : 0;
                $estado_pago = "Fraccionado\n(próx $fecha_prox-$importe_prox)";
            }
        }


        echo '<tr>';
        echo '<td>' . esc_html($contador++) . '</td>';
        echo '<td>' . esc_html($datos_pedido) . '</td>';
        echo '<td>' . esc_html($cliente) . '</td>';
        echo '<td>' . esc_html($nombre_alumno) . '</td>';
        echo '<td>' . esc_html($apellido_alumno) . '</td>';
        echo '<td>' . esc_html($fecha_nacimiento) . '</td>';
		echo '<td>' . esc_html($total_pedido) . '</td>'; // Formato moneda con wc_price
        echo '<td>' . esc_html($redsys_order_number) . '</td>';
		echo '<td style="white-space: pre-line;">' . nl2br($estado_pago) . '</td>';
        echo '<td>';
        // Botón para ir a la edición en esta misma plantilla
        $edit_url = add_query_arg(['editar_pedido' => $order_id]);
        echo '<a class="button" href="' . esc_url($edit_url) . '">Editar</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * Ejemplo de función para mostrar un formulario que edita TODOS los campos
 * de la meta key 'datos_alumno' de un pedido de WooCommerce.
 */

function mostrar_formulario_edicion_pedido($order_id)
{
    // Asegurarnos de que el usuario tiene permisos (puedes ajustarlo)
    if (!current_user_can('manage_woocommerce')) {
        wp_die('No tienes permisos para editar este pedido.');
    }

    // Obtener el pedido
    $pedido = wc_get_order($order_id);
    if (!$pedido) {
        echo '<p>Pedido no encontrado.</p>';
        return;
    }

    // Obtener el array 'datos_alumno'
    $datos_alumno = $pedido->get_meta('datos_alumno');
    if (!is_array($datos_alumno)) {
        $datos_alumno = []; // Si no existe o no es un array, iniciamos vacío
    }

    // Recupera el meta 'plazas_reservadas'
    $plazas_reservadas = $pedido->get_meta('plazas_reservadas');
    if (!is_array($plazas_reservadas)) {
        $plazas_reservadas = []; // si no existe o no es array, iniciamos vacío
    }

    // Mostrar formulario
    ?>
    <h2>Editar Pedido #<?php echo esc_html($order_id); ?></h2>

    <form method="post" class="dashboard-form-container">
        <?php
        // Nonce para seguridad
        wp_nonce_field('editar_pedido_nonce', 'editar_pedido_nonce_field');
        ?>
        <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">

        <h3>Datos del Alumno</h3>
        <?php if (!empty($datos_alumno)): ?>
            <div class="form-grid">
                <?php foreach ($datos_alumno as $campo => $valor): ?>
                    <div class="form-field">
                        <label for="campo_<?php echo esc_attr($campo); ?>">
                            <?php echo esc_html($campo); ?>
                        </label>
                        <input type="text" id="campo_<?php echo esc_attr($campo); ?>"
                            name="datos_alumno[<?php echo esc_attr($campo); ?>]" value="<?php echo esc_attr($valor); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No hay campos en <code>datos_alumno</code> para este pedido.</p>
        <?php endif; ?>

        <h3>Plazas Reservadas</h3>

        <?php
        // Obtenemos todas las semanas disponibles de la BD
        $semanas_bd = obtener_semanas_disponibles();
        ?>

        <?php if (!empty($plazas_reservadas) && is_array($plazas_reservadas)):


            $contador = 1;
            foreach ($plazas_reservadas as $semana_label => $datos_semana): ?>
                <fieldset style="margin-bottom: 20px; padding: 10px; border:1px solid #ccc;">
                    <legend><strong><?php echo esc_html('Semana ' . $contador); ?></strong></legend>
                    <div class="form-grid">

                        <!-- SELECT para cambiar la semana -->
                        <div class="form-field">
                            <label>Semana reservada</label>
                            <select name="plazas_reservadas_nuevo_label[<?php echo esc_attr($semana_label); ?>]">
                                <option value="<?php echo esc_html($semana_label); ?>"><?php echo esc_html($semana_label); ?>
                                </option>
                                <?php
                                if (!empty($semanas_bd)) {
                                    foreach ($semanas_bd as $nombre_semana) {
                                        ?>
                                        <option value="<?php echo esc_attr($nombre_semana); ?>">
                                            <?php echo esc_html($nombre_semana); ?>
                                        </option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <!-- Horario -->
                        <div class="form-field">
                            <label for="horario_<?php echo esc_attr($semana_label); ?>">
                                Horario
                            </label>
                            <input type="text" id="horario_<?php echo esc_attr($semana_label); ?>"
                                name="plazas_reservadas[<?php echo esc_attr($semana_label); ?>][horario]"
                                value="<?php echo esc_attr(isset($datos_semana['horario']) ? $datos_semana['horario'] : ''); ?>">
                        </div>

                        <!-- Acogida -->
                        <div class="form-field">
                            <label for="acogida_<?php echo esc_attr($semana_label); ?>">
                                Acogida
                            </label>
                            <input type="text" id="acogida_<?php echo esc_attr($semana_label); ?>"
                                name="plazas_reservadas[<?php echo esc_attr($semana_label); ?>][acogida]"
                                value="<?php echo esc_attr(isset($datos_semana['acogida']) ? $datos_semana['acogida'] : ''); ?>">
                        </div>

                        <!-- Beca -->
                        <div class="form-field">
                            <label for="beca_<?php echo esc_attr($semana_label); ?>">
                                Beca
                            </label>
                            <input type="text" id="beca_<?php echo esc_attr($semana_label); ?>"
                                name="plazas_reservadas[<?php echo esc_attr($semana_label); ?>][beca]"
                                value="<?php echo esc_attr(isset($datos_semana['beca']) ? $datos_semana['beca'] : ''); ?>">
                        </div>
                    </div>
                </fieldset>
                <?php $contador++; endforeach; ?>
        <?php else: ?>
            <p>No hay datos en <code>plazas_reservadas</code> para este pedido.</p>
        <?php endif; ?>

        <p>
            <input type="submit" name="guardar_cambios" class="button button-primary" value="Guardar Cambios">
            &nbsp;
            <a class="button" href="<?php echo esc_url(remove_query_arg('editar_pedido')); ?>">Volver al Listado</a>
        </p>
    </form>
    <style>
        /* Estilos para el contenedor global del formulario */
        .dashboard-form-container {
            margin: 20px 0;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        /* Encabezados de secciones */
        .dashboard-form-container h3 {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        /* GRID para organizar campos en columnas */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            /* 2 columnas; ajusta a tu gusto */
            gap: 20px;
            /* espacio entre columnas */
            margin-bottom: 20px;
        }

        /* Cada "celda" del grid */
        .form-field {
            display: flex;
            flex-direction: column;
        }

        /* Estilo para el label */
        .form-field label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Opcional: limita el ancho máximo de los inputs */
        .form-field input[type="text"],
        .form-field input[type="date"],
        .form-field input[type="number"] {
            max-width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
    </style>
    <?php

}


function obtener_semanas_disponibles()
{
    global $wpdb;
    $tabla = $wpdb->prefix . 'semanas_campamento'; // Ajusta el nombre de tu tabla
    // Recuperamos semanas en ES y CA, y devolvemos una lista unica para el selector.
    $results = $wpdb->get_results("SELECT id, semana, semana_ca FROM $tabla ORDER BY id ASC", ARRAY_A);

    $lista = [];
    $vistos = [];

    foreach ((array) $results as $row) {
        $candidatas = [
            isset($row['semana']) ? (string) $row['semana'] : '',
            isset($row['semana_ca']) ? (string) $row['semana_ca'] : '',
        ];

        foreach ($candidatas as $nombre_semana) {
            $nombre_semana = trim(preg_replace('/\s+/u', ' ', $nombre_semana));
            if ($nombre_semana === '') {
                continue;
            }

            $clave = function_exists('mb_strtolower') ? mb_strtolower($nombre_semana, 'UTF-8') : strtolower($nombre_semana);
            if (isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $lista[] = $nombre_semana;
        }
    }

    return $lista;
}

/**
 * Fase 1 (legacy): centraliza las cabeceras fijas actuales para mantener el mismo CSV.
 */
function skc_csv_get_cabeceras_legacy(): array
{
    return [
        'Num Suscripció',
        'Data Suscripció',
        'Nom Nen/a',
        'Cognoms Nen/a',
        'Data Naixement Nen/a',
        'Nom Pare/Mare',
        'Cognoms Pare/Mare',
        'Email Mare/Pare',
        'Telèfon Mare/Pare',
        'DNI/NIE Pare/Mare',
        'Nom Pare/Mare2',
        'Cognoms Pare/Mare2',
        'Email Mare/Pare Secundari',
        'Telèfon Mare/Pare Secundari',
        'Adreça',
        'Població/Ciutat',
        'Codi Postal',
        'Nota Client',
        'Forma de pagament',
        'BECA',
        'IDALU',
        'Discapacidad o necesidad especial',
        'Discapacidad o necesidad especial - Descripción',
        'Núm. Tarjeta Sanitaria',
        'Compañía',
        'Vacunat',
        'Alergias, intolerancias o enfermedades',
        'Alergias, intolerancias o enfermedades - Descripción',
        'Escuela de Procedencia',
        'Otra Escuela',
        '¿Alumno Kids&us?',
        'Amigos que asistirán a los campamentos',
        'Bombolleta a la piscina?',
        'Siesta',
        'Autoritzo Imatges - XXSS',
        'Autoritzo Imatges - Intern',
        'Suma de Cost Camp/Setmana',
        'Pendiente pago',
        'Suma de (S1) 9h-17h',
        'Suma de (S1) 9h-14:30h',
        'Suma de (S1) 9h-17h beca',
        'Suma de (S1) Acoll',
        'Suma de (S2) 9h-17h',
        'Suma de (S2) 9h-14:30h',
        'Suma de (S2) 9h-17h beca',
        'Suma de (S2) Acoll',
        'Suma de (S3) 9h-17h',
        'Suma de (S3) 9h-14:30h',
        'Suma de (S3) 9h-17h beca',
        'Suma de (S3) Acoll',
        'Suma de (S4) 9h-17h',
        'Suma de (S4) 9h-14:30h',
        'Suma de (S4) 9h-17h beca',
        'Suma de (S4) Acoll',
        'Suma de (S5) 9h-17h',
        'Suma de (S5) 9h-14:30h',
        'Suma de (S5) 9h-17h beca',
        'Suma de (S5) Acoll',
        'Suma de (S6) 9h-17h',
        'Suma de (S6) 9h-14:30h',
        'Suma de (S6) 9h-17h beca',
        'Suma de (S6) Acoll'
    ];
}

/**
 * Fase 1 (legacy): mantiene el mapeo historico de etiquetas de semana a S1..S6.
 */
function skc_csv_get_semanas_mapeo_legacy(): array
{
    return [
        //Semana 1
        '25 al 27 de junio' => 'S1',
        '25 al 27 junio' => 'S1',
        '25 al 27 de juny' => 'S1',
        '25 al 27 juny' => 'S1',
        //Semana 2
        '30 de junio al 4 de julio' => 'S2',
        '31 de junio al 4 de julio' => 'S2',
        '30 de juny al 4 de juliol' => 'S2',
        '31 de juny al 4 de juliol' => 'S2',

        //Semana 3
        '7 al 11 de julio' => 'S3',
        '7 al 11 de juliol' => 'S3',
        '7 al 11 julio' => 'S3',
        '7 al 11 juliol' => 'S3',

        //Semana 4
        '14 al 18 de julio' => 'S4',
        '14 al 18 de juliol' => 'S4',
        '14 al 18 juliol' => 'S4',
        '14 al 18 julio' => 'S4',

        //Semana 5
        '21 al 25 de julio' => 'S5',
        '21 al 25 de juliol' => 'S5',
        '21 al 25 julio' => 'S5',
        '21 al 25 juliol' => 'S5',

        //Semana 6
        '28 de julio al 1 de agosto' => 'S6',
        '28 de julio al 1 agosto' => 'S6',
        '28 de juliol al 1 agost' => 'S6',
        '28 de juliol al 1 de agost' => 'S6',
    ];
}

/**
 * Fase 1 (legacy): calcula el pendiente exactamente con la regla actual.
 */
function skc_csv_total_pendiente_legacy($fracciones): int
{
    $total_pendiente = 0;

    if (!empty($fracciones) && is_array($fracciones)) {
        foreach ($fracciones as $fraccion) {
            if (isset($fraccion['estado']) && $fraccion['estado'] === 'pendiente') {
                $total_pendiente += $fraccion['importe'];
            }
        }
    }

    return $total_pendiente;
}

/**
 * Fase 1 (legacy): inicia la matriz fija de semanas/opciones del CSV historico.
 */
function skc_csv_inicializar_datos_semana_legacy(): array
{
    return [
        'S1' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
        'S2' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
        'S3' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
        'S4' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
        'S5' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
        'S6' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0']
    ];
}

/**
 * Fase 1 (legacy): traduce plazas_reservadas a columnas S1..S6 y mantiene orden fijo.
 */
function skc_csv_append_semanas_legacy(array &$fila, $semanas_reservadas, array $semanas_mapeo): void
{
    $datos_semana_excel = skc_csv_inicializar_datos_semana_legacy();
    $orden_semanas = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'];
    $orden_opciones = ['9h-17h', '9h-14:30h', '9h-17h-beca', 'acoll'];

    if (!empty($semanas_reservadas)) {
        foreach ($semanas_reservadas as $semana => $datos_semana) {
            $semana_mapeada = isset($semanas_mapeo[$semana]) ? $semanas_mapeo[$semana] : null;
            if ($semana_mapeada) {
                if (isset($datos_semana['horario']) && $datos_semana['horario'] === 'completo') {
                    $datos_semana_excel[$semana_mapeada]['9h-17h'] = '1';
                } else {
                    $datos_semana_excel[$semana_mapeada]['9h-17h'] = '0';
                    $datos_semana_excel[$semana_mapeada]['9h-14:30h'] = '1';
                }
                if (isset($datos_semana['acogida']) && $datos_semana['acogida'] === 'Si') {
                    $datos_semana_excel[$semana_mapeada]['acoll'] = '1';
                }
                if (isset($datos_semana['beca']) && $datos_semana['beca'] === 'Si') {
                    $datos_semana_excel[$semana_mapeada]['9h-17h-beca'] = '1';
                }
            }
        }
    }

    foreach ($orden_semanas as $semana_key) {
        if (isset($datos_semana_excel[$semana_key])) {
            $datos_internos = $datos_semana_excel[$semana_key];
            foreach ($orden_opciones as $opcion_key) {
                $fila[] = isset($datos_internos[$opcion_key]) ? $datos_internos[$opcion_key] : '0';
            }
        } else {
            foreach ($orden_opciones as $opcion_key) {
                $fila[] = '0';
            }
        }
    }
}

/**
 * Bloque comun de columnas no dinamicas compartidas por legacy y v2.
 */
function skc_csv_construir_fila_base_comun($pedido): array
{
    $id_pedido = $pedido->get_id();
    $fila = [];

    $fila[] = $id_pedido;
    $fila[] = $pedido->get_date_created() ? $pedido->get_date_created()->format('Y-m-d H:i:s') : 'no';

    $datos_alumno = $pedido->get_meta('datos_alumno');
    $es_datos_alumno_valido = is_array($datos_alumno);

    if (!$es_datos_alumno_valido) {
        error_log("Advertencia: datos_alumno no es un array para Pedido ID: " . $id_pedido . ". Tipo recibido: " . gettype($datos_alumno));
    }

    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['nombre_alumno']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['apellido_alumno']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['fecha_nacimiento']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['nombre_tutor']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['apellido_tutor']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['email_tutor']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['telefono_tutor']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['dni_tutor']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['nombre_tutor_2']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['apellido_tutor_2']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['email_tutor_2']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['telefono_tutor_2']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['direccion_tutor']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['ciudad_tutor']) : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['codigo_postal_tutor']) : 'no';

    $fila[] = 'no';
    $fila[] = 'Redsys';

    $tiene_beca = 'no';
    $codigo_para_fila = '';
    if ($es_datos_alumno_valido && !empty($datos_alumno['codigo_idalu'])) {
        $tiene_beca = 'si';
        $codigo_para_fila = $datos_alumno['codigo_idalu'];
    }
    $fila[] = $tiene_beca;
    $fila[] = $codigo_para_fila;

    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['tiene_discapacidad'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['detalle_discapacidad'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['numero_tarjeta_sanitaria'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['compania_seguro'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['vacunado'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['tiene_alergias'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['detalle_alergias'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['escuela_procedencia'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['escuela_procedencia_otra'] ?? '') : '';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['alumno_kids_us'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['nombre_amigo_campamento'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['necesita_flotador'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['necesita_siesta'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['redes_sociales'] ?? 'no') : 'no';
    $fila[] = $es_datos_alumno_valido ? ($datos_alumno['img_administrativas'] ?? 'no') : 'no';

    $fila[] = $pedido->get_total() ?: '0';
    $fracciones = $pedido->get_meta('fracciones');
    $total_pendiente = skc_csv_total_pendiente_legacy($fracciones);
    $fila[] = $total_pendiente > 0 ? $total_pendiente : '0';

    return $fila;
}

/**
 * Fase 1 (legacy): construye la fila del pedido manteniendo exactamente el formato actual.
 */
function skc_csv_construir_fila_legacy($pedido, array $semanas_mapeo): array
{
    $fila = skc_csv_construir_fila_base_comun($pedido);

    $semanas_reservadas = $pedido->get_meta('plazas_reservadas');
    skc_csv_append_semanas_legacy($fila, $semanas_reservadas, $semanas_mapeo);

    return $fila;
}

/**
 * Fase 2 (v2): cabeceras base comunes previas a columnas dinamicas.
 */
function skc_csv_get_cabeceras_base_comun(): array
{
    return array_slice(skc_csv_get_cabeceras_legacy(), 0, 38);
}

/**
 * Fase 2 (v2): obtiene los pedidos con el mismo criterio de export legacy.
 */
function skc_csv_get_pedidos_exportacion(): array
{
    $args = [
        'status' => ['processing', 'completed'],
        'limit'  => -1,
        'type'   => 'shop_order'
    ];

    return wc_get_orders($args);
}

/**
 * Fase 2 (v2): cachea nombres de escuela para etiquetas de columnas dinamicas.
 */
function skc_csv_get_escuelas_lookup(): array
{
    global $wpdb;
    $tabla_escuelas = $wpdb->prefix . 'skc_escuelas';

    $existe_tabla = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tabla_escuelas)) === $tabla_escuelas;
    if (!$existe_tabla) {
        return [];
    }

    $rows = $wpdb->get_results("SELECT id, nombre FROM {$tabla_escuelas}", ARRAY_A);
    $lookup = [];

    foreach ((array) $rows as $row) {
        $lookup[(int) $row['id']] = (string) $row['nombre'];
    }

    return $lookup;
}

/**
 * Fase 2 (v2): etiqueta legible y estable para escuela.
 */
function skc_csv_get_etiqueta_escuela(int $escuela_id, array $escuelas_lookup): string
{
    if ($escuela_id > 0 && isset($escuelas_lookup[$escuela_id]) && $escuelas_lookup[$escuela_id] !== '') {
        return $escuelas_lookup[$escuela_id];
    }

    if ($escuela_id > 0) {
        return 'Escuela ID ' . $escuela_id;
    }

    return 'Escuela sin identificar';
}

/**
 * Fase 2 (v2): construye y ordena las columnas dinamicas segun reservas reales.
 */
function skc_csv_build_columnas_dinamicas_v2(array $pedidos): array
{
    $escuelas_lookup = skc_csv_get_escuelas_lookup();
    $columnas = [];

    foreach ($pedidos as $pedido) {
        $semanas_reservadas = $pedido->get_meta('plazas_reservadas');
        if (empty($semanas_reservadas) || !is_array($semanas_reservadas)) {
            continue;
        }

        foreach ($semanas_reservadas as $semana_label => $datos_semana) {
            $semana = trim((string) $semana_label);
            if ($semana === '') {
                continue;
            }

            $escuela_id = isset($datos_semana['escuela_id']) ? absint($datos_semana['escuela_id']) : 0;
            $escuela_etiqueta = skc_csv_get_etiqueta_escuela($escuela_id, $escuelas_lookup);
            $horario = isset($datos_semana['horario']) ? trim((string) $datos_semana['horario']) : '';
            if ($horario === '') {
                $horario = 'sin_horario';
            }

            $key_reserva = 'reserva|' . $escuela_id . '|' . $semana . '|' . $horario;
            $columnas[$key_reserva] = [
                'key' => $key_reserva,
                'escuela' => $escuela_etiqueta,
                'semana' => $semana,
                'tipo' => 'reserva',
                'horario' => $horario,
                'header' => 'Reserva - Escuela: ' . $escuela_etiqueta . ' | Semana: ' . $semana . ' | Horario: ' . $horario,
            ];

            $key_beca = 'beca|' . $escuela_id . '|' . $semana;
            $columnas[$key_beca] = [
                'key' => $key_beca,
                'escuela' => $escuela_etiqueta,
                'semana' => $semana,
                'tipo' => 'beca',
                'horario' => '',
                'header' => 'Beca - Escuela: ' . $escuela_etiqueta . ' | Semana: ' . $semana,
            ];

            $key_acogida = 'acogida|' . $escuela_id . '|' . $semana;
            $columnas[$key_acogida] = [
                'key' => $key_acogida,
                'escuela' => $escuela_etiqueta,
                'semana' => $semana,
                'tipo' => 'acogida',
                'horario' => '',
                'header' => 'Acogida - Escuela: ' . $escuela_etiqueta . ' | Semana: ' . $semana,
            ];
        }
    }

    $columnas = array_values($columnas);
    usort($columnas, static function (array $a, array $b): int {
        $cmp_escuela = strcasecmp($a['escuela'], $b['escuela']);
        if ($cmp_escuela !== 0) {
            return $cmp_escuela;
        }

        $cmp_semana = strcasecmp($a['semana'], $b['semana']);
        if ($cmp_semana !== 0) {
            return $cmp_semana;
        }

        $prioridad = ['reserva' => 1, 'beca' => 2, 'acogida' => 3];
        $pa = $prioridad[$a['tipo']] ?? 99;
        $pb = $prioridad[$b['tipo']] ?? 99;
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        return strcasecmp($a['horario'], $b['horario']);
    });

    $column_keys = [];
    $column_headers = [];
    foreach ($columnas as $columna) {
        $column_keys[] = $columna['key'];
        $column_headers[] = $columna['header'];
    }

    return [
        'keys' => $column_keys,
        'headers' => $column_headers,
    ];
}

/**
 * Fase 2 (v2): completa valores dinamicos por escuela/semana/horario para un pedido.
 */
function skc_csv_append_semanas_dinamicas_v2(array &$fila, $semanas_reservadas, array $column_keys): void
{
    $valores = [];
    foreach ($column_keys as $key) {
        $valores[$key] = '0';
    }

    if (!empty($semanas_reservadas) && is_array($semanas_reservadas)) {
        foreach ($semanas_reservadas as $semana_label => $datos_semana) {
            $semana = trim((string) $semana_label);
            if ($semana === '') {
                continue;
            }

            $escuela_id = isset($datos_semana['escuela_id']) ? absint($datos_semana['escuela_id']) : 0;
            $horario = isset($datos_semana['horario']) ? trim((string) $datos_semana['horario']) : '';
            if ($horario === '') {
                $horario = 'sin_horario';
            }

            $key_reserva = 'reserva|' . $escuela_id . '|' . $semana . '|' . $horario;
            if (array_key_exists($key_reserva, $valores)) {
                $valores[$key_reserva] = '1';
            }

            $key_beca = 'beca|' . $escuela_id . '|' . $semana;
            if (array_key_exists($key_beca, $valores) && isset($datos_semana['beca']) && $datos_semana['beca'] === 'Si') {
                $valores[$key_beca] = '1';
            }

            $key_acogida = 'acogida|' . $escuela_id . '|' . $semana;
            if (array_key_exists($key_acogida, $valores) && isset($datos_semana['acogida']) && $datos_semana['acogida'] === 'Si') {
                $valores[$key_acogida] = '1';
            }
        }
    }

    foreach ($column_keys as $key) {
        $fila[] = $valores[$key] ?? '0';
    }
}

/**
 * Fase 2 (v2): construye fila base + columnas dinamicas.
 */
function skc_csv_construir_fila_v2($pedido, array $column_keys): array
{
    $fila = skc_csv_construir_fila_base_comun($pedido);
    $semanas_reservadas = $pedido->get_meta('plazas_reservadas');
    skc_csv_append_semanas_dinamicas_v2($fila, $semanas_reservadas, $column_keys);

    return $fila;
}


function exportar_csv_funcion()
{

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }

    // Definir el nombre del archivo
    $nombre_archivo = 'reservas_pedidos_' . date('Y-m-d') . '.csv';

    // Configurar cabeceras para descarga
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    // Asegurarse de que no haya salida previa
    ob_clean();
    flush();

    // Abrir el archivo para escribir
    $output = fopen('php://output', 'w');

    // Asegurar codificación UTF-8 para caracteres especiales
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Fase 1: las cabeceras salen de helper para poder refactorizar sin cambiar formato.
    $cabeceras = skc_csv_get_cabeceras_legacy();

    // Escribir las cabeceras
    fputcsv($output, $cabeceras);

    // Obtener todos los pedidos
    $args = [
       'status' => ['processing', 'completed'],
    'limit'  => -1,
    'type'   => 'shop_order'
    ];
    $pedidos = wc_get_orders($args);
    // Fase 1: mismo mapeo historico, movido a helper para reutilizacion controlada.
    $semanas_mapeo = skc_csv_get_semanas_mapeo_legacy();

    foreach ($pedidos as $pedido) {
        // Fase 1: se construye la fila usando helper legacy para separar responsabilidades.
        $fila = skc_csv_construir_fila_legacy($pedido, $semanas_mapeo);

        // Escribir la fila en el CSV
        fputcsv($output, $fila);

    
}

    fclose($output);
    exit;
}

/**
 * Fase 2 (v2): exporta CSV filtrado por escuela.
 * Columnas dinamicas construidas desde wp_semanas_campamento + wp_horarios_semana.
 * Una columna por combinacion semana+horario. Valor: 1 si reservado, vacio si no.
 */
function exportar_csv_funcion_v2()
{
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para realizar esta acción.' );
    }

    $escuela_id = isset( $_GET['escuela_id'] ) ? absint( $_GET['escuela_id'] ) : 0;
    if ( ! $escuela_id ) {
        wp_die( 'Debes seleccionar una escuela para exportar.' );
    }

    global $wpdb;

    // Obtener nombre y product_id/product_id_ca de la escuela
    $escuela = $wpdb->get_row( $wpdb->prepare(
        "SELECT nombre, product_id, product_id_ca FROM {$wpdb->prefix}skc_escuelas WHERE id = %d",
        $escuela_id
    ) );
    $nombre_escuela = $escuela ? $escuela->nombre : 'escuela_' . $escuela_id;
    $slug_archivo   = sanitize_title( $nombre_escuela );
    $product_id     = $escuela ? (int) $escuela->product_id : 0;
    $product_id_ca  = $escuela ? (int) $escuela->product_id_ca : 0;

    // Obtener semanas de esta escuela ordenadas por ID
    $semanas = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, semana FROM {$wpdb->prefix}semanas_campamento WHERE escuela_id = %d ORDER BY id ASC",
        $escuela_id
    ) );

    if ( empty( $semanas ) ) {
        wp_die( 'Esta escuela no tiene semanas configuradas.' );
    }

    // Construir listado de columnas dinamicas por semana en orden legacy:
    // horario 1, horario 2, ..., beca, acogida.
    // Keys canónicas para cruces robustos: semana_id||tipo_horario, semana_id||beca, semana_id||acogida.
    $columnas = [];
    $indice_semana = 1;
    foreach ( $semanas as $semana ) {
        $horarios = $wpdb->get_results( $wpdb->prepare(
            "SELECT tipo_horario, nombre_horario FROM {$wpdb->prefix}horarios_semana WHERE semana_id = %d ORDER BY id ASC",
            $semana->id
        ) );

        foreach ( $horarios as $horario ) {
            $nombre_horario = trim( (string) $horario->nombre_horario );
            if ( $nombre_horario === '' ) {
                $nombre_horario = (string) $horario->tipo_horario;
            }

            $columnas[] = [
                'semana_id'      => (int) $semana->id,
                'semana_label'   => $semana->semana,
                'tipo_horario'   => (string) $horario->tipo_horario,
                'nombre_horario' => (string) $horario->nombre_horario,
                'key'            => (int) $semana->id . '||' . (string) $horario->tipo_horario,
                'header'         => 'Suma de (S' . $indice_semana . ') ' . $nombre_horario,
                'tipo_columna'   => 'horario',
            ];
        }

        $columnas[] = [
            'semana_id'    => (int) $semana->id,
            'key'          => (int) $semana->id . '||beca',
            'header'       => 'Suma de (S' . $indice_semana . ') beca',
            'tipo_columna' => 'beca',
        ];

        $columnas[] = [
            'semana_id'    => (int) $semana->id,
            'key'          => (int) $semana->id . '||acogida',
            'header'       => 'Suma de (S' . $indice_semana . ') Acoll',
            'tipo_columna' => 'acogida',
        ];

        $indice_semana++;
    }

    if ( empty( $columnas ) ) {
        wp_die( 'Esta escuela no tiene horarios configurados en sus semanas.' );
    }

    // Filtrar pedidos que tengan reservas en esta escuela (ES o CA)
    $todos_pedidos   = skc_csv_get_pedidos_exportacion();
    $pedidos_escuela = [];
    foreach ( $todos_pedidos as $pedido ) {
        $plazas = $pedido->get_meta( 'plazas_reservadas' );
        if ( ! is_array( $plazas ) ) {
            continue;
        }

        foreach ( $plazas as $datos_semana ) {
            $es_escuela = (
                ( isset( $datos_semana['escuela_id'] ) && (int) $datos_semana['escuela_id'] === $escuela_id )
                || ( isset( $datos_semana['product_id'] ) && ( (int) $datos_semana['product_id'] === $product_id || (int) $datos_semana['product_id'] === $product_id_ca ) )
            );

            if ( $es_escuela ) {
                $pedidos_escuela[] = $pedido;
                break;
            }
        }
    }

    // Cabeceras: base comun + una columna por semana+horario
    $cabeceras_dinamicas = [];
    foreach ( $columnas as $col ) {
        $cabeceras_dinamicas[] = $col['header'];
    }
    $cabeceras = array_merge( skc_csv_get_cabeceras_base_comun(), $cabeceras_dinamicas );

    // Iniciar descarga
    $nombre_archivo = 'reservas_' . $slug_archivo . '_' . date( 'Y-m-d' ) . '.csv';
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . $nombre_archivo . '"' );
    ob_clean();
    flush();

    $output = fopen( 'php://output', 'w' );
    fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM UTF-8

    fputcsv( $output, $cabeceras );

    foreach ( $pedidos_escuela as $pedido ) {
        // Columnas base (datos alumno, tutor, etc.)
        $fila = skc_csv_construir_fila_base_comun( $pedido );

        // Construir mapa de reservas del pedido para esta escuela: key => 1
        $plazas     = $pedido->get_meta( 'plazas_reservadas' );
        $plazas_map = [];

        if ( is_array( $plazas ) ) {
            foreach ( $plazas as $semana_label => $datos_semana ) {
                $es_escuela = (
                    ( isset( $datos_semana['escuela_id'] ) && (int) $datos_semana['escuela_id'] === $escuela_id )
                    || ( isset( $datos_semana['product_id'] ) && ( (int) $datos_semana['product_id'] === $product_id || (int) $datos_semana['product_id'] === $product_id_ca ) )
                );

                if ( ! $es_escuela ) {
                    continue;
                }

                $semana_id = skc_obtener_semana_id_por_nombre( (string) $semana_label, $escuela_id );
                if ( ! $semana_id ) {
                    // Fallback para históricos donde la semana no resuelve con escuela concreta.
                    $semana_id = skc_obtener_semana_id_por_nombre( (string) $semana_label, null );
                }
                if ( ! $semana_id ) {
                    continue;
                }

                $horario_raw = isset( $datos_semana['horario'] ) ? (string) $datos_semana['horario'] : '';
                $tipo = skc_resolver_tipo_horario_por_semana( (int) $semana_id, $horario_raw );
                if ( ! $tipo ) {
                    // Si ya viene guardado como tipo_horario, reutilizarlo.
                    $tipo = trim( $horario_raw );
                }
                if ( $tipo === '' ) {
                    continue;
                }

                $plazas_map[ (int) $semana_id . '||' . $tipo ] = '1';

                if ( isset( $datos_semana['beca'] ) && strtolower( trim( (string) $datos_semana['beca'] ) ) === 'si' ) {
                    $plazas_map[ (int) $semana_id . '||beca' ] = '1';
                }

                if ( isset( $datos_semana['acogida'] ) && strtolower( trim( (string) $datos_semana['acogida'] ) ) === 'si' ) {
                    $plazas_map[ (int) $semana_id . '||acogida' ] = '1';
                }
            }
        }

        // Rellenar columnas dinamicas: 1 si reservado, 0 si no
        foreach ( $columnas as $col ) {
            $fila[] = isset( $plazas_map[ $col['key'] ] ) ? '1' : '0';
        }

        fputcsv( $output, $fila );
    }

    fclose( $output );
    exit;
}

?>