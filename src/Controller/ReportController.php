<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\ActivitySummaryCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reports', name: 'report_')]
final class ReportController extends AbstractController
{
    public function __construct(private readonly ActivitySummaryCalculator $calculator) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('report/index.html.twig', [
            'byVolunteer' => $this->calculator->summarizeByVolunteer(),
            'byProject' => $this->calculator->summarizeByProject(),
        ]);
    }
}
