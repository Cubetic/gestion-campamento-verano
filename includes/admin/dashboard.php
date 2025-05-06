<?php
/**
 * Template Name: Dashboard de Reservas
 */

// Evitar acceso directo    
if (!defined('ABSPATH')) {
    exit;
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
                    // Guardamos los campos (horario, acogida y beca) de la reserva
                    $nuevo_plazas[$nuevo_label] = [
                        'horario' => sanitize_text_field($datos_semana['horario'] ?? ''),
                        'acogida' => sanitize_text_field($datos_semana['acogida'] ?? ''),
                        'beca' => sanitize_text_field($datos_semana['beca'] ?? ''),
                    ];
                }

                // 2. Obtenemos el array antiguo guardado en el pedido
                $old_plazas = $pedido->get_meta('plazas_reservadas');
                if (!is_array($old_plazas)) {
                    $old_plazas = [];
                }

                // 3. Calculamos qué semanas se han "removido" (cambiadas) y cuáles se han "agregado"
                $old_keys = array_keys($old_plazas);
                $new_keys = array_keys($nuevo_plazas);
                $keys_removed = array_diff($old_keys, $new_keys); // semanas antiguas que ya no están
                $keys_added = array_diff($new_keys, $old_keys);   // nuevas semanas que se han agregado

                // Diccionario para convertir el nombre de la semana al ID (basado en lo que usas en descontar_plazas)
                $idSemana = array(
                    '25 al 27 de juny' => 1,
                    '30 de juny al 4 de juliol' => 2,
                    '7 al 11 de juliol' => 3,
                    '14 al 18 de juliol' => 4,
                    '21 al 25 de juliol' => 5,
                    '28 de juliol al 1 agost' => 6,
                    '25 al 27 de junio' => 1,
                    '30 de junio al 4 de julio' => 2,
                    '7 al 11 de julio' => 3,
                    '14 al 18 de julio' => 4,
                    '21 al 25 de julio' => 5,
                    '28 de julio al 1 agosto' => 6,
                    '28 de julio al 1 de agosto' => 6,
                    '28 de juliol al 1 de agost' => 6,      
                              );

                global $wpdb;
                // 4. Para cada semana que se ha removido (cambio de semana), se reponen la plaza
                foreach ($keys_removed as $old_key) {
                    if (isset($idSemana[$old_key])) {
                        $semana_id = $idSemana[$old_key];
                        // Obtenemos el tipo de horario que estaba reservado en la semana antigua
                        $old_datos = $old_plazas[$old_key];
                        $tipo = $old_datos['horario'];

                        // Buscamos el registro en wp_horarios_semana
                        $horario = $wpdb->get_row($wpdb->prepare(
                            "SELECT id, plazas, plazas_reservadas FROM wp_horarios_semana WHERE semana_id = %d AND tipo_horario = %s",
                            $semana_id,
                            $tipo
                        ));
                        if ($horario) {
                            // Reponer: incrementar plazas y decrementar plazas_reservadas
                            $nuevas_plazas = $horario->plazas + 1;
                            $nuevas_reservas = max(0, $horario->plazas_reservadas - 1);
                            $wpdb->update(
                                'wp_horarios_semana',
                                ['plazas' => $nuevas_plazas, 'plazas_reservadas' => $nuevas_reservas],
                                ['id' => $horario->id]
                            );
                            error_log("Repuesta plaza en semana '$old_key' (ID: {$horario->id}): Plazas: $nuevas_plazas, Reservadas: $nuevas_reservas");
                        }
                    }
                }

                // 5. Para cada nueva semana agregada, se descuenta la plaza
                foreach ($keys_added as $new_key) {
                    if (isset($idSemana[$new_key])) {
                        $semana_id = $idSemana[$new_key];
                        $new_datos = $nuevo_plazas[$new_key];
                        $tipo = $new_datos['horario'];

                        $horario = $wpdb->get_row($wpdb->prepare(
                            "SELECT id, plazas, plazas_reservadas FROM wp_horarios_semana WHERE semana_id = %d AND tipo_horario = %s",
                            $semana_id,
                            $tipo
                        ));
                        if ($horario) {
                            // Verificar disponibilidad (opcional)
                            if ($horario->plazas > 0) {
                                $nuevas_plazas = $horario->plazas - 1;
                                $nuevas_reservas = $horario->plazas_reservadas + 1;
                                $wpdb->update(
                                    'wp_horarios_semana',
                                    ['plazas' => $nuevas_plazas, 'plazas_reservadas' => $nuevas_reservas],
                                    ['id' => $horario->id]
                                );
                                error_log("Descontada plaza en nueva semana '$new_key' (ID: {$horario->id}): Plazas: $nuevas_plazas, Reservadas: $nuevas_reservas");
                            } else {
                                error_log("No hay plazas disponibles en nueva semana '$new_key' (tipo: $tipo)");
                            }
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
                                    foreach ($semanas_bd as $fila) {
                                        // El valor será el "nombre_semana" que queremos asignar
                                        $nombre_semana = $fila->semana;
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
    // Recuperamos todas las semanas, por ejemplo id y nombre
    $results = $wpdb->get_results("SELECT id, semana FROM $tabla ORDER BY id ASC");
    return $results; // Array de objetos con ->semana_id y ->nombre_semana
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

    // Definir las cabeceras del CSV
    $cabeceras = [
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

    // Escribir las cabeceras
    fputcsv($output, $cabeceras);

    // Obtener todos los pedidos
    $args = [
       'status' => ['processing', 'completed'],
    'limit'  => -1,
    'type'   => 'shop_order'
    ];
    $pedidos = wc_get_orders($args);
    // Mapeo de semanas en castellano y catalán
    // Mapeo de semanas en castellano a S1, S2, S3, etc.
    $semanas_mapeo = [  
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

    foreach ($pedidos as $pedido) {
        $id_pedido = $pedido->get_id();

        // Inicializar array para almacenar los datos de cada fila
        $fila = [];

        // Datos básicos del pedido
        $fila[] = $id_pedido; // Num Suscripció
        $fila[] = $pedido->get_date_created() ? $pedido->get_date_created()->format('Y-m-d H:i:s') : 'no'; // Data Suscripció (Añadida verificación)

        // Datos del alumno
        $datos_alumno = $pedido->get_meta('datos_alumno');  
        $es_datos_alumno_valido = is_array($datos_alumno); // Variable de control


        if (!$es_datos_alumno_valido) {  
            error_log("Advertencia: datos_alumno no es un array para Pedido ID: " . $id_pedido . ". Tipo recibido: " . gettype($datos_alumno));  
        }  
        // --- Fin Validación ---  
  
        // --- Acceso Seguro a datos_alumno ---  
        // Usamos la variable $es_datos_alumno_valido para decidir si acceder o usar default  
  
        // Datos del alumno  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['nombre_alumno']) : 'no'; // Nom Nen/a  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['apellido_alumno']) : 'no'; // Cognoms Nen/a  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['fecha_nacimiento']) : 'no'; // Data Naixement Nen/a  
  
        // Datos del tutor principal  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['nombre_tutor']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['apellido_tutor']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['email_tutor']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['telefono_tutor']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['dni_tutor']) : 'no';  
  
        // Información del tutor secundario  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['nombre_tutor_2']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['apellido_tutor_2']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['email_tutor_2']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['telefono_tutor_2']) : 'no';  
  
        // Dirección  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['direccion_tutor']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['ciudad_tutor']) : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['codigo_postal_tutor']) : 'no';  
  
        // Notas y forma de pago  
        $fila[] = 'no'; // Nota Client (Original comentado) - Si lo usas, verifica $pedido->get_customer_note()  
        $fila[] = 'Redsys'; // Forma de pagament  
  
        // Información de beca (codigo_idalu)  
        $tiene_beca = 'no';  
        $codigo_para_fila = '';  
        // Solo intentamos la lógica si $datos_alumno es válido  
        if ($es_datos_alumno_valido && !empty($datos_alumno['codigo_idalu'])) {  
            $tiene_beca = 'si';  
            $codigo_para_fila = $datos_alumno['codigo_idalu']; // Ya sabemos que existe y no está vacío  
        }  
        $fila[] = $tiene_beca;  
        $fila[] = $codigo_para_fila;  
  
        // Información de salud  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['tiene_discapacidad'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['detalle_discapacidad'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['numero_tarjeta_sanitaria'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['compania_seguro'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['vacunado'] ?? 'no') : 'no'; // El ?? maneja si existe o no  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['tiene_alergias'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['detalle_alergias'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['escuela_procedencia'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['escuela_procedencia_otra'] ?? '') : ''; // Default ''  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['alumno_kids_us'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['nombre_amigo_campamento'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['necesita_flotador'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['necesita_siesta'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['redes_sociales'] ?? 'no') : 'no';  
        $fila[] = $es_datos_alumno_valido ? ($datos_alumno['img_administrativas'] ?? 'no') : 'no';  
  
        // --- Fin Acceso Seguro ---  
  
        // Datos finales del pedido (asumimos seguros, pero podrías añadir `?: '0'`)  
        $fila[] = $pedido->get_total() ?: '0'; // Suma de Cost Camp/Setmana  
        // Obtener el meta 'fracciones'  
        $fracciones = $pedido->get_meta('fracciones');  
  
// Inicializar la variable para el total pendiente  
$total_pendiente = 0;  
  
// Verificar si 'fracciones' existe y es un array  
if (!empty($fracciones) && is_array($fracciones)) {  
    foreach ($fracciones as $fraccion) {  
        if (isset($fraccion['estado']) && $fraccion['estado'] === 'pendiente') {  
            $total_pendiente += $fraccion['importe']; // Sumar el importe de las fracciones pendientes  
        }  
    }  
}  
 
  
// Asignar el total pendiente a $fila[]  
$fila[] = $total_pendiente > 0 ? $total_pendiente : '0';

        // Inicializar los datos de las semanas
        $datos_semana_excel = [
            'S1' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
            'S2' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
            'S3' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
            'S4' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
            'S5' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0'],
            'S6' => ['9h-17h' => '0', '9h-14:30h' => '0', '9h-17h-beca' => '0', 'acoll' => '0']
        ];

        // 1. Definir el orden consistente para asegurar columnas correctas en el CSV  
        $orden_semanas = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'];
        $orden_opciones = ['9h-17h', '9h-14:30h', '9h-17h-beca', 'acoll'];

        $semanas_reservadas = $pedido->get_meta('plazas_reservadas');
        // Procesar las semanas reservadas
        if (!empty($semanas_reservadas)) {
            foreach ($semanas_reservadas as $semana => $datos_semana) {
                // Mapear la semana a su identificador S1, S2, etc.
                $semana_mapeada = isset($semanas_mapeo[$semana]) ? $semanas_mapeo[$semana] : null;
                if ($semana_mapeada) {
                    // Actualizamos las celdas correspondientes según la semana
                    if (isset($datos_semana['horario']) && $datos_semana['horario'] === 'completo') {
                        $datos_semana_excel[$semana_mapeada]['9h-17h'] = '1';
                    }else{
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

        // 2. Iterar sobre las semanas y opciones en el orden definido  
        foreach ($orden_semanas as $semana_key) {
            // Verificar si la clave de la semana (ej. 'S1') existe en $datos_semana  
            if (isset($datos_semana_excel[$semana_key])) {
                $datos_internos = $datos_semana_excel[$semana_key];
                // Iterar sobre las opciones definidas para esa semana  
                foreach ($orden_opciones as $opcion_key) {
                    // Añadir el valor a $fila. Usar '0' como default si la opción específica no existe.  
                    $fila[] = isset($datos_internos[$opcion_key]) ? $datos_internos[$opcion_key] : '0';
                }
            } else {
                // Si la clave de la semana completa (ej. 'S3') no existe en $datos_semana,  
                // añadir valores por defecto ('0') para todas sus opciones para mantener la estructura del CSV.  
                foreach ($orden_opciones as $opcion_key) {
                    $fila[] = '0';
                }
            }
        }
        // Escribir la fila en el CSV
        fputcsv($output, $fila);

    
}

    fclose($output);
    exit;
}

?>