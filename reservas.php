<?php
/**
 * Plugin Name: Gestion de plazas de campamento de verano
 * Description: Muestra una tabla con los datos de las reservas realizadas por los alumnos para las escuelas Duran I Bas".
 * Version: 1.0.2
 * Author: Nico Demarchi
 */

if (!defined('ABSPATH')) {
    exit; // Seguridad
}

define('IMG_URL', plugin_dir_url(__FILE__) . 'assets/images/');
define('SKC_DB_SCHEMA_VERSION', '1.3.0');
require_once plugin_dir_path(__FILE__) . './includes/db/skc-db-schema.php';
require_once plugin_dir_path(__FILE__) . './includes/db/skc-db-migrations.php';
require_once plugin_dir_path(__FILE__) . './includes/semanas.php';
// Incluir el archivo de funciones personalizado
require_once plugin_dir_path(__FILE__) . './includes/functions.php';
require_once plugin_dir_path(__FILE__) . './includes/descuentos.php';
require_once plugin_dir_path(__FILE__) . './includes/cron.php';
require_once plugin_dir_path(__FILE__) . './includes/admin/dashboard_stock.php';
require_once plugin_dir_path(__FILE__) . './includes/admin/dashboard_escuelas.php';
require_once plugin_dir_path(__FILE__) . './includes/admin/dashboard_producto.php';
require_once plugin_dir_path(__FILE__) . './includes/admin/dashboard_carnet.php';
require_once plugin_dir_path(__FILE__) . './includes/admin/dashboard_diplomas.php';

// Agregar menú en el panel de administración
function reservas_menu_admin()
{
    // Agregar menú principal
    add_menu_page(
        'SportyKidsCamp',  // Nombre en el menú
        'SportyKidsCamp',  // Título de la página
        'manage_woocommerce', // Permisos necesarios
        'SportyKidsCamp',  // Slug del menú
        'mostrar_reservas_pedidos',  // Función para mostrar contenido
        'dashicons-calendar-alt', // Icono del menú
        56 // Posición en el menú
    );

    // Submenú para Inicio
    add_submenu_page(
        'SportyKidsCamp',  // Slug del menú principal
        'Dashboard',  // Título de la página
        'Inicio',  // Nombre en el menú
        'manage_woocommerce',  // Permisos
        'dashboard',  // Slug del submenú
        'mostrar_dashboard_sportykidscamp'
    );

    // Submenú para Horarios y Stock
    add_submenu_page(
        'SportyKidsCamp', // Slug del menú principal
        'Gestión de Stock por Horarios',  // Título de la página
        'Horarios y Stock',  // Nombre en el menú
        'manage_woocommerce',  // Permisos
        'horarios',  // Slug del submenú
        'pagina_admin_gestion_campamento'  // Función para mostrar contenido
    );

    // Submenú para Escuelas
    add_submenu_page(
        'SportyKidsCamp',
        'Escuelas',
        'Escuelas',
        'manage_woocommerce',
        'escuelas',
        'skc_admin_escuelas_page'
    );

    // Submenú para Crear Producto
    add_submenu_page(
        'SportyKidsCamp',
        'Creación de Producto',
        'Crear Producto',
        'manage_woocommerce',
        'crear-producto',
        'skc_admin_crear_producto_page'
    );

    // Submenú para generar carnets en PDF
    add_submenu_page(
        'SportyKidsCamp',   // Slug del menú principal
        'Carnets PDF',      // Título de la página
        'Carnets PDF',      // Nombre en el menú
        'manage_woocommerce',  // Permisos
        'carnets_pdf',      // Slug del submenú
        'carnets_campamento_page',  // Función para mostrar la página
    );

    add_submenu_page(
        'SportyKidsCamp',   // Slug del menú principal
        'Diplomas PDF',      // Título de la página
        'Diplomas PDF',      // Nombre en el menú
        'manage_woocommerce',  // Permisos
        'diplomas_pdf',      // Slug del submenú
        'diplomas_campamento_page',  // Función para mostrar la página
    );
    // Submenú para Pagos Fraccionados
    add_submenu_page(
        'SportyKidsCamp',   // Slug del menú principal
        'Pagos Fraccionados',  // Título de la página
        'Pagos Fraccionados',  // Nombre en el menú
        'manage_woocommerce',  // Permisos
        'pagos-fraccionados',  // Slug del submenú
        'mostrar_pagina_estado_cron'  // Función para mostrar la página
    );

    // Quitar el enlace duplicado del menú principal en la lista de submenús
    remove_submenu_page('SportyKidsCamp', 'SportyKidsCamp');

}
add_action('admin_menu', 'reservas_menu_admin');

