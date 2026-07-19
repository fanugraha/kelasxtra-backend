<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

abstract class DashboardHeadingWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard-heading';

    protected static ?string $headingText = null;

    protected static ?string $headingIcon = null;

    protected function getViewData(): array
    {
        return [
            'heading' => static::$headingText,
            'icon' => static::$headingIcon,
        ];
    }
}
