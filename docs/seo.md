# SEO notes

## Languages
English, Uzbek and Russian each have their own address: the plain URL is English, the others add `?lang=uz` / `?lang=ru` (`SEO_LOCALES` in `.env`, default `en,uz,ru`). `?lang=` always wins over the cookie and the browser header, so a crawler gets a stable language per URL. Every indexable page carries a self-referencing canonical, `hreflang` for those three plus `x-default`, and the sitemap lists each language with its alternates. The other interface languages (es, hi, pt_BR, id, tr) still work for visitors but are not indexed separately (their canonical is the plain URL).
Everything comes from `App\Support\SeoUrls`; do not hand-write hreflang or canonical links. The default language is `config('app.default_locale')` (`APP_LOCALE`), because `app.locale` is overwritten per request.

## Sitemap
`/sitemap.xml` is an index of `/sitemaps/static.xml`, `posts.xml` and `users.xml`, built on request and cached for 10 minutes (no file in `public/`). Profiles are listed only if the user has asked a question. A sitemap file holds 50,000 URLs and every page appears three times, so past roughly 16,000 questions `posts.xml` needs splitting.

## IndexNow (Bing, Yandex, Naver, Seznam)
Set `INDEXNOW_KEY` (`openssl rand -hex 16`) in the production `.env`. The key is served at `/indexnow-key.txt` and every question create, edit and delete submits its three language addresses plus the home page (`PingSearchEngines`, production only). Google has no push API for this content: it follows the sitemap `lastmod`.

## Question pages
Schema is `QAPage` > `Question` with the two options as `suggestedAnswer` (no "accepted" answer), built in `App\Support\QuestionSchema`. Title is cut near 60 characters; the description is the AI summary, or "question, A or B, N votes". Each page has one `<h1>`. `/posts/ID` and `/p/ID/slug` answer 301 to `/@author/post/ID`. Link previews use the share card (`/@author/post/ID/card.jpg`).

## Speed
The question list is in the page, not revealed by a timer; option pictures have a real `src` (the first question's are `fetchpriority=high`, the rest `loading=lazy`); the AI panel is rendered in its final state so Alpine moves nothing; Google Analytics loads after the page has loaded. Lighthouse (mobile, throttled, local): home 70 to 88, question 81 to 97, SEO and accessibility 100, layout shift 0.
Left as it is: AdSense and the analytics tag still cost about 500 KB and a third-party cookie (best practices 79).

## After deploying
1. Search Console: add the property, submit `https://www.goat.uz/sitemap.xml`, and check "Pages" and "Core Web Vitals" after a few days.
2. Bing Webmaster Tools: add the site (it can import from Search Console); IndexNow starts working once the key file is reachable.
3. The page cache (`cache.response`) is keyed per language since the language fix: run `php artisan responsecache:clear` once after deploying.
