<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Volunteer;
use App\Enum\ActivityDuration;
use App\Factory\ActivityFactory;
use App\Factory\BranchFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class VolunteerControllerTest extends WebTestCase
{
    use ReadsListExports;

    #[Test]
    public function theExportCarriesTheOnScreenSortOrEveryRowInDefaultOrder(): void
    {
        $client = static::createClient();
        VolunteerFactory::new()->inactive()->create(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma']);
        $client->loginUser(UserFactory::createOne());

        $sorted = self::exportedRows($client, '/volunteers/export.csv?sort=name&direction=asc');
        self::assertSame(['Aisha Achieng', 'Zawadi Zuma'], array_column($sorted, 0));

        // The default order is active first, and the status column says so.
        $whole = self::exportedRows($client, '/volunteers/export.csv');
        self::assertSame(['Zawadi Zuma', 'Aisha Achieng'], array_column($whole, 0));
        self::assertSame(['Active', 'Inactive'], array_column($whole, 3));
    }

    #[Test]
    public function indexListsSeededVolunteers(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
    }

    /**
     * Volunteers leave after a few weeks, so someone who finished their stint
     * shouldn't sit between two people working this week. Nobody is hidden —
     * active status only decides the default order.
     */
    #[Test]
    public function theIndexListsActiveVolunteersBeforeInactiveOnes(): void
    {
        $client = static::createClient();
        VolunteerFactory::new()->inactive()->create(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        // Zuma sorts after Achieng by name; active-first is what puts her top.
        self::assertStringContainsString('Zawadi Zuma', $crawler->filter('table tbody tr')->first()->text());
    }

    #[Test]
    public function newWithValidDataPersistsAndRedirects(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers/new');

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Grace',
            'volunteer_form[lastName]' => 'Wanjiru',
            'volunteer_form[email]' => 'grace@example.org',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Grace Wanjiru');
    }

    #[Test]
    public function newWithInvalidDataIsUnprocessableAndReshowsErrors(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers/new');

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => '',
            'volunteer_form[lastName]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function showDisplaysVolunteerDetailsAndTimeline(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge', 'notes' => 'Fluent in Swahili.']);
        ActivityFactory::createOne([
            'volunteer' => $volunteer,
            'date' => new \DateTimeImmutable('yesterday'),
            'duration' => ActivityDuration::FullDay,
        ]);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', "/volunteers/{$volunteer->getId()}");

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
        self::assertSelectorTextContains('body', 'Fluent in Swahili.');
        self::assertSelectorTextContains('body', 'Activities logged');
        self::assertSelectorTextContains('body', 'Full day');
    }

    #[Test]
    public function showTagsAFutureActivityAsPlanned(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => new \DateTimeImmutable('+1 week')]);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', "/volunteers/{$volunteer->getId()}");

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Planned');
    }

    #[Test]
    public function showLinksToTheVolunteersFilteredActivityList(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => new \DateTimeImmutable('yesterday')]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}");
        $link = $crawler->selectLink('See in Activities →');

        self::assertCount(1, $link);
        self::assertSame("/activities?volunteer={$volunteer->getId()}", $link->attr('href'));

        // The timeline is read-only; the point of the link is the editable list.
        $client->click($link->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Activities — Aisha Njoroge');
    }

    #[Test]
    public function editUpdatesTheVolunteer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge', 'phone' => '+254700000000']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/edit");

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Aisha',
            'volunteer_form[lastName]' => 'Njoroge',
            'volunteer_form[phone]' => '+254711111111',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', '+254711111111');
    }

    #[Test]
    public function deleteRemovesAVolunteerWithNoActivities(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No volunteers yet');
    }

    /**
     * The index no longer offers Delete once a volunteer has activity, so the
     * way to reach the server guard is the way a reader would in real life:
     * with a page rendered before the activity existed. That stale-tab case is
     * exactly why the guard stays in the controller rather than moving into
     * the view.
     */
    #[Test]
    public function deleteIsBlockedWhenAnActivityReferencesTheVolunteer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');

        ActivityFactory::createOne(['volunteer' => $volunteer]);
        $client->submitForm('Delete');

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Aisha Njoroge');
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
    }

    #[Test]
    public function theIndexShowsDeleteAsUnavailableForAVolunteerWithActivity(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['volunteer' => $volunteer]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        self::assertResponseIsSuccessful();
        // No form to submit, so nothing to confirm and then be refused.
        self::assertCount(0, $crawler->filter('table tbody form'));

        $inert = $crawler->filter('table tbody [aria-disabled="true"]');
        self::assertCount(1, $inert);
        self::assertStringContainsString('Delete', $inert->text());
        // The same sentence the flash would have shown, ahead of the attempt.
        self::assertStringContainsString(
            'Cannot delete Aisha Njoroge — 1 activity references them.',
            (string) $inert->attr('title'),
        );
    }

    #[Test]
    public function theIndexKeepsDeleteForAVolunteerWithNoActivity(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        self::assertCount(1, $crawler->filter('table tbody form'));
        self::assertCount(0, $crawler->filter('table tbody [aria-disabled="true"]'));
    }

    #[Test]
    public function theIndexGuardsOnlyTheRowsWithActivity(): void
    {
        $client = static::createClient();
        foreach (['Aisha Njoroge', 'Grace Wanjiru'] as $fullName) {
            [$firstName, $lastName] = explode(' ', $fullName, 2);
            ActivityFactory::createOne([
                'volunteer' => VolunteerFactory::createOne(['firstName' => $firstName, 'lastName' => $lastName]),
            ]);
        }
        VolunteerFactory::createOne(['firstName' => 'Susan', 'lastName' => 'Njoki']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers');

        self::assertCount(2, $crawler->filter('table tbody [aria-disabled="true"]'));
        self::assertCount(1, $crawler->filter('table tbody form'));
        // The reason in words is on each volunteer's own edit screen, not here.
        self::assertCount(0, $crawler->filter('[data-delete-guard-note]'));
    }

    #[Test]
    public function theEditScreenSaysWhyDeleteIsUnavailable(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['volunteer' => $volunteer]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers/' . $volunteer->getId() . '/edit');

        self::assertResponseIsSuccessful();
        $note = $crawler->filter('[data-delete-guard-note]');
        self::assertCount(1, $note);
        // Word for word what the index's inert Delete and the flash both say.
        self::assertStringContainsString(
            'Cannot delete Aisha Njoroge — 1 activity references them.',
            $note->text(),
        );
        self::assertSame(
            '/activities?volunteer=' . $volunteer->getId(),
            $note->filter('a')->attr('href'),
        );
    }

    #[Test]
    public function theEditScreenHasNoNoteForAVolunteerWithNoActivity(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers/' . $volunteer->getId() . '/edit');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-delete-guard-note]'));
    }

    #[Test]
    public function theIndexPaginatesAtTwentyFivePerPage(): void
    {
        $client = static::createClient();
        VolunteerFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/volunteers');
        self::assertCount(25, $crawler->filter('table tbody tr'));

        $crawler = $client->request('GET', '/volunteers?page=2');
        self::assertCount(1, $crawler->filter('table tbody tr'));
    }

    #[Test]
    public function theIndexSortsByARequestedColumn(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma']);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/volunteers?sort=name&direction=asc');
        self::assertStringContainsString('Aisha Achieng', $crawler->filter('table tbody tr')->first()->text());

        $crawler = $client->request('GET', '/volunteers?sort=name&direction=desc');
        self::assertStringContainsString('Zawadi Zuma', $crawler->filter('table tbody tr')->first()->text());
    }

    /**
     * The map is the whitelist, so a stale bookmark or a hand-edited URL falls
     * back to the default order instead of erroring — the same posture
     * ListPaginator already keeps for `page` and `perPage`.
     */
    #[Test]
    public function theIndexShrugsOffAnUnknownSortColumn(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);
        VolunteerFactory::createOne(['firstName' => 'Zawadi', 'lastName' => 'Zuma']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers?sort=v.lastName&direction=sideways');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aisha Achieng', $crawler->filter('table tbody tr')->first()->text());
    }

    /**
     * The case that would bite: `InputBag::get()` throws a BadRequestException
     * on a non-scalar, so reading `sort` through it would turn `?sort[]=name`
     * into a 400. ListPaginator goes through `query->all()` instead, and the
     * page-size form and page links both skip iterable params.
     */
    #[Test]
    public function theIndexShrugsOffAnArraySortParam(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Achieng']);

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers?sort[]=name&direction[]=desc');

        self::assertResponseIsSuccessful();
    }

    /**
     * Covers the shared DataTable header markup, not just this screen: the
     * link text has to stay the bare column label (several tests resolve
     * headers and buttons by text) with the direction carried by aria-sort
     * and an arrow outside the link.
     */
    #[Test]
    public function sortableHeadersLinkOnTheLabelAndAnnounceTheDirection(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers?sort=name&direction=asc');

        self::assertSame('Name', $crawler->filter('[data-sort-link="name"]')->text());
        self::assertSame('ascending', $crawler->filter('thead th')->first()->attr('aria-sort'));

        // A second click flips it; every other column starts over at ascending.
        self::assertStringContainsString('direction=desc', (string) $crawler->filter('[data-sort-link="name"]')->attr('href'));
        self::assertStringContainsString('direction=asc', (string) $crawler->filter('[data-sort-link="email"]')->attr('href'));
    }

    #[Test]
    public function aSortLinkKeepsThePageSizeAndStartsOverAtPageOne(): void
    {
        $client = static::createClient();
        VolunteerFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers?page=2&perPage=50');

        $href = (string) $crawler->filter('[data-sort-link="email"]')->attr('href');

        self::assertStringContainsString('perPage=50', $href);
        // A new order means a new page 1; keeping the old offset would drop
        // the reader somewhere arbitrary in the re-sorted list.
        self::assertStringNotContainsString('page=2', $href);
    }

    /**
     * Status has two values, so without the default order kept as a tie-break
     * SQLite is free to hand back a row on page 2 that was already on page 1.
     */
    #[Test]
    public function sortingByALowCardinalityColumnStillPagesWithoutRepeatingRows(): void
    {
        $client = static::createClient();
        foreach (range(1, 26) as $number) {
            VolunteerFactory::createOne(['firstName' => 'Volunteer', 'lastName' => sprintf('Number%02d', $number)]);
        }

        $client->loginUser(UserFactory::createOne());

        $firstPage = $client->request('GET', '/volunteers?sort=status&direction=asc')->filter('table tbody tr')->each(
            static fn($row) => $row->text(),
        );
        $secondPage = $client->request('GET', '/volunteers?sort=status&direction=asc&page=2')->filter('table tbody tr')->each(
            static fn($row) => $row->text(),
        );

        self::assertCount(25, $firstPage);
        self::assertCount(1, $secondPage);
        self::assertSame([], array_intersect($firstPage, $secondPage));
    }

    #[Test]
    public function editSavesTheProfileFieldsAndBlanksStoreNull(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/edit");

        $client->submit($crawler->selectButton('Save')->form([
            'volunteer_form[nationality]' => 'DE',
            'volunteer_form[countryOfResidence]' => 'KE',
            'volunteer_form[dateOfBirth]' => '1998-04-02',
            'volunteer_form[profession]' => 'Nurse',
            'volunteer_form[skills]' => 'First aid',
            'volunteer_form[interests]' => '',
            'volunteer_form[emergencyContacts]' => 'Anna (sister) +49 170 0000000',
        ]));

        self::assertResponseRedirects('/volunteers');
        $volunteer = self::reloadVolunteer($client, (int) $volunteer->getId());
        self::assertSame('DE', $volunteer->getNationality());
        self::assertSame('KE', $volunteer->getCountryOfResidence());
        self::assertSame('1998-04-02', $volunteer->getDateOfBirth()?->format('Y-m-d'));
        self::assertSame('Nurse', $volunteer->getProfession());
        self::assertNull($volunteer->getInterests());
        self::assertTrue($volunteer->isProfileIncomplete());

        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}");
        self::assertSelectorTextContains('[data-profile]', 'Germany');
        self::assertSelectorTextContains('[data-profile]', 'Kenya');
        self::assertSelectorTextContains('[data-profile]', 'Anna (sister)');
        self::assertCount(1, $crawler->filter('[data-profile-incomplete]'));
    }

    #[Test]
    public function aFutureDateOfBirthIsRefused(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/edit");

        $client->submit($crawler->selectButton('Save')->form([
            'volunteer_form[dateOfBirth]' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'A date of birth must be in the past.');
    }

    /**
     * A landscape JPEG tagged "rotate 90° clockwise" with a GPS tag, as a phone
     * would send it: stored upright, at most 800 px, with no metadata left.
     */
    #[Test]
    public function anUploadedPhotoIsTurnedUprightShrunkAndStrippedOfMetadata(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $client->loginUser(UserFactory::createOne());
        $upload = self::jpegWithExif(1200, 600);
        self::assertSame('S', exif_read_data($upload)['GPSLatitudeRef'] ?? null, 'The fixture must carry GPS.');

        self::submitPhoto($client, (int) $volunteer->getId(), $upload);

        self::assertResponseRedirects('/volunteers');
        $client->request('GET', "/volunteers/{$volunteer->getId()}/photo");
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));

        $stored = (string) $client->getResponse()->getContent();
        $size = getimagesizefromstring($stored);
        self::assertIsArray($size);
        self::assertSame([400, 800], [$size[0], $size[1]]);
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($stored));
        self::assertArrayNotHasKey('GPSLatitudeRef', is_array($exif) ? $exif : []);
        self::assertArrayNotHasKey('Orientation', is_array($exif) ? $exif : []);
    }

    #[Test]
    public function aFileThatIsNotAPictureIsRefused(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne();
        $client->loginUser(UserFactory::createOne());
        $path = (string) tempnam(sys_get_temp_dir(), 'not-a-photo');
        file_put_contents($path, 'plain text, not a picture');

        self::submitPhoto($client, (int) $volunteer->getId(), $path);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Upload a JPEG, PNG or WebP picture.');
        self::assertSame(0, self::photoRows());
    }

    #[Test]
    public function removePhotoDeletesTheStoredPicture(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withPhoto()->create();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/edit");
        self::assertCount(1, $crawler->filter('img[alt^="Current photo"]'));

        $form = $crawler->selectButton('Save')->form();
        $checkbox = $form['volunteer_form[removePhoto]'];
        self::assertInstanceOf(ChoiceFormField::class, $checkbox);
        $checkbox->tick();
        $client->submit($form);

        self::assertResponseRedirects('/volunteers');
        self::assertSame(0, self::photoRows());
        $client->request('GET', "/volunteers/{$volunteer->getId()}/photo");
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deletingAVolunteerDeletesTheirPhoto(): void
    {
        $client = static::createClient();
        VolunteerFactory::new()->withPhoto()->create();
        self::assertSame(1, self::photoRows());
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/volunteers');
        self::assertSame(0, self::photoRows());
    }

    #[Test]
    public function theProfileShowsThePhotoOrItsPlaceholder(): void
    {
        $client = static::createClient();
        $with = VolunteerFactory::new()->withPhoto()->create();
        $without = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$with->getId()}");
        self::assertCount(1, $crawler->filter('[data-volunteer-photo]'));

        $crawler = $client->request('GET', "/volunteers/{$without->getId()}");
        self::assertCount(0, $crawler->filter('[data-volunteer-photo]'));
        self::assertSelectorTextContains('a[title="Add a photo"]', 'AN');
    }

    /**
     * ADR 0026: the stay covering today wins; without one, the latest stay.
     */
    #[Test]
    public function theProfileShowsTheBranchOfAttachment(): void
    {
        $client = static::createClient();
        $today = new \DateTimeImmutable('today');
        $mombasa = BranchFactory::find(['name' => 'Mombasa']);
        $current = VolunteerFactory::createOne();
        StayFactory::new()->past()->create(['volunteer' => $current, 'branch' => $mombasa]);
        $former = VolunteerFactory::new()->withoutStay()->create();
        StayFactory::new()->past()->create(['volunteer' => $former]);
        StayFactory::createOne([
            'volunteer' => $former,
            'branch' => $mombasa,
            'startDate' => $today->modify('-3 months'),
            'endDate' => $today->modify('-2 months'),
        ]);
        $newcomer = VolunteerFactory::new()->withoutStay()->create();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', "/volunteers/{$current->getId()}");
        self::assertSelectorTextSame('[data-branch-of-attachment]', 'Nairobi (HQ)');
        $client->request('GET', "/volunteers/{$former->getId()}");
        self::assertSelectorTextSame('[data-branch-of-attachment]', 'Mombasa');
        $client->request('GET', "/volunteers/{$newcomer->getId()}");
        self::assertSelectorTextSame('[data-branch-of-attachment]', '—');
    }

    /**
     * The record is often entered weeks after the work started, so the date
     * comes from the oldest activity, not from createdAt.
     */
    #[Test]
    public function volunteerSinceIsTheOldestActivityDate(): void
    {
        $client = static::createClient();
        $today = new \DateTimeImmutable('today');
        $veteran = VolunteerFactory::createOne();
        ActivityFactory::createOne(['volunteer' => $veteran, 'date' => $today->modify('-20 days')]);
        ActivityFactory::createOne(['volunteer' => $veteran, 'date' => $today->modify('-3 days')]);
        $planned = VolunteerFactory::createOne();
        ActivityFactory::createOne(['volunteer' => $planned, 'date' => $today->modify('+5 days')]);
        $newcomer = VolunteerFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', "/volunteers/{$veteran->getId()}");
        self::assertSelectorTextSame('[data-volunteer-since]', 'Volunteer since ' . $today->modify('-20 days')->format('j F Y'));
        $client->request('GET', "/volunteers/{$planned->getId()}");
        self::assertSelectorTextSame('[data-volunteer-since]', 'First activity planned for ' . $today->modify('+5 days')->format('j F Y'));
        $client->request('GET', "/volunteers/{$newcomer->getId()}");
        self::assertSelectorTextSame('[data-volunteer-since]', 'Added on ' . $newcomer->getCreatedAt()->format('j F Y'));
    }

    private static function submitPhoto(KernelBrowser $client, int $volunteerId, string $path): void
    {
        $form = $client->request('GET', "/volunteers/{$volunteerId}/edit")->selectButton('Save')->form();
        $field = $form['volunteer_form[photo]'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($path);
        $client->submit($form);
    }

    /** Through a cleared manager: the identity map still holds the pre-submission object. */
    private static function reloadVolunteer(KernelBrowser $client, int $id): Volunteer
    {
        $manager = $client->getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $volunteer = $manager->getRepository(Volunteer::class)->find($id);
        self::assertInstanceOf(Volunteer::class, $volunteer);

        return $volunteer;
    }

    private static function photoRows(): int
    {
        $count = self::getContainer()->get('doctrine.dbal.default_connection')->fetchOne('SELECT COUNT(*) FROM volunteer_photo');

        return is_numeric($count) ? (int) $count : -1;
    }

    /**
     * A GD-drawn JPEG with a hand-built EXIF segment: Orientation 6 (rotate
     * 90° clockwise) and a GPS IFD holding GPSLatitudeRef "S". GD can't write
     * EXIF, and a committed phone photo would be a real person's.
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    private static function jpegWithExif(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $entry = static fn(int $tag, int $type, int $value): string => pack('vvVV', $tag, $type, 1, $value);
        $tiff = "II\x2A\x00" . pack('V', 8)
            . pack('v', 2) . pack('vvVv', 0x0112, 3, 1, 6) . "\x00\x00" . $entry(0x8825, 4, 38) . pack('V', 0)
            . pack('v', 1) . pack('vvV', 0x0001, 2, 2) . "S\x00\x00\x00" . pack('V', 0);
        $segment = "Exif\x00\x00" . $tiff;
        $jpeg = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($segment) + 2) . $segment . substr($jpeg, 2);

        $path = (string) tempnam(sys_get_temp_dir(), 'photo') . '.jpg';
        file_put_contents($path, $jpeg);

        return $path;
    }
}
