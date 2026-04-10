# Propuesta segura: personalizar por semana los dos horarios actuales

Este documento parte del estado real actual del plugin y propone el cambio mas seguro para permitir modificar por semana los horarios de manana y completo.

## 1. Estado real actual

Ahora mismo el sistema solo tiene dos tipos de horario:

- manana
- completo

Eso no es solo una convencion visual. Esta metido en varias capas del plugin:

- La tabla base original crea tipo_horario como ENUM('mañana', 'completo').
- El admin de semanas guarda siempre dos filas: manana y completo.
- El parser del pedido detecta el tipo segun el texto exacto de la franja:
  - 9:00h a 14:30h -> manana
  - 9:00h a 17:00h -> completo
- Las utilidades del carrito hacen la misma deteccion.
- El JS del frontend detecta el horario mirando si el texto contiene 14:30h o 17:00h.

Conclusión:

- Hoy no existe tarde.
- Hoy no se puede cambiar libremente el texto del horario sin romper reserva, stock o frontend.

## 2. Objetivo del cambio

Permitir que dentro de cada semana se pueda modificar:

- el texto visible del horario de manana
- el texto visible del horario completo
- las plazas de ambos horarios

Sin cambiar el modelo funcional base del plugin, que seguira siendo de dos tipos internos:

- manana
- completo

## 3. La forma mas segura de hacerlo

La forma mas segura no es convertir el sistema en dinamico, sino desacoplar el texto visible del identificador interno.

Es decir:

- Internamente el sistema sigue trabajando con tipo_horario = manana o completo.
- Visualmente, cada semana puede mostrar una franja distinta para cada uno.

Ejemplo:

- Semana A:
  - manana = De 9:00h a 14:30h
  - completo = De 9:00h a 17:00h

- Semana B:
  - manana = De 8:30h a 13:00h
  - completo = De 8:30h a 16:00h

Internamente ambas semanas seguirian usando solo:

- manana
- completo

## 4. Cambio minimo de base de datos

La base de datos no necesita rehacerse. El cambio minimo recomendado es anadir un solo campo nuevo en wp_horarios_semana:

- nombre_horario VARCHAR(120) NOT NULL DEFAULT ''

La tabla seguiria usando:

- semana_id
- tipo_horario
- nombre_horario
- plazas
- plazas_reservadas

De esta forma:

- tipo_horario identifica la logica interna
- nombre_horario guarda la franja visible para esa semana

## 5. Backfill inicial

Para los registros ya existentes:

- tipo_horario = manana -> nombre_horario = De 9:00h a 14:30h
- tipo_horario = completo -> nombre_horario = De 9:00h a 17:00h

Esto permite migrar sin romper nada visualmente.

## 6. Cambios necesarios en admin

## 6.1 Pantalla Escuelas > Semanas

Ahora mismo el formulario solo guarda:

- plazas_manana
- plazas_completo

Habria que ampliarlo para guardar tambien:

- nombre_horario_manana
- plazas_manana
- nombre_horario_completo
- plazas_completo

La UI mas segura es mantener solo dos bloques fijos:

### Bloque manana

- Franja horario manana
- Plazas manana

### Bloque completo

- Franja horario completo
- Plazas completo

No hace falta añadir checks, bloques dinamicos ni estados nuevos.

## 6.2 Guardado

La logica recomendada es:

- INSERT ... ON DUPLICATE KEY UPDATE para la fila manana
- INSERT ... ON DUPLICATE KEY UPDATE para la fila completo

Actualizando estos campos:

- nombre_horario
- plazas

## 6.3 Listado de semanas

Seria conveniente que el listado muestre, para cada semana:

- plazas manana / reservadas manana
- plazas completo / reservadas completo
- opcionalmente la franja actual de cada uno

## 7. Punto critico real: parser y frontend

Este es el punto importante.

Ahora mismo el sistema no guarda el tipo de horario porque lo reconozca por configuracion, sino porque lo deduce leyendo el texto de la opcion seleccionada.

Ejemplos actuales:

