<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReviewsTest extends TestCase
{
    public function test_exactly_eleven_reviews_are_configured(): void
    {
        $this->assertCount(11, config('reviews.reviews'));
    }

    public function test_six_google_and_five_trustpilot_reviews_are_configured(): void
    {
        $reviews = collect(config('reviews.reviews'));

        $this->assertSame(6, $reviews->where('source', 'google')->count());
        $this->assertSame(5, $reviews->where('source', 'trustpilot')->count());
    }

    public function test_no_placeholder_or_fake_review_data_remains(): void
    {
        $reviews = collect(config('reviews.reviews'));

        $this->assertFalse($reviews->contains('author', 'Thomas V.'));
        $this->assertFalse($reviews->contains('author', 'Nathalie D.'));
        $this->assertFalse($reviews->contains('author', 'Jean D.'));

        // Every review must carry the fields the real dataset requires —
        // catches any stray incomplete/placeholder entry.
        foreach ($reviews as $review) {
            $this->assertNotEmpty($review['author']);
            $this->assertNotEmpty($review['rating']);
            $this->assertContains($review['source'], ['google', 'trustpilot']);
            $this->assertNotEmpty($review['source_url']);
            $this->assertNotEmpty($review['original_locale']);
            $this->assertNotEmpty($review['original_text']);
            $this->assertArrayHasKey('nl', $review['translations']);
            $this->assertArrayHasKey('fr', $review['translations']);
            $this->assertArrayHasKey('en', $review['translations']);
        }
    }

    public function test_negative_one_star_review_is_retained(): void
    {
        $review = collect(config('reviews.reviews'))->firstWhere('author', 'Viktorija Riskute');

        $this->assertNotNull($review, 'The 1-star review must not be filtered out.');
        $this->assertSame(1, $review['rating']);

        $response = $this->get(route('pages.home', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee('Not very reliable... Started work 5 weeks ago and dissappeared.');
    }

    public function test_one_star_review_renders_exactly_one_filled_star(): void
    {
        $html = $this->get(route('pages.home', ['locale' => 'en']))->assertOk()->getContent();

        // Isolate the exact <article class="review-card">...</article> for
        // Viktorija Riskute and count filled vs. empty stars within just
        // that card — the 1-star rating must not silently become 5 stars.
        $namePos = strpos($html, 'Viktorija Riskute');
        $this->assertNotFalse($namePos);

        $articleStart = strrpos(substr($html, 0, $namePos), '<article class="review-card">');
        $this->assertNotFalse($articleStart);

        $articleEnd = strpos($html, '</article>', $namePos);
        $this->assertNotFalse($articleEnd);

        $cardHtml = substr($html, $articleStart, $articleEnd - $articleStart);

        $filled = substr_count($cardHtml, 'fill="#f59e0b"');
        $empty  = substr_count($cardHtml, 'fill="none"');

        $this->assertSame(1, $filled);
        $this->assertSame(4, $empty);
    }

    public function test_truncated_google_reviews_keep_ellipsis_and_show_read_more(): void
    {
        $response = $this->get(route('pages.home', ['locale' => 'nl']));

        $response->assertOk();
        // jeremy burton's review is source-truncated even though it's short
        // enough to fit within the card's own excerpt limit.
        $response->assertSee('professioneel – en bovendien was …');
        $response->assertSee(config('reviews.platforms.google.url'), false);
    }

    public function test_dutch_locale_shows_dutch_translations(): void
    {
        $response = $this->get(route('pages.home', ['locale' => 'nl']));

        $response->assertOk();
        $response->assertSee('Allround vakman, super tevreden');
        $response->assertSee('Uitstekende service! Goede communicatie! Sterk aanbevolen!');
    }

    public function test_french_locale_shows_french_translations(): void
    {
        $response = $this->get(route('pages.home', ['locale' => 'fr']));

        $response->assertOk();
        $response->assertSee('Professionnel polyvalent, très satisfait');
        $response->assertSee('Excellent service ! Bonne communication ! Fortement recommandé !');
    }

    public function test_english_locale_shows_english_translations(): void
    {
        $response = $this->get(route('pages.home', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee('All-round professional, very satisfied');
        $response->assertSee('Excellent service! Good communication! Strongly recommended!');
    }

    public function test_original_language_review_shows_no_translated_from_note(): void
    {
        // Jeroen Everaerts' review is original_locale=nl; viewed in nl it
        // must not claim to be translated from anything.
        $html = $this->get(route('pages.home', ['locale' => 'nl']))->assertOk()->getContent();

        $namePos = strpos($html, 'Jeroen Everaerts');
        $this->assertNotFalse($namePos);

        $articleStart = strrpos(substr($html, 0, $namePos), '<article class="review-card">');
        $articleEnd = strpos($html, '</article>', $namePos);
        $cardHtml = substr($html, $articleStart, $articleEnd - $articleStart);

        $this->assertStringContainsString('Top kerel', $cardHtml);
        $this->assertStringNotContainsString('review-translated-note', $cardHtml);
    }

    public function test_translated_from_note_appears_for_non_original_locale(): void
    {
        // Tout Clean Services' review is original_locale=fr; viewed in nl it
        // must carry the "translated from French" notice.
        $response = $this->get(route('pages.home', ['locale' => 'nl']));

        $response->assertOk();
        $response->assertSee('Vertaald uit het Frans');
    }

    public function test_trustpilot_published_date_is_shown_and_distinct_from_experience_date(): void
    {
        $review = collect(config('reviews.reviews'))->firstWhere('author', 'Lucia');

        $this->assertSame('2022-12-26', $review['published_date']);
        $this->assertSame('2022-12-22', $review['experience_date']);
        $this->assertNotSame($review['published_date'], $review['experience_date']);

        $response = $this->get(route('pages.home', ['locale' => 'nl']));

        $response->assertOk();
        $response->assertSee('26/12/2022', false);
    }

    public function test_no_review_or_aggregate_rating_structured_data_added(): void
    {
        $response = $this->get(route('pages.home', ['locale' => 'nl']));

        $response->assertOk();
        $response->assertDontSee('"@type": "Review"', false);
        $response->assertDontSee('"@type": "AggregateRating"', false);
        $response->assertDontSee('aggregateRating', false);
    }
}
