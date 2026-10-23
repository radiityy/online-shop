<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\BiteshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function __construct(private readonly BiteshipService $biteship)
    {
    }

    public function index(): Response
    {
        $addresses = Address::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(function (Address $address) {
                return [
                    'id' => $address->id,
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'province' => $address->province,
                    'city' => $address->city,
                    'district' => $address->district,
                    'area_id' => $address->area_id,
                    'area_name' => $address->area_name,
                    'postal_code' => $address->postal_code,
                    'full_address' => $address->full_address,
                    'is_default' => $address->is_default,
                ];
            })
            ->values();

        return Inertia::render('Store/Account/Addresses/Index', [
            'addresses' => $addresses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAddress($request);

        $area = $this->resolveArea($validated['area_id']);

        $hasAddress = Address::query()
            ->where('user_id', Auth::id())
            ->exists();

        $isDefault = (bool) ($validated['is_default'] ?? false) || ! $hasAddress;

        if ($isDefault) {
            Address::query()
                ->where('user_id', Auth::id())
                ->update(['is_default' => false]);
        }

        Address::query()->create([
            'user_id' => Auth::id(),
            'recipient_name' => $validated['recipient_name'],
            'phone' => $validated['phone'],
            'province' => $area['province'],
            'city' => $area['city'],
            'district' => $area['district'],
            'area_id' => $area['id'],
            'area_name' => $area['name'],
            'postal_code' => $validated['postal_code'] ?? $area['postal_code'],
            'full_address' => $validated['full_address'],
            'is_default' => $isDefault,
        ]);

        return back()->with('success', 'Alamat berhasil ditambahkan.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeAddress($address);

        $validated = $this->validateAddress($request);

        $isDefault = (bool) ($validated['is_default'] ?? false);

        if ($isDefault) {
            Address::query()
                ->where('user_id', Auth::id())
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $attributes = [
            'recipient_name' => $validated['recipient_name'],
            'phone' => $validated['phone'],
            'full_address' => $validated['full_address'],
            'is_default' => $isDefault,
        ];

        if ($address->area_id !== null && $address->area_id === $validated['area_id']) {
            $attributes['postal_code'] = $validated['postal_code'] ?? $address->postal_code;
        } else {
            $area = $this->resolveArea($validated['area_id']);

            $attributes['province'] = $area['province'];
            $attributes['city'] = $area['city'];
            $attributes['district'] = $area['district'];
            $attributes['area_id'] = $area['id'];
            $attributes['area_name'] = $area['name'];
            $attributes['postal_code'] = $validated['postal_code'] ?? $area['postal_code'];
        }

        $address->update($attributes);

        return back()->with('success', 'Alamat berhasil diperbarui.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorizeAddress($address);

        $wasDefault = $address->is_default;

        $address->delete();

        if ($wasDefault) {
            $nextAddress = Address::query()
                ->where('user_id', Auth::id())
                ->latest()
                ->first();

            if ($nextAddress) {
                $nextAddress->update([
                    'is_default' => true,
                ]);
            }
        }

        return back()->with('success', 'Alamat berhasil dihapus.');
    }

    public function setDefault(Address $address): RedirectResponse
    {
        $this->authorizeAddress($address);

        Address::query()
            ->where('user_id', Auth::id())
            ->update(['is_default' => false]);

        $address->update([
            'is_default' => true,
        ]);

        return back()->with('success', 'Alamat utama berhasil diubah.');
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'area_id' => ['required', 'string', 'max:64'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'full_address' => ['required', 'string', 'max:500'],
            'is_default' => ['boolean'],
        ]);
    }

    private function resolveArea(string $areaId): array
    {
        $area = $this->biteship->findKnownArea($areaId);

        if ($area === null) {
            throw ValidationException::withMessages([
                'area_id' => 'Area tidak valid atau pencarian sudah kedaluwarsa. Cari dan pilih ulang areanya.',
            ]);
        }

        return $area;
    }

    private function authorizeAddress(Address $address): void
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }
    }
}