<x-mail::message>
# Your order is on its way

Good news, {{ $order->shop->name }}: your order #{{ $order->id }} has been posted.

**{{ $order->quantity }} × {{ $order->product_name }}**

@if ($order->courier || $order->tracking_number)
Courier: {{ $order->courier ?? '–' }}@if ($order->tracking_number) · Tracking number: {{ $order->tracking_number }}@endif

@endif
@if ($order->tracking_url)
<x-mail::button :url="$order->tracking_url">
Track your parcel
</x-mail::button>
@endif

@if ($order->delivery_address)
Delivering to: {{ $order->delivery_address }}
@endif

You can follow it any time under **Orders** in your dashboard: [{{ $ordersUrl }}]({{ $ordersUrl }})

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
