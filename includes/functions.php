<?php
// Acción para exportar los pedidos
//add_action('admin_post_exportar_reservas_csv', 'exportar_reservas_csv');


function exportar_reservas_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }

    // Definir el nombre del archivo
    $nombre_archivo = 'reservas_pedidos_' . date('Y-m-d') . '.csv';

    // Configurar cabeceras para descarga
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    $output = fopen('php://output', 'w');

    // Definir las cabeceras del CSV
    fputcsv($output, [
        'ID Pedido', 'Fecha Pedido', 'Nombre Alumno', 'Curso Escolar',
        'Nombre Tutor', 'Teléfono Tutor', 'Semanas Seleccionadas', 'Acogida'
    ]);

    // Obtener todos los pedidos
    $args = [
        'status' => 'any',
        'limit' => -1, // Obtener todos los pedidos
    ];
    $pedidos = wc_get_orders($args);

    foreach ($pedidos as $pedido) {
        $id_pedido = $pedido->get_id();
        $fecha_pedido = $pedido->get_date_created()->format('Y-m-d H:i:s');
        $nombre_alumno = get_post_meta($id_pedido, '_nombre_alumno', true);
        $curso_escolar = get_post_meta($id_pedido, '_curso_escolar', true);
        $nombre_tutor = get_post_meta($id_pedido, '_nombre_tutor', true);
        $telefono_tutor = get_post_meta($id_pedido, '_telefono_tutor', true);
        $semanas_seleccionadas = get_post_meta($id_pedido, '_semanas_seleccionadas', true);
        $acogida = get_post_meta($id_pedido, '_acogida_seleccionada', true);

        // Convertir arrays a string si es necesario
        if (is_array($semanas_seleccionadas)) {
            $semanas_seleccionadas = implode(', ', $semanas_seleccionadas);
        }

        // Agregar la fila al CSV
        fputcsv($output, [
            $id_pedido, $fecha_pedido, $nombre_alumno, $curso_escolar,
            $nombre_tutor, $telefono_tutor, $semanas_seleccionadas, $acogida
        ]);
    }

    fclose($output);
    exit;
}


// Función para mostrar la tabla de reservas
function mostrar_reservas_pedidos() {
    $orders = wc_get_orders([
        'limit' => -1,
        'status' => ['wc-processing', 'wc-completed', 'wc-on-hold'],
    ]);

    echo '<div class="wrap">';
    echo '<h1>Reservas de Pedidos</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>
            <tr>
                <th>Cliente</th>
                <th>DNI</th>
                <th>Datos Alumno</th>
                <th>Datos Tutor</th>
                <th>Semanas Reservadas</th>
                <th>Estado</th>
            </tr>
          </thead><tbody>';

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $cliente = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $dni_cliente = get_post_meta($order_id, '_dni_tutor', true);
        $estado = wc_get_order_status_name($order->get_status());

        // Datos del alumno
        $datos_alumno = sprintf(
            "Nombre: %s %s<br>Fecha Nacimiento: %s<br>Kids Us: %s<br>Discapacidad: %s<br>Alergias: %s",
            get_post_meta($order_id, '_nombre_alumno', true),
            get_post_meta($order_id, '_apellido_alumno', true),
            get_post_meta($order_id, '_fecha_nacimiento', true),
            get_post_meta($order_id, '_alumno_kids_us', true),
            get_post_meta($order_id, '_tiene_discapacidad', true),
            get_post_meta($order_id, '_tiene_alergias', true)
        );

        // Datos del tutor
        $datos_tutor = sprintf(
            "Nombre: %s %s<br>Teléfono: %s<br>Email: %s",
            get_post_meta($order_id, '_nombre_tutor', true),
            get_post_meta($order_id, '_apellido_tutor', true),
            get_post_meta($order_id, '_telefono_tutor', true),
            get_post_meta($order_id, '_email_tutor', true)
        );

        // Procesar semanas reservadas
        $semanas = get_post_meta($order_id, '_semanas_reservadas', true);
           
        $semanas_html = '';
        if ($semanas) {
            $semanas_array = explode(' | ', $semanas);
            $semanas_html = '<table class="inner-table">
                            <tr><th>Semana</th><th>Horario</th><th>Acogida</th></tr>';
            foreach ($semanas_array as $semana) {
                preg_match('/Semana: (.*?), horario: (.*?), acogida: (.*?)$/', $semana, $matches);
                if ($matches) {
                    $semanas_html .= sprintf(
                        '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                        $matches[1], $matches[2], $matches[3]
                    );
                }
            }
            $semanas_html .= '</table>';
        }

        echo "<tr>
                <td>{$cliente}</td>
                <td>{$dni_cliente}</td>
                <td>{$datos_alumno}</td>
                <td>{$datos_tutor}</td>
                <td>{$semanas_html}</td>
                <td>{$estado}</td>
              </tr>";
    }

    echo '</tbody></table></div>';
    
    // Estilos para la tabla interna
    echo '<style>
        .inner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .inner-table th, .inner-table td {
            border: 1px solid #ddd;
            padding: 4px;
            font-size: 12px;
        }
        .inner-table th {
            background-color: #f5f5f5;
        }
    </style>';
}


