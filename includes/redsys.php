<?php
// redsys.php
//
if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

/**
 * Funciones de Redsys para pagos fraccionados
 */

//add_action('woocommerce_thankyou_redsys_gw', 'capturar_token_redsys', 10, 1);
//add_action('woocommerce_api_wc_gateway_redsys', 'capturar_token_redsys_api', 10);
//add_filter('woocommerce_redsys_args', 'añadir_tokenizacion_redsys', 99, 2);
//add_filter('rgw_ds_args', 'añadir_tokenizacion_redsys', 99, 2);
add_action('woocommerce_review_order_before_payment', 'add_redsys_installment_option');
add_action('woocommerce_thankyou', 'limpiar_datos_fraccionamiento', 10, 1);
//add_action('procesar_segundo_pago_redsys', 'procesar_segundo_pago_redsys');
add_action( 'redsys_pago_fraccionado_fallido', 'redsys_pago_fraccionado_fallido', 10, 1 );
add_action( 'redsys_pago_fraccionado_correcto', 'redsys_pago_fraccionado_correcto', 10, 1 );
//add_action( 'rgw_payment_processed', 'redsys_rgw_payment_processed', 10, 3 );



/**
 * Summary of add_redsys_installment_option
 * @return void
 */
function add_redsys_installment_option()
{
    if (!is_checkout()) {
        return;
    }
    

    $product_id = null;
    if (WC()->cart && count(WC()->cart->get_cart()) === 1) {
        $cart_items = WC()->cart->get_cart();
        $first_item = reset($cart_items); // Obtiene el primer (y único) producto del carrito
        $product_id = $first_item['product_id'];
    }

    
   $total_carrito = WC()->cart->total;
    if ($total_carrito > 60){
        ?>
    <div id="redsys-installment-container" data-Id="<?php echo $product_id ?>"
        style="display: none; margin-bottom: 20px; padding: 15px; background-color: #f8f8f8; border-radius: 5px;">
        <h3><?php _e('Opciones de pago', 'woocommerce'); ?></h3>

        <p>
            <input type="radio" id="redsys_no_installments" name="redsys_use_installments" value="no" checked>
            <label for="redsys_no_installments"><?php _e('Pagament únic', 'woocommerce'); ?></label>
        </p>
        <p>
            <input type="radio" id="redsys_yes_installments" name="redsys_use_installments" value="yes">
            <label for="redsys_yes_installments"><?php _e('Pagament fraccionat 50 %. El pròxim pagament es realitza automàticament en 30 dies.', 'woocommerce'); ?></label>
        </p>
    </div>

    <script type="text/javascript">
        jQuery(function ($) {
            // Función para mostrar/ocultar opciones de fraccionamiento según el método de pago
            function toggleInstallmentOptions() {
                // Verificar si el método de pago seleccionado es RedSys
                var selectedPayment = $('input[name="payment_method"]:checked').val();

                // Mostrar opciones solo si es RedSys (ajusta el ID según tu configuración)
                if (selectedPayment === 'redsys_gw') {
                    $('#redsys-installment-container').show();
                } else {
                    $('#redsys-installment-container').hide();
                }
            }

            // Ejecutar al cargar la página y cuando cambie el método de pago
            toggleInstallmentOptions();
            $(document.body).on('payment_method_selected', toggleInstallmentOptions);
            $('input[name="payment_method"]').change(toggleInstallmentOptions);

            // Cuando cambia la opción de fraccionamiento
            $('input[name="redsys_use_installments"]').change(function () {
                var useInstallments = $(this).val();

                let propduct_id = $('#redsys-installment-container').attr('data-Id');
                // Mostrar indicador de carga
                $('body').append('<div class="blockUI" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.6); z-index: 9999;"><div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">Actualizando...</div></div>');

                // Llamada AJAX para actualizar el fraccionamiento
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'update_redsys_installments',
                        use_installments: useInstallments,
                        id: propduct_id
                    },
                    success: function (response) {
                        // Actualizar el checkout para reflejar los cambios
                        $('body').trigger('update_checkout');
                    },
                    complete: function () {
                        // Quitar indicador de carga
                        $('.blockUI').remove();
                    }
                });
            });
        });
    </script>
     <?php
    }
    
    }

add_filter('rgw_fracciones', 'modificar_fracciones_dinamicas_por_sesion', 10, 2);

/**
 * Permite cambiar dinámicamente las fracciones por producto usando sesión de usuario.
 */
function modificar_fracciones_dinamicas_por_sesion($valor_original, $product_id) {
    if (!WC()->session) return $valor_original;

    $valor_sesion = WC()->session->get("fracciones_{$product_id}");
    
    if (!is_null($valor_sesion)) {
        return intval($valor_sesion); // Prioriza la sesión
    }

    return $valor_original; // Fallback al valor por defecto (meta)
}







