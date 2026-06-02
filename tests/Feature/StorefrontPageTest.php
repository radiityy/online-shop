<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storefront_pages_can_be_opened(): void
    {
        $pages = [
            '/',
            '/products',
            '/about',
            '/faq',
            '/shipping',
            '/returns',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_product_search_filters_products(): void
    {
        $product = $this->createTestProduct([
            'name' => 'NEVERENDING Test Search Tee ' . Str::upper(Str::random(6)),
            'slug' => 'neverending-test-search-tee-' . Str::lower(Str::random(6)),
        ]);

        $this->get('/products?search=test-search')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Products/Index')
                ->where('filters.search', 'test-search')
                ->has('products.data')
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.name', $product->name)
            );
    }

    public function test_category_filter_filters_products(): void
    {
        $categorySlug = 'test-category-' . Str::lower(Str::random(6));

        $product = $this->createTestProduct([
            'name' => 'NEVERENDING Category Filter Tee',
            'slug' => 'neverending-category-filter-tee-' . Str::lower(Str::random(6)),
            'category' => [
                'name' => 'Test Category',
                'slug' => $categorySlug,
            ],
        ]);

        $this->get('/products?category=' . $categorySlug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Products/Index')
                ->where('filters.category', $categorySlug)
                ->has('products.data')
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.category.slug', $categorySlug)
            );
    }

    public function test_product_detail_page_can_be_opened(): void
    {
        $product = $this->createTestProduct([
            'name' => 'NEVERENDING Detail Test Tee',
            'slug' => 'neverending-detail-test-tee-' . Str::lower(Str::random(6)),
        ]);

        $this->get('/products/' . $product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Products/Show')
                ->where('product.id', $product->id)
                ->where('product.name', $product->name)
                ->has('product.images')
                ->has('product.variants')
            );
    }

    public function test_guest_is_redirected_from_customer_pages(): void
    {
        $protectedPages = [
            '/cart',
            '/checkout',
            '/orders',
            '/account',
            '/account/addresses',
        ];

        foreach ($protectedPages as $page) {
            $this->get($page)->assertRedirect('/login');
        }
    }

    public function test_authenticated_customer_can_open_customer_pages(): void
    {
        $user = User::factory()->create();

        $pages = [
            '/cart',
            '/orders',
            '/account',
            '/account/addresses',
        ];

        foreach ($pages as $page) {
            $this->actingAs($user)
                ->get($page)
                ->assertOk();
        }
    }

    private function createTestProduct(array $overrides = []): Product
    {
        $categoryData = $overrides['category'] ?? [];

        unset($overrides['category']);

        $category = Category::forceCreate([
            'name' => $categoryData['name'] ?? 'Test Tees',
            'slug' => $categoryData['slug'] ?? 'test-tees-' . Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $product = Product::forceCreate(array_merge([
            'category_id' => $category->id,
            'name' => 'NEVERENDING Test Product ' . Str::upper(Str::random(6)),
            'slug' => 'neverending-test-product-' . Str::lower(Str::random(6)),
            'description' => 'Test product for automated storefront testing.',
            'price' => 150000,
            'weight' => 300,
            'is_active' => true,
        ], $overrides));

        ProductImage::forceCreate([
            'product_id' => $product->id,
            'image_path' => 'products/test-product.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        ProductVariant::forceCreate([
            'product_id' => $product->id,
            'size' => 'S',
            'color' => null,
            'sku' => 'TEST-' . Str::upper(Str::random(6)),
            'stock' => 5,
            'additional_price' => 0,
            'is_active' => true,
        ]);

        return $product;
    }
}