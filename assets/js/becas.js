/**
 * Variables globales
 */
let becasSeleccionadas = 0;
let maxBecas = 2;

/**
 * Inicialización cuando el documento está listo
 */
jQuery(document).ready(function($) {
  // Verificar estado inicial
  contarBecasSeleccionadas();
  actualizarEstadoBecas();
  
  // Configurar event listeners
  configurarEventListeners();

	  
const htmlLang = document.documentElement.lang || 'ca';
const isCatalan = (htmlLang === 'ca');

  /**
 * Configura todos los event listeners necesarios
 */
function configurarEventListeners() {
    // Cuando cambia un checkbox de beca
    $(document).on('change', '.wapf-field-container.wapf-field-checkboxes.becas input[type="checkbox"]', function() {
      // Usar setTimeout para asegurar que el cambio se ha aplicado
      setTimeout(function() {
        contarBecasSeleccionadas();
        actualizarEstadoBecas();
      }, 100);
    });
    
    // Cuando cambia el horario
    $(document).on('change', 'input[name="horario"]', function() {
      setTimeout(function() {
        contarBecasSeleccionadas();
        actualizarEstadoBecas();
      }, 100);
    });
    
    // Cuando cambian las semanas seleccionadas
    $(document).on('change', '.wapf-field-container input[type="checkbox"][name^="semanas"]', function() {
      setTimeout(function() {
        contarBecasSeleccionadas();
        actualizarEstadoBecas();
      }, 100);
    });
  }
  
  /**
   * Cuenta las becas actualmente seleccionadas
   */
  function contarBecasSeleccionadas() {
    becasSeleccionadas = $('.wapf-field-container.wapf-field-checkboxes.becas input[type="checkbox"]:checked').length;
  }

/**
 * Actualiza el estado de las becas basado en el número de becas seleccionadas
 */
function actualizarEstadoBecas() {
  // Contar becas seleccionadas
  becasSeleccionadas = $('.wapf-field-container.wapf-field-checkboxes.becas input[type="checkbox"]:checked').length;
  
  // Procesar todos los contenedores de becas
  $('.wapf-field-container.wapf-field-checkboxes.becas').each(function() {
    let $container = $(this);
    let $checkbox = $container.find('input[type="checkbox"]');
    let $description = $container.find('.wapf-field-description');
    let $checkable = $checkbox.closest('.wapf-checkable');
    
    // Guardar el texto original si aún no se ha guardado
    if (!$description.data('texto-original')) {
      $description.data('texto-original', $description.html());
    }
    
    if (becasSeleccionadas >= maxBecas && !$checkbox.prop('checked')) {
      // Si ya hay 2 o más becas seleccionadas y este checkbox no está marcado
      
      // Modificar la descripción para mostrar advertencia
     // Modificar la descripción para mostrar advertencia
      isCatalan ? $description.html('<strong style="color: #e74c3c;">⚠️ LÍMIT ASSOLIT: Ja has seleccionat el màxim de 2 beques permeses per nen/a</strong>') : $description.html('<strong style="color: #e74c3c;">⚠️ LÍMITE ALCANZADO: Ya has seleccionado el máximo de 2 becas permitidas por niño/a</strong>');  
      
      
      // Aplicar múltiples métodos para asegurar que se deshabilita
      $checkbox.prop('disabled', true);
      $checkbox.attr('disabled', 'disabled');
      $checkbox.attr('readonly', 'readonly');
      
      // Añadir clase para estilo visual
      $checkable.addClass('beca-limite-alcanzado');
      
      // Aplicar estilos inline para bloquear interacción
      $checkbox.css({
        'pointer-events': 'none',
        'opacity': '0.4',
        'cursor': 'not-allowed',
        'user-select': 'none',
        'webkit-user-select': 'none',
        'moz-user-select': 'none',
        '-ms-user-select': 'none',
        '-o-user-select': 'none',
        'text-decoration': 'line-through'
      });
      
      // Añadir un div transparente encima para bloquear clics
      if ($checkable.find('.beca-bloqueador').length === 0) {
        $checkable.css('position', 'relative');
        $checkable.append('<div class="beca-bloqueador" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10;"></div>');
      }
    } else {
      // Si hay menos de 2 becas o este checkbox ya está marcado
      
      // Restaurar texto original de la descripción
      $description.html($description.data('texto-original'));
      
      // Habilitar el checkbox
      $checkbox.prop('disabled', false);
      $checkbox.removeAttr('disabled');
      $checkbox.removeAttr('readonly');
      $checkable.removeClass('beca-limite-alcanzado');
      $checkbox.css({
        'pointer-events': 'auto',
        'opacity': '1'
      });
      $checkable.find('.beca-bloqueador').remove();
    }
  });
}




  function bloquear_seleccion_becas() {
    $('.wapf-field-container.wapf-field-checkboxes.becas:visible input[type="checkbox"]').each(function() {
      let $checkbox = $(this);
      if (!$checkbox.prop('checked')) {
        // Usar attr en lugar de prop para asegurar que se aplica el atributo HTML
        $checkbox.attr('disabled', 'disabled');
        $checkbox.closest('.wapf-checkable').addClass('beca-limite-alcanzado');
      }
    });
  }


});

