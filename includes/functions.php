<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Carga el archivo de funciones de peticiones ajax
 */
require_once plugin_dir_path(__FILE__) . 'ajax.php';

/**
 * Carga el archivo de utilidades
 */
require_once plugin_dir_path(__FILE__) . 'utilidades.php';

/**
 * Carga el archivo de funciones de pago con Redsys
 */
require_once plugin_dir_path(__FILE__) . 'redsys.php';

require_once plugin_dir_path(__FILE__) . 'email.php';


add_action('woocommerce_checkout_create_order', 'crear_usuarios_tutor_y_alumno', 20, 2);
add_filter('woocommerce_enable_order_notes_field', '__return_false');
add_action('woocommerce_checkout_fields', 'mostrar_campos_formulario');
add_filter('woocommerce_checkout_fields', 'skc_remove_billing_fields_requirement');
add_action('woocommerce_checkout_create_order', 'skc_set_tutor_data_as_billing', 10, 2);
add_action('woocommerce_checkout_order_created', 'reservar_plazas_campamento', 10, 2);

//add_action('woocommerce_payment_complete', 'descontar_plazas', 10, 1);
//add_action('woocommerce_thankyou', 'descontar_plazas', 10, 1);
add_action('woocommerce_checkout_process', 'validar_campos_personalizados', 10, 1);
add_action('woocommerce_checkout_update_order_meta', 'capturar_datos_alumno');
add_filter('woocommerce_add_to_cart_redirect', 'custom_add_to_cart_redirect');
add_action('woocommerce_order_status_processing', 'descontar_plazas', 10, 1);

// Diccionario de traducción de catalán a castellano
   

function descontar_plazas($order_id)
{
    $idSemana = array(
        '25 al 27 de juny' => 1,
        '30 de juny al 4 de juliol' => 2,
        '7 al 11 de juliol' => 3,
        '14 al 18 de juliol' => 4,
        '21 al 25 de juliol' => 5,
        '28 de juliol al 1 de agost' => 6,
		'25 al 27 de junio' => 1,
        '30 de junio al 4 de julio' => 2,
        '7 al 11 de julio' => 3,
        '14 al 18 de julio' => 4,
        '21 al 25 de julio' => 5,
        '28 de julio al 1 de agosto' => 6,
    );
   
    // Obtener el pedido
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("❌ Pedido no encontrado: $order_id");
        return;
    }

    // Recuperar el metadato 'plazas_reservadas'
    $plazas_reservadas = $order->get_meta('plazas_reservadas', true);
    if (empty($plazas_reservadas)) {
        error_log("ℹ️ No hay plazas reservadas en el pedido $order_id.");
        return;
    }
    
    global $wpdb;

    error_log("🔄 Iniciando transacción en la base de datos...");
    $wpdb->query('START TRANSACTION');

    try {
        foreach ($plazas_reservadas as $nombre_semana => $datos) {
            error_log("📆 Procesando la semana: $nombre_semana");

            $tipo = $datos['horario'];
            error_log("🕒 Tipo de horario: $tipo");

       
                $semana = $idSemana[$nombre_semana];

            // Obtener el ID del horario correspondiente
            $horario = $wpdb->get_row($wpdb->prepare(
                "SELECT id, plazas, plazas_reservadas FROM wp_horarios_semana WHERE semana_id = %d AND tipo_horario = %s",
                $semana,
                $tipo
            ));

            if (!$horario) {
                error_log("❌ No se encontró horario para la semana ID $semana con tipo $tipo");
                continue;
            }
            error_log("📊 Horario encontrado (ID: {$horario->id}): Plazas disponibles: {$horario->plazas}, Plazas reservadas: {$horario->plazas_reservadas}");

            // Verificar que haya plazas disponibles
            if ($horario->plazas <= 0) {
                error_log("⚠️ No hay plazas disponibles para el horario ID {$horario->id}");
                continue;
            }

            // Descontar una plaza
            $nuevas_plazas = $horario->plazas - 1;
            $nuevas_reservas = $horario->plazas_reservadas + 1;

            error_log("➡️ Actualizando plazas para horario ID {$horario->id}: Nuevas plazas: $nuevas_plazas, Nuevas reservas: $nuevas_reservas");

            // Actualizar la cantidad de plazas y reservas en la base de datos
            $resultado = $wpdb->update(
                'wp_horarios_semana',
                ['plazas' => $nuevas_plazas, 'plazas_reservadas' => $nuevas_reservas],
                ['id' => $horario->id]
            );

            if ($resultado === false) {
                error_log("❌ Error al actualizar la base de datos para horario ID {$horario->id}");
                throw new Exception("Error en la actualización de plazas para el horario ID {$horario->id}");
            } else {
                error_log("✅ Plazas actualizadas correctamente para el horario ID {$horario->id}");
            }
        }

        error_log("✅ Confirmando transacción...");
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        error_log("❌ Error en la transacción: " . $e->getMessage());
        $wpdb->query('ROLLBACK');
    }
}


function custom_add_to_cart_redirect($url)
{
    return wc_get_cart_url();
}

/**
 * Summary of capturar_datos_alumno
 * @param mixed $order_id
 * @return void
 */
