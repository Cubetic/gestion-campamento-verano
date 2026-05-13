# Mapeo CSV dinamico por escuela, semana y horario

## 1) Objetivo

Adaptar la exportacion CSV para que deje de depender de semanas fijas (S1..S6) y horarios fijos, y use datos dinamicos segun:

- la escuela real del pedido,
- la semana real reservada,
- el horario real configurado para esa semana.

La idea es mantener lo que ya funciona y migrar sin romper integraciones existentes.

---

## 2) Estado actual detectado (codigo real)

### 2.1 Punto de entrada de exportacion

- La exportacion se dispara en includes/admin/dashboard.php cuando llega exportar_csv=true.
- La funcion principal es exportar_csv_funcion() en ese mismo archivo.

### 2.2 Problema principal actual

En exportar_csv_funcion() hay una estructura fija:

- Cabeceras fijas para columnas S1..S6.
- Mapeo manual de texto de semana a S1..S6 ($semanas_mapeo).
- Matriz fija de semanas y opciones:
  - S1..S6
  - 9h-17h
  - 9h-14:30h
  - 9h-17h-beca
  - acoll

Esto hace que el CSV no escale bien cuando:

- se crea una escuela nueva,
- se crean semanas nuevas fuera del set historico,
- se cambian textos de semana,
- se cambian o amplian horarios.

### 2.3 Modelo de datos actual (ya preparado para dinamico)

El plugin ya guarda datos con contexto dinamico:

- Tabla semanas: wp_semanas_campamento
  - incluye escuela_id, semana, semana_ca
- Tabla horarios: wp_horarios_semana
  - incluye semana_id, tipo_horario (varchar), nombre_horario
- Meta de pedido plazas_reservadas
  - por cada semana guardada incluye al menos:
	 - horario
	 - acogida
	 - beca
	 - escuela_id
	 - (en algunos flujos tambien product_id)

Conclusiones:

- El core de reserva/stock ya va con escuela_id + semana + tipo_horario.
- El cuello de botella actual esta en la exportacion CSV, no en la reserva.

---

## 3) Mapeo funcional actual de extremo a extremo

1. Front/checkout solicita disponibilidad por escuela/producto (AJAX get_horarios_stock).
2. El pedido se crea y reservar_plazas_campamento() guarda plazas_reservadas en meta.
3. plazas_reservadas se usa en:
	- descuento de plazas,
	- devolucion de plazas,
	- edicion manual en dashboard,
	- exportacion CSV.
4. En CSV, la traduccion final no usa el catalogo dinamico de BD, sino mapeo fijo S1..S6.

Punto exacto de desacople:

- La escritura de pedido es dinamica.
- La lectura para CSV sigue fija.

---

## 4) Propuesta de mapeo CSV dinamico

## 4.1 Principio

La fuente de verdad del CSV debe ser plazas_reservadas del pedido + catalogo real de semanas/horarios de su escuela.

No debe depender de strings hardcodeados de semanas historicas.

## 4.2 Estructura recomendada de salida

Para no romper reporting actual, plantear 2 capas:

- Capa A (legacy): mantener columnas actuales S1..S6 mientras conviva el sistema antiguo.
- Capa B (nueva): agregar columnas dinamicas por escuela/semana/horario.

Ejemplo de naming dinamico de columnas:

- Escuela [slug] | Semana [label] | Horario [tipo_horario] | Reserva
- Escuela [slug] | Semana [label] | Beca
- Escuela [slug] | Semana [label] | Acogida

Si se quiere un CSV mas limpio para BI, se puede ofrecer ademas un segundo formato:

- 1 fila por reserva (pedido x semana), en lugar de 1 fila por pedido.

## 4.3 Resolucion de datos por pedido

Para cada pedido:

1. Leer plazas_reservadas.
2. Para cada entrada de semana:
	- resolver escuela_id (si no existe, fallback por product_id o por consulta de semana),
	- resolver semana_id con helper existente,
	- resolver tipo_horario real,
	- mapear beca/acogida,
	- volcar en columnas dinamicas.
3. Si un pedido no encaja en catalogo actual (dato historico), registrar warning y mantener fallback legacy.

---

## 5) Problemas que podemos encontrar

1. Compatibilidad con informes actuales
- Si alguien consume columnas S1..S6 de forma estricta, romperian al quitarlas.

2. Pedidos historicos con etiquetas antiguas
- Hay variaciones de texto de semana (ejemplo 30/31 junio).
- Ya existen alias en helpers de semanas, pero en CSV fijo aun hay rigidez.

3. Pedidos sin escuela_id en meta
- En pedidos viejos podria faltar escuela_id en plazas_reservadas.
- Hay que aplicar fallback seguro para no perder datos.

4. Cambios de nombre de horarios
- nombre_horario visible puede cambiar; tipo_horario es la clave funcional.
- El CSV debe basarse en tipo_horario y mostrar nombre_horario solo como etiqueta.

5. Ambiguedades de resolucion
- Si una misma etiqueta de semana existe en varias escuelas, sin escuela_id el mapeo puede ser ambiguo.

6. Rendimiento
- Construir cabeceras dinamicas con todos los pedidos puede ser costoso.
- Conviene pre-cargar catalogos por escuela y cachear durante la exportacion.

---

## 6) Pasos recomendados para migrar sin romper nada

## Fase 0 - Congelar comportamiento actual

1. Guardar muestra de CSV actual (golden file) con datos reales.
2. Documentar consumidor actual del CSV (quien lo importa y que columnas exige).

## Fase 1 - Refactor interno sin cambiar salida

