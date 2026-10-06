<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductLabelController extends Controller
{
    public const SIZES = [
        '2x1' => ['label' => '2" × 1" (50.8 × 25.4 mm)', 'width' => 50.8, 'height' => 25.4],
        '50x30' => ['label' => '50 × 30 mm', 'width' => 50, 'height' => 30],
        '40x25' => ['label' => '40 × 25 mm', 'width' => 40, 'height' => 25],
        '58x40' => ['label' => '58 × 40 mm', 'width' => 58, 'height' => 40],
    ];

    private const MAX_LABELS = 1000;

    public function index(Request $request): View
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:500'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:500'],
            'size' => ['nullable', 'string'],
        ]);

        $quantities = collect($data['items'] ?? [])->map(fn ($qty) => (int) $qty);
        foreach ($data['ids'] ?? [] as $id) {
            $quantities->put((int) $id, $quantities->get((int) $id, (int) ($data['copies'] ?? 1)));
        }

        $products = Product::query()
            ->whereIn('id', $quantities->keys())
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $sizeKey = array_key_exists($data['size'] ?? '', self::SIZES) ? $data['size'] : '2x1';
        $total = min(self::MAX_LABELS, $products->sum(fn (Product $p) => $quantities->get($p->id, 0)));

        return view('inventory.products.labels', [
            'products' => $products,
            'quantities' => $quantities,
            'sizes' => self::SIZES,
            'sizeKey' => $sizeKey,
            'size' => self::SIZES[$sizeKey],
            'total' => $total,
            'truncated' => $products->sum(fn (Product $p) => $quantities->get($p->id, 0)) > self::MAX_LABELS,
            'maxLabels' => self::MAX_LABELS,
        ]);
    }
}
