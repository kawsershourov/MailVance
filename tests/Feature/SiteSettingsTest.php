<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function userWithRole(string $slug): User
    {
        return User::factory()->roles([$slug])->create();
    }

    public function test_only_super_admin_can_reach_site_settings(): void
    {
        foreach (['admin', 'manager', 'member', 'viewer'] as $slug) {
            $this->actingAs($this->userWithRole($slug))
                ->get(route('admin.settings.edit'))
                ->assertForbidden();
        }

        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN))
            ->get(route('admin.settings.edit'))
            ->assertOk();
    }

    public function test_super_admin_can_update_text_fields_and_invalid_brand_color_is_rejected(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'site_title' => 'Acme Mail',
            'company_name' => 'Acme Inc.',
            'support_email' => 'support@acme.test',
            'brand_color' => 'not-a-color',
        ])->assertSessionHasErrors('brand_color');

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'site_title' => 'Acme Mail',
            'company_name' => 'Acme Inc.',
            'support_email' => 'support@acme.test',
            'brand_color' => '#4f46e5',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertDatabaseHas('site_settings', [
            'site_title' => 'Acme Mail',
            'company_name' => 'Acme Inc.',
            'support_email' => 'support@acme.test',
            'brand_color' => '#4f46e5',
        ]);
    }

    public function test_logo_upload_lands_on_public_disk_and_can_be_removed(): void
    {
        Storage::fake('public');

        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect(route('admin.settings.edit'));

        $settings = SiteSetting::current();
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
        $this->assertStringStartsWith('site/', $settings->logo_path);

        $storedPath = $settings->logo_path;

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'remove_logo' => '1',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertNull(SiteSetting::current()->logo_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_favicon_upload_lands_on_public_disk_and_can_be_removed(): void
    {
        Storage::fake('public');

        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'favicon' => UploadedFile::fake()->image('favicon.png'),
        ])->assertRedirect(route('admin.settings.edit'));

        $settings = SiteSetting::current();
        $this->assertNotNull($settings->favicon_path);
        Storage::disk('public')->assertExists($settings->favicon_path);

        $storedPath = $settings->favicon_path;

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'remove_favicon' => '1',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertNull(SiteSetting::current()->favicon_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_login_page_reflects_configured_site_title_for_guests(): void
    {
        SiteSetting::current()->update(['site_title' => 'Acme Mail']);
        SiteSetting::forget();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Acme Mail');
    }

    public function test_a_custom_brand_color_reproduces_itself_at_the_600_stop(): void
    {
        // #059669 = rgb(5, 150, 105) — the 600 stop (ratio 1.0) must round-trip
        // back to the exact picked color, since that is the shade `bg-brand-600`
        // etc. render throughout the app.
        $settings = new SiteSetting(['brand_color' => '#059669']);

        $this->assertSame('5 150 105', $settings->brandPaletteRgb()[600]);
    }

    public function test_dashboard_injects_the_custom_brand_color_as_css_variables(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        SiteSetting::current()->update(['brand_color' => '#e11d48']);
        SiteSetting::forget();

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--brand-600: 225 29 72;', false);
    }

    public function test_dashboard_footer_shows_company_name_and_support_email_only_when_set(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($superAdmin)->get(route('dashboard'))->assertDontSee('Acme Footer Co');

        SiteSetting::current()->update(['company_name' => 'Acme Footer Co', 'support_email' => 'foot@example.com']);
        SiteSetting::forget();

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertSee('Acme Footer Co')
            ->assertSee('foot@example.com');
    }

    public function test_dashboard_title_reflects_a_custom_tagline(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertSee('Enterprise Email Marketing &amp; Sender', false);

        SiteSetting::current()->update(['tagline' => 'Custom Tagline Here']);
        SiteSetting::forget();

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertSee('Custom Tagline Here')
            ->assertDontSee('Enterprise Email Marketing &amp; Sender', false);
    }

    public function test_default_favicon_follows_the_brand_color_without_an_upload(): void
    {
        $this->assertSame('#4f46e5', $this->decodedFaviconFill(SiteSetting::current()));

        SiteSetting::current()->update(['brand_color' => '#e11d48']);
        SiteSetting::forget();

        $this->assertSame('#e11d48', $this->decodedFaviconFill(SiteSetting::current()));
    }

    protected function decodedFaviconFill(SiteSetting $settings): ?string
    {
        $svg = base64_decode(substr($settings->faviconUrl(), strpos($settings->faviconUrl(), ',') + 1));

        return preg_match('/fill="(#[0-9a-f]{6})"/', $svg, $matches) ? $matches[1] : null;
    }
}
