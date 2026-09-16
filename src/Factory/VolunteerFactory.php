<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Volunteer;
use App\Entity\VolunteerPhoto;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Volunteer>
 */
final class VolunteerFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Volunteer::class;
    }

    protected function defaults(): array
    {
        return [
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'email' => self::faker()->unique()->safeEmail(),
            'phone' => self::faker()->e164PhoneNumber(),
            // Active by default, as before stays existed: one stay covering
            // today, so the activity forms offer this volunteer (ADR 0026).
            'stays' => StayFactory::new()->many(1),
        ];
    }

    /** One stay that ended months ago: finished their stint. */
    public function inactive(): self
    {
        return $this->with(['stays' => StayFactory::new()->past()->many(1)]);
    }

    /** A flat grey square drawn by GD: never a real person (ADR 0032). */
    public function withPhoto(): self
    {
        return $this->with(static function (): array {
            $image = imagecreatetruecolor(8, 8);
            ob_start();
            imagejpeg($image);

            return ['photo' => new VolunteerPhoto((string) ob_get_clean())];
        });
    }

    public function withoutStay(): self
    {
        return $this->with(['stays' => []]);
    }
}
