<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStage;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every shop's product orders, for posting them out. Also where the admin
 * records an order paid outside the app (bank transfer / cash), e.g. agreed
 * with the owner on the phone - that hides the owner's order banner too.
 */
class OrderController extends Controller
{
    /** Period filter (days), like the admin dashboard's cards. */
    public const RANGES = [7, 30, 90, 365];

    public const DEFAULT_RANGE = 30;

    public function index(Request $request): Response
    {
        $days = in_array($request->integer('days'), self::RANGES, true) ? $request->integer('days') : self::DEFAULT_RANGE;
        $start = today()->subDays($days - 1);
        $previousStart = $start->copy()->subDays($days);

        return Inertia::render('Admin/Orders/Index', [
            'days' => $days,
            'ranges' => self::RANGES,
            'insights' => [
                'current' => $this->periodTotals($start, null),
                'previous' => $this->periodTotals($previousStart, $start),
                // Work in hand right now, whatever the period.
                'to_do' => Order::where('status', OrderStatus::Paid)->whereNull('dispatched_at')->whereNull('delivered_at')->count(),
                'on_the_way' => Order::where('status', OrderStatus::Paid)->whereNotNull('dispatched_at')->whereNull('delivered_at')->count(),
            ],
            'sales' => $this->salesPerDay($start, $days),
            // For "New order": any shop, any product on sale, and how it's paid.
            'shops' => Shop::orderBy('name')->get(['id', 'name', 'slug']),
            'products' => Product::active()->orderByDesc('is_featured')->orderBy('name')->get(['id', 'name', 'price_pence', 'price_tiers']),
            'paymentMethods' => collect(PaymentMethod::manual())->map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()]),
            'bankDetailsSet' => filled(Setting::get(Setting::BANK_DETAILS)),
            // Orders placed in the period, plus any older ones not delivered
            // yet - unfinished work never drops out of view. All rows at once,
            // searched/sorted in the browser like the Shops grid.
            'orders' => Order::with(['shop:id,name,slug', 'placedBy:id,name,role'])
                ->where(fn ($q) => $q
                    ->where('created_at', '>=', $start)
                    ->orWhere(fn ($open) => $open->where('status', OrderStatus::Paid)->whereNull('delivered_at')))
                ->latest()
                ->get()
                ->map(fn (Order $order) => self::row($order)),
        ]);
    }

    /**
     * One row per day of the period (zero-filled): money collected and paid
     * orders, by the day they were paid. One grouped query.
     *
     * @return list<array{date: string, label: string, weekday: string, collected: int, orders: int}>
     */
    private function salesPerDay($start, int $days): array
    {
        $byDay = Order::where('status', OrderStatus::Paid)
            ->where('paid_at', '>=', $start)
            ->selectRaw('DATE(paid_at) AS day, COALESCE(SUM(total_pence), 0) AS collected, COUNT(*) AS orders')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(range(0, $days - 1))->map(function (int $i) use ($start, $byDay) {
            $day = $start->copy()->addDays($i);
            $row = $byDay[$day->format('Y-m-d')] ?? null;

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('j M'),
                'weekday' => $day->format('D'),
                'collected' => (int) ($row->collected ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        })->all();
    }

    /**
     * Paid orders from $from (up to $to, if given): how many, money collected (online vs taken
     * by hand), items sold.
     *
     * @return array{orders: int, collected_pence: int, online_pence: int, manual_pence: int, units: int}
     */
    private function periodTotals($from, $to = null): array
    {
        $row = Order::where('status', OrderStatus::Paid)
            ->where('paid_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('paid_at', '<', $to))
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(total_pence), 0) AS collected, COALESCE(SUM(quantity), 0) AS units')
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_method = ? THEN total_pence ELSE 0 END), 0) AS online', [PaymentMethod::Stripe->value])
            ->first();

        return [
            'orders' => (int) $row->orders,
            'collected_pence' => (int) $row->collected,
            'online_pence' => (int) $row->online,
            'manual_pence' => (int) $row->collected - (int) $row->online,
            'units' => (int) $row->units,
        ];
    }

    public function store(StoreManualOrderRequest $request, Shop $shop, OrderService $orders): RedirectResponse
    {
        $method = PaymentMethod::from($request->string('payment_method')->value());

        $orders->recordManual(
            $shop,
            Product::findOrFail($request->integer('product_id')),
            $request->user(),
            $method,
            $request->integer('quantity'),
            $request->amountPence(),
            $request->boolean('paid'),
            $request->filled('note') ? $request->string('note')->trim()->value() : null,
        );

        return back()->with('status', $request->boolean('paid') || $method === PaymentMethod::Free
            ? "Order created for {$shop->name}."
            : "Order created for {$shop->name} - awaiting their payment.");
    }

    /** Corrects an order (see UpdateOrderRequest for what each kind may change). */
    public function update(UpdateOrderRequest $request, Order $order, OrderService $orders): RedirectResponse
    {
        $clean = fn (string $field) => $request->filled($field) ? trim($request->string($field)->value()) : null;

        $orders->update($order, [
            'delivery_address' => $clean('delivery_address'),
            'note' => $clean('note'),
            'product_id' => $request->integer('product_id'),
            'quantity' => $request->integer('quantity'),
            'payment_method' => $request->filled('payment_method') ? PaymentMethod::from($request->string('payment_method')->value()) : null,
            'total_pence' => $request->amountPence(),
        ]);

        return back()->with('status', "Order #{$order->id} updated.");
    }

    /** The shop's bank transfer arrived (or cash was taken): start the order. */
    public function confirmPayment(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->awaitingManualPayment(), 422, 'This order is not waiting for a payment.');

        $method = $request->validate([
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::BankTransfer, PaymentMethod::Cash])],
        ])['payment_method'];

        $orders->confirmPayment($order, PaymentMethod::from($method));

        return back()->with('status', "Payment confirmed for order #{$order->id}.");
    }

    /** Calls off an unpaid order (the transfer never came, or the shop changed its mind). */
    public function cancel(Order $order, OrderService $orders): RedirectResponse
    {
        abort_if($order->isPaid(), 422, 'Paid orders cannot be cancelled.');

        $orders->cancel($order);

        return back()->with('status', "Order #{$order->id} cancelled.");
    }

    /**
     * Moves a paid order along (or back): received → processing →
     * dispatched (+ courier / tracking) → delivered. The owner sees it on
     * their Orders page, and is emailed at dispatched and delivered.
     */
    public function stage(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->isPaid(), 422, 'This order has not been paid yet.');

        $input = $request->validate([
            'stage' => ['required', Rule::enum(OrderStage::class)],
            'courier' => ['nullable', 'string', 'max:60'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:500'],
        ]);
        $stage = OrderStage::from($input['stage']);

        // Only the tracking fields actually sent are changed; the rest are kept.
        $tracking = collect(['courier', 'tracking_number', 'tracking_url'])
            ->filter(fn (string $field) => $request->exists($field))
            ->mapWithKeys(fn (string $field) => [$field => filled($input[$field] ?? null) ? trim($input[$field]) : null])
            ->all();

        $orders->moveTo($order, $stage, $tracking);

        return back()->with('status', "Order #{$order->id} is now: {$stage->label()}.");
    }

    /** One order as the admin pages show it (Orders grid and a shop's settings page). */
    public static function row(Order $order): array
    {
        return [
            ...$order->summary(),
            'shop_id' => $order->shop_id,
            'shop_name' => $order->shop?->name,
            'product_id' => $order->product_id,
            'placed_by' => $order->placedBy?->name,
            'note' => $order->note,
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }
}