function capturar_datos_alumno($order_id)
{
    // Creamos el array que contendrá todos los datos del alumno.
    $datos_alumno = array();

    $order = wc_get_order($order_id);

    // Lista de campos personalizados a capturar del $_POST.
    $campos_personalizados = [
        'nombre_alumno',
        'apellido_alumno',
        'fecha_nacimiento',
        'curso_escolar',
        'tiene_reserva_previa',
        'nombre_reserva_anterior',
        'necesita_siesta',
        'necesita_flotador',
        'alumno_kids_us',
        'escuela_procedencia',
        'escuela_procedencia_otra',
        'tiene_discapacidad',
        'detalle_discapacidad',
        'codigo_idalu',
        'amigo_campamento',
        'nombre_amigo_campamento',
        'numero_tarjeta_sanitaria',
        'compania_seguro',
        'tiene_alergias',
        'detalle_alergias',
        'redes_sociales',
        'img_administrativas',
        'otros_aspectos',
        'nombre_tutor',
        'apellido_tutor',
        'telefono_tutor',
        'dni_tutor',
        'email_tutor',
        'direccion_tutor',
        'codigo_postal_tutor',
        'ciudad_tutor',
        'provincia_tutor',
        'pais_tutor',
		'condiciones_generales',
        'nombre_tutor_2',
        'apellido_tutor_2',
        'telefono_tutor_2',
        'dni_tutor_2',
        'email_tutor_2',
        'direccion_tutor_2',
        'codigo_postal_tutor_2',
        'ciudad_tutor_2',
        'pais_tutor_2',
    ];

    // Recorremos cada campo y lo asignamos al array de datos_alumno
    foreach ($campos_personalizados as $campo) {
        if (isset($_POST[$campo])) {
            $datos_alumno[$campo] = sanitize_text_field($_POST[$campo]);
			if (isset($campo) && $campo ==='nombre_alumno'){
				update_post_meta($order_id, 'nombre_alumno', $datos_alumno[$campo]);
			}
            if (isset($campo) && $campo ==='apellido_alumno'){
				update_post_meta($order_id, 'apellido_alumno', $datos_alumno[$campo]);
			}
			if (isset($campo) && $campo ==='dni_tutor'){
				update_post_meta($order_id, 'dni_tutor', $datos_alumno[$campo]);
			}
        }
    }
    // Finalmente, guardamos todo el array 'datos_alumno' en el pedido
    $order->update_meta_data('datos_alumno', $datos_alumno);
    // Guarda los cambios en el objeto de pedido
    $order->save();
}


/**
 * Summary of function_woocommerce_checkout_before_customer_details
 * @return void
 */
function mostrar_campos_formulario()
{
    //$ruta_formulario = plugin_dir_path(__FILE__) . 'formulario-checkout.php';

    if (!function_exists('WC'))
        return; // Verifica que WooCommerce está cargado
    if (is_admin() || !is_checkout())
        return; // Evita ejecución en el admin y en otras páginas

    static $already_executed = false; // Variable de control para evitar duplicación

    if ($already_executed)
        return; // Si ya se ejecutó, no volver a agregar los formularios
    $already_executed = true; // Marcar como ejecutado

    $checkout = WC()->checkout(); // Obtiene el objeto de checkout correctamente

    agregar_campos_checkout($checkout);
}


/**
 * Summary of agregar_campos_checkout
 * @param mixed $checkout
 * @return void
 */
