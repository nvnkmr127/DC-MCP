<?php

namespace App\Modules\Notifications\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $titleText;
    public string $bodyText;

    /**
     * Create a new message instance.
     */
    public function __construct(string $titleText, string $bodyText)
    {
        $this->titleText = $titleText;
        $this->bodyText = $bodyText;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->titleText,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Inter, Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #4f46e5; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0;'>Digicloudify Alert</h2>
                    <h3 style='color: #1e293b; margin-top: 20px;'>{$this->titleText}</h3>
                    <p style='color: #475569; line-height: 1.6; font-size: 16px;'>{$this->bodyText}</p>
                    <div style='margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 15px; font-size: 12px; color: #94a3b8; text-align: center;'>
                        This is an automated notification from Digicloudify. Do not reply directly.
                    </div>
                </div>
            "
        );
    }
}
