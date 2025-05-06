<?php
/**
 * Gestión de pagos fraccionados mediante cron
 * 
 * Este archivo maneja la programación y ejecución de pagos fraccionados
 * para el plugin SportyKidsCamp.
 */

if (!defined('ABSPATH')) {
    exit; // Salida si se accede directamente
}

class Redsys_Pagos_Fraccionados_Cron
{

    /**
     * Nombre del hook para el evento cron
     */
    const CRON_HOOK = 'redsys_procesar_pagos_fraccionados';

    /**
     * Nombre de la opción para guardar la hora del cron
     */
    const OPTION_HORA_CRON = 'redsys_cron_hora_ejecucion';

    /**
     * Nombre de la opción para guardar el registro de ejecuciones
     */
    const OPTION_REGISTRO_CRON = 'redsys_cron_registro_ejecuciones';

    /**
     * Número máximo de registros a mantener
     */
    const MAX_REGISTROS = 50;

    /**
     * Inicializa los hooks y acciones
     */
    public static function init()
    {
        // Registrar el hook para el cron
        add_action(self::CRON_HOOK, [__CLASS__, 'ejecutar_proceso_cron']);

        // Asegurar que todos los plugins estén cargados antes de verificar clases
        add_action('plugins_loaded', [__CLASS__, 'verificar_dependencias']);

        // Activar el cron al activar el plugin
        register_activation_hook(plugin_basename(dirname(__FILE__, 2) . '/gestion-plazas-campamento.php'), [__CLASS__, 'activar_cron']);

        // Desactivar el cron al desactivar el plugin
        register_deactivation_hook(plugin_basename(dirname(__FILE__, 2) . '/gestion-plazas-campamento.php'), [__CLASS__, 'desactivar_cron']);

        // Hooks para capturar resultados de pagos
        add_action('redsys_pago_fraccionado_correcto', [__CLASS__, 'registrar_pago_correcto'], 10, 1);
        add_action('redsys_pago_fraccionado_fallido', [__CLASS__, 'registrar_pago_fallido'], 10, 1);
	
    }
	
	

    /**
     * Obtiene la hora configurada para el cron
     * 
     * @return array Array con hora y minutos
     */
    public static function obtener_hora_cron()
    {
        $hora_guardada = get_option(self::OPTION_HORA_CRON, '08:00');
        $partes = explode(':', $hora_guardada);

        return [
            'hora' => isset($partes[0]) ? intval($partes[0]) : 8,
            'minuto' => isset($partes[1]) ? intval($partes[1]) : 0
        ];
    }

    /**
     * Activa el evento cron si no está programado
     */
    public static function activar_cron()
    {
        self::reprogramar_cron();
    }

