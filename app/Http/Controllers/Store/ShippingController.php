<?php

namespace App\Http\Controllers\Store;

use App\Exceptions\ShippingException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Services\BiteshipService;
use App\Services\ShippingQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ShippingController extends Controller
{
    public function __construct(
        private readonly BiteshipService $biteship,
        private readonly ShippingQuoteService $quotes,
    ) {
    }

    public function areas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:60'],
        ]);

        try {
            $areas = $this->biteship->searchAreas($validated['q']);
        } catch (ShippingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json([
            'areas' => array_slice($areas, 0, 10),
        ]);
    }

    public function rates(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();

        $validated = $request->validate([
            'address_id' => [
                'nullable',
                'required_without:area_id',
                Rule::exists('addresses', 'id')->where('user_id', $userId),
            ],
            'area_id' => ['nullable', 'required_without:address_id', 'string', 'max:64'],
        ]);

        try {
            $destinationAreaId = $this->destinationAreaId($validated, $userId);

            $cart = Cart::query()->where('user_id', $userId)->first();

            if (! $cart) {
                throw new ShippingException('Bag masih kosong.', 422);
            }

            $options = $this->quotes->forCart($cart, $destinationAreaId);
        } catch (ShippingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json([
            'options' => $options,
        ]);
    }

    private function destinationAreaId(array $validated, int $userId): string
    {
        if (! empty($validated['address_id'])) {
            $areaId = Address::query()
                ->where('user_id', $userId)
                ->whereKey($validated['address_id'])
                ->value('area_id');

            if (! $areaId) {
                throw new ShippingException(
                    'Alamat ini belum memiliki area pengiriman. Silakan pilih ulang area alamat.',
                    422
                );
            }

            return $areaId;
        }

        $area = $this->biteship->findKnownArea((string) $validated['area_id']);

        if ($area === null) {
            throw new ShippingException('Area pengiriman tidak valid. Silakan pilih ulang area.', 422);
        }

        return $area['id'];
    }
}
