# Tienda Online — Laboratorio ORM (Laravel 13 + Eloquent)

**Curso:** Base de Datos 1 — Semestre 01, 2026
**Autor:** Denil Parada
**Tema:** Modelado de un dominio con un ORM (Eloquent / Laravel 13)

Este repositorio implementa el laboratorio de ORM modelando el dominio de una **tienda en línea** (e-commerce).

---

## 1. Cumplimiento de requisitos

| # | Requisito | Cumplimiento |
|---|-----------|--------------|
| 1 | ≥ 10 tablas con `up()` y `down()` | **16 tablas** (3 de Laravel + 1 alter de users + 11 de dominio + jobs/cache de Laravel) |
| 2 | Modelo Eloquent por tabla con `$fillable` y `$casts` | 11 modelos en `app/Models/` |
| 3 | ≥ 5 relaciones (definidas en ambos lados) | **10 relaciones bidireccionales** |
| 4 | ≥ 5 consultas Eloquent con relaciones, filtros y ordenamiento | **7 consultas** en `app/Queries/DemoQueries.php` |
| 5 | Al menos una consulta con Eager Loading + justificación | `activeUsersWithPaidOrders()` — docblock explica el N+1 |
| 6 | DatabaseSeeder con ≥ 10,000 registros coherentes | ~15,000 registros |
| 7 | README con instrucciones | Este archivo |

---

## 2. Dominio modelado: Tienda en línea

```
users ──< addresses
users ──< orders ──< order_items >── products >── categories (auto-relación)
                                       │
                                       └──< product_images
orders ──── payments        (1-1)
orders ──── shipments       (1-1)
orders ─>──< coupons        (N-M vía coupon_order)
users  ──< reviews >── products
```

### Tablas del dominio (11)

`addresses`, `categories`, `products`, `product_images`, `orders`, `order_items`, `payments`, `shipments`, `reviews`, `coupons`, `coupon_order`

Más la tabla `users` extendida con `phone`, `birth_date`, `is_active`.

### Las 10 relaciones

| # | Modelo | Tipo | Modelo destino | Inverso |
|---|--------|------|----------------|---------|
| 1 | `User` | hasMany | `Address` | `Address::user()` (belongsTo) |
| 2 | `User` | hasMany | `Order` | `Order::user()` (belongsTo) |
| 3 | `User` | hasMany | `Review` | `Review::user()` (belongsTo) |
| 4 | `Category` | hasMany (self) | `Category` (children) | `Category::parent()` (belongsTo) |
| 5 | `Category` | hasMany | `Product` | `Product::category()` (belongsTo) |
| 6 | `Product` | hasMany | `ProductImage` | `ProductImage::product()` (belongsTo) |
| 7 | `Product` | hasMany | `OrderItem` | `OrderItem::product()` (belongsTo) |
| 8 | `Order` | hasMany | `OrderItem` | `OrderItem::order()` (belongsTo) |
| 9 | `Order` | hasOne | `Payment` | `Payment::order()` (belongsTo) |
| 10 | `Order` | hasOne / belongsToMany | `Shipment` / `Coupon` | `Shipment::order()` / `Coupon::orders()` |

---

## 3. Instalación

### Pre-requisitos

- PHP ≥ 8.3 con las extensiones: `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `fileinfo`, `zip`, `curl`
- Composer ≥ 2.x

### Pasos

```bash
# 1. Instalar dependencias
composer install

# 2. Copiar el .env (si no existe)
cp .env.example .env

# 3. Generar la APP_KEY
php artisan key:generate

# 4. Crear el archivo SQLite vacío (puede que ya exista)
#    En PowerShell:
#    New-Item -Path database\database.sqlite -ItemType File -Force
#    En Git Bash:
touch database/database.sqlite

# 5. Correr migraciones + sembrar datos (~15,000 registros, 1-3 min)
php artisan migrate:fresh --seed
```

Cuando el seeder termine, verás un resumen como:

```
═══════════════════════════════════════
  RESUMEN DE REGISTROS SEMBRADOS
═══════════════════════════════════════
  users                500
  addresses          1,000
  ...
  TOTAL             15,000+
