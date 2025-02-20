<?php
/**
 * Plugin Name: Gestion de plazas de campamento de verano
 * Description: Muestra una tabla con los datos de las reservas realizadas por los alumnos para las escuelas Duran I Bas".
 * Version: 1.0
 * Author: Nico Demarchi
 */

if (!defined('ABSPATH')) {
    exit; // Seguridad
}

// Agregar menú en el panel de administración
function reservas_menu_admin() {
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

    // Submenú para Reservas
    add_submenu_page(
        'SportyKidsCamp',  // Slug del menú principal
        'Gestión de Reservas',  // Título de la página
        'Reservas',  // Nombre en el menú
        'manage_woocommerce',  // Permisos
        'reservas',  // Slug del submenú
        'mostrar_reservas_pedidos'  // Función para mostrar contenido
    );

    // Submenú para Horarios y Stock
    add_submenu_page(
        'SportyKidsCamp', // Slug del menú principal
        'Gestión de Stock por Horarios',  // Título de la página
        'Horarios y Stock',  // Nombre en el menú
        'manage_woocommerce',  // Permisos
        'horarios',  // Slug del submenú
        'pagina_admin_stock'  // Función para mostrar contenido
    );
    // Submenú para generar carnets en PDF
add_submenu_page(
    'SportyKidsCamp',   // Slug del menú principal
    'Carnets PDF',      // Título de la página
    'Carnets PDF',      // Nombre en el menú
    'manage_woocommerce',  // Permisos
    'carnets_pdf',      // Slug del submenú
    'pagina_admin_carnets'  // Función para mostrar la página
);


}
add_action('admin_menu', 'reservas_menu_admin');

// Incluir el archivo de funciones personalizado
require_once plugin_dir_path(__FILE__) . './includes/functions.php';
// Incluir el archivo de funciones personalizado
require_once plugin_dir_path(__FILE__) . './includes/enquenque-script.php';

function pagina_admin_stock() {
    global $wpdb;
    $tabla_stock = $wpdb->prefix . 'stock_horarios';

    // Guardar un nuevo horario
    if (isset($_POST['nuevo_horario']) && isset($_POST['nuevo_stock'])) {
        $horario = sanitize_text_field($_POST['nuevo_horario']);
        $stock = intval($_POST['nuevo_stock']);

        $wpdb->insert($tabla_stock, [
            'horario' => $horario,
            'stock' => $stock
        ]);
    }

    // Eliminar horario
    if (isset($_GET['eliminar'])) {
        $id = intval($_GET['eliminar']);
        $wpdb->delete($tabla_stock, ['id' => $id]);
    }

    // Obtener todos los horarios
    $horarios = $wpdb->get_results("SELECT * FROM $tabla_stock");

    ?>
    <div class="wrap">
        <h1>📌 Gestión de Stock por Horarios</h1>

        <h2>Añadir Nuevo Horario</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Horario (ej: 9h-14:30h)</th>
                    <td><input type="text" name="nuevo_horario" required class="regular-text"></td>
                </tr>
                <tr>
                    <th>Stock Disponible</th>
                    <td><input type="number" name="nuevo_stock" required class="small-text" min="1"></td>
                </tr>
            </table>
            <p><input type="submit" class="button button-primary" value="Añadir Horario"></p>
        </form>

        <h2>📋 Horarios Disponibles</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Horario</th>
                    <th>Stock</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horarios as $horario) : ?>
                    <tr>
                        <td><?php echo esc_html($horario->id); ?></td>
                        <td><?php echo esc_html($horario->horario); ?></td>
                        <td><?php echo esc_html($horario->stock); ?></td>
                        <td>
                            <a href="?page=gestion-stock-horarios&eliminar=<?php echo $horario->id; ?>" class="button button-small button-danger">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}


function crear_tabla_stock_horarios() {
    global $wpdb;
    $tabla_stock = $wpdb->prefix . 'stock_horarios';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $tabla_stock (
        id INT NOT NULL AUTO_INCREMENT,
        horario VARCHAR(20) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY horario (horario)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'crear_tabla_stock_horarios');

function pagina_admin_carnets() {
    global $wpdb;
    
    // Obtener los datos de los alumnos con reservas
    $reservas = $wpdb->get_results("
        SELECT post_id, meta_value as nombre_alumno
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_nombre_alumno'
    ");

    echo '<div class="wrap">';
    echo '<h1>Carnets de Alumnos</h1>';
    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr><th>Nombre del Alumno</th><th>Acción</th></tr></thead>';
    echo '<tbody>';

    foreach ($reservas as $reserva) {
        $post_id = $reserva->post_id;
        $nombre_alumno = esc_html($reserva->nombre_alumno);
        
        echo "<tr>
            <td>{$nombre_alumno}</td>
            <td>
                <a href='" . admin_url("admin-post.php?action=generar_carnet_pdf&reserva_id={$post_id}") . "' class='button button-primary'>Generar PDF</a>
            </td>
        </tr>";
    }

    echo '</tbody></table></div>';
}







