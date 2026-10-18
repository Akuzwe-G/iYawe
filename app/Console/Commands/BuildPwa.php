<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildPwa extends Command
{
    protected $signature = 'iyawe:pwa-build';

    protected $description = 'Generate the iYawe PWA icons, manifest and static-only service worker allowlist';

    public function handle(): int
    {
        File::ensureDirectoryExists(public_path('pwa/icons'));
        foreach ([192, 512, 180] as $size) {
            $this->icon($size, public_path('pwa/icons/icon-'.$size.'.png'), false);
        }
        $this->icon(512, public_path('pwa/icons/maskable-512.png'), true);
        $assets = [];
        if (is_file(public_path('build/manifest.json'))) {
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
            foreach ($manifest as $entry) {
                foreach (array_merge([$entry['file'] ?? null], $entry['css'] ?? [], $entry['assets'] ?? []) as $file) {
                    if ($file && preg_match('/\.(?:js|css|woff2?|png|svg)$/i', $file) && is_file(public_path('build/'.$file))) {
                        $assets[] = '/build/'.$file;
                    }
                }
            }
        }
        $assets = array_values(array_unique($assets));
        sort($assets);
        $version = substr(hash('sha256', json_encode($assets).file_get_contents(resource_path('pwa/offline.html')).file_get_contents(resource_path('pwa/service-worker.js'))), 0, 16);
        $offline = file_get_contents(resource_path('pwa/offline.html'));
        if (is_file(public_path('build/fonts-manifest.json'))) {
            $fonts = json_decode(file_get_contents(public_path('build/fonts-manifest.json')), true, 512, JSON_THROW_ON_ERROR);
            $css = $fonts['style']['familyStyles']['montserrat'] ?? '';
            $offline = preg_replace('/<style>.*?\/\* PWA_FONTS \*\/.*?<\/style>/s', '', $offline);
            if ($css && ! str_contains($offline, 'PWA_FONTS')) {
                $offline = str_replace('</head>', '<style>/* PWA_FONTS */'.$css.'</style></head>', $offline);
                File::put(public_path('offline.html'), $offline);
            }
        }
        File::put(public_path('offline.html'), $offline);
        $iconHashes = implode('', array_map(fn ($file) => hash_file('sha256', $file), glob(public_path('pwa/icons/*.png'))));
        $version = substr(hash('sha256', $version.$offline.$iconHashes), 0, 16);
        foreach (['icon-192.png', 'icon-512.png', 'icon-180.png', 'maskable-512.png'] as $icon) {
            $assets[] = '/pwa/icons/'.$icon.'?v='.$version;
        }
        $assets[] = '/offline.html?v='.$version;
        $worker = str_replace(['__VERSION__', '__ASSETS__'], [$version, json_encode($assets, JSON_UNESCAPED_SLASHES)], file_get_contents(resource_path('pwa/service-worker.js')));
        File::put(public_path('sw.js'), $worker);
        $iconBase = '/pwa/icons/';
        $manifest = ['id' => '/', 'name' => 'iYawe', 'short_name' => 'iYawe', 'description' => 'Digital acknowledgements, made simple.', 'start_url' => '/dashboard', 'scope' => '/', 'display' => 'standalone', 'theme_color' => '#1747A6', 'background_color' => '#F3F8FC', 'icons' => [
            ['src' => $iconBase.'icon-192.png?v='.$version, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $iconBase.'icon-512.png?v='.$version, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $iconBase.'maskable-512.png?v='.$version, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ]];
        File::put(public_path('manifest.webmanifest'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put(public_path('pwa/version.json'), json_encode(['version' => $version]));
        $this->info('PWA icons, manifest and static-only worker generated.');

        return self::SUCCESS;
    }

    private function icon(int $size, string $path, bool $maskable): void
    {
        $image = imagecreatetruecolor($size, $size);
        for ($x = 0; $x < $size; $x++) {
            $ratio = $x / max(1, $size - 1);
            $a = $ratio <= .55 ? [23, 71, 166] : [22, 137, 185];
            $b = $ratio <= .55 ? [22, 137, 185] : [22, 183, 140];
            $t = $ratio <= .55 ? $ratio / .55 : ($ratio - .55) / .45;
            $color = imagecolorallocate($image, (int) round($a[0] + ($b[0] - $a[0]) * $t), (int) round($a[1] + ($b[1] - $a[1]) * $t), (int) round($a[2] + ($b[2] - $a[2]) * $t));
            imageline($image, $x, 0, $x, $size - 1, $color);
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $scale = $maskable ? .8 : 1;
        imagefilledellipse($image, (int) ($size * .5), (int) ($size * (.5 - .2 * $scale)), (int) ($size * .16 * $scale), (int) ($size * .16 * $scale), $white);
        imagefilledrectangle($image, (int) ($size * (.5 - .08 * $scale)), (int) ($size * (.5 - .02 * $scale)), (int) ($size * (.5 + .08 * $scale)), (int) ($size * (.5 + .29 * $scale)), $white);
        imagepng($image, $path, 9);
        imagedestroy($image);
    }
}
