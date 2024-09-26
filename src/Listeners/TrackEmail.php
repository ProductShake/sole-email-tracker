<?php

namespace Productshake\EmailTracker\Listeners;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

class TrackEmail
{
    public function handle(MessageSent $event)
    {
        if (App::environment('local')) {
            return;
        }

        $message = $event->message;

        $toAddresses = $message->getTo();

        $toEmail = null;
        if (!empty($toAddresses)) {
            $firstAddress = reset($toAddresses);
            $toEmail = $firstAddress instanceof Address ? $firstAddress->getAddress() : $firstAddress;
        }

        // Ensure $toEmail is not empty
        if (empty($toEmail)) {
            $toEmail = 'mail-not-catched@no.mails';
        }

        $subject = $message->getSubject() ?: 'No Subject';

        // Handle different mailer bodies for SwiftMailer and Symfony Mailer
        if (method_exists($message, 'getHtmlBody')) {
            // Symfony Mailer (Laravel 9+)
            $htmlBody = $message->getHtmlBody();
            $textBody = $message->getTextBody();
        } else {
            // SwiftMailer (Laravel 8 and below)
            $htmlBody = $message->getBody();
            $textBody = $message->getBody(); // Fallback to text content if HTML is not available
        }

        $content = $htmlBody ?: $textBody ?: 'No Content';

        $emailData = [
            'to' => $toEmail,
            'subject' => $subject,
            'content' => $content,
            'sent_at' => Carbon::now(),
            'project_ulid' => config('sole-email-tracker.project_ulid'),
        ];

        $client = new Client();

        try {
            $client->post('https://sole.sh/api/v1/email-sent', [
                'json' => $emailData,
            ]);

        } catch (GuzzleException|Exception $e) {
            Log::error('Error sending email data to centralized SaaS system: ', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
