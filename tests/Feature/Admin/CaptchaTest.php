<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Captcha\CaptchaServiceInterface;
use App\Services\Captcha\MathCaptchaService;
use App\Services\Captcha\NullCaptchaService;
use App\Services\Captcha\TurnstileCaptchaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['password' => Hash::make('kata-sandi-rahasia')]);
    }

    private function useMathDriver(): MathCaptchaService
    {
        config(['captcha.driver' => 'math']);

        $service = new MathCaptchaService(app('session.store'));
        $this->app->instance(CaptchaServiceInterface::class, $service);

        return $service;
    }

    /* ------------------------------------------------------------- driver */

    public function test_the_configured_driver_is_resolved(): void
    {
        foreach ([
            'null' => NullCaptchaService::class,
            'math' => MathCaptchaService::class,
        ] as $driver => $expected) {
            $this->app->forgetInstance(CaptchaServiceInterface::class);
            config(['captcha.driver' => $driver]);

            $this->assertInstanceOf($expected, app(CaptchaServiceInterface::class));
        }
    }

    public function test_turnstile_is_used_when_it_is_configured(): void
    {
        $this->app->forgetInstance(CaptchaServiceInterface::class);
        config([
            'captcha.driver' => 'turnstile',
            'captcha.turnstile.site_key' => 'site',
            'captcha.turnstile.secret_key' => 'secret',
        ]);

        $service = app(CaptchaServiceInterface::class);

        $this->assertInstanceOf(TurnstileCaptchaService::class, $service);
        $this->assertTrue($service->enabled());
        // Turnstile draws its own widget, so it poses no server-side question.
        $this->assertNull($service->challenge());
    }

    public function test_turnstile_stays_disabled_until_both_keys_are_present(): void
    {
        $service = new TurnstileCaptchaService('site-only', null, 'https://example.test', 5);

        $this->assertFalse($service->enabled());
    }

    /* ---------------------------------------------------------- the login */

    public function test_the_login_page_shows_a_readable_question(): void
    {
        $this->useMathDriver();

        $response = $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Verifikasi: berapa hasil')
            // A written question with real controls, never an image.
            ->assertSee('<fieldset', false)
            ->assertSee('<legend', false)
            ->assertDontSee('<img', false);

        // Four radios, so picking one is what the markup actually means.
        $this->assertSame(4, substr_count($response->getContent(), 'type="radio"'));
    }

    public function test_the_challenge_offers_four_options_including_the_answer(): void
    {
        $captcha = $this->useMathDriver();

        for ($round = 0; $round < 25; $round++) {
            $challenge = $captcha->challenge();

            [$first, , $second] = explode(' ', $challenge->question);
            $answer = (int) $first + (int) $second;

            $this->assertTrue($challenge->isMultipleChoice());
            $this->assertCount(4, $challenge->options);
            $this->assertSame($challenge->options, array_unique($challenge->options));
            $this->assertContains((string) $answer, $challenge->options, 'Jawaban benar harus selalu ada di antara pilihan.');

            // Distractors stay plausible: close to the answer and never below
            // the smallest sum this challenge can produce.
            foreach ($challenge->options as $option) {
                $this->assertGreaterThanOrEqual(2, (int) $option);
                $this->assertLessThanOrEqual(3, abs((int) $option - $answer));
            }
        }
    }

    public function test_the_answer_is_not_always_in_the_same_position(): void
    {
        $captcha = $this->useMathDriver();
        $positions = [];

        for ($round = 0; $round < 60; $round++) {
            $challenge = $captcha->challenge();
            [$first, , $second] = explode(' ', $challenge->question);
            $positions[] = array_search((string) ((int) $first + (int) $second), $challenge->options, true);
        }

        // A fixed slot would let a bot skip the question entirely.
        $this->assertGreaterThan(1, count(array_unique($positions)));
    }

    public function test_a_wrong_answer_blocks_the_login_even_with_correct_credentials(): void
    {
        $captcha = $this->useMathDriver();
        $challenge = $captcha->challenge();

        [$first, , $second] = explode(' ', $challenge->question);
        $answer = (string) ((int) $first + (int) $second);

        // One of the offered options, but the wrong one.
        $wrong = collect($challenge->options)->reject(fn ($o) => $o === $answer)->first();

        $this->post(route('admin.login.store'), [
            'email' => $this->user->email,
            'password' => 'kata-sandi-rahasia',
            'captcha' => $wrong,
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_a_value_that_was_never_offered_is_rejected(): void
    {
        $captcha = $this->useMathDriver();
        $captcha->challenge();

        // The options are a convenience for the person; the server still checks
        // the answer rather than trusting the submitted value.
        $this->post(route('admin.login.store'), [
            'email' => $this->user->email,
            'password' => 'kata-sandi-rahasia',
            'captcha' => '9999',
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_a_missing_answer_blocks_the_login(): void
    {
        $this->useMathDriver();

        $this->post(route('admin.login.store'), [
            'email' => $this->user->email,
            'password' => 'kata-sandi-rahasia',
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_the_correct_answer_lets_a_valid_login_through(): void
    {
        $captcha = $this->useMathDriver();

        [$first, , $second] = explode(' ', $captcha->challenge()->question);

        $this->post(route('admin.login.store'), [
            'email' => $this->user->email,
            'password' => 'kata-sandi-rahasia',
            'captcha' => (string) ((int) $first + (int) $second),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_an_answer_cannot_be_replayed(): void
    {
        $captcha = $this->useMathDriver();

        [$first, , $second] = explode(' ', $captcha->challenge()->question);
        $answer = (string) ((int) $first + (int) $second);

        // Correct, but spent on a failed attempt.
        $this->post(route('admin.login.store'), [
            'email' => $this->user->email,
            'password' => 'salah',
            'captcha' => $answer,
        ]);

        // The same response must not work a second time.
        $this->assertFalse($captcha->verify($answer));
    }

    public function test_a_non_numeric_answer_is_rejected(): void
    {
        $captcha = $this->useMathDriver();
        $captcha->challenge();

        foreach (['', 'sepuluh', '<script>alert(1)</script>', '10a'] as $answer) {
            $this->assertFalse($captcha->verify($answer));
            $captcha->challenge();
        }
    }

    /* --------------------------------------------------------- turnstile */

    public function test_turnstile_fails_closed_when_the_provider_is_unreachable(): void
    {
        Http::fake(fn () => throw new \RuntimeException('jaringan putus'));

        $service = new TurnstileCaptchaService('site', 'secret', 'https://example.test', 1);

        // An unreachable provider must never become a way past the challenge.
        $this->assertFalse($service->verify('token'));
    }

    public function test_turnstile_accepts_a_verified_token(): void
    {
        Http::fake(['example.test/*' => Http::response(['success' => true])]);

        $service = new TurnstileCaptchaService('site', 'secret', 'https://example.test/verify', 5);

        $this->assertTrue($service->verify('token'));
    }

    public function test_turnstile_rejects_a_token_the_provider_refuses(): void
    {
        // Separate test rather than a second fake: Http::fake() adds stubs, it
        // does not replace them, so a second call would never be reached.
        Http::fake(['example.test/*' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ])]);

        $service = new TurnstileCaptchaService('site', 'secret', 'https://example.test/verify', 5);

        $this->assertFalse($service->verify('token'));
    }

    public function test_the_null_driver_renders_no_challenge(): void
    {
        config(['captcha.driver' => 'null']);
        $this->app->forgetInstance(CaptchaServiceInterface::class);

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('Verifikasi: berapa hasil');

        $this->post(route('admin.login.store'), [
            'email' => $this->user->email,
            'password' => 'kata-sandi-rahasia',
        ])->assertRedirect(route('admin.dashboard'));
    }
}
