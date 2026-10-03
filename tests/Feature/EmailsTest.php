<?php

namespace Tests\Feature;

use App\Mail\EmailVerification;
use App\Mail\NewPostsNotification;
use App\Mail\RegistrationExpired;
use App\Mail\UnsubscribedNotification;
use App\Mail\WelcomeMessage;
use App\Models\Post;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailsTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return array_map(fn ($l) => [$l], ['en', 'ru', 'uz', 'es', 'hi', 'pt_BR', 'id', 'tr']);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['first_name' => 'Aziza', 'receives_notifications' => true]);
    }

    private function posts(): array
    {
        $author = User::factory()->create();
        $main = Post::factory()->create(['user_id' => $author->id, 'question' => 'Tea or coffee?', 'option_one_title' => 'Tea', 'option_two_title' => 'Coffee', 'total_votes' => 40, 'option_one_votes' => 25, 'option_two_votes' => 15]);
        $more = collect([
            Post::factory()->create(['user_id' => $author->id, 'question' => 'Cats or dogs?', 'total_votes' => 7]),
            Post::factory()->create(['user_id' => $author->id, 'question' => 'Sea or mountains?', 'total_votes' => 0]),
        ]);

        return [$main, $more];
    }

    // name => [subject, html, text]
    private function everyEmail(User $user): array
    {
        [$main, $more] = $this->posts();
        $out = [];
        foreach ([
            'verification' => new EmailVerification($user, 'https://www.goat.uz/email/verify/1/abc?signature=x'),
            'welcome' => new WelcomeMessage($user),
            'expired' => new RegistrationExpired($user),
            'unsubscribed' => new UnsubscribedNotification($user, '203.0.113.7'),
            'digest' => new NewPostsNotification($user, $main, $more),
        ] as $name => $mailable) {
            $content = $mailable->content();
            $out[$name] = [$mailable->envelope()->subject, $mailable->render(), view($content->text, $content->with)->render()];
        }

        $reset = (new QueuedResetPassword('reset-token'))->toMail($user);
        $out['reset'] = [$reset->subject, $reset->render(), view($reset->view[1], $reset->viewData)->render()];

        return $out;
    }

    #[DataProvider('locales')]
    public function test_every_email_is_clean_in_every_language(string $locale): void
    {
        app()->setLocale($locale);
        $lang = str_replace('_', '-', $locale);

        foreach ($this->everyEmail($this->user()) as $name => [$subject, $html, $text]) {
            $where = "{$name} ({$locale})";

            $this->assertNotSame('', trim($subject), "{$where}: subject");
            $this->assertLessThanOrEqual(80, mb_strlen($subject), "{$where}: subject too long for an inbox");
            $this->assertDoesNotMatchRegularExpression('/\bmail\.[a-z_]+\.[a-z_0-9]+/', $subject.$html.$text, "{$where}: a translation key leaked");

            $this->assertStringContainsString("<html lang=\"{$lang}\"", $html, $where);
            // the bugs of the old templates: markdown shown as code, tags and <br> printed as text
            $this->assertStringNotContainsString('<pre', $html, $where);
            $this->assertStringNotContainsString('<code', $html, $where);
            $this->assertStringNotContainsString('&lt;table', $html, $where);
            $this->assertStringNotContainsString('&lt;br', $html, $where);

            $this->assertStringContainsString('display: none; max-height: 0', $html, "{$where}: preheader");
            $this->assertMatchesRegularExpression('#<img src="https?://[^"]+/images/email/logo-white\.png"[^>]*alt="GOAT\.uz"#', $html, "{$where}: logo with alt text");

            preg_match_all('/<img\b[^>]*>/', $html, $images);
            foreach ($images[0] as $image) {
                $this->assertMatchesRegularExpression('/\balt="[^"]+"/', $image, "{$where}: every picture needs alt text: {$image}");
                $this->assertMatchesRegularExpression('#\bsrc="https?://#', $image, "{$where}: pictures need absolute addresses");
            }
            preg_match_all('/<a\b[^>]*\bhref="([^"]*)"/', $html, $links);
            $this->assertNotEmpty($links[1], $where);
            foreach ($links[1] as $href) {
                $this->assertMatchesRegularExpression('#^https?://#', html_entity_decode($href), "{$where}: relative or empty link '{$href}'");
            }

            $this->assertSame($text, strip_tags($text), "{$where}: the text version contains HTML");
            $this->assertMatchesRegularExpression('#https?://#', $text, "{$where}: the text version has no link");
            $this->assertStringNotContainsString('&amp;', $text, "{$where}: the text version is escaped");
        }
    }

    #[DataProvider('locales')]
    public function test_the_translations_are_complete_and_keep_their_placeholders(string $locale): void
    {
        $english = require lang_path('en/mail.php');
        $other = require lang_path("{$locale}/mail.php");

        $flatten = function (array $a, string $prefix = '') use (&$flatten) {
            $out = [];
            foreach ($a as $k => $v) {
                is_array($v) ? $out += $flatten($v, "{$prefix}{$k}.") : $out["{$prefix}{$k}"] = $v;
            }

            return $out;
        };
        $en = $flatten($english);
        $tr = $flatten($other);

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($tr))), "{$locale}: missing keys");
        $this->assertSame([], array_values(array_diff(array_keys($tr), array_keys($en))), "{$locale}: keys English does not have");
        foreach ($en as $key => $value) {
            preg_match_all('/:[a-z]+/', $value, $a);
            preg_match_all('/:[a-z]+/', $tr[$key], $b);
            $this->assertEqualsCanonicalizing($a[0], $b[0], "{$locale}: placeholders of {$key}");
        }
    }

    public function test_the_welcome_email_renders_and_links_to_places_that_exist(): void
    {
        $html = (new WelcomeMessage($this->user()))->render();

        foreach ([route('home'), route('posts.create'), route('profile.edit')] as $url) {
            $this->assertStringContainsString('href="'.$url.'"', $html);
        }
        $this->assertStringContainsString('Welcome to GOAT.uz, Aziza!', $html);
    }

    public function test_durations_are_written_in_the_readers_language(): void
    {
        app()->setLocale('en');
        $this->assertStringContainsString('works for 1 hour', (new EmailVerification($this->user(), 'https://x.test'))->render());

        app()->setLocale('ru');
        $this->assertStringContainsString('действует 1 час', (new EmailVerification($this->user(), 'https://x.test'))->render());

        app()->setLocale('uz');
        $this->assertStringContainsString('1 soat', (new EmailVerification($this->user(), 'https://x.test'))->render());
    }

    public function test_the_verification_text_and_the_link_lifetime_come_from_one_number(): void
    {
        $user = $this->user(['email_verification_token' => 'tok']);

        $url = app(EmailVerificationService::class)->generateVerificationUrl($user);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertEqualsWithDelta(time() + EmailVerificationService::TTL_MINUTES * 60, (int) $query['expires'], 5);
        $this->assertSame(60, EmailVerificationService::TTL_MINUTES);
    }

    public function test_mail_to_a_user_is_written_in_their_saved_language(): void
    {
        config(['mail.default' => 'array']);
        $user = $this->user(['locale' => 'ru']);

        Mail::to($user)->send(new WelcomeMessage($user));

        $message = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertStringContainsString('Добро пожаловать', $message->getSubject());
        $this->assertStringContainsString('Добро пожаловать', $message->getHtmlBody());
        $this->assertNotNull($message->getTextBody());
    }

    public function test_an_unknown_saved_language_falls_back_instead_of_breaking(): void
    {
        $this->assertNull($this->user(['locale' => 'xx'])->preferredLocale());
        $this->assertNull($this->user(['locale' => null])->preferredLocale());
        $this->assertSame('uz', $this->user(['locale' => 'uz'])->preferredLocale());
    }

    public function test_the_reset_email_uses_the_shared_layout_the_configured_lifetime_and_the_token(): void
    {
        config(['auth.passwords.users.expire' => 30]);
        $user = $this->user(['email' => 'aziza@example.com']);

        $mail = (new QueuedResetPassword('the-token'))->toMail($user);
        $html = $mail->render();

        $this->assertSame('Reset your GOAT.uz password', $mail->subject);
        $this->assertStringContainsString('the-token', $html);
        $this->assertStringContainsString('aziza%40example.com', $html);
        $this->assertStringContainsString('30 minutes', $html);
        $this->assertStringContainsString('images/email/logo-white.png', $html);
        $this->assertStringContainsString('Your password stays the same', $html);
    }

    public function test_the_reset_email_is_written_in_the_language_of_the_request(): void
    {
        Notification::fake();
        $user = $this->user();

        app()->setLocale('uz');
        $user->sendPasswordResetNotification('tok');

        Notification::assertSentTo($user, QueuedResetPassword::class, fn ($notification) => $notification->locale === 'uz');
    }

    public function test_the_digest_carries_one_click_unsubscribe_headers_and_needs_no_stored_token(): void
    {
        [$main, $more] = $this->posts();
        $user = $this->user();

        $mail = new NewPostsNotification($user, $main, $more);
        $headers = $mail->headers()->text;

        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
        $url = trim($headers['List-Unsubscribe'], '<>');
        $this->assertTrue(URL::hasValidSignature(\Illuminate\Http\Request::create($url)));
        $this->assertStringContainsString("/email/{$user->id}/unsubscribe", $url);
        $this->assertSame(0, DB::table('unsubscribe_tokens')->count(), 'building the email must not write token rows');

        $html = $mail->render();
        $this->assertStringContainsString(html_entity_decode($url), html_entity_decode($html), 'the visible footer link is the same signed address');
        $this->assertStringContainsString('/card.jpg?v=', $html, 'the hottest debate shows its share card, a JPEG every client can show');
        $this->assertStringNotContainsString('.webp', $html);
        $this->assertStringContainsString('Cats or dogs?', $html);
    }

    public function test_the_digest_is_always_the_same_layout(): void
    {
        [$main, $more] = $this->posts();
        $user = $this->user();

        $first = (new NewPostsNotification($user, $main, $more))->render();
        $second = (new NewPostsNotification($user, $main, $more))->render();

        $this->assertSame(preg_replace('/signature=[a-f0-9]+/', '', $first), preg_replace('/signature=[a-f0-9]+/', '', $second));
    }

    public function test_the_sample_command_writes_every_email_as_html_and_text_and_refuses_to_send_in_production(): void
    {
        $this->posts();
        $this->user();
        $dir = sys_get_temp_dir().'/samples-'.uniqid();

        $this->artisan('mail:samples', ['--dir' => $dir, '--locale' => 'ru'])->assertExitCode(0);

        foreach (['verification', 'welcome', 'expired', 'unsubscribed', 'digest', 'reset'] as $name) {
            $this->assertFileExists("{$dir}/{$name}.html");
            $this->assertFileExists("{$dir}/{$name}.txt");
        }
        $this->assertStringContainsString('lang="ru"', file_get_contents("{$dir}/welcome.html"));
        File::deleteDirectory($dir);

        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('mail:samples', ['--to' => 'someone@example.com'])->expectsOutputToContain('not sent from production')->assertExitCode(1);
    }

    // ---- the unsubscribe page and one-click unsubscribe

    private function signed(User $user, string $route = 'notifications.email.unsubscribe'): string
    {
        return URL::signedRoute($route, ['user' => $user->id]);
    }

    public function test_opening_the_unsubscribe_link_only_shows_a_page_and_changes_nothing(): void
    {
        $user = $this->user();

        $html = $this->get($this->signed($user))->assertOk()->getContent();

        $this->assertStringContainsString('Unsubscribe from debate updates?', $html);
        $this->assertStringContainsString('noindex', $html);
        $this->assertTrue((bool) $user->fresh()->receives_notifications, 'a link scanner opening the link must not unsubscribe anyone');
    }

    public function test_confirming_unsubscribes_once_and_sends_a_single_confirmation(): void
    {
        Mail::fake();
        $user = $this->user();
        $url = $this->signed($user);

        $this->post($url)->assertOk()->assertSee('You’re unsubscribed', false)->assertSee('Turn updates back on', false);
        $this->post($url)->assertOk();

        $this->assertFalse((bool) $user->fresh()->receives_notifications);
        Mail::assertQueued(UnsubscribedNotification::class, 1);
    }

    public function test_mail_clients_can_unsubscribe_with_one_click_without_a_csrf_token(): void
    {
        Mail::fake();
        $user = $this->user();

        $response = $this->post($this->signed($user), ['List-Unsubscribe' => 'One-Click']);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertFalse((bool) $user->fresh()->receives_notifications);
    }

    public function test_a_changed_or_missing_signature_is_refused_politely(): void
    {
        $user = $this->user();
        $other = $this->user();
        $url = $this->signed($user);

        // someone else's id with this user's signature, and no signature at all
        $forged = str_replace("/email/{$user->id}/", "/email/{$other->id}/", $url);
        $this->post($forged)->assertForbidden()->assertSee('This link doesn’t work', false);
        $this->post(route('notifications.email.unsubscribe', ['user' => $user->id]))->assertForbidden();
        $this->get($forged)->assertForbidden();

        $this->assertTrue((bool) $user->fresh()->receives_notifications);
        $this->assertTrue((bool) $other->fresh()->receives_notifications);
    }

    public function test_a_valid_signature_for_a_deleted_account_does_nothing(): void
    {
        $user = $this->user();
        $url = $this->signed($user);
        $user->forceDelete();

        $this->post($url)->assertForbidden();
    }

    public function test_turning_updates_back_on_and_the_already_unsubscribed_state(): void
    {
        $user = $this->user(['receives_notifications' => false]);

        $this->get($this->signed($user))->assertOk()->assertSee('You’re already unsubscribed', false);

        $this->post($this->signed($user, 'notifications.email.resubscribe'))->assertOk()->assertSee('Updates are back on', false);
        $this->assertTrue((bool) $user->fresh()->receives_notifications);
    }

    public function test_the_unsubscribe_page_speaks_the_visitors_language(): void
    {
        $user = $this->user();

        $this->get($this->signed($user), ['Accept-Language' => 'ru'])->assertOk()->assertSee('Отписаться от обновлений о дебатах?', false);
    }

    public function test_links_from_older_emails_now_answer_with_a_page_not_json(): void
    {
        Mail::fake();
        $user = $this->user();
        DB::table('unsubscribe_tokens')->insert(['user_id' => $user->id, 'token' => 'old-token', 'expires_at' => now()->addDay(), 'created_at' => now()]);

        $response = $this->get('/notifications/unsubscribe/old-token')->assertOk();

        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $response->assertSee('You’re unsubscribed', false);
        $this->assertFalse((bool) $user->fresh()->receives_notifications);

        $expired = $this->get('/notifications/unsubscribe/nonsense')->assertStatus(410);
        $this->assertStringContainsString('text/html', $expired->headers->get('Content-Type'));
        $expired->assertSee('This link doesn’t work', false);
    }

    public function test_the_reset_notification_really_goes_through_the_queue_without_php_warnings(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $user = $this->user();

        // an undefined $delay used to raise a warning here
        $user->sendPasswordResetNotification('tok');

        \Illuminate\Support\Facades\Queue::assertPushed(\Illuminate\Notifications\SendQueuedNotifications::class);
    }
}
