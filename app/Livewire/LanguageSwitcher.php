<?php

namespace App\Livewire;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    public function switchLanguage($lang)
    {
        Validator::make(['locale' => $lang], [
            'locale' => ['required', 'string', Rule::in(SetLocale::SUPPORTED_LOCALES)],
        ])->validate();

        Session::put('locale', $lang);
        $this->redirect(request()->header('Referer') ?? url()->previous());
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
