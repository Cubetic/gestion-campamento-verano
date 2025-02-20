<?php

add_action('wp_enqueue_scripts', 'cargar_estilos_y_scripts_en_carrito_checkout');

function cargar_estilos_y_scripts_en_carrito_checkout() {
    if (is_cart() || is_checkout()) { 
        // Cargar el archivo CSS
        wp_enqueue_style(
            'SportKids', 
            plugin_dir_url(__FILE__) . './assets/css/sportkidscamp.css', 
            array(), 
            '1.0'
        );

        // Cargar el archivo JS con dependencia de jQuery
        wp_enqueue_script(
            'SportKids', 
            plugin_dir_url(__FILE__) . './assets/js/sportkidscamp.js', 
            array('jquery'), 
            '1.0', 
            true
        );

        // Registrar jQuery si no está cargado
    if (!wp_script_is('jquery', 'enqueued')) {
        //wp_enqueue_script('sportykidscamp-js', plugin_dir_url(__FILE__) . 'assets/js/sportykidscamp.js', array('jquery'), null, true);
    }

    // Cargar el JS de tu plugin asegurando que depende de jQuery
   
    }
}