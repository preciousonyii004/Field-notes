const fallbackImage =
  "https://images.unsplash.com/photo-1449247709967-d4461a6a6103?auto=format&fit=crop&w=900&q=80";
const legacyStorageKey = "fieldnotes.posts.v1";
let postsCache = [];

async function fetchPosts(manage = false) {
  const endpoint = manage ? "posts-api.php?manage=1" : "posts-api.php";
  const response = await fetch(endpoint, { credentials: "same-origin" });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.error || "Could not load stories.");
  postsCache = payload.posts;
  return postsCache;
}

function escapeHtml(value) {
  return String(value ?? "").replace(
    /[&<>"']/g,
    (character) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[character],
  );
}

function formatDate(dateString) {
  const date = new Date(`${dateString}T12:00:00`);
  if (Number.isNaN(date.getTime())) return "Date not set";
  return new Intl.DateTimeFormat("en", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

function renderPublicPosts() {
  const grid = document.getElementById("post-grid");
  if (!grid) return;
  const selectedCategory =
    document.querySelector(".filter-chip.active")?.dataset.category || "All";
  const searchTerm = document
    .getElementById("post-search")
    .value.trim()
    .toLowerCase();
  const posts = postsCache
    .filter((post) => post.status === "Published")
    .filter((post) => {
      const matchesCategory =
        selectedCategory === "All" || post.category === selectedCategory;
      const haystack =
        `${post.title} ${post.excerpt} ${post.content} ${post.category}`.toLowerCase();
      return matchesCategory && haystack.includes(searchTerm);
    })
    .sort((first, second) => second.date.localeCompare(first.date));

  grid.innerHTML = posts
    .map(
      (post, index) => `
    <article class="post-card" style="animation-delay:${Math.min(index * 70, 350)}ms">
      <a class="post-image-wrap story-open" href="#story-${escapeHtml(post.id)}" data-post-id="${escapeHtml(post.id)}" aria-label="Read ${escapeHtml(post.title)}">
        <img class="post-image" src="${escapeHtml(post.image || fallbackImage)}" alt="" loading="lazy" onerror="this.src='${fallbackImage}'" />
      </a>
      <span class="post-category">${escapeHtml(post.category)}</span>
      <h3 class="post-title"><a class="story-open" href="#story-${escapeHtml(post.id)}" data-post-id="${escapeHtml(post.id)}">${escapeHtml(post.title)}</a></h3>
      <p class="post-excerpt">${escapeHtml(post.excerpt)}</p>
      <div class="post-meta"><span>${escapeHtml(formatDate(post.date))}</span><span>5 min read</span></div>
      <button class="post-read story-open" type="button" data-post-id="${escapeHtml(post.id)}">Read story <span aria-hidden="true">→</span></button>
    </article>`,
    )
    .join("");
  document.getElementById("empty-state").hidden = posts.length > 0;
}

async function showStory(postId) {
  const preview = postsCache.find(
    (entry) => entry.id === postId && entry.status === "Published",
  );
  if (!preview) return;
  let dialog = document.getElementById("story-dialog");
  if (!dialog) {
    dialog = document.createElement("dialog");
    dialog.id = "story-dialog";
    dialog.className = "story-dialog";
    document.body.append(dialog);
  }
  let post;
  try {
    const response = await fetch(
      `posts-api.php?id=${encodeURIComponent(postId)}`,
      {
        credentials: "same-origin",
      },
    );
    const result = await response.json();
    if (response.status === 401) {
      showAccountPrompt(postId);
      return;
    }
    if (!response.ok)
      throw new Error(result.error || "Could not load the story.");
    post = result.post;
  } catch (error) {
    showToast(error.message, "error");
    return;
  }
  dialog.innerHTML = `<button class="dialog-close" type="button" aria-label="Close story"><i class="fa-solid fa-xmark"></i></button><img src="${escapeHtml(post.image || fallbackImage)}" alt="" /><div class="dialog-copy"><p class="eyebrow">${escapeHtml(post.category)} · ${escapeHtml(formatDate(post.date))}</p><h2>${escapeHtml(post.title)}</h2><p class="dialog-excerpt">${escapeHtml(post.excerpt)}</p><div class="dialog-content">${escapeHtml(post.content).replace(/\n/g, "<br><br>")}</div></div>`;
  dialog
    .querySelector(".dialog-close")
    .addEventListener("click", () => dialog.close());
  dialog.addEventListener(
    "click",
    (event) => {
      if (event.target === dialog) dialog.close();
    },
    { once: true },
  );
  dialog.showModal();
}

function showToast(message, type = "success") {
  document.querySelector(".auth-toast")?.remove();
  const toast = document.createElement("div");
  toast.className = `auth-toast ${type}`;
  toast.setAttribute("role", type === "error" ? "alert" : "status");
  toast.setAttribute("aria-live", "polite");
  toast.innerHTML =
    '<span class="auth-toast-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span><span class="auth-toast-message"></span><button class="auth-toast-close" type="button" aria-label="Dismiss notification"><i class="fa-solid fa-xmark"></i></button>';
  toast.querySelector(".auth-toast-message").textContent = message;
  toast
    .querySelector(".auth-toast-close")
    .addEventListener("click", () => toast.remove());
  document.body.append(toast);
  window.setTimeout(() => toast.remove(), 6000);
}

function showAccountPrompt(postId) {
  let dialog = document.getElementById("account-dialog");
  if (!dialog) {
    dialog = document.createElement("dialog");
    dialog.id = "account-dialog";
    dialog.className = "account-dialog";
    document.body.append(dialog);
  }
  const returnPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;
  const returnUrl = new URL(returnPath, window.location.origin);
  returnUrl.searchParams.set("read", postId);
  const next = encodeURIComponent(
    `${returnUrl.pathname}${returnUrl.search}${returnUrl.hash}`,
  );
  dialog.innerHTML = `<button class="dialog-close" type="button" aria-label="Close account prompt"><i class="fa-solid fa-xmark"></i></button><span class="account-dialog-icon"><i class="fa-regular fa-bookmark"></i></span><p class="eyebrow">A STORY WORTH YOUR TIME</p><h2>Make yourself <em>at home.</em></h2><p class="account-dialog-copy">Create a free account or sign in to read the full story and join the Fieldnotes community.</p><div class="account-dialog-actions"><a class="solid-button" href="signup.php?next=${next}">Create account <i class="fa-solid fa-arrow-right"></i></a><a class="account-signin" href="login.php?next=${next}">I already have an account</a></div></div>`;
  dialog
    .querySelector(".dialog-close")
    .addEventListener("click", () => dialog.close());
  dialog.addEventListener(
    "click",
    (event) => {
      if (event.target === dialog) dialog.close();
    },
    { once: true },
  );
  dialog.showModal();
}

function renderAdmin() {
  const list = document.getElementById("admin-post-list");
  if (!list) return;
  const posts = postsCache
    .slice()
    .sort((first, second) => second.date.localeCompare(first.date));
  const searchTerm = document
    .getElementById("admin-search")
    .value.trim()
    .toLowerCase();
  const visiblePosts = posts.filter((post) =>
    `${post.title} ${post.category} ${post.status}`
      .toLowerCase()
      .includes(searchTerm),
  );
  document.getElementById("total-count").textContent = posts.length;
  document.getElementById("published-count").textContent = posts.filter(
    (post) => post.status === "Published",
  ).length;
  document.getElementById("draft-count").textContent = posts.filter(
    (post) => post.status === "Draft",
  ).length;
  list.innerHTML = visiblePosts
    .map(
      (post) => `
    <tr>
      <td><div class="story-cell"><img class="table-thumb" src="${escapeHtml(post.image || fallbackImage)}" alt="" onerror="this.src='${fallbackImage}'" /><span><strong>${escapeHtml(post.title)}</strong><small>${escapeHtml(post.excerpt)}</small></span></div></td>
      <td>${escapeHtml(post.category)}</td>
      <td><span class="status-pill ${post.status === "Published" ? "published" : "draft"}">${escapeHtml(post.status)}</span></td>
      <td>${escapeHtml(formatDate(post.date))}</td>
      <td><div class="row-actions"><button class="icon-button" type="button" data-action="edit" data-id="${escapeHtml(post.id)}" aria-label="Edit ${escapeHtml(post.title)}" title="Edit"><i class="fa-regular fa-pen-to-square"></i></button><button class="icon-button delete" type="button" data-action="delete" data-id="${escapeHtml(post.id)}" aria-label="Delete ${escapeHtml(post.title)}" title="Delete"><i class="fa-regular fa-trash-can"></i></button></div></td>
    </tr>`,
    )
    .join("");
  document.getElementById("admin-empty").hidden = visiblePosts.length > 0;
}

function resetEditor() {
  const form = document.getElementById("post-form");
  if (!form) return;
  form.reset();
  document.getElementById("post-id").value = "";
  document.getElementById("editor-heading").textContent = "Write a story";
  document.getElementById("submit-label").textContent = "Publish story";
  document.getElementById("form-feedback").textContent = "";
  const preview = document.getElementById("cover-preview");
  if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
  preview.removeAttribute("src");
  delete preview.dataset.objectUrl;
  preview.hidden = true;
}

function initAdmin() {
  const form = document.getElementById("post-form");
  if (!form) return;
  fetchPosts(true)
    .then(renderAdmin)
    .catch((error) => {
      document.getElementById("form-feedback").textContent = error.message;
      document.getElementById("admin-empty").hidden = false;
      document.getElementById("admin-empty").textContent = error.message;
    });
  document
    .getElementById("admin-search")
    .addEventListener("input", renderAdmin);
  document
    .getElementById("reset-editor")
    .addEventListener("click", resetEditor);
  const legacyPosts = localStorage.getItem(legacyStorageKey);
  const importButton = document.getElementById("import-legacy");
  if (legacyPosts && importButton) {
    importButton.hidden = false;
    importButton.addEventListener("click", async () => {
      const body = new FormData();
      body.set("action", "import_legacy");
      body.set("csrf_token", document.getElementById("post-csrf").value);
      body.set("legacy_posts", legacyPosts);
      importButton.disabled = true;
      document.getElementById("form-feedback").textContent =
        "Importing browser stories...";
      try {
        const response = await fetch("posts-api.php", {
          method: "POST",
          credentials: "same-origin",
          body,
        });
        const result = await response.json();
        if (!response.ok)
          throw new Error(result.error || "Could not import browser stories.");
        localStorage.removeItem(legacyStorageKey);
        importButton.hidden = true;
        await fetchPosts(true);
        renderAdmin();
        renderPublicPosts();
        document.getElementById("form-feedback").textContent =
          `${result.imported} stories imported into the database.`;
      } catch (error) {
        importButton.disabled = false;
        document.getElementById("form-feedback").textContent = error.message;
      }
    });
  }
  document.getElementById("post-upload").addEventListener("change", (event) => {
    const preview = document.getElementById("cover-preview");
    if (preview.dataset.objectUrl)
      URL.revokeObjectURL(preview.dataset.objectUrl);
    const file = event.currentTarget.files[0];
    if (!file) {
      preview.hidden = true;
      preview.removeAttribute("src");
      delete preview.dataset.objectUrl;
      return;
    }
    const objectUrl = URL.createObjectURL(file);
    preview.dataset.objectUrl = objectUrl;
    preview.src = objectUrl;
    preview.hidden = false;
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(form);
    formData.set("id", document.getElementById("post-id").value);
    formData.set("csrf_token", document.getElementById("post-csrf").value);
    const submitButton = form.querySelector('[type="submit"]');
    submitButton.disabled = true;
    document.getElementById("form-feedback").textContent = "Saving story...";
    try {
      const response = await fetch("posts-api.php", {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      });
      const result = await response.json();
      if (!response.ok)
        throw new Error(result.error || "Could not save story.");
      const confirmation =
        result.post.status === "Published"
          ? "Your story is live."
          : "Draft saved.";
      await fetchPosts(true);
      renderAdmin();
      resetEditor();
      document.getElementById("form-feedback").textContent = confirmation;
      renderPublicPosts();
    } catch (error) {
      document.getElementById("form-feedback").textContent = error.message;
    } finally {
      submitButton.disabled = false;
    }
  });

  document
    .getElementById("admin-post-list")
    .addEventListener("click", async (event) => {
      const actionButton = event.target.closest("button[data-action]");
      if (!actionButton) return;
      const post = postsCache.find(
        (entry) => entry.id === actionButton.dataset.id,
      );
      if (!post) return;
      if (actionButton.dataset.action === "delete") {
        if (!window.confirm(`Delete “${post.title}”? This cannot be undone.`))
          return;
        const body = new FormData();
        body.set("action", "delete");
        body.set("id", post.id);
        body.set("csrf_token", document.getElementById("post-csrf").value);
        try {
          const response = await fetch("posts-api.php", {
            method: "POST",
            credentials: "same-origin",
            body,
          });
          const result = await response.json();
          if (!response.ok)
            throw new Error(result.error || "Could not delete story.");
          await fetchPosts(true);
          renderAdmin();
          renderPublicPosts();
        } catch (error) {
          document.getElementById("form-feedback").textContent = error.message;
        }
        return;
      }
      document.getElementById("post-id").value = post.id;
      document.getElementById("post-title").value = post.title;
      document.getElementById("post-excerpt").value = post.excerpt;
      document.getElementById("post-content").value = post.content;
      document.getElementById("post-category").value = post.category;
      document.getElementById("post-image").value = post.image || "";
      document.getElementById("post-upload").value = "";
      const coverPreview = document.getElementById("cover-preview");
      if (coverPreview.dataset.objectUrl)
        URL.revokeObjectURL(coverPreview.dataset.objectUrl);
      delete coverPreview.dataset.objectUrl;
      coverPreview.src = post.image || fallbackImage;
      coverPreview.hidden = !post.image;
      document.getElementById("post-status").value = post.status;
      document.getElementById("editor-heading").textContent = "Edit story";
      document.getElementById("submit-label").textContent = "Save changes";
      document.getElementById("form-feedback").textContent = "";
      document.getElementById("editor").scrollIntoView({ behavior: "smooth" });
    });
}

document.addEventListener("DOMContentLoaded", () => {
  fetchPosts()
    .then(() => {
      renderPublicPosts();
      const storyToRead = new URLSearchParams(window.location.search).get(
        "read",
      );
      if (storyToRead) showStory(storyToRead);
    })
    .catch(() => {
      const emptyState = document.getElementById("empty-state");
      if (emptyState) {
        emptyState.textContent =
          "Stories could not be loaded. Please refresh and try again.";
        emptyState.hidden = false;
      }
    });
  initAdmin();
  const query = new URLSearchParams(window.location.search);
  const storyToRead = query.get("read");
  const authMessage = query.has("signed-in")
    ? "You are signed in. Welcome back to Fieldnotes."
    : query.has("welcome")
      ? "Your account is ready. Welcome to Fieldnotes."
      : "";
  if (authMessage) {
    const toast = document.createElement("div");
    toast.className = "auth-toast success";
    toast.setAttribute("role", "status");
    toast.setAttribute("aria-live", "polite");
    toast.innerHTML =
      '<span class="auth-toast-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span><span class="auth-toast-message"></span><button class="auth-toast-close" type="button" aria-label="Dismiss notification"><i class="fa-solid fa-xmark"></i></button>';
    toast.querySelector(".auth-toast-message").textContent = authMessage;
    document.body.append(toast);
    toast
      .querySelector(".auth-toast-close")
      .addEventListener("click", () => toast.remove());
    window.setTimeout(() => toast.remove(), 6000);
    query.delete("signed-in");
    query.delete("welcome");
    const cleanUrl = `${window.location.pathname}${query.size ? `?${query}` : ""}${window.location.hash}`;
    window.history.replaceState({}, "", cleanUrl);
  }
  document
    .getElementById("post-search")
    ?.addEventListener("input", renderPublicPosts);
  document
    .getElementById("category-filters")
    ?.addEventListener("click", (event) => {
      const chip = event.target.closest(".filter-chip");
      if (!chip) return;
      document
        .querySelectorAll(".filter-chip")
        .forEach((button) =>
          button.classList.toggle("active", button === chip),
        );
      renderPublicPosts();
    });
  document.getElementById("post-grid")?.addEventListener("click", (event) => {
    const storyLink = event.target.closest(".story-open");
    if (!storyLink) return;
    event.preventDefault();
    showStory(storyLink.dataset.postId);
  });
  document
    .getElementById("newsletter-form")
    ?.addEventListener("submit", (event) => {
      event.preventDefault();
      document.getElementById("newsletter-feedback").textContent =
        "Thanks for reading along.";
      event.currentTarget.reset();
    });
  const menuToggle = document.querySelector(".menu-toggle");
  menuToggle?.addEventListener("click", () => {
    const nav = document.querySelector(".main-nav");
    const isOpen = nav.classList.toggle("open");
    menuToggle.setAttribute("aria-expanded", String(isOpen));
  });
});
