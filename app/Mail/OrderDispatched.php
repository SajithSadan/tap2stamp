<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Your order is on its way" - sent when the admin marks an order dispatched. */
class OrderDispatched extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your {$this->order->product_name} is on its way");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.dispatched', with: [
            'ordersUrl' => route('dashboard.orders'),
        ]);
    }
}
