<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\QuietProjectFinder;
use App\Report\RosterBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly RosterBuilder $rosters,
        private readonly QuietProjectFinder $quietProjects,
    ) {}

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $today = new \DateTimeImmutable('today');

        return $this->render('dashboard/index.html.twig', [
            'greeting' => $this->greetingFor(new \DateTimeImmutable()),
            'today' => $today,
            'todayRoster' => $this->rosters->buildFor($today),
            'tomorrowRoster' => $this->rosters->buildFor($today->modify('+1 day')),
            'quietProjects' => $this->quietProjects->find($today),
            'quiet_after_days' => QuietProjectFinder::WARN_AFTER_DAYS,
        ]);
    }

    private function greetingFor(\DateTimeImmutable $now): string
    {
        $hour = (int) $now->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
