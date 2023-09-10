<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * @return void
     * @throws TransportExceptionInterface
     */
    public function sendEmail($positions)
    {
        $email = (new Email())
            ->from('hello@example.com')
            ->to('you@example.com')
            ->subject("mail de l'application Webtrader")
            ->text("Vos positions ont été actualisées.");

        foreach ($positions as $position) {
            $email->html("<p>{$position}</p>");
        }

        // envoi du mail
        $this->mailer->send($email);

        $this->logger->info("Mail envoyé par webtrader");
    }
}