function agregar_campos_checkout($checkout)
{
    // Detectar idioma actual con Polylang
    $current_lang = function_exists('pll_current_language') ? pll_current_language() : 'es';
    $is_catalan = ($current_lang === 'ca');

    // Arrays de traducciones
    $translations = [
        'datos_alumno' => $is_catalan ? '📌 Dades de l\'Alumne' : '📌 Datos del Alumno',
        'nombre_alumno' => $is_catalan ? "Nom de l'infant" : 'Nombre del niño/a',
        'apellido_alumno' => $is_catalan ? "Cognoms de l'infant" : 'Apellidos del niño/a',
        'fecha_nacimiento' => $is_catalan ? 'Data de naixement' : 'Fecha de Nacimiento',
        'curso_escolar' => $is_catalan ? 'Curs Escolar' : 'Curso Escolar',
        'selecciona_opcion' => $is_catalan ? 'Selecciona una opció' : 'Selecciona una opción',
        'primaria' => $is_catalan ? 'Primària' : 'Primaria',
        'necesita_siesta' => $is_catalan ? 'El nen/a fa migdiada?' : '¿El niño/a toma siesta?',
        'necesita_flotador' => $is_catalan ? 'El nen/a necessita bombolleta a la piscina?' : '¿El niño/a necesita burbujita en la piscina?',
        'tiene_reserva_previa' => $is_catalan ? 'Has inscrit un germà/na prèviament?' : 'Has inscrito un hermano/a previamente?',
        'nombre_reserva_anterior' => $is_catalan ? 'Nom germà/na (sense cognoms)' : 'Nombre hermano/a (sin apellidos)',
        'alumno_kids_us' => $is_catalan ? 'És alumne Kids & Us' : 'Es alumno Kids & Us',
        'escuela_procedencia' => $is_catalan ? 'Escola de procedència' : 'Escuela de procedencia',
        'otra_escuela' => $is_catalan ? 'Una altra' : 'Otra',
        'tiene_discapacidad' => $is_catalan ? 'Té alguna necessitat especial / discapacitat?' : '¿Tiene alguna necesidad especial / discapacidad?',
        'detalle_discapacidad' => $is_catalan ? 'Indiqui quina/es' : 'Indique cuál/es',
        'tiene_alergias' => $is_catalan ? 'Té alguna al·lèrgies / intolerància?' : '¿Tiene Alergias / intolerancia?',
        'detalle_alergias' => $is_catalan ? 'Indiqui quina/es' : 'Indique cuál/es',
        'amigo_campamento' => $is_catalan ? 'Amic/ga que participa al casal' : 'Amigo/a que particia en el campamento',
        'nombre_amigo_campamento' => $is_catalan ? 'Nom de l\'amic/ga al campament' : 'Nombre del amigo/a en el campamento',
        'otros_aspectos' => $is_catalan ? 'Indiqui qualsevol aspecte a tenir en compte' : 'Indique cualquier aspecto a tener en cuenta',

        // Información adicional
        'informacion_adicional' => $is_catalan ? '📌 Informació Addicional' : '📌 Informacion Adicional',
        'redes_sociales' => $is_catalan ? 'Autoritzo a Sporty Kids Camp a compartir imatges del nen/a a les seves xarxes socials' : 'Autorizo a Sporty Kids Camp a compartir imágenes del niño/a en sus redes sociales',
        'img_administrativas' => $is_catalan ? 'Autoritzo a Sporty Kids Camp a compartir imatges del nen/a només entre participants del casal' : 'Autorizo a Sporty Kids Camp a compartir imágenes del niño/a sólo entre participantes del campamento?',
        'numero_tarjeta_sanitaria' => $is_catalan ? 'Número de targeta sanitària' : 'Número de tarjeta sanitaria',
        'compania_seguro' => $is_catalan ? 'Companyia' : 'Compañía',

        // Información autorizacion de imagenes 
        'autorizacion_imagenes' => $is_catalan ? '📌 Autorització tractament imatge' : '📌 Autorización al tratamiento de imagenes',

        // Código IDALU
        'codigo_idalu_titulo' => $is_catalan ? '📌 Codi IDALU' : '📌 Código IDALU',
        'codigo_idalu' => $is_catalan ? 'Codi IDALU' : 'Código IDALU',
        'ingrese_codigo' => $is_catalan ? 'Introduïu el codi' : 'Ingrese el código',

        // Datos del tutor
        'datos_tutor' => $is_catalan ? '📌 Dades del Pare | Mare | Tutor' : '📌 Datos del Padre | Madre | Tutor',
        'nombre_tutor' => $is_catalan ? 'Nom' : 'Nombre',
        'apellido_tutor' => $is_catalan ? 'Cognoms' : 'Apellidos',
        'telefono_tutor' => $is_catalan ? 'Telèfon' : 'Teléfono',
        'dni_tutor' => $is_catalan ? 'DNI' : 'DNI',
        'email_tutor' => $is_catalan ? 'Correu Electrònic' : 'Correo Electrónico',
        'fraccionamiento' => $is_catalan ? 'desea fraccionar' : 'desea fraccionar',
        'direccion_tutor' => $is_catalan ? 'Adreça' : 'Dirección',
        'codigo_postal_tutor' => $is_catalan ? 'Codi Postal' : 'Código Postal',
        'ciudad_tutor' => $is_catalan ? 'Ciutat' : 'Ciudad',
        'pais_tutor' => $is_catalan ? 'País' : 'País',
        'si' => $is_catalan ? 'Sí' : 'Sí',
        'no' => $is_catalan ? 'No' : 'No',
        'generales' => $is_catalan ? '📌 Informacio Important' : '📌 Información importante',
        'condiciones_generales' => $is_catalan
            ? sprintf('He llegit i acceptat els <a href="%s" target="_blank">termes i condicions generals</a>', 'https://sportykidscamp.es/condicions-generals-i-autoritzacions/')
            : sprintf('He leido y accepto los <a href="%s" target="_blank">terminos y condiciones generales</a>', 'https://sportykidscamp.es/es/condiciones-generales-y-autorizaciones/'),
        // Texto para segundo tutor
        'titulo_segundo_tutor' => $is_catalan ? '📌 Afegir segon tutor' : ' 📌 Agregar segundo tutor',
        'segundo_tutor' => $is_catalan ? 'Dades del segon tutor/a' : 'Datos del segundo tutor/a',
        'labels_segundo_tutor' => $is_catalan ? 'Voleu incloure un segon tutor?' : 'Desea incluir un segundo tutor?',
    ];

    ?>
    <div id="custom_checkout_fields">
        <div class="custom_field">
            <h3><?php echo $translations['datos_alumno']; ?> </h3>

            <?php
            /**
             * Bloque de informacion del alumno
             */
            $camposDatosAlumno = [
                'nombre_alumno' => ['type' => 'text', 'label' => $translations['nombre_alumno'], 'required' => true],
                'apellido_alumno' => ['type' => 'text', 'label' => $translations['apellido_alumno'], 'required' => true],
                'fecha_nacimiento' => ['type' => 'date', 'label' => $translations['fecha_nacimiento'], 'required' => true],
            ];

            foreach ($camposDatosAlumno as $campo => $config) {
                woocommerce_form_field($campo, array_merge(['class' => ['form-row-wide']], $config), $checkout->get_value($campo, ''));
            }

            /**
             * Bloque de codigo (Idalu)
             */
            $semanas_beca = get_semanas_con_beca();

            if (!empty($semanas_beca)) {

                ?>

                <h3><?php echo $translations['codigo_idalu']; ?></h3>
                <?php

                woocommerce_form_field('codigo_idalu', [
                    'type' => 'text',
                    'class' => ['form-row-wide'],
                    'label' => $translations['codigo_idalu'],
                    'placeholder' => 'Ingrese el código',
                    'required' => false,
                ], WC()->checkout->get_value('codigo_idalu'));
            }

            /**
             * Bloque de targeta sanitaria
             */

            $tarjetaSanitaria = [
                'numero_tarjeta_sanitaria' => ['type' => 'text', 'label' => $translations['numero_tarjeta_sanitaria'], 'required' => true],
                'compania_seguro' => ['type' => 'text', 'label' => $translations['compania_seguro'], 'required' => true],
            ];
            foreach ($tarjetaSanitaria as $campo => $config) {
                woocommerce_form_field($campo, array_merge(['class' => ['form-row-wide']], $config), $checkout->get_value($campo, ''));
            }

            /**
             * Bloque de informacion adicional 
             */
            ?>
        </div>
        <div class="custom_field">
            <h3><?php echo $translations['informacion_adicional']; ?></h3>
            <?php
            $camposInformacionAdicional = [
                'curso_escolar' => [
                    'type' => 'select',
                    'label' => $translations['curso_escolar'],
                    'options' => [
                        '' => $translations['selecciona_opcion'],
                        'i3' => 'i3',
                        'i4' => 'i4',
                        'i5' => 'i5',
                        '1_primaria' => '1º ' . ($is_catalan ? 'Primària' : 'Primaria'),
                        '2_primaria' => '2º ' . ($is_catalan ? 'Primària' : 'Primaria'),
                        '3_primaria' => '3º ' . ($is_catalan ? 'Primària' : 'Primaria'),
                        '4_primaria' => '4º ' . ($is_catalan ? 'Primària' : 'Primaria'),
                        '5_primaria' => '5º ' . ($is_catalan ? 'Primària' : 'Primaria'),
                        '6_primaria' => '6º ' . ($is_catalan ? 'Primària' : 'Primaria')
                    ],
                    'required' => true
                ],
                'necesita_siesta' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['necesita_siesta'],
                    'options' => ['si' => 'Sí', 'no' => 'No'],
                    'required' => true,
                    'default' => 'No',
                ],
                'necesita_flotador' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['necesita_flotador'],
                    'options' => ['si' => 'Si', 'no' => 'No'],
                    'required' => true,
                    'default' => 'No'
                ],
                'alumno_kids_us' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['alumno_kids_us'],
                    'options' => ['si' => 'Sí', 'no' => 'No'],
                    'required' => true,
                    'default' => 'No'
                ],
                'escuela_procedencia' => [
                    'type' => 'select',
                    'class' => ['form-row-wide'],
                    'label' => $translations['escuela_procedencia'],
                    'required' => true,
                    'default' => '',
                    'options' => [
                        '' => 'Selecciona una opción',
                        'duran_i_bas' => 'Duran i Bas',
                        'jaume_i' => 'Jaume I',
                        'anglesola' => 'Anglesola',
                        'itaca' => 'Ítaca',
                        'les_corts' => 'Les Corts',
                        'pare_manyanet' => 'Pare Manyanet',
                        'santa_teresa_lisieux' => 'Santa Teresa Lisieux',
                        'otra' => 'Una altra'
                    ],
                ],
                'escuela_procedencia_otra' => [
                    'type' => 'text',
                    'class' => ['form-row-wide'],
                    'label' => $translations['otra_escuela'],
                ],
                'tiene_alergias' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['tiene_alergias'],
                    'options' => ['no' => 'No','si' => 'Sí'],
                    'required' => true,
                    'default' => ''
                ],
                'detalle_alergias' => [
                    'type' => 'text',
                    'class' => ['form-row-wide'],
                    'label' => $translations['detalle_alergias'],
                    'required' => true
                ],
                'tiene_discapacidad' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['tiene_discapacidad'],
                    'options' => ['no' => 'No','si' => 'Sí'],
                    'required' => true,
                    'default' => ''
                ],
                'detalle_discapacidad' => [
                    'type' => 'text',
                    'class' => ['form-row-wide'],
                    'label' => $translations['detalle_discapacidad'],
                    'required' => false,

                ],
                'amigo_campamento' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['amigo_campamento'],
                    'options' => ['no' => 'No','si' => 'Sí'],
                    'required' => true,
                    'default' => ''
                ],
                'nombre_amigo_campamento' => [
                    'type' => 'text',
                    'class' => ['form-row-wide'],
                    'label' => $translations['nombre_amigo_campamento'],
                    'required' => true,
                ],
                'tiene_reserva_previa' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['tiene_reserva_previa'],
                    'options' => ['no' => 'No','si' => 'Sí'],
                    'required' => true,
                    'default' => ''
                ],
                'nombre_reserva_anterior' => [
                    'type' => 'text',
                    'class' => ['form-row-wide'],
                    'label' => $translations['nombre_reserva_anterior'],
                    'required' => true
                ],
                'otros_aspectos' => ['type' => 'textarea', 'label' => $translations['otros_aspectos'], 'required' => false],
            ];
            foreach ($camposInformacionAdicional as $campo => $config) {
                woocommerce_form_field($campo, array_merge(['class' => ['form-row-wide']], $config), $checkout->get_value($campo, ''));
            }

            /**Bloque de autorizacion de imagenes
             * 
             */
            ?>
        </div>
        <div class="custom_field">
            <h3><?php echo $translations['autorizacion_imagenes']; ?></h3>
            <?php
            $campos_autorizacion_imagenes = [

                'redes_sociales' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['redes_sociales'],
                    'options' => ['si' => 'Sí', 'no' => 'No'],
                    'required' => true
                ],

                'img_administrativas' => [
                    'type' => 'radio',
                    'class' => ['form-row-wide', 'radio-inline-group'],
                    'label' => $translations['img_administrativas'],
                    'options' => ['si' => 'Sí', 'no' => 'No'],
                    'required' => true
                ],


            ];

            foreach ($campos_autorizacion_imagenes as $campo => $config) {
                woocommerce_form_field($campo, array_merge(['class' => ['form-row-wide']], $config), $checkout->get_value($campo, ''));
            }

            /**
             * Bloque de datos del tutor 
             */
            ?>
        </div>
        <div class="custom_field">

            <h3><?php echo $translations['datos_tutor']; ?></h3>
            <?php
            $campos_tutor = [
                'nombre_tutor' => ['type' => 'text', 'label' => $translations['nombre_tutor'], 'required' => true],
                'apellido_tutor' => ['type' => 'text', 'label' => $translations['apellido_tutor'], 'required' => true],
                'telefono_tutor' => ['type' => 'text', 'label' => $translations['telefono_tutor'], 'required' => true],
                'dni_tutor' => ['type' => 'text', 'label' => $translations['dni_tutor'], 'required' => true],
                'email_tutor' => [
                    'type' => 'email',
                    'class' => ['form-row-wide'],
                    'label' => $translations['email_tutor'],
                    'required' => true
                ],
                'email_tutor_confirm' => [
                    'type' => 'email',
                    'class' => ['form-row-wide'],
                    'label' => $translations['email_tutor'] . ' (confirmación)',
                    'required' => true
                ],
                'direccion_tutor' => ['type' => 'text', 'label' => $translations['direccion_tutor'], 'required' => true],
                'codigo_postal_tutor' => ['type' => 'text', 'label' => $translations['codigo_postal_tutor'], 'required' => true],
                'ciudad_tutor' => ['type' => 'text', 'label' => $translations['ciudad_tutor'], 'required' => true],
                'pais_tutor' => [
                    'type' => 'select',
                    'label' => $translations['pais_tutor'],
                    'options' => ['ES' => 'España', 'FR' => 'Francia', 'PT' => 'Portugal'],
                    'required' => false // Único campo opcional
                ],
            ];

            foreach ($campos_tutor as $campo => $config) {
                woocommerce_form_field($campo, array_merge(['class' => ['form-row-wide']], $config), $checkout->get_value($campo, ''));
            }

            /**
             * Bloque de condiciones generales.
             */
            ?>
            <h3><?php echo $translations['generales']; ?></h3>
            <?php
            woocommerce_form_field('condiciones_generales', [
                'type' => 'checkbox',
                'class' => array('form-row privacy'),
                'label_class' => array('woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'),
                'input_class' => array('woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'),
                'required' => true,
                'label' => $translations['condiciones_generales']
            ]);

            ?>
        </div>
        <div>
            <h3><?php echo $translations['titulo_segundo_tutor']; ?></h3>
            <?php
            woocommerce_form_field('segundo_tutor', [
                'type' => 'checkbox',
                'class' => ['form-row-wide'],
                'label' => $translations['labels_segundo_tutor'],
                'required' => false,
            ], $checkout->get_value('segundo_tutor'));

            ?>
            <!-- Campos para el segundo tutor, ocultos por defecto -->
            <div id="segundo_tutor_campos" class="custom_field" style="display: none; margin-top: 20px;">
                <h3><?php echo $translations['segundo_tutor']; ?></h3>
                <?php
                $camposTutor2 = [
                    'nombre_tutor_2' => ['type' => 'text', 'label' => $translations['nombre_tutor'], 'required' => false],
                    'apellido_tutor_2' => ['type' => 'text', 'label' => $translations['apellido_tutor'], 'required' => false],
                    'telefono_tutor_2' => ['type' => 'text', 'label' => $translations['telefono_tutor'], 'required' => false],
                    'dni_tutor_2' => ['type' => 'text', 'label' => $translations['dni_tutor'], 'required' => false],
                    'email_tutor_2' => [
                        'type' => 'email',
                        'class' => ['form-row-wide'],
                        'label' => $translations['email_tutor'],
                    ],
                    'email_tutor_2_confirm' => [
                        'type' => 'email',
                        'class' => ['form-row-wide'],
                        'label' => $translations['email_tutor']. ' (confirmación)',
                        'required' => false
                    ],
                    'direccion_tutor_2' => ['type' => 'text', 'label' => $translations['direccion_tutor'], 'required' => false],
                    'codigo_postal_tutor_2' => ['type' => 'text', 'label' => $translations['codigo_postal_tutor'], 'required' => false],
                    'ciudad_tutor_2' => ['type' => 'text', 'label' => $translations['ciudad_tutor'], 'required' => false],
                    'pais_tutor_2' => [
                        'type' => 'select',
                        'label' => $translations['pais_tutor'],
                        'options' => ['ES' => 'España', 'FR' => 'Francia', 'PT' => 'Portugal'],
                        'required' => false
                    ],
                ];
                foreach ($camposTutor2 as $campo => $config) {
                    woocommerce_form_field($campo, array_merge(['class' => ['form-row-wide']], $config), $checkout->get_value($campo));
                }
                ?>
            </div>
        </div>

        <?php
}

