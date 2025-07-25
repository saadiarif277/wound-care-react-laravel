<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Order\ProductRequest;
use App\Models\User;

class OrderSubmissionNotification extends Mailable
{
    use Queueable, SerializesModels;

    public ProductRequest $order;
    public User $submitter;
    public ?string $adminNote;

    /**
     * Create a new message instance.
     */
    public function __construct(ProductRequest $order, User $submitter, ?string $adminNote = null)
    {
        $this->order = $order;
        $this->submitter = $submitter;
        $this->adminNote = $adminNote;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order Submitted by {$this->submitter->name} – {$this->order->request_number}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-submission-notification',
            with: [
                'order' => $this->order,
                'submitter' => $this->submitter,
                'adminNote' => $this->adminNote,
                'orderUrl' => route('admin.orders.show', $this->order->id),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
