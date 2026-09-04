# Fixture data

The app's fixtures are seeded from what actually happened at UCESCO —
never from a generator. The rule and the reasoning are
[ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md);
this file is the working detail.

## What lives here

- **`rosters.yaml`** — committed. The derived dataset:
  volunteers, escorts, projects, and one entry per roster line the VM
  posted. `src/Story/AppStory.php` reads it through
  `App\Fixture\RosterArchive`; nothing else in the app touches it.
- **`*_dumps.txt`** — **gitignored, never committed.** The raw WhatsApp
  exports the extract is transcribed from. They stay on the machine that
  produced them.

Load the result with:

```bash
docker compose exec php bin/console foundry:load-fixtures --no-interaction
```

## Why the raw dump is not committed

This repository is public. The exports contain, alongside the rosters,
scholarship announcements that name individual sponsored children with
their school and their grade level, and the donors funding them. That is
identifiable information about minors, and Kenya's Data Protection Act
2019 applies to it. None of it reaches `rosters.yaml`, the database, or
git.

## Transcription rules

When a new export is added, transcribe it into `rosters.yaml` by hand
and apply these rules. They are what keep the extract honest, and they
are the reason this is a human pass rather than a parser.

1. **Drop everything that is not a roster.** Scholarship updates, thank
   you messages, chat. The app models volunteering, not sponsorship.
2. **No child, no donor, no contact detail.** Ever, in any field,
   including the free-text notes.
3. **`date` is the day the roster covers**, which is the day *after* the
   message. `posted` records the message's own date.
4. **Respect the truncation.** WhatsApp exports cut long messages off at
   "Voir plus" / "See more". Transcribe only the sites you can actually
   read and set `truncated: true`. Never reconstruct the tail.
5. **A site whose header survives but whose volunteers are cut off is
   not a roster entry.** It may still be worth adding to `projects:` —
   the site is real — but it seeds no activity.
6. **An escort line lost to the cut is an empty `escorts: []`**, not a
   guess. Same for a volunteer note.
7. **`-Name-later OtherSite` becomes two entries**, one at each site,
   with the note preserved. That is what the line says, and the same
   volunteer at two sites in one day is a case the app has to handle.
8. **Volunteers are recorded as the roster spells them**, first name
   only. Two spellings are not merged unless the messages say they are
   the same person.
9. **Anything inferred gets a comment saying so**, next to the entry, in
   the words of the message it was inferred from. There are three such
   inferences in the current extract: Mt Hermon's location, the
   unlabelled "Sports" of 21/08, and the escort attribution on 31/08.
10. **A site's name never carries its region.** The messages qualify the
    coast sites in passing — "Minto children Orphanage (Mombasa)" — but
    that qualifier is the project's `location`, not part of what the site
    is called. Transcribe the name alone
    ([ADR 0015](../adr/0015-keep-a-projects-region-in-its-location-not-its-name.md)).
11. **`active: false`** for a volunteer whose last roster appearance is
    more than a week before the end of the archive, or who says goodbye
    in the group. Volunteers leave after a few weeks; that is normal and
    is what `Volunteer::$isActive` is for.

## What the archive cannot supply

Three fields are required by the model but absent from every roster
message. They are filled the same way every time, and that is recorded
here rather than hidden in the fixture code:

- **`duration`** — the rosters never state one. Every seeded activity is
  a half day, the shape of a normal site visit. Override per site with
  `duration: full_day` in the YAML if a future message says otherwise.
- **`loggedBy`** — the app's single admin user. There is no other.
- **Volunteer `email` and `phone`** — not in the rosters, so they stay
  `null` rather than being invented. `lastName` too
  ([ADR 0014](../adr/0014-make-a-volunteers-last-name-optional.md)).

Project `location`, `ownership` and partner names are not in the rosters
either; they come from the project records already established with the
VM (`docs/brainstorm/04`), and are carried in `projects:` for that
reason.

## Dates and the home screen

The home screen's rosters cover today and tomorrow, so a fixed archive
date would leave it empty on every day but one. The two most recent
archive days carry `anchor: today` and `anchor: tomorrow`, and the whole
archive is shifted so that `anchor: today` lands on the day the fixtures
are loaded. Every other day moves by that same offset — shifting only the
anchored pair would leave a gap where the rest of the archive stops. Only
the calendar moves; the sites, people, escorts and notes stay the real
ones.

Note the day the fixtures load is Nairobi's, not the host's: the app runs
on `Africa/Nairobi` (ADR 0003, `frankenphp/conf.d/10-app.ini`), so a
machine in the Americas will seed "tomorrow" for part of its evening.
