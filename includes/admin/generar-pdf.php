<?php
if (!defined('ABSPATH')) {
    exit;
}

// Verificar si FPDF está disponible
if (!class_exists('FPDF')) {
    $fpdf_path = dirname(__FILE__) . '../../fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        wp_die('Error: No se encuentra el archivo FPDF en ' . $fpdf_path);
    }
    require_once $fpdf_path;
}

function generar_carnets_pdf() {
    // Verificar acceso
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado');
    }

    // Verificar nonce
    //if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'generar_carnets_pdf')) {
       // wp_die('Verificación de seguridad fallida');
    //}

    // Definir la función si no existe
    if (!function_exists('obtener_alumnos_campamento_pdf')) {
        function obtener_alumnos_campamento_pdf(){
            // Obtener pedidos
            $args = [
            'status' => ['processing', 'completed'],
            'limit'  => -1,
            'type'   => 'shop_order'
            ];

            $pedidos = wc_get_orders($args);
            $alumnos = [];
            
            foreach ($pedidos as $pedido) {
                $id_pedido = $pedido->get_id();
                
                $datos_alumno = $pedido->get_meta('datos_alumno'); 
                // Obtener metadatos del pedido
                $nombre_alumno = $datos_alumno['nombre_alumno'];
                $apellido_alumno =  $datos_alumno['apellido_alumno'];
                
                // Verificar si hay datos de alumno antes de añadirlo
                if (!empty($nombre_alumno) || !empty($apellido_alumno)) {
                    // Añadir este alumno al array
                    $alumnos[] = [
                        'nombre' => $nombre_alumno,
                        'apellidos' => $apellido_alumno,
                        'id_pedido' => $id_pedido
                    ];
                }
            }
            
            return $alumnos;
        }
    }

    // Obtener alumnos según el filtro
    $alumnos = obtener_alumnos_campamento_pdf();

    if (empty($alumnos)) {
        wp_die('No se encontraron alumnos con los criterios seleccionados.');
    }

    // Definir la clase CarnetPDF si no existe
    if (!class_exists('CarnetPDF')) {
        class CarnetPDF extends FPDF {  
            function __construct() {
                parent::__construct();
                // Añadir soporte para caracteres especiales
                $this->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
                $this->AddFont('DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
            }

            // Eliminar el header y footer predeterminados  
            function Header() {  
                // No header  
            }  
              
            function Footer() {  
                // No footer  
            }  
              
            // Función para generar carnet de alumno  
            function CarnetAlumno($nombre, $apellidos, $x, $y) {  

                // Array con las imágenes de fondo disponibles  
                $imagenes_fondo = [  
                    'carnetrosa.jpg',  
                    'carnetazul.jpg',  
                    'carnetamarillo.jpg'  
                ];  
                  
                // Seleccionar una imagen aleatoria  
                $imagen_seleccionada = $imagenes_fondo[array_rand($imagenes_fondo)];

               
                // Cambiar la ruta para usar la ruta del sistema de archivos
                $imagen_fondo = plugin_dir_path(__FILE__) . '../../assets/images/' . $imagen_seleccionada;


                
                  
                // Verificar si la imagen existe  
                if (!file_exists($imagen_fondo)) {  
                    $this->SetFont('Times', 'B', 12);  
                    $this->Cell(0, 10, 'Error: No se encontro la imagen de fondo (' . $imagen_fondo . ')', 0, 1, 'C');  
                    return;  
                }  
                  
                // Guardar la posición actual  
                $this->SetXY($x, $y);  
                  
                // Añadir la imagen de fondo (ajustada para 8 carnets por página)  
                // Ancho: 95mm (A4 ancho = 210mm, dividido en 2 columnas = 105mm - margen)  
                // Alto: 65mm (A4 alto = 297mm, dividido en 4 filas = 74.25mm - margen)  
                $this->Image($imagen_fondo, $x, $y, 95, 65);  
                  
                // Configurar fuente para el nombre (arriba)  
                $this->SetFont('DejaVu', 'B', 12);
                
                // Convertir el texto a UTF-8 si no lo está
                $nombre = mb_convert_encoding($nombre, 'UTF-8', 'auto');
                $apellidos = mb_convert_encoding($apellidos, 'UTF-8', 'auto');
                
                // Combinar nombre y apellidos
                $nombre_completo = $nombre . ' ' . $apellidos;

                //$this->SetTextColor(0, 0, 0); // Negro
                $this->SetTextColor(128, 128, 128); // Gris
                  
                // Posicionar y escribir el nombre en la parte superior  
                $this->SetXY($x + 32, $y + 25);  
                $nombre_mayusculas = mb_strtoupper($nombre, 'UTF-8');  
                $this->Cell(75, 10, $nombre_mayusculas, 0, 0, 'C');  
                  
                // Configurar fuente para el apellido (abajo)  
                $this->SetFont('Times', 'B', 8);  
                  
                // Posicionar y escribir el apellido en la parte inferior  
                $this->SetXY($x + 33, $y + 32);  
                $apellidos_mayusculas = mb_strtoupper($apellidos, 'UTF-8');  
                $this->Cell(75, 10, $apellidos_mayusculas, 0, 0, 'C');  
            }  
        }  
    }
      
    // Iniciar PDF  
    $pdf = new CarnetPDF();  
    $pdf->AddPage();  
      
    // Configuración para 8 carnets por página (2 columnas x 4 filas)  
    $margen_x = 10;  
    $margen_y = 10;  
    $ancho_carnet = 95;  
    $alto_carnet = 65;  
    $espacio_x = 0;  
    $espacio_y = 5;  
      
    // Contador para controlar la disposición en la página  
    $contador = 0;  
    $carnets_por_pagina = 8;  
      
    foreach ($alumnos as $alumno) {  
        // Si ya hemos puesto el máximo de carnets en una página, añadir nueva página  
        if ($contador >= $carnets_por_pagina) {  
            $pdf->AddPage();  
            $contador = 0;  
        }  
          
        // Calcular la posición X e Y para este carnet  
        $fila = floor($contador / 2);  
        $columna = $contador % 2;  
          
        $pos_x = $margen_x + ($columna * ($ancho_carnet + $espacio_x));  
        $pos_y = $margen_y + ($fila * ($alto_carnet + $espacio_y));  
          
        // Generar carnet para el alumno  
        $pdf->CarnetAlumno(  
            $alumno['nombre'],  
            $alumno['apellidos'],  
            $pos_x,  
            $pos_y  
        );  
          
        $contador++;  
    }  
      
    // Generar el PDF y ofrecerlo para descarga  
    $pdf->Output('D', 'carnets_campamento.pdf');  
    exit;
}