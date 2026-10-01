<x-mail::message>
# Your order has arrived

Your order #{{ $order->id }} ({{ $order->quantity }} × {{ $order->product_name }}) has been delivered to {{ $order->shop->name }}.

Put it on your counter where customers pay - they tap their phone or scan the QR to collect stamps.

<x-mail::button :url="$dashboardUrl">
Open your dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
