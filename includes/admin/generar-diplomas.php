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

function generar_diplomas_pdf() {
    // Verificar acceso
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado');
    }

    // Definir la clase DiplomaPDF si no existe
    if (!class_exists('DiplomaPDF')) {
        class DiplomaPDF extends FPDF {
            function Header() {
                // No header
            }
            
            function Footer() {
                // No footer
            }
            
            function GenerarDiploma($nombre, $apellidos) {
                // Array con las imágenes de fondo disponibles para diplomas
                $imagenes_fondo = [
                    'diploma-rosa.jpg',
                    'diploma-amarillo.jpg',
                    'diploma-azul.jpg'
                ];
                
                // Seleccionar una imagen aleatoria
                $imagen_seleccionada = $imagenes_fondo[array_rand($imagenes_fondo)];
                
                // Cambiar la ruta para usar la ruta del sistema de archivos
                $imagen_fondo = plugin_dir_path(__FILE__) . '../../assets/images/' . $imagen_seleccionada;
                
                // Verificar si la imagen existe
                if (!file_exists($imagen_fondo)) {
                    $this->SetFont('Arial', 'B', 12);
                    $this->Cell(0, 10, 'Error: No se encontró la imagen de fondo (' . $imagen_fondo . ')', 0, 1, 'C');
                    return;
                }
                
                // Añadir la imagen de fondo (tamaño A4 horizontal con margen de 1cm)
                $this->Image($imagen_fondo, 5, 5, 287, 200);
                
                // Nombre del alumno en el cuadro central
                $this->SetFont('Arial', 'B', 26);
                $this->SetTextColor(128, 128, 128); // Gris
                
                // Nombre completo del alumno
                $nombre_completo = mb_strtoupper($nombre . ' ' . $apellidos, 'UTF-8');
                
                // Centrar el nombre en el diploma
                $this->SetXY(0, 110);
                $this->Cell(297, 0, $nombre_completo, 0, 0, 'C');
            }
        }
    }

    // Obtener alumnos
    $args = [
        'status' => ['processing', 'completed'],
        'limit'  => -1,
        'type'   => 'shop_order'
    ];
    
    $pedidos = wc_get_orders($args);
    $alumnos = [];
    
    foreach ($pedidos as $pedido) {
        $datos_alumno = $pedido->get_meta('datos_alumno');
        
        if (!empty($datos_alumno['nombre_alumno']) || !empty($datos_alumno['apellido_alumno'])) {
            $alumnos[] = [
                'nombre' => $datos_alumno['nombre_alumno'],
                'apellidos' => $datos_alumno['apellido_alumno']
            ];
        }
    }

    if (empty($alumnos)) {
        wp_die('No se encontraron alumnos para generar diplomas.');
    }

    // Iniciar PDF en formato A4 horizontal
    $pdf = new DiplomaPDF('L', 'mm', 'A4');

    // Generar un diploma por página para cada alumno
    foreach ($alumnos as $alumno) {
        $pdf->AddPage();
        $pdf->GenerarDiploma(
            $alumno['nombre'],
            $alumno['apellidos']
        );
    }

    // Generar el PDF y ofrecerlo para descarga
    $pdf->Output('D', 'diplomas_campamento.pdf');
    exit;
}