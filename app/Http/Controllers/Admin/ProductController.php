<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What shops can order. Products are never deleted (past orders point at
 * them) - switching one off takes it off sale. At most one is featured: the
 * one the owner dashboard offers.
 */
class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Products/Index', [
            'products' => Product::withCount(['orders as paid_orders' => fn ($q) => $q->where('status', OrderStatus::Paid)])
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    ...$product->pricing(),
                    'is_active' => $product->is_active,
                    'is_featured' => $product->is_featured,
                    'paid_orders' => $product->paid_orders,
                ]),
        ]);
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        $product = $this->save(new Product, $request->productValues());

        return back()->with('status', "{$product->name} added.");
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $this->save($product, $request->productValues());

        return back()->with('status', "{$product->name} saved.");
    }

    private function save(Product $product, array $values): Product
    {
        return DB::transaction(function () use ($product, $values) {
            if ($values['is_featured']) {
                Product::whereKeyNot($product->id ?? 0)->update(['is_featured' => false]);
            }

            $product->fill($values)->save();

            return $product;
        });
    }
}
