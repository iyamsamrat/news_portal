<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin', 'editor']);

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

// Default form values (very flexible; we can add fields later)
$form = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'summary' => '',
    'content' => '',
    'category_id' => 0,
    'status' => 'draft',
    'is_featured' => 0,
    'allow_comments' => 1,
    'source_name' => '',
    'source_url' => '',
    'media_type' => 'none',
    'media_url' => '',
    'meta_title' => '',
    'meta_description' => '',
    'tags' => '',
    'cover_image' => '', // existing filename if any
];

$categories = [];
try {
    $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC")->fetchAll() ?: [];
} catch (Throwable $e) {
    $categories = [];
}

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id=:id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        $pageTitle = "Article Not Found - Admin";
        require_once __DIR__ . '/_partials/admin_header.php';
        require_once __DIR__ . '/_partials/admin_nav.php';
        ?>
        <main id="main" class="container-fluid p-3 p-md-4">
            <div class="np-card p-4 bg-white" style="max-width:900px;">
                <h1 class="h5 mb-2">Article not found</h1>
                <div class="text-muted mb-3">This article may have been deleted.</div>
                <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/articles.php">Back to Articles</a>
            </div>
        </main>
        <?php
        require_once __DIR__ . '/_partials/admin_footer.php';
        exit;
    }

    // Map DB -> form (keeps flexibility)
    $form['id'] = (int)$row['id'];
    $form['title'] = (string)($row['title'] ?? '');
    $form['slug'] = (string)($row['slug'] ?? '');
    $form['summary'] = (string)($row['summary'] ?? '');
    $form['content'] = (string)($row['content'] ?? '');
    $form['category_id'] = (int)($row['category_id'] ?? 0);
    $form['status'] = (string)($row['status'] ?? 'draft');
    $form['is_featured'] = (int)($row['is_featured'] ?? 0);
    $form['allow_comments'] = (int)($row['allow_comments'] ?? 1);
    $form['source_name'] = (string)($row['source_name'] ?? '');
    $form['source_url'] = (string)($row['source_url'] ?? '');
    $form['media_type'] = (string)($row['media_type'] ?? 'none');
    $form['media_url'] = (string)($row['media_url'] ?? '');
    $form['meta_title'] = (string)($row['meta_title'] ?? '');
    $form['meta_description'] = (string)($row['meta_description'] ?? '');
    $form['cover_image'] = (string)($row['cover_image'] ?? '');

    // If you already have tags tables, we can fetch them; otherwise leave empty safely
    try {
        $t = $pdo->prepare("
            SELECT GROUP_CONCAT(tags.name ORDER BY tags.name SEPARATOR ', ')
            FROM article_tags
            INNER JOIN tags ON tags.id = article_tags.tag_id
            WHERE article_tags.article_id = :aid
        ");
        $t->execute(['aid' => $id]);
        $tagsCsv = (string)($t->fetchColumn() ?? '');
        $form['tags'] = $tagsCsv;
    } catch (Throwable $e) {
        $form['tags'] = '';
    }
}

// lightweight slug suggestion for UI (real slug handling in save step)
function suggest_slug(string $title): string
{
    $title = mb_strtolower(trim($title));
    $slug = preg_replace('~[^\pL\pN]+~u', '-', $title) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'article';
}

$pageTitle = ($isEdit ? "Edit Article" : "New Article") . " - Admin";
$extraHead  = '<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">';
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';