/**
 * Summary of skc_remove_billing_fields_requirement
 * @param mixed $fields
 */
function skc_remove_billing_fields_requirement($fields)
{
    // Desmarcar como requeridos para evitar errores
    $fields['billing']['billing_first_name']['required'] = false;
    $fields['billing']['billing_last_name']['required'] = false;
    $fields['billing']['billing_address_1']['required'] = false;
    $fields['billing']['billing_city']['required'] = false;
    $fields['billing']['billing_postcode']['required'] = false;
    $fields['billing']['billing_country']['required'] = false;
    $fields['billing']['billing_email']['required'] = false;
    $fields['billing']['billing_phone']['required'] = false;

    return $fields;
}


/**
 * Summary of skc_set_tutor_data_as_billing
 * @param mixed $order
 * @param mixed $data
 */
function skc_set_tutor_data_as_billing($order, $data)
{

    // Verificamos si existen los campos del tutor en $_POST y los asignamos al objeto $order
    if (isset($_POST['nombre_tutor'])) {
        $order->set_billing_first_name(sanitize_text_field($_POST['nombre_tutor']));
    }
    if (isset($_POST['apellido_tutor'])) {
        $order->set_billing_last_name(sanitize_text_field($_POST['apellido_tutor']));
    }
    if (isset($_POST['email_tutor'])) {
        $order->set_billing_email(sanitize_email($_POST['email_tutor']));
    }
    if (isset($_POST['telefono_tutor'])) {
        $order->set_billing_phone(sanitize_text_field($_POST['telefono_tutor']));
    }
    if (isset($_POST['direccion_tutor'])) {
        $order->set_billing_address_1(sanitize_text_field($_POST['direccion_tutor']));
    }
    if (isset($_POST['codigo_postal_tutor'])) {
        $order->set_billing_postcode(sanitize_text_field($_POST['codigo_postal_tutor']));
    }
    if (isset($_POST['ciudad_tutor'])) {
        $order->set_billing_city(sanitize_text_field($_POST['ciudad_tutor']));
    }
    if (isset($_POST['provincia_tutor'])) {
        $order->set_billing_state(sanitize_text_field($_POST['provincia_tutor']));
    }
    if (isset($_POST['pais_tutor'])) {
        // Asegúrate de que coincida con un código de país válido si WooCommerce lo requiere
        $order->set_billing_country(sanitize_text_field($_POST['pais_tutor']));
    }

    //asignamos los metadatos al pedido para luegoi descoentar las plazas en el cheout

}