// Aplicar el descuento solo al producto "Escuela Duran I Bas 9h a 17h" y desglosar por semana
add_action('woocommerce_cart_calculate_fees', function($cart) {
    if (is_admin() || !defined('DOING_AJAX') || !DOING_AJAX) return;
    
   
    $tiene_hermano = WC()->session->get('tiene_hermano');
    $nombre_hermano = WC()->session->get('nombre_hermano');

    if ($tiene_hermano === 'valido' && !empty($nombre_hermano)) {
        $descuento_hermano = $cart->subtotal * 0.05;
        $cart->add_fee("🟢 Descuento por Hermano", -$descuento_hermano, true);
    }

      $solicita_beca = WC()->session->get('solicita_beca');
    $privacidad_aceptada = WC()->session->get('privacidad_aceptada');

    if ($privacidad_aceptada !== 'yes' || $solicita_beca !== 'si') return;

    $max_semanas_con_descuento = 2;
    $semanas_descuento = 0;

    foreach ($cart->get_cart() as $cart_item) {
        $producto = $cart_item['data'];
        $nombre_producto = $producto->get_name();
        $cantidad = $cart_item['quantity'];

        // Solo aplica a "Escuela Duran I Bas 9h a 17h"
        if ($nombre_producto !== 'Escuela Duran I Bas 9:00h a 17:00h') continue;

        // Obtener semanas seleccionadas y sus precios
        if (!isset($cart_item['wapf'][0]['value_cart']) || !isset($cart_item['wapf'][0]['price'])) continue;

        $semanas_seleccionadas = explode(", ", $cart_item['wapf'][0]['value_cart']);
        $precios_semanas = $cart_item['wapf'][0]['price'];

        foreach ($semanas_seleccionadas as $index => $semana) {
            if ($semanas_descuento >= $max_semanas_con_descuento) break;

            if (isset($precios_semanas[$index]['value'])) {
                $precio_semana = $precios_semanas[$index]['value'];
                $descuento = $precio_semana * 0.90; // 90% de descuento
                $precio_final = $precio_semana - $descuento;

                $cart->add_fee("🎓 Descuento Beca: $semana (Original: $precio_semana € - Descuento: $descuento € → Final: $precio_final €)", -$descuento, true);
                $semanas_descuento++;
            }
        }
    }
});

// Redirigir al checkout tras añadir un producto al carrito
//add_filter('woocommerce_add_to_cart_redirect', 'redirigir_al_carrito');

function redirigir_al_carrito($url) {
    return wc_get_cart_url(); // Redirige al carrito
}

add_filter('woocommerce_enable_order_notes_field', '__return_false');

