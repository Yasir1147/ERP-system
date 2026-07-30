<?php

namespace App\Mail;

use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentExpiryReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmployeeDocument $document,
        public int $daysUntilExpiry,
    ) {
    }

    public function build(): self
    {
        $status = $this->daysUntilExpiry < 0
            ? abs($this->daysUntilExpiry).' days expired'
            : $this->daysUntilExpiry.' days remaining';

        return $this
            ->subject("Document Expiry Reminder: {$this->document->category?->name} ({$status})")
            ->view('emails.document-expiry-reminder');
    }
}
