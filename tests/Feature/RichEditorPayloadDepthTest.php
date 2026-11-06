<?php

namespace Tests\Feature;

use Livewire\Component;
use Livewire\Exceptions\MaxNestingDepthExceededException;
use Livewire\Livewire;
use Tests\TestCase;

class RichEditorPayloadDepthTest extends TestCase
{
    public function test_localized_editor_updates_accept_the_reported_property_path(): void
    {
        $path = 'data.description.tr.content.12.content.0.content.0.content.0';

        Livewire::test(RichEditorPayloadDepthComponent::class)
            ->set($path, 'Updated content')
            ->assertStatus(200)
            ->assertSet($path, 'Updated content');
    }

    public function test_editor_updates_accept_the_configured_depth_boundary(): void
    {
        $this->assertSame(20, config('livewire.payload.max_nesting_depth'));
        $path = 'data.'.implode('.', array_fill(0, 19, 'content'));

        Livewire::test(RichEditorPayloadDepthComponent::class)
            ->set($path, 'Updated content')
            ->assertSet($path, 'Updated content');
    }

    public function test_updates_beyond_the_depth_limit_are_rejected(): void
    {
        $path = 'data.'.implode('.', array_fill(0, 20, 'content'));
        $component = Livewire::test(RichEditorPayloadDepthComponent::class);

        $this->expectException(MaxNestingDepthExceededException::class);

        $component->set($path, 'Too deep');
    }
}

class RichEditorPayloadDepthComponent extends Component
{
    public array $data = [];

    public function render(): string
    {
        return '<div></div>';
    }
}
