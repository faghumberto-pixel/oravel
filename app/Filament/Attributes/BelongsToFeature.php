<?php
namespace App\Filament\Attributes;
use Attribute;
#[Attribute(Attribute::TARGET_CLASS)]
class BelongsToFeature {
    public function __construct(public string $featureSlug) {}
}
