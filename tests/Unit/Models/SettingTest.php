<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_value_round_trips_through_encryption(): void
    {
        Setting::set('github_webhook_secret', 's3cret-value');

        // Stored ciphertext is not the plaintext.
        $raw = DB::table('settings')->where('key', 'github_webhook_secret')->value('value');
        $this->assertNotSame('s3cret-value', $raw);

        // Reading decrypts back to plaintext.
        $this->assertSame('s3cret-value', Setting::get('github_webhook_secret'));
    }

    public function test_setting_a_blank_value_clears_it(): void
    {
        Setting::set('linear_webhook_secret', 'abc');
        $this->assertSame('abc', Setting::get('linear_webhook_secret'));

        Setting::set('linear_webhook_secret', '');
        $this->assertNull(Setting::get('linear_webhook_secret'));
    }

    public function test_get_returns_default_when_missing(): void
    {
        $this->assertNull(Setting::get('nope'));
        $this->assertSame('fallback', Setting::get('nope', 'fallback'));
    }
}
