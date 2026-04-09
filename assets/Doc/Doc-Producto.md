# Guia rapida: crear producto funcional (estado actual)

Este documento resume lo validado y corregido hoy para que un producto nuevo quede operativo con stock por semanas/horarios.

## 1) Flujo recomendado de alta (orden correcto)

1. Crear o editar escuela en SportyKidsCamp > Escuelas.
2. Asignar un producto de WooCommerce en el campo producto_id.
3. Crear semanas de esa escuela en SportyKidsCamp > Escuelas > Gestionar semanas.
4. Configurar WAPF en el producto.
5. Probar pedido real y revisar meta plazas_reservadas del pedido.

## 2) Datos de escuela que importan

- slug: identificador unico de escuela (util para migracion/gestion).
- producto_id: enlace real escuela <-> producto; sin esto, los filtros de disponibilidad y la reserva pueden ser ambiguos.

Nota: evitar repetir el mismo producto_id en varias escuelas.

## 3) Configuracion WAPF minima obligatoria

### Campo Semanas

- Tipo: checkboxes.
- Label: Semanas.
- Debe existir y contener la seleccion de semanas.

### Campo Horario de cada semana

- Tipo: radio.
- Label: Horario del [texto de semana] o Horari del [texto de semana].
- Opciones esperadas por codigo:
	- 9:00h a 14:30h  -> tipo manana
	- 9:00h a 17:00h  -> tipo completo

## 4) Errores detectados hoy (causa real)

### Error A: typo en label horario

Caso detectado: "Hoario del ..." (sin "r").

Impacto: no se detecta el campo de horario y no se genera bien plazas_reservadas.

### Error B: formato de horas distinto

Caso detectado: "de 9:00 a 14:30" (sin h).

Impacto: no se reconoce manana/completo porque el parser espera "9:00h a 14:30h" o "9:00h a 17:00h".

### Error C: semana escrita con comas de dias

Caso detectado: "22,23,25,26 de junio".

Problema historico: el codigo separaba siempre por comas y rompia esa semana en 4 claves falsas.

## 5) Cambios de codigo aplicados hoy

### Soporte checkout Store API (bloques)

Se anadio el hook:

- woocommerce_store_api_checkout_order_processed

Esto evita que falle la reserva cuando el pedido se crea via store-api.

### Reserva desde line_items del pedido

La funcion reservar_plazas_campamento ahora lee _wapf_meta del pedido (line item), no del carrito.

Resultado: se genera plazas_reservadas tambien en checkout por bloques.

### Parser robusto de semanas con comas

Se agrego parser para que:

- "22,23,25,26 de junio" se trate como UNA sola semana.
- Se mantenga el comportamiento previo para listas reales separadas por comas.

Se aplico en:

- reserva de plazas (functions.php)
- obtener_info_cart (utilidades.php)
- get_semanas_con_beca (utilidades.php)

## 6) Checklist de verificacion rapida

1. Crear pedido de prueba con semana y horario.
2. Confirmar en el pedido meta plazas_reservadas con:
	 - clave de semana correcta (sin troceo raro)
	 - horario = manana o completo
	 - escuela_id y product_id informados
3. Verificar en SportyKidsCamp > Horarios y Stock que descuenta en la fila correcta.
4. Cancelar/reembolsar pedido de prueba y validar reposicion.

## 7) Recomendaciones operativas

- Mantener nomenclatura de semanas consistente entre BD y WAPF.
- Evitar typos en labels (Horario del / Horari del).
- Mantener formato de horas con "h" para no romper el parser actual.
- Si se duplica un producto para otra escuela, revisar siempre producto_id en Escuelas antes de probar checkout.

