# Implementacion por fases - gestion-campamento-verano

Fecha de inicio: 8 de abril de 2026

## Fase 1 - Migracion de base de datos

Estado: completada en codigo, pendiente de validacion funcional en entorno.

### Objetivo de esta fase

- Preparar la base de datos para soporte multi-escuela.
- Mantener compatibilidad con datos actuales.
- Dejar listo el camino para horarios dinamicos y textos bilingues en BD.

### Cambios aplicados

Archivos modificados:
- reservas.php
- includes/db/skc-db-schema.php
- includes/db/skc-db-migrations.php

1. Se agrego versionado de esquema:
- Constante `SKC_DB_SCHEMA_VERSION` con valor `1.1.0`.
- Opcion de WordPress `skc_db_schema_version` para ejecutar migraciones solo una vez.

2. Se agregaron funciones de migracion:
- `skc_tabla_tiene_columna()`
- `skc_migrar_base_datos_fase_1()`
- `skc_ejecutar_migraciones()`

2.1 Refactor de organizacion interna:
- Se movio la creacion de tablas base a includes/db/skc-db-schema.php.
- Se movio la logica de versionado y migraciones a includes/db/skc-db-migrations.php.
- reservas.php ahora solo carga archivos y registra hooks (entrypoint mas limpio).

3. Nueva tabla creada en migracion:
- `wp_skc_escuelas` con campos:
	- `id`, `nombre`, `nombre_ca`, `slug`, `producto_id`, `activa`

4. Se inserta escuela base si no existe:
- slug: `duran-i-bas`
- nombre: `Duran i Bas`

5. Tabla `wp_semanas_campamento` extendida con:
- `escuela_id` (INT NOT NULL DEFAULT 1)
- `semana_ca` (VARCHAR(100) NOT NULL DEFAULT '')

6. Datos existentes inicializados:
- Se asigna `escuela_id` a la escuela base.
- Se rellenan traducciones `semana_ca` para semanas actuales conocidas.

7. Tabla `wp_horarios_semana` extendida con:
- `tipo_horario_ca` (VARCHAR(50) NOT NULL DEFAULT '')

8. Datos existentes inicializados:
- `mañana` -> `mati`
- `completo` -> `complet`

9. Cambio estructural clave:
- `tipo_horario` se migra de `ENUM` a `VARCHAR(50)` cuando detecta formato ENUM.

10. Integridad referencial:
- Se intenta crear FK `wp_semanas_campamento.escuela_id` -> `wp_skc_escuelas.id`.
- Si falla la FK, se registra en log y la migracion no se detiene.

### Hook de ejecucion

- Se mantiene `register_activation_hook(__FILE__, 'crear_e_inicializar_tablas_campamento')`.
- Se agrega `add_action('plugins_loaded', 'skc_ejecutar_migraciones', 20)` para ejecutar la migracion por version de esquema.

### Que probar ahora (Fase 1)

1. Cargar el admin de WordPress una vez con el plugin activo para disparar `plugins_loaded`.
2. Verificar en base de datos:
- Existe tabla `wp_skc_escuelas`.
- Existe una fila base con slug `duran-i-bas`.
- `wp_semanas_campamento` tiene columnas `escuela_id` y `semana_ca`.
- `wp_horarios_semana` tiene columna `tipo_horario_ca`.
- Columna `tipo_horario` en `wp_horarios_semana` es `varchar(50)`.
3. Verificar datos:
- Semanas antiguas siguen presentes.
- `escuela_id` esta informado en semanas existentes.
- `semana_ca` y `tipo_horario_ca` tienen datos en filas existentes.
4. Verificar funcionamiento basico:
- Entrar a SportyKidsCamp -> Horarios y Stock y confirmar que carga sin errores.
- Ir a producto de campamento y confirmar que frontend de horarios sigue funcionando.
- Hacer una compra de prueba (entorno local) y confirmar que descuenta plazas como antes.

### Riesgos detectados en esta fase

- Entornos MySQL con restricciones de FK pueden no permitir agregar la clave foranea sin ajustar indices/tipos.
- Si no existe CLI de PHP (como en este entorno), no se puede correr `php -l` desde terminal para lint rapido.

### Notas

- Esta fase no cambia aun la UI de administracion ni el flujo de reservas por escuela; solo prepara estructura y compatibilidad.
- La eliminacion de diccionarios hardcodeados (`$idSemana`) queda para Fase 2.
- La reorganizacion en carpeta includes/db deja preparado el plugin para agregar futuras migraciones sin seguir creciendo en reservas.php.

