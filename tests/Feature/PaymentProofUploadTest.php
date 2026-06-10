<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentProofUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_upload_payment_proof_for_pending_order(): void
    {
        Storage::fake('public');

        [$user, $order] = $this->createOrderWithPayment([
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'shipping_status' => 'not_shipped',
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)
            ->post("/orders/{$order->order_code}/payment-proof", [
                'proof_image' => $file,
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('waiting_confirmation', $order->payment_status);
        $this->assertSame('waiting_confirmation', $order->payment->status);
        $this->assertNotNull($order->payment->proof_image);

        Storage::disk('public')->assertExists($order->payment->proof_image);
    }

    public function test_customer_cannot_upload_payment_proof_for_paid_order(): void
    {
        Storage::fake('public');

        [$user, $order] = $this->createOrderWithPayment([
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'shipping_status' => 'not_shipped',
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)
            ->from("/orders/{$order->order_code}")
            ->post("/orders/{$order->order_code}/payment-proof", [
                'proof_image' => $file,
            ])
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHasErrors('proof_image');

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertNull($order->payment->proof_image);
    }

    public function test_customer_cannot_upload_payment_proof_for_failed_order(): void
    {
        Storage::fake('public');

        [$user, $order] = $this->createOrderWithPayment([
            'payment_status' => 'failed',
            'order_status' => 'pending',
            'shipping_status' => 'not_shipped',
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)
            ->from("/orders/{$order->order_code}")
            ->post("/orders/{$order->order_code}/payment-proof", [
                'proof_image' => $file,
            ])
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHasErrors('proof_image');

        $order->refresh();

        $this->assertSame('failed', $order->payment_status);
        $this->assertNull($order->payment->proof_image);
    }

    public function test_customer_cannot_upload_payment_proof_for_expired_order(): void
    {
        Storage::fake('public');

        [$user, $order] = $this->createOrderWithPayment([
            'payment_status' => 'expired',
            'order_status' => 'pending',
            'shipping_status' => 'not_shipped',
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)
            ->from("/orders/{$order->order_code}")
            ->post("/orders/{$order->order_code}/payment-proof", [
                'proof_image' => $file,
            ])
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHasErrors('proof_image');

        $order->refresh();

        $this->assertSame('expired', $order->payment_status);
        $this->assertNull($order->payment->proof_image);
    }

    public function test_customer_cannot_upload_payment_proof_for_cancelled_order(): void
    {
        Storage::fake('public');

        [$user, $order] = $this->createOrderWithPayment([
            'payment_status' => 'pending',
            'order_status' => 'cancelled',
            'shipping_status' => 'not_shipped',
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)
            ->from("/orders/{$order->order_code}")
            ->post("/orders/{$order->order_code}/payment-proof", [
                'proof_image' => $file,
            ])
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHasErrors('proof_image');

        $order->refresh();

        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertNull($order->payment->proof_image);
    }

    public function test_customer_cannot_upload_payment_proof_to_other_users_order(): void
    {
        Storage::fake('public');

        [, $order] = $this->createOrderWithPayment([
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'shipping_status' => 'not_shipped',
        ]);

        $otherUser = User::factory()->create();

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($otherUser)
            ->post("/orders/{$order->order_code}/payment-proof", [
                'proof_image' => $file,
            ])
            ->assertNotFound();

        $order->refresh();

        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->payment->proof_image);
    }

    private function createOrderWithPayment(array $overrides = []): array
    {
        $user = User::factory()->create();

        $order = Order::forceCreate(array_merge([
            'user_id' => $user->id,
            'order_code' => 'NE-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'recipient_name' => 'Test Customer',
            'phone' => '08123456789',
            'province' => 'DIY',
            'city' => 'Yogyakarta',
            'district' => 'Depok',
            'postal_code' => '55281',
            'full_address' => 'Jl. Testing No. 1',
            'subtotal' => 150000,
            'shipping_cost' => 15000,
            'total' => 165000,
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'shipping_status' => 'not_shipped',
            'stock_released' => false,
            'notes' => null,
        ], $overrides));

        Payment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'manual_transfer',
            'amount' => $order->total,
            'status' => $order->payment_status,
            'proof_image' => null,
        ]);

        $order->load('payment');

        return [$user, $order];
    }
}