<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DatabaseSeeder coherente con el dominio de tienda en línea.
 *
 * Distribución aproximada (≥ 10,000 registros — requisito del lab):
 *   - users:           500
 *   - addresses:     1,000
 *   - categories:       30  (5 padre + 25 hijas)
 *   - products:        500
 *   - product_images: 1,500
 *   - orders:        2,000
 *   - order_items:   ~5,000
 *   - payments:      ~1,250
 *   - shipments:     ~850
 *   - reviews:       2,000
 *   - coupons:          50
 *   - coupon_order:    ~350
 *   ────────────────────────────
 *   TOTAL aprox:    ~15,000+ registros
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('es_ES');
        $faker->seed(1234);

        $this->command->info('Sembrando usuarios...');
        $this->seedUsers($faker, 500);

        $this->command->info('Sembrando direcciones...');
        $this->seedAddresses($faker, 1000);

        $this->command->info('Sembrando categorías...');
        $this->seedCategories();

        $this->command->info('Sembrando productos...');
        $this->seedProducts($faker, 500);

        $this->command->info('Sembrando imágenes de productos...');
        $this->seedProductImages($faker, 1500);

        $this->command->info('Sembrando cupones...');
        $this->seedCoupons($faker, 50);

        $this->command->info('Sembrando órdenes + items + pagos + envíos + cupones aplicados...');
        $this->seedOrders($faker, 2000);

        $this->command->info('Sembrando reseñas...');
        $this->seedReviews($faker, 2000);

        $this->printSummary();
    }

    // ───────────────────────────────────────────────────────────────────
    private function seedUsers(\Faker\Generator $faker, int $count): void
    {
        $batch = [];
        $now   = now();
        $hash  = Hash::make('password'); // todos los users con password "password"

        for ($i = 1; $i <= $count; $i++) {
            $name = $faker->name;
            $batch[] = [
                'name'              => $name,
                'email'             => Str::lower(Str::slug($name, '.')) . $i . '@tienda.test',
                'email_verified_at' => $faker->boolean(80) ? $now : null,
                'password'          => $hash,
                'phone'             => substr($faker->phoneNumber, 0, 25),
                'birth_date'        => $faker->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
                'is_active'         => $faker->boolean(95),
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
            if (count($batch) === 500) {
                User::insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            User::insert($batch);
        }
    }

    // ───────────────────────────────────────────────────────────────────
    private function seedAddresses(\Faker\Generator $faker, int $count): void
    {
        $userIds = User::pluck('id')->all();
        $labels  = ['Casa', 'Trabajo', 'Casa de familiares', 'Oficina'];
        $now     = now();
        $batch   = [];

        // 1 dirección default por user
        foreach ($userIds as $uid) {
            $batch[] = $this->addressRow($faker, $uid, $labels, $now, true);
        }

        $remaining = $count - count($userIds);
        for ($i = 0; $i < $remaining; $i++) {
            $batch[] = $this->addressRow($faker, $faker->randomElement($userIds), $labels, $now, false);
            if (count($batch) >= 500) {
                Address::insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            Address::insert($batch);
        }
    }

    private function addressRow($faker, int $userId, array $labels, $now, bool $default): array
    {
        return [
            'user_id'        => $userId,
            'label'          => $faker->randomElement($labels),
            'recipient_name' => $faker->name,
            'phone'          => substr($faker->phoneNumber, 0, 25),
            'street'         => substr($faker->streetAddress, 0, 180),
            'city'           => substr($faker->city, 0, 80),
            'state'          => substr($faker->state, 0, 80),
            'postal_code'    => substr($faker->postcode, 0, 15),
            'country'        => 'Guatemala',
            'is_default'     => $default,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    private function seedCategories(): void
    {
        $parents = [
            'Electrónica'    => ['Smartphones', 'Laptops', 'Audífonos', 'Cámaras', 'Smartwatches'],
            'Hogar'          => ['Muebles', 'Cocina', 'Decoración', 'Iluminación', 'Jardín'],
            'Moda'           => ['Ropa hombre', 'Ropa mujer', 'Zapatos', 'Accesorios', 'Bolsos'],
            'Deportes'       => ['Fitness', 'Camping', 'Ciclismo', 'Running', 'Natación'],
            'Libros y media' => ['Novelas', 'Educativos', 'Música', 'Películas', 'Cómics'],
        ];

        foreach ($parents as $parentName => $children) {
            $parent = Category::create([
                'name'        => $parentName,
                'slug'        => Str::slug($parentName),
                'description' => "Sección general de $parentName",
                'is_active'   => true,
            ]);

            foreach ($children as $child) {
                Category::create([
                    'name'        => $child,
                    'slug'        => Str::slug($parentName . '-' . $child),
                    'description' => "Productos de $child",
                    'parent_id'   => $parent->id,
                    'is_active'   => true,
                ]);
            }
        }
    }

    // ───────────────────────────────────────────────────────────────────
    private function seedProducts(\Faker\Generator $faker, int $count): void
    {
        $leafCategoryIds = Category::whereNotNull('parent_id')->pluck('id')->all();
        $batch = [];
        $now   = now();

        for ($i = 1; $i <= $count; $i++) {
            $name = ucfirst($faker->words(rand(2, 4), true));
            $cost = $faker->randomFloat(2, 5, 500);

            $batch[] = [
                'category_id'  => $faker->randomElement($leafCategoryIds),
                'sku'          => 'SKU-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
                'name'         => $name,
                'slug'         => Str::slug($name) . '-' . $i,
                'description'  => $faker->paragraph(3),
                'price'        => round($cost * $faker->randomFloat(2, 1.3, 2.5), 2),
                'cost'         => $cost,
                'stock'        => $faker->numberBetween(0, 300),
                'weight_kg'    => $faker->randomFloat(3, 0.05, 25),
                'is_active'    => $faker->boolean(90),
                'published_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            if (count($batch) >= 500) {
                Product::insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            Product::insert($batch);
        }
    }

    // ───────────────────────────────────────────────────────────────────
    private function seedProductImages(\Faker\Generator $faker, int $count): void
    {
        $productIds = Product::pluck('id')->all();
        $batch = [];
        $now   = now();

        // Aseguramos al menos 1 imagen primaria por producto
        foreach ($productIds as $pid) {
            $batch[] = [
                'product_id' => $pid,
                'url'        => 'https://picsum.photos/seed/' . $pid . '/600/600',
                'alt_text'   => 'Imagen principal',
                'position'   => 0,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $remaining = $count - count($productIds);
        for ($i = 0; $i < $remaining; $i++) {
            $pid = $faker->randomElement($productIds);
            $batch[] = [
                'product_id' => $pid,
                'url'        => 'https://picsum.photos/seed/' . $pid . '-' . $i . '/600/600',
                'alt_text'   => $faker->words(3, true),
                'position'   => $faker->numberBetween(1, 5),
                'is_primary' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                ProductImage::insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            ProductImage::insert($batch);
        }
    }

    // ───────────────────────────────────────────────────────────────────
    private function seedCoupons(\Faker\Generator $faker, int $count): void
    {
        $now   = now();
        $batch = [];

        for ($i = 1; $i <= $count; $i++) {
            $type = $faker->randomElement(['percent', 'fixed']);
            $batch[] = [
                'code'             => strtoupper(Str::random(8)) . $i,
                'type'             => $type,
                'value'            => $type === 'percent'
                                        ? $faker->numberBetween(5, 30)
                                        : $faker->randomFloat(2, 5, 100),
                'min_order_amount' => $faker->randomFloat(2, 0, 100),
                'max_uses'         => $faker->randomElement([null, 50, 100, 500]),
                'used_count'       => 0,
                'valid_from'       => $faker->dateTimeBetween('-1 year', 'now'),
                'valid_until'      => $faker->dateTimeBetween('now', '+6 months'),
                'is_active'        => $faker->boolean(80),
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        Coupon::insert($batch);
    }

    // ───────────────────────────────────────────────────────────────────
    private function seedOrders(\Faker\Generator $faker, int $count): void
    {
        $users      = User::with(['addresses' => fn ($q) => $q->where('is_default', true)])
            ->whereHas('addresses')
            ->get(['id']);
        $productIds = Product::pluck('id')->all();
        $products   = Product::select('id', 'price')->get()->keyBy('id');
        $couponIds  = Coupon::where('is_active', true)->pluck('id')->all();
        $statuses   = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

        $now = now();
        $orderRows = $itemRows = $paymentRows = $shipmentRows = $couponRows = [];
        $couponSeen = [];

        for ($i = 1; $i <= $count; $i++) {
            $user      = $users->random();
            $addressId = $user->addresses->first()?->id;
            $status    = $faker->randomElement($statuses);
            $placedAt  = $faker->dateTimeBetween('-1 year', 'now');

            $itemCount = $faker->numberBetween(1, 5);
            $pickedIds = $faker->randomElements($productIds, $itemCount);

            $subtotal = 0;
            $thisOrderItems = [];
            foreach ($pickedIds as $pid) {
                $qty   = $faker->numberBetween(1, 4);
                $price = (float)$products[$pid]->price;
                $line  = round($price * $qty, 2);
                $subtotal += $line;

                $thisOrderItems[] = [
                    'product_id' => $pid,
                    'quantity'   => $qty,
                    'unit_price' => $price,
                    'line_total' => $line,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $tax      = round($subtotal * 0.12, 2);     // IVA 12% (Guatemala)
            $shipping = $faker->randomFloat(2, 5, 50);
            $discount = $faker->boolean(25) ? round($subtotal * 0.10, 2) : 0;
            $total    = round($subtotal + $tax + $shipping - $discount, 2);

            $orderId = $i;

            $orderRows[] = [
                'id'                  => $orderId,
                'user_id'             => $user->id,
                'shipping_address_id' => $addressId,
                'order_number'        => 'ORD-' . str_pad((string)$i, 8, '0', STR_PAD_LEFT),
                'status'              => $status,
                'subtotal'            => $subtotal,
                'tax'                 => $tax,
                'shipping_cost'       => $shipping,
                'discount'            => $discount,
                'total'               => $total,
                'notes'               => $faker->boolean(20) ? $faker->sentence : null,
                'placed_at'           => $placedAt,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            foreach ($thisOrderItems as $row) {
                $row['order_id'] = $orderId;
                $itemRows[] = $row;
            }

            if (in_array($status, ['paid', 'shipped', 'delivered'])) {
                $paymentRows[] = [
                    'order_id'              => $orderId,
                    'method'                => $faker->randomElement(['credit_card','debit_card','paypal','transfer','cash']),
                    'status'                => 'completed',
                    'amount'                => $total,
                    'transaction_reference' => 'TXN-' . Str::upper(Str::random(12)) . $i,
                    'paid_at'               => $placedAt,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ];
            }

            if (in_array($status, ['shipped', 'delivered'])) {
                $shipmentRows[] = [
                    'order_id'        => $orderId,
                    'carrier'         => $faker->randomElement(['DHL', 'FedEx', 'Cargo Expreso', 'Guatex']),
                    'tracking_number' => 'TRK-' . Str::upper(Str::random(10)) . $i,
                    'status'          => $status === 'delivered' ? 'delivered' : 'in_transit',
                    'shipped_at'      => $placedAt,
                    'delivered_at'    => $status === 'delivered'
                                           ? Carbon::parse($placedAt)->addDays(rand(2,7))
                                           : null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            if ($discount > 0 && !empty($couponIds) && $faker->boolean(70)) {
                $cid = $faker->randomElement($couponIds);
                $key = $cid . '-' . $orderId;
                if (!isset($couponSeen[$key])) {
                    $couponSeen[$key] = true;
                    $couponRows[] = [
                        'coupon_id'      => $cid,
                        'order_id'       => $orderId,
                        'amount_applied' => $discount,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }

            // Insertar por chunks de 500 órdenes
            if (count($orderRows) >= 500) {
                Order::insert($orderRows);
                OrderItem::insert($itemRows);
                if ($paymentRows) {
                    Payment::insert($paymentRows);
                }
                if ($shipmentRows) {
                    Shipment::insert($shipmentRows);
                }
                if ($couponRows) {
                    DB::table('coupon_order')->insert($couponRows);
                }
                $orderRows = $itemRows = $paymentRows = $shipmentRows = $couponRows = [];
            }
        }

        // Insertar lo restante
        if ($orderRows)    Order::insert($orderRows);
        if ($itemRows)     OrderItem::insert($itemRows);
        if ($paymentRows)  Payment::insert($paymentRows);
        if ($shipmentRows) Shipment::insert($shipmentRows);
        if ($couponRows)   DB::table('coupon_order')->insert($couponRows);

        // Recalcular used_count en cupones
        DB::statement('
            UPDATE coupons
            SET used_count = (
                SELECT COUNT(*) FROM coupon_order WHERE coupon_order.coupon_id = coupons.id
            )
        ');
    }

    // ───────────────────────────────────────────────────────────────────
    private function seedReviews(\Faker\Generator $faker, int $count): void
    {
        // Solo reseñas de productos que el usuario efectivamente compró
        $purchased = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('orders.user_id', 'order_items.product_id')
            ->distinct()
            ->limit($count * 2)
            ->get();

        $now   = now();
        $batch = [];
        $seen  = [];

        foreach ($purchased as $row) {
            $key = $row->user_id . '-' . $row->product_id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $batch[] = [
                'user_id'              => $row->user_id,
                'product_id'           => $row->product_id,
                'rating'               => $faker->numberBetween(1, 5),
                'title'                => $faker->boolean(80) ? $faker->sentence(4) : null,
                'body'                 => $faker->boolean(70) ? $faker->paragraph(2) : null,
                'is_verified_purchase' => true,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];

            if (count($batch) >= $count) {
                break;
            }
            if (count($batch) % 500 === 0) {
                Review::insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            Review::insert($batch);
        }
    }

    // ───────────────────────────────────────────────────────────────────
    private function printSummary(): void
    {
        $counts = [
            'users'          => User::count(),
            'addresses'      => Address::count(),
            'categories'     => Category::count(),
            'products'       => Product::count(),
            'product_images' => ProductImage::count(),
            'orders'         => Order::count(),
            'order_items'    => OrderItem::count(),
            'payments'       => Payment::count(),
            'shipments'      => Shipment::count(),
            'reviews'        => Review::count(),
            'coupons'        => Coupon::count(),
            'coupon_order'   => DB::table('coupon_order')->count(),
        ];
        $total = array_sum($counts);

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('  RESUMEN DE REGISTROS SEMBRADOS');
        $this->command->info('═══════════════════════════════════════');
        foreach ($counts as $table => $n) {
            $this->command->info(sprintf('  %-18s %s', $table, number_format($n)));
        }
        $this->command->info('───────────────────────────────────────');
        $this->command->info(sprintf('  %-18s %s', 'TOTAL', number_format($total)));
        $this->command->info('═══════════════════════════════════════');
    }
}