- Si el valor contiene 9:00h a 14:30h -> manana
- Si el valor contiene 9:00h a 17:00h -> completo

Esto significa que si cambias la franja a:

- 8:30h a 13:00h

el parser actual ya no sabra si eso es manana o completo.

## 8. Fallos que podria dar si solo cambiamos el texto y nada mas

### Fallo 1: no se guarda bien plazas_reservadas

Si el parser no reconoce la franja, horario quedaria vacio en plazas_reservadas.

Consecuencia:

- el pedido se crea
- pero luego no sabe que fila de stock descontar

### Fallo 2: no descuenta stock al pasar a processing

descontar_plazas busca por semana_id + tipo_horario.

Si el tipo no se detecto bien antes, no encontrara el horario correcto.

### Fallo 3: no repone stock en cancelacion/reembolso

La reposicion usa tambien el tipo guardado.

Si el pedido se guardo sin tipo correcto, la reposicion puede fallar.

### Fallo 4: frontend no bloquea bien opciones sin plazas

El JS actual asocia:

- 14:30h -> manana
- 17:00h -> completo

Si el texto cambia, el JS puede dejar de saber que opcion corresponde a cada horario y mostrar disponibilidad equivocada.

### Fallo 5: WAPF y WordPress quedan desalineados

Si en WordPress guardas una franja pero en WAPF el texto sigue siendo el antiguo, la experiencia queda inconsistente y el parser puede romperse.

## 9. Como hacerlo de la manera mas segura

La forma segura es tocar primero la identificacion del horario y solo despues permitir editar el texto visible.

## 9.1 Principio clave

El sistema no debe deducir manana/completo por las horas escritas.

Debe obtener ese tipo por una referencia estable.

## 9.2 Opcion mas segura de implementacion

Mantener los dos tipos internos:

- manana
- completo

Y usar nombre_horario solo como etiqueta visible.

Luego adaptar backend y frontend para que mapeen la opcion elegida contra la fila real de wp_horarios_semana de esa semana, en lugar de hardcodear las horas.

En la practica:

1. Para cada semana, la BD sabra:
	- manana -> nombre_horario configurado
	- completo -> nombre_horario configurado
2. Cuando el usuario elija una opcion en WAPF:
	- el sistema debe comparar ese texto con nombre_horario de la semana
	- y resolver si corresponde a manana o completo
3. El pedido guardara igualmente:
	- horario = manana o completo

Esto mantiene la compatibilidad con el resto del plugin.

## 10. Cambios concretos recomendados

## 10.1 Base de datos

- Añadir nombre_horario a wp_horarios_semana.
- Rellenar manana/completo existentes con sus textos actuales.

## 10.2 Admin de semanas

- Añadir dos inputs nuevos:
  - nombre_horario_manana
  - nombre_horario_completo
- Seguir manteniendo:
  - plazas_manana
  - plazas_completo

## 10.3 Admin de stock

- Mostrar tambien la franja configurada de manana y completo.
- No hace falta cambiar el modelo general del panel.

## 10.4 Backend de pedido y carrito

Cambiar las partes que hoy hardcodean las horas:

- reservar_plazas_campamento
- obtener_info_cart

Nuevo comportamiento recomendado:

- si el usuario selecciona un texto de horario para una semana,
- buscar en wp_horarios_semana de esa semana,
- comparar con nombre_horario,
- y resolver a tipo_horario = manana o completo.

## 10.5 Frontend JS

El JS de disponibilidad tambien debe dejar de deducir el tipo por 14:30h y 17:00h.

La opcion segura es que el AJAX devuelva, por cada semana:

- tipo_horario
- nombre_horario
- plazas
- estado

Y que el JS cruce la opcion visible con nombre_horario, no con el string fijo de la hora.

## 10.6 WAPF

Para que esto sea estable, el texto mostrado en WAPF para cada semana debe coincidir con nombre_horario guardado en WordPress.

Si no coinciden, el mapeo fallara.

## 11. Riesgos incluso haciendolo bien

