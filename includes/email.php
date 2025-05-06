<?php
// mail.php
//
// Agregar información sobre pagos y número de transacción antes de la tabla
add_action('woocommerce_email_before_order_table', 'agregar_info_extra_email', 10, 4);
function agregar_info_extra_email($order, $sent_to_admin, $plain_text, $email) {
    if ($plain_text) {
        return; // No modificar si el correo es en texto plano.
    }

	$current_lang = function_exists('pll_current_language') ? pll_current_language() : 'ca';  
	$es_catalan = ($current_lang === 'ca');
    // Detectar el idioma del pedido
    //$idioma_pedido = get_locale();
    //$es_catalan = (strpos($idioma_pedido, 'ca') !== false);
	
	$texto_principal = $es_catalan ? "Si s’ha realitzat el 100% del pagament, teniu ja la plaça confirmada. En cas de que s’hagi demanat pagament fraccionat, la comanda està reservada i quedarà automàticament confirmada quan rebem el segon pagament en 30 dies, que es carregarà automàticament a la targeta que acabeu d’utilitzar." : "Si se ha realizado el 100% del pago, ya tenéis la plaza confirmada. En caso de que se haya solicitado el pago fraccionado, el pedido está reservado y quedará automáticamente confirmado cuando recibamos el segundo pago en 30 días, que se cargará automáticamente a la tarjeta que acabáis de utilizar.";
	
	//imprimimos el encabezado 
	echo $texto_principal;

    // Traducción de textos
    $textos = [
        'reserva' => $es_catalan ? 'Has reservat un total de' : 'Has reservado un total de',
        'precio' => $es_catalan ? 'amb un preu de' : 'con un precio de',
        'pagado' => $es_catalan ? 'Has pagat amb' : 'Has pagado con',
        'transaccion' => $es_catalan ? 'amb el número de transacció' : 'con el número de transacción',
        'proximo_pago' => $es_catalan ? 'El proper pagament es realitzarà automàticament el' : 'El próximo pago se realizará automáticamente el',
        'aviso_pdf' => $es_catalan ? 'Aquest correu inclou adjunta la informació de la reserva i les condicions generals.' : 'Este correo incluye adjunta la información de la reserva y las condiciones generales.',
        'reservas_titulo' => $es_catalan ? 'Comanda:' : 'pedido:',
        'horario' => $es_catalan ? 'en horari' : 'en horario',
        'con_acogida' => $es_catalan ? 'amb acollida' : 'con acogida',
        'sin_acogida' => $es_catalan ? 'sense acollida' : 'sin acogida',
        'con_beca' => $es_catalan ? 'i amb beca' : 'y con beca',
        'sin_beca' => $es_catalan ? 'i sense beca' : 'y sin beca'
    ];

    // Obtener información del pedido
    $semanas_reservadas = $order->get_meta('plazas_reservadas');
    if (!is_array($semanas_reservadas)) {
        $semanas_reservadas = maybe_unserialize($semanas_reservadas);
    }
   

    // Añadir resumen de reservas
    if (!empty($semanas_reservadas)) {
        echo '<div style="margin: 20px 0; background-color: #f8f8f8; padding: 10px; border-left: 4px solid #7eb742;">';
        echo '<h3 style="margin-top: 0; color: #7eb742;">' . esc_html($textos['reservas_titulo']) . '</h3>';
        echo '<ul style="list-style-type: none; padding-left: 0; margin: 0;">';
        
        // Mostrar cada semana reservada en formato simple
        foreach ($semanas_reservadas as $semana => $datos) {
            echo '<li style="margin-bottom: 5px; font-size: 14px;">';
            
            // Semana
            echo '<strong>' . esc_html($semana) . '</strong> ';
            
            // Horario
            if (isset($datos['horario'])) {
                echo esc_html($textos['horario']) . ' ' . esc_html($datos['horario']) . ' ';
            }
            
            // Acogida
            if (isset($datos['acogida'])) {
                $tiene_acogida = ($datos['acogida'] === 'Si' || $datos['acogida'] === 'Sí' || $datos['acogida'] === true);
                echo $tiene_acogida ? esc_html($textos['con_acogida']) . ' ' : esc_html($textos['sin_acogida']) . ' ';
            }
            
            // Beca
            if (isset($datos['beca'])) {
                $tiene_beca = ($datos['beca'] === 'Si' || $datos['beca'] === 'Sí' || $datos['beca'] === true || $datos['beca'] === 'Solicita beca');
                echo $tiene_beca ? esc_html($textos['con_beca']) : esc_html($textos['sin_beca']);
            }
            
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }

    // Aviso de archivos adjuntos
    echo '<p><strong>' . esc_html($textos['aviso_pdf']) . '</strong></p>';
}


	$current_lang = function_exists('pll_current_language') ? pll_current_language() : 'ca';  
	$is_catalan = ($current_lang === 'ca');

/**
 * Adjuntar el PDF generado con los datos del alumno y el archivo de condiciones generales
 * según el idioma.
 */
add_filter( 'woocommerce_email_attachments', 'adjuntar_pdf_datos_alumno', 10, 3 );
function adjuntar_pdf_datos_alumno( $attachments, $email_id, $order ) {
    // Verificar que $order es un objeto WC_Order
    if ( ! is_a( $order, 'WC_Order' ) ) {
        return $attachments;
    }

    // Solo en correos específicos
    $emails_objetivo = array( 'new_order', 'customer_processing_order' );
    if ( ! in_array( $email_id, $emails_objetivo ) ) {
        return $attachments;
    }

    // Generar el PDF con los datos del alumno y adjuntarlo
    $pdf_path = generar_pdf_datos_alumno( $order );
    if ( $pdf_path && file_exists( $pdf_path ) ) {
        $attachments[] = $pdf_path;
    }

    // Adjuntar el archivo de condiciones generales según el idioma
    $current_lang = function_exists('pll_current_language') ? pll_current_language() : 'es';
    if ( $current_lang === 'ca' ) {
        $conditions_pdf = home_url('/wp-content/uploads/2025/03/CONDICIONS-GENERALS-I-AUTORITZACIONS-SKC25.pdf');
    } else {
        $conditions_pdf = home_url('/wp-content/uploads/2025/03/CONDICIONES-GENERALES-y-AUTORIZACIONES-SKC25.pdf');
    }
    // Convertir la URL a una ruta absoluta (suponiendo que la ruta local coincide)
    $parsed = parse_url( $conditions_pdf );
    $conditions_path = ABSPATH . ltrim( $parsed['path'], '/' );
    if ( file_exists( $conditions_path ) ) {
        $attachments[] = $conditions_path;
    }

    return $attachments;
}

	//$current_lang = function_exists('pll_current_language') ? pll_current_language() : 'ca';  
	//$is_catalan = ($current_lang === 'ca');

/**
 * Función que genera el PDF con los datos del alumno.
 */
function generar_pdf_datos_alumno($order) {  
	  // Detectar el idioma del pedido
    $idioma_pedido = get_locale();
    $is_catalan = (strpos($idioma_pedido, 'ca') !== false);

      
    // Recuperar la información del alumno desde el meta 'datos_alumno'  
    $datos_alumno = $order->get_meta('datos_alumno');  
    if (empty($datos_alumno) || !is_array($datos_alumno)) {  
        return false; // No hay datos que mostrar  
    }  
  
    // Crear la carpeta "pdfs" dentro de /uploads si no existe  
    $upload_dir = wp_upload_dir();  
    $pdf_folder = $upload_dir['basedir'] . '/pdfs';  
    if (!file_exists($pdf_folder)) {  
        wp_mkdir_p($pdf_folder);  
    }  
  
    // Definir la ruta del PDF  
    $lang_suffix = $is_catalan ? '_ca' : '_es';  
    $pdf_file_path = $pdf_folder . '/comanda_alumno_' . $datos_alumno['nombre_alumno'] . '_' . $datos_alumno['apellido_alumno'] . '_' . $order->get_id() . $lang_suffix . '_' . time() . '.pdf';  
  
    // Incluir la librería FPDF si no está cargada  
    if (!class_exists('FPDF')) {  
        require_once dirname(__FILE__) . '/fpdf/fpdf.php';  
    }  
  
    // Crear instancia de FPDF y configurar el PDF  
    $pdf = new FPDF();  
    $pdf->AddPage();  
  
    // Título centrado  
    $pdf->SetFont('Arial', 'B', 16);  
    $pdf->Cell(0, 10, utf8_decode($is_catalan ? 'Fitxa d\'Inscripció' : 'Ficha de Inscripción'), 0, 1, 'C');  
    $pdf->Ln(5);  
  
    // Arrays de traducciones (usar el array global)  
    global $translations;  
  
    // Procesar cada campo  
    foreach ($datos_alumno as $campo => $valor) {  
        // Verificar si existe una traducción para este campo  
        if (isset($translations[$campo])) {  
            $campo_formateado = $translations[$campo];  
        } else {  
            // Si no hay traducción, usar el formato predeterminado  
            $campo_formateado = ucfirst(str_replace('_', ' ', $campo));  
        }  
          // Comprobar si es el campo de condiciones generales
if ($campo === 'condiciones_generales' && $valor == 1) {
    $valor = $es_catalan ? 'Sí' : 'Sí';
}
        // Formatear valores específicos (si/no)  
        if (in_array(strtolower($valor), ['si', 'no']) && isset($translations[strtolower($valor)])) {  
            $valor = $translations[strtolower($valor)];  
        }  
          
        // Manejar arrays  
        if (is_array($valor)) {  
            $valor = implode(', ', $valor);  
        }
          
        // Escribir en el PDF  
        $pdf->SetFont('Arial', 'B', 9); 
        $pdf->Cell(95, 10, utf8_decode($campo_formateado), 1, 0);  
        $pdf->MultiCell(95, 10, utf8_decode($valor), 1, 1);  
    }  
  
    // Guardar el PDF en el servidor  
    $pdf->Output('F', $pdf_file_path);  
  
    return $pdf_file_path;  
}


// Arrays de traducciones
    $translations = [
        'nombre_alumno' => $is_catalan ? "Nom de l'infant" : 'Nombre del niño/a',
        'apellido_alumno' => $is_catalan ? "Cognoms de l'infant" : 'Apellidos del niño/a',
        'fecha_nacimiento' => $is_catalan ? 'Data de naixement' : 'Fecha de Nacimiento',
        'curso_escolar' => $is_catalan ? 'Curs Escolar' : 'Curso Escolar',
        'necesita_siesta' => $is_catalan ? 'El nen/a fa migdiada?' : '¿El niño/a hace siesta?',
        'necesita_flotador' => $is_catalan ? 'El nen/a necessita bombolleta a la piscina?' : '¿El niño/a necesita burbujita en la piscina?',
        'tiene_reserva_previa' => $is_catalan ? 'Has inscrit un germà/na prèviament?' : 'Has inscrito un hermano/a previamente?',
        'nombre_reserva_anterior' => $is_catalan ? 'Nom del germà/na' : 'Nombre del hermano/a',
        'alumno_kids_us' => $is_catalan ? 'És alumne Kids&Us' : 'Es alumno Kids&Us',
        'escuela_procedencia' => $is_catalan ? 'Escola de procedència' : 'Escuela de procedencia',
        'escuela_procedencia_otra' => $is_catalan ? 'Una altra' : 'Otra escuela',
        'tiene_discapacidad' => $is_catalan ? 'Té alguna necessitat especial / discapacitat?' : '¿Tiene alguna necesidad especial / discapacidad?',
        'detalle_discapacidad' => $is_catalan ? 'Indiqui quina/es' : 'Indique cuál/es',
        'tiene_alergias' => $is_catalan ? 'Té alguna al·lèrgies / intolerància?' : '¿Tiene Alergias / intolerancia?',
        'detalle_alergias' => $is_catalan ? 'Indiqui quina/es' : 'Indique cuál/es',
        'amigo_campamento' => $is_catalan ? 'Amic/ga que participa al casal' : 'Amigo/a que particia en el casal',
        'nombre_amigo_campamento' => $is_catalan ? 'Nom de l\'amic/ga al casal' : 'Nombre del amigo/a en el casal',
        'otros_aspectos' => $is_catalan ? 'Indiqui qualsevol aspecte a tenir en compte' : 'Indique cualquier aspecto a tener en cuenta',

        // Información adicional
        'redes_sociales' => $is_catalan ? 'Autorització imatges xarxes socials' : 'Autorizacion imágenes redes sociales',
        'img_administrativas' => $is_catalan ? 'Autorització imatges internes' : 'Autorizacion de imagenes internas',
        'numero_tarjeta_sanitaria' => $is_catalan ? 'Número de targeta sanitària' : 'Número de tarjeta sanitaria',
        'compania_seguro' => $is_catalan ? 'Companyia' : 'Compañía',

        // Código IDALU
        'codigo_idalu' => $is_catalan ? 'Codi IDALU' : 'Código IDALU',

        // Datos del tutor
        'nombre_tutor' => $is_catalan ? 'Nom tutor 1' : 'Nombre tutor 1',
        'apellido_tutor' => $is_catalan ? 'Cognom tutor 1' : 'Apellidos tutor 1',
        'telefono_tutor' => $is_catalan ? 'Telèfon tutor 1' : 'Teléfono tutor 1',
        'dni_tutor' => $is_catalan ? 'DNI tutor 1' : 'DNI tutor 1',
        'email_tutor' => $is_catalan ? 'Correu Electrònic tutor 1' : 'Correo Electrónico tutor 1',
        'direccion_tutor' => $is_catalan ? 'Adreça tutor 1' : 'Dirección tutor 1',
        'codigo_postal_tutor' => $is_catalan ? 'Codi Postal tutor 1' : 'Código Postal tutor 1',
        'ciudad_tutor' => $is_catalan ? 'Ciutat tutor 1' : 'Ciudad tutor 1',
        'pais_tutor' => $is_catalan ? 'País tutor 1' : 'País tutor 1',
        'si' => $is_catalan ? 'Sí' : 'Sí',
        'no' => $is_catalan ? 'No' : 'No',
        'condiciones_generales' => $is_catalan ? 'Autorizació de condicions generals' : 'Autorizacion de condiciones generales',   
        'nombre_tutor_2' => $is_catalan ? 'Nom tutor 2' : 'Nombre tutor 2',
        'apellido_tutor_2' => $is_catalan ? 'Cognom tutor 2' : 'Apellidos tutor 2',
        'telefono_tutor_2' => $is_catalan ? 'Telèfon tutor 2' : 'Teléfono tutor 2', 
        'dni_tutor_2' => $is_catalan ? 'DNI tutor 2' : 'DNI tutor 2',
        'email_tutor_2' => $is_catalan ? 'Correu Electrònic tutor 2' : 'Correo Electrónico tutor 2',
        'direccion_tutor_2' => $is_catalan ? 'Adreça tutor 2' : 'Dirección tutor 2',
        'codigo_postal_tutor_2' => $is_catalan ? 'Codi Postal tutor 2' : 'Código Postal tutor 2',
        'ciudad_tutor_2' => $is_catalan ? 'Ciutat tutor 2' : 'Ciudad tutor 2',
        'pais_tutor_2' => $is_catalan ? 'País tutor 2' : 'País tutor 2',
    ];