$coverPreview = '';
if (!empty($form['cover_image']) && defined('UPLOAD_URL')) {
    $coverPreview = rtrim(UPLOAD_URL, '/') . '/' . ltrim($form['cover_image'], '/');
}
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible d-flex align-items-center gap-2 mb-3" style="max-width:1100px;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?= h($_SESSION['flash_error']) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2 mb-3" style="max-width:1100px;">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= h($_SESSION['flash_success']) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><?= $isEdit ? 'Edit Article' : 'New Article' ?></h1>
            <div class="small text-muted">Keep it minimal, but fully changeable from CMS.</div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/articles.php">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <?php if ($isEdit): ?>
                <a class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener"
                   href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode($form['slug']) ?>">
                    <i class="bi bi-box-arrow-up-right"></i> View
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="np-card p-3 p-md-4 bg-white" style="max-width: 1100px;"
          method="post" action="<?= h($ADMIN_URL) ?>/article_save.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="row g-3">

            <!-- LEFT: main content -->
            <div class="col-12 col-lg-8">

                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="title" value="<?= h($form['title']) ?>"
                           placeholder="Write a clear headline..." required>
                    <div class="form-text">Tip: Keep titles short and specific.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input class="form-control" type="text" name="slug" value="<?= h($form['slug']) ?>"
                           placeholder="<?= h(suggest_slug($form['title'])) ?>">
                    <div class="form-text">Leave blank to auto-generate from title (recommended).</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Summary</label>
                    <textarea class="form-control" name="summary" rows="3"
                              placeholder="Short summary shown on cards..."><?= h($form['summary']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Content <span class="text-danger">*</span></label>
                    <div id="np-editor" style="min-height:320px;background:#fff;border-radius:0 0 6px 6px;font-size:.95rem;"></div>
                    <textarea id="np-content" name="content" style="display:none;"><?= h($form['content']) ?></textarea>
                    <div id="np-content-error" class="invalid-feedback" style="display:none;">Content is required.</div>
                    <div class="form-text">Use the toolbar to format text. Click the image icon to upload images.</div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Source Name</label>
                        <input class="form-control" type="text" name="source_name" value="<?= h($form['source_name']) ?>"
                               placeholder="e.g., BBC, Kantipur, Reuters">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Source URL</label>
                        <input class="form-control" type="url" name="source_url" value="<?= h($form['source_url']) ?>"
                               placeholder="https://example.com/...">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Media Type</label>
                        <select class="form-select" name="media_type">
                            <option value="none" <?= $form['media_type']==='none'?'selected':'' ?>>None</option>
                            <option value="image" <?= $form['media_type']==='image'?'selected':'' ?>>Image</option>
                            <option value="video" <?= $form['media_type']==='video'?'selected':'' ?>>Video</option>
                            <option value="audio" <?= $form['media_type']==='audio'?'selected':'' ?>>Audio</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label">Media URL</label>
                        <input class="form-control" type="url" name="media_url" value="<?= h($form['media_url']) ?>"
                               placeholder="https://youtube.com/... or direct media link">
                    </div>
                </div>

                <hr class="my-4">

                <div class="mb-3">
                    <label class="form-label">SEO Title</label>
                    <input class="form-control" type="text" name="meta_title" value="<?= h($form['meta_title']) ?>"
                           placeholder="Optional SEO title">
                </div>

                <div class="mb-0">
                    <label class="form-label">SEO Description</label>
                    <textarea class="form-control" name="meta_description" rows="3"
                              placeholder="Optional meta description"><?= h($form['meta_description']) ?></textarea>
                </div>

            </div>

            <!-- RIGHT: controls -->
            <div class="col-12 col-lg-4">

                <div class="np-card p-3 border bg-light-subtle">
                    <div class="fw-semibold mb-2">Publishing</div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="draft" <?= $form['status']==='draft'?'selected':'' ?>>Draft</option>
                            <option value="published" <?= $form['status']==='published'?'selected':'' ?>>Published</option>
                            <option value="archived" <?= $form['status']==='archived'?'selected':'' ?>>Archived</option>
                        </select>
                        <div class="form-text">Published will show on the site.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category_id">
                            <option value="0">— Select —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ($form['category_id']===(int)$c['id'])?'selected':'' ?>>
                                    <?= h($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <input class="form-control" type="text" name="tags" value="<?= h($form['tags']) ?>"
                               placeholder="e.g., Nepal, Sports, Politics">
                        <div class="form-text">Comma-separated. We will normalize into tags table in Step 3.</div>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured"
                            <?= ((int)$form['is_featured'] === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_featured">Featured</label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="allow_comments" name="allow_comments"
                            <?= ((int)$form['allow_comments'] === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="allow_comments">Allow comments</label>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-dark" type="submit">
                            <i class="bi bi-check2"></i> <?= $isEdit ? 'Update' : 'Create' ?>
                        </button>
                        <a class="btn btn-outline-dark" href="<?= h($ADMIN_URL) ?>/articles.php">Cancel</a>
                    </div>
                </div>

                <div class="np-card p-3 mt-3 border bg-white">
                    <div class="fw-semibold mb-2">Cover Image</div>

                    <?php if ($coverPreview): ?>
                        <div id="cover-preview-wrap" class="mb-2">
                            <img id="cover-preview-img" src="<?= h($coverPreview) ?>"
                                 class="img-fluid rounded-3 border w-100"
                                 style="object-fit:cover;max-height:180px;" alt="Cover">
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="small text-muted text-truncate"><?= h(basename($form['cover_image'])) ?></span>
                            <button type="button" id="cover-remove-btn"
                                    class="btn btn-sm btn-outline-danger ms-auto py-0">
                                <i class="bi bi-x"></i> Remove
                            </button>
                        </div>
                        <input type="hidden" name="remove_cover" id="remove_cover" value="0">
                    <?php else: ?>
                        <div id="cover-preview-wrap" style="display:none;" class="mb-2">
                            <img id="cover-preview-img" src="" class="img-fluid rounded-3 border w-100"
                                 style="object-fit:cover;max-height:180px;" alt="Cover preview">
                        </div>
                        <input type="hidden" name="remove_cover" id="remove_cover" value="0">
                    <?php endif; ?>

                    <input type="file" name="cover_image" id="cover_image_input"
                           class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/gif">
                    <div class="form-text">JPG, PNG, WebP or GIF. Leave blank to keep current.</div>
                </div>

            </div>
        </div>
    </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
// ---- Rich text editor ----
(function () {
    var contentTextarea = document.getElementById('np-content');
    var editorDiv       = document.getElementById('np-editor');
    var errEl           = document.getElementById('np-content-error');
    var form            = document.querySelector('form');

    // Fallback: if Quill didn't load, reveal the plain textarea and submit normally
    if (typeof Quill === 'undefined') {
        contentTextarea.style.display = '';
        contentTextarea.rows = 14;
        if (editorDiv) editorDiv.style.display = 'none';
        return;
    }

    var uploadUrl = <?= json_encode(rtrim(BASE_URL, '/') . '/admin/editor_upload.php') ?>;
    var csrfToken = document.querySelector('[name=csrf_token]').value;
    var quill;

    try {
        quill = new Quill(editorDiv, {
            theme: 'snow',
            placeholder: 'Write the full article content…',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [2, 3, 4, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ indent: '-1' }, { indent: '+1' }],
                        ['blockquote', 'code-block'],
                        ['link', 'image'],
                        [{ align: [] }],
                        ['clean']
                    ],
                    handlers: {
                        image: function () {
                            var picker = document.createElement('input');
                            picker.type = 'file';
                            picker.accept = 'image/*';
                            picker.onchange = function () {
                                var file = picker.files[0];
                                if (!file) return;
                                var fd = new FormData();
                                fd.append('image', file);
                                fd.append('csrf_token', csrfToken);
                                fetch(uploadUrl, { method: 'POST', body: fd })
                                    .then(function (r) { return r.json(); })
                                    .then(function (data) {
                                        if (data.url) {
                                            var range = quill.getSelection(true);
                                            quill.insertEmbed(range.index, 'image', data.url, Quill.sources.USER);
                                            quill.setSelection(range.index + 1, Quill.sources.SILENT);
                                        } else {
                                            alert('Upload failed: ' + (data.error || 'unknown'));
                                        }
                                    })
                                    .catch(function () { alert('Image upload failed.'); });
                            };
                            picker.click();
                        }
                    }
                }
            }
        });
    } catch (e) {
        // Quill init failed — fall back to plain textarea
        contentTextarea.style.display = '';
        contentTextarea.rows = 14;
        if (editorDiv) editorDiv.style.display = 'none';
        return;
    }

    // Load existing content
    var existing = contentTextarea.value.trim();
    if (existing !== '') {
        quill.root.innerHTML = /<[a-z][\s\S]*>/i.test(existing)
            ? existing
            : existing.split(/\n\n+/).map(function (p) {
                return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
              }).join('');
    }

    // Clear error state on type
    quill.on('text-change', function () {
        errEl.style.display = 'none';
        quill.root.style.outline = '';
    });

    // Validate + sync on submit
    form.addEventListener('submit', function (e) {
        var html = quill.root.innerHTML.trim();
        if (html === '' || html === '<p><br></p>') {
            e.preventDefault();
            errEl.style.display = 'block';
            quill.root.style.outline = '1px solid #dc3545';
            quill.focus();
            return;
        }
        contentTextarea.value = html;
    });
})();

