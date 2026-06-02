<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalePriceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_page_shows_sale_price_data(): void
    {
        $product = $this->createSaleProduct();

        $this->get('/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Products/Index')
                ->has('products.data')
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.price', '165000.00')
                ->where('products.data.0.sale_price', '130000.00')
                ->where('products.data.0.final_price', 130000)
                ->where('products.data.0.is_on_sale', true)
            );
    }

    public function test_product_detail_shows_sale_price_data(): void
    {
        $product = $this->createSaleProduct();

        $this->get('/products/' . $product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Products/Show')
                ->where('product.id', $product->id)
                ->where('product.price', '165000.00')
                ->where('product.sale_price', '130000.00')
                ->where('product.final_price', 130000)
                ->where('product.is_on_sale', true)
            );
    }

    public function test_cart_uses_sale_price_as_unit_price(): void
    {
        [$user, $product, $variant] = $this->createSaleProductWithUserCart();

        $this->actingAs($user)
            ->get('/cart')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Cart/Index')
                ->has('items', 1)
                ->where('items.0.product.id', $product->id)
                ->where('items.0.variant.id', $variant->id)
                ->where('items.0.quantity', 2)
                ->where('items.0.unit_price', 130000)
                ->where('items.0.subtotal', 260000)
                ->where('summary.subtotal', 260000)
            );
    }

    public function test_checkout_uses_sale_price_for_summary(): void
    {
        [$user] = $this->createSaleProductWithUserCart();

        $this->actingAs($user)
            ->get('/checkout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Checkout/Index')
                ->has('items', 1)
                ->where('items.0.unit_price', 130000)
                ->where('items.0.subtotal', 260000)
                ->where('summary.subtotal', 260000)
                ->where('summary.shipping_cost', 15000)
                ->where('summary.total', 275000)
            );
    }

    public function test_normal_product_uses_original_price_when_sale_price_is_null(): void
    {
        [$user, $product, $variant] = $this->createSaleProductWithUserCart([
            'sale_price' => null,
        ]);

        $this->actingAs($user)
            ->get('/cart')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Cart/Index')
                ->where('items.0.product.id', $product->id)
                ->where('items.0.variant.id', $variant->id)
                ->where('items.0.unit_price', 165000)
                ->where('items.0.subtotal', 330000)
                ->where('summary.subtotal', 330000)
            );
    }

    private function createSaleProduct(array $overrides = []): Product
    {
        $category = Category::forceCreate([
            'name' => 'Test Tees',
            'slug' => 'test-tees-' . Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $product = Product::forceCreate(array_merge([
            'category_id' => $category->id,
            'name' => 'NEVERENDING Sale Tee',
            'slug' => 'neverending-sale-tee-' . Str::lower(Str::random(6)),
            'description' => 'Sale product for automated testing.',
            'price' => 165000,
            'sale_price' => 130000,
            'weight' => 300,
            'is_active' => true,
        ], $overrides));

        ProductImage::forceCreate([
            'product_id' => $product->id,
            'image_path' => 'products/test-sale-product.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        ProductVariant::forceCreate([
            'product_id' => $product->id,
            'size' => 'M',
            'color' => null,
            'sku' => 'SALE-' . Str::upper(Str::random(6)),
            'stock' => 5,
            'additional_price' => 0,
            'is_active' => true,
        ]);

        return $product;
    }

    private function createSaleProductWithUserCart(array $productOverrides = []): array
    {
        $user = User::factory()->create();

        $product = $this->createSaleProduct($productOverrides);

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->firstOrFail();

        $cart = Cart::forceCreate([
            'user_id' => $user->id,
        ]);

        CartItem::forceCreate([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        return [$user, $product, $variant];
    }
}