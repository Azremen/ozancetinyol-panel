<div class="flex items-center gap-2 mx-4 z-50">
    <button wire:click="switchLanguage('tr')" type="button" aria-label="{{ __('panel.languages.tr') }}" aria-pressed="{{ app()->getLocale() === 'tr' ? 'true' : 'false' }}" class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition {{ app()->getLocale() === 'tr' ? 'bg-gray-100 dark:bg-gray-800 text-primary-600 dark:text-primary-400 font-bold' : 'text-gray-600 dark:text-gray-400' }}">
        <span class="text-base">🇹🇷</span>
        <span class="hidden sm:inline">{{ __('panel.languages.tr') }}</span>
    </button>
    <button wire:click="switchLanguage('en')" type="button" aria-label="{{ __('panel.languages.en') }}" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}" class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition {{ app()->getLocale() === 'en' ? 'bg-gray-100 dark:bg-gray-800 text-primary-600 dark:text-primary-400 font-bold' : 'text-gray-600 dark:text-gray-400' }}">
        <span class="text-base">🇬🇧</span>
        <span class="hidden sm:inline">{{ __('panel.languages.en') }}</span>
    </button>
</div>