add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    $campos_personalizados = [
        'nombre_alumno', 'apellido_alumno', 'fecha_nacimiento', 'direccion_alumno', 
        'codigo_postal_alumno', 'ciudad_alumno', 'pais_alumno', 'curso_escolar','tiene_reserva_previa','nombre_reserva_anterior',
        'alumno_kids_us', 'amigos_asistiran', 'tiene_discapacidad', 'detalle_discapacidad',
        'numero_tarjeta_sanitaria', 'compania_seguro', 'tiene_alergias', 
        'detalle_alergias', 'otros_aspectos', 'solicita_beca', 'acepta_privacidad', 
        'codigo_beca', 'nombre_tutor', 'apellido_tutor', 'telefono_tutor', 
        'dni_tutor', 'email_tutor'
    ];

    foreach ($campos_personalizados as $campo) {
        if (isset($_POST[$campo])) {
            update_post_meta($order_id, "_$campo", sanitize_text_field($_POST[$campo]));
        }
    }

    $semanas_data = [];

    foreach (WC()->cart->get_cart() as $cart_item) {
        
        $producto_nombre = $cart_item['data']->get_name();

        // Extraer horario correctamente**
        // Separar por espacio
        preg_match('/(\d{1,2}:\d{2})h a (\d{1,2}:\d{2})h/', $producto_nombre, $matches);

       $horario_inicio = $matches[1];
    $horario_fin = $matches[2]; 

    $horario = "$horario_inicio a $horario_fin";

        // **2️⃣ Obtener semanas seleccionadas sin precios**

		preg_match_all('/([^,]+?)(?:\s*\([^)]+\))/', $cart_item['wapf'][0]['value_cart'], $matches);
		$semanas_limpias = array_map('trim', $matches[1]);


        // **3️⃣ Detectar acogidas confirmadas**
        $acogida_info = [];
        if (isset($cart_item['wapf']) && is_array($cart_item['wapf'])) {
            foreach ($cart_item['wapf'] as $index => $item) {
                if ($index > 0 && $item['type'] === 'true-false' && strpos($item['value_cart'], 'verdadero') !== false) {
                    $acogida_info[trim(str_replace('Acogida ', '', $item['label']))] = 'si';
                }
            }
        }

        // **4️⃣ Asociar semanas con acogida**
        foreach ($semanas_limpias as $semana) {
            if (!empty($semana)) { // **Evita agregar datos vacíos**
                $tiene_acogida = isset($acogida_info[$semana]) ? 'sí' : 'no';
                $semanas_data[] = "Semana: $semana, horario: $horario, acogida: $tiene_acogida";
            }
        }
    }
    // Guardamos las semanas separadas por "|"
    if (!empty($semanas_data)) {
        update_post_meta($order_id, '_semanas_reservadas', implode(" | ", $semanas_data));
    }
});

add_filter('woocommerce_get_item_data', 'filtrar_acogida_verdaderos', 10, 2);

function filtrar_acogida_verdaderos($item_data, $cart_item) {
    $nuevos_datos = array();

    // Verificamos si el carrito tiene el campo 'wapf' (donde están las opciones personalizadas)
    if (isset($cart_item['wapf']) && is_array($cart_item['wapf'])) {
        foreach ($cart_item['wapf'] as $campo) {
            // Asegurar que existen los campos necesarios antes de usarlos
            if (!isset($campo['label']) || !isset($campo['value'])) {
                continue;
            }

            // Convertir a string para evitar errores y verificar si es una opción de "Acogida"
            $nombre = (string) $campo['label'];
            $valor = (string) $campo['value'];

            // Filtrar SOLO las opciones "Acogida" que sean "verdadero"
            if (strpos($nombre, 'Acogida') !== false && strpos(strtolower($valor), 'falso') !== false) {
                continue; // Omitir las opciones "falso"
            }

            // Agregar los valores permitidos al array de nuevos datos
            $nuevos_datos[] = array(
                'name'  => $nombre,
                'value' => $valor,
            );
        }
    }

    return $nuevos_datos;
}

function cargar_bootstrap_checkout() {
    if (is_checkout()) { // Solo en la página de checkout
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
        wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
    }
}
add_action('wp_enqueue_scripts', 'cargar_bootstrap_checkout');



//add_action('wp_footer', 'depurar_carrito_woocommerce');

function depurar_carrito_woocommerce() {
    if (is_cart() || is_checkout()) { // Solo muestra en el carrito o en la página de finalizar compra
        echo '<pre style="background:#fff; color:#000; padding:10px; border:1px solid #000;">';
        print_r(WC()->cart->get_cart()); // Muestra el array completo del carrito
        echo '</pre>';
    }
}


