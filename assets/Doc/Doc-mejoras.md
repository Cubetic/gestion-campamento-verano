# Documentación Técnica – Plugin `gestion-campamento-verano`

> Autor del análisis: GitHub Copilot  
> Fecha: 8 de abril de 2026  
> Plugin: `gestion-campamento-verano` / Marca pública: **SportyKidsCamp**

---

## 1. Descripción General

El plugin `gestion-campamento-verano` es un plugin WordPress a medida que gestiona las inscripciones de una escuela de verano (actualmente **una sola escuela**). Se integra con **WooCommerce** para procesar los pedidos y con **Polylang** para el soporte bilingüe castellano/catalán.

Funcionalidades principales:

- Formulario de checkout personalizado (datos del alumno, tutor, autorizaciones).
- Control de plazas por semana y por horario (mañana / completo) mediante tablas propias en la base de datos.
- Panel de administración con submenús: Inicio, Horarios y Stock, Carnets PDF, Diplomas PDF, Pagos Fraccionados.
- Descuentos por hermano y becas (IDALU).
- Integración con Redsys para pagos fraccionados.
- Exportación de datos a CSV.
- Generación de carnets y diplomas en PDF (FPDF).

---

## 2. Estructura de Archivos

```
gestion-campamento-verano/
├── reservas.php                 ← Archivo principal del plugin (menús, activación, enqueue)
├── includes/
│   ├── functions.php            ← Checkout, validación, reserva/descuento de plazas, formulario bilingüe
│   ├── ajax.php                 ← Endpoints AJAX (stock, descuentos, hermano, Redsys)
│   ├── utilidades.php           ← Helpers: carrito, becas, descuentos, depuración
│   ├── descuentos.php           ← Lógica de descuentos por hermano/DNI
│   ├── email.php                ← Emails de confirmación
│   ├── cron.php                 ← Pagos fraccionados vía WP-Cron
│   ├── redsys.php               ← Integración Redsys
│   └── admin/
│       ├── dashboard.php        ← Lista de pedidos, edición manual, exportación CSV
│       ├── dashboard_stock.php  ← Gestión de plazas por semana y horario ← PANTALLA DE LA IMAGEN
│       ├── dashboard_carnet.php ← Generación de carnets PDF
│       └── dashboard_diplomas.php ← Generación de diplomas PDF
└── assets/
    ├── js/
    │   ├── horarios.js          ← Muestra disponibilidad en tiempo real en la ficha de producto
    │   ├── sportkidscamp.js     ← Lógica del checkout (hermano, segundo tutor, etc.)
    │   └── becas.js             ← Lógica de becas en el carrito
    ├── css/
    │   ├── sportkidscamp.css    ← Estilos frontend
    │   └── admin.css            ← Estilos backend
    └── Doc/
        └── Doc-mejoras.md       ← Este archivo
```

---

## 3. Base de Datos

El plugin crea **2 tablas propias** al activarse:

### `wp_semanas_campamento`

| Campo           | Tipo         | Descripción                                |
|-----------------|--------------|--------------------------------------------|
| `id`            | INT PK AI    | Identificador único de la semana           |
| `semana`        | VARCHAR(50)  | Nombre de la semana, ej: "25 al 27 de junio" |
| `plazas_totales`| INT          | Total de plazas (mañana + completo)        |

### `wp_horarios_semana`

| Campo              | Tipo                    | Descripción                           |
|--------------------|-------------------------|---------------------------------------|
| `id`               | INT PK AI               | Identificador único                   |
| `semana_id`        | INT FK                  | Referencia a `wp_semanas_campamento`  |
| `tipo_horario`     | ENUM('mañana','completo') | Tipo de horario                     |
| `plazas`           | INT                     | Plazas disponibles restantes          |
| `plazas_reservadas`| INT                     | Plazas ya ocupadas                    |

**Datos iniciales hardcodeados** al activar el plugin (en `reservas.php`):

```php
$semanas = [
    '25 al 27 de junio',
    '31 junio al 4 de julio',
    '7 al 11 de julio',
    '14 al 18 de julio',
    '21 al 25 de julio',
    '28 de julio al 1 de agosto'
];
// 120 plazas totales por semana: 60 mañana + 60 completo
```

