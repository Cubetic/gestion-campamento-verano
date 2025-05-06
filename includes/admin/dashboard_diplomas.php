<?php
// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Página de administración para generar diplomas
function diplomas_campamento_page() {
    // Verificar permisos de administrador
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos para acceder a esta página.'));
    }

    ?>
    <div class="wrap">
        <h1>Generador de Diplomas para Alumnos del Campamento</h1>
        
        <?php
        // Usar admin-post.php en lugar de apuntar directamente al archivo
        $action_url = admin_url('admin-post.php');
        ?>
        
        <form method="post" action="<?php echo esc_url($action_url); ?>">
            <?php 
            wp_nonce_field('generar_diplomas_pdf', 'diplomas_nonce');
            ?>
            <input type="hidden" name="action" value="generar_diplomas_pdf">
            
            <div class="card">
                <h2>Generar los diplomas de todos los alumnos</h2>
                <p><input type="submit" class="button button-primary" value="Generar Diplomas"></p>
            </div>
        </form>
    </div>
    <?php
}

// Agregar la función para manejar la generación del PDF
add_action('admin_post_generar_diplomas_pdf', 'manejar_generacion_diplomas');
function manejar_generacion_diplomas() {
    try {

        // Incluir directamente el código de generación del PDF
        require_once WP_PLUGIN_DIR . '/reservas-pedidos/includes/admin/generar-diplomas.php';

        // Delegar la generación del PDF al archivo generar-diplomas.php
        generar_diplomas_pdf();
        
        exit;

    } catch (Exception $e) {
        // Log del error
        error_log('Error generando PDF: ' . $e->getMessage());
        wp_die('Ha ocurrido un error al generar los diplomas: ' . $e->getMessage());
    }
}