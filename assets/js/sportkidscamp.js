jQuery(document).ready(function ($) {
    // Función AJAX para limpiar la sesión de descuentos
    function limpiarSesionDescuentos() {
        jQuery.post(wc_checkout_params.ajax_url, { action: 'limpiar_sesion_descuentos' }, function (response) {
            console.log('Sesión de descuentos limpiada:', response);
            $(document.body).trigger('update_checkout');
        });
    }

        function limpiarSesionDescuentosDni() {
        jQuery.post(wc_checkout_params.ajax_url, { action: 'limpiar_sesion_descuentos_dni' }, function (response) {
            console.log('DNI eliminado:', response);
            $(document.body).trigger('update_checkout');
        });
    }

       function limpiarSesionDescuentosNombre() {
        jQuery.post(wc_checkout_params.ajax_url, { action: 'limpiar_sesion_descuentos_dni' }, function (response) {
            console.log('Nombre eliminado:', response);
            $(document.body).trigger('update_checkout');
        });
    }

    // Función para obtener los datos de la sesión
    function obtenerSesionWooCommerce() {
        jQuery.post(wc_checkout_params.ajax_url, { action: 'obtener_sesion_woocommerce' }, function (response) {
            console.log('Datos de la sesión:', response.data);
        });
    }

	limpiarSesionDescuentos();
	  
const htmlLang = document.documentElement.lang || 'ca';
const isCatalan = (htmlLang === 'ca');


// 🔍 Validar nombre del hermano
    $('#nombre_reserva_anterior').on('change', function () {
    limpiarSesionDescuentosNombre();
    $('.woocommerce-error-custom').remove();
    $(document.body).trigger('update_checkout');
    $(document.body).trigger('wc_fragment_refresh'); // Para el carrito
    let nombreHermano = $(this).val();
    let tieneHermano = $('input[name="tiene_reserva_previa"]:checked').val();
  //  Si el campo está vacío, no hacer nada
    if (nombreHermano === '') {
        return;
    }


        $.post(wc_checkout_params.ajax_url, {
            action: 'verificar_hermano',
            nombre_hermano: nombreHermano,
            tiene_hermano: tieneHermano
        }, function (response) {
           if (response.success) {
                    $(document.body).trigger('update_checkout'); // Funciona en checkout
                    $(document.body).trigger('wc_fragment_refresh'); // Para el carrito
                    isCatalan ? $('#nombre_reserva_anterior_field').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">El descompte per germà s'aplicarà quan validem el dni del tutor i només a les setmanes sense beca, l'acollida no porta descompte</div>`) : $('#nombre_reserva_anterior_field').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">El descuento por hermano se aplicara cuando validemos el dni del tutor y solamente a las semanas sin beca, la acogida no lleva descuento</div>`);
                } else {
                    limpiarSesionDescuentosNombre();
    if (response.data && response.data.mensaje === "No se encontró alumno.") {
        isCatalan ? $('#nombre_reserva_anterior_field').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Aquest alumne no es troba a la nostra base de dades amb reserva activa.</div>`) : $('#nombre_reserva_anterior_field').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Este alumno no se encuentra en nuestra base de datos con reserva activa.</div>`);
    }
                    $(document.body).trigger('update_checkout'); // Funciona en checkout
                    $(document.body).trigger('wc_fragment_refresh'); // Para el carrito
                    //
                }
        });
});


// 🔍 Validar DNI del tutor
$('#dni_tutor').on('change', function () {
    limpiarSesionDescuentosDni();
    $('.woocommerce-error-custom').remove();
 $(document.body).trigger('update_checkout');
    $(document.body).trigger('wc_fragment_refresh'); // Para el carrito
    let nombreHermano = $('#nombre_reserva_anterior').val();
    let dni = $(this).val();
     const tieneHermano = $('input[name="tiene_reserva_previa"]:checked').val();

    // Validaciones antes de enviar
    if (nombreHermano === '' || tieneHermano !== 'si') {
        return;
    }

        $.post(wc_checkout_params.ajax_url, {
            action: 'verificar_dni_tutor',
            nombre_hermano: nombreHermano,
            dni_tutor: dni
        }, function (response) {
            if (response.success) {
                    $(document.body).trigger('update_checkout'); // Funciona en checkout
                    $(document.body).trigger('wc_fragment_refresh'); // Para el carrito
                    console.log('✅ Dni capturado y valido');
                } else {
                    console.log(response);
                    limpiarSesionDescuentosDni();
                    $(document.body).trigger('update_checkout'); // Funciona en checkout
                    $(document.body).trigger('wc_fragment_refresh'); // Para el carrito
                    isCatalan ? $('#dni_tutor_field').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">No hi ha descompte germà/na per no coincidència amb paràmatres: DNI i nom-cognom germà/a</div>`) : $('#dni_tutor_field').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">no hay descuento hermano/a por no coincidencia con parámetros: DNI y nombre-apellido hermano/a</div>`);
                }
        });
});





    // Función para mostrar/ocultar campos según el valor de un radio
    function toggleField(radioName, containerId) {
        let targetContainer = $("#" + containerId); // Capturamos el contenedor

        function updateVisibility() {
            let selectedValue = $("input[name='" + radioName + "']:checked").val();
            if (selectedValue === "si") {
                targetContainer.removeClass("d-none");
                // Marcar como requerido si está visible
                targetContainer.find('input, textarea').attr('required', true);
            } else {
                targetContainer.addClass("d-none").find("input, textarea").val(""); // Limpia el campo si se oculta
                // Quitar requerido si está oculto
                targetContainer.find('input, textarea').attr('required', false);
            }
        }

        // Ejecutar la función cuando se cambia el radio
        $("input[name='" + radioName + "']").change(updateVisibility);

        // Ejecutar al cargar la página para establecer el estado inicial
        updateVisibility();
    }

    // Función para mostrar/ocultar campos según el curso escolar
    function toggleFields() {
        let selectedValue = $("#curso_escolar").val();

        // Mostrar "siesta" solo si es I3
        if (selectedValue === "i3") {
            $("#necesita_siesta_field").removeClass("d-none");
            // Marcar como requerido
            $("input[name='necesita_siesta']").attr('required', true);
        } else {
            $("#necesita_siesta_field").addClass("d-none").find("input").prop('checked', false);
            // Quitar requerido
            $("input[name='necesita_siesta']").attr('required', false);
        }

        // Mostrar "flotador" hasta 1_primaria incluido
        if (["i3", "i4", "i5", "1_primaria"].includes(selectedValue)) {
            $("#necesita_flotador_field").removeClass("d-none");
            // Marcar como requerido
            $("input[name='necesita_flotador']").attr('required', true);
        } else {
            $("#necesita_flotador_field").addClass("d-none").find("input").prop('checked', false);
            // Quitar requerido
            $("input[name='necesita_flotador']").attr('required', false);
        }
    }

    // Ejecutar cuando cambia el select
    $("#curso_escolar").change(toggleFields);

    // Ejecutar al cargar la página para establecer el estado inicial
    toggleFields();

    // Aplicar la función a los campos necesarios
    toggleField("tiene_discapacidad", "detalle_discapacidad_field");
    toggleField("tiene_alergias", "detalle_alergias_field");
    toggleField("tiene_reserva_previa", "nombre_reserva_anterior_field");
    toggleField("amigo_campamento", "nombre_amigo_campamento_field");

    // Validación de códigos de beca
    function validarCodigosBeca() {
        var codigosValidos = true;
        $('.campos-beca input[type="text"]').each(function () {
            var codigo = $(this).val();
            if (!codigo || codigo.length < 5) {
                codigosValidos = false;
            }
        });
        return codigosValidos;
    }

    // Función para validar escuela de procedencia
    function toggleOtraEscuela() {
        var seleccion = $('#escuela_procedencia').val();
        if (seleccion === 'otra') {
            $('#escuela_procedencia_otra_field').removeClass('d-none');
            $('#escuela_procedencia_otra').attr('required', true);
        } else {
            $('#escuela_procedencia_otra_field').addClass('d-none');
            $('#escuela_procedencia_otra').val('').attr('required', false);
        }
    }

    // Ejecutar al cargar la página
    toggleOtraEscuela();
    // Ejecutar al cambiar la selección
    $('#escuela_procedencia').on('change', toggleOtraEscuela);

    // Función para mostrar/ocultar campos del segundo tutor
    function toggleSegundoTutor() {
        if ($('#segundo_tutor').is(':checked')) {
            $('#segundo_tutor_campos').slideDown();
        } else {
            $('#segundo_tutor_campos').slideUp();
            // Limpiar campos del segundo tutor cuando se oculta
            $('#segundo_tutor_campos input, #segundo_tutor_campos textarea').val('');
            // Quitar requerido
            $('#nombre_tutor_2, #apellido_tutor_2, #telefono_tutor_2, #dni_tutor_2').attr('required', false);
            // Quitar borde rojo
            $('#nombre_tutor_2, #apellido_tutor_2, #telefono_tutor_2, #dni_tutor_2').css('border-left', '');
        }
    }

    // Al cargar la página, ejecutamos la función por si ya está marcado
    toggleSegundoTutor();
    // Al cambiar el checkbox, volvemos a ejecutar
    $('#segundo_tutor').on('change', toggleSegundoTutor);

    // Validación para el primer tutor
    function validarEmailTutor1() {
        var email1 = $('#email_tutor').val();
        var email2 = $('#email_tutor_confirm').val();

        // Mostramos un mensaje si ambos campos están rellenados
        if (email1 && email2) {
            $('#email_tutor_confirm_field .email-match').remove();

            if (email1 === email2) {
                // Si coinciden, podrías mostrar un mensaje "Coinciden" en verde
                isCatalan ? $('#email_tutor_confirm_field .woocommerce-input-wrapper').append('<span class="email-match" style="color:green;">✔ El correu electrònic coincideix</span>') : $('#email_tutor_confirm_field .woocommerce-input-wrapper').append('<span class="email-match" style="color:green;">✔ El correo electrónico coincide</span>');
                $('#email_tutor_confirm').css('border-color', 'green');
                return true;
            } else {
                // Si no coinciden, mensaje en rojo
                isCatalan ? $('#email_tutor_confirm_field .woocommerce-input-wrapper').append('<span class="email-match" style="color:red;">✗ El correu electrònic no coincideix</span>') : $('#email_tutor_confirm_field .woocommerce-input-wrapper').append('<span class="email-match" style="color:red;">✗ El correo electrónico no coincide</span>');
                $('#email_tutor_confirm').css('border-color', 'red');
                return false;
            }
        }
        return true; // Si alguno está vacío, no validamos aún
    }

    // Cada vez que el usuario escriba en alguno de los dos campos, borramos el mensaje anterior y validamos
    $('#email_tutor, #email_tutor_confirm').on('input', function () {
        // Borrar cualquier mensaje anterior
        $('#email_tutor_confirm_field .email-match').remove();
        validarEmailTutor1();
    });

    // Validación para el segundo tutor (si existe)
    function validarEmailTutor2() {
        var email1 = $('#email_tutor_2').val();
        var email2 = $('#email_tutor_2_confirm').val();

        if (email1 && email2) {
            $('#email_tutor_2_confirm_field .email-match').remove();
            if (email1 === email2) {
                isCatalan ? $('#email_tutor_2_confirm_field .woocommerce-input-wrapper').append('<span class="email-match" style="color:green;">✔ El correu electrònic coincideix</span>') : $('#email_tutor_2_confirm_field .woocommerce-input-wrapper').append('<span class="email-match" style="color:green;">✔ El correo electrónico coincide</span>');
                $('#email_tutor_2_confirm').css('border-color', 'green');
                return true;
            } else {
                isCatalan ? $('#email_tutor_2_confirm_field .woocommerce-input-wrapper').append('<span class="email-match" style="color:red;">✗ El correu electrònic no coincideix</span>') : $('#email_tutor_2_confirm_field .woocommerce-input-wrapper').append('<span class="email-match" style="color:red;">✗ El correo electrónico no coincide</span>');
                $('#email_tutor_2_confirm').css('border-color', 'red');
                return false;
            }
        }
        return true; // Si alguno está vacío, no validamos aún
    }

    $('#email_tutor_2, #email_tutor_2_confirm').on('input', function () {
        $('#email_tutor_2_confirm_field .email-match').remove();
        validarEmailTutor2();
    });

    // Función para validar campos en tiempo real
    function validarCampoEnTiempoReal() {
        const $campo = $(this);

        // Solo validar si el campo es requerido
        if ($campo.attr('required')) {
            if ($campo.val()) {
                // Campo válido
                $campo.removeClass('error').addClass('valid');
                $campo.closest('.form-row').find('.woocommerce-error-custom').remove();
            } else {
                // Campo inválido
                $campo.removeClass('valid').addClass('error');
            }
        }
    }

    // Aplicar validación en tiempo real a todos los campos de texto, select y textarea
    $('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select, textarea').on('blur', validarCampoEnTiempoReal);

    // FUNCIÓN: Validación específica para los datos del alumno y tutores
    function validarDatosPersonales() {
        let isValid = true;
        let firstErrorField = null;

        // 1. Validar datos del alumno (siempre requeridos)
        const camposAlumno = [
            { id: 'nombre_alumno', label: 'Nom de l\'infant' },
            { id: 'apellido_alumno', label: 'Cognoms de l\'infant' },
            { id: 'fecha_nacimiento', label: 'Data de naixement' },
            { id: 'numero_tarjeta_sanitaria', label: 'Número de targeta sanitària' },
            { id: 'compania_seguro', label: 'Companyia' }
        ];

        camposAlumno.forEach(function (campo) {
            const $campo = $(`#${campo.id}`);
            if (!$campo.val()) {
                isValid = false;
                if (!firstErrorField) firstErrorField = $campo;

                // Añadir mensaje de error si no existe ya
                if (!$campo.closest('.form-row').find('.woocommerce-error-custom').length) {
                    isCatalan ? $campo.closest('.form-row').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">El camp ${campo.label} és obligatori</div>`) : $campo.closest('.form-row').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">El campo ${campo.label} es obligatorio</div>`);
                }
            }
        });

        // 2. Validar datos del tutor principal (siempre requeridos)
        const camposTutor = [
            { id: 'nombre_tutor', label: 'Nom' },
            { id: 'apellido_tutor', label: 'Cognoms' },
            { id: 'telefono_tutor', label: 'Telèfon' },
            { id: 'dni_tutor', label: 'DNI' },
            { id: 'email_tutor', label: 'Correu Electrònic' },
            { id: 'email_tutor_confirm', label: 'Confirmació del correu' }
        ];

        camposTutor.forEach(function (campo) {
            const $campo = $(`#${campo.id}`);
            if (!$campo.val()) {
                isValid = false;
                if (!firstErrorField) firstErrorField = $campo;

                // Añadir mensaje de error si no existe ya
                if (!$campo.closest('.form-row').find('.woocommerce-error-custom').length) {
                    isCatalan ? $campo.closest('.form-row').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">El camp ${campo.label} és obligatori</div>`) : $campo.closest('.form-row').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">El campo ${campo.label} es obligatorio</div>`);
                }
            }
        });

        // 3. Validar datos del segundo tutor (solo si está marcado el checkbox)
        /*if ($('#segundo_tutor').is(':checked')) {
            // Estos campos se vuelven obligatorios cuando se marca el checkbox
            const camposTutor2 = [
                { id: 'nombre_tutor_2', label: 'Nom 2' },
                { id: 'apellido_tutor_2', label: 'Cognoms 2' },
                { id: 'telefono_tutor_2', label: 'Telèfon 2' },
                { id: 'dni_tutor_2', label: 'DNI 2' }
            ];

            camposTutor2.forEach(function (campo) {
                const $campo = $(`#${campo.id}`);
                if (!$campo.val()) {
                    isValid = false;
                    if (!firstErrorField) firstErrorField = $campo;

                    // Añadir mensaje de error si no existe ya
                    if (!$campo.closest('.form-row').find('.woocommerce-error-custom').length) {
                        isCatalan ? $campo.closest('.form-row').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">El camp ${campo.label} és obligatori quan s'inclou un segon tutor</div>`) : $campo.closest('.form-row').append(`<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">El campo ${campo.label} es obligatorio cuando se incluye un segundo tutor</div>`);
                    }
                }
            });

            // Validar coincidencia de emails del segundo tutor solo si ambos están rellenados
            if ($('#email_tutor_2').val() && $('#email_tutor_2_confirm').val()) {
                if ($('#email_tutor_2').val() !== $('#email_tutor_2_confirm').val()) {
                    isValid = false;
                    if (!firstErrorField) firstErrorField = $('#email_tutor_2_confirm');

                    if (!$('#email_tutor_2_confirm_field').find('.woocommerce-error-custom').length) {
                        isCatalan ? $('#email_tutor_2_confirm_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Els correus electrònics no coincideixen</div>') : $('#email_tutor_2_confirm_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Los correos electrónicos no coinciden</div>');
                    }
                }
            }
        }*/

        return { isValid, firstErrorField };
    }

    // FUNCIÓN: Validación completa del formulario antes de enviar
    $('form.checkout').on('checkout_place_order', function (e) {
        // Limpiar mensajes de error previos
        $('.woocommerce-error-custom').remove();
        $('.woocommerce-error-summary').remove();

        // Validar datos personales
        const resultadoValidacion = validarDatosPersonales();
        let isValid = resultadoValidacion.isValid;
        let firstErrorField = resultadoValidacion.firstErrorField;

        // 1. Validar campos básicos requeridos
        $('input[required], select[required], textarea[required]').each(function () {
            if ($(this).is(':visible') && !$(this).val()) {
                isValid = false;
                if (!firstErrorField) firstErrorField = $(this);

                // Añadir mensaje de error
                isCatalan ? $(this).after('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Aquest camp és obligatori</div>') : $(this).after('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Este campo es obligatorio</div>');
            }
        });

        // 2. Validar campos de radio requeridos
        const radioGroups = ['necesita_siesta', 'necesita_flotador', 'alumno_kids_us', 'tiene_alergias',
            'tiene_discapacidad', 'amigo_campamento', 'tiene_reserva_previa',
            'redes_sociales', 'img_administrativas'];

        radioGroups.forEach(function (groupName) {
            const $radioGroup = $(`input[name="${groupName}"]`);
            const $container = $radioGroup.closest('.form-row');

            // Solo validar si el campo está visible
            if ($container.is(':visible')) {
                if (!$(`input[name="${groupName}"]:checked`).length) {
                    isValid = false;
                    if (!firstErrorField) firstErrorField = $radioGroup.first();

                    // Añadir mensaje de error si no existe ya
                    if (!$container.find('.woocommerce-error-custom').length) {
                        isCatalan ? $container.append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, selecciona una opció</div>') : $container.append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, selecciona una opción</div>');
                    }
                }
            }
        });

        // 3. Validar campos condicionales según el curso
        const cursoSeleccionado = $('#curso_escolar').val();

        // Validar que se ha seleccionado un curso
        if (!cursoSeleccionado) {
            isValid = false;
            if (!firstErrorField) firstErrorField = $('#curso_escolar');

            if (!$('#curso_escolar_field').find('.woocommerce-error-custom').length) {
                isCatalan ? $('#curso_escolar_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, selecciona un curs escolar</div>') : $('#curso_escolar_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, selecciona un curso escolar</div>');
            }
        } else {
            // Validar siesta solo para i3
            if (cursoSeleccionado === 'i3') {
                if (!$('input[name="necesita_siesta"]:checked').length) {
                    isValid = false;
                    if (!firstErrorField) firstErrorField = $('input[name="necesita_siesta"]').first();

                    const $container = $('input[name="necesita_siesta"]').closest('.form-row');
                    if (!$container.find('.woocommerce-error-custom').length) {
                        isCatalan ? $container.append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, indica si l\'infant fa migdiada</div>') : $container.append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, indica si el niño/a toma siesta</div>');
                    }
                }
            }

            // Validar flotador para i3, i4, i5
            if (['i3', 'i4', 'i5'].includes(cursoSeleccionado)) {
                if (!$('input[name="necesita_flotador"]:checked').length) {
                    isValid = false;
                    if (!firstErrorField) firstErrorField = $('input[name="necesita_flotador"]').first();

                    const $container = $('input[name="necesita_flotador"]').closest('.form-row');
                    if (!$container.find('.woocommerce-error-custom').length) {
                        isCatalan ? $container.append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, indica si l\'infant necessita flotador</div>') : $container.append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, indica si el niño/a necesita flotador</div>');
                    }
                }
            }
        }

        // 4. Validar campos condicionales según selecciones
        // Validar detalle de alergias
        if ($('input[name="tiene_alergias"]:checked').val() === 'si' && !$('#detalle_alergias').val()) {
            isValid = false;
            if (!firstErrorField) firstErrorField = $('#detalle_alergias');

            if (!$('#detalle_alergias_field').find('.woocommerce-error-custom').length) {
                isCatalan ? $('#detalle_alergias_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, detalla les al·lèrgies</div>') : $('#detalle_alergias_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, detalla las alergias</div>');
            }
        }

        // Validar detalle de discapacidad
        if ($('input[name="tiene_discapacidad"]:checked').val() === 'si' && !$('#detalle_discapacidad').val()) {
            isValid = false;
            if (!firstErrorField) firstErrorField = $('#detalle_discapacidad');

            if (!$('#detalle_discapacidad_field').find('.woocommerce-error-custom').length) {
                isCatalan ? $('#detalle_discapacidad_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, detalla la necessitat especial o discapacitat</div>') : $('#detalle_discapacidad_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, detalla la necesidad especial o discapacidad</div>');
            }
        }


        // Validar nombre de amigo en campamento
        if ($('input[name="amigo_campamento"]:checked').val() === 'si' && !$('#nombre_amigo_campamento').val()) {
            isValid = false;
            if (!firstErrorField) firstErrorField = $('#nombre_amigo_campamento');

            if (!$('#nombre_amigo_campamento_field').find('.woocommerce-error-custom').length) {
                isCatalan ? $('#nombre_amigo_campamento_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, indica el nom de l\'amic/a</div>') : $('#nombre_amigo_campamento_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, indica el nombre del amigo/a</div>');
            }
        }

        // Validar nombre de reserva anterior
        if ($('input[name="tiene_reserva_previa"]:checked').val() === 'si' && !$('#nombre_reserva_anterior').val()) {
            isValid = false;
            if (!firstErrorField) firstErrorField = $('#nombre_reserva_anterior');

            if (!$('#nombre_reserva_anterior_field').find('.woocommerce-error-custom').length) {
                isCatalan ? $('#nombre_reserva_anterior_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, indica el nom del germà/ana</div>') : $('#nombre_reserva_anterior_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, indica el nombre del hermano/a</div>');
            }
        }


        // Validar escuela de procedencia si es "otra"
        if ($('#escuela_procedencia').val() === 'otra' && !$('#escuela_procedencia_otra').val()) {
            isValid = false;
            if (!firstErrorField) firstErrorField = $('#escuela_procedencia_otra');

            if (!$('#escuela_procedencia_otra_field').find('.woocommerce-error-custom').length) {
                isCatalan ? $('#escuela_procedencia_otra_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, indica el nom de l\'escola</div>') : $('#escuela_procedencia_otra_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, indica el nombre de la escuela</div>');
            }
        }

        // 5. Validar coincidencia de emails
        if (!validarEmailTutor1()) {
            isValid = false;
            if (!firstErrorField) firstErrorField = $('#email_tutor_confirm');
        }

        // Validar email del segundo tutor si está visible
        if ($('#segundo_tutor').is(':checked') && $('#email_tutor_2').val() && $('#email_tutor_2_confirm').val()) {
            if (!validarEmailTutor2()) {
                isValid = false;
                if (!firstErrorField) firstErrorField = $('#email_tutor_2_confirm');
            }
        }

        // 6. Validar condiciones generales
        if (!$('#condiciones_generales').is(':checked')) {
            isValid = false;
            if (!firstErrorField) firstErrorField = $('#condiciones_generales');

            if (!$('#condiciones_generales_field').find('.woocommerce-error-custom').length) {
                isCatalan ? $('#condiciones_generales_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Si us plau, accepta les condicions generals</div>') : $('#condiciones_generales_field').append('<div class="woocommerce-error-custom" style="color:red;margin-top:5px;">Por favor, acepta las condiciones generales</div>');
            }
        }

        // 7. Validar códigos de beca si existen
        if ($('.campos-beca').length && !validarCodigosBeca()) {
            isValid = false;
            // Mensaje ya se muestra en la función validarCodigosBeca
        }

        // Si hay errores, desplazarse al primer campo con error
        if (!isValid && firstErrorField) {
            e.preventDefault(); // Prevenir el envío del formulario
            $('html, body').animate({
                scrollTop: firstErrorField.offset().top - 100
            }, 500);

            // Mostrar mensaje general de error
            isCatalan ? $('form.checkout').before('<div class="woocommerce-error-summary" style="color:red;padding:10px;margin-bottom:20px;background:#fff6f6;border-left:3px solid red;">Si us plau, revisa els camps marcats en vermell abans de continuar.</div>') : $('form.checkout').before('<div class="woocommerce-error-summary" style="color:red;padding:10px;margin-bottom:20px;background:#fff6f6;border-left:3px solid red;">Por favor, revisa los campos marcados en rojo antes de continuar.</div>');
            return false;
        }

        return true;
    });

    // Añadir estilos CSS para mejorar la visualización
    $('head').append(`
        <style>
            .d-none {
                display: none !important;
            }
            .woocommerce-error-custom {
                color: #b81c23;
                margin-top: 5px;
                font-size: 0.85em;
            }
            .woocommerce-error-summary {
                color: #b81c23;
                padding: 10px;
                margin-bottom: 20px;
                background: #fff6f6;
                border-left: 3px solid #b81c23;
                font-weight: bold;
            }
            .form-row {
                margin-bottom: 15px !important;
                position: relative;
            }
            .radio-inline-group label {
                display: inline-block;
                margin-right: 15px;
            }
            input:required, select:required, textarea:required {
                border-left: 3px solid #b81c23;
            }
            .custom_field {
                margin-bottom: 30px;
                padding: 20px;
                background-color: #f8f8f8;
                border-radius: 5px;
            }
            .email-match {
                display: block;
                margin-top: 5px;
                font-size: 0.85em;
            }
            /* Estilo para campos con error */
            input.error, select.error, textarea.error {
                border-color: #b81c23;
            }
            /* Estilo para campos válidos */
            input.valid, select.valid, textarea.valid {
                border-color: green;
            }
        </style>
    `);
});