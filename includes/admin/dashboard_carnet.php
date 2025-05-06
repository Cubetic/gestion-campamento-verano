<?php
// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Página de administración para generar carnets
function carnets_campamento_page() {
    // Verificar permisos de administrador
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos para acceder a esta página.'));
    }

    ?>
    <div class="wrap">
        <h1>Generador de Carnets para Alumnos del Campamento</h1>
        
        <?php
        // Usar admin-post.php en lugar de apuntar directamente al archivo
        $action_url = admin_url('admin-post.php');
        ?>
        
        <form method="post" action="<?php echo esc_url($action_url); ?>">
            <?php 
            wp_nonce_field('generar_carnets_pdf', 'carnets_nonce');
            ?>
            <input type="hidden" name="action" value="generar_carnets_pdf">
            
            <div class="card">
                <p><input type="submit" class="button button-primary" value="Generar Carnets"></p>
            </div>
        </form>
    </div>
    <?php
}

// Agregar la función para manejar la generación del PDF
add_action('admin_post_generar_carnets_pdf', 'manejar_generacion_carnets');
function manejar_generacion_carnets() {
    try {

        // Incluir directamente el código de generación del PDF
        require_once WP_PLUGIN_DIR . '/reservas-pedidos/includes/admin/generar-pdf.php';

        // Delegar la generación del PDF al archivo generar-pdf.php
        generar_carnets_pdf();
        
        exit;

    } catch (Exception $e) {
        // Log del error
        error_log('Error generando PDF: ' . $e->getMessage());
        wp_die('Ha ocurrido un error al generar los carnets: ' . $e->getMessage());
    }
}