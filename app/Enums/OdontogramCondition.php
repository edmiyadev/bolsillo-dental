<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OdontogramCondition: string implements HasColor, HasLabel
{
    case Caries = 'caries';
    case Resin = 'resin';
    case Amalgam = 'amalgam';
    case Inlay = 'inlay';
    case Sealant = 'sealant';
    case Fracture = 'fracture';
    case Extraction = 'extraction';
    case Missing = 'missing';
    case Endodontics = 'endodontics';
    case Crown = 'crown';
    case Implant = 'implant';
    case Bridge = 'bridge';
    case Prophylaxis = 'prophylaxis';

    public function getLabel(): string
    {
        return match ($this) {
            self::Caries => __('Caries'),
            self::Resin => __('Composite resin'),
            self::Amalgam => __('Amalgam'),
            self::Inlay => __('Inlay'),
            self::Sealant => __('Pit and fissure sealant'),
            self::Fracture => __('Dental fracture'),
            self::Extraction => __('Extraction'),
            self::Missing => __('Missing tooth'),
            self::Endodontics => __('Endodontics (Root canal)'),
            self::Crown => __('Full crown'),
            self::Implant => __('Dental implant'),
            self::Bridge => __('Fixed bridge'),
            self::Prophylaxis => __('Prophylaxis / General procedure'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Caries, self::Fracture, self::Extraction => 'danger',
            self::Missing => 'gray',
            self::Resin, self::Amalgam, self::Inlay, self::Sealant, self::Endodontics, self::Crown, self::Implant, self::Bridge, self::Prophylaxis => 'info',
        };
    }

    public function defaultScope(): OdontogramScope
    {
        return match ($this) {
            self::Caries, self::Resin, self::Amalgam, self::Inlay, self::Sealant => OdontogramScope::Surface,
            self::Fracture, self::Extraction, self::Missing, self::Endodontics, self::Crown, self::Implant => OdontogramScope::Tooth,
            self::Bridge => OdontogramScope::Range,
            self::Prophylaxis => OdontogramScope::Mouth,
        };
    }

    public function supportsScope(OdontogramScope $scope): bool
    {
        return match ($this) {
            self::Caries, self::Resin, self::Amalgam, self::Inlay, self::Sealant => $scope === OdontogramScope::Surface,
            self::Fracture => in_array($scope, [OdontogramScope::Tooth, OdontogramScope::Surface], true),
            self::Extraction, self::Missing, self::Endodontics, self::Crown, self::Implant => $scope === OdontogramScope::Tooth,
            self::Bridge => $scope === OdontogramScope::Range,
            self::Prophylaxis => in_array($scope, [OdontogramScope::Arch, OdontogramScope::Mouth], true),
        };
    }

    public function isFindingCondition(): bool
    {
        return in_array($this, [self::Caries, self::Fracture], true);
    }

    public function isPreExistingCondition(): bool
    {
        return in_array($this, [
            self::Resin,
            self::Amalgam,
            self::Inlay,
            self::Sealant,
            self::Missing,
            self::Endodontics,
            self::Crown,
            self::Implant,
            self::Bridge,
        ], true);
    }

    public function isTreatmentCondition(): bool
    {
        return in_array($this, [
            self::Resin,
            self::Amalgam,
            self::Inlay,
            self::Sealant,
            self::Extraction,
            self::Endodontics,
            self::Crown,
            self::Implant,
            self::Bridge,
            self::Prophylaxis,
        ], true);
    }
}
