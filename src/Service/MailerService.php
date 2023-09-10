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
    public function sendEmail()
    {
        $email = (new Email())
            ->from('hello@example.com')
            ->to('you@example.com')
            ->subject("mail de l'application Webtrader")
            ->text("Mail envoyé lorsqu'une action est requise de la part de l'utilisateur.")
            ->html("<p>Il faut encore distinguer entre les différentes actions possibles.</p>");

        // envoi d'un mail
        $this->mailer->send($email);

        $this->logger->info("Mail envoyé par webtrader");
    }
}