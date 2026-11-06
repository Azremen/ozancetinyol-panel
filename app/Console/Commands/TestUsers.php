<?php

namespace App\Console\Commands;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Illuminate\Console\Command;
use Livewire\Livewire;

 class TestUsers extends Command
{
    protected $signature = 'test:users';

    public function handle()
    {
        try {
            Livewire::test(ListUsers::class);
            $this->info('ListUsers works!');
        } catch (\Exception $e) {
            $this->error('ListUsers error: '.$e->getMessage());
        } try {
            $user = User::first();
            Livewire::test(ViewUser::class, ['record' => $user->id]);
            $this->info('ViewUser works!');
        } catch (\Exception $e) {
            $this->error('ViewUser error: '.$e->getMessage());
        }
    }
}