// ---- Cover image UI ----
(function () {
    var fileInput   = document.getElementById('cover_image_input');
    var previewWrap = document.getElementById('cover-preview-wrap');
    var previewImg  = document.getElementById('cover-preview-img');
    var filenameEl  = document.getElementById('cover-filename');
    var zone        = document.getElementById('cover-upload-zone');
    var removeBtn   = document.getElementById('cover-remove-btn');
    var removeInput = document.getElementById('remove_cover');

    if (!fileInput || !zone) return;

    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.style.borderColor = '#555';
        zone.style.background  = 'rgba(0,0,0,.03)';
    });
    zone.addEventListener('dragleave', function () {
        zone.style.borderColor = '';
        zone.style.background  = '';
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.style.borderColor = '';
        zone.style.background  = '';
        var files = e.dataTransfer.files;
        if (!files.length) return;
        // Assign dropped file to input via DataTransfer
        try {
            var dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
        } catch (_) {}
        showPreview(files[0]);
    });

    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) {
            showPreview(fileInput.files[0]);
        }
    });

    function showPreview(file) {
        if (!file || !file.type.startsWith('image/')) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
            previewImg.src = ev.target.result;
            previewWrap.style.display = '';
            if (filenameEl) filenameEl.textContent = file.name;
            if (removeInput) removeInput.value = '0';
        };
        reader.readAsDataURL(file);
    }

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            previewWrap.style.display = 'none';
            previewImg.src = '';
            fileInput.value = '';
            if (filenameEl) filenameEl.textContent = 'No image';
            if (removeInput) removeInput.value = '1';
        });
    }
})();
</script>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>