/**
 * Summary of crear_usuarios_tutor_y_alumno
 * @param mixed $order
 * @param mixed $data
 * @return void
 */
function crear_usuarios_tutor_y_alumno($order, $data)
{
    // Variable para almacenar el ID del usuario tutor (ya sea nuevo o existente)
    $tutor_user_id = 0;

    // Solo proceder a crear/vincular tutor si el usuario NO está logueado
    if (!is_user_logged_in()) {
        // Capturamos el email del tutor desde el formulario de checkout personalizado
        $email_tutor = isset($_POST['email_tutor']) ? sanitize_email($_POST['email_tutor']) : '';

        // Si no hay email, no podemos crear ni vincular usuario
        if (empty($email_tutor)) {
            return;
        }

        // Verificar si ya existe un usuario con ese email
        $existing_user_id = email_exists($email_tutor);

        if (!$existing_user_id) {
            // Si NO existe, creamos un nuevo usuario con ese email
            $random_password = wp_generate_password(12, false);
            $user_id = wp_create_user($email_tutor, $random_password, $email_tutor);

            if (!is_wp_error($user_id)) {
                // Asignamos rol "customer" al nuevo usuario
                $user = new WP_User($user_id);
                $user->set_role('customer');

                // Guardamos datos del tutor en el perfil del usuario
                if (isset($_POST['nombre_tutor'])) {
                    update_user_meta($user_id, 'first_name', sanitize_text_field($_POST['nombre_tutor']));
                }
                if (isset($_POST['apellido_tutor'])) {
                    update_user_meta($user_id, 'last_name', sanitize_text_field($_POST['apellido_tutor']));
                }
                if (isset($_POST['telefono_tutor'])) {
                    update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['telefono_tutor']));
                }
                if (isset($_POST['direccion_tutor'])) {
                    update_user_meta($user_id, 'billing_address_1', sanitize_text_field($_POST['direccion_tutor']));
                }
                if (isset($_POST['ciudad_tutor'])) {
                    update_user_meta($user_id, 'billing_city', sanitize_text_field($_POST['ciudad_tutor']));
                }
                if (isset($_POST['fraccionamiento'])) {
                    update_user_meta($user_id, 'billing_city', sanitize_text_field($_POST['fraccionamiento']));
                }
                if (isset($_POST['provincia_tutor'])) {
                    update_user_meta($user_id, 'billing_state', sanitize_text_field($_POST['provincia_tutor']));
                }
                if (isset($_POST['pais_tutor'])) {
                    update_user_meta($user_id, 'billing_country', sanitize_text_field($_POST['pais_tutor']));
                }
                if (isset($_POST['codigo_postal_tutor'])) {
                    update_user_meta($user_id, 'billing_postcode', sanitize_text_field($_POST['codigo_postal_tutor']));
                }

                // Asignamos este nuevo usuario al pedido como "cliente"
                $order->set_customer_id($user_id);
                $tutor_user_id = $user_id;
            }
        } else {
            // Si el usuario con ese email ya existe, simplemente vinculamos el pedido a ese usuario
            $order->set_customer_id($existing_user_id);
            $tutor_user_id = $existing_user_id;
        }
    } else {
        // Si el usuario está logueado, usamos su ID
        $tutor_user_id = get_current_user_id();
    }
}

