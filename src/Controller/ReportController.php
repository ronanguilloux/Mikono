<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\ActivitySummaryCalculator;
use App\Report\ReportMetricsCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reports', name: 'report_')]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly ActivitySummaryCalculator $calculator,
        private readonly ReportMetricsCalculator $metrics,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // summarizeByVolunteer() already sorts by total days descending, so the
        // "Top volunteers" card is the head of this same list — no second pass.
        return $this->render('report/index.html.twig', [
            'metrics' => $this->metrics->calculate(new \DateTimeImmutable('today')),
            'byVolunteer' => $this->calculator->summarizeByVolunteer(),
            'byProject' => $this->calculator->summarizeByProject(),
        ]);
    }
}