### Riesgo 1: semanas mal configuradas

Si dejas nombre_horario vacio o duplicas el mismo texto en manana y completo dentro de la misma semana, el parser tendra ambigüedad.

Mitigacion:

- Validar en admin que ambos nombres no esten vacios.
- Validar que manana y completo no tengan el mismo nombre_horario en una misma semana.

### Riesgo 2: configuracion WAPF no actualizada

Si cambias el horario en WordPress y WAPF sigue mostrando el texto antiguo, el flujo se rompe.

Mitigacion:

- Definir proceso operativo claro para actualizar WAPF.
- O mejor aun, automatizar en una fase posterior la sincronizacion.

### Riesgo 3: pedidos legacy

Los pedidos antiguos ya guardados con manana/completo no deberian romperse.

Mitigacion:

- Mantener compatibilidad leyendo horario legacy como hasta ahora.

### Riesgo 4: JS con coincidencias parciales raras

Si el JS compara texto de forma demasiado flexible, podria asociar mal una opcion.

Mitigacion:

- Comparar igualdad normalizada exacta cuando sea posible.
- Usar coincidencias parciales solo como ultimo recurso.

## 12. Orden mas seguro de implementacion

1. Migracion BD:
	- Añadir nombre_horario
	- Backfill de manana y completo
2. Admin semanas:
	- Añadir campos de franja para manana y completo
	- Guardarlos en nombre_horario
3. Backend:
	- Cambiar parser para mapear por nombre_horario en vez de horas fijas
4. Frontend:
	- Ajustar AJAX y JS para usar nombre_horario
5. Operativa:
	- Revisar WAPF y asegurar que los textos coincidan

## 13. Checklist de validacion

1. Editar una semana y cambiar solo el texto visible de manana.
2. Editar una semana y cambiar solo el texto visible de completo.
3. Confirmar que el admin guarda bien ambas franjas.
4. Hacer pedido con manana y verificar plazas_reservadas correcto.
5. Hacer pedido con completo y verificar plazas_reservadas correcto.
6. Pasar pedido a processing y confirmar descuento de stock.
7. Cancelar pedido y confirmar reposicion.
8. Verificar que frontend bloquea bien opciones sin plazas.
9. Verificar que WAPF y WordPress muestran exactamente el mismo texto.

## 14. Recomendacion final

La mejor opcion ahora mismo es no añadir tarde ni meter horarios dinamicos generales. Lo mas seguro es mantener el sistema de dos tipos internos, manana y completo, y permitir solo cambiar su texto visible por semana mediante un nuevo campo nombre_horario. El cambio importante no es visual, sino tecnico: dejar de deducir el tipo por las horas hardcodeadas y empezar a resolverlo contra la configuracion real de la semana.

## 15. Implementacion por fases

La idea es desplegar el cambio poco a poco, con pruebas entre fases, para evitar quedarnos en un punto en el que el admin deja guardar una franja nueva pero el checkout todavia no sabe interpretarla.

Regla general:

- No pasar a la fase siguiente hasta validar la anterior en local.
- Mantener compatibilidad con el flujo actual mientras la siguiente fase no este cerrada.

## 15.1 Fase 1: base de datos y preparacion sin cambiar el comportamiento

Objetivo:

- Añadir la capacidad tecnica de guardar el texto visible del horario por semana.
- No cambiar todavia ni checkout ni parser ni frontend.

Cambios incluidos:

1. Añadir columna nombre_horario en wp_horarios_semana.
2. Rellenar datos actuales:
	- manana -> De 9:00h a 14:30h
	- completo -> De 9:00h a 17:00h
3. Mantener intacto el resto del flujo.
4. Subir version de esquema y crear migracion nueva.

Archivos afectados previsibles:

- reservas.php
- includes/db/skc-db-migrations.php
- includes/db/skc-db-schema.php

Lo importante de esta fase:

- El plugin debe seguir funcionando exactamente igual que antes.
- Aunque exista nombre_horario, todavia no debe dependerse de el en la logica de stock.

