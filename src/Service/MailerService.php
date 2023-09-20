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

    public function __construct(MailerInterface $mailer, LoggerInterface $myAppLogger)
    {
        $this->mailer = $mailer;
        $this->logger = $myAppLogger;
    }

    /**
     * @return void
     */
    public function sendEmail($positions)
    {
        $content = 'Contenu de mon mail de test : ';
        foreach ($positions as $position) {
            $content .= $position . ' ';
        }

        $email = (new Email())
            ->from('mickael.bula@srf.fr')
            ->to('bula.mickael@neuf.fr')
            ->subject("mail de l'application Webtrader")
            ->text("Vos positions ont été actualisées.")
            ->html('<p>' . $content . '</p>');

        // envoi du mail
        try {
            $this->mailer->send($email);
            $this->logger->info("Mail envoyé par webtrader");
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(sprintf("Impossible de se connecter au transport spécifié : %s", $e->getMessage()));
        }
    }
}