---

## 4. Flujo de una Reserva

1. El cliente entra a la ficha de producto en WooCommerce.
2. `horarios.js` hace una llamada AJAX a `wp_ajax_get_horarios_stock` → `my_get_horarios_stock()` para mostrar disponibilidad en tiempo real.
3. El cliente selecciona semanas, horario y opciones (beca, acogida) mediante campos WAPF (WooCommerce Advanced Product Fields).
4. Al hacer checkout, `mostrar_campos_formulario()` inyecta el formulario con datos del alumno y tutor (bilingüe).
5. Al crear el pedido (`woocommerce_checkout_order_created`) → `reservar_plazas_campamento()` guarda el detalle de las semanas en el meta `plazas_reservadas` del pedido.
6. Al cambiar el pedido a `processing` → `descontar_plazas()` descuenta las plazas en `wp_horarios_semana`.
7. Si el pedido se cancela o reembolsa → `reponer_plazas_en_pedido_cancelado()` devuelve las plazas.

---

## 5. Soporte Bilingüe (Castellano / Catalán)

El plugin utiliza **Polylang** para detectar el idioma activo:

```php
$current_lang = function_exists('pll_current_language') ? pll_current_language() : 'es';
$is_catalan = ($current_lang === 'ca');
```

Todos los literales del formulario de checkout están duplicados en un array `$translations` con clave `$is_catalan ? 'texto_ca' : 'texto_es'`.

### Problema actual: diccionario de semanas hardcodeado

Las funciones `descontar_plazas()`, `reponer_plazas_en_pedido_cancelado()` y la edición de pedidos en `dashboard.php` contienen un diccionario estático que convierte el nombre de la semana (en castellano o catalán) al ID en la base de datos:

```php
$idSemana = array(
    '25 al 27 de juny'          => 1,  // catalán
    '25 al 27 de junio'         => 1,  // castellano
    '30 de juny al 4 de juliol' => 2,
    '30 de junio al 4 de julio' => 2,
    // ... etc, hasta semana 6
);
```

Este diccionario está **duplicado en 4 lugares distintos del código** y referencia las semanas por su nombre textual. Cada año hay que actualizarlo manualmente en todos esos puntos.

El JS (`horarios.js`) también tiene su propio diccionario de traducción catalán→castellano de nombres de meses para normalizar los nombres de semana antes de compararlos con los datos de la BD.

---

## 6. Problema Actual: Semanas y Escuelas Hardcodeadas

### Problema 1 – Las semanas no son configurables

Las semanas se insertan como datos fijos en la activación del plugin. Si cambia el año o el calendario, hay que:
1. Editar `reservas.php` (función `crear_e_inicializar_tablas_campamento`).
2. Actualizar el diccionario `$idSemana` en 4 archivos PHP diferentes.
3. Actualizar las traducciones catalán↔castellano en `horarios.js`.

### Problema 2 – Solo existe una escuela

La tabla `wp_semanas_campamento` no tiene ningún campo que identifique a qué escuela pertenece cada semana. Todo el plugin asume que existe **una sola escuela**. Si se añade una segunda escuela, actualmente no hay forma de distinguir sus semanas en la base de datos.

### Problema 3 – Los horarios son fijos (ENUM)

`tipo_horario` es un `ENUM('mañana', 'completo')`. Si la nueva escuela tiene horarios distintos (p.ej. "tarde" o "horario extendido"), no es posible añadirlos sin modificar la estructura de la base de datos.

---

## 7. Plan de Mejora: Gestión Dinámica de Escuelas, Semanas y Horarios

### Objetivo

Permitir que desde el panel de administración se puedan:
- Crear varias **escuelas** (con nombre, descripción y producto WooCommerce asociado).
- Para cada escuela, definir **semanas** con fechas en castellano y catalán.
- Para cada semana, definir **horarios** con nombre configurable (no hardcodeado), plazas y precio si aplica.
- Sin romper el funcionamiento actual de la escuela existente (migración conservadora).

---

### 7.1 Cambios en Base de Datos

