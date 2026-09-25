<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Volunteer;
use App\Entity\VolunteerPhoto;
use App\Export\ListExport;
use App\Form\VolunteerFormType;
use App\Pagination\ListPaginator;
use App\Repository\ActivityRepository;
use App\Repository\VolunteerRepository;
use App\Security\PassportNumberCipher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/volunteers', name: 'volunteer_')]
final class VolunteerController extends AbstractController
{
    /**
     * Column key => DQL field(s) for the index's sortable headers. The map is
     * the whitelist, so nothing a reader types reaches DQL. `name` needs two
     * fields because getFullName() has no single column behind it. See ADR 0011.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['v.lastName', 'v.firstName'],
        'email' => ['v.email'],
        'phone' => ['v.phone'],
        // The HIDDEN count createOrderedByNameQueryBuilder() selects: active is
        // read from stays, so there is no column to sort on (ADR 0026).
        'status' => ['isCurrent'],
    ];

    private const int PHOTO_MAX_EDGE = 800;

    /** The list shows 24 px avatars; 96 px keeps them sharp on 2x screens whatever the aspect ratio. */
    private const int THUMB_MAX_EDGE = 96;

    /** @var list<array{key: string, label: string}> */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'phone', 'label' => 'Phone'],
        ['key' => 'status', 'label' => 'Status'],
    ];

    public function __construct(
        private readonly VolunteerRepository $volunteers,
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $entityManager,
        private readonly ListPaginator $paginator,
        private readonly PassportNumberCipher $passportCipher,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginateQuery($this->listQueryBuilder($request), Volunteer::class, $request);

        /** @var list<Volunteer> $volunteersOnPage */
        $volunteersOnPage = iterator_to_array($pagination, false);
        // One query for the whole page. The delete-guard's own count stays
        // per-entity in delete() — this is the same rule read ahead of time so
        // the index can show Delete as unavailable rather than let the reader
        // discover it from a flash after confirming.
        $activityCounts = $this->volunteers->countReferencingActivitiesFor($volunteersOnPage);
        $staying = $this->volunteers->findIdsStayingOn($volunteersOnPage, new \DateTimeImmutable('today'));

        $rows = [];
        foreach ($volunteersOnPage as $volunteer) {
            $id = $volunteer->getId();
            $referencingCount = null === $id ? 0 : ($activityCounts[$id] ?? 0);

            $rows[] = [
                'cells' => $this->cells($volunteer, $staying),
                // getPhoto() is an unloaded proxy and its id is known without a
                // query, so the list never reads photo bytes (ADR 0032).
                'avatars' => ['name' => null === $volunteer->getPhoto() ? null : $this->generateUrl('volunteer_photo', [
                    'id' => $id,
                    'v' => $volunteer->getPhoto()->getId(),
                    'size' => 'thumb',
                ])],
                'actions' => [
                    ['label' => 'View', 'url' => $this->generateUrl('volunteer_show', ['id' => $id])],
                    ['label' => 'Edit', 'url' => $this->generateUrl('volunteer_edit', ['id' => $id])],
                    $referencingCount > 0
                        ? ['label' => 'Delete', 'disabledReason' => $this->guardReason($volunteer, $referencingCount)]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('volunteer_delete', ['id' => $id]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete %s?', $volunteer->getFullName()),
                            'csrfTokenId' => $this->csrfTokenId($volunteer),
                        ],
                ],
            ];
        }

        return $this->render('volunteer/index.html.twig', [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'pagination' => $pagination,
            'sortState' => $this->paginator->sortState($request, self::SORT_MAP),
            'search' => $this->requestedSearch($request),
        ]);
    }

    /**
     * Declared before show() so `/volunteers/export` is not read as an id.
     */
    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        // getResult() rather than toIterable(): the status cell needs the
        // whole list up front for findIdsStayingOn(), and the query's HIDDEN
        // select is not what toIterable() is built for. A few hundred rows at
        // most — the same load as the index's "All" page size.
        /** @var list<Volunteer> $volunteers */
        $volunteers = $this->listQueryBuilder($request)->getQuery()->getResult();
        $staying = $this->volunteers->findIdsStayingOn($volunteers, new \DateTimeImmutable('today'));

        return ListExport::response(
            'volunteers',
            $format,
            self::COLUMNS,
            array_map(fn(Volunteer $volunteer): array => $this->cells($volunteer, $staying), $volunteers),
        );
    }

    /**
     * The one query behind both the index and its export.
     */
    private function listQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->volunteers->createOrderedByNameQueryBuilder($this->requestedSearch($request));
        $this->paginator->applySort($queryBuilder, $request, self::SORT_MAP);

        return $queryBuilder;
    }

    /**
     * The index's `?q=` search, read through query->all() so `q[]=x` degrades
     * to no filter rather than a 400 (ADR 0023). Blank means no filter too.
     */
    private function requestedSearch(Request $request): ?string
    {
        $raw = $request->query->all()['q'] ?? null;
        $search = is_scalar($raw) ? trim((string) $raw) : '';

        return '' === $search ? null : $search;
    }

    /**
     * @param array<int, true> $staying volunteer id => true, from findIdsStayingOn()
     *
     * @return array<string, string>
     */
    private function cells(Volunteer $volunteer, array $staying): array
    {
        $id = $volunteer->getId();

        return [
            'name' => $volunteer->getFullName(),
            'email' => $volunteer->getEmail() ?? '—',
            'phone' => $volunteer->getPhone() ?? '—',
            'status' => null !== $id && isset($staying[$id]) ? 'Active' : 'Inactive',
        ];
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $volunteer = new Volunteer();
        $form = $this->createForm(VolunteerFormType::class, $volunteer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->applyPhoto($form, $volunteer)) {
            $this->applyPassportNumber($form, $volunteer);
            $this->entityManager->persist($volunteer);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was added.', $volunteer->getFullName()));

            return $this->redirectToRoute('volunteer_index');
        }

        return $this->render('volunteer/new.html.twig', ['form' => $form]);
    }

    /**
     * The picture, only ever served through here: it lives in the database,
     * never under a public path (ADR 0032).
     */
    #[Route('/{id}/photo', name: 'photo', methods: ['GET'])]
    public function photo(Request $request, Volunteer $volunteer): Response
    {
        $photo = $volunteer->getPhoto() ?? throw $this->createNotFoundException('This volunteer has no photo.');
        $query = $request->query->all();

        $response = new Response();
        $response->setPrivate();
        // Pages link here with v=<photo id>. A replaced photo is a new row with
        // a new id, so a URL naming the current one never changes content and
        // the browser can keep it; the /volunteers list relies on that.
        if (isset($query['v']) && is_scalar($query['v']) && (string) $query['v'] === (string) $photo->getId()) {
            $response->setMaxAge(31_536_000);
            $response->setImmutable();
        }
        $response->setLastModified($photo->getUpdatedAt());
        if ($response->isNotModified($request)) {
            return $response;
        }

        $bytes = $photo->getBytes();
        if ('thumb' === ($query['size'] ?? null)) {
            // ponytail: resized on every uncached request; store a thumbnail
            // next to the photo if the list ever gets slow.
            $image = imagecreatefromstring($bytes);
            if (false !== $image) {
                $bytes = self::encodeJpeg($image, self::THUMB_MAX_EDGE);
            }
        }

        $response->headers->set('Content-Type', 'image/jpeg');
        $response->setContent($bytes);

        return $response;
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Volunteer $volunteer): Response
    {
        $activities = $this->activities->findByVolunteerOrderedByDateDesc($volunteer);

        $totalDays = 0.0;
        $mostRecent = null;
        // When they started volunteering, which the record's createdAt is not:
        // the archive and UAT were entered weeks after the work happened.
        $firstActivity = null;
        $activityCountsByStay = [];
        foreach ($activities as $activity) {
            $totalDays += $activity->getDuration()?->toDays() ?? 0.0;

            $stayId = $activity->getStay()?->getId();
            if (null !== $stayId) {
                $activityCountsByStay[$stayId] = ($activityCountsByStay[$stayId] ?? 0) + 1;
            }

            $date = $activity->getDate();
            if (null !== $date && (null === $mostRecent || $date > $mostRecent)) {
                $mostRecent = $date;
            }
            if (null !== $date && (null === $firstActivity || $date < $firstActivity)) {
                $firstActivity = $date;
            }
        }

        $today = new \DateTimeImmutable('today');

        // The Stays panel's Edit/Delete, with Delete inert on a stay that
        // activities are logged in — the same guard StayController::delete()
        // enforces, read from the activities already loaded above.
        $stays = [];
        foreach ($volunteer->getStays() as $stay) {
            $referencingCount = $activityCountsByStay[(int) $stay->getId()] ?? 0;
            $stays[] = [
                'stay' => $stay,
                'actions' => [
                    ['label' => 'Edit', 'url' => $this->generateUrl('stay_edit', ['id' => $stay->getId()])],
                    $referencingCount > 0
                        ? ['label' => 'Delete', 'disabledReason' => StayController::guardReason($referencingCount)]
                        : [
                            'label' => 'Delete',
                            'url' => $this->generateUrl('stay_delete', ['id' => $stay->getId()]),
                            'method' => 'post',
                            'confirm' => sprintf('Delete the stay at %s?', $stay->getBranch()?->getName() ?? 'this branch'),
                            'csrfTokenId' => StayController::csrfTokenId($stay),
                        ],
                ],
            ];
        }

        return $this->render('volunteer/show.html.twig', [
            'volunteer' => $volunteer,
            // Names, not codes; Countries is already here for the form's CountryType.
            'nationalityName' => null === $volunteer->getNationality() ? null : Countries::getName($volunteer->getNationality()),
            'residenceName' => null === $volunteer->getCountryOfResidence() ? null : Countries::getName($volunteer->getCountryOfResidence()),
            'passportNumber' => $this->passportNumber($volunteer),
            'stays' => $stays,
            'activities' => $activities,
            'activityCount' => count($activities),
            'totalDays' => $totalDays,
            'mostRecent' => $mostRecent,
            'firstActivity' => $firstActivity,
            'mostRecentIsPlanned' => null !== $mostRecent && $mostRecent > $today,
            'today' => $today,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Volunteer $volunteer): Response
    {
        $form = $this->createForm(VolunteerFormType::class, $volunteer);
        $form->get('passportNumber')->setData($this->passportNumber($volunteer));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->applyPhoto($form, $volunteer)) {
            $this->applyPassportNumber($form, $volunteer);
            $volunteer->touch();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s was updated.', $volunteer->getFullName()));

            return $this->redirectToRoute('volunteer_index');
        }

        // Said here rather than on the index: a reader who wants to delete one
        // volunteer is on that volunteer's screen, and the list already renders
        // Delete inert on the rows this would block.
        $referencingCount = $this->volunteers->countReferencingActivities($volunteer);

        return $this->render('volunteer/edit.html.twig', [
            'form' => $form,
            'volunteer' => $volunteer,
            'deleteGuardReason' => $referencingCount > 0
                ? $this->guardReason($volunteer, $referencingCount)
                : null,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Volunteer $volunteer): Response
    {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($volunteer), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token — please try again.');

            return $this->redirectToRoute('volunteer_index');
        }

        $referencingCount = $this->volunteers->countReferencingActivities($volunteer);
        if ($referencingCount > 0) {
            $this->addFlash('error', $this->guardReason($volunteer, $referencingCount));

            return $this->redirectToRoute('volunteer_index');
        }

        $this->entityManager->remove($volunteer);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s was deleted.', $volunteer->getFullName()));

        return $this->redirectToRoute('volunteer_index');
    }

    /** The decrypted passport number, if one is on file (ADR 0033). */
    private function passportNumber(Volunteer $volunteer): ?string
    {
        $stored = $volunteer->getPassportNumberCiphertext();

        return null === $stored ? null : $this->passportCipher->decrypt($stored);
    }

    /**
     * Encrypts the form's unmapped passport number onto the volunteer; a blank
     * field removes it. Stored uppercase and without spaces, as printed on
     * the passport's data page.
     *
     * @param FormInterface<mixed> $form
     */
    private function applyPassportNumber(FormInterface $form, Volunteer $volunteer): void
    {
        $submitted = $form->get('passportNumber')->getData();
        $number = is_string($submitted) ? strtoupper(str_replace(' ', '', $submitted)) : '';

        $volunteer->setPassportNumberCiphertext('' === $number ? null : $this->passportCipher->encrypt($number));
    }

    /**
     * Removes or replaces the photo from the form's unmapped fields. Every
     * upload is decoded and re-encoded as a JPEG of at most PHOTO_MAX_EDGE px,
     * which drops all metadata, GPS included. Returns false, with a form
     * error, when the file cannot be decoded.
     *
     * @param FormInterface<mixed> $form
     */
    private function applyPhoto(FormInterface $form, Volunteer $volunteer): bool
    {
        if ($form->has('removePhoto') && true === $form->get('removePhoto')->getData()) {
            $volunteer->setPhoto(null);
        }

        $upload = $form->get('photo')->getData();
        if (!$upload instanceof UploadedFile) {
            return true;
        }

        $path = $upload->getPathname();
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (false === $image) {
            $form->get('photo')->addError(new FormError('This picture could not be read. Try another file.'));

            return false;
        }

        // The re-encode below drops the EXIF Orientation tag, so apply it now
        // or portrait phone photos come out sideways.
        $exif = 'image/jpeg' === $upload->getMimeType() ? @exif_read_data($path) : false;
        $angle = match (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };
        if (0 !== $angle) {
            $rotated = imagerotate($image, $angle, 0);
            $image = false === $rotated ? $image : $rotated;
        }

        $volunteer->setPhoto(new VolunteerPhoto(self::encodeJpeg($image, self::PHOTO_MAX_EDGE)));

        return true;
    }

    /** Scales $image down to fit $maxEdge px and encodes it as a metadata-free JPEG. */
    private static function encodeJpeg(\GdImage $image, int $maxEdge): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if (max($width, $height) > $maxEdge) {
            $image = $width >= $height
                ? imagescale($image, $maxEdge)
                : imagescale($image, (int) round($width * $maxEdge / $height), $maxEdge);
            \assert(false !== $image);
        }

        ob_start();
        imagejpeg($image, null, 82);

        return (string) ob_get_clean();
    }

    /**
     * Why this volunteer can't be deleted, in one sentence. Shared by the
     * index's greyed-out Delete, the note on the edit screen, and the flash
     * raised if a delete is attempted anyway, so the warning and the refusal
     * can't drift apart.
     */
    private function guardReason(Volunteer $volunteer, int $referencingCount): string
    {
        return sprintf(
            'Cannot delete %s — %d activit%s reference%s them. Mark them inactive instead.',
            $volunteer->getFullName(),
            $referencingCount,
            1 === $referencingCount ? 'y' : 'ies',
            1 === $referencingCount ? 's' : '',
        );
    }

    private function csrfTokenId(Volunteer $volunteer): string
    {
        return 'delete-volunteer-' . $volunteer->getId();
    }
}
