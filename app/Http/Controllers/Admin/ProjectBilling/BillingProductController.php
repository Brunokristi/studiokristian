<?php

namespace App\Http\Controllers\Admin\ProjectBilling;

use App\Http\Controllers\Controller;
use App\Models\BillingProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BillingProductController extends Controller
{
    public function index(Request $request)
    {
        $products = BillingProduct::query()
            ->when($request->boolean('active_only'), fn ($query) => $query->where('active', true))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $product = BillingProduct::query()->create($this->validated($request));

        return response()->json(['data' => $product], 201);
    }

    public function update(Request $request, BillingProduct $billingProduct)
    {
        $billingProduct->update($this->validated($request));

        return response()->json(['data' => $billingProduct->fresh()]);
    }

    public function destroy(BillingProduct $billingProduct): Response
    {
        // Keep historical invoices intact - deactivate instead of deleting.
        $billingProduct->update(['active' => false]);

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_type' => ['required', Rule::in(BillingProduct::TYPES)],
            'interval' => ['nullable', Rule::in(BillingProduct::INTERVALS), 'required_if:billing_type,recurring'],
            'interval_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'active' => ['boolean'],
        ]);

        $data['currency'] = strtoupper($data['currency'] ?? config('billing.currency'));

        if ($data['billing_type'] === BillingProduct::TYPE_ONE_TIME) {
            $data['interval'] = null;
            $data['interval_count'] = 1;
        }

        return $data;
    }
}
