<?php

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Página de administración para Creación de Producto
function skc_admin_crear_producto_page() {
    // Verificar permisos de administrador
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos para acceder a esta página.'));
    }

    ?>
    <div class="wrap">
        <h1>Creación de Producto</h1>
        
        <div class="card">
            <p><strong>Esta guía te muestra el proceso completo para crear una nueva escuela/campamento, configurar sus semanas, crear un producto en WooCommerce y vincularlo correctamente con todos los campos necesarios.</strong></p>
        </div>

        <!-- NOTAS PREVIAS -->
        <div class="notice notice-info" style="margin-top: 20px; margin-bottom: 25px;">
            <p><strong>ℹ️ Nota importante:</strong> El ID del producto de WooCommerce se asignará más adelante una vez creado el producto. Por ahora puedes dejarlo en blanco.</p>
        </div>

        <div class="notice notice-info" style="margin-bottom: 25px;">
            <p><strong>💡 Recomendación:</strong> Sigue los formatos sugeridos en cada campo para asegurar compatibilidad posterior con los campos dinámicos del producto.</p>
        </div>

        <!-- PASO 1 -->
        <h2 style="margin-top: 40px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Paso 1: Crear Escuela</h2>
        
        <div class="card" style="background-color: #f9f9f9; padding: 20px; margin: 15px 0;">
            <ol style="line-height: 1.8;">
                <li>Accede a la sección <strong>Escuelas</strong> en el panel de administración del plugin.</li>
                <li>Haz clic en el botón <strong>Crear escuela</strong>.</li>
                <li>Se desplegará el formulario de creación con los siguientes campos:
                    <ul style="margin-top: 8px;">
                        <li>Nombre de la escuela</li>
                        <li>Descripción</li>
                        <li>Contacto</li>
                        <li><strong>ID del producto de WooCommerce</strong> (dejar vacío por ahora, lo asignaremos después)</li>
                    </ul>
                </li>
                <li>Introduce los datos requeridos.</li>
                <li>Haz clic en <strong>Crear escuela</strong>.</li>
                <li>La nueva escuela aparecerá en la tabla de escuelas ubicada arriba.</li>
            </ol>
        </div>

        <!-- PASO 2 -->
        <h2 style="margin-top: 40px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Paso 2: Crear Semanas</h2>
        
        <div class="card" style="background-color: #f9f9f9; padding: 20px; margin: 15px 0;">
            <ol style="line-height: 1.8;">
                <li>En la misma sección de <strong>Escuelas</strong>, desplázate hacia abajo.</li>
                <li>Haz clic en el botón <strong>Nueva semana</strong>.</li>
                <li>Se abrirá el formulario de creación de semana con los siguientes campos:
                    <ul style="margin-top: 8px;">
                        <li><strong>Escuela:</strong> En el select, elige la escuela que acabas de crear.</li>
                        <li><strong>Semana (ES):</strong> Introduce el nombre o rango de fechas (ej: <em>9 al 13 de abril</em>). Recomendación: sigue el formato "DD al DD de mes".</li>
                        <li><strong>Horario Mañana:</strong> Introduce el rango horario (ej: <em>8:00h a 14:00h</em>).</li>
                        <li><strong>Plazas Mañana:</strong> Número de plazas disponibles.</li>
                        <li><strong>Horario Completo:</strong> Introduce el rango horario (ej: <em>8:00h a 17:00h</em>).</li>
                        <li><strong>Plazas Completo:</strong> Número de plazas disponibles.</li>
                    </ul>
                </li>
                <li>Una vez completo el formulario, haz clic en <strong>Crear semana</strong>.</li>
                <li>Si tienes el filtro de escuela aplicado, la nueva semana aparecerá en la tabla.</li>
                <li><strong>Repite este proceso tantas veces como sea necesario</strong> hasta tener todas las semanas que deseas poner a la venta.</li>
            </ol>
        </div>

        <!-- PASO 3 -->
        <h2 style="margin-top: 40px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Paso 3: Crear Producto en WooCommerce y Vincularlo</h2>
        
        <div class="card" style="background-color: #f9f9f9; padding: 20px; margin: 15px 0;">
            <h3 style="margin-top: 0;">3.1 Crear el Producto</h3>
            <ol style="line-height: 1.8;">
                <li>Accede a la sección <strong>Productos</strong> de WooCommerce.</li>
                <li>Haz clic en <strong>Añadir nuevo producto</strong>.</li>
                <li>Introduce el nombre del producto (ej: nombre de la escuela).</li>
                <li>Rellena otros detalles básicos del producto según sea necesario.</li>
                <li>Haz clic en <strong>Publicar</strong> para crear el producto.</li>
                <li>Ahora ve a <strong>Todos los productos</strong> para localizar el producto que acabas de crear.</li>
                <li>Pasa el ratón sobre el nombre del producto. Verás que aparece su <strong>ID de producto</strong>.</li>
                <li><strong>Copia el ID del producto</strong>.</li>
            </ol>

            <h3 style="margin-top: 30px;">3.2 Vincular el Producto a la Escuela</h3>
            <ol style="line-height: 1.8;">
                <li>Ve a la sección <strong>Escuelas</strong> del plugin.</li>
                <li>Haz clic en <strong>Editar</strong> para la escuela que creaste en el Paso 1.</li>
                <li>Localiza el campo <strong>Producto WooCommerce (ID)</strong>.</li>
                <li>Pega el ID que copiaste.</li>
                <li>Haz clic en <strong>Actualizar escuela</strong>.</li>
            </ol>
        </div>

        <!-- PASO 4 -->
        <h2 style="margin-top: 40px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Paso 4: Configurar Campos de Entrada en el Producto (Input Fields)</h2>
        
        <div class="card" style="background-color: #f9f9f9; padding: 20px; margin: 15px 0;">
            <h3 style="margin-top: 0;">4.1 Crear Campo de Semanas (Casillas de Verificación)</h3>
            <ol style="line-height: 1.8;">
                <li>Ve a <strong>Todos los productos</strong> en WooCommerce.</li>
                <li><strong>Edita</strong> el producto que creaste.</li>
                <li>Desplázate hacia abajo hasta encontrar la sección <strong>Datos del producto</strong>.</li>
                <li>Busca la subsección <strong>Product input fields</strong> (Campos de entrada del producto).</li>
                <li>Haz clic en el botón azul <strong>+ input field</strong>.</li>
                <li>En el modal que aparece, selecciona <strong>Casillas de verificación</strong>.</li>
                <li>Rellena los siguientes campos:
                    <ul style="margin-top: 8px;">
                        <li><strong>Etiqueta:</strong> <code>Semanas</code> (tal cual)</li>
                        <li><strong>Opciones:</strong> Añade una opción por cada semana que creaste. En la etiqueta de opción escribe exactamente como la pusiste en la sección de escuelas (ej: <em>9 al 13 de abril</em>).</li>
                        <li><strong>Ajustar precio:</strong> Deja en "El precio no cambia".</li>
                        <li><strong>Cantidad del precio:</strong> Puedes poner cualquier valor, por ejemplo 100 (no se usará).</li>
                    </ul>
                </li>
            </ol>

            <h3 style="margin-top: 30px;">4.2 Crear Campos de Horarios (Botones de Radio)</h3>
            <p style="line-height: 1.8;">Para cada semana que creaste, deberás crear un campo de botones de radio:</p>
            <ol style="line-height: 1.8;">
                <li>Haz clic nuevamente en el botón azul <strong>+ input field</strong>.</li>
                <li>Selecciona <strong>Botones de radio</strong>.</li>
                <li>Rellena los siguientes campos:
                    <ul style="margin-top: 8px;">
                        <li><strong>Etiqueta:</strong> <code>Horario del [nombre_semana]</code> — <strong style="color: #d63638;">Es indispensable comenzar con la palabra "Horario del"</strong> (ej: <em>Horario del 9 al 13 de abril</em>).</li>
                        <li><strong>Opciones:</strong> Añade dos opciones:
                            <ul style="margin-top: 8px; margin-left: 20px;">
                                <li><strong>Primera opción (Horario Mañana):</strong>
                                    <ul>
                                        <li>Etiqueta de opción: El horario de mañana que asignaste en la semana (ej: <em>8:00h a 14:00h</em>)</li>
                                        <li>Ajustar precio: <strong>Tarifa plana</strong></li>
                                        <li>Cantidad del precio: Introduce el precio para este horario</li>
                                    </ul>
                                </li>
                                <li><strong>Segunda opción (Horario Completo):</strong>
                                    <ul>
                                        <li>Etiqueta de opción: El horario completo que asignaste en la semana (ej: <em>8:00h a 17:00h</em>)</li>
                                        <li>Ajustar precio: <strong>Tarifa plana</strong></li>
                                        <li>Cantidad del precio: Introduce el precio para este horario</li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li>Desplázate más abajo y busca <strong>Conditional logic</strong>.</li>
                <li>Haz clic en <strong>or add new rule group</strong>.</li>
                <li>En el primer select, elige <strong>Semanas</strong>.</li>
                <li>En el segundo select, elige <strong>es igual a</strong>.</li>
                <li>En el tercer select, elige la semana correspondiente (ej: <em>9 al 13 de abril</em>).</li>
                <li>Haz clic fuera del modal para cerrar.</li>
            </ol>

            <h3 style="margin-top: 30px;">4.3 Repetir para Todas las Semanas</h3>
            <p style="line-height: 1.8;">Repite los pasos de la sección 4.2 para cada semana que tengas, creando un campo de botones de radio con su lógica condicional correspondiente.</p>

            <h3 style="margin-top: 30px;">4.4 Guardar Cambios</h3>
            <p style="line-height: 1.8;">Una vez hayas añadido todos los campos de semanas y horarios:</p>
            <ol style="line-height: 1.8;">
                <li>Desplázate al principio del formulario del producto.</li>
                <li>Haz clic en <strong>Actualizar</strong> para guardar todos los cambios.</li>
                <li><strong>¡Tu tienda está configurada y lista para usar!</strong></li>
            </ol>
        </div>

        <!-- NOTAS IMPORTANTES -->
        <h2 style="margin-top: 40px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">⚠️ Notas Importantes</h2>
        
        <div class="card" style="background-color: #fff8e5; padding: 20px; margin: 15px 0; border-left: 4px solid #ffb900;">
            <ul style="line-height: 1.9;">
                <li><strong>Nombres consistentes:</strong> Asegúrate de usar exactamente los mismos nombres de semana en todos los pasos (Escuelas → Input Fields de producto).</li>
                <li><strong>Formato de horarios:</strong> Mantén un formato consistente (ej: <code>HH:00h a HH:00h</code>).</li>
                <li><strong>Etiqueta de horarios:</strong> Los campos de radio <strong>deben comenzar con "Horario del"</strong> para que funcione correctamente con la lógica condicional y las validaciones del plugin.</li>
                <li><strong>Lógica condicional:</strong> Sin la lógica condicional, los campos de horario se verán en todos los casos. La regla "Semanas = [semana específica]" asegura que cada horario se muestre solo cuando se selecciona su semana correspondiente.</li>
            </ul>
        </div>

        <!-- DIAGRAMA VISUAL -->
        <h2 style="margin-top: 40px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Resumen del Flujo</h2>
        
        <div class="card" style="background-color: #f0f6ff; padding: 20px; margin: 15px 0;">
            <pre style="background: white; padding: 15px; border-radius: 4px; border: 1px solid #0073aa; overflow-x: auto; line-height: 1.8;">
