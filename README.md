# 🛒 Tienda Online — Laboratorio ORM (Laravel 13 + Eloquent)

**Curso:** Base de Datos 1 — Semestre 01, 2026  
**Autor:** Denil Parada  
**Tema:** Modelado de dominio con ORM (Eloquent / Laravel 13)

Este proyecto implementa un laboratorio de ORM modelando una **tienda en línea (e-commerce)** utilizando **Laravel 13 y Eloquent**. Incluye diseño de base de datos, relaciones, consultas avanzadas y generación de datos coherentes.

---

## 📌 1. Cumplimiento de requisitos

| # | Requisito | Resultado |
|---|----------|----------|
| 1 | ≥ 10 tablas con `up()` y `down()` | ✅ 16 tablas |
| 2 | Modelo Eloquent con `$fillable` y `$casts` | ✅ 11 modelos |
| 3 | ≥ 5 relaciones bidireccionales | ✅ 10 relaciones |
| 4 | ≥ 5 consultas con filtros y relaciones | ✅ 7 consultas |
| 5 | Eager Loading con justificación | ✅ Incluido |
| 6 | ≥ 10,000 registros en seeder | ✅ ~15,000 |
| 7 | README con instrucciones | ✅ Este documento |

---

## 🧩 2. Dominio: Tienda en línea

```
users ──< addresses
users ──< orders ──< order_items >── products >── categories (self)
                                       │
                                       └──< product_images
orders ──── payments        (1-1)
orders ──── shipments       (1-1)
orders ─>──< coupons        (N-M)
users  ──< reviews >── products
```

### 🗃️ Tablas del dominio (11)

- addresses  
- categories  
- products  
- product_images  
- orders  
- order_items  
- payments  
- shipments  
- reviews  
- coupons  
- coupon_order  

Además:
- users extendida con: phone, birth_date, is_active

---

## 🔗 Relaciones principales

- Usuario → Direcciones, Órdenes, Reseñas  
- Categoría → Subcategorías (auto-relación)  
- Categoría → Productos  
- Producto → Imágenes, Items de orden  
- Orden → Items, Pago, Envío, Cupones  

---

## ⚙️ 3. Instalación

### Requisitos

- PHP ≥ 8.3  
- Composer ≥ 2.x  
- Extensiones:  
  pdo_sqlite, mbstring, openssl, tokenizer, fileinfo, zip, curl  

### Pasos

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
```

Se generarán aproximadamente **15,000 registros**.

---

## 🚀 4. Ejecutar consultas Eloquent

### Opción A — Artisan

```bash
php artisan demo:queries
```

### Opción B — Tinker

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

---

## 🧠 5. Consultas implementadas

Archivo: app/Queries/DemoQueries.php

1. topSellingProducts() — productos más vendidos  
2. activeUsersWithPaidOrders() — incluye Eager Loading (evita N+1)  
3. inStockProductsByParentCategory() — filtros con whereHas  
4. recentLargeOrders() — órdenes recientes por monto  
5. bestRatedProducts() — agregaciones (withAvg, having)  
6. parentCategoriesWithProductCount() — conteos jerárquicos  
7. activeCouponsUsage() — uso de cupones (N-M)  

---

## 📁 6. Estructura del proyecto

```
app/
├── Models/
├── Queries/
├── Console/Commands/

database/
├── migrations/
├── seeders/

README.md
```

---

## 🔍 7. Coherencia de datos (Seeder)

El seeder garantiza:

- Reseñas solo de productos comprados  
- Pagos válidos según estado de orden  
- Envíos coherentes  
- Usuarios con al menos una dirección por defecto  
- Productos con imagen principal  
- Totales correctos en órdenes  
- IVA del 12% (Guatemala) aplicado  

---

## 🔄 8. Reiniciar base de datos

```bash
php artisan migrate:fresh --seed
```

---

## 🧾 Notas finales

Este proyecto demuestra:

- Uso de Eloquent ORM  
- Relaciones complejas  
- Consultas optimizadas  
- Buenas prácticas en modelado de datos  