/**
 * 
 * @param mixed $order
 * @return void
 */
function reservar_plazas_campamento($order): void
{
    $info_semanal = obtener_info_cart();

    $plazas_reservadas = [];

    // Recorremos cada semana
    foreach ($info_semanal['semanas'] as $nombre_semana => $datos_semana) {
        // Extraemos la beca, el tipo de horario y el precio base
        $tipo = $datos_semana['horario']['tipo'];
        $acogida = $datos_semana['acogida']; // Asegúrate de que esta clave exista
        $beca = $datos_semana['beca']; // Asegúrate de que esta clave exista

        // Guardamos la información en un array
        $plazas_reservadas[$nombre_semana] = [
            'horario' => $tipo,
            'acogida' => $acogida,
            'beca' => $beca,
        ];
    }

    // Actualizamos el meta del pedido utilizando el método de objeto
    $order->update_meta_data('plazas_reservadas', $plazas_reservadas);

    // Guarda los cambios en el objeto de pedido
    $order->save();
}



function validar_campos_personalizados()
{
    //error_log(print_r($_POST, true));  
    // Detectar idioma actual con Polylang  
    $current_lang = function_exists('pll_current_language') ? pll_current_language() : 'es';  
    $is_catalan = ($current_lang === 'ca');  
  
    // Array de etiquetas para los campos (para mostrarlas en el mensaje)  
    $required_fields = [  
        'nombre_alumno' => $is_catalan ? "Nom de l'infant" : 'Nombre del niño/a',  
        'apellido_alumno' => $is_catalan ? "Cognoms de l'infant" : 'Apellidos del niño/a',  
        'fecha_nacimiento' => $is_catalan ? 'Data de naixement' : 'Fecha de Nacimiento',  
        'numero_tarjeta_sanitaria' => $is_catalan ? 'Número de targeta sanitària' : 'Número de Tarjeta Sanitaria',  
        'compania_seguro' => $is_catalan ? 'Companyia de segur' : 'Compañía de Seguro',  
        'curso_escolar' => $is_catalan ? 'Curs Escolar' : 'Curso Escolar',  
        'nombre_tutor' => $is_catalan ? 'Nom del Tutor' : 'Nombre del Tutor',  
        'apellido_tutor' => $is_catalan ? 'Cognoms del Tutor' : 'Apellidos del Tutor',  
        'telefono_tutor' => $is_catalan ? 'Telèfon del Tutor' : 'Teléfono del Tutor',  
        'dni_tutor' => $is_catalan ? 'DNI del Tutor' : 'DNI del Tutor',  
        'email_tutor' => $is_catalan ? 'Correu electrònic del Tutor' : 'Email del Tutor',  
        'email_tutor_confirm' => $is_catalan ? 'Confirmació del correu del Tutor' : 'Confirmación de Email del Tutor',  
        'direccion_tutor' => $is_catalan ? 'Adreça del Tutor' : 'Dirección del Tutor',  
        'codigo_postal_tutor' => $is_catalan ? 'Codi postal del Tutor' : 'Código Postal del Tutor',  
        'ciudad_tutor' => $is_catalan ? 'Ciutat del Tutor' : 'Ciudad del Tutor',  
    ];  
  
    // Mensajes de error traducidos  
    $error_required = $is_catalan ? 'Si us plau, omple el camp: %s' : 'Por favor, completa el campo: %s';  
    $error_radio_required = $is_catalan ? 'Si us plau, selecciona una opció per: %s' : 'Por favor, selecciona una opción para: %s';  
    $error_otra_escuela = $is_catalan ? 'Si us plau, indica el nom de l\'altra escola.' : 'Por favor, indica el nombre de la otra escuela.';  
    $error_detalle_alergias = $is_catalan ? 'Si us plau, detalla les al·lèrgies o intoleràncies.' : 'Por favor, detalla las alergias o intolerancias.';  
    $error_detalle_discapacidad = $is_catalan ? 'Si us plau, detalla la necessitat especial o discapacitat.' : 'Por favor, detalla la necesidad especial o discapacidad.';  
    $error_amigo_campamento = $is_catalan ? 'Si us plau, indica el nom de l\'amic/ga al campament.' : 'Por favor, indica el nombre del amigo/a en el campamento.';  
    $error_reserva_previa = $is_catalan ? 'Si us plau, indica el nom del germà/na prèviament inscrit.' : 'Por favor, indica el nombre del hermano/a previamente inscrito.';  
    $error_email_tutor = $is_catalan ? 'Els correus del Tutor no coincideixen. Si us plau, verifica-ho.' : 'Los correos del Tutor no coinciden. Por favor, verifica e inténtalo de nuevo.';  
    $error_email_tutor_2 = $is_catalan ? 'Els correus del segon Tutor no coincideixen. Si us plau, verifica-ho.' : 'Los correos del segundo Tutor no coinciden. Por favor, verifica e inténtalo de nuevo.';  
    $error_generales = $is_catalan ? 'Has d\'acceptar les condicions generals per continuar.' : 'Debes aceptar las condiciones generales para continuar.';  
  
    // 1) Validar campos básicos obligatorios  
    foreach ($required_fields as $field_key => $field_label) {  
        if (empty($_POST[$field_key])) {  
            wc_add_notice(  
                sprintf($error_required, $field_label),  
                'error'  
            );  
        }  
    }  
  
    // 2) Validar campos de tipo radio obligatorios según el curso escolar  
    if (isset($_POST['curso_escolar'])) {  
        $curso = $_POST['curso_escolar'];  
          
        // Validar siesta solo para i3  
        if ($curso === 'i3') {  
            if (!isset($_POST['necesita_siesta']) || $_POST['necesita_siesta'] === '') {  
                $label_siesta = $is_catalan ? 'El nen/a fa migdiada?' : '¿El niño/a toma siesta?';  
                wc_add_notice(sprintf($error_radio_required, $label_siesta), 'error');  
            }  
        }  
          
        // Validar flotador para i3, i4 e i5  
        if (in_array($curso, ['i3', 'i4', 'i5'])) {  
            if (!isset($_POST['necesita_flotador']) || $_POST['necesita_flotador'] === '') {  
                $label_flotador = $is_catalan ? 'El nen/a necessita bombolleta a la piscina?' : '¿El niño/a necesita flotador en la piscina?';  
                wc_add_notice(sprintf($error_radio_required, $label_flotador), 'error');  
            }  
        }  
    }  
  
    // 3) Validar campos de tipo radio obligatorios para todos los cursos  
    $required_radio_fields_all = [  
        'alumno_kids_us' => $is_catalan ? 'És alumne Kids & Us?' : '¿Es alumno Kids & Us?',  
        'tiene_alergias' => $is_catalan ? 'Té al·lèrgies?' : '¿Tiene Alergias?',  
        'tiene_discapacidad' => $is_catalan ? 'Té discapacitat?' : '¿Tiene Discapacidad?',  
        'amigo_campamento' => $is_catalan ? 'Amic/ga al campament?' : '¿Amigo en el campamento?',  
        'tiene_reserva_previa' => $is_catalan ? 'Ha inscrit un germà/na prèviament?' : '¿Has inscrito un hermano/a previamente?',  
        'redes_sociales' => $is_catalan ? 'Autorizació per a les xarxes socials' : 'Autorización redes sociales',  
        'img_administrativas' => $is_catalan ? 'Autorizació per a imatges administratives' : 'Autorización imágenes administrativas',  
    ];  
      
    foreach ($required_radio_fields_all as $field_key => $field_label) {  
        if (!isset($_POST[$field_key]) || $_POST[$field_key] === '') {  
            wc_add_notice(sprintf($error_radio_required, $field_label), 'error');  
        }  
    }

    // 3) Validar escuela de procedencia
    if (
        isset($_POST['escuela_procedencia']) &&
        $_POST['escuela_procedencia'] === 'otra' &&
        empty(trim($_POST['escuela_procedencia_otra'] ?? ''))
    ) {
        wc_add_notice($error_otra_escuela, 'error');
    }

    // 4) Validar detalle de alergias solo si "tiene_alergias" = "si"
    if (
        isset($_POST['tiene_alergias']) &&
        $_POST['tiene_alergias'] === 'si' &&
        empty(trim($_POST['detalle_alergias'] ?? ''))
    ) {
        wc_add_notice($error_detalle_alergias, 'error');
    }

    // 5) Validar detalle de discapacidad solo si "tiene_discapacidad" = "si"
    if (
        isset($_POST['tiene_discapacidad']) &&
        $_POST['tiene_discapacidad'] === 'si' &&
        empty(trim($_POST['detalle_discapacidad'] ?? ''))
    ) {
        wc_add_notice($error_detalle_discapacidad, 'error');
    }

    // 6) Validar amigo de campamento solo si "amigo_campamento" = "si"
    if (
        isset($_POST['amigo_campamento']) &&
        $_POST['amigo_campamento'] === 'si' &&
        empty(trim($_POST['nombre_amigo_campamento'] ?? ''))
    ) {
        wc_add_notice($error_amigo_campamento, 'error');
    }

    // 7) Validar nombre de reserva anterior solo si "tiene_reserva_previa" = "si"
    if (
        isset($_POST['tiene_reserva_previa']) &&
        $_POST['tiene_reserva_previa'] === 'si' &&
        empty(trim($_POST['nombre_reserva_anterior'] ?? ''))
    ) {
        wc_add_notice($error_reserva_previa, 'error');
    }

    // 8) Validar coincidencia de emails (Tutor principal)
    if (
        !empty($_POST['email_tutor']) &&
        !empty($_POST['email_tutor_confirm']) &&
        $_POST['email_tutor'] !== $_POST['email_tutor_confirm']
    ) {
        wc_add_notice($error_email_tutor, 'error');
    }

    // 9) Validar datos del segundo tutor solo si "segundo_tutor" está marcado
    if (isset($_POST['segundo_tutor']) && $_POST['segundo_tutor'] === '1') {
        // Ejemplo: solo validamos coincidencia de email del 2º tutor si se rellenaron
        if (
            !empty($_POST['email_tutor_2']) &&
            !empty($_POST['email_tutor_2_confirm']) &&
            $_POST['email_tutor_2'] !== $_POST['email_tutor_2_confirm']
        ) {
            wc_add_notice($error_email_tutor_2, 'error');
        }
    }

    // 10) Validar condiciones generales (checkbox)
    if (!isset($_POST['condiciones_generales']) || empty($_POST['condiciones_generales'])) {
        wc_add_notice($error_generales, 'error');
    }
}