## Fase 2 - Resolucion centralizada de semanas

Estado: completada en codigo, pendiente de validacion funcional en entorno.

### Objetivo de esta fase

- Eliminar diccionarios hardcodeados de semana -> ID.
- Centralizar la resolucion de semanas en un unico helper.
- Mantener compatibilidad con etiquetas historicas ya guardadas en pedidos antiguos.

### Cambios aplicados

Archivos modificados:
- reservas.php
- includes/semanas.php
- includes/functions.php
- includes/admin/dashboard.php

1. Nuevo archivo central de soporte:
- `includes/semanas.php`

2. Nuevas funciones agregadas:
- `skc_generar_aliases_semana()`
- `skc_obtener_semana_id_por_nombre()`

3. Logica implementada en el helper:
- Busca una semana por nombre en castellano o catalan.
- Consulta primero la base de datos (`semana` o `semana_ca`).
- Si el texto corresponde a una etiqueta historica inconsistente, genera aliases compatibles antes de consultar.

4. Compatibilidad historica cubierta:
- Variantes antiguas de la segunda semana (`30/31 de junio`).
- Variantes antiguas de la ultima semana con/sin `de` en `1 de agosto` y `1 de agost`.

5. Reemplazos hechos en logica de negocio:
- `descontar_plazas()` ya no usa array `$idSemana`.
- `reponer_plazas_en_pedido_cancelado()` ya no usa array `$idSemana`.

6. Reemplazos hechos en administracion:
- En `includes/admin/dashboard.php`, la edicion manual de pedido ya no usa diccionario hardcodeado para reponer o descontar plazas al cambiar semanas.

7. Mejora adicional aplicada al tocar estos bloques:
- En los puntos editados se deja de usar el nombre de tabla hardcodeado `wp_horarios_semana` y se usa `$wpdb->prefix . 'horarios_semana'`.

### Que probar ahora (Fase 2)

1. Verificar que SportyKidsCamp -> Inicio carga correctamente.
2. Editar manualmente un pedido existente desde el dashboard:
- cambiar una semana por otra
- guardar
- comprobar que se repone stock en la semana antigua
- comprobar que se descuenta stock en la nueva
3. Probar con pedidos antiguos si tienes alguno con etiquetas historicas:
- `30 de junio al 4 de julio`
- `31 junio al 4 de julio`
- `28 de julio al 1 agosto`
- `28 de juliol al 1 agost`
4. Crear un pedido nuevo y pasarlo a `processing`:
- confirmar que `descontar_plazas()` sigue funcionando
5. Cancelar o reembolsar ese pedido:
- confirmar que `reponer_plazas_en_pedido_cancelado()` devuelve la plaza correctamente
6. Revisar logs de PHP/WordPress por si aparece alguno de estos mensajes:
- `No se encontró ID para la semana`
- `No se pudo resolver la semana antigua`
- `No se pudo resolver la semana nueva`

### Riesgos detectados en esta fase

- Si aparecen nuevas variantes de etiquetas guardadas en pedidos viejos que no esten en el helper, la resolucion devolvera null y no ajustara stock para ese pedido.
- La compatibilidad historica sigue controlada por codigo mientras existan pedidos con textos inconsistentes heredados.

### Notas

- La deuda principal de los arrays `$idSemana` queda eliminada.
- La compatibilidad de pedidos historicos ya no esta duplicada en varios archivos; ahora vive en un solo sitio.
- La siguiente fase natural es refactorizar la UI/admin para gestionar escuelas, semanas y horarios de forma dinamica.

## Fase 3 - UI admin para escuelas y filtro de stock

Estado: completada en codigo, pendiente de validacion funcional en entorno.

### Objetivo de esta fase

- Añadir una pantalla de gestion de escuelas en el admin.
- Integrar escuelas en el menu principal del plugin.
- Permitir filtrar la pantalla Horarios y Stock por escuela.

### Cambios aplicados

Archivos modificados:
- reservas.php
- includes/admin/dashboard_escuelas.php
- includes/admin/dashboard_stock.php

1. Nuevo submenu del plugin:
- SportyKidsCamp -> Escuelas
- Callback: `skc_admin_escuelas_page()`

2. Nuevo archivo admin:
- `includes/admin/dashboard_escuelas.php`

3. Funcionalidad implementada en Escuelas:
- Alta de escuela (nombre ES, nombre CA, slug, producto_id, activa).
- Edicion de escuela existente.
- Eliminacion de escuela solo si no tiene semanas asociadas.
- Listado de escuelas en tabla de admin.
- Mensajes de exito/error segun resultado.

