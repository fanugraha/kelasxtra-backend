<?php

namespace App\Filament\Widgets;

class ActionNeededHeading extends DashboardHeadingWidget
{
    protected static ?int $sort = -30;

    protected static ?string $headingText = 'Perlu Tindakan';

    protected static ?string $headingIcon = 'heroicon-o-exclamation-triangle';
}
