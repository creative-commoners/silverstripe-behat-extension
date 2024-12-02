<?php

namespace SilverStripe\BehatExtension\Utility;

use InvalidArgumentException;
use SilverStripe\Control\Email\Email;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use SilverStripe\Dev\TestMailer as BaseTestMailer;
use SilverStripe\TestSession\TestSessionEnvironment;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Same principle as core TestMailer class,
 * but saves emails in {@link TestSessionEnvironment}
 * to share the state between PHP calls (CLI vs. browser).
 */
class TestMailer extends BaseTestMailer
{
    /**
     * @var TestSessionEnvironment
     */
    protected $testSessionEnvironment;

    public function __construct(
        TransportInterface $transport,
        EventDispatcherInterface $dispatcher
    ) {
        parent::__construct($transport, $dispatcher);
        $this->testSessionEnvironment = TestSessionEnvironment::singleton();
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        parent::send($message, $envelope);
        /** @var Email $email */
        $email = $message;
        $data = $this->createData($email);
        // save email to testsession state
        $state = $this->testSessionEnvironment->getState();
        if (!isset($state->emails)) {
            $state->emails = array();
        }
        $state->emails[] = array_filter($data ?? []);
        $this->testSessionEnvironment->applyState($state);
    }

    /**
     * Clear the log of emails sent
     */
    public function clearEmails(): void
    {
        $state = $this->testSessionEnvironment->getState();
        if (isset($state->emails)) {
            unset($state->emails);
        }
        $this->testSessionEnvironment->applyState($state);
    }

    public function findEmail(
        string $to,
        ?string $from = null,
        ?string $subject = null,
        ?string $content = null
    ): ?array {
        $matches = $this->findEmails($to, $from, $subject, $content);
        //got the count of matches emails
        $emailCount = count($matches ?? []);
        //get the last(latest) one

        return $matches ? $matches[$emailCount-1] : null;
    }

    /**
     * Search for all emails.
     * All of the parameters can either be a string, or, if they start with "/", a PREG-compatible regular expression.
     *
     * @param $to
     * @param $from
     * @param $subject
     * @param $content
     * @return array Contains the keys: 'type', 'to', 'from', 'subject', 'content', 'plainContent', 'attachedFiles',
     *               'customHeaders', 'htmlContent', 'inlineImages'
     */
    public function findEmails($to = null, $from = null, $subject = null, $content = null)
    {
        $matches = array();
        $args = func_get_args();
        $state = $this->testSessionEnvironment->getState();
        $emails = isset($state->emails) ? $state->emails : array();
        foreach ($emails as $email) {
            $matched = true;

            foreach (array('To', 'From', 'Subject', 'Content') as $i => $field) {
                if (!isset($email->$field)) {
                    continue;
                }
                $value = (isset($args[$i])) ? $args[$i] : null;
                if ($value) {
                    if ($value[0] == '/') {
                        $matched = preg_match($value ?? '', $email->$field ?? '');
                    } else {
                        $matched = ($value == $email->$field);
                    }
                    if (!$matched) {
                        break;
                    }
                }
            }
            if ($matched) {
                $matches[] = json_decode(json_encode($email), true);
            }
        }

        return $matches;
    }

    protected function saveEmail(array $data)
    {
        $state = $this->testSessionEnvironment->getState();
        if (!isset($state->emails)) {
            $state->emails = array();
        }
        $state->emails[] = array_filter($data ?? []);
        $this->testSessionEnvironment->applyState($state);
    }
}
