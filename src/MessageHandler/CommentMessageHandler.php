<?php

namespace App\MessageHandler;

use App\ImageOptimizer;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\AdminRecipient;
use Symfony\Component\Workflow\WorkflowInterface;
use App\Notification\CommentReviewNotification;
use Twig\Environment;

class CommentMessageHandler implements MessageHandlerInterface
{
    private $spamChecker;
    private $entityManager;
    private $commentRepository;
    private $bus;
    private $workflow;
//    private $mailer;
    private $notifier;
    private $twig;
    private $imageOptimizer;
    private $adminEmail;
    private $photoDir;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        SpamChecker $spamChecker,
        CommentRepository $commentRepository,
        MessageBusInterface $bus,
        WorkflowInterface $commentStateMachine,
//        \Swift_Mailer $mailer,
//        MailerInterface $mailer,
        NotifierInterface $notifier,
        Environment $twig,
        ImageOptimizer $imageOptimizer,
        string $adminEmail,
        string $photoDir,
        LoggerInterface $logger = null
        )
    {
        $this->entityManager = $entityManager;
        $this->spamChecker = $spamChecker;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->workflow = $commentStateMachine;
//        $this->mailer = $mailer;
        $this->notifier = $notifier;
        $this->twig = $twig;
        $this->imageOptimizer;
        $this->adminEmail = $adminEmail;
        $this->photoDir;
        $this->logger = $logger;
    }

    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }

       if ($this->workflow->can($comment, 'accept')){
           $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
           $transition = 'accept';
           if (2 === $score){
               $transition = 'reject_spam';
           } elseif (1 === $score){
               $transition = 'might_be_spam';
           }
           $this->workflow->apply($comment, $transition);
           $this->entityManager->flush();

           $this->bus->dispatch($message);
       }  elseif ($this->workflow->can($comment, 'publish') || $this->workflow->can($comment, 'publish_ham')){

          $notification = new CommentReviewNotification($comment, $message->getReviewURL());
          $this->notifier->send($notification, ...$this->notifier->getAdminRecipients());

       } elseif($this->logger){
           $this->logger->debug('Dropping comment message', ['comment' =>$comment->getId(), 'state' => $comment->getState()]);
       }

       if ($comment->getState() == 'published'){
           $recipient = new AdminRecipient(
               $comment->getEmail()
           );

           $notification2 = (new Notification('Comment Review'))
               ->content('Your comment was accepted ');

           $this->notifier->send($notification2, $recipient);
       }

    }
}