function agregar_estilos_checkout() {  
    if (is_checkout()) {  
        ?>  
        <style>  
            .radio-inline-group label {  
                display: inline-block;  
                margin-right: 15px;  
            }  
            .woocommerce-error {  
                color: #b81c23;  
                margin-top: 5px;  
            }  
            .custom_field {  
                margin-bottom: 30px;  
                padding: 20px;  
                background-color: #f8f8f8;  
                border-radius: 5px;  
            }  
            .form-row {  
                margin-bottom: 15px !important;  
            }  
        </style>  
        <?php  
    }  
}  
add_action('wp_head', 'agregar_estilos_checkout');


  
// Eliminar todos los avisos de WooCommerce relacionados con cupones  
add_filter('woocommerce_add_message', 'filtrar_avisos_woocommerce');  
function filtrar_avisos_woocommerce($message) {  
    // Verificar si el mensaje está relacionado con cupones  
    if (strpos($message, 'cupó') !== false || strpos($message, 'cupón') !== false) {  
        return ''; // Devolver cadena vacía para eliminar el mensaje  
    }  
    return $message;  
}

// Función para reponer las plazas cuando el pedido cambia a cancelado o reembolsado
function reponer_plazas_en_pedido_cancelado( $order_id, $old_status, $new_status, $order ) {
    $idSemana = array(
        '25 al 27 de juny' => 1,
        '30 de juny al 4 de juliol' => 2,
        '7 al 11 de juliol' => 3,
        '14 al 18 de juliol' => 4,
        '21 al 25 de juliol' => 5,
        '28 de juliol al 1 agost' => 6,
		'25 al 27 de junio' => 1,
        '30 de junio al 4 de julio' => 2,
        '7 al 11 de julio' => 3,
        '14 al 18 de julio' => 4,
        '21 al 25 de julio' => 5,
        '28 de julio al 1 agosto' => 6,
    );
    // Si el nuevo estado es 'refunded' o 'cancelled'
    if ( in_array( $new_status, array( 'refunded', 'cancelled' ) ) ) {
        // Recuperar las plazas reservadas del pedido
        $plazas_reservadas = $order->get_meta( 'plazas_reservadas' );
        if ( empty( $plazas_reservadas ) || ! is_array( $plazas_reservadas ) ) {
            return;
        }
        global $wpdb;
        foreach ( $plazas_reservadas as $nombre_semana => $datos ) {
            if ( ! isset( $idSemana[ $nombre_semana ] ) ) {
                error_log("❌ No se encontró ID para la semana: $nombre_semana");
                continue;
            }
            
            $semana_id = $idSemana[ $nombre_semana ];
            // Se asume que el tipo de horario está en el campo 'horario'
            $tipo = isset( $datos['horario'] ) ? $datos['horario'] : '';
            
            // Buscar el registro correspondiente en la tabla wp_horarios_semana
            $horario = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, plazas, plazas_reservadas FROM wp_horarios_semana WHERE semana_id = %d AND tipo_horario = %s",
                    $semana_id,
                    $tipo
                )
            );
            
            if ( ! $horario ) {
                error_log("❌ No se encontró horario para la semana ID $semana_id con tipo $tipo");
                continue;
            }
            
            // Reponer: incrementar plazas disponibles y decrementar reservas (sin quedar negativo)
            $nuevas_plazas = $horario->plazas + 1;
            $nuevas_reservas = max( 0, $horario->plazas_reservadas - 1 );
            
            $resultado = $wpdb->update(
                'wp_horarios_semana',
                array(
                    'plazas'             => $nuevas_plazas,
                    'plazas_reservadas'  => $nuevas_reservas,
                ),
                array( 'id' => $horario->id )
            );
            
            if ( $resultado === false ) {
                error_log("❌ Error al reponer plazas para el horario ID {$horario->id}");
            } else {
                error_log("✅ Plaza repuesta para horario ID {$horario->id}: Plazas: $nuevas_plazas, Reservadas: $nuevas_reservas");
            }
        }
    }
}
add_action( 'woocommerce_order_status_changed', 'reponer_plazas_en_pedido_cancelado', 10, 4 );