4. Cambios en Horarios y Stock:
- Se agrega selector de escuela en la parte superior.
- Se filtra la consulta de semanas por `escuela_id`.
- Al guardar plazas, solo se actualizan semanas pertenecientes a la escuela seleccionada.
- Se agrega aviso si no hay escuelas o si no hay semanas para la escuela filtrada.

5. Robustez adicional:
- Se elimino una funcion no usada (`esta_completo`) que llamaba a una funcion inexistente y generaba error de analisis.

### Que probar ahora (Fase 3)

1. Entrar al admin en SportyKidsCamp -> Escuelas.
2. Crear una escuela nueva y verificar que aparece en el listado.
3. Editar esa escuela y confirmar que guarda cambios.
4. Intentar eliminar una escuela con semanas asociadas y verificar que muestra bloqueo.
5. Ir a SportyKidsCamp -> Horarios y Stock.
6. Cambiar el selector de escuela y comprobar que cambia el listado de semanas.
7. Guardar cambios de plazas en una escuela y validar en BD que solo afecta a semanas de esa escuela.
8. Verificar que para escuelas sin semanas aparece mensaje informativo y no rompe la vista.

### Riesgos detectados en esta fase

- En esta fase el CRUD de escuelas no crea aun semanas ni horarios automaticamente; eso se cubrira en la siguiente fase de ampliacion.
- El campo `producto_id` se guarda como referencia y se usa como base para filtrar frontend en la Fase 4.

### Notas

- La base para multi-escuela en admin ya esta activa.
- La evolucion natural siguiente es ampliar esta pantalla para gestionar tambien semanas/horarios por escuela desde la misma interfaz.

## Fase 4 - Filtro AJAX por producto y escuela

Estado: completada en codigo, pendiente de validacion funcional en entorno.

### Objetivo de esta fase

- Hacer que el endpoint de disponibilidad devuelva semanas de la escuela correcta.
- Enviar desde frontend el contexto del producto para resolver la escuela asociada.
- Evitar que un producto vea semanas de otra escuela.

### Cambios aplicados

Archivos modificados:
- includes/ajax.php
- reservas.php
- assets/js/horarios.js

1. Backend AJAX mejorado (`my_get_horarios_stock`):
- Acepta `escuela_id` y `product_id` por POST.
- Si no llega `escuela_id` pero llega `product_id`, resuelve escuela desde `wp_skc_escuelas.producto_id`.
- Aplica `WHERE s.escuela_id = ...` cuando hay escuela resuelta.
- Mantiene comportamiento retrocompatible: si no hay filtro, devuelve todas las semanas.

2. Frontend (localize script):
- Se envia `productId` del producto actual en la variable JS `misDatosAjax`.
- Se mantiene `ajaxUrl` y se deja `escuelaId` preparado para extensiones.

3. Frontend JS (`horarios.js`):
- En cada llamada a disponibilidad se envia `product_id` y `escuela_id`.
- El endpoint ya puede limitar resultados al producto/escuela correctos.

4. Debug útil en respuesta:
- El JSON de respuesta incluye `escuela_id` y `product_id` aplicados.

### Que probar ahora (Fase 4)

1. Crear dos escuelas en admin y asignar `producto_id` distinto a cada una.
2. Asegurar que cada escuela tiene semanas en BD.
3. Abrir frontend del Producto A:
- comprobar que solo aparecen semanas de la escuela del Producto A.
4. Abrir frontend del Producto B:
- comprobar que solo aparecen semanas de la escuela del Producto B.
5. Validar via red (DevTools -> Network -> admin-ajax.php):
- request incluye `action=get_horarios_stock` y `product_id`.
- response devuelve `escuela_id` esperado.
6. Probar caso sin asociacion producto->escuela:
- quitar temporalmente `producto_id` en una escuela y recargar producto.
- confirmar que el sistema no rompe (comportamiento fallback).

### Riesgos detectados en esta fase

- Si hay varios registros de escuela con el mismo `producto_id`, se toma el primero por `activa DESC, id ASC`.
- Si un producto no esta asociado a escuela, el endpoint cae al modo sin filtro y puede devolver todas las semanas.

### Notas

- Esta fase deja listo el backend/frontend para separar disponibilidad por escuela sin romper productos existentes.
- El siguiente paso natural es reforzar validaciones para impedir asociaciones ambiguas de `producto_id` en admin.