Posibles problemas:

- La migracion puede no ejecutarse si la version de esquema en local ya quedo adelantada por pruebas anteriores.
- Puede haber filas existentes en wp_horarios_semana con valores inesperados o incompletos.
- En instalaciones nuevas, si skc-db-schema.php no queda alineado con la migracion, una instalacion limpia y una migrada podrian quedar distintas.

Cosas a probar al cerrar Fase 1:

1. Ejecutar la migracion y verificar que la columna nombre_horario existe.
2. Confirmar que manana y completo han recibido el valor correcto en registros existentes.
3. Crear una semana nueva y verificar que el sistema sigue creando manana y completo como hasta ahora.
4. Hacer un pedido normal sin cambiar ningun horario y confirmar que plazas_reservadas sigue saliendo bien.
5. Confirmar que no cambia nada en frontend ni en admin visible para usuario final.

## 15.2 Fase 2: admin de semanas para editar la franja visible

Objetivo:

- Permitir editar desde WordPress el texto visible de manana y completo.
- Seguir manteniendo el sistema operativo con los valores actuales por defecto.

Cambios incluidos:

1. Añadir dos campos nuevos en Escuelas > Semanas:
	- nombre_horario_manana
	- nombre_horario_completo
2. Cargar esos valores al editar una semana.
3. Guardarlos en wp_horarios_semana junto con plazas.
4. Mostrar en el listado de semanas, si conviene, la franja configurada de cada horario.

Archivos afectados previsibles:

- includes/admin/dashboard_escuelas.php

Decision importante en esta fase:

- Aunque el admin permita editar la franja, no deberia usarse todavia en produccion hasta que la Fase 3 este cerrada.
- En local si se puede empezar a probar, pero sabiendo que el checkout aun puede depender de textos antiguos.

Posibles problemas:

- Que el formulario guarde plazas pero no nombre_horario por un ON DUPLICATE KEY UPDATE incompleto.
- Que al editar una semana se vean vacios los nuevos campos porque la consulta no trae nombre_horario.
- Que nombre_horario manana y nombre_horario completo queden iguales en una misma semana y luego eso complique el parser.

Validaciones recomendadas en admin:

- nombre_horario_manana obligatorio.
- nombre_horario_completo obligatorio.
- No permitir que ambos tengan exactamente el mismo texto dentro de la misma semana.

Cosas a probar al cerrar Fase 2:

1. Abrir una semana existente y ver los dos campos rellenos con los horarios actuales.
2. Cambiar solo la franja de manana y guardar.
3. Cambiar solo la franja de completo y guardar.
4. Confirmar en base de datos que nombre_horario se ha actualizado en ambas filas correctas.
5. Verificar que las plazas siguen guardando bien.
6. Verificar que una semana nueva crea ambos horarios con texto por defecto si no se informa otro.

## 15.3 Fase 3: backend de pedido y carrito deja de depender de las horas fijas

Objetivo:

- Hacer que el sistema identifique manana/completo por la configuracion real de la semana y no por strings hardcodeados.

Cambios incluidos:

1. En reservar_plazas_campamento(), cuando se lee el valor del horario, resolverlo comparandolo con nombre_horario de la semana correspondiente.
2. En obtener_info_cart(), hacer el mismo cambio.
3. Mantener fallback legacy:
	- si no se encuentra coincidencia por nombre_horario,
	- seguir intentando la deteccion antigua por 9:00h a 14:30h / 9:00h a 17:00h.

Archivos afectados previsibles:

- includes/functions.php
- includes/utilidades.php

Forma segura de implementar esta fase:

- Primero intentar resolver por configuracion de base de datos.
- Solo si falla, usar el comportamiento antiguo.

Posibles problemas:

- No resolver bien la semana correcta si el label del campo WAPF no coincide bien con el nombre de la semana.
- Comparar mal el texto si hay espacios, parentesis o diferencias menores de formato.
- Ambigüedad si manana y completo tienen el mismo nombre_horario dentro de una semana.

