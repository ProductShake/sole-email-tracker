<?php

namespace Productshake\EmailTracker\Listeners;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

class TrackEmail
{
    public function handle(MessageSent $event)
    {
        $message = $event->message;

        $toAddresses = $message->getTo();

        $toEmails = array_map(static function ($address, $name) {
            // SwiftMailer returns an associative array where the key is the email and the value is the name
            if ($address instanceof Address) {
                return $address->getAddress();
            }

            if (is_string($address)) {
                return $address; // SwiftMailer scenario
            }

            if (is_string($name) && is_string($address)) {
                return $address; // Fallback for other edge cases
            }
            return null;
        }, array_keys($toAddresses), $toAddresses);

        $toEmails = array_filter($toEmails);
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
            'to' => implode(',', $toEmails),
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