<strong>Escuela</strong> → <strong>Semanas</strong> (asociadas a la escuela)
           ↓
      <strong>Producto WooCommerce</strong>
           ↓
      <strong>Vincular producto a escuela</strong> (ID)
           ↓
      <strong>Configurar campos dinámicos en producto:</strong>
      - Casillas "Semanas"
      - Para cada semana: Botones de radio "Horario del [semana]"
      - Cada botón con lógica condicional</pre>
        </div>

        <!-- TROUBLESHOOTING -->
        <h2 style="margin-top: 40px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">🔧 Solución de Problemas</h2>
        
        <div class="card">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="border: 1px solid #ddd; padding: 12px; text-align: left; font-weight: bold;">Problema</th>
                        <th style="border: 1px solid #ddd; padding: 12px; text-align: left; font-weight: bold;">Solución</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 12px;">Las semanas no aparecen en el filtro</td>
                        <td style="border: 1px solid #ddd; padding: 12px;">Verifica que la escuela esté creada y vinculada a las semanas</td>
                    </tr>
                    <tr style="background-color: #f9f9f9;">
                        <td style="border: 1px solid #ddd; padding: 12px;">Los horarios no se muestran en el carrito</td>
                        <td style="border: 1px solid #ddd; padding: 12px;">Revisa que la lógica condicional esté correctamente configurada</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 12px;">El producto no está vinculado a la escuela</td>
                        <td style="border: 1px solid #ddd; padding: 12px;">Confirma que el ID del producto en la escuela es correcto</td>
                    </tr>
                    <tr style="background-color: #f9f9f9;">
                        <td style="border: 1px solid #ddd; padding: 12px;">Los precios no se aplican</td>
                        <td style="border: 1px solid #ddd; padding: 12px;">Asegúrate de seleccionar "Tarifa plana" en cada opción de horario</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 40px; padding: 20px; background-color: #ecf3ff; border-radius: 4px; border-left: 4px solid #0073aa;">
            <p style="margin: 0;">📖 <strong>Documentación Completa:</strong> Puedes descargar la documentación detallada en formato Markdown desde la carpeta de assets del plugin.</p>
        </div>

    </div>
    <?php
}
