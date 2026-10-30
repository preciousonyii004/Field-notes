<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin · Fieldnotes Journal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link rel="stylesheet" href="blog.css" />
    <style>
      .logout-button { border: 0; background: transparent; color: #a6b3a7; padding: 6px; cursor: pointer; }
      .logout-button:hover { color: #fff; }
      .sidebar-account form { margin-left: auto; }
    </style>
  </head>
  <body class="admin-page">
    <aside class="admin-sidebar">
      <a class="wordmark" href="index.html">fieldnotes<span>.</span></a>
      <p class="sidebar-label">WORKSPACE</p>
      <a class="sidebar-link active" href="admin.php"><i class="fa-solid fa-chart-line"></i> Overview</a>
      <a class="sidebar-link" href="#editor"><i class="fa-regular fa-pen-to-square"></i> Write a story</a>
      <a class="sidebar-link" href="index.html"><i class="fa-regular fa-eye"></i> View journal</a>
      <div class="sidebar-note"><span class="status-light"></span> Local workspace<br /><small>Changes stay in this browser</small></div>
      <div class="sidebar-account">
        <span class="avatar"><?= auth_escape(substr($adminUser['first_name'], 0, 1)) ?></span>
        <span><?= auth_escape($adminUser['first_name'] . ' ' . $adminUser['last_name']) ?><small>Administrator</small></span>
        <form method="post" action="logout.php">
          <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>" />
          <button class="logout-button" type="submit" aria-label="Sign out" title="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button>
        </form>
      </div>
    </aside>
    <main class="admin-main">
      <header class="admin-topbar"><div><p class="eyebrow">FIELDNOTES / CONTENT</p><h1>Journal desk</h1></div><a class="outline-button" href="index.html"><i class="fa-solid fa-arrow-up-right-from-square"></i> View site</a></header>
      <section class="admin-content">
        <div class="admin-welcome"><div><p class="eyebrow">YOUR PUBLICATION</p><h2>Make something <em>worth reading.</em></h2><p>Shape the stories your readers will find here.</p></div><div class="welcome-doodle" aria-hidden="true">✳</div></div>
        <div class="stats-grid">
          <article class="stat-panel"><span>Total stories</span><strong id="total-count">0</strong><small><i class="fa-regular fa-file-lines"></i> In your journal</small></article>
          <article class="stat-panel"><span>Published</span><strong id="published-count">0</strong><small><i class="fa-regular fa-circle-check"></i> Live on the site</small></article>
          <article class="stat-panel"><span>Drafts</span><strong id="draft-count">0</strong><small><i class="fa-regular fa-pen-to-square"></i> Still in progress</small></article>
        </div>
        <section class="editor-section" id="editor">
          <div class="admin-section-title"><div><p class="eyebrow">CONTENT STUDIO</p><h2 id="editor-heading">Write a story</h2></div><button type="button" class="quiet-button" id="reset-editor">Clear form</button></div>
          <form id="post-form" class="post-form" enctype="multipart/form-data">
            <input type="hidden" id="post-id" />
            <input type="hidden" id="post-csrf" value="<?= auth_escape(auth_csrf_token()) ?>" />
            <div class="form-main-fields">
              <label>Story title<input id="post-title" name="title" type="text" placeholder="Give your story a thoughtful title" maxlength="100" required /></label>
              <label>Short introduction<textarea id="post-excerpt" name="excerpt" rows="2" maxlength="180" placeholder="A short preview for the journal listing" required></textarea></label>
              <label>Story content<textarea id="post-content" name="content" rows="7" placeholder="Write your story here..." required></textarea></label>
            </div>
            <div class="form-side-fields">
              <label>Category<select id="post-category" name="category"><option>Living</option><option>Culture</option><option>Places</option><option>Practice</option></select></label>
              <label>Cover image from your device<input id="post-upload" name="cover_image" type="file" accept="image/jpeg,image/png,image/webp" /></label>
              <p class="upload-hint">JPEG, PNG, or WebP · Up to 5 MB</p>
              <img id="cover-preview" class="cover-preview" alt="Selected cover preview" hidden />
              <label>Or use an image URL<input id="post-image" name="image_url" type="url" placeholder="https://..." /></label>
              <label>Publishing status<select id="post-status" name="status"><option value="Published">Published</option><option value="Draft">Draft</option></select></label>
              <div class="form-tip"><i class="fa-regular fa-lightbulb"></i><p>Use a direct image link for your cover. Save a draft and finish it later.</p></div>
            </div>
            <div class="form-actions"><p class="form-feedback" id="form-feedback" aria-live="polite"></p><button type="submit" class="solid-button"><i class="fa-regular fa-paper-plane"></i> <span id="submit-label">Publish story</span></button></div>
          </form>
        </section>
        <section class="posts-management">
          <div class="admin-section-title"><div><p class="eyebrow">YOUR LIBRARY</p><h2>Stories</h2></div><div class="library-actions"><button type="button" class="outline-button" id="import-legacy" hidden>Import browser stories</button><label class="admin-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="admin-search" placeholder="Find a story" aria-label="Find a story" /></label></div></div>
          <div class="table-wrap"><table><thead><tr><th>STORY</th><th>CATEGORY</th><th>STATUS</th><th>DATE</th><th><span class="visually-hidden">Actions</span></th></tr></thead><tbody id="admin-post-list"></tbody></table><p class="table-empty" id="admin-empty" hidden>No stories match that search.</p></div>
        </section>
      </section>
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/js/all.min.js" defer></script>
    <script src="blog.js" defer></script>
  </body>
</html>