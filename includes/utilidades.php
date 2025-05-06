<?php
// utilidades.php

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

//add_action('wp_footer', 'depurar_carrito_woocommerce');
// Agrega este código al principio de tu functions.php o en tu plugin de pruebas
add_action('init', function() {
    if ( isset($_GET['reset_discount']) && $_GET['reset_discount'] == 1 ) {
        if ( WC()->session ) {
            WC()->session->set('hermano_descuento_aplicado', false);
            WC()->session->set('total_descuento_hermano', 0);
            WC()->session->set('desglose_descuento_hermano', array());
            WC()->session->set('dni_valido', false);
            // Puedes añadir un mensaje en el log para confirmar
            error_log("Se ha reseteado la variable de descuento en sesión");
        }
    }
});
/**
 * Summary of get_semanas_con_beca
 * @return array{cart_key: mixed, semana: string[]}
 */
function get_semanas_con_beca()
{
    $semanas_beca = array();
    $cart = WC()->cart->get_cart();

    foreach ($cart as $cart_item_key => $cart_item) {
        if (!isset($cart_item['wapf']))
            continue;

        // Obtener todas las semanas seleccionadas
        $semanas = array();
        $becas = array();

        // Primero obtenemos todas las semanas
        foreach ($cart_item['wapf'] as $field) {
            if ($field['type'] === 'checkboxes' && $field['label'] === 'Semanas') {
                $semanas = explode(',', $field['value']);
                $semanas = array_map('trim', $semanas);
            }
        }

        // Luego buscamos las becas
        foreach ($cart_item['wapf'] as $field) {
            if (
                $field['type'] === 'checkboxes' &&
                strpos($field['label'], 'Beca') === 0 &&
                $field['value'] === 'Solicita beca'
            ) {

                // Extraer el período de la beca del label (después de "Beca del ")
                $periodo_beca = substr($field['label'], 9);

                // Añadir la semana con beca al array resultado
                $semanas_beca[] = array(
                    'semana' => trim($periodo_beca),
                    'cart_key' => $cart_item_key
                );
            }
        }
    }

    return $semanas_beca;
}

/**
 * Summary of marcar_beca_en_sesion
 * @return void
 */
add_action( 'beca_aplicada', 'marcar_beca_en_sesion' );
function marcar_beca_en_sesion() {
    // Guardamos en sesión que la beca está activa
    WC()->session->set( 'beca_activa', true );
}


function limpiar_beca_sesion( $order_id ) {
    // Si hay sesión de WooCommerce, eliminamos la clave 'beca_activa'
    if ( WC()->session ) {
        WC()->session->__unset( 'beca_activa', false);
        WC()->session->__unset( 'dni_valido', false);
    }
}


/**
 * Summary of depurar_carrito_woocommerce
 * @return void
 */
function depurar_carrito_woocommerce()
{



     echo '<pre style="background:#fff; color:#000; padding:10px; border:1px solid #000;">';
     echo 'listando el carrito ';
     echo '<pre>';
     $WC_Cart = new WC_Cart();
     //obtener_info_cart();
         print_r($WC_Cart->get_cart());
    echo '</pre>';
 // Comprobar si el carrito está disponible
 if ( ! WC()->cart ) {
    echo "El carrito no está disponible.";
    return;
}

// Verificar si el carrito está vacío
if ( WC()->cart->is_empty() ) {
    echo "El carrito está vacío.";
    return;
}

// Mostrar el contenido del carrito con var_dump, formateado para mejor lectura
echo '<pre>';
print_r( WC()->cart->get_cart() );
echo '</pre>';


     
}



/**
 * Summary of obtener_info_cart
 * @return array[]|array{semanas: array}
 */
