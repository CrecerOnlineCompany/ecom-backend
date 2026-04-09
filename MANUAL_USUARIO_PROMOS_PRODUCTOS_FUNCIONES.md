# Manual de usuario - Promociones, Productos y Funciones

Fecha: 2026-04-08

Este manual resume como operar las secciones de Promociones, Productos y Funciones en el panel admin de CINEA.

## Acceso rapido

- Promociones: /admin/promotions
- Productos: /admin/products
- Funciones: /admin/screenings
- Crear orden manual (para probar promos y productos): /admin/orders/manual/create

## Promociones

### Objetivo
Las promociones aplican descuentos a items del carrito (entradas o productos) durante el checkout o en la creacion manual de ordenes.

### Crear o editar una promocion
1) Ir a Promociones y hacer clic en Crear.
2) Completar los campos principales.
3) Configurar el JSON de settings o usar el constructor de condiciones.
4) Guardar y verificar en el listado.

### Campos principales
- Codigo: opcional. Si se informa, el cliente puede ingresarlo en checkout o en orden manual.
- Nombre: obligatorio.
- Tipo: percentage, fixed_amount, bxgy.
- Activa: habilita o deshabilita la promo.
- Automatica: si esta activa, se evalua sin codigo.
- Acumulable: si esta inactiva, esta promo corta la evaluacion de las demas.
- Prioridad: menor numero = mayor prioridad.
- Inicio/Fin: ventana de vigencia.
- Limite de uso: opcional.
- Configuracion (JSON): define parametros del descuento.

### Tipos y settings (JSON)
1) percentage (porcentaje)
- Ejemplo:
  {"percentage":10,"target_item_type":"ticket_seat"}
- Campos:
  - percentage: numero 0-100.
  - target_item_type: ticket_seat, product, combo.
  - target_codes (opcional): lista de codigos de producto/combo.
  - max_discount (opcional): tope del descuento.

2) fixed_amount (monto fijo)
- Ejemplo:
  {"amount":500,"target_item_type":"ticket_seat"}
- Campos:
  - amount: monto a descontar.
  - target_item_type / target_codes: mismo criterio que percentage.

3) bxgy (2x1, 3x2, etc)
- Ejemplo 2x1 entradas:
  {"buy_qty":2,"pay_qty":1,"target_item_type":"ticket_seat"}
- Ejemplo 2x1 productos por codigo:
  {"buy_qty":2,"pay_qty":1,"target_item_type":"product","target_codes":["COMBO_2G_PG"]}
- Regla: se bonifican los items mas baratos dentro del target.

### Condiciones avanzadas
Puede definir condiciones dentro de settings.conditions o con el constructor.

Estructura base:
{
  "aggregator":"all",
  "conditions":[
    {"type":"cart_quantity","item_type":"ticket_seat","operator":">=","value":2}
  ]
}

Tipos de condicion:
- cart_quantity: cantidad de items en carrito (por tipo y opcionalmente por codigo).
- cart_subtotal: subtotal por tipo.
- context_value: compara valores del contexto (ej: screening_id).

Tambien puede usar grupos anidados con aggregator: all (AND) o any (OR).

### Restricciones por contexto
En settings se pueden limitar funciones/salas/cines:
- screening_ids: [1,2,3]
- room_ids: [10,11]
- cinema_ids: [5]

Si una promo no aplica al contexto, el sistema la descarta.

### Promocion automatica 2x1 por funcion
En el listado de Funciones hay acciones para:
- Asignar 2x1 automatico
- Quitar 2x1 automatico

Esto crea o desactiva una promo tipo bxgy vinculada a la funcion.

### Validacion rapida
Use la creacion de orden manual para probar:
- Seleccione funcion, asientos y productos.
- Ingrese promotion_code si la promo requiere codigo.
- Verifique el total y el resumen.

## Productos

### Objetivo
Los productos se usan en el checkout y en ordenes manuales como adicionales (candy bar).

### Catalogo y fuente de datos
- Si existe la tabla products, el sistema usa esos registros.
- Si no existe, usa el catalogo de config/concessions.php.

### Crear o editar producto
1) Ir a Productos y hacer clic en Crear.
2) Completar:
   - Codigo (se recomienda en MAYUSCULAS).
   - Nombre.
   - Imagen (opcional).
   - Tipo: product o combo.
   - Precio y moneda.
   - Activo.
   - Orden (sort_order).
3) Guardar.

### Reglas importantes
- El codigo es unico y se envia desde el checkout.
- El sistema normaliza codigo, tipo y moneda a mayusculas/minusculas.
- Solo se muestran productos activos.
- La imagen se guarda en la carpeta products.

### Uso en orden manual
En /admin/orders/manual/create:
- Se cargan los productos activos.
- Puede ajustar cantidades antes de crear la orden.

## Funciones (Screenings)

### Objetivo
Una funcion define una pelicula, sala, horario y precio base.

### Crear o editar funcion
1) Ir a Funciones y hacer clic en Crear.
2) Completar:
   - Pelicula.
   - Sala.
   - Inicio y fin (Y-m-d H:i:s).
   - Precio base.
   - Formato (2D, 3D, IMAX, 4DX).
   - Activo.
3) Guardar.

### Excluir asientos
En la edicion de la funcion:
- Campo "Asientos a excluir".
- Se crean reservas administrativas para impedir su venta.
- Solo aparecen asientos de la sala seleccionada.

### Acciones en listado
- Sync Seats: sincroniza inventario de asientos.
- Asignar/Quitar 2x1 automatico.
- Exportar a Excel/CSV.
- Importar funciones.
- Carga semanal (weekly screenings).

### Recomendaciones
- Verifique que la sala tenga asientos activos.
- Ajuste el precio base; los asientos usan price_modifier cuando aplique.
- Use la exclusion de asientos para bloquear filas o ubicaciones especiales.

## Checklist rapido
- Promos activas, con prioridad correcta y fechas vigentes.
- Productos activos, con codigo unico y precio correcto.
- Funciones con pelicula, sala, horarios y precio base correctos.
- Probar con orden manual antes de publicar una promo importante.

## Referencias tecnicas
- Promociones: app/Admin/Controllers/PromotionController.php, app/Services/PromotionEngineService.php
- Productos: app/Admin/Controllers/ProductController.php, app/Services/OrderItemPricingService.php
- Funciones: app/Admin/Controllers/ScreeningController.php
- Catalogo fallback: config/concessions.php
- Orden manual: resources/views/admin/orders/manual-create.blade.php