#### Nueva tabla: `wp_skc_escuelas`

```sql
CREATE TABLE wp_skc_escuelas (
    id            INT NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(100) NOT NULL,
    nombre_ca     VARCHAR(100) NOT NULL,     -- nombre en catalán
    slug          VARCHAR(100) NOT NULL,     -- identificador único legible
    producto_id   BIGINT DEFAULT NULL,       -- ID del producto WooCommerce asociado
    activa        TINYINT(1) DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
);
```

#### Modificar: `wp_semanas_campamento` → añadir columna `escuela_id` y traducciones

```sql
ALTER TABLE wp_semanas_campamento
    ADD COLUMN escuela_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD COLUMN semana_ca VARCHAR(100) NOT NULL DEFAULT '' AFTER semana,
    ADD FOREIGN KEY (escuela_id) REFERENCES wp_skc_escuelas(id) ON DELETE CASCADE;
```

> **Compatibilidad hacia atrás**: las semanas existentes quedan con `escuela_id = 1`, que corresponderá a la escuela actual ("Duran i Bas").

#### Modificar: `wp_horarios_semana` → `tipo_horario` pasa de ENUM a VARCHAR

```sql
ALTER TABLE wp_horarios_semana
    MODIFY COLUMN tipo_horario VARCHAR(50) NOT NULL,
    ADD COLUMN tipo_horario_ca VARCHAR(50) NOT NULL DEFAULT '' AFTER tipo_horario;
```

> Esto elimina la restricción `ENUM` y permite definir cualquier nombre de horario. Los datos existentes ('mañana', 'completo') siguen siendo válidos.

---

### 7.2 Nueva UI de Administración: Gestión de Escuelas

Se añadirá un nuevo submenú en el panel **SportyKidsCamp → Escuelas** con las siguientes operaciones:

1. **Listar escuelas** existentes con sus semanas y horarios (similar a la tabla actual de "Horarios y Stock").
2. **Crear escuela**: nombre ES/CA, slug, producto WooCommerce vinculado.
3. **Añadir semanas** a una escuela: nombre ES/CA, plazas totales.
4. **Añadir horarios** a una semana: nombre ES/CA del horario, plazas disponibles.
5. **Editar/eliminar** semanas y horarios.

La tabla actual de **Horarios y Stock** (`dashboard_stock.php`) se refactorizará para:
- Mostrar un selector de escuela en la parte superior.
- Renderizar la tabla de semanas/horarios para la escuela seleccionada.
- Seguir mostrando los datos actuales por defecto (escuela 1).

---

### 7.3 Eliminar los Diccionarios Hardcodeados (`$idSemana`)

En lugar de usar un diccionario nombre→ID, se consultará la base de datos directamente por el nombre normalizado de la semana.

**Función centralizada sustituta:**

```php
function skc_get_semana_id_por_nombre(string $nombre_semana): ?int {
    global $wpdb;
    $tabla = $wpdb->prefix . 'semanas_campamento';

    // Buscar por nombre en castellano o en catalán
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $tabla WHERE semana = %s OR semana_ca = %s LIMIT 1",
        $nombre_semana,
        $nombre_semana
    ));

    return $id ? (int) $id : null;
}
```

Esta función sustituirá el array `$idSemana` en los 4 archivos donde está duplicado:
- `includes/functions.php` → `descontar_plazas()`
- `includes/functions.php` → `reponer_plazas_en_pedido_cancelado()`
- `includes/admin/dashboard.php` → edición de pedido (×2 bloques)

---

### 7.4 Soporte Bilingüe Dinámico en horarios.js

Actualmente el diccionario catalán↔castellano de meses está hardcodeado en `horarios.js`. Se pasará desde PHP via `wp_localize_script`:

```php
wp_localize_script('mi-script-stock', 'misDatosAjax', [
    'ajaxUrl'      => admin_url('admin-ajax.php'),
    'diccionario'  => [
        'juny'    => 'junio',
        'juliol'  => 'julio',
        'agost'   => 'agosto',
        // ... resto de meses
    ]
]);
```