Mitigaciones:

- Normalizar texto antes de comparar.
- Quitar contenido entre parentesis como ya se hace ahora.
- Mantener validacion en admin para evitar nombres duplicados en la misma semana.

Cosas a probar al cerrar Fase 3:

1. Cambiar la franja de manana en una semana a un valor distinto del original.
2. Hacer un pedido de esa semana con manana.
3. Verificar que plazas_reservadas guarda horario=manana.
4. Repetir con completo.
5. Confirmar que un pedido legacy con horario antiguo sigue funcionando.
6. Confirmar que el descuento a processing sigue encontrando la fila correcta.
7. Confirmar que cancelar o reembolsar repone bien.

## 15.4 Fase 4: frontend y AJAX dejan de deducir por 14:30h y 17:00h

Objetivo:

- Hacer que la disponibilidad en frontend siga funcionando aunque cambien las franjas visibles.

Cambios incluidos:

1. El endpoint AJAX debe devolver tambien nombre_horario junto a tipo_horario.
2. El JS debe identificar la opcion correcta comparando el texto visible con nombre_horario de la semana.
3. Mantener como fallback temporal la deteccion antigua por 14:30h y 17:00h mientras se prueban casos reales.

Archivos afectados previsibles:

- includes/ajax.php
- assets/js/horarios.js

Posibles problemas:

- Que el JS haga coincidencias parciales erroneas y asocie una opcion al horario incorrecto.
- Que WAPF muestre un texto distinto del guardado en nombre_horario.
- Que el AJAX devuelva la informacion correcta pero el JS siga leyendo solo el string de horas antiguo.

Mitigaciones:

- Comparacion exacta normalizada entre opcion visible y nombre_horario.
- Logs temporales en consola o en PHP durante pruebas locales.
- Mantener fallback legacy solo durante la transicion.

Cosas a probar al cerrar Fase 4:

1. Cambiar la franja de manana y completo en una semana.
2. Entrar al producto y verificar que las opciones sin plazas se bloquean correctamente.
3. Verificar que las opciones con plazas siguen habilitadas.
4. Probar con una semana configurada con textos antiguos.
5. Probar con una semana configurada con textos nuevos.
6. Verificar que no hay cruces raros entre semanas distintas.

## 15.5 Fase 5: endurecimiento y limpieza

Objetivo:

- Dejar el sistema estable despues de validar que el nuevo flujo funciona.

Cambios incluidos:

1. Revisar si ya se puede retirar parte del fallback legacy.
2. Añadir validaciones extra en admin.
3. Revisar mensajes de error y logs.
4. Dejar documentado el proceso operativo para no desalinear WordPress y WAPF.

Posibles problemas:

- Eliminar fallback demasiado pronto y romper semanas antiguas.
- Dar por cerrado el cambio sin haber probado cancelaciones o reembolsos.
- Olvidar la operativa de sincronizar WAPF cuando se cambia una franja.

Cosas a probar al cerrar Fase 5:

1. Pedido nuevo con franja personalizada.
2. Pedido nuevo con franja antigua.
3. Cancelacion.
4. Reembolso.
5. Cambio de semana en admin y nueva compra posterior.
6. Verificacion visual completa de admin, checkout y stock.

## 16. Recomendacion operativa entre fases

Mientras no este cerrada la Fase 4, lo prudente es:

- implementar y probar en local
- no cambiar aun en produccion las franjas visibles de manana/completo
- mantener en WAPF los textos actuales

Motivo:

- Hasta que backend y frontend dejen de depender de strings fijos, cambiar la franja visible en produccion puede romper el descuento o la deteccion de stock.

## 17. Punto de corte recomendado para pasar a accion

El punto mas razonable para empezar es este:

1. Fase 1 completa.
2. Fase 2 completa.
3. No usar aun textos nuevos en pedidos reales.
4. Pasar a Fase 3 y probar pedidos reales en local.

Asi el cambio entra en el sistema de forma controlada y siempre hay una forma clara de aislar donde falla si algo se rompe.
