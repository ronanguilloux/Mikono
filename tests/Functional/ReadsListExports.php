<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Reads back the CSV a list's export route just returned (ADR 0029).
 */
trait ReadsListExports
{
    /**
     * @return list<list<string>> the data rows, header row excluded
     */
    private static function exportedRows(KernelBrowser $client, string $url): array
    {
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $content = $client->getInternalResponse()->getContent();
        self::assertStringStartsWith("\u{FEFF}", $content, 'The CSV must start with a UTF-8 BOM so Excel reads accents.');

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, substr($content, 3));
        rewind($stream);

        $rows = [];
        while (false !== $row = fgetcsv($stream, escape: '')) {
            $rows[] = array_map(strval(...), $row);
        }
        fclose($stream);

        array_shift($rows);

        return $rows;
    }
}