function registrar_plazas_reservadas_en_log() {
    // Definir la consulta para obtener todos los pedidos
    $args = array(
        'post_type'      => 'shop_order',  // Pedidos de WooCommerce
        'post_status'    => 'any',         // Obtener pedidos con cualquier estado
        'posts_per_page' => -1,            // Obtener todos los pedidos
        'fields'         => 'ids'          // Solo obtener los IDs de los pedidos
    );

    $pedidos = get_posts($args);

    // Inicializamos un array para almacenar los datos de las semanas contadas
    $semanas_contadas = [];

    // Recorremos los pedidos para obtener el meta 'plazas_reservadas'
    foreach ($pedidos as $pedido_id) {
        // Obtener el valor del meta 'plazas_reservadas'
        $plazas_reservadas_meta = get_post_meta($pedido_id, 'plazas_reservadas', true);

        // Si existe el meta, registrarlo en el array de semanas
        if (!empty($plazas_reservadas_meta)) {
            // Contamos los valores de cada semana dentro de 'plazas_reservadas'
            foreach ($plazas_reservadas_meta as $semana => $datos) {
                // Si la semana no existe en el array, la inicializamos
                if (!isset($semanas_contadas[$semana])) {
                    $semanas_contadas[$semana] = [
                        'horario_completo' => 0,
                        'horario_mañana' => 0,
                        'acogida_Si' => 0,
                        'acogida_No' => 0,
                        'beca_Si' => 0,
                        'beca_No' => 0
                    ];
                }

                // Incrementamos los valores correspondientes para cada semana
                if (isset($datos['horario'])) {
                    if ($datos['horario'] === 'completo') {
                        $semanas_contadas[$semana]['horario_completo']++;
                    } elseif ($datos['horario'] === 'mañana' ) {
                        $semanas_contadas[$semana]['horario_mañana']++;
                    }
                }

                if (isset($datos['acogida'])) {
                    if ($datos['acogida'] === 'si') {
                        $semanas_contadas[$semana]['acogida_Si']++;
                    } elseif ($datos['acogida'] === 'no') {
                        $semanas_contadas[$semana]['acogida_No']++;
                    }
                }

                if (isset($datos['beca'])) {
                    if ($datos['beca'] === 'si') {
                        $semanas_contadas[$semana]['beca_Si']++;
                    } elseif ($datos['beca'] === 'No') {
                        $semanas_contadas[$semana]['beca_No']++;
                    }
                }
            }
        } else {
            // Si no existe el meta 'plazas_reservadas', registrar un mensaje en el log
            error_log("Pedido ID: $pedido_id | No se encontró el meta 'plazas_reservadas'.");
        }
    }

    // Una vez fuera del bucle, registramos los datos finales en el log de errores
    foreach ($semanas_contadas as $semana => $conteo) {
        $log_message = "Semana: $semana\n";
        $log_message .= "Horario Completo: " . $conteo['horario_completo'] . "\n";
        $log_message .= "Horario Mañana: " . $conteo['horario_mañana'] . "\n";
        $log_message .= "Acogida Sí: " . $conteo['acogida_Si'] . "\n";
        $log_message .= "Acogida No: " . $conteo['acogida_No'] . "\n";
        $log_message .= "Beca Sí: " . $conteo['beca_Si'] . "\n";
        $log_message .= "Beca No: " . $conteo['beca_No'] . "\n";

        // Escribir el mensaje en el archivo de log de WordPress
        error_log($log_message);
    }
}




//add_action('admin_init', 'registrar_plazas_reservadas_en_log');

