<?php

namespace App\MessageHandler;

use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Twig\Environment;

class CommentMessageHandler implements MessageHandlerInterface
{
    private $spamChecker;
    private $entityManager;
    private $commentRepository;
    private $bus;
    private $workflow;
    private $mailer;
    private $twig;
    private $adminEmail;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        SpamChecker $spamChecker,
        CommentRepository $commentRepository,
        MessageBusInterface $bus,
        WorkflowInterface $commentStateMachine,
//        \Swift_Mailer $mailer,
        MailerInterface $mailer,
        Environment $twig,
        string $adminEmail,
        LoggerInterface $logger = null
        )
    {
        $this->entityManager = $entityManager;
        $this->spamChecker = $spamChecker;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->workflow = $commentStateMachine;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->adminEmail = $adminEmail;
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
//            $this->mailer->send($this->mail
//                ->setSubject('New Comment Posted')
//                ->setTo($this->adminEmail)
//                ->setFrom($this->adminEmail)
//                ->setBody($this->twig->render('emails/comment_notification.html.twig'))
//            );


//           $message = (new \Swift_Message('New Comment Posted'))
//               ->setFrom($this->adminEmail)
//               ->setTo($this->adminEmail)
//               ->setBody(
//                   '<html>' .
//                   '<body>' .
//                   '   <button style="margin:10px;" href="{{ url(\'review_comment\', {id: comment.id}) }}">Accept</button>' .
//                   ' <button href="{{ url(\'review_comment\', { id: comment.id, reject: true }) }}">Reject</button>',
//                   'text/html')
//               ;
//           $this->mailer->send($message);

           $this->mailer->send((new NotificationEmail())
                   ->subject('New comment posted')
                   ->htmlTemplate('emails/comment_notification.html.twig')
                          ->from($this->adminEmail)
                           ->to($this->adminEmail)
                          ->context(['comment' => $comment])
                      );

       } elseif($this->logger){
           $this->logger->debug('Dropping comment message', ['comment' =>$comment->getId(), 'state' => $comment->getState()]);
       }
    }
}
