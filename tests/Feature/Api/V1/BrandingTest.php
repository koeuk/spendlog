<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_is_public_and_carries_the_stock_flags(): void
    {
        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'name', 'copyright', 'logo', 'favicon', 'button_color', 'branded', 'body_color', 'plain_background',
            ]])
            ->assertJsonPath('data.branded', false);
    }

    public function test_a_chosen_colour_marks_the_payload_branded(): void
    {
        $settings = AppSetting::current();
        $settings->button_color = '#1d4ed8';
        $settings->body_color = '#faf8f4';
        $settings->app_name = 'Ledger';
        $settings->save();

        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('data.name', 'Ledger')
            ->assertJsonPath('data.button_color', '#1d4ed8')
            ->assertJsonPath('data.branded', true)
            ->assertJsonPath('data.plain_background', true);
    }
}