Esto facilita su mantenimiento y posibilita ampliar el diccionario sin tocar JS.

---

### 7.5 Endpoint AJAX: `get_horarios_stock` con filtro por escuela

El endpoint actual devuelve todas las semanas. Se añadirá un parámetro opcional `escuela_id` (o `producto_id`) para filtrar:

```php
function my_get_horarios_stock() {
    global $wpdb;
    $escuela_id = isset($_POST['escuela_id']) ? intval($_POST['escuela_id']) : null;
    $producto_id = isset($_POST['producto_id']) ? intval($_POST['producto_id']) : null;

    // Si se pasa producto_id, resolver el escuela_id desde la tabla de escuelas
    if ($producto_id && !$escuela_id) {
        $escuela_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}skc_escuelas WHERE producto_id = %d",
            $producto_id
        ));
    }

    $where = $escuela_id ? $wpdb->prepare("WHERE s.escuela_id = %d", $escuela_id) : '';
    // ... resto de la query
}
```

`horarios.js` pasará el `product_id` (disponible via `wc_add_to_cart_params` o localize) en la llamada AJAX, de modo que cada producto solo vea sus propias semanas.

---

## 8. Plan de Implementación Paso a Paso

### Fase 0 – Preparación (sin romper nada)
- [ ] **0.1** Hacer backup completo de la base de datos antes de cualquier cambio.
- [ ] **0.2** Crear una rama Git `feature/multi-escuela` para desarrollar en paralelo.
- [ ] **0.3** Documentar el estado actual de las tablas y datos reales de producción.

### Fase 1 – Migración de Base de Datos
- [ ] **1.1** Crear tabla `wp_skc_escuelas` e insertar la escuela actual como `id=1`.
- [ ] **1.2** Añadir columna `escuela_id` a `wp_semanas_campamento` (DEFAULT 1 para no romper datos).
- [ ] **1.3** Añadir columna `semana_ca` a `wp_semanas_campamento` y rellenar con las traducciones actuales al catalán.
- [ ] **1.4** Añadir columna `tipo_horario_ca` a `wp_horarios_semana` y rellenar ('mañana'→'matí', 'completo'→'complet').
- [ ] **1.5** Cambiar `tipo_horario` de ENUM a VARCHAR(50) (compatible hacia atrás).

> ⚠️ **Riesgo 1.5**: Si hay restricciones de ENUM en producción, el ALTER puede fallar si hay valores no contemplados. Verificar antes con un SELECT DISTINCT.

### Fase 2 – Función centralizada de resolución de semana_id
- [ ] **2.1** Crear `skc_get_semana_id_por_nombre()` en un nuevo archivo `includes/semanas.php`.
- [ ] **2.2** Reemplazar el array `$idSemana` en `descontar_plazas()`.
- [ ] **2.3** Reemplazar el array `$idSemana` en `reponer_plazas_en_pedido_cancelado()`.
- [ ] **2.4** Reemplazar los dos arrays `$idSemana` en `dashboard.php` (edición de pedido).
- [ ] **2.5** Probar con pedidos reales: crear, cancelar y editar semana de un pedido.

> ⚠️ **Riesgo 2.x**: Los pedidos existentes tienen guardado en `plazas_reservadas` el nombre en castellano antiguo. Si los nombres de semana en la BD cambian, la búsqueda fallará. Solución: mantener los nombres existentes exactamente y añadir las traducciones en columnas adicionales, no modificar `semana`.

### Fase 3 – UI de Gestión de Escuelas en el Admin
- [ ] **3.1** Crear `includes/admin/dashboard_escuelas.php` con CRUD de escuelas.
- [ ] **3.2** Registrar el submenú y la función de callback en `reservas.php`.
- [ ] **3.3** Refactorizar `dashboard_stock.php` para incluir el selector de escuela.
- [ ] **3.4** Permitir añadir/editar semanas y horarios desde la interfaz (no solo editarles las plazas).

### Fase 4 – Endpoint AJAX con filtro por escuela
- [ ] **4.1** Modificar `my_get_horarios_stock()` en `ajax.php` para aceptar `escuela_id`.
- [ ] **4.2** Pasar `producto_id` desde `horarios.js` en la llamada AJAX.
- [ ] **4.3** Verificar que la disponibilidad de producto A no muestra semanas de producto B.

