<?php
//desceuntos.php
if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

/**
 * Hook para aplicar el descuento por hermano
 */
//add_action('woocommerce_cart_calculate_fees', 'aplicar_descuento_hermano', 20, 1);
add_action('woocommerce_order_status_failed', 'limpiar_beca_sesion');
add_action('woocommerce_order_status_cancelled', 'limpiar_beca_sesion');
add_action('woocommerce_cart_calculate_fees', 'calcular_descuentos', 10, 1);
add_action('woocommerce_cart_calculate_fees', 'aplicar_descuento_hermano_checkout', 99);


/**
 * Summary of calcular_descuentos
 * @param mixed $cart
 * @return void
 */
function calcular_descuentos($cart)
{
    if (is_admin() && !defined('DOING_AJAX'))
        return;

    // Evitar cálculos múltiples
    static $run = false;
    if ($run)
        return;
    $run = true;

    $info_semanal = obtener_info_cart();
    $es_catalan = function_exists('pll_current_language') && pll_current_language() === 'ca';
    $tiene_descuento = false;
    $total_descuentos = 0;

    // Recorremos cada semana
    foreach ($info_semanal['semanas'] as $nombre_semana => $datos_semana) {
        // Extraemos la beca, el tipo de horario y el precio base
        $beca = $datos_semana['beca'] ?? '';
        $tipo = $datos_semana['horario']['tipo'] ?? '';
        $precio = $datos_semana['horario']['precio'] ?? 0;
        $periodo = $nombre_semana;

        // Verificamos si se ha solicitado beca para esta semana
        if ($beca == 'Si' && $precio > 0) {
            // Calculamos el descuento del 90%
            $descuento = $precio * 0.90;
            $total_descuentos += $descuento;

            $texto_descuento = $es_catalan
                ? sprintf('Reducció per beca - %s', $periodo)
                : sprintf('Reducción por beca - %s', $periodo);

            // Aplicamos el descuento como fee negativo
            $cart->add_fee($texto_descuento, -$descuento, true);
            $tiene_descuento = true;

            // Guardamos en sesión que tiene beca activa
            WC()->session->set('beca_activa', true);
            WC()->session->set('beca_semana_' . sanitize_title($periodo), true);

            // Disparamos acción para otros hooks
            do_action('beca_aplicada', $periodo, $descuento, $precio);

            error_log("Aplicado descuento de {$descuento}€ para el periodo {$periodo} (precio original: {$precio}€)");
        }
    }
}


/**
 * 4. Aplica descuentos por hermano en el checkout
 */
function aplicar_descuento_hermano_checkout($cart)
{
    // Solo ejecutar en el checkout
    if (!is_checkout()) {
        return;
    }

    // Verificar que se haya ingresado el dato del hermano
    $tiene_hermano = WC()->session->get('tiene_hermano');
    $nombre_hermano = WC()->session->get('nombre_hermano');
    $dni_valido = WC()->session->get('dni_valido');

    $es_catalan = function_exists('pll_current_language') && pll_current_language() === 'ca';


    if (
        $tiene_hermano !== 'valido' ||
        empty($nombre_hermano) ||
        empty($dni_valido) ||
        $dni_valido !== 'valido'
    ) {
        return;
    }


    // Verificar que existe la función obtener_info_cart()
    if (!function_exists('obtener_info_cart')) {
        error_log('❌ obtener_info_cart no existe');
        return;
    }

    $info_semanal = obtener_info_cart();

    if (!is_array($info_semanal) || !isset($info_semanal['semanas'])) {
        return;
    }

    // Inicializar el total de descuentos
    $total_descuentos = 0;

    // Recorrer cada semana y, si no tiene beca, aplicar un fee negativo
    foreach ($info_semanal['semanas'] as $nombre_semana => $datos_semana) {
        // Se considera que tiene beca si 'beca' es 'Solicita beca' o 'Si'
        $tiene_beca = isset($datos_semana['beca']) &&
            ($datos_semana['beca'] === 'Solicita beca' || $datos_semana['beca'] === 'Si');

        if (!$tiene_beca && !empty($datos_semana['horario']['precio'])) {
            $precio_semana = floatval($datos_semana['horario']['precio']);
            $descuento = $precio_semana * 0.05; // 5% de descuento para esa semana
            $total_descuentos += $descuento;

            if ($descuento > 0) {
                // Título para la línea de descuento
                $texto_descuento = $es_catalan
                ? sprintf('Reducció per germà/a - %s', $nombre_semana)
                : sprintf('Reducción por hermano - %s', $nombre_semana);

            // Aplicamos el descuento como fee negativo
            $cart->add_fee($texto_descuento, -$descuento, true);

                error_log("Fee añadido en checkout: {$texto_descuento} - Descuento: {$descuento}€");
            } else {
                $cart->remove_fee($texto_descuento);
                //$cart->calculate_totals();
            }
        }
    }

}