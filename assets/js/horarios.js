jQuery(document).ready(function ($) {
	
	// Detección del idioma: "ca" para catalán
const langAttr = $('html').attr('lang') || '';
const isCatalan = langAttr.toLowerCase().startsWith('ca');

// Texto a mostrar cuando no hay plazas, según el idioma
const TEXT_SIN_PLAZAS = isCatalan ? ' (Sense places)' : ' (Sin plazas)';
  // ==================================================
  // 1) FUNCIÓN PARA UNIFICAR MESES DE CATALÁN A CASTELLANO
  // ==================================================
  function unificarMesesACastellano(texto) {
    // Diccionario para pasar de catalán a castellano
    const diccionario = {
      'gener': 'enero',
      'febrer': 'febrero',
      'març': 'marzo',
      'abril': 'abril',
      'maig': 'mayo',
      'juny': 'junio',
      'juliol': 'julio',
      'agost': 'agosto',
      'setembre': 'septiembre',
      'octubre': 'octubre',
      'novembre': 'noviembre',
      'desembre': 'diciembre'
    };

    // Reemplazar cada palabra en catalán por su equivalente en castellano
    Object.keys(diccionario).forEach(cat => {
      // \b asegura que la palabra coincida completa (ej. "juny" no pilla "junyors")
      const regExp = new RegExp('\\b' + cat + '\\b', 'gi');
      texto = texto.replace(regExp, diccionario[cat]);
    });

    return texto;
  }

  // ==================================================
  // 2) VARIABLES GLOBALES
  // ==================================================
  let becasSeleccionadas = 0;
  let maxBecas = 2;
  let disponibilidadSemanas = {}; // Almacenará los datos de disponibilidad por semana
  let actualizacionEnProceso = false; // Bandera para evitar actualizaciones simultáneas

  // ==================================================
  // 3) CARGAR DISPONIBILIDAD DESDE EL SERVIDOR
  // ==================================================
  function cargarDisponibilidad() {
    if (actualizacionEnProceso) return;
    actualizacionEnProceso = true;

  
    $.ajax({
      url: misDatosAjax.ajaxUrl,
      method: 'POST',
      data: { action: 'get_horarios_stock' },
      success: function (response) {
        if (response && response.success && response.disponibilidad) {
        
          // Guardar datos de disponibilidad
          procesarDatosDisponibilidad(response.disponibilidad);
        
          // Actualizar UI de semanas y horarios
          actualizarDisponibilidadSemanas();
          updateHorariosStock();
        } else {
          console.error('Formato de respuesta incorrecto:', response);
        }
        actualizacionEnProceso = false;
      },
      error: function (err) {
        console.error('Error al obtener disponibilidad:', err);
        actualizacionEnProceso = false;
      }
    });
  }

  // ==================================================
  // 4) PROCESAR DATOS DE DISPONIBILIDAD
  // ==================================================
  function procesarDatosDisponibilidad(datos) {
    disponibilidadSemanas = {};
  
    datos.forEach(function(semana) {
      const semanaId = semana.id;
      const nombreSemana = semana.nombre;  // Esto llega en castellano desde la BBDD
      
      // Extraer fechas de la semana (ej: "Semana 1 (25 al 27 de Junio)" -> "25 al 27 de Junio")
      let fechasSemana = '';
      if (nombreSemana.includes('(') && nombreSemana.includes(')')) {
        fechasSemana = nombreSemana.split('(')[1].split(')')[0].trim();
      } else {
        // Intentar extraer fechas de otra forma si el formato es diferente
        // Ampliado para incluir ç (por si "març", etc.) 
        const match = nombreSemana.match(/\d+\s+(?:al|a)\s+\d+\s+de\s+[A-Za-zñÑáéíóúÁÉÍÓÚçÇ]+/);
        if (match) {
          fechasSemana = match[0];
        }
      }
    
      // Si no se pudo extraer la fecha, usar el nombre completo
      if (!fechasSemana) {
        fechasSemana = nombreSemana;
      }
    
      // Normalizar el texto de la fecha para facilitar comparaciones
      const fechaNormalizada = normalizarTexto(fechasSemana);
    
      // Crear objeto de disponibilidad para esta semana
      disponibilidadSemanas[fechaNormalizada] = {
        id: semanaId,
        nombre: nombreSemana,       // original en castellano
        fechaOriginal: fechasSemana, // extraída
        plazasTotales: semana.plazas_totales,
        horarios: semana.horarios || {},
        estaCompleta: true // Asumimos que está completa hasta que encontremos un horario con plazas
      };
    
      // Verificar si hay algún horario con plazas disponibles
      if (semana.horarios) {
        Object.keys(semana.horarios).forEach(function(tipoHorario) {
          const horario = semana.horarios[tipoHorario];
          if (parseInt(horario.plazas) > 0) {
            disponibilidadSemanas[fechaNormalizada].estaCompleta = false;
          }
        });
      }
    });
  }

  // ==================================================
  // 5) NORMALIZAR TEXTO (ELIMINAR ACENTOS Y ESPACIOS EXTRA)
  // ==================================================
  function normalizarTexto(texto) {
    return texto
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\s+/g, " ")
      .trim();
  }

  // ==================================================
  // 6) ACTUALIZAR DISPONIBILIDAD DE SEMANAS
  // ==================================================
  function actualizarDisponibilidadSemanas() {
  
    $('.wapf-field-container.semanas .wapf-checkable').each(function() {
      const $container = $(this);
      const $checkbox = $container.find('input[type="checkbox"]');
      const $label = $container.find('span.wapf-label-text');
      
      // Tomamos el texto que ve el usuario (podría estar en catalán)
      let textoSemanaUI = $label.text().trim();
      // Unificamos meses a castellano para poder comparar con la BBDD
      textoSemanaUI = unificarMesesACastellano(textoSemanaUI);
      // Luego normalizamos
      const textoNormalizado = normalizarTexto(textoSemanaUI);
    
      // Buscar esta semana en nuestros datos de disponibilidad
      let semanaEncontrada = null;
      Object.keys(disponibilidadSemanas).forEach(function(fechaSemana) {
        const fechaNormalizada = normalizarTexto(fechaSemana);
        if (
          textoNormalizado.includes(fechaNormalizada) || 
          fechaNormalizada.includes(textoNormalizado)
        ) {
          semanaEncontrada = disponibilidadSemanas[fechaSemana];
        }
      });
    
      if (semanaEncontrada) {
        // Crear o encontrar el elemento para mostrar disponibilidad
        let $disponibilidadInfo = $container.find('.disponibilidad-semana');
        if ($disponibilidadInfo.length === 0) {
          $disponibilidadInfo = $('<span class="disponibilidad-semana"></span>');
          $label.after($disponibilidadInfo);
        }
      
        if (semanaEncontrada.estaCompleta) {
          // Semana sin plazas disponibles
         $disponibilidadInfo.text(TEXT_SIN_PLAZAS);
          $disponibilidadInfo.css({
            'color': '#dc3545',
            'font-weight': 'bold',
            'margin-left': '5px'
          });
        
          $checkbox.prop('disabled', true);
          $checkbox.attr('data-sin-plazas', 'true');
          $container.addClass('semana-completa');
        } else {
          // Semana con plazas disponibles
          $disponibilidadInfo.text('');
          $checkbox.prop('disabled', false);
          $checkbox.attr('data-sin-plazas', 'false');
          $container.removeClass('semana-completa');
        }
      } else {
        console.log("No se encontraron datos para esta semana");
      }
    });
  }

  // ==================================================
  // 7) ACTUALIZAR STOCK DE HORARIOS
  // ==================================================
  function updateHorariosStock() {
    if (actualizacionEnProceso) return;
    actualizacionEnProceso = true;

    // Identificar qué semanas están seleccionadas
    let semanasSeleccionadas = [];
  
    $('.wapf-field-container.semanas .wapf-checkable input[type="checkbox"]:checked').each(function() {
      let textoSemana = $(this).closest('.wapf-checkable').find('span.wapf-label-text').text().trim();
      // Unificar a castellano antes de normalizar
      textoSemana = unificarMesesACastellano(textoSemana);
      const textoNormalizado = normalizarTexto(textoSemana);
 
      // Buscar la semana en nuestros datos de disponibilidad
      Object.keys(disponibilidadSemanas).forEach(function(fechaSemana) {
        const fechaNormalizada = normalizarTexto(fechaSemana);
        if (
          textoNormalizado.includes(fechaNormalizada) || 
          fechaNormalizada.includes(textoNormalizado)
        ) {
          semanasSeleccionadas.push({
            texto: textoSemana,
            textoNormalizado: textoNormalizado,
            fechaSemana: fechaSemana,
            fechaNormalizada: fechaNormalizada,
            datos: disponibilidadSemanas[fechaSemana]
          });
        }
      });
    });

    // Recopilamos todos los horarios visibles
    $('.wapf-field-container.wapf-field-radio.horario:visible').each(function() {
      const $horarioContainer = $(this);
      let horarioTitle = $horarioContainer.find('.wapf-field-label').text().trim();
      // Unificar a castellano
      horarioTitle = unificarMesesACastellano(horarioTitle);
      const horarioTitleNormalizado = normalizarTexto(horarioTitle);
    
      // Extraer la fecha del título del horario (ej: "Horario del 25 al 27 de Junio" -> "25 al 27 de Junio")
      let fechaHorario = '';
      const match = horarioTitle.match(/Horario del (.+)/i);
      if (match && match[1]) {
        fechaHorario = match[1].trim();
      }
      // Unificar a castellano y normalizar
      fechaHorario = unificarMesesACastellano(fechaHorario);
      const fechaHorarioNormalizada = normalizarTexto(fechaHorario);
    
      // Buscar la semana correspondiente a este horario
      let semanaCorrespondiente = null;
      let semanaIndex = -1;
    
      // Coincidencia exacta
      for (let i = 0; i < semanasSeleccionadas.length; i++) {
        if (fechaHorarioNormalizada === semanasSeleccionadas[i].fechaNormalizada) {
          semanaCorrespondiente = semanasSeleccionadas[i].datos;
          semanaIndex = i;
          break;
        }
      }
    
      // Coincidencias parciales
      if (!semanaCorrespondiente) {
        for (let i = 0; i < semanasSeleccionadas.length; i++) {
          if (
            fechaHorarioNormalizada.includes(semanasSeleccionadas[i].fechaNormalizada) ||
            semanasSeleccionadas[i].fechaNormalizada.includes(fechaHorarioNormalizada) ||
            fechaHorarioNormalizada.includes(semanasSeleccionadas[i].textoNormalizado) ||
            semanasSeleccionadas[i].textoNormalizado.includes(fechaHorarioNormalizada)
          ) {
            semanaCorrespondiente = semanasSeleccionadas[i].datos;
            semanaIndex = i;
            break;
          }
        }
      }
    
      // Caso especial: 31 de junio al 4 de julio (incluyendo catalán: "juny" y "juliol")
     /* if (
        fechaHorarioNormalizada.includes("31") && 
        (fechaHorarioNormalizada.includes("junio") || fechaHorarioNormalizada.includes("juny")) &&
        fechaHorarioNormalizada.includes("4") && 
        (fechaHorarioNormalizada.includes("julio") || fechaHorarioNormalizada.includes("juliol"))
      ) {
        for (let i = 0; i < semanasSeleccionadas.length; i++) {
          if (
            semanasSeleccionadas[i].textoNormalizado.includes("31") &&
            (
              semanasSeleccionadas[i].textoNormalizado.includes("junio") || 
              semanasSeleccionadas[i].textoNormalizado.includes("juny")
            ) &&
            semanasSeleccionadas[i].textoNormalizado.includes("4") && 
            (
              semanasSeleccionadas[i].textoNormalizado.includes("julio") || 
              semanasSeleccionadas[i].textoNormalizado.includes("juliol")
            )
          ) {
            semanaCorrespondiente = semanasSeleccionadas[i].datos;
            semanaIndex = i;
            break;
          }
        }
      }*/
    
      // Coincidencia más general si no encontramos nada
      if (!semanaCorrespondiente && semanasSeleccionadas.length > 0) {
        // Si solo hay una semana seleccionada, la usamos
        semanaCorrespondiente = semanasSeleccionadas[0].datos;
        semanaIndex = 0;
        console.log("Usando la única semana seleccionada como correspondiente");
      }
    
      if (!semanaCorrespondiente) {
        console.log("No se encontró semana correspondiente para:", fechaHorario);
        return;
      }
    
      // Procesar cada opción de horario dentro de este grupo
      $horarioContainer.find('.wapf-checkable').each(function() {
        const $container = $(this);
        const $horarioSpan = $(this).find('span.wapf-label-text');
        const $input = $container.find('input');
      
        // Extraer solo el texto del horario (sin pricing-hint)
        const horarioTexto = $horarioSpan.contents().filter(function() {
          return this.nodeType === 3; // Nodo de texto
        }).text().trim();
      
      
        // Determinar el tipo de horario
        let tipoHorario = '';
        if (horarioTexto.includes('14:30h')) {
          tipoHorario = 'mañana';
        } else if (horarioTexto.includes('17:00h')) {
          tipoHorario = 'completo';
        }
        if (!tipoHorario) {
          console.log("No se pudo determinar el tipo de horario");
          return;
        }
      
      
        // Verificar si tenemos datos de este horario
        if (semanaCorrespondiente && semanaCorrespondiente.horarios && semanaCorrespondiente.horarios[tipoHorario]) {
          const horarioData = semanaCorrespondiente.horarios[tipoHorario];
          const plazas = parseInt(horarioData.plazas);
        
         
        
          // Crear o encontrar el elemento para mostrar disponibilidad
          let $pricingHint = $container.find('.wapf-pricing-hint');
          if ($pricingHint.length === 0) {
            $pricingHint = $('<span class="wapf-pricing-hint"></span>');
            $horarioSpan.append($pricingHint);
          }
        
          // Caso especial 31 de junio -> 4 de julio (mañana)
          if (
            fechaHorarioNormalizada.includes("31") && 
            (fechaHorarioNormalizada.includes("junio") || fechaHorarioNormalizada.includes("juny")) &&
            fechaHorarioNormalizada.includes("4") && 
            (fechaHorarioNormalizada.includes("julio") || fechaHorarioNormalizada.includes("juliol")) &&
            tipoHorario === 'mañana'
          ) {
           
            $pricingHint.text('');
            $input.prop('disabled', false);
            $input.attr('data-sin-stock', 'false');
            $container.removeClass('opcion-sin-stock');
          } else {
            // Actualizar la información de disponibilidad normalmente
            if (plazas > 0) {
              $pricingHint.text('');
              $input.prop('disabled', false);
              $input.attr('data-sin-stock', 'false');
              $container.removeClass('opcion-sin-stock');
              
            } else {
              $pricingHint.text(TEXT_SIN_PLAZAS);
              $pricingHint.css({
                'color': '#dc3545',
                'font-weight': 'bold'
              });
              $input.prop('disabled', true);
              $input.attr('data-sin-stock', 'true');
              $container.addClass('opcion-sin-stock');

            }
          }
        } else {
          console.log("No se encontraron datos para este horario en la semana correspondiente");
        }
      });
    });
  
    // Forzar la deshabilitación después de actualizar
    setTimeout(function() {
      forzarDeshabilitacionElementosSinStock();
      actualizacionEnProceso = false;
    }, 100);
  }

  // ==================================================
  // 8) FORZAR DESHABILITACIÓN DE ELEMENTOS SIN STOCK
  // ==================================================
  function forzarDeshabilitacionElementosSinStock() {
  
    // Forzar deshabilitación SOLO de horarios sin plazas
    $('.wapf-field-container.horario .wapf-checkable input[data-sin-stock="true"]').each(function() {
      $(this).prop('disabled', true);
      $(this).closest('.wapf-checkable').addClass('opcion-sin-stock');
    
      // Asegurarse de que el texto "(Sin plazas)" esté presente
      const $container = $(this).closest('.wapf-checkable');
      const $horarioSpan = $container.find('span.wapf-label-text');
      let $pricingHint = $container.find('.wapf-pricing-hint');
    
      if ($pricingHint.length === 0) {
        $pricingHint = $('<span class="wapf-pricing-hint"></span>');
        $horarioSpan.append($pricingHint);
      }
    
     if (!$pricingHint.text().includes(isCatalan ? 'Sense places' : 'Sin plazas')) {
  $pricingHint.text(TEXT_SIN_PLAZAS);
        $pricingHint.css({
          'color': '#dc3545',
          'font-weight': 'bold'
        });
      }
    });
  
    // Asegurarse de que los elementos CON plazas estén habilitados
    $('.wapf-field-container.horario .wapf-checkable input[data-sin-stock="false"]').each(function() {
      $(this).prop('disabled', false);
      $(this).closest('.wapf-checkable').removeClass('opcion-sin-stock');
    });
  
    // Caso especial para la semana del 31 de junio al 4 de julio
    $('.wapf-field-container.horario').each(function() {
      const horarioTitle = $(this).find('.wapf-field-label').text().trim();
      // Unificar a castellano
      const horarioTitleCast = unificarMesesACastellano(horarioTitle);
      if (
        horarioTitleCast.includes("31") && 
        (horarioTitleCast.includes("junio") || horarioTitleCast.includes("juny")) &&
        horarioTitleCast.includes("4") && 
        (horarioTitleCast.includes("julio") || horarioTitleCast.includes("juliol"))
      ) {
        // Habilitar específicamente el horario de mañana
        $(this).find('.wapf-checkable').each(function() {
          const horarioTexto = $(this).find('span.wapf-label-text').text().trim();
          if (horarioTexto.includes('14:30h')) {
            const $input = $(this).find('input');
            let $pricingHint = $(this).find('.wapf-pricing-hint');
          
            $pricingHint.text('');
            $input.prop('disabled', false);
            $input.attr('data-sin-stock', 'false');
            $(this).removeClass('opcion-sin-stock');
          }
        });
      }
    });
  }

  // ==================================================
  // 9) LIMPIAR MENSAJES DE SIN PLAZAS
  // ==================================================
  function limpiarMensajesSinPlazas() {
  
    // Limpiar todos los mensajes de horarios
    $('.wapf-field-container.wapf-field-radio.horario .wapf-pricing-hint').text('');
    $('.wapf-field-container.wapf-field-radio.horario input').prop('disabled', false);
    $('.wapf-field-container.wapf-field-radio.horario .wapf-checkable').removeClass('opcion-sin-stock');
    $('.wapf-field-container.wapf-field-radio.horario input').attr('data-sin-stock', 'false');
  }

  // ==================================================
  // 10) EVENTOS Y OBSERVADORES
  // ==================================================
  // Interceptar clics en opciones sin stock para evitar su selección
  $(document).on('click', '.opcion-sin-stock label', function(e) {
    e.preventDefault();
    e.stopPropagation();
    return false;
  });

  // Evento change para semanas
  $('.semanas').on('change', 'input[type="checkbox"]', function() {
    // Limpiar mensajes primero
    limpiarMensajesSinPlazas();
  
    // Esperar a que WAPF actualice el DOM
    setTimeout(updateHorariosStock, 300);
  });

  // Añadir estilos CSS para semanas completas y opciones sin stock
  $('<style>')
    .prop('type', 'text/css')
    .html(`
      .semana-completa {
        opacity: 0.7;
      }
      .semana-completa label {
        cursor: not-allowed;
      }
      .disponibilidad-semana {
        display: inline-block;
        margin-left: 5px;
      }
      .opcion-sin-stock {
        opacity: 0.7;
      }
      .opcion-sin-stock label {
        cursor: not-allowed;
      }
      .wapf-pricing-hint {
        display: inline-block;
        margin-left: 5px;
      }
    `)
    .appendTo('head');

  // Observador de mutaciones para mantener deshabilitados los elementos sin stock
  const observer = new MutationObserver(function(mutations) {
    clearTimeout(window.observerTimeout);
    window.observerTimeout = setTimeout(forzarDeshabilitacionElementosSinStock, 100);
  });

  // Configurar el observador para vigilar cambios en el contenedor de campos WAPF
  const targetNode = document.querySelector('.wapf-field-container');
  if (targetNode) {
    observer.observe(targetNode.parentNode, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'style', 'disabled']
    });
  }

  // ==================================================
  // 11) INICIALIZACIONES
  // ==================================================
  // Cargar disponibilidad al iniciar la página
  cargarDisponibilidad();

  // Actualizar disponibilidad cada 5 minutos
  setInterval(cargarDisponibilidad, 5 * 60 * 1000);

  // Ejecutar actualización adicional después de que la página esté completamente cargada
  $(window).on('load', function() {
    setTimeout(updateHorariosStock, 1000);
  });
});
