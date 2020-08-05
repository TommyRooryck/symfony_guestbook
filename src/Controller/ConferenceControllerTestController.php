<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ConferenceControllerTestController extends AbstractController
{
    /**
     * @Route("/conference/controller/test", name="conference_controller_test")
     */
    public function index()
    {
        return $this->render('conference_controller_test/index.html.twig', [
            'controller_name' => 'ConferenceControllerTestController',
        ]);
    }
}