1. Extraer logica de CSV a funciones auxiliares (sin tocar formato final).
2. Encapsular:
	- builder de cabecera,
	- mapper de pedido,
	- writer de fila.
3. Confirmar que el CSV generado es identico al actual.

## Fase 2 - Introducir mapeo dinamico en paralelo

1. Crear nuevo modo de exportacion (ejemplo exportar_csv_v2=true).
2. Construir cabecera dinamica a partir de:
	- semanas/horarios activos por escuela,
	- o union de lo realmente reservado en los pedidos filtrados.
3. Mantener simultaneamente export legacy y export dinamico.

## Fase 3 - Compatibilidad controlada

1. En modo dinamico, incluir tambien columnas legacy opcionales al final o al inicio.
2. Añadir logs de diagnostico para pedidos no resolubles.
3. Validar contra casos mixtos:
	- escuelas nuevas,
	- semanas nuevas,
	- pedidos historicos,
	- pedidos editados manualmente.

## Fase 4 - Corte definitivo

1. Cuando los consumidores externos confirmen, dejar dinamico como default.
2. Mantener feature flag para volver temporalmente al legacy.

---

## 7) Checklist tecnico minimo antes de desplegar

1. Verificar permisos y nonce de exportacion.
2. Proteger salida CSV (sin salida previa, BOM UTF-8, flush correcto).
3. Garantizar orden estable de columnas dinamicas (sorting determinista).
4. Añadir fallback para pedidos sin escuela_id.
5. Añadir pruebas manuales con matriz de escenarios:
	- Escuela A con semanas historicas.
	- Escuela B con semanas nuevas.
	- Horario renombrado en una semana.
	- Pedido con beca y acogida.
6. Comparar totales de reservas por semana/horario entre:
	- dashboard stock,
	- meta plazas_reservadas,
	- CSV exportado.

---

## 8) Recomendacion de implementacion concreta

Implementar primero un export CSV v2 sin tocar el boton actual:

- Boton actual: mantiene salida legacy.
- Boton nuevo: exporta dinamico.

Cuando v2 este validado en produccion con datos reales:

- cambiar el boton principal a v2,
- dejar legacy como backup temporal.

Con este enfoque evitamos regresiones y tenemos rollback inmediato.

---

## 9) Resumen ejecutivo

El plugin ya esta preparado en base de datos y en reservas para trabajar de forma dinamica por escuela/semana/horario. El unico punto que quedo fijo es la exportacion CSV de dashboard. La solucion recomendada es migrar la exportacion en fases, con modo v2 paralelo, fallback legacy y validacion contra pedidos historicos para evitar romper el flujo actual.

---

## 10) Control de cambios

### 2026-05-12 - Fase 1 aplicada (refactor interno sin cambio de salida)

Archivo modificado:

- includes/admin/dashboard.php

Cambios realizados:

1. Se extrajeron helpers legacy para separar responsabilidades sin alterar el CSV final:
	- skc_csv_get_cabeceras_legacy()
	- skc_csv_get_semanas_mapeo_legacy()
	- skc_csv_total_pendiente_legacy()
	- skc_csv_inicializar_datos_semana_legacy()
	- skc_csv_append_semanas_legacy()
	- skc_csv_construir_fila_legacy()
2. exportar_csv_funcion() ahora usa esos helpers, manteniendo:
	- mismas cabeceras,
	- mismo orden de columnas,
	- misma logica legacy S1..S6,
	- misma logica de beca/acogida/pendiente.
3. Se añadieron comentarios en el codigo para identificar explicitamente que estos bloques pertenecen a la Fase 1 (legacy) y facilitar el paso a Fase 2.

Resultado esperado de Fase 1:

- No cambia el formato del CSV.
- Mejora mantenibilidad y trazabilidad para introducir CSV dinamico en Fase 2 con menor riesgo.

### 2026-05-12 - Fase 2 aplicada (CSV dinamico en paralelo)

Archivo modificado:

- includes/admin/dashboard.php

Cambios realizados:

1. Se añadió una nueva via de exportacion en paralelo:
	- parametro GET exportar_csv_v2=true
	- funcion exportar_csv_funcion_v2()
2. Se añadió boton dedicado en dashboard:
	- Exportar CSV v2 (dinamico)
3. Se mantuvo intacto el flujo legacy:
	- exportar_csv=true sigue usando exportar_csv_funcion()
4. Se implementaron helpers de Fase 2 para columnas dinamicas por reservas reales:
	- skc_csv_get_cabeceras_base_comun()
	- skc_csv_get_pedidos_exportacion()
	- skc_csv_get_escuelas_lookup()
	- skc_csv_get_etiqueta_escuela()
	- skc_csv_build_columnas_dinamicas_v2()
	- skc_csv_append_semanas_dinamicas_v2()
	- skc_csv_construir_fila_v2()
5. Se introdujo un bloque base comun reutilizable entre legacy y v2:
	- skc_csv_construir_fila_base_comun()
	- legacy ahora reutiliza este bloque y luego añade su matriz fija S1..S6.

Formato de salida v2:

- Mantiene columnas base del CSV actual (datos del pedido/alumno/pago).
- Sustituye bloque fijo S1..S6 por columnas dinamicas detectadas en pedidos:
  - Reserva - Escuela: X | Semana: Y | Horario: Z
  - Beca - Escuela: X | Semana: Y
  - Acogida - Escuela: X | Semana: Y

Resultado esperado de Fase 2:

- Conviven dos exportaciones: legacy y dinamica.
- No se rompe la integracion existente.
- Permite validar en real la v2 antes de cambiar el flujo principal.
