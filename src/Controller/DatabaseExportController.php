<?php

declare(strict_types=1);

namespace App\Controller;

use App\Export\DatabaseExport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Every table in one zip. Admin only, unlike the per-list exports: it carries
 * sign-in IPs, gender and emergency contacts in bulk. See ADR 0039.
 */
#[IsGranted('ROLE_ADMIN')]
final class DatabaseExportController extends AbstractController
{
    #[Route('/database/export.zip', name: 'database_export', methods: ['GET'])]
    public function export(DatabaseExport $export): BinaryFileResponse
    {
        $response = new BinaryFileResponse($export->writeZip(), headers: ['Content-Type' => 'application/zip']);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            \sprintf('mikono-database-%s.zip', new \DateTimeImmutable('today')->format('Y-m-d')),
        );

        return $response->deleteFileAfterSend();
    }
}
