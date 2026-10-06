# API CRM ↔ e-commerce (MVP)

API server-to-server para que una tienda aparte consulte catálogo/stock y cree ventas **contra entrega** (`cash`) o **transferencia** (`transfer`).

- Base URL: `https://tu-crm/api/ecommerce/v1`
- Auth: `Authorization: Bearer {ECOMMERCE_API_TOKEN}`
- Rate limit: 60 req/min

## Configuración (.env)

```env
ECOMMERCE_API_TOKEN=genera-un-token-largo
ECOMMERCE_SELLER_CODE=WEB
# o: ECOMMERCE_SELLER_ID=123
ECOMMERCE_ALLOW_ON_DEMAND=true
ECOMMERCE_DEFAULT_SHIPPING=3
```

Crea un vendedor activo con código `WEB` (o el que indiques) y prefijo de venta, por ejemplo `W-`.

## Endpoints

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/products` | Catálogo activo + stock + precio c/IVA |
| GET | `/products/{code}` | Detalle por código |
| GET | `/combos` | Combos activos expandibles |
| GET | `/shipping-carriers` | Couriers activos |
| POST | `/orders` | Crear venta (idempotente por `external_order_id`) |
| GET | `/orders/{external_order_id}` | Estado por id de la tienda |
| GET | `/orders/by-number/{number}` | Lookup por número CRM |

Los precios los define **solo el CRM** (incluye promo). La tienda no puede mandar precios.

Al crear la venta se descuenta stock FIFO (mismo flujo que el CRM). Despacho/Sistrack sigue en la UI del CRM.

## POST `/orders`

```json
{
  "external_order_id": "SHOP-1001",
  "payment_method": "cash",
  "shipping_carrier_id": 1,
  "has_shipping": true,
  "notes": "Pedido web",
  "customer": {
    "name": "Ana Pérez",
    "phone": "77771111",
    "email": null,
    "address": "Col. Escalón",
    "department": "San Salvador",
    "municipality": "San Salvador"
  },
  "items": [
    { "product_code": "ACELE-RA-ACERBIS-A", "quantity": 2 },
    { "combo_code": "COMBO-1", "quantity": 1 }
  ]
}
```

Respuestas:
- `201` pedido nuevo
- `200` mismo `external_order_id` ya existía (idempotente)
- `401` token inválido
- `422` validación / stock / vendedor no configurado

Cliente: se busca por teléfono (últimos 8 dígitos); si no existe, se crea.

## Smoke test (curl)

```bash
export TOKEN='tu-token'
export BASE='https://ventas.roloaccesorios.com/api/ecommerce/v1'

curl -sS -H "Authorization: Bearer $TOKEN" "$BASE/products" | head
curl -sS -H "Authorization: Bearer $TOKEN" "$BASE/shipping-carriers"

curl -sS -X POST "$BASE/orders" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "external_order_id": "SMOKE-001",
    "payment_method": "cash",
    "shipping_carrier_id": 1,
    "has_shipping": true,
    "customer": {
      "name": "Smoke Test",
      "phone": "70000001",
      "address": "Test",
      "department": "San Salvador",
      "municipality": "San Salvador"
    },
    "items": [{ "product_code": "CODIGO-ACTIVO", "quantity": 1 }]
  }'

curl -sS -H "Authorization: Bearer $TOKEN" "$BASE/orders/SMOKE-001"
```

## Fuera de alcance (fase 2)

Pasarela de tarjeta, hold de stock, webhooks, imágenes, cuentas Sanctum de cliente final.
