<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'settings' => SiteSetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $settings = SiteSetting::current();

        $validated = $request->validate([
            'site_title' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'brand_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
            'favicon' => 'nullable|mimes:ico,png,svg|max:2048',
            'remove_logo' => 'nullable|boolean',
            'remove_favicon' => 'nullable|boolean',
        ]);

        if ($request->boolean('remove_logo')) {
            $this->deleteAsset($settings->logo_path);
            $validated['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            $this->deleteAsset($settings->logo_path);
            $validated['logo_path'] = $request->file('logo')->store('site', 'public');
        }

        if ($request->boolean('remove_favicon')) {
            $this->deleteAsset($settings->favicon_path);
            $validated['favicon_path'] = null;
        } elseif ($request->hasFile('favicon')) {
            $this->deleteAsset($settings->favicon_path);
            $validated['favicon_path'] = $request->file('favicon')->store('site', 'public');
        }

        unset($validated['logo'], $validated['favicon'], $validated['remove_logo'], $validated['remove_favicon']);

        $settings->update($validated);
        SiteSetting::forget();

        return redirect()->route('admin.settings.edit')->with('success', 'Site settings updated.');
    }

    protected function deleteAsset(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
