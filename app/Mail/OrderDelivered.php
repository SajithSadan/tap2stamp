<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Your order has arrived" - sent when the admin marks an order delivered. */
class OrderDelivered extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your {$this->order->product_name} has been delivered");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.delivered', with: [
            'dashboardUrl' => route('dashboard.index'),
        ]);
    }
}
