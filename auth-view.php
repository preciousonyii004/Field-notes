<?php
declare(strict_types=1);

function auth_page_start(string $title, string $kicker, string $heading, string $description, string $topLabel, string $topHref): void
{
    $escapedTitle = auth_escape($title);
    $escapedKicker = auth_escape($kicker);
    $escapedHeading = str_replace(
      ['&lt;em&gt;', '&lt;/em&gt;'],
      ['<em>', '</em>'],
      auth_escape($heading)
    );
    $escapedDescription = auth_escape($description);
    $escapedTopLabel = auth_escape($topLabel);
    $escapedTopHref = auth_escape($topHref);
    echo <<<HTML
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#f8f8f3" />
    <title>{$escapedTitle} · Fieldnotes Journal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link rel="stylesheet" href="style.css" />
  </head>
  <body class="auth-page">
    <main class="auth-shell">
      <aside class="auth-art" aria-label="Fieldnotes journal">
        <div class="auth-photo">
          <img src="https://images.unsplash.com/photo-1445116572660-236099ec97a0?auto=format&fit=crop&w=1200&q=85" alt="A quiet, sunlit cafe interior" />
          <div class="auth-photo-caption"><span>FIELDNOTES JOURNAL</span><span>Pay attention. Find the good.</span></div>
        </div>
        <a class="auth-brand" href="index.html">fieldnotes<span>.</span></a>
        <div class="auth-stamp">READ<br />SLOWLY</div>
        <div class="auth-art-footer"><span>An independent journal</span><span>Est. 2024</span></div>
      </aside>
      <section class="auth-panel">
        <div class="auth-topline"><span>{$escapedTopLabel}</span><a href="{$escapedTopHref}">{$escapedTopHref}</a></div>
        <div class="auth-form-wrap">
          <p class="auth-kicker">{$escapedKicker}</p>
          <h1 class="auth-title">{$escapedHeading}</h1>
          <p class="auth-intro">{$escapedDescription}</p>
          <script>
            document.addEventListener('click', function (event) {
              const closeButton = event.target.closest('.auth-message-close');
              if (closeButton) closeButton.closest('.auth-message').remove();
            });
          </script>
HTML;
}

function auth_page_end(): void
{
    echo <<<'HTML'
          <a class="auth-back" href="index.html"><i class="fa-solid fa-arrow-left"></i> Back to the journal</a>
        </div>
        <footer class="auth-panel-footer">© 2026 FIELDNOTES JOURNAL</footer>
      </section>
    </main>
  </body>
</html>
HTML;
}