## Fase 5 - Checkout con contexto de escuela

Estado: completada en codigo, pendiente de validacion funcional en entorno.

### Objetivo de esta fase

- Guardar el `escuela_id` en las reservas del pedido.
- Resolver semanas con contexto de escuela para evitar ambiguedades.
- Evitar carritos con productos de escuelas distintas en un mismo checkout.

### Cambios aplicados

Archivos modificados:
- includes/semanas.php
- includes/utilidades.php
- includes/functions.php
- includes/admin/dashboard.php

1. Helpers de escuelas/semanas ampliados (`includes/semanas.php`):
- `skc_obtener_escuela_id_por_producto(int $product_id)`
- `skc_obtener_semana_id_por_nombre_y_escuela(string $nombre_semana, ?int $escuela_id)`
- `skc_obtener_semana_id_por_nombre()` mantiene compatibilidad y delega en la version con contexto.

2. Informacion del carrito enriquecida (`includes/utilidades.php`):
- `obtener_info_cart()` ahora anade `escuela_id` y `product_id` en cada semana reservada.

3. Validacion de carrito (`includes/utilidades.php` + `includes/functions.php`):
- Nuevo validador `skc_validar_carrito_una_escuela()`.
- Hook: `woocommerce_check_cart_items`.
- Si detecta mezcla de escuelas en el carrito, bloquea checkout con notice de error.

4. Reserva en pedido mejorada (`includes/functions.php`):
- `reservar_plazas_campamento()` guarda en meta `plazas_reservadas`:
	- horario
	- acogida
	- beca
	- escuela_id
	- product_id

5. Ajustes de stock con contexto (`includes/functions.php`):
- `descontar_plazas()` usa `escuela_id` del meta al resolver semana.
- `reponer_plazas_en_pedido_cancelado()` usa `escuela_id` del meta al resolver semana.

6. Edicion manual de pedido compatible (`includes/admin/dashboard.php`):
- Preserva `escuela_id` al reconstruir `plazas_reservadas`.
- Usa `escuela_id` en la resolucion de semanas para reponer/descontar al editar.

### Que probar ahora (Fase 5)

1. Crear pedido nuevo con un producto asociado a escuela:
- pasar a `processing` y comprobar descuento de plazas.
- verificar en meta `plazas_reservadas` que cada semana incluye `escuela_id` y `product_id`.
2. Cancelar/reembolsar ese pedido:
- comprobar reposicion de plazas correcta en la misma escuela.
3. Intentar agregar al carrito productos de dos escuelas distintas:
- debe aparecer notice de error y bloquear checkout.
4. Editar manualmente un pedido desde dashboard (cambiar semana):
- debe reponer semana antigua y descontar semana nueva sin perder contexto de escuela.
5. Probar pedido historico (sin `escuela_id` en meta):
- debe seguir funcionando por fallback usando resolucion sin escuela.

### Riesgos detectados en esta fase

- El bloqueo por mezcla de escuelas en carrito depende de que los productos tengan `producto_id` bien vinculado en `wp_skc_escuelas`.
- Pedidos historicos sin `escuela_id` siguen por fallback, pero pueden ser ambiguos si hay semanas duplicadas entre escuelas.

### Notas

- Esta fase cierra la parte de checkout multi-escuela a nivel de datos y consistencia.
- La fase siguiente recomendada es permitir gestionar semanas/horarios por escuela desde admin (alta/edicion) para evitar carga manual en BD.

## Fase 6 - Diccionario de meses dinamico en frontend

Estado: completada en codigo, pendiente de validacion funcional en entorno.

### Objetivo de esta fase

- Mover el diccionario de meses CA->ES desde JS a PHP.
- Permitir mantenimiento centralizado del diccionario sin editar JS.
- Mantener compatibilidad con fallback local en caso de ausencia de datos localizados.

### Cambios aplicados

Archivos modificados:
- reservas.php
- assets/js/horarios.js

1. Localize script ampliado (`reservas.php`):
- Se añade `monthsMap` dentro de `misDatosAjax`.
- El diccionario se define en PHP al cargar el script de producto.

2. Frontend refactor (`assets/js/horarios.js`):
- Nuevo objeto `MONTHS_MAP` que toma prioridad desde `misDatosAjax.monthsMap`.
- Se mantiene fallback local si no llega `monthsMap`.
- `unificarMesesACastellano()` ya usa `MONTHS_MAP` en vez de diccionario hardcodeado interno.

