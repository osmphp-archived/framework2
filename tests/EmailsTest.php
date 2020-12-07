<?php

namespace Osm\Framework\Tests;

use Osm\Core\App;
use Osm\Framework\Emails\Module;
use Osm\Framework\Emails\Views\Email;
use Osm\Framework\Testing\Tests\UnitTestCase;
use Osm\Framework\Views\Views\Text;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;

/**
 * @property Module $email_module @required
 */
class EmailsTest extends UnitTestCase
{
    public function __get($property) {
        global $osm_app; /* @var App $osm_app */

        switch ($property) {
            case 'email_module': return $osm_app->modules['Osm_Framework_Emails'];
        }

        return parent::__get($property);
    }

    public function testThatSwiftMailerWorks() {
        if (!env('SMTP_USER')) {
            $this->assertTrue(true);
            return;
        }

        // Create the Transport
        $transport = new Swift_SmtpTransport(env('SMTP_HOST'),
            env('SMTP_PORT'), env('SMTP_ENCRYPTION'));
        $transport
            ->setUsername(env('SMTP_USER'))
            ->setPassword(env('SMTP_PASSWORD'));

        // Create the Mailer using your created Transport
        $mailer = new Swift_Mailer($transport);

        // Create a message
        $message = (new Swift_Message('SwiftMailer Works!'))
            ->setFrom(['example@domain.com' => 'The Sender'])
            ->setTo(['another@domain.com'])
            ->setBody('My <em>amazing</em> body', 'text/html')
            ->addPart('My amazing body in plain text', 'text/plain')
          ;

        // Send the message and assert it is successful
        $this->assertEquals(1, $mailer->send($message));
    }

    public function testEmailApi() {
        $sent = $this->email_module->send((new Swift_Message('Module send() Works!'))
            ->setFrom(['example@domain.com' => 'The Sender'])
            ->setTo(['another@domain.com'])
            ->setBody('My <em>amazing</em> body', 'text/html')
            ->addPart('My amazing body in plain text', 'text/plain'));

        $this->assertEquals(1, $sent);
    }

    public function testEmailApiUsingHelperFunction() {
        try {
            global $osm_app; /* @var App $osm_app */

            $osm_app->area = 'test';

            $sent = osm_send_email(Email::new([
                'from' => ['example@domain.com' => 'The Sender'],
                'to' => 'recipient@example.com',
                'subject' => osm_t("osm_send_email() Works!"),
                'body' => Text::new(['contents' => 'Hello']),
            ]));

            $this->assertEquals($osm_app->settings->use_email_queue ? 0 : 1, $sent);
        }
        finally {
            // further tests may be corrupted as 'test' area is propagated
            // through the all the objects, so we create new, fresh app instance
            $this->recreateApp();
        }
    }
}