═══════════════════════════════════════
```

---

## 4. Cómo correr las 7 consultas Eloquent

### Opción A — Comando Artisan dedicado

```bash
php artisan demo:queries
```

### Opción B — Tinker (REPL interactivo)

```bash
php artisan tinker
```

```php
$demo = new App\Queries\DemoQueries();

$demo->topSellingProducts(10);
$demo->activeUsersWithPaidOrders(5);
$demo->inStockProductsByParentCategory('electronica');
$demo->recentLargeOrders(200, 90);
$demo->bestRatedProducts();
$demo->parentCategoriesWithProductCount();
$demo->activeCouponsUsage();
```

### Opción C — Ver el SQL real generado por Eloquent

```php
DB::enableQueryLog();
$users = (new App\Queries\DemoQueries)->activeUsersWithPaidOrders(20);
foreach ($users as $u) {
    foreach ($u->orders as $o) {
        foreach ($o->items as $it) {
            $it->product->name;
        }
    }
}
dd(DB::getQueryLog()); // sólo ~4 queries en total, no cientos
```

---

## 5. Las 7 consultas en una línea

Archivo: [`app/Queries/DemoQueries.php`](app/Queries/DemoQueries.php)

1. `topSellingProducts()` — top vendidos, `withSum`, filtro activo, ordenamiento DESC.
2. **`activeUsersWithPaidOrders()`** — ⭐ EAGER LOADING justificado en el docblock.
3. `inStockProductsByParentCategory()` — `whereHas` anidado, filtro stock, ordenamiento.
4. `recentLargeOrders()` — filtros temporal y por monto, eager load de user/payment/shipment.
5. `bestRatedProducts()` — `withCount` + `withAvg` + `having`.
6. `parentCategoriesWithProductCount()` — auto-relación + conteos anidados.
7. `activeCouponsUsage()` — `belongsToMany` + filtro temporal.

---

## 6. Estructura del proyecto (archivos del lab)

```
tienda-online/
├── app/
│   ├── Console/Commands/
│   │   └── DemoQueriesCommand.php       ← php artisan demo:queries
│   ├── Models/
│   │   ├── Address.php
│   │   ├── Category.php
│   │   ├── Coupon.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Payment.php
│   │   ├── Product.php
│   │   ├── ProductImage.php
│   │   ├── Review.php
│   │   ├── Shipment.php
│   │   └── User.php                     ← modificado: agrega relaciones
│   └── Queries/
│       └── DemoQueries.php              ← 7 consultas demostrativas
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php           ← Laravel default
│   │   ├── 0001_01_01_000001_create_cache_table.php           ← Laravel default
│   │   ├── 0001_01_01_000002_create_jobs_table.php            ← Laravel default
│   │   ├── 2026_01_01_000001_add_columns_to_users_table.php   ← extensión
│   │   ├── 2026_01_01_000002_create_addresses_table.php
│   │   ├── 2026_01_01_000003_create_categories_table.php
│   │   ├── 2026_01_01_000004_create_products_table.php
│   │   ├── 2026_01_01_000005_create_product_images_table.php
│   │   ├── 2026_01_01_000006_create_orders_table.php
│   │   ├── 2026_01_01_000007_create_order_items_table.php
│   │   ├── 2026_01_01_000008_create_payments_table.php
│   │   ├── 2026_01_01_000009_create_shipments_table.php
│   │   ├── 2026_01_01_000010_create_reviews_table.php
│   │   ├── 2026_01_01_000011_create_coupons_table.php
│   │   └── 2026_01_01_000012_create_coupon_order_table.php
│   └── seeders/
│       └── DatabaseSeeder.php           ← ~15,000 registros
└── README.md                            ← este archivo
```

---

## 7. Coherencias garantizadas por el seeder

- Reseñas solo de productos comprados (`is_verified_purchase = true`).
- Pagos solo para órdenes `paid`, `shipped` o `delivered`.
- Envíos solo para órdenes `shipped` o `delivered`.
- Cada usuario tiene al menos una dirección `is_default = true`.
- Cada producto tiene al menos una imagen `is_primary = true`.
- `total = subtotal + tax + shipping_cost - discount` se cumple en todas las órdenes.
- IVA del 12% (Guatemala) aplicado en cada orden.

---

## 8. Reiniciar / regenerar la BD

```bash
# Borrar todo y volver a sembrar
php artisan migrate:fresh --seed
```
#   O R M  
 #   O R M  
 