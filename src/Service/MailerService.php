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

    public function createMailContentWhenPositionIsOpened(Position $position): void
    {
        if ($position->getBuyLimit() && $position->getBuyLimit()->getBuyLimit()) {
            $content = "Ouverture d'une position.\n" ;
            $content .= "La position avec une limite d'achat sur le CAC à {$position->getBuyTarget()} points a été touchée.\n";
            $content .= "La limite d'achat sur le LVC est {$position->getLvcBuyTarget()} €.\n";
            $content .= "Le statut de la position passe de isWaiting à isRunning";
            $this->sendEmail($content);
        } else {
            $this->logger->info("Mail non expédié : vérifiez la position {$position}");
        }
    }

    public function createMailContentWhenPositionIsClosed(Position $position): void
    {
        if ($position->getBuyLimit() && $position->getBuyLimit()->getBuyLimit()) {
            $content = 'Contenu de mon mail de test : ';
            $content .= "La position avec une limite de vente sur le CAC à {$position->getSellTarget()} points a été touchée.\n";
            $content .= "La limite de vente sur le LVC est {$position->getLvcSellTarget()} €.\n";
            $content .= "Le statut de la position passe de isRunning à isClosed";
            $this->sendEmail($content);
        } else {
            $this->logger->info("Mail non expédié : vérifiez la position {$position}");
        }
    }

    public function createMailContentWhenPositionsAreUpdated(array $data): void
    {
        $index = 1;
        $content = "Voici les niveaux d'achats relatif au LAST_HIGH={$data[0]->getBuyLimit()->getBuyLimit()} pts pour le CAC :\n";
        foreach ($data as $position) {
            /** @var Position $position */
            $content .= "Position {(string)$index} : +{$position->getQuantity()} LVC à {$position->getLvcBuyTarget()} €, CAC={$position->getBuyTarget()}.\n";
            $index++;
        }
        $this->sendEmail($content);
    }

    /**
     * @param $content
     * @return void
     */
    public function sendEmail($content): void
    {
        // préparation du mail
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