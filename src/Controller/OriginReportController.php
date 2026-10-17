<?php

declare(strict_types=1);

namespace App\Controller;

use App\Export\ListExport;
use App\Repository\VolunteerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reports → Volunteers' Origins: volunteers per country of residence, as an
 * interactive world map (jsvectormap, assets/controllers/world_map_controller.js)
 * above a bar chart of every country. Residence, not nationality: nationality
 * is on file for a handful of volunteers only.
 *
 * @phpstan-type OriginRow array{code: string, country: string, volunteers: int}
 */
final class OriginReportController extends AbstractController
{
    private const array COLUMNS = [
        ['key' => 'country', 'label' => 'Country of residence'],
        ['key' => 'code', 'label' => 'Code'],
        ['key' => 'volunteers', 'label' => 'Volunteers'],
        ['key' => 'share', 'label' => 'Share'],
    ];

    /**
     * Lowest count in each bin => its fill. One-hue brand ramp, light to
     * dark, checked with the dataviz ordinal validator: monotone lightness,
     * the lightest step at 2.6:1 against the white page.
     *
     * @var array<int, string>
     */
    private const array BINS = [1 => '#00aed9', 2 => '#0085a6', 5 => '#00647d', 10 => '#004a5c', 25 => '#002129'];

    private const string NO_VOLUNTEERS = '#e2e8f0';

    /**
     * The countries jsvectormap 1.7.0's world map draws. Any other is too
     * small at that scale (Monaco, Singapore…), and the page names it in words.
     */
    private const string DRAWN = 'AE AF AL AM AO AR AT AU AZ BA BD BE BF BG BI BJ BN BO BR BS BT BW BY BZ CA CD CF CG CH CI CL CM CN CO CR CU CY CZ DE DJ DK DO DZ EC EE EG EH ER ES ET FI FJ FK FR GA GB GE GH GL GM GN GQ GR GT GW GY HN HR HT HU ID IE IL IN IQ IR IS IT JM JO JP KE KG KH KP KR KW KZ LA LB LK LR LS LT LU LV LY MA MD ME MG MK ML MM MN MR MW MX MY MZ NA NC NE NG NI NL NO NP NZ OM PA PE PG PH PK PL PR PS PT PY QA RO RS RU RW SA SB SD SE SI SK SL SN SO SR SS SV SY SZ TD TF TG TH TJ TL TM TN TR TT TW TZ UA UG US UY UZ VE VN VU YE ZA ZM ZW';

    public function __construct(
        private readonly VolunteerRepository $volunteers,
    ) {}

    #[Route('/reports/origins', name: 'report_origins', methods: ['GET'])]
    public function index(): Response
    {
        $rows = $this->originRows();
        $countries = array_values(array_filter($rows, static fn(array $row): bool => '' !== $row['code']));
        $recorded = array_sum(array_column($countries, 'volunteers'));
        $drawn = array_flip(explode(' ', self::DRAWN));

        $legend = [];
        $lows = array_keys(self::BINS);
        foreach ($lows as $i => $low) {
            $next = $lows[$i + 1] ?? null;
            $legend[] = [
                'label' => match (true) {
                    null === $next => $low . '+',
                    $next - 1 === $low => (string) $low,
                    default => $low . '–' . ($next - 1),
                },
                'fill' => self::BINS[$low],
            ];
        }

        $fills = [];
        $titles = [];
        foreach ($countries as $row) {
            $fills[$row['code']] = self::fill($row['volunteers']);
            $titles[$row['code']] = $row['country'] . ': ' . (1 === $row['volunteers'] ? '1 volunteer' : $row['volunteers'] . ' volunteers');
        }

        return $this->render('report/origins.html.twig', [
            'total' => array_sum(array_column($rows, 'volunteers')),
            'recorded' => $recorded,
            'countryCount' => \count($countries),
            'legend' => $legend,
            'noVolunteersFill' => self::NO_VOLUNTEERS,
            'fills' => $fills,
            'titles' => $titles,
            'tooSmall' => array_values(array_filter($countries, static fn(array $row): bool => !isset($drawn[$row['code']]))),
            'bars' => array_map(static fn(array $row): array => [
                'label' => $row['country'],
                'value' => $row['volunteers'],
                'detail' => self::share($row['volunteers'], $recorded),
            ], $countries),
        ]);
    }

    #[Route('/reports/origins/export.{format}', name: 'report_origins_export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(string $format): StreamedResponse
    {
        $rows = $this->originRows();
        $recorded = array_sum(array_column(array_filter($rows, static fn(array $row): bool => '' !== $row['code']), 'volunteers'));

        return ListExport::response('origins', $format, self::COLUMNS, array_map(static fn(array $row): array => [
            'country' => $row['country'],
            'code' => '' === $row['code'] ? '—' : $row['code'],
            'volunteers' => (string) $row['volunteers'],
            // A share of those with a country, as on the chart, so the
            // countries add up to 100% and "Not recorded" has none.
            'share' => '' === $row['code'] ? '—' : self::share($row['volunteers'], $recorded),
        ], $rows));
    }

    /**
     * Largest first, then by name; "Not recorded" last. The one source of
     * both the page and its export.
     *
     * @return list<OriginRow>
     */
    private function originRows(): array
    {
        $rows = [];
        $notRecorded = null;
        foreach ($this->volunteers->countByCountryOfResidence() as $code => $volunteers) {
            if ('' === $code) {
                $notRecorded = ['code' => '', 'country' => 'Not recorded', 'volunteers' => $volunteers];
                continue;
            }
            // An imported value Intl doesn't know still shows, as its code.
            $rows[] = ['code' => $code, 'country' => Countries::exists($code) ? Countries::getName($code) : $code, 'volunteers' => $volunteers];
        }

        usort($rows, static fn(array $a, array $b): int => [$b['volunteers'], $a['country']] <=> [$a['volunteers'], $b['country']]);

        return null === $notRecorded ? $rows : [...$rows, $notRecorded];
    }

    private static function share(int $volunteers, int $of): string
    {
        return 0 === $of ? '—' : number_format($volunteers / $of * 100, 1) . '%';
    }

    private static function fill(int $count): string
    {
        $fill = self::NO_VOLUNTEERS;
        foreach (self::BINS as $low => $binFill) {
            if ($count >= $low) {
                $fill = $binFill;
            }
        }

        return $fill;
    }
}
