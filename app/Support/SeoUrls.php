<?php

namespace App\Support;

// One address per language and page: the default language has the plain URL, the others get ?lang=xx.
// Canonical, hreflang and the sitemap all come from here, so they cannot disagree.
class SeoUrls
{
    private const OG = ['en' => 'en_US', 'ru' => 'ru_RU', 'uz' => 'uz_UZ', 'es' => 'es_ES', 'hi' => 'hi_IN', 'pt_BR' => 'pt_BR', 'id' => 'id_ID', 'tr' => 'tr_TR'];

    public static function default(): string
    {
        return (string) config('app.default_locale', 'en');
    }

    // languages with their own indexable URL, default first
    public static function locales(): array
    {
        $locales = array_values(array_intersect((array) config('app.seo_locales', []), array_keys((array) config('app.available_locales', []))));

        return array_values(array_unique([self::default(), ...$locales]));
    }

    public static function variants(string $url): array
    {
        $variants = [];
        foreach (self::locales() as $locale) {
            $variants[$locale] = $locale === self::default() ? $url : $url.(str_contains($url, '?') ? '&' : '?').'lang='.$locale;
        }

        return $variants;
    }

    // the address of the page being served: its own language variant when ?lang= asked for one
    public static function canonical(string $url, ?string $requestedLang): string
    {
        $variants = self::variants($url);

        return $requestedLang !== null && isset($variants[$requestedLang]) ? $variants[$requestedLang] : $url;
    }

    // hreflang uses BCP 47: pt_BR -> pt-BR
    public static function hreflang(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }

    public static function ogLocale(string $locale): string
    {
        return self::OG[$locale] ?? str_replace('-', '_', $locale);
    }
}
