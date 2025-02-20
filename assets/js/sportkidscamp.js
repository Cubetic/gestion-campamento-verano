/*jQuery(document).ready(function($) {
     // Capturar el input del usuario cuando pierde el foco (blur)
            $('#nombre_reserva_anterior').change(function () {
                let tieneHermano = $('input[name="tiene_reserva_previa"]:checked').val();
                let nombreHermano = $('#nombre_reserva_anterior').val();
                // let mensajeValidacion = $('#mensaje_validacion_hermano');

	                console.log(nombreHermano);

                    $.ajax({
                        type: 'POST',
                        url: wc_checkout_params.ajax_url,
                        data: {
                            action: 'verificar_hermano',
                            nombre_hermano: nombreHermano,
                            tiene_hermano: tieneHermano
                        },
                        success: function (response) {
                            if (response.success) {
                                console.log('✅ Hermano encontrado. Se aplicará el descuento.');
                           } else {
                            console.log(response);
                            }
                        }
                    });
            });

            

            $('input[name="acepta_privacidad"]').change(actualizarCheckout);
            $('input[name="solicita_beca"]').change(actualizarCheckout);

            function actualizarCheckout() {
                let beca = $('input[name="solicita_beca"]:checked').val();
                let privacidadAceptada = $('input[name="acepta_privacidad"]').is(':checked');

                if (privacidadAceptada) {
                     $('input[name="solicita_beca"]').prop('disabled', false);
                } else {
                    $('input[name="solicita_beca"]').prop('disabled', true).prop('checked', false);
                }

                $.ajax({
                    type: 'POST',
                    url: wc_checkout_params.ajax_url,
                    data: {
                        action: 'apply_beca_discount',
                        solicita_beca: beca,
                        privacidad_aceptada: privacidadAceptada ? 'yes' : 'no'
                    },
                    success: function() {
                        $('body').trigger('update_checkout');
                    }
                });
            }

   
});

// Función AJAX para limpiar la sesión de descuentos
    function limpiarSesionDescuentos() {
        $.post(wc_checkout_params.ajax_url, { action: 'limpiar_sesion_descuentos' }, function(response) {
            console.log('Sesión de descuentos limpiada:', response);
        });
    }

             // Función para obtener los datos de la sesión
    function obtenerSesionWooCommerce() {
        $.post(wc_checkout_params.ajax_url, { action: 'obtener_sesion_woocommerce' }, function(response) {
            console.log('Datos de la sesión:', response.data);
        });
    }


/*jQuery(document).ready(function ($) {
    
    function toggleField(radioName, fieldId) {
        let fieldContainer = $('#' + fieldId).closest('.form-row');

        function updateVisibility() {
            let selectedValue = $("input[name='" + radioName + "']:checked").val();
            if (selectedValue === "si") {
                fieldContainer.show(); // Mostrar si el usuario selecciona "Sí"
            } else {
                fieldContainer.hide().find('textarea, input').val(""); // Ocultar y limpiar el campo
            }
        }

        $("input[name='" + radioName + "']").change(updateVisibility);
        updateVisibility(); // Ejecutar al cargar la página para el estado inicial
    }

    // Aplicamos la función a los radio buttons correspondientes
    toggleField('tiene_discapacidad', 'detalle_discapacidad_field');
    toggleField('tiene_alergias', 'detalle_alergias_field');
    toggleField('solicita_beca', 'codigo_beca_field');

    // Ocultar el campo de nombre del hermano con reserva por defecto
    $('#nombre_reserva_anterior').closest('.form-row').hide();
    
    $('#tiene_reserva_previa').change(function(){
        if ($(this).is(':checked')) {
            $('#nombre_reserva_anterior').closest('.form-row').show();
        } else {
            $('#nombre_reserva_anterior').closest('.form-row').hide().find('input').val("");
        }
    });

    // Validar la beca solo si se acepta la privacidad
    $("input[name='solicita_beca']").change(function () {
        if (!$("input[name='acepta_privacidad']").is(":checked")) {
            if ($('#beca_alert').length === 0) {
                $("<p id='beca_alert' style='color: red; font-size: 14px;'>Debe aceptar las políticas de privacidad antes de solicitar la beca.</p>").insertAfter($(this).closest('label'));
            }
            $(this).prop("checked", false);
        } else {
            $('#beca_alert').remove();
        }
    });

    // Bloquear el checkbox de beca si no se acepta la privacidad
    function actualizarEstadoBeca() {
        let privacidadAceptada = $("input[name='acepta_privacidad']").is(":checked");
        let becaCheckbox = $("input[name='solicita_beca']");
        
        if (privacidadAceptada) {
            becaCheckbox.prop("disabled", false);
        } else {
            becaCheckbox.prop("disabled", true).prop("checked", false);
        }
    }

    $("input[name='acepta_privacidad']").change(actualizarEstadoBeca);
    actualizarEstadoBeca(); // Ejecutar al cargar la página para estado inicial
});*/



jQuery(document).ready(function(){
    function toggleField(radioName, containerId) {
        let targetContainer = $("#" + containerId); // Capturamos el contenedor

        function updateVisibility() {
            let selectedValue = $("input[name='" + radioName + "']:checked").val();
            if (selectedValue === "si") {
                targetContainer.removeClass("d-none");
            } else {
                targetContainer.addClass("d-none").find("input, textarea").val(""); // Limpia el campo si se oculta
            }
        }

        // Ejecutar la función cuando se cambia el radio
        $("input[name='" + radioName + "']").change(updateVisibility);
        
        // Ejecutar al cargar la página para establecer el estado inicial
        updateVisibility();
    }

    // Aplicar la función a los campos necesarios
    toggleField("tiene_discapacidad", "detalle_discapacidad_container");
    toggleField("tiene_alergias", "detalle_alergias_container");
    toggleField("tiene_reserva_previa", "nombre_reserva_anterior_container");

 });

jQuery(document).ready(function () {
    function toggleFields() {
        let selectedValue = $("#curso_escolar").val();

        // Mostrar "siesta" solo si es I3
        if (selectedValue === "i3") {
            $("#siesta_container").removeClass("d-none");
        } else {
            $("#siesta_container").addClass("d-none").find("input, textarea").val(""); // Limpia valores si se oculta
        }

        // Mostrar "flotador" hasta I5 incluido
        if (["i3", "i4", "i5"].includes(selectedValue)) {
            $("#flotador_container").removeClass("d-none");
        } else {
            $("#flotador_container").addClass("d-none").find("input, textarea").val("");
        }
    }

    // Ejecutar cuando cambia el select
    $("#curso_escolar").change(toggleFields);

    // Ejecutar al cargar la página para establecer el estado inicial
    toggleFields();
});

    
