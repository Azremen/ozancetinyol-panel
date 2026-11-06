@php
use App\Models\Slider;
$slides = Slider::where('is_active', true)->orderBy('order')->get();
@endphp

@if($slides->isNotEmpty())
<div class="slider-fade">
    <div class="owl-carousel">
        @foreach($slides as $slide)
        @php
        /** @var \App\Models\Slider $slide */
        $hasMediaMethod = is_object($slide) && method_exists($slide, 'getFirstMediaUrl');
        $hero_webp = $hasMediaMethod ? $slide->getFirstMediaUrl('sliders', 'hero_1920x700_webp') : null;
        $hero_1920 = $hasMediaMethod ? $slide->getFirstMediaUrl('sliders', 'hero_1920x700') : null;
        $hero_1600 = $hasMediaMethod ? $slide->getFirstMediaUrl('sliders', 'hero_1600x600') : null;
        @endphp

        <div class="item">
            <picture>
                @if($hero_webp)
                <source type="image/webp" srcset="{{ $hero_webp }} 1920w, {{ $hero_1600 ?: $hero_1920 }} 1600w" sizes="100vw">
                @endif
                @if($hero_1920)
                    <img
                        class="bg-img"
                        src="{{ $hero_1920 }}"
                        @if($hero_1600) srcset="{{ $hero_1600 }} 1600w, {{ $hero_1920 }} 1920w" @endif
                        sizes="(max-width:1200px) 100vw, 1920px"
                        alt="{{ $slide->display_title ?: 'Slider image' }}"
                        width="1920" height="700">
                @endif
            </picture>

            <div class="caption">
                @if($slide->display_subtitle)
                <span>{{ $slide->display_subtitle }}</span>
                @endif
                @if($slide->display_title)
                <h1>{{ $slide->display_title }}</h1>
                @endif
                @if($slide->display_button_text)
                <p>
                    <a href="{{ $slide->button_link ?? '#' }}" class="butn-light">{{ $slide->display_button_text }}</a>
                </p>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif