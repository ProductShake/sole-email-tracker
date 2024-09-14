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
        $toEmails = array_map(static function (Address $address) {
            return $address->getAddress();
        }, $toAddresses);

        $subject = $message->getSubject() ?: 'No Subject';

        $htmlBody = $message->getHtmlBody();
        $textBody = $message->getTextBody();
        $content = $htmlBody ?: $textBody ?: 'No Content';

        $emailData = [
            'to' => implode(',', $toEmails),
            'subject' => $subject,
            'content' => $content, // Store either HTML or text content
            'sent_at' => Carbon::now(),
            'project_ulid' => config('sole-email-tracker.project_ulid'),
        ];

        $client = new Client;

        try {
            $client->post(config('sole-email-tracker.saas_endpoint').'/api/v1/email-sent', [
                'json' => $emailData,
            ]);
        } catch (GuzzleException|Exception $e) {
            Log::error('Error sending email data to centralized SaaS system: '.$e->getMessage());
        }
    }
}