    public static function verificar_dependencias()
    {
        if (!class_exists('WC_Redsys_Gateway')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p>';
                echo 'El plugin de pagos fraccionados requiere el plugin Redsys Gateway para WooCommerce.';
                echo '</p></div>';
            });
        }
    }

    /**
     * Reprograma el cron con la hora configurada
     */
    public static function reprogramar_cron()
    {
        // Desactivar el cron existente
        self::desactivar_cron();

        // Obtener la hora configurada
        $hora_cron = self::obtener_hora_cron();

        // Calcular el timestamp para la próxima ejecución
        $now = time();
        $timestamp = strtotime(sprintf('today %02d:%02d', $hora_cron['hora'], $hora_cron['minuto']));

        // Si la hora ya pasó hoy, programar para mañana
        if ($timestamp < $now) {
            $timestamp = strtotime(sprintf('tomorrow %02d:%02d', $hora_cron['hora'], $hora_cron['minuto']));
        }

        // Programar el evento
        wp_schedule_event($timestamp, 'daily', self::CRON_HOOK);

        return $timestamp;
    }

    /**
     * Desactiva el evento cron
     */
    public static function desactivar_cron()
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Registra información en el log de ejecuciones
     */
    public static function registrar_log($tipo, $mensaje, $datos = [])
    {
        $registro = get_option(self::OPTION_REGISTRO_CRON, []);

        // Limitar el tamaño del registro
        if (count($registro) >= self::MAX_REGISTROS) {
            $registro = array_slice($registro, -self::MAX_REGISTROS + 1);
        }

        // Añadir nuevo registro
        $registro[] = [
            'fecha' => current_time('mysql'),
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'datos' => $datos
        ];

        update_option(self::OPTION_REGISTRO_CRON, $registro);
    }

    /**
     * Registra un pago procesado correctamente
     */
    public static function registrar_pago_correcto($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order)
            return;

        self::registrar_log(
            'exito',
            sprintf('Pago fraccionado procesado correctamente para el pedido #%s', $order->get_order_number()),
            ['order_id' => $order_id]
        );
    }

    /**
     * Registra un pago fallido
     */
    public static function registrar_pago_fallido($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order)
            return;

        $ultimo_fallo = $order->get_meta('fracciones_ultimo_fallo', true);

        self::registrar_log(
            'error',
            sprintf('Error en el pago fraccionado para el pedido #%s', $order->get_order_number()),
            [
                'order_id' => $order_id,
                'error' => $ultimo_fallo
            ]
        );
    }

    /**
     * Ejecuta el proceso de cron llamando directamente a la función cron_process de WC_Redsys_Gateway
     */
    public static function ejecutar_proceso_cron()
    {
        // Registrar inicio de la ejecución
        self::registrar_log('info', 'Iniciando procesamiento de pagos fraccionados');

        // Intentar cargar la clase si no está disponible
        if (!class_exists('WC_Redsys_Gateway')) {
            // Ruta al archivo de la clase en el plugin de Redsys
            $redsys_file = WP_PLUGIN_DIR . '/redys-gateway-for-woocommerce-pro/classes/classes/class-wc-redsys-fraccionado.php';

            if (file_exists($redsys_file)) {
                require_once $redsys_file;
            }
        }

        // Verificar si la clase está disponible ahora
        if (class_exists('WC_RedSys_Fraccionado')) {
            // Llamar al método estático cron_process
            WC_RedSys_Fraccionado::cron_process();

            // Registrar finalización
            self::registrar_log('info', 'Finalizado el procesamiento de pagos fraccionados');

            update_option('redsys_cron_ultima_ejecucion', [
                'fecha' => current_time('mysql'),
                'ejecutado' => true
            ]);
        } else {
            // Registrar error si la clase no existe
            self::registrar_log('error', 'Error: La clase WC_Redsys_Gateway no está disponible');

            update_option('redsys_cron_ultima_ejecucion', [
                'fecha' => current_time('mysql'),
                'ejecutado' => false,
                'error' => 'La clase WC_Redsys_Gateway no está disponible'
            ]);
        }
    }

    /**
     * Función para probar el cron manualmente
     */
    public static function test_cron_process() {
        try {
            // Registrar inicio
            self::registrar_log('test', 'Iniciando prueba manual del cron');
            
            // Verificar si la clase existe
            if (!class_exists('WC_RedSys_Fraccionado')) {
                throw new Exception('La clase WC_RedSys_Fraccionado no está disponible');
            }

            // Obtener órdenes para procesar
            $orders = wc_get_orders(array(
                'limit' => -1,
                'status' => ['processing', 'completed'],
                'meta_key' => 'fracciones',
                'meta_value' => date('Y-m-d'),
                'meta_compare' => 'LIKE',
            ));

            $resultados = [
                'total_ordenes' => count($orders),
                'procesadas' => 0,
                'exitosas' => 0,
                'fallidas' => 0,
                'detalles' => []
            ];

            if (empty($orders)) {
                self::registrar_log('test', 'No se encontraron órdenes para procesar');
                return $resultados;
            }

            // Instanciar gateway y clase de fraccionamiento
            $gateway = new WC_Redsys_Gateway();
            $fraccionado = new WC_RedSys_Fraccionado();

            foreach ($orders as $order) {
                $fracciones = $order->get_meta('fracciones', true);
                $resultados['detalles'][$order->get_id()] = [];

                foreach ($fracciones as $it => $fraccion) {
                    if ($fraccion['fecha'] == date('Y-m-d') && 
                        in_array($fraccion['estado'], array('pendiente', 'fallido'))) {
                        
                        $resultados['procesadas']++;
                        
                        // Procesar el pago usando la instancia de WC_RedSys_Fraccionado
                        try {
                            $respuesta = $gateway->enviar_pago_fraccionado($order, $it);
                            
                            if ($respuesta == "OK") {
                                $fraccion['estado'] = 'pagado';
                                $resultados['exitosas']++;
                                // Usar el método de la clase directamente
                                $fraccionado->payment_processed($order, ['Ds_Response' => 'OK'], $gateway);
                                self::registrar_pago_correcto($order->get_id());
                            } else {
                                $fraccion['estado'] = 'fallido';
                                $resultados['fallidas']++;
                                $order->update_meta_data('fracciones_ultimo_fallo', $respuesta);
                                self::registrar_pago_fallido($order->get_id());
                            }

                            $fracciones[$it] = $fraccion;
                            $resultados['detalles'][$order->get_id()][] = [
                                'fraccion' => $it,
                                'estado' => $fraccion['estado'],
                                'respuesta' => $respuesta
                            ];
                        } catch (Exception $e) {
                            self::registrar_log('error', 'Error procesando pago: ' . $e->getMessage());
                        }
                    }
                }

                $order->update_meta_data('fracciones', $fracciones);
                $order->save();
            }

            // Registrar resultados
            self::registrar_log('test', 'Prueba completada', $resultados);
            
            return $resultados;

        } catch (Exception $e) {
            self::registrar_log('error', 'Error en prueba del cron: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Muestra la página de estado del cron en el panel de administración
     */
    public static function mostrar_pagina_estado() {
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'sportykidscamp'));
        }

        // Procesar cambio de hora del cron
        if (isset($_POST['cambiar_hora_cron']) && check_admin_referer('cambiar_hora_cron_pagos_fraccionados')) {
            $hora = isset($_POST['hora_cron']) ? intval($_POST['hora_cron']) : 8;
            $minuto = isset($_POST['minuto_cron']) ? intval($_POST['minuto_cron']) : 0;

            // Validar valores
            $hora = max(0, min(23, $hora));
            $minuto = max(0, min(59, $minuto));

            // Guardar la nueva hora
            update_option(self::OPTION_HORA_CRON, sprintf('%02d:%02d', $hora, $minuto));

            // Reprogramar el cron
            self::reprogramar_cron();

            // Registrar el cambio
            self::registrar_log(
                'config',
                sprintf('Hora del cron actualizada a %02d:%02d', $hora, $minuto)
            );

            echo '<div class="notice notice-success"><p>' . __('Hora del cron actualizada correctamente.', 'sportykidscamp') . '</p></div>';
        }

        // Procesar ejecución manual
        if (isset($_POST['ejecutar_cron_ahora']) && check_admin_referer('ejecutar_cron_pagos_fraccionados')) {
            self::registrar_log(
                'manual',
                'Ejecución manual del cron iniciada'
            );

            self::ejecutar_proceso_cron();

            echo '<div class="notice notice-success"><p>' . __('Procesamiento de pagos fraccionados ejecutado manualmente.', 'sportykidscamp') . '</p></div>';
        }

        // Procesar limpieza del registro
        if (isset($_POST['limpiar_registro']) && check_admin_referer('limpiar_registro_cron')) {
            update_option(self::OPTION_REGISTRO_CRON, []);
            echo '<div class="notice notice-success"><p>' . __('Registro de ejecuciones limpiado correctamente.', 'sportykidscamp') . '</p></div>';
        }

        // Obtener información del último cron ejecutado
        $ultima_ejecucion = get_option('redsys_cron_ultima_ejecucion', [
            'fecha' => __('Nunca', 'sportykidscamp'),
            'ejecutado' => false
        ]);

        // Obtener próxima ejecución programada
        $proximo_cron = wp_next_scheduled(self::CRON_HOOK);
        $proximo_cron_fecha = $proximo_cron ? date_i18n('Y-m-d H:i:s', $proximo_cron) : __('No programado', 'sportykidscamp');

        // Obtener la hora configurada
        $hora_cron = self::obtener_hora_cron();

        // Obtener el registro de ejecuciones
        $registro = get_option(self::OPTION_REGISTRO_CRON, []);

        // Contar pagos exitosos y fallidos del día de hoy
        $pagos_exitosos_hoy = 0;
        $pagos_fallidos_hoy = 0;
        $fecha_hoy = date('Y-m-d');

        foreach ($registro as $log) {
            if (substr($log['fecha'], 0, 10) === $fecha_hoy) {
                if ($log['tipo'] === 'exito') {
                    $pagos_exitosos_hoy++;
                } elseif ($log['tipo'] === 'error' && isset($log['datos']['order_id'])) {
                    $pagos_fallidos_hoy++;
                }
            }
        }

        // Modificar la estructura de columnas en el div principal
        ?>
        <div class="wrap">
            <h1><?php _e('Gestión de Pagos Fraccionados', 'sportykidscamp'); ?></h1>

            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                <!-- Columna izquierda (30%) -->
                <div style="flex: 0 0 28%; min-width: 250px;">
                    <div class="card" style="margin-bottom: 20px;">
                        <!-- Estado del Cron -->
                        <h2><?php _e('Estado del Cron', 'sportykidscamp'); ?></h2>
                        <p>
                            <strong><?php _e('Última ejecución:', 'sportykidscamp'); ?></strong>
                            <?php echo esc_html($ultima_ejecucion['fecha']); ?>
                        </p>
                        <p>
                            <strong><?php _e('Estado:', 'sportykidscamp'); ?></strong>
                            <?php
                            if (isset($ultima_ejecucion['ejecutado']) && $ultima_ejecucion['ejecutado']) {
                                echo '<span style="color:green;">' . __('Ejecutado correctamente', 'sportykidscamp') . '</span>';
                            } else {
                                echo '<span style="color:red;">' . __('No ejecutado', 'sportykidscamp') . '</span>';
                                if (isset($ultima_ejecucion['error'])) {
                                    echo ' - ' . esc_html($ultima_ejecucion['error']);
                                }
                            }
                            ?>
                        </p>
                        <p>
                            <strong><?php _e('Pagos procesados hoy:', 'sportykidscamp'); ?></strong>
                            <span style="color:green;"><?php echo $pagos_exitosos_hoy; ?>
                                <?php _e('exitosos', 'sportykidscamp'); ?></span>,
                            <span style="color:red;"><?php echo $pagos_fallidos_hoy; ?>
                                <?php _e('fallidos', 'sportykidscamp'); ?></span>
                        </p>
                        <p>
                            <strong><?php _e('Próxima ejecución programada:', 'sportykidscamp'); ?></strong>
                            <?php echo esc_html($proximo_cron_fecha); ?>
                        </p>
                    </div>

                    <div class="card" style="margin-bottom: 20px;">
                        <!-- Configuración de hora -->
                        <h2><?php _e('Configuración de hora de ejecución', 'sportykidscamp'); ?></h2>
                        <form method="post" action="">
                            <?php wp_nonce_field('cambiar_hora_cron_pagos_fraccionados'); ?>
                            <p>
                                <label for="hora_cron"><?php _e('Hora:', 'sportykidscamp'); ?></label>
                                <select name="hora_cron" id="hora_cron">
                                    <?php for ($i = 0; $i < 24; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php selected($hora_cron['hora'], $i); ?>>
                                            <?php echo sprintf('%02d', $i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>

                                <label for="minuto_cron"><?php _e('Minuto:', 'sportykidscamp'); ?></label>
                                <select name="minuto_cron" id="minuto_cron">
                                    <?php for ($i = 0; $i < 60; $i += 5): ?>
                                        <option value="<?php echo $i; ?>" <?php selected($hora_cron['minuto'], $i); ?>>
                                            <?php echo sprintf('%02d', $i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </p>
                            <p class="description">
                                <?php _e('El cron se ejecutará diariamente a esta hora.', 'sportykidscamp'); ?>
                            </p>
                            <p>
                                <input type="submit" name="cambiar_hora_cron" class="button button-primary"
                                    value="<?php esc_attr_e('Guardar hora', 'sportykidscamp'); ?>">
                            </p>
                        </form>
                    </div>

                    <div class="card" style="margin-bottom: 20px;">
                        <!-- Ejecutar manualmente -->
                        <h2><?php _e('Ejecutar manualmente', 'sportykidscamp'); ?></h2>
                        <p><?php _e('Puedes ejecutar manualmente el procesamiento de pagos fraccionados haciendo clic en el botón a continuación.', 'sportykidscamp'); ?>
                        </p>
                        <form method="post" action="">
                            <?php wp_nonce_field('ejecutar_cron_pagos_fraccionados'); ?>
                            <input type="submit" name="ejecutar_cron_ahora" class="button button-primary"
                                value="<?php esc_attr_e('Ejecutar ahora', 'sportykidscamp'); ?>">
                        </form>
                    </div>
                </div>

                <!-- Columna derecha (70%) -->
                <div style="flex: 0 0 70%; min-width: 600px;">
                    <!-- Historial de Pagos Fallidos -->
                    <div class="card" style="margin-bottom: 20px; width: 100%; max-width: 1200px">
                        <h2><?php _e('Historial de Pagos Fallidos', 'sportykidscamp'); ?></h2>

                        <?php
                        // Reemplazar la sección de consulta de pedidos fallidos con este código:
                        $args = array(
                            'limit' => -1,
                            'status' => array('processing', 'completed'),
                            'orderby' => 'date',
                            'order' => 'DESC',
                            'return' => 'ids', // Primero obtenemos solo los IDs para optimizar
                        );

                        $order_ids = wc_get_orders($args);
                        $pedidos_fallidos = array();

                        // Filtrar manualmente los pedidos con pagos fallidos
                        foreach ($order_ids as $order_id) {
                            $order = wc_get_order($order_id);
                            $fracciones = $order->get_meta('fracciones', true);
                            
                            if (!empty($fracciones) && is_array($fracciones)) {
                                foreach ($fracciones as $key => $fraccion) {
                                    if (isset($fraccion['estado']) && $fraccion['estado'] === 'fallido') {
                                        $pedidos_fallidos[] = array(
                                            'order' => $order,
                                            'fraccion' => $key,
                                            'fecha' => $fraccion['fecha'],
                                            'ultimo_error' => $order->get_meta('fracciones_ultimo_fallo', true)
                                        );
                                    }
                                }
                            }
                        }

                        // Ordenar por fecha de más reciente a más antiguo
                        usort($pedidos_fallidos, function($a, $b) {
                            return strtotime($b['fecha']) - strtotime($a['fecha']);
                        });

                        if (empty($pedidos_fallidos)) {
                            echo '<p>' . __('No se encontraron pagos fallidos.', 'sportykidscamp') . '</p>';
                        } else {
                            echo '<table class="widefat" style="width: 100%";>
                                <thead>
                                    <tr>
                                        <th>' . __('Pedido', 'sportykidscamp') . '</th>
                                        <th>' . __('Cliente', 'sportykidscamp') . '</th>
                                        <th>' . __('Email', 'sportykidscamp') . '</th>
                                        <th>' . __('Fecha Programada', 'sportykidscamp') . '</th>
                                        <th>' . __('Nº Pago', 'sportykidscamp') . '</th>
                                        <th>' . __('Error', 'sportykidscamp') . '</th>
                                        <th>' . __('Acciones', 'sportykidscamp') . '</th>
                                    </tr>
                                </thead>
                                <tbody>';

                            foreach ($pedidos_fallidos as $item) {
                                $order = $item['order'];
                                echo '<tr>
                                    <td><a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '">#' . 
                                        $order->get_order_number() . '</a></td>
                                    <td>' . esc_html($order->get_formatted_billing_full_name()) . '</td>
                                    <td>' . esc_html($order->get_billing_email()) . '</td>
                                    <td>' . esc_html($item['fecha']) . '</td>
                                    <td>' . __('Pago 2', 'sportykidscamp') . '</td>
                                    <td>' . esc_html($item['ultimo_error']) . '</td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            ' . wp_nonce_field('reintentar_pago_fraccionado', '_wpnonce', true, false) . '
                                            <input type="hidden" name="order_id" value="' . $order->get_id() . '">
                                            <input type="hidden" name="fraccion" value="1">
                                            <button type="submit" name="reintentar_pago" class="button button-small">
                                                ' . __('Reintentar', 'sportykidscamp') . '
                                            </button>
                                        </form>
                                    </td>
                                </tr>';
                            }

                            echo '</tbody></table>';
                        }
                        ?>
                    </div>
					
					 <div class="card" style="margin-bottom: 20px;  max-width: 1200px">
                        <!-- Pagos pendientes para hoy -->
                        <h2><?php _e('Pagos pendientes para hoy', 'sportykidscamp'); ?></h2>
                        <?php
                        // Buscar pedidos con fracciones programadas para hoy
                        $orders = wc_get_orders([
                            'limit' => -1,
                            'status' => ['processing', 'completed'],
                            'meta_key' => 'fracciones',
                            'meta_value' => date('Y-m-d'),
                            'meta_compare' => 'LIKE',
                        ]);

                        if (empty($orders)) {
                            echo '<p>' . __('No hay pagos fraccionados programados para hoy.', 'sportykidscamp') . '</p>';
                        } else {
                            echo '<table class="widefat">';
                            echo '<thead><tr>';
                            echo '<th>' . __('Pedido', 'sportykidscamp') . '</th>';
                            echo '<th>' . __('Cliente', 'sportykidscamp') . '</th>';
                            echo '<th>' . __('Email', 'sportykidscamp') . '</th>';
                            echo '<th>' . __('Estado', 'sportykidscamp') . '</th>';
                            echo '</tr></thead><tbody>';

                            foreach ($orders as $order) {
                                $fracciones = $order->get_meta('fracciones', true);
                                $hay_para_hoy = false;

                                if (is_array($fracciones)) {
                                    foreach ($fracciones as $fraccion) {
                                        if (
                                            isset($fraccion['fecha']) && $fraccion['fecha'] == date('Y-m-d') &&
                                            isset($fraccion['estado']) && in_array($fraccion['estado'], ['pendiente', 'fallido'])
                                        ) {
                                            $hay_para_hoy = true;
                                            echo '<tr>';
                                            echo '<td><a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '">#' . $order->get_order_number() . '</a></td>';
                                            echo '<td>' . esc_html($order->get_formatted_billing_full_name()) . '</td>';
                                            echo '<td>' . esc_html($order->get_billing_email()) . '</td>';
                                            echo '<td>' . esc_html($fraccion['estado']) . '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                }

                                if (!$hay_para_hoy) {
                                    // Si no hay fracciones para hoy, no mostrar este pedido
                                    continue;
                                }
                            }

                            echo '</tbody></table>';
                        }
                        ?>
                    </div>

                    <!-- Registro de ejecuciones -->
                    <div class="card" style="margin-bottom: 20px; max-width: 1200px">
                        <h2><?php _e('Registro de Ejecuciones', 'sportykidscamp'); ?></h2>
                        <?php if (empty($registro)): ?>
                            <p><?php _e('No hay registros de ejecuciones.', 'sportykidscamp'); ?></p>
                        <?php else: ?>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Fecha', 'sportykidscamp'); ?></th>
                                        <th><?php _e('Tipo', 'sportykidscamp'); ?></th>
                                        <th><?php _e('Mensaje', 'sportykidscamp'); ?></th>
                                        <th><?php _e('Detalles', 'sportykidscamp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Mostrar los registros en orden inverso (más recientes primero)
                                    $registro_invertido = array_reverse($registro);
                                    foreach ($registro_invertido as $log):
                                        // Determinar el color según el tipo
                                        $color = '';
                                        switch ($log['tipo']) {
                                            case 'error':
                                                $color = 'color:red;';
                                                break;
                                            case 'exito':
                                                $color = 'color:green;';
                                                break;
                                            case 'manual':
                                                $color = 'color:blue;';
                                                break;
                                        }
                                        ?>
                                        <tr style="<?php echo $color; ?>">
                                            <td><?php echo esc_html($log['fecha']); ?></td>
                                            <td><?php echo esc_html($log['tipo']); ?></td>
                                            <td><?php echo esc_html($log['mensaje']); ?></td>
                                            <td>
                                                <?php
                                                if (!empty($log['datos'])) {
                                                    if (isset($log['datos']['pedidos']) && is_array($log['datos']['pedidos'])) {
                                                        echo __('Pedidos: ', 'sportykidscamp') . implode(', ', array_map(function ($id) {
                                                            return '<a href="' . admin_url('post.php?post=' . $id . '&action=edit') . '">#' . $id . '</a>';
                                                        }, $log['datos']['pedidos']));
                                                    } elseif (isset($log['datos']['order_id'])) {
                                                        echo __('Pedido: ', 'sportykidscamp') . '<a href="' . admin_url('post.php?post=' . $log['datos']['order_id'] . '&action=edit') . '">#' . $log['datos']['order_id'] . '</a>';

                                                        if (isset($log['datos']['error'])) {
                                                            echo '<br>' . __('Error: ', 'sportykidscamp') . esc_html($log['datos']['error']);
                                                        }
                                                    } else {
                                                        echo esc_html(json_encode($log['datos']));
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <form method="post" action="" style="margin-top: 10px;">
                                <?php wp_nonce_field('limpiar_registro_cron'); ?>
                                <input type="submit" name="limpiar_registro" class="button"
                                    value="<?php esc_attr_e('Limpiar registro', 'sportykidscamp'); ?>">
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}



// Inicializar la clase
Redsys_Pagos_Fraccionados_Cron::init();