3. Compatibilidad y robustez:
- Se incluye `marc` y `març` para cubrir variantes de escritura.
- No cambia la interfaz publica del endpoint ni del resto del flujo de stock.

### Que probar ahora (Fase 6)

1. Abrir un producto de campamento en castellano y en catalan.
2. En DevTools consola, comprobar que existe `misDatosAjax.monthsMap`.
3. Verificar que semanas en catalan se normalizan correctamente y comparan bien con la disponibilidad de BD.
4. Validar que mensajes de sin plazas siguen funcionando (`Sin plazas` / `Sense places`).
5. Simular ausencia de `monthsMap` (temporalmente quitandolo en localize) y comprobar que el fallback local de JS sigue operando.

### Riesgos detectados en esta fase

- Si se eliminan claves de meses en `monthsMap`, algunas comparaciones de semanas traducidas podrian fallar.
- Si algun frontend cachea JS antiguo con localize nuevo (o viceversa), puede haber inconsistencias temporales hasta limpiar cache.

### Notas

- Esta fase elimina deuda tecnica de diccionario duplicado y deja listo el soporte de traducciones dinamicas para futuras variantes.

## Fase 7 - Gestion de semanas y horarios desde admin

Estado: completada en codigo, pendiente de validacion funcional en entorno.

### Objetivo de esta fase

- Evitar carga manual por SQL para semanas de escuelas nuevas.
- Gestionar altas, ediciones y bajas de semanas desde la pantalla de Escuelas.
- Mantener sincronizados los dos horarios base (`mañana` y `completo`) por cada semana.

### Cambios aplicados

Archivos modificados:
- includes/admin/dashboard_escuelas.php

1. Backend de semanas en admin (`dashboard_escuelas.php`):
- Nuevo handler `skc_guardar_semana` con nonce `skc_guardar_semana_nonce`.
- Permite crear y editar semanas con campos:
	- escuela
	- semana (ES)
	- semana (CA)
	- plazas `mañana`
	- plazas `completo`
- Calcula `plazas_totales` como suma de mañana + completo.

2. Sincronizacion de horarios por semana:
- Tras guardar semana, hace `INSERT ... ON DUPLICATE KEY UPDATE` en `wp_horarios_semana` para:
	- `mañana` / `mati`
	- `completo` / `complet`
- Con esto, la semana queda operativa para disponibilidad y descuento de plazas sin pasos manuales extra.

3. Eliminacion segura de semanas:
- Nuevo handler `skc_eliminar_semana` con nonce `skc_eliminar_semana_nonce`.
- Bloquea eliminacion si la suma de `plazas_reservadas` de la semana es mayor que 0.
- Si no hay reservas, elimina primero horarios y luego la semana.

4. Nueva UI en la misma pantalla de Escuelas:
- Selector de escuela a gestionar (`escuela_gestion_id`).
- Formulario de alta/edicion de semana.
- Listado de semanas de la escuela seleccionada con:
	- plazas por horario
	- plazas reservadas por horario
	- plazas totales
	- acciones editar/eliminar.

5. Ajuste de contexto al editar semana:
- Si se abre `editar_semana` de otra escuela, la vista cambia automaticamente a la escuela correcta para evitar inconsistencias de UI.

### Que probar ahora (Fase 7)

1. Ir a SportyKidsCamp -> Escuelas y pulsar `Gestionar semanas` en una escuela.
2. Crear una semana nueva con plazas en mañana/completo y guardar.
3. Verificar en BD:
- se crea fila en `wp_semanas_campamento` con `escuela_id` correcto.
- existen filas en `wp_horarios_semana` para `mañana` y `completo` con las plazas definidas.
4. Editar la misma semana y cambiar plazas:
- comprobar que se actualizan `plazas` en ambos horarios.
5. Intentar eliminar una semana con reservas:
- debe mostrar aviso y no borrar.
6. Eliminar una semana sin reservas:
- debe eliminar semana y sus horarios asociados.
7. Entrar al frontend del producto asociado y validar que la semana nueva aparece en disponibilidad.

### Riesgos detectados en esta fase

- La sincronizacion de horarios usa tipos base (`mañana` y `completo`); si en el futuro se agregan mas tipos, habra que extender esta pantalla.
- Si en BD no existe indice unico esperado para `semana_id + tipo_horario`, el `ON DUPLICATE KEY` no actuara como update.

### Notas

- Con esta fase, alta de escuela + configuracion de semanas puede hacerse 100% desde WordPress admin.
- Se reduce significativamente la dependencia de SQL manual para operativa diaria.
