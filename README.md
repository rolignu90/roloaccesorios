# ROLO Accesorios — CRM de Inventario y Ventas

Sistema interno para **ROLO Accesorios** (El Salvador): inventario FIFO, ventas con IVA, contra entrega / COD, consignación, costos y exportación de etiquetas de envío.

- **Producción:** [https://ventas.roloaccesorios.com](https://ventas.roloaccesorios.com)
- **Repo:** [github.com/rolignu90/roloaccesorios](https://github.com/rolignu90/roloaccesorios)
- **Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 · Laravel Sail (Docker) · Nginx en DigitalOcean

---

## Objetivo del sistema

Operar el día a día del negocio en un solo CRM:

1. Comprar / recibir stock por lotes (FIFO).
2. Vender (mostrador o contra entrega) con precios, IVA, descuentos y envío.
3. Ajustar pedidos COD antes del despacho (cliente, dirección, ítems).
4. Calcular **margen real** restando COGS FIFO y costos del courier (envío + comisión COD).
5. Exportar CSV de etiquetas para la empresa de envío.
6. Registrar consignaciones y gastos / márgenes.

Moneda: **USD**. IVA por defecto: **13%** (`SALES_VAT_RATE`).

---

## Acceso

- Login por **PIN** (`APP_PIN` en `.env`), no hay usuarios multi-rol todavía.
- Middleware `pin.auth` protege todo el panel.

---

## Módulos

### 1. Inventario

| Función | Descripción |
|--------|-------------|
| Dashboard | Resumen de stock, alertas de mínimo, compras |
| Productos | CRUD, duplicar, precios venta / mayoreo / promo, peso, envío gratis |
| Proveedores | CRUD y vínculo producto–proveedor con precio de compra |
| Entradas de stock | Recepción por lote FIFO; rectificar / anular entradas no vendidas |
| Historial de movimientos | Entradas, ventas, anulaciones, ajustes |
| Envío gratis | Catálogo de productos con `free_shipping` |

**FIFO:** cada entrada crea un lote (`inventory_lots`). Las ventas consumen el lote más antiguo primero (`FifoInventoryService`). Al anular venta o bajar cantidad se restaura stock.

### 2. Ventas

| Función | Descripción |
|--------|-------------|
| Clientes | Datos de entrega (depto/municipio SV), precios por tramos de cantidad |
| Vendedores | Prefijo de numeración (`M-`, `F-`, …) → `M-20260801-0001` |
| Empresas de envío | Costo de envío + comisión COD (fijo o % del total c/IVA) |
| Ventas | Crear, listar (infinite scroll), ver, anular, editar COD |
| Etiquetas CSV | Exportar día completo o selección de ventas con envío |

#### Crear venta

- Cliente existente o nuevo (código `CLI-####` automático).
- Líneas: producto, cantidad, precio c/IVA, descuentos por línea.
- Descuento global % y/o monto c/IVA.
- Flag **Lleva envío** (default sí). Monto cobrado al cliente (default $3) independiente del costo del courier.
- Empresa de envío obligatoria si hay envío; la primera activa queda preseleccionada.
- Si algún producto tiene envío gratis → envío cobrado = $0.
- Acciones: **Confirmar venta** o **Confirmar y nueva venta**.

#### Edición de ventas confirmadas (contra entrega)

Pensado para COD: el cliente paga al recibir y el pedido puede cambiar.

En la ficha de venta confirmada se puede:

- Editar **cliente / entrega** (nombre, teléfono, email, dirección, departamento, municipio, país, CP) → actualiza el cliente del CRM.
- **Agregar / quitar ítems** y cambiar cantidades → FIFO + totales + comisión COD.
- Ajustar **envío cobrado** y **notas**.
- Ventas **anuladas** solo lectura.

#### Márgenes

| Concepto | Fórmula resumida |
|----------|------------------|
| Margen producto s/IVA | Base gravada − COGS |
| Margen producto c/IVA | (Total − envío cliente) − COGS |
| Costo empresa | `carrier_shipping_cost` + `carrier_commission_amount` |
| Margen real s/IVA | Margen producto s/IVA + envío cliente − costo empresa |
| Margen real c/IVA | Total cobrado − COGS − costo empresa |

#### Exportación de etiquetas

CSV estilo `modelo_carga` con columnas: ORDEN, NOMBRE, TELEFONO, EMAIL, DIRECCION, MUNICIPIO, DEPARTAMENTO, PAIS, CODIGO POSTAL, DESCRIPCION, PESO, PRECIO, OBSERVACIONES.

- **DESCRIPCION:** `[últimos 4 del número] \| [prefijo] - [Cant] [Producto], …`
- **OBSERVACIONES:** solo las notas de la venta.
- Solo ventas **confirmadas con envío**.
- Listado con **infinite scroll** para marcar muchas y exportar la selección.

### 3. Consignación

- Entrega de mercadería a un cliente/punto sin venta definitiva.
- Pagos parciales y devoluciones.
- Dashboard de saldos / estado.
- Precios pueden resolver tramos del cliente.

### 4. Finanzas / Costos

- Dashboard de costos y márgenes (s/IVA, c/IVA, margen real).
- Gastos por categoría.
- Detalle de márgenes por venta.

---

## Arquitectura (resumen)

```
app/
  Http/Controllers/   Inventory · Sales · Consignments · Costs · Auth PIN
  Models/             Product, InventoryLot, Sale, SaleItem, Customer, …
  Services/           FifoInventoryService, SaleService, SaleLabelExportService, …
  Support/            ElSalvadorGeo, helpers (money, price_with_vat)
resources/views/      Blade + CSS embebido (layout responsive / drawer móvil)
database/migrations/  Esquema completo del CRM
```

Servicios clave:

- `SaleService` — crear, anular, agregar/quitar/ajustar ítems, recalcular totales y COD.
- `FifoInventoryService` — receive / consume / restore.
- `SaleLabelExportService` — CSV de etiquetas.
- `CustomerPricingService` — precios por cliente y tramo de cantidad.

---

## Setup local (Sail)

Requisitos: Docker Desktop, Composer.

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

App: [http://localhost:8888](http://localhost:8888) (puerto `APP_PORT`).

PIN por defecto en `.env.example`: ver `APP_PIN`.

Comandos útiles:

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan tinker
./vendor/bin/sail exec mysql mysqldump -usail -ppassword --no-tablespaces rolo_inventory_sales > backup.sql
```

---

## Producción (DigitalOcean)

Desplegado en Droplet Ubuntu con:

- Nginx + PHP-FPM 8.4 + MySQL
- App en `/var/www/ventas.roloaccesorios.com`
- SSL Let’s Encrypt (`ventas.roloaccesorios.com`)
- Firewall: 22 / 80 / 443

Variables importantes de producción:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ventas.roloaccesorios.com
APP_PIN=*****
DB_HOST=127.0.0.1
DB_DATABASE=rolo_inventory_sales
DB_USERNAME=rolo
DB_PASSWORD=*****
SESSION_DOMAIN=.roloaccesorios.com
```

Actualizar código (desde la máquina de desarrollo):

```bash
rsync -az --exclude='.git' --exclude='vendor' --exclude='node_modules' --exclude='.env' \
  ./ root@TU_IP:/var/www/ventas.roloaccesorios.com/
ssh root@TU_IP 'cd /var/www/ventas.roloaccesorios.com && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan view:clear && php artisan config:cache && php artisan route:cache'
```

---

## Datos geográficos (El Salvador)

Departamentos y municipios con código postal asistido (`App\Support\ElSalvadorGeo` + JSON en `resources/data/`). Selects enlazados en formularios de cliente / entrega.

---

## UX

- Branding ROLO Accesorios (logo, tipografía Oswald / Space Grotesk).
- Selects buscables (Tom Select).
- Layout **responsive**: barra móvil + menú drawer; tablas con scroll horizontal; formularios apilados.
- Infinite scroll en listado de ventas para selección masiva de export.

---

## Seguridad (estado actual)

- Acceso por PIN compartido (adecuado para equipo pequeño interno).
- `.env` fuera de Git; no versionar secretos.
- HTTPS en producción.
- Ventas anuladas no editables; stock se restaura vía FIFO.

Mejoras futuras posibles: usuarios/roles, auditoría de cambios COD, snapshot de dirección por venta, cola de trabajos, backups automáticos.

---

## Roadmap ya entregado (plan del sistema)

- [x] Inventario + proveedores + lotes FIFO
- [x] Productos (promo, mayoreo, envío gratis, peso)
- [x] Clientes + geo SV + precios por tramo
- [x] Vendedores con prefijo de numeración
- [x] Ventas con IVA, descuentos, envío cobrado vs absorbido
- [x] Empresas de envío + costo + comisión COD
- [x] Margen producto y margen real (s/IVA y c/IVA)
- [x] Export CSV etiquetas (día / selección)
- [x] Infinite scroll en ventas
- [x] Edición COD: cliente, ítems, envío/notas
- [x] Consignaciones (entregas, pagos, devoluciones)
- [x] Costos / gastos / dashboard de márgenes
- [x] UI móvil (drawer)
- [x] Deploy DigitalOcean + dominio + SSL
- [x] Repositorio GitHub versionado

---

## Licencia

Uso interno de ROLO Accesorios. Basado en el skeleton de Laravel (MIT).
