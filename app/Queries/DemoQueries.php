<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 7 consultas Eloquent de demostración.
 *
 * Para correrlas:
 *   php artisan demo:queries
 *
 * También se pueden invocar individualmente desde Tinker:
 *   php artisan tinker
 *   >>> (new App\Queries\DemoQueries)->topSellingProducts(10);
 */
class DemoQueries
{
    /**
     * (1) Top productos más vendidos.
     *  - Usa hasMany (Product->orderItems).
     *  - Filtro: sólo productos activos.
     *  - Agregación con withSum.
     *  - Ordenamiento DESC por cantidad vendida.
     */
    public function topSellingProducts(int $limit = 10): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->withSum('orderItems as total_sold', 'quantity')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get(['id', 'sku', 'name', 'price']);
    }

    /**
     * (2) ⭐ EAGER LOADING — usuarios activos con sus órdenes pagadas.
     *
     *  ────────────────────────────────────────────────────────────────
     *  ¿Por qué usamos Eager Loading aquí?
     *
     *  Vamos a recorrer la lista de usuarios y, por cada uno, acceder a
     *  sus órdenes y a los items de cada orden y al producto de cada item.
     *  Sin `with()` esto dispara el clásico problema N+1:
     *
     *      1 query    → SELECT * FROM users WHERE is_active = 1
     *      N queries  → SELECT * FROM orders WHERE user_id = ? (una por user)
     *      N×M queries→ SELECT * FROM order_items WHERE order_id = ?
     *      ...y otra capa por cada product
     *
     *  Con 500 usuarios y miles de órdenes/ítems eso son cientos o miles
     *  de consultas. Con eager loading son solo 4 queries totales (una
     *  por relación), gracias a que Eloquent hace `WHERE IN (...)` para
     *  todas las claves en un solo viaje al motor.
     *  ────────────────────────────────────────────────────────────────
     */
    public function activeUsersWithPaidOrders(int $limit = 20): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('orders', fn ($q) => $q->where('status', 'paid'))
            // Eager loading: evita N+1 al iterar luego sobre orders/items/product.
            ->with([
                'orders' => fn ($q) => $q->where('status', 'paid')->orderByDesc('placed_at'),
                'orders.items.product:id,name,price',
            ])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email']);
    }

    /**
     * (3) Productos en stock de una categoría padre dada.
     *  - whereHas sobre relación anidada (category.parent).
     *  - Filtro escalar (stock > 0).
     *  - Ordenamiento DESC por precio.
     */
    public function inStockProductsByParentCategory(string $parentSlug): Collection
    {
        return Product::query()
            ->whereHas('category.parent', fn ($q) => $q->where('slug', $parentSlug))
            ->where('stock', '>', 0)
            ->where('is_active', true)
            ->with('category:id,name,parent_id')
            ->orderByDesc('price')
            ->get(['id', 'category_id', 'name', 'price', 'stock']);
    }

    /**
     * (4) Órdenes recientes grandes con pago y envío.
     *  - hasOne (Order->payment, Order->shipment).
     *  - Filtro temporal y por monto.
     *  - Ordenamiento por total DESC.
     */
    public function recentLargeOrders(float $minTotal = 200.0, int $days = 90): Collection
    {
        return Order::query()
            ->where('placed_at', '>=', now()->subDays($days))
            ->where('total', '>=', $minTotal)
            ->with(['user:id,name,email', 'payment', 'shipment'])
            ->orderByDesc('total')
            ->limit(50)
            ->get();
    }

    /**
     * (5) Productos mejor calificados.
     *  - withCount + withAvg sobre la relación reviews.
     *  - having sobre agregados.
     *  - Ordenamiento DESC por rating y luego por # de reseñas.
     */
    public function bestRatedProducts(): Collection
    {
        return Product::query()
            ->withCount('reviews')
            ->withAvg('reviews as avg_rating', 'rating')
            ->having('reviews_count', '>=', 3)
            ->having('avg_rating', '>=', 4)
            ->orderByDesc('avg_rating')
            ->orderByDesc('reviews_count')
            ->limit(25)
            ->get(['id', 'name', 'price']);
    }

    /**
     * (6) Categorías padre con conteos.
     *  - Auto-relación (Category->children).
     *  - withCount anidado.
     */
    public function parentCategoriesWithProductCount(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->withCount('children')
            ->with(['children' => fn ($q) => $q->withCount('products')])
            ->orderBy('name')
            ->get();
    }

    /**
     * (7) Cupones activos efectivamente usados.
     *  - belongsToMany (Coupon<->Order vía pivote coupon_order).
     *  - Filtro temporal con valid_until.
     */
    public function activeCouponsUsage(): Collection
    {
        return Coupon::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now());
            })
            ->withCount('orders as orders_using')
            ->having('orders_using', '>', 0)
            ->orderByDesc('orders_using')
            ->get(['id', 'code', 'type', 'value']);
    }

    /**
     * Ejecuta todas las consultas e imprime un resumen en consola.
     */
    public function runAll(): void
    {
        $this->line('1) Top productos más vendidos');
        foreach ($this->topSellingProducts(5) as $p) {
            $this->line("   - {$p->name} (vendidos: " . ($p->total_sold ?? 0) . ")");
        }

        $this->line('');
        $this->line('2) Usuarios activos con órdenes pagadas (EAGER LOADING)');
        DB::enableQueryLog();
        $users = $this->activeUsersWithPaidOrders(5);
        foreach ($users as $u) {
            $orders = $u->orders->count();
            $items  = $u->orders->sum(fn ($o) => $o->items->count());
            $this->line("   - {$u->name} → {$orders} órdenes pagadas, {$items} items totales");
        }
        $totalQueries = count(DB::getQueryLog());
        $this->line("   ⚡ Total queries ejecutadas: {$totalQueries} (sin eager loading serían ~" . (1 + $users->count() * 3) . "+)");
        DB::disableQueryLog();

        $this->line('');
        $this->line('3) Productos en stock de "electronica"');
        foreach ($this->inStockProductsByParentCategory('electronica')->take(5) as $p) {
            $this->line("   - {$p->name} | Q{$p->price} | stock {$p->stock}");
        }

        $this->line('');
        $this->line('4) Órdenes recientes grandes (total ≥ Q200)');
        foreach ($this->recentLargeOrders(200, 90)->take(5) as $o) {
            $pay = $o->payment?->status ?? 'sin pago';
            $this->line("   - {$o->order_number} | Q{$o->total} | {$o->status} | pago: {$pay}");
        }

        $this->line('');
        $this->line('5) Productos mejor calificados');
        foreach ($this->bestRatedProducts()->take(5) as $p) {
            $this->line("   - {$p->name} | ★ " . number_format($p->avg_rating, 2) . " ({$p->reviews_count} reseñas)");
        }

        $this->line('');
        $this->line('6) Categorías padre');
        foreach ($this->parentCategoriesWithProductCount() as $c) {
            $kids = $c->children->sum('products_count');
            $this->line("   - {$c->name}: {$c->children_count} subcategorías | {$kids} productos");
        }

        $this->line('');
        $this->line('7) Cupones activos en uso');
        foreach ($this->activeCouponsUsage()->take(5) as $c) {
            $this->line("   - {$c->code} ({$c->type} {$c->value}) → usado en {$c->orders_using} órdenes");
        }
    }

    private function line(string $msg): void
    {
        echo $msg . PHP_EOL;
    }
}