### Fase 5 – Checkout con soporte multi-escuela
- [ ] **5.1** En `obtener_info_cart()` (`utilidades.php`), identificar a qué escuela pertenece cada ítem del carrito usando el `product_id`.
- [ ] **5.2** En `reservar_plazas_campamento()`, guardar también el `escuela_id` junto al detalle de semana.
- [ ] **5.3** Verificar que el formulario de checkout funcione correctamente cuando hay ítems de distintas escuelas en el mismo carrito (decidir: ¿se permite o se restringe?).

### Fase 6 – Diccionario de meses dinámico en JS
- [ ] **6.1** Mover el diccionario de `horarios.js` a `wp_localize_script` en `reservas.php`.
- [ ] **6.2** Actualizar `horarios.js` para leer el diccionario desde la variable localizada.

---

## 9. Riesgos y Consideraciones

| Riesgo | Impacto | Mitigación |
|--------|---------|------------|
| Pedidos existentes guardan nombres de semana como clave en `plazas_reservadas` | Alto | No renombrar semanas existentes; solo añadir columnas de traducción |
| El diccionario `$idSemana` en 4 archivos puede quedar desincronizado durante la transición | Alto | Migrar Fase 2 completa antes de añadir nuevas semanas |
| `ALTER TABLE` en producción con datos puede ser lento o fallido | Medio | Ejecutar con `ALTER TABLE ... ALGORITHM=INPLACE, LOCK=NONE` si el motor lo soporta (InnoDB ≥ MySQL 5.6) |
| Dos escuelas en el mismo carrito pueden generar conflictos en el checkout | Medio | Validar en `woocommerce_add_to_cart` que los productos del carrito pertenezcan a la misma escuela, o diseñar el formulario para soportarlo |
| El ENUM en `tipo_horario` puede no permitir el ALTER si hay datos inválidos | Bajo | Verificar con `SELECT DISTINCT tipo_horario FROM wp_horarios_semana` antes del ALTER |
| El producto WooCommerce tiene campos WAPF (semanas, beca) que también son hardcodeados en el producto | Alto | Evaluar si los campos WAPF de semanas del producto se pueden generar dinámicamente desde la BD (requiere WAPF Pro API o uso de opciones guardadas) |

---

## 10. Notas sobre el Producto WooCommerce (WAPF)

El plugin usa **WooCommerce Advanced Product Fields (WAPF)** para mostrar las checkboxes de semanas en la ficha de producto. Actualmente estas semanas están configuradas **directamente en el producto** dentro de WooCommerce (como opciones del campo WAPF), no vienen de la base de datos del plugin.

Esto significa que:
- Las semanas visibles al cliente NO se leen automáticamente de `wp_semanas_campamento`.
- Hay que actualizar las semanas tanto en la BD del plugin (para el control de plazas) como en la configuración del producto WAPF (para el formulario de compra).
- Al crear una nueva escuela, habrá que crear un nuevo producto WooCommerce con sus propios campos WAPF configurados.

**Recomendación**: En la UI de gestión de escuelas (Fase 3), mostrar un aviso recordatorio de que las semanas del producto WAPF deben coincidir con las semanas registradas en la BD del plugin.

---

## 11. Estado Actual de la Deuda Técnica

| Elemento | Estado | Prioridad |
|----------|--------|-----------|
| Diccionario `$idSemana` duplicado ×4 | ❌ Deuda activa | Alta |
| Semanas hardcodeadas en activación del plugin | ❌ Deuda activa | Alta |
| `tipo_horario` como ENUM no extensible | ❌ Deuda activa | Media |
| Soporte para múltiples escuelas | ❌ No implementado | Alta |
| Diccionario meses catalán→castellano hardcodeado en JS | ⚠️ Mejorable | Baja |
| Uso de `wp_` prefix directo en algunas queries | ⚠️ Mejorable | Baja |
| WAPF de semanas desacoplado de la BD del plugin | ⚠️ Limitación conocida | Media |
