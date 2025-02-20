<?php if (!defined('ABSPATH'))
    exit; ?>

<div id="custom_checkout_fields">
    <form id="custom_checkout_form">
        <div class="p-4">
            <!-- 📌 DATOS DEL ALUMNO -->
            <div class="row p-3">
                <h3 class="mt-4">📌 Datos del Alumno/a</h3>
                <div class="row">
                    <div class="col-md-6">
                        <label for="nombre_alumno" class="form-label">Nombre del Alumno/a</label>
                        <input type="text" id="nombre_alumno" name="nombre_alumno" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="apellido_alumno" class="form-label">Apellidos del Alumno/a</label>
                        <input type="text" id="apellido_alumno" name="apellido_alumno" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label for="curso_escolar" class="form-label">Curso Escolar</label>
                        <select id="curso_escolar" name="curso_escolar" class="form-select" required>
                            <option value="">Selecciona curso escolar</option>
                            <option value="i3">I3</option>
                            <option value="i4">I4</option>
                            <option value="i5">I5</option>
                            <option value="1_primaria">1º Primaria</option>
                            <option value="2_primaria">2º Primaria</option>
                            <option value="3_primaria">3º Primaria</option>
                            <option value="4_primaria">4º Primaria</option>
                            <option value="5_primaria">5º Primaria</option>
                            <option value="6_primaria">6º Primaria</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div id="siesta_container" class="col-md-12 mt-2 mt-3 d-none">
                        <label class="form-label">¿El alumno/a toma siesta?</label>
                        <div class="d-flex gap-3">
                            <input type="radio" name="necesita_siesta" value="si"> Sí
                            <input type="radio" name="necesita_siesta" value="no"> No
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div id="flotador_container" class="mt-3 d-none col-md-12 mt-2">
                        <label class="form-label">¿El alumno/a necesita flotador en la piscina?</label>
                        <div class="d-flex gap-3">
                            <input type="radio" name="necesita_flotador" value="si"> Sí
                            <input type="radio" name="necesita_flotador" value="no"> No
                        </div>
                    </div>
                </div>
            </div>
            <!-- 📌 HERMANO CON RESERVA -->
            <div class="row p-3">
                <h3 class="mb-2">📌 ¿Tiene un hermano/a con plaza en el campamento?</h3>

                <div class="d-flex gap-3 mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tiene_reserva_previa" id="reserva_si"
                            value="si">
                        <label class="form-check-label" for="reserva_si">Sí</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tiene_reserva_previa" id="reserva_no"
                            value="no" checked>
                        <label class="form-check-label" for="reserva_no">No</label>
                    </div>
                </div>

                <!-- 📌 Campo de texto para el nombre del hermano (Oculto por defecto) -->
                <div id="nombre_reserva_anterior_container" class="d-none mt-2">
                    <label for="nombre_reserva_anterior" class="form-label"> Indique el nombre del hermano/a con
                        reserva</label>
                    <input type="text" id="nombre_reserva_anterior" name="nombre_reserva_anterior" class="form-control">
                </div>
            </div>

            <!-- 📌 INFORMACIÓN ADICIONAL -->
            <div class="row p-3">
                <h3 class="mt-4">📌 Información Adicional</h3>
                <div class="col-md-12 mt-2">
                    <label class="form-label">Alumno Kids & Us</label>
                    <div class="d-flex gap-3">
                        <input type="radio" name="alumno_kids_us" value="si"> Sí
                        <input type="radio" name="alumno_kids_us" value="no"> No
                    </div>
                </div>
                <div class="col-md-12 mt-2">
                    <label class="form-label">¿Tiene alguna necesidad especial?</label>
                    <div class="d-flex gap-3">
                        <input type="radio" name="tiene_discapacidad" id="discapacidad_si" value="si"> Sí
                        <input type="radio" name="tiene_discapacidad" id="discapacidad_no" value="no"> No
                    </div>
                </div>
                <div class="col-md-12 mt-2">
                    <div id="detalle_discapacidad_container" class="d-none mt-2">
                        <label for="detalle_discapacidad" class="form-label">Indique cuál/es</label>
                        <textarea id="detalle_discapacidad" name="detalle_discapacidad" class="form-control"></textarea>
                    </div>
                </div>

                <div class="col-md-12 mt-2">
                    <label class="form-label">Indique si el niño/a tiene alergias, intolerancias o enfermedades
                        crónicas</label>
                    <div class="d-flex gap-3">
                        <input type="radio" name="tiene_alergias" id="alergias_si" value="si"> Sí
                        <input type="radio" name="tiene_alergias" id="alergias_no" value="no"> No
                    </div>
                </div>
                <div class="col-md-12 mt-2">
                    <div id="detalle_alergias_container" class="d-none mt-2">
                        <label for="detalle_alergias" class="form-label">Indique cuál/es son</label>
                        <textarea id="detalle_alergias" name="detalle_alergias" class="form-control"></textarea>
                    </div>
                </div>
            </div>

            <!-- 📌 MODALIDAD DE PAGO -->
            <div class="row p-3">
                <h3 class="mt-4">📌 Modalidad de Pago</h3>
                <div class="d-flex gap-3">
                    <input type="radio" name="solicita_beca" id="solicita_beca_si" value="si"> Sí
                    <input type="radio" name="solicita_beca" id="solicita_beca_no" value="no"> No
                </div>
                <div id="codigo_beca_container" class="d-none mt-2">
                    <label for="codigo_beca" class="form-label">Código/o de/las Beca/s</label>
                    <input type="text" id="codigo_beca" name="codigo_beca" class="form-control">
                </div>
            </div>


            <!-- 📌 DATOS DEL PADRE/MADRE/TUTOR -->
            <div class="row">
                <h3 class="mt-4">📌 Datos del Padre | Madre | Tutor</h3>
                <!-- NOmbre y apellidos -->
                <div class="row p-3">
                    <div class="col-md-6">
                        <label for="nombre_tutor" class="form-label">Nombre</label>
                        <input type="text" id="nombre_tutor" name="nombre_tutor" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="apellido_tutor" class="form-label">Apellidos</label>
                        <input type="text" id="apellido_tutor" name="apellido_tutor" class="form-control" required>
                    </div>
                </div>
                <!-- Direccion y codigo postal -->
                <div class="row p-3">
                    <div class="col-md-6">
                        <label for="direccion_tutor" class="form-label">Dirección</label>
                        <input type="text" id="direccion_tutor" name="direccion_tutor" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="codigo_postal_tutor" class="form-label">Código Postal</label>
                        <input type="text" id="codigo_postal_tutor" name="codigo_postal_tutor" class="form-control"
                            required>
                    </div>
                </div>
                <!-- Emqail y telefono  -->
                <div class="row p-3">
                    <div class="col-md-6">
                        <label for="email_tutor" class="form-label">Email</label>
                        <input type="email" id="email_tutor" name="email_tutor" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="telefono_tutor" class="form-label">Teléfono</label>
                        <input type="text" id="telefono_tutor" name="telefono_tutor" class="form-control" required>
                    </div>
                </div>
                <!-- Ciudad -->
                <div class="row p-3">
                    <div class="col-md-6">
                        <label for="ciudad_tutor" class="form-label">Ciudad</label>
                        <input type="text" id="ciudad_tutor" name="ciudad_tutor" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="provincia_tutor" class="form-label">Provincia</label>
                        <input type="text" id="provincia_tutor" name="provincia_tutor" class="form-control" required>
                    </div>
                </div>
                <!-- Pais -->
                <div class="row p-3">
                    <div class="col-md-6">
                        <label for="pais_tutor" class="form-label">País</label>
                        <select id="pais_tutor" name="pais_tutor" class="form-select" required>
                            <option value="España">España</option>
                            <option value="Francia">Francia</option>
                            <option value="Portugal">Portugal</option>
                        </select>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>