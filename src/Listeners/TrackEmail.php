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

        $toAddresses = $message->getTo() ?? [];
        $toEmails = array_filter(array_map(static function ($address) {
            // SwiftMailer uses a string for the email address, while Symfony Mailer uses the Address object
            if ($address instanceof Address) {
                return $address->getAddress();
            }
            if (is_string($address)) {
                return $address;
            }
            return null;
        }, $toAddresses));

        if (empty($toEmails)) {
            Log::warning('No valid email addresses found in the "To" field.');
            return;
        }

        $subject = $message->getSubject() ?: 'No Subject';

        $htmlBody = $message->getHtmlBody();
        $textBody = $message->getTextBody();
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
