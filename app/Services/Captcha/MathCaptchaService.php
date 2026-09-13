<?php

namespace App\Services\Captcha;

use Illuminate\Contracts\Session\Session;

/**
 * A self-contained challenge that needs no third-party account: a short
 * arithmetic question answered by picking one of four offered numbers.
 *
 * Deliberately **text and real form controls**, never a distorted image. Image
 * CAPTCHAs are unreadable to screen-reader users and to many people with low
 * vision — on a public-sector admin panel that would lock out exactly the staff
 * this system is meant to serve. A written question with radio options is
 * announced correctly by assistive technology and is operable from the
 * keyboard alone.
 *
 * Four options means a blind guess succeeds one time in four. That is weaker
 * than a free-text answer, and it is the rate limits in LoginRequest — five
 * attempts per credential, twenty per IP per minute — that keep the cost of
 * guessing high. This challenge is a speed bump against scripted form posts,
 * not a defence against a determined attacker; Turnstile is that.
 */
class MathCaptchaService implements CaptchaServiceInterface
{
    private const SESSION_KEY = 'captcha.expected';

    private const OPTION_COUNT = 4;

    public function __construct(private readonly Session $session)
    {
    }

    public function enabled(): bool
    {
        return true;
    }

    public function siteKey(): ?string
    {
        return null;
    }

    public function responseField(): string
    {
        return 'captcha';
    }

    public function challenge(): ?Challenge
    {
        $first = random_int(1, 9);
        $second = random_int(1, 9);
        $answer = $first + $second;

        $this->session->put(self::SESSION_KEY, $answer);

        return new Challenge(
            question: "{$first} + {$second}",
            options: $this->options($answer),
        );
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        // Pulled, not read: an answer is good for exactly one attempt, so a
        // captured response cannot be replayed.
        $expected = $this->session->pull(self::SESSION_KEY);

        if ($expected === null || ! is_numeric(trim((string) $token))) {
            return false;
        }

        return (int) trim((string) $token) === (int) $expected;
    }

    /**
     * The correct answer plus near-miss distractors, shuffled.
     *
     * Distractors sit close to the answer so none of them can be dismissed at a
     * glance, and the position is randomised so the right choice is never at a
     * predictable index.
     *
     * @return array<int, string>
     */
    private function options(int $answer): array
    {
        $options = [$answer];
        $offsets = [-3, -2, -1, 1, 2, 3];
        shuffle($offsets);

        foreach ($offsets as $offset) {
            if (count($options) === self::OPTION_COUNT) {
                break;
            }

            $candidate = $answer + $offset;

            // Sums here are always at least 2, so a distractor below that would
            // be recognisable as impossible.
            if ($candidate >= 2 && ! in_array($candidate, $options, true)) {
                $options[] = $candidate;
            }
        }

        shuffle($options);

        return array_map(strval(...), $options);
    }
}

