<?php

namespace App\Helpers;

class IconHelper
{
    public const ICON_CLASSES = [
        'flaticon-calendar' => 'fa-solid fa-calendar-days',
        'flaticon-call' => 'fa-solid fa-phone',
        'flaticon-checklist' => 'fa-solid fa-list-check',
        'flaticon-clock' => 'fa-solid fa-clock',
        'flaticon-globe' => 'fa-solid fa-globe',
        'flaticon-gym' => 'fa-solid fa-dumbbell',
        'flaticon-message' => 'fa-solid fa-comment',
        'flaticon-rating' => 'fa-solid fa-star',
        'flaticon-shield' => 'fa-solid fa-shield-halved',
        'flaticon-teamwork' => 'fa-solid fa-people-group',
        'flaticon-user' => 'fa-solid fa-user',
        'flaticon-world' => 'fa-solid fa-person-walking',
    ];

    public static function getFlaticons(): array
    {
        $icons = [
            'flaticon-calendar' => __('panel.icons.calendar'),
            'flaticon-call' => __('panel.icons.call'),
            'flaticon-checklist' => __('panel.icons.checklist'),
            'flaticon-clock' => __('panel.icons.clock'),
            'flaticon-globe' => __('panel.icons.globe'),
            'flaticon-gym' => __('panel.icons.gym'),
            'flaticon-message' => __('panel.icons.message'),
            'flaticon-rating' => __('panel.icons.rating'),
            'flaticon-shield' => __('panel.icons.shield'),
            'flaticon-teamwork' => __('panel.icons.teamwork'),
            'flaticon-user' => __('panel.icons.user'),
            'flaticon-world' => __('panel.icons.world'),
        ];

        $htmlIcons = [];
        foreach ($icons as $class => $label) {
            $iconClass = self::ICON_CLASSES[$class];
            $htmlIcons[$class] = "<div class='flex items-center gap-2'><i class='{$iconClass} text-xl' aria-hidden='true' style='font-size: 1.5rem; width: 24px; text-align: center'></i><span>".e($label).'</span></div>';
        }

        return $htmlIcons;
    }
}
