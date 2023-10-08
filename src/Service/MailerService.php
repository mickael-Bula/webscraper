<?php

namespace App\Service;

use App\Entity\Position;
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
        //FIX: Lorsqu'une position change de statut, un mail est envoyé : il faut gérer ce cas d'un paramètre qui n'est pas un tableau

        // TODO : gérer le message envoyé avec les infos liées au statut des positions touchées notamment. A voir comment formuler le message
        $content = 'Contenu de mon mail de test : ';
        foreach ($positions as $position) {
            /** @var Position $position */
             $content .= 'La position avec une limite d\'achat sur le CAC à ' . $position->getBuyLimit()->getBuyLimit() . ' points a été touchée.' . PHP_EOL;
             $content .= 'La limite d\'achat sur le LVC est ' . $position->getBuyLimit()->getLvcBuyLimit() . ' €' . PHP_EOL;
             $content .= "Le statut de la position passe du statut xxx à yyy";
        }

        $email = (new Email())
            ->from('mickael.bula@sfr.fr')
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