/**
 * Summary of añadir_tokenizacion_redsys
 * @param mixed $args
 * @param mixed $order
 */
function añadir_tokenizacion_redsys($args, $order)
{
    error_log('Iniciamos añadir_tokenizacion_redsys');

    // Verificar si hay productos con fraccionamiento en el pedido
    $es_fraccionado = false;
    // Método 1: Verificar la sesión
    if (function_exists('WC') && WC()->session) {
        $session_installments = WC()->session->get('redsys_use_installments');
        if ($session_installments === 'yes') {
            $es_fraccionado = true;
            error_log('RedSys Tokenización: Fraccionamiento detectado en sesión');
        }
    }

    // Método 2: Verificar metadatos de productos
    if (!$es_fraccionado) {
        $items = $order->get_items();
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $installments = get_post_meta($product_id, '_installments', true);
            $fracciones = get_post_meta($product_id, 'fracciones', true);

            if ($installments && $fracciones > 0) {
                $es_fraccionado = true;
                error_log('RedSys Tokenización: Producto con fraccionamiento detectado: ' . $product_id . ' con ' . $fracciones . ' fracciones');
                break;
            }
        }
    }

    error_log('RedSys Tokenización: Interceptando args para pedido ' . $order->get_id() . ': ' . print_r($args, true));

    // Solo añadir tokenización si se ha seleccionado pago fraccionado
    if ($es_fraccionado) {
        error_log('RedSys Tokenización: Interceptando args para pedido ' . $order->get_id() . ' con pago fraccionado');

        // Guardar metadatos del pedido para el pago fraccionado
        $total = $order->get_total();
        $primer_pago = round($total / 2, 2);
        $segundo_pago = $total - $primer_pago;

        // Fecha y hora actual para el primer pago
        $fecha_primer_pago = current_time('Y-m-d');
        $hora_primer_pago = current_time('H:i:s');

        // Fecha y hora estimada para el segundo pago (un mes después)
        $fecha_segundo_pago = date('Y-m-d', strtotime('+1 month'));
        $hora_segundo_pago = current_time('H:i:s');

        // Guardar todos los metadatos
        update_post_meta($order->get_id(), '_redsys_pago_fraccionado', 'yes');
        update_post_meta($order->get_id(), '_redsys_fecha_primer_pago', $fecha_primer_pago);
        update_post_meta($order->get_id(), '_redsys_monto_primer_pago', $primer_pago);
        update_post_meta($order->get_id(), '_redsys_fecha_segundo_pago', $fecha_segundo_pago);
        update_post_meta($order->get_id(), '_redsys_monto_segundo_pago', $segundo_pago);
        update_post_meta($order->get_id(), '_redsys_segundo_pago_estado', 'pendiente');

        error_log('RedSys Tokenización: Metadatos de pago fraccionado guardados para pedido ' . $order->get_id());
    }

    return $args;
}

/**
 * Summary of capturar_token_redsys
 * @param mixed $order_id
 * @return void
 */
function capturar_token_redsys($order_id)
{
    error_log('RedSys Tokenización: Procesando respuesta para pedido ' . $order_id);

    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('RedSys Tokenización: No se pudo obtener el pedido ' . $order_id);
        return;
    }

    // Verificar si es un pedido con pago fraccionado
    $es_fraccionado = get_post_meta($order_id, '_redsys_pago_fraccionado', true);

    // Solo capturar token si se ha seleccionado pago fraccionado
    if ($es_fraccionado !== 'yes') {
        error_log('RedSys Tokenización: El pedido ' . $order_id . ' no es fraccionado, no se captura token');
        return;
    }

    // Intentar obtener el token de diferentes fuentes
    $token = null;

    // 1. Intentar desde los parámetros de respuesta
    if (isset($_REQUEST['Ds_MerchantParameters'])) {
        error_log('RedSys Tokenización: Parámetros recibidos: ' . $_REQUEST['Ds_MerchantParameters']);
        $params = json_decode(base64_decode($_REQUEST['Ds_MerchantParameters']), true);
        error_log('RedSys Tokenización: Parámetros decodificados: ' . print_r($params, true));

        if (isset($params['Ds_Merchant_Identifier'])) {
            $token = sanitize_text_field($params['Ds_Merchant_Identifier']);
        } elseif (isset($params['Ds_CardNumber'])) {
            // Algunas versiones de Redsys usan este campo
            $token = sanitize_text_field($params['Ds_CardNumber']);
        } elseif (isset($params['Ds_Token'])) {
            // Otras versiones pueden usar este campo
            $token = sanitize_text_field($params['Ds_Token']);
        } elseif (isset($params['Ds_TokenPAN'])) {
            // Otra posible variante
            $token = sanitize_text_field($params['Ds_TokenPAN']);
        }
    }

    // Si encontramos el token, guardarlo
    if ($token) {
        update_post_meta($order_id, '_redsys_token', $token);
        $order->add_order_note('Token de Redsys capturado: ' . $token);
        error_log('RedSys Tokenización: Token capturado para pedido ' . $order_id . ': ' . $token);
    } else {
        error_log('RedSys Tokenización: No se pudo capturar el token para el pedido ' . $order_id);
    }
}


