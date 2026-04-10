# Documentación: Creación de Producto desde Cero

## Introducción

Este documento describe el proceso completo para crear una nueva escuela/campamento, configurar sus semanas, crear un producto en WooCommerce y vincularlo correctamente con todos los campos necesarios (semanas y horarios).

---

## Paso 1: Crear Escuela

1. Accede a la sección **Escuelas** en el panel de administración del plugin.
2. Haz clic en el botón **Crear escuela**.
3. Se desplegará el formulario de creación con los siguientes campos:
   - Nombre de la escuela
   - Descripción
   - Contacto
   - **ID del producto de WooCommerce** (dejar vacío por ahora, lo asignaremos después)
4. Introduce los datos requeridos.
5. Haz clic en **Crear escuela**.
6. La nueva escuela aparecerá en la tabla de escuelas ubicada arriba.

> **Nota importante:** El ID del producto de WooCommerce se asignará más adelante una vez creado el producto. Por ahora puedes deixarlo en blanco.

---

## Paso 2: Crear Semanas

1. En la misma sección de **Escuelas**, desplázate hacia abajo.
2. Haz clic en el botón **Nueva semana**.
3. Se abrirá el formulario de creación de semana con los siguientes campos:
   - **Escuela**: En el select, elige la escuela que acabas de crear.
   - **Semana (ES)**: Introduce el nombre o rango de fechas (ej: *9 al 13 de abril*). Recomendación: sigue el formato "DD al DD de mes".
   - **Horario Mañana**: Introduce el rango horario (ej: *8:00h a 14:00h*).
   - **Plazas Mañana**: Número de plazas disponibles.
   - **Horario Completo**: Introduce el rango horario (ej: *8:00h a 17:00h*).
   - **Plazas Completo**: Número de plazas disponibles.

4. Una vez completo el formulario, haz clic en **Crear semana**.
5. Si tienes el filtro de escuela aplicado, la nueva semana aparecerá en la tabla.
6. **Repite este proceso tantas veces como sea necesario** hasta tener todas las semanas que deseas poner a la venta.

> **Recomendación:** Sigue los formatos sugeridos en cada campo para asegurar compatibilidad posterior con los campos dinámicos del producto.

---

## Paso 3: Crear Producto en WooCommerce y Vincularlo

1. Accede a la sección **Productos** de WooCommerce.
2. Haz clic en **Añadir nuevo producto**.
3. Introduce el nombre del producto (ej: nombre de la escuela).
4. Rellena otros detalles básicos del producto según sea necesario.
5. Haz clic en **Publicar** para crear el producto.
6. Ahora ve a **Todos los productos** para localizar el producto que acabas de crear.
7. Pasa el ratón sobre el nombre del producto. Verás que aparece su **ID de producto**.
8. **Copia el ID del producto**.

### Vincular el Producto a la Escuela

1. Ve a la sección **Escuelas** del plugin.
2. Haz clic en **Editar** para la escuela que creaste en el Paso 1.
3. Localiza el campo **Producto WooCommerce (ID)**.
4. Pega el ID que copiaste.
5. Haz clic en **Actualizar escuela**.

---

## Paso 4: Configurar Campos de Entrada en el Producto (Input Fields)

### 4.1. Crear Campo de Semanas (Casillas de Verificación)

1. Ve a **Todos los productos** en WooCommerce.
2. **Edita** el producto que creaste.
3. Desplázate hacia abajo hasta encontrar la sección **Datos del producto**.
4. Busca la subsección **Product input fields** (Campos de entrada del producto).
5. Haz clic en el botón azul **+ input field**.
6. En el modal que aparece, selecciona **Casillas de verificación**.
7. Rellena los siguientes campos:
   - **Etiqueta**: `Semanas` (tal cual)
   - **Opciones**: Añade una opción por cada semana que creaste. En la etiqueta de opción escribe exactamente como la pusiste en la sección de escuelas (ej: *9 al 13 de abril*).
   - **Ajustar precio**: Deja en "El precio no cambia".
   - **Cantidad del precio**: Puedes poner cualquier valor, por ejemplo 100 (no se usará).

### 4.2. Crear Campos de Horarios (Botones de Radio)

Para cada semana que creaste, deberás crear un campo de botones de radio:

1. Haz clic nuevamente en el botón azul **+ input field**.
2. Selecciona **Botones de radio**.
3. Rellena los siguientes campos:
   - **Etiqueta**: `Horario del [nombre_semana]` — **Es indispensable comenzar con la palabra "Horario del"** (ej: *Horario del 9 al 13 de abril*).
   - **Opciones**: Añade dos opciones:
     - **Primera opción (Horario Mañana)**:
       - Etiqueta de opción: El horario de mañana que asignaste en la semana (ej: *8:00h a 14:00h*)
       - Ajustar precio: **Tarifa plana**
       - Cantidad del precio: Introduce el precio para este horario
     - **Segunda opción (Horario Completo)**:
       - Etiqueta de opción: El horario completo que asignaste en la semana (ej: *8:00h a 17:00h*)
       - Ajustar precio: **Tarifa plana**
       - Cantidad del precio: Introduce el precio para este horario

4. Desplázate más abajo y busca **Conditional logic**.
5. Haz clic en **or add new rule group**.
6. En el primer select, elige **Semanas**.
7. En el segundo select, elige **es igual a**.
8. En el tercer select, elige la semana correspondiente (ej: *9 al 13 de abril*).
9. Haz clic fuera del modal para cerrar.

### 4.3. Repetir para Todas las Semanas

Repite los pasos de la sección 4.2 para cada semana que tengas, creando un campo de botones de radio con su lógica condicional correspondiente.

### 4.4. Guardar Cambios

Una vez hayas añadido todos los campos de semanas y horarios:
1. Desplázate al principio del formulario del producto.
2. Haz clic en **Actualizar** para guardar todos los cambios.
3. ¡Tu tienda está configurada y lista para usar!

---

## Resumen del Flujo

```
Escuela → Semanas (asociadas a la escuela)
          ↓
      Producto WooCommerce
          ↓
      Vincular producto a escuela (ID)
          ↓
      Configurar campos dinámicos en producto:
      - Casillas "Semanas"
      - Para cada semana: Botones de radio "Horario del [semana]"
      - Cada botón con lógica condicional
```

---

## Notas Importantes

- **Nombres consistentes**: Asegúrate de usar exactamente los mismos nombres de semana en todos los pasos (Escuelas → Input Fields de producto).
- **Formato de horarios**: Mantén un formato consistente (ej: `HH:00h a HH:00h`).
- **Etiqueta de horarios**: Los campos de radio **deben comenzar con "Horario del"** para que funcione correctamente con la lógica condicionaly las validaciones del plugin.
- **Lógica condicional**: Sin la lógica condicional, los campos de horario será verán en todos los casos. La regla "Semanas = [semana específica]" asegura que cada horario se muestre solo cuando se selecciona su semana correspondiente.

---

## Troubleshooting

| Problema | Solución |
|----------|----------|
| Las semanas no aparecen en el filtro | Verifica que la escuela esté creada y vinculada a las semanas |
| Los horarios no se muestran en el carrito | Revisa que la lógica condicional esté correctamente configurada |
| El producto no está vinculado a la escuela | Confirma que el ID del producto en la escuela es correcto |
| Los precios no se aplican | Asegúrate de seleccionar "Tarifa plana" en cada opción de horario |