add_action('wp_ajax_verificar_hermano', 'verificar_hermano_callback');
add_action('wp_ajax_nopriv_verificar_hermano', 'verificar_hermano_callback');

function verificar_hermano_callback() {
    global $wpdb;
    
    $nombre_hermano = sanitize_text_field($_POST['nombre_hermano']);
    
    // Consulta para verificar si el hermano tiene una reserva pagada
    $existe_hermano = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
        WHERE meta_key = '_nombre_alumno' 
        AND meta_value = %s ",
        $nombre_hermano
    ));

    if ($existe_hermano) {
         // Guardar en la sesión
        WC()->session->set('tiene_hermano', 'valido');
        WC()->session->set('nombre_hermano', $nombre_hermano);

        wp_send_json_success(array(
            'mensaje'        => 'Hermano verificado correctamente',
            'tiene_hermano'  => WC()->session->get('tiene_hermano'),
            'nombre_hermano' => WC()->session->get('nombre_hermano'),
        ));
        
    } else {
        wp_send_json_error();
    }

    wp_die();
}



add_action('wp_ajax_apply_beca_discount', function() {
    WC()->session->set('solicita_beca', $_POST['solicita_beca']);
    WC()->session->set('privacidad_aceptada', $_POST['privacidad_aceptada']);

    wp_die();
});
add_action('wp_ajax_nopriv_apply_beca_discount', function() {
    WC()->session->set('solicita_beca', $_POST['solicita_beca']);
    WC()->session->set('privacidad_aceptada', $_POST['privacidad_aceptada']);
    wp_die();
});

add_action('wp_ajax_limpiar_sesion_descuentos', 'limpiar_sesion_descuentos_callback');
add_action('wp_ajax_nopriv_limpiar_sesion_descuentos', 'limpiar_sesion_descuentos_callback');

function limpiar_sesion_descuentos_callback() {
   if (!WC()->session) {
        wp_send_json_error('Sesión de WooCommerce no disponible');
        return;
    }

    // 🔥 Eliminar los valores de la sesión
    WC()->session->__unset('tiene_hermano');
    WC()->session->__unset('nombre_hermano');
    WC()->session->__unset('solicita_beca');
    WC()->session->__unset('privacidad_aceptada');

    // 🔄 Recalcular los totales para aplicar cambios
    WC()->cart->calculate_totals();

    wp_send_json_success('Sesión de descuentos eliminada correctamente');
}



add_action('woocommerce_checkout_before_customer_details', function() {
    $ruta_formulario = plugin_dir_path(__FILE__) . 'formulario-checkout.php';

    if (!function_exists('WC')) return; // Verifica que WooCommerce está cargado
    if (is_admin() || !is_checkout()) return; // Evita ejecución en el admin y en otras páginas

    static $already_executed = false; // Variable de control para evitar duplicación

    if ($already_executed) return; // Si ya se ejecutó, no volver a agregar los formularios
    $already_executed = true; // Marcar como ejecutado

    $checkout = WC()->checkout(); // Obtiene el objeto de checkout correctamente


    if (file_exists($ruta_formulario)) {
        include $ruta_formulario;
    }
});







add_action('wp_ajax_obtener_sesion_woocommerce', 'obtener_sesion_woocommerce_callback');
add_action('wp_ajax_nopriv_obtener_sesion_woocommerce', 'obtener_sesion_woocommerce_callback');

function obtener_sesion_woocommerce_callback() {
    $datos_sesion = array(
        'tiene_hermano'        => WC()->session->get('tiene_hermano'),
        'nombre_hermano'       => WC()->session->get('nombre_hermano'),
        'solicita_beca'        => WC()->session->get('solicita_beca'),
        'privacidad_aceptada'  => WC()->session->get('privacidad_aceptada'),
    );

    wp_send_json_success($datos_sesion);
}


add_filter('woocommerce_checkout_fields', function($fields) {
    // Eliminar la sección de facturación completa
    unset($fields['billing']);
    return $fields;
});