/**
 * Summary of limpiar_datos_fraccionamiento
 * @param mixed $order_id
 * @return void
 */
function limpiar_datos_fraccionamiento($order_id)
{
    if (!$order_id)
        return;

    error_log('RedSys Tokenización: Limpiando datos de fraccionamiento para pedido ' . $order_id);

    // 1. Limpiar datos de sesión
    if (WC()->session) {
        WC()->session->__unset('redsys_use_installments');
        error_log('RedSys Tokenización: Limpiada sesión de fraccionamiento');
    }

    // 2. Restablecer fracciones a 0 para los productos del pedido
    $order = wc_get_order($order_id);
    if ($order) {
        foreach ($order->get_items() as $item) {
            // Intentar diferentes métodos para obtener el ID del producto
            $product_id = null;

            // Método 1: Usando get_data() (compatible con la mayoría de versiones)
            if (method_exists($item, 'get_data')) {
                $data = $item->get_data();
                if (isset($data['product_id'])) {
                    $product_id = $data['product_id'];
                }
            }

            // Método 2: Acceso directo como array (versiones antiguas)
            if (!$product_id && isset($item['product_id'])) {
                $product_id = $item['product_id'];
            }

            // Método 3: Usando get_meta (versiones recientes)
            if (!$product_id && method_exists($item, 'get_meta')) {
                $product_id = $item->get_meta('_product_id');
            }

            // Si tenemos un ID de producto, actualizar sus metadatos
            if ($product_id) {
                update_post_meta($product_id, 'fracciones', '0');
				 update_post_meta($product_id, '_installments', '0');	
                error_log('RedSys Tokenización: Restablecido fracciones a 0 para producto ' . $product_id);
            } else {
                error_log('RedSys Tokenización: No se pudo obtener ID del producto para un item');
            }
        }
    }

    error_log('RedSys Tokenización: Limpieza de datos completada');
}


/**
 * Summary of redsys_pago_fraccionado_correcto
 * @param mixed $order_id
 * @return void
 */
function redsys_pago_fraccionado_correcto( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // 2. Actualizar metadatos (cambia 'mi_meta_key' y 'valor' a lo que necesites)
    $order->update_meta_data( '_redsys_segundo_pago_estado', 'pagado' );

    // 3. Añadir una nota al pedido (para el registro de actividad)
    $order->add_order_note( 'Pago fraccionado completado correctamente.' );
    
    // 4. Guardar los cambios
    $order->save();
}



/**
 * Summary of redyss_pago_fraccionado_fallido
 * @param mixed $order_id
 * @return void
 */
function redyss_pago_fraccionado_fallido( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // 2. Actualizar metadatos (cambia 'mi_meta_key' y 'valor' a lo que necesites)
    $order->update_meta_data( '_redsys_segundo_pago_estado', 'Fallido' );

    // 3. Añadir una nota al pedido
    $order->add_order_note( 'Pago fraccionado fallido. Revisar detalles con Redsys.' );
    
    // 4. Guardar los cambios
    $order->save();
}


/**
 * 
 * @param mixed $order_id
 * @param mixed $order
 * @param mixed $datos_decodificados
 * @param mixed $gateway
 * @return void
 */
function redsys_rgw_payment_processed( $order, $datos_decodificados, $gateway ) {
    // 1. Guardar metadatos extra
    $order->update_post_data( 'redsys_extra_info', $datos_decodificados );
    
    // 2. Añadir una nota al pedido
    $order->add_order_note( 'Pago Redsys procesado correctamente. Autorización: ' . $datos_decodificados['Ds_AuthorisationCode'] );
    
    // 3. Guardar
    $order->save();

    limpiar_beca_sesion( $order->get_id() );

}

add_action('woocommerce_thankyou', function($order_id) {
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        WC()->session->__unset("fracciones_{$product_id}");
    }

    WC()->session->__unset('redsys_use_installments');
});
