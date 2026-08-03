{{-- Visible FAQ block. Always rendered as plain, expanded <details> content so
     the answers are in the DOM without interaction — FAQPage markup whose
     answers are hidden behind JavaScript is not eligible for rich results, and
     it is bad for the reader either way.

     Expects: $faqs (array of ['question' => .., 'answer' => ..]) and $faqTitle.
     The matching FAQPage node is added to the schema graph by the caller. --}}
<section class="section section-white faq-section">
    <div class="container">
        <div class="section-header">
            <h2>{{ $faqTitle }}</h2>
        </div>

        <div class="faq-list">
            @foreach ($faqs as $faq)
                <details class="faq-item" @if ($loop->first) open @endif>
                    <summary class="faq-question">
                        <h3>{{ $faq['question'] }}</h3>
                        <span class="faq-marker" aria-hidden="true"></span>
                    </summary>
                    <div class="faq-answer">
                        <p>{{ $faq['answer'] }}</p>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>