function obtener_info_cart() {
    // Función auxiliar para quitar cualquier contenido entre paréntesis (incluyéndolos)
    $quitar_parentesis = function( $texto ) {
        return preg_replace('/\s*\(.*?\)\s*/', '', $texto);
    };
    // Inicializamos la estructura final: 'semanas' y contador global de plazas
    $resultado = array(
        'semanas' => array()
    );
    // Obtenemos los artículos del carrito
    $cart = WC()->cart->get_cart();

    foreach ($cart as $cart_item) {
        // Verifica que el artículo tenga la información personalizada "wapf"
        if (!isset($cart_item['wapf']) || !is_array($cart_item['wapf'])) {
            continue;
        }

        $wapf = $cart_item['wapf'];

        // El primer elemento debe contener la lista de semanas separadas por comas
        if (empty($wapf[0]['value'])) {
            continue;
        }

        // Separamos la cadena en un array de semanas
        $semanas = array_map('trim', explode(',', $wapf[0]['value']));

   
        // Inicializamos la información para cada semana en la clave "semanas" del resultado
        foreach ($semanas as $semana) {
            if (!isset($resultado['semanas'][$semana])) {
                $resultado['semanas'][$semana] = array(
                    'semana'  => $semana,
                    'horario' => array(
                        'nombre' => '',
                        'precio' => 0,
                        'tipo'   => ''
                    ),
                    'acogida' => '',
                    'beca'    => ''
                );
            }
        }

        // Recorremos el resto de campos en "wapf" (índices 1 en adelante)
        for ($i = 1; $i < count($wapf); $i++) {
            $campo = $wapf[$i];
            if (empty($campo['label']) || empty($campo['value'])) {
                continue;
            }

            $label = $campo['label'];//Horari del 25 al 27 de juny
            $valor = $campo['value'];

            // Recorremos cada semana para ver a cuál pertenece el campo, según si el label contiene el nombre de la semana
            foreach ($semanas as $semana) {
                if (stripos($label, $semana) !== false) {
                    // Si es el campo "Horario"
                    if (stripos($label, 'Horario del') !== false || stripos($label, 'Horari del') !== false) {
                        $precio = 0;
                        if (!empty($campo['price']) && is_array($campo['price'])) {
                            $precio = floatval($campo['price'][0]['value']);
                        }
                        $valor_limpio = $quitar_parentesis($valor);
                        // Determinar el tipo de horario según el contenido
                        $tipo = '';
                        if (stripos($valor_limpio, '9:00h a 14:30h') !== false) {
                            $tipo = 'mañana';
                        } elseif (stripos($valor_limpio, '9:00h a 17:00h') !== false) {
                            $tipo = 'completo';
                        }

                        $resultado['semanas'][$semana]['horario'] = array(
                            'nombre' => $valor_limpio,
                            'precio' => $precio,
                            'tipo'   => $tipo
                        );
                    }
                    // Si es el campo "Acogida"
                    elseif (stripos($label, 'Acogida') !== false || stripos($label, 'Acollida') !== false) {
                        $valor_limpio = $quitar_parentesis($valor);
                        $resultado['semanas'][$semana]['acogida'] = $valor_limpio;
                    }
                    // Si es el campo "Beca": detectamos si en label o en valor aparece "beca"
                    elseif (stripos($label, 'Beca') !== false || stripos($valor, 'beca') !== false) {
                        $valor_limpio = $quitar_parentesis($valor);
                       if (stripos($valor_limpio, 'solicita beca') !== false) {
                            $resultado['semanas'][$semana]['beca'] = 'Si';
                         } 
                        // Si está vacío o cualquier otro valor, ponemos "No"
                        else {
                            $resultado['semanas'][$semana]['beca'] = 'No';
                        }
                    }
                }
            }
        }
    }
    return $resultado ;
}


// Opcional: Limpiar el estado cuando se vacía el carrito
add_action('woocommerce_cart_emptied', 'limpiar_descuento_hermano');
function limpiar_descuento_hermano() {
    WC()->session->set('descuento_hermano_aplicado', false);
    WC()->session->set('beca_activa', false);
   WC()->session->set('tiene_hermano', false);
   WC()->session->set('nombre_hermano', false);
   WC()->session->set('dni_valido', false);
}



