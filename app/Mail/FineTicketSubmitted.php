<?php

namespace App\Mail;

use App\Models\EmployeeFine;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FineTicketSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmployeeFine $fine)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('New Employee Fine Ticket')
            ->view('emails.fine-ticket-submitted');
    }
}