// Función para mostrar la página de estado del cron
function mostrar_pagina_estado_cron() {
    // Verificar que la clase existe
    if (class_exists('Redsys_Pagos_Fraccionados_Cron')) {
        Redsys_Pagos_Fraccionados_Cron::mostrar_pagina_estado();
    } else {
        echo '<div class="wrap"><h1>Pagos Fraccionados</h1>';
        echo '<div class="notice notice-error"><p>El módulo de pagos fraccionados no está disponible. Por favor, verifica la instalación.</p></div>';
        echo '</div>';
    }
}

/**  
 * Función para mostrar el dashboard  
 */
function mostrar_dashboard_sportykidscamp()
{
    // Incluir el template del dashboard  
    include(plugin_dir_path(__FILE__) . 'includes/admin/dashboard.php');
}

// Registrar la función para que se ejecute al activar el plugin
register_activation_hook(__FILE__, 'crear_e_inicializar_tablas_campamento');
add_action('plugins_loaded', 'skc_ejecutar_migraciones', 20);

// También puedes ejecutar esta función manualmente una vez para inicializar los datos
// Descomenta la siguiente línea para ejecutar la función al cargar el plugin
// add_action('plugins_loaded', 'crear_e_inicializar_tablas_campamento');


add_action('wp_enqueue_scripts', 'cargar_mi_script_stock');
function cargar_mi_script_stock()
{

    // Cargar el archivo CSS
    wp_enqueue_style(
        'SportKids',
        plugin_dir_url(__FILE__) . 'assets/css/sportkidscamp.css',
        array(),
        '1.0'
    );

    wp_enqueue_style(
        'SportKids-admin',
        plugin_dir_url(__FILE__) . 'assets/css/admin.css',
        array(),
        '1.0'
    );

    if (is_checkout()) {
        // Cargar el archivo JS con dependencia de jQuery
        wp_enqueue_script(
            'SportKids-horarios',
            plugin_dir_url(__FILE__) . 'assets/js/sportkidscamp.js',
            array('jquery'),
            '1.0',
            true
        );
    }
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
    // Asegúrate de que WooCommerce esté activo y la función is_product() exista
    if (function_exists('is_product') && is_product()) {
        $product_id = get_queried_object_id();

        // Diccionario de meses para normalizar etiquetas CA->ES en frontend.
        $months_map = [
            'gener' => 'enero',
            'febrer' => 'febrero',
            'marc' => 'marzo',
            'març' => 'marzo',
            'abril' => 'abril',
            'maig' => 'mayo',
            'juny' => 'junio',
            'juliol' => 'julio',
            'agost' => 'agosto',
            'setembre' => 'septiembre',
            'octubre' => 'octubre',
            'novembre' => 'noviembre',
            'desembre' => 'diciembre',
        ];

        // Cargamos nuestro script
        wp_enqueue_script(
            'mi-script-stock',
            plugin_dir_url(__FILE__) . 'assets/js/horarios.js',
            ['jquery'],     // Dependencia de jQuery
            '1.0',          // Versión
            true            // Cargar en el footer
        );

        // Pasamos la URL de admin-ajax.php para usar en JavaScript
        wp_localize_script('mi-script-stock', 'misDatosAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'productId' => (int) $product_id,
            'escuelaId' => 0,
            'monthsMap' => $months_map,
        ]);
        // Cargar el JS de tu plugin asegurando que depende de jQuery

        // Cargar el archivo JS con dependencia de jQuery
        wp_enqueue_script(
            'SportKids-becas',
            plugin_dir_url(__FILE__) . 'assets/js/becas.js',
            array('jquery'),
            '1.0',
            true
        );
    }


}








