(function () {
    'use strict';

    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    async function api(endpoint, options = {}) {
        const url = DANMOO.rest_url + endpoint;
        const headers = { 'X-WP-Nonce': DANMOO.nonce };

        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);

        try {
            const res = await fetch(url, {
                ...options,
                headers: { ...headers, ...(options.headers || {}) },
                signal: controller.signal,
            });
            clearTimeout(timeout);
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || '오류가 발생했습니다.');
            return data;
        } catch (err) {
            clearTimeout(timeout);
            if (err.name === 'AbortError') throw new Error('요청 시간이 초과되었습니다.');
            throw err;
        }
    }

    async function getRecaptchaToken(action) {
        if (!DANMOO.recaptcha_key || typeof grecaptcha === 'undefined') return '';
        try {
            return await grecaptcha.execute(DANMOO.recaptcha_key, { action });
        } catch {
            return '';
        }
    }

    function timeAgo(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return '방금 전';
        if (diff < 3600) return Math.floor(diff / 60) + '분 전';
        if (diff < 86400) return Math.floor(diff / 3600) + '시간 전';
        if (diff < 2592000) return Math.floor(diff / 86400) + '일 전';
        return date.toLocaleDateString('ko-KR');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ─── Feed ───

    class DanmooFeed {
        constructor(container) {
            this.el = container;
            this.grid = $('.danmoo-feed-grid', this.el);
            this.footer = $('.danmoo-feed-footer', this.el);
            this.empty = $('.danmoo-feed-empty', this.el);
            this.loading = $('.danmoo-feed-loading', this.el);
            this.perPage = parseInt(this.el.dataset.perPage) || 12;
            this.page = 1;
            this.sort = 'latest';
            this.category = '';
            this.totalPages = 1;

            this.initSortTabs();
            this.loadCategories();
            this.loadPosts();
        }

        initSortTabs() {
            $$('.danmoo-sort-tab', this.el).forEach(tab => {
                tab.addEventListener('click', () => {
                    $$('.danmoo-sort-tab', this.el).forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    this.sort = tab.dataset.sort;
                    this.page = 1;
                    this.loadPosts(true);
                });
            });

            const loadMore = $('.danmoo-load-more', this.el);
            if (loadMore) {
                loadMore.addEventListener('click', () => this.loadMore());
            }
        }

        async loadCategories() {
            try {
                const data = await api('categories');
                if (data.categories.length === 0) return;

                const filter = $('.danmoo-category-filter', this.el);
                filter.style.display = '';

                data.categories.forEach(cat => {
                    const chip = document.createElement('button');
                    chip.className = 'danmoo-category-chip';
                    chip.dataset.category = cat.slug;
                    chip.textContent = cat.name;
                    chip.addEventListener('click', () => {
                        $$('.danmoo-category-chip', this.el).forEach(c => c.classList.remove('active'));
                        chip.classList.add('active');
                        this.category = cat.slug;
                        this.page = 1;
                        this.loadPosts(true);
                    });
                    filter.appendChild(chip);
                });

                const allChip = $('.danmoo-category-chip[data-category=""]', this.el);
                if (allChip) {
                    allChip.addEventListener('click', () => {
                        $$('.danmoo-category-chip', this.el).forEach(c => c.classList.remove('active'));
                        allChip.classList.add('active');
                        this.category = '';
                        this.page = 1;
                        this.loadPosts(true);
                    });
                }
            } catch (err) {
                console.error('Failed to load categories:', err);
            }
        }

        async loadPosts(reset = false) {
            if (reset) {
                this.grid.innerHTML = '';
                this.loading.style.display = 'flex';
                this.grid.appendChild(this.loading);
            }

            try {
                const params = new URLSearchParams({
                    page: this.page,
                    per_page: this.perPage,
                    sort: this.sort,
                });
                if (this.category) params.set('category', this.category);

                const data = await api('posts?' + params.toString());
                this.totalPages = data.total_pages;

                if (reset) this.grid.innerHTML = '';

                if (data.posts.length === 0 && this.page === 1) {
                    this.empty.style.display = 'block';
                    this.footer.style.display = 'none';
                    return;
                }

                this.empty.style.display = 'none';

                data.posts.forEach(post => {
                    this.grid.appendChild(this.renderCard(post));
                });

                this.footer.style.display = this.page < this.totalPages ? 'block' : 'none';
            } catch (err) {
                this.grid.innerHTML = '<p style="text-align:center;color:var(--juso-fg-muted);">로딩에 실패했습니다.</p>';
            }
        }

        loadMore() {
            this.page++;
            this.loadPosts(false);
        }

        renderCard(post) {
            const a = document.createElement('a');
            a.href = post.url;
            a.className = 'danmoo-card';

            a.innerHTML = `
                <div class="danmoo-card-header">
                    ${post.category ? `<span class="danmoo-badge">${escapeHtml(post.category)}</span>` : ''}
                    <h3 class="danmoo-card-title">${escapeHtml(post.title)}</h3>
                </div>
                ${post.excerpt ? `<p class="danmoo-card-excerpt">${escapeHtml(post.excerpt)}</p>` : ''}
                <div class="danmoo-card-stats">
                    <span class="danmoo-card-stat">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        ${post.like_count}
                    </span>
                    <span class="danmoo-card-stat">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        ${post.view_count}
                    </span>
                    <span class="danmoo-card-stat">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        ${post.comment_count}
                    </span>
                    <span class="danmoo-card-date">${timeAgo(post.date)}</span>
                </div>
            `;
            return a;
        }
    }

    // ─── Submit Form ───

    class DanmooSubmit {
        constructor(container) {
            this.el = container;
            this.form = container;
            this.success = $('.danmoo-submit-success', this.el.parentElement);
            this.loadCategories();
            this.initEvents();
        }

        async loadCategories() {
            try {
                const data = await api('categories');
                if (data.categories.length === 0) return;

                const group = $('.danmoo-category-group', this.el.parentElement);
                if (group) group.style.display = '';

                const select = $('select[name="category"]', this.el);
                data.categories.forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat.slug;
                    opt.textContent = cat.name;
                    select.appendChild(opt);
                });
            } catch (err) {
                console.error('Failed to load categories:', err);
            }
        }

        initEvents() {
            // Character count
            const textarea = $('textarea[name="content"]', this.el);
            const charCount = $('.danmoo-char-count', this.el);
            if (textarea && charCount) {
                textarea.addEventListener('input', () => {
                    charCount.textContent = textarea.value.length + '/' + textarea.maxLength;
                });
            }

            // Rules toggle
            const toggle = $('.danmoo-rules-toggle', this.el);
            const preview = $('.danmoo-rules-preview', this.el);
            if (toggle && preview) {
                toggle.addEventListener('click', () => {
                    const visible = preview.style.display !== 'none';
                    preview.style.display = visible ? 'none' : 'block';
                    toggle.textContent = visible ? '규칙 보기' : '규칙 닫기';
                });
            }

            // Submit
            this.el.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleSubmit();
            });

            // Copy URL
            const copyBtn = $('.danmoo-copy-btn', this.el.parentElement);
            if (copyBtn) {
                copyBtn.addEventListener('click', () => {
                    const input = $('.danmoo-url-input', this.el.parentElement);
                    navigator.clipboard.writeText(input.value).then(() => {
                        copyBtn.textContent = '복사됨!';
                        setTimeout(() => { copyBtn.textContent = '복사'; }, 2000);
                    });
                });
            }
        }

        async handleSubmit() {
            const title = $('input[name="title"]', this.el).value.trim();
            const content = $('textarea[name="content"]', this.el).value.trim();
            const category = $('select[name="category"]', this.el).value;
            const agreed = $('input[name="agree_rules"]', this.el).checked;

            if (!title || !content) {
                alert('제목과 내용을 입력해주세요.');
                return;
            }
            if (!agreed) {
                alert('커뮤니티 이용 규칙에 동의해주세요.');
                return;
            }

            const submitBtn = $('button[type="submit"]', this.el);
            submitBtn.disabled = true;
            submitBtn.textContent = '등록 중...';

            try {
                const token = await getRecaptchaToken('submit_post');
                const data = await api('posts', {
                    method: 'POST',
                    body: { title, content, category, recaptcha_token: token },
                });

                // Show success
                this.el.style.display = 'none';
                this.success.style.display = 'block';
                const urlInput = $('.danmoo-url-input', this.success);
                urlInput.value = data.url;
                const viewBtn = $('.danmoo-view-post-btn', this.success);
                viewBtn.href = data.url;
            } catch (err) {
                alert(err.message);
                submitBtn.disabled = false;
                submitBtn.textContent = '등록하기';
            }
        }
    }

    // ─── Detail ───

    class DanmooDetail {
        constructor(container) {
            this.el = container;
            this.hash = container.dataset.hash;
            this.recordView();
            this.initLike();
            this.initReport();
            this.loadComments();
            this.initCommentForm();
            if (DANMOO.is_admin) this.initAdminControls();
        }

        async recordView() {
            try {
                const data = await api(`posts/${this.hash}/view`, { method: 'POST' });
                const el = $('.danmoo-view-count', this.el);
                if (el) el.textContent = data.view_count;
            } catch {}
        }

        initLike() {
            const btn = $('.danmoo-like-btn', this.el);
            if (!btn) return;

            // Check initial state
            api(`posts/${this.hash}`).then(data => {
                if (data.liked) btn.classList.add('liked');
                $('.danmoo-like-count', btn).textContent = data.like_count;
            }).catch(() => {});

            btn.addEventListener('click', async () => {
                try {
                    const data = await api(`posts/${this.hash}/like`, { method: 'POST' });
                    btn.classList.toggle('liked', data.liked);
                    $('.danmoo-like-count', btn).textContent = data.like_count;
                } catch (err) {
                    alert(err.message);
                }
            });
        }

        initReport() {
            const reportBtn = $('.danmoo-report-btn', this.el);
            if (!reportBtn) return;

            reportBtn.addEventListener('click', () => {
                this.showReportModal('post', this.hash);
            });
        }

        showReportModal(type, id) {
            const modal = $('.danmoo-report-modal', this.el);
            const reasonsEl = $('.danmoo-report-reasons', modal);
            reasonsEl.innerHTML = '';

            DANMOO.i18n.report_reasons.forEach(reason => {
                const label = document.createElement('label');
                label.className = 'danmoo-report-reason';
                label.innerHTML = `<input type="radio" name="report_reason" value="${escapeHtml(reason)}"> <span>${escapeHtml(reason)}</span>`;
                label.addEventListener('click', () => {
                    $$('.danmoo-report-reason', modal).forEach(r => r.classList.remove('selected'));
                    label.classList.add('selected');
                });
                reasonsEl.appendChild(label);
            });

            modal.style.display = 'flex';

            const cancel = $('.danmoo-modal-cancel', modal);
            const submit = $('.danmoo-modal-submit', modal);
            const backdrop = $('.danmoo-modal-backdrop', modal);

            const close = () => { modal.style.display = 'none'; };
            cancel.onclick = close;
            backdrop.onclick = close;

            submit.onclick = async () => {
                const checked = $('input[name="report_reason"]:checked', modal);
                if (!checked) {
                    alert('신고 사유를 선택해주세요.');
                    return;
                }

                try {
                    let endpoint;
                    if (type === 'post') {
                        endpoint = `posts/${id}/report`;
                    } else {
                        endpoint = `comments/${id}/report`;
                    }
                    await api(endpoint, {
                        method: 'POST',
                        body: { reason: checked.value },
                    });
                    alert('신고가 접수되었습니다.');
                    close();
                } catch (err) {
                    alert(err.message);
                }
            };
        }

        async loadComments() {
            const list = $('.danmoo-comments-list', this.el);
            try {
                const data = await api(`posts/${this.hash}/comments`);
                list.innerHTML = '';

                if (data.comments.length === 0) {
                    list.innerHTML = '<p class="danmoo-text-muted" style="text-align:center;padding:1rem;">아직 댓글이 없습니다.</p>';
                    return;
                }

                data.comments.forEach(c => {
                    list.appendChild(this.renderComment(c));
                });
            } catch {
                list.innerHTML = '<p class="danmoo-text-muted" style="text-align:center;">댓글을 불러올 수 없습니다.</p>';
            }
        }

        renderComment(c) {
            const div = document.createElement('div');
            div.className = 'danmoo-comment';
            div.dataset.id = c.id;

            const adminBtns = DANMOO.is_admin ? `
                <button class="danmoo-btn danmoo-admin-del-comment" data-id="${c.id}">삭제</button>
            ` : '';

            div.innerHTML = `
                <div class="danmoo-comment-header">
                    <span class="danmoo-anon-label ${c.is_author ? 'is-author' : ''}">${escapeHtml(c.anon_label)}</span>
                    <span class="danmoo-comment-date">${timeAgo(c.date)}</span>
                </div>
                <div class="danmoo-comment-body">${escapeHtml(c.content)}</div>
                <div class="danmoo-comment-actions">
                    <button class="danmoo-btn danmoo-comment-like ${c.liked ? 'liked' : ''}" data-id="${c.id}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="${c.liked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        <span>${c.like_count}</span>
                    </button>
                    <button class="danmoo-btn danmoo-comment-report" data-id="${c.id}">신고</button>
                    ${adminBtns}
                </div>
            `;

            // Comment like
            const likeBtn = $('.danmoo-comment-like', div);
            likeBtn.addEventListener('click', async () => {
                try {
                    const data = await api(`comments/${c.id}/like`, { method: 'POST' });
                    likeBtn.classList.toggle('liked', data.liked);
                    $('svg', likeBtn).setAttribute('fill', data.liked ? 'currentColor' : 'none');
                    $('span', likeBtn).textContent = data.like_count;
                } catch (err) {
                    alert(err.message);
                }
            });

            // Comment report
            const reportBtn = $('.danmoo-comment-report', div);
            reportBtn.addEventListener('click', () => {
                this.showReportModal('comment', c.id);
            });

            // Admin delete comment
            if (DANMOO.is_admin) {
                const delBtn = $('.danmoo-admin-del-comment', div);
                if (delBtn) {
                    delBtn.addEventListener('click', async () => {
                        if (!confirm('이 댓글을 삭제하시겠습니까?')) return;
                        try {
                            await api(`admin/comments/${c.id}/delete`, { method: 'POST' });
                            div.remove();
                            const cList = $('.danmoo-comments-list', this.el);
                            if (cList) cList.dataset.count = Math.max(0, parseInt(cList.dataset.count || 0) - 1);
                        } catch (err) {
                            alert(err.message);
                        }
                    });
                }
            }

            return div;
        }

        initCommentForm() {
            const form = $('.danmoo-comment-form', this.el);
            const textarea = $('.danmoo-comment-input', form);
            const charCount = $('.danmoo-comment-char-count', form);

            textarea.addEventListener('input', () => {
                charCount.textContent = textarea.value.length + '/' + textarea.maxLength;
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const content = textarea.value.trim();
                if (!content) return;

                const btn = $('button[type="submit"]', form);
                btn.disabled = true;

                try {
                    const token = await getRecaptchaToken('submit_comment');
                    const data = await api(`posts/${this.hash}/comments`, {
                        method: 'POST',
                        body: { content, recaptcha_token: token },
                    });

                    // Remove empty message if exists
                    const emptyMsg = $('.danmoo-comments-list .danmoo-text-muted', this.el);
                    if (emptyMsg) emptyMsg.remove();

                    const commentList = $('.danmoo-comments-list', this.el);
                    commentList.appendChild(this.renderComment(data.comment));
                    commentList.dataset.count = parseInt(commentList.dataset.count || 0) + 1;

                    textarea.value = '';
                    charCount.textContent = '0/' + textarea.maxLength;
                } catch (err) {
                    alert(err.message);
                }

                btn.disabled = false;
            });
        }

        initAdminControls() {
            const hideBtn = $('.danmoo-admin-hide-btn', this.el);
            const deleteBtn = $('.danmoo-admin-delete-btn', this.el);
            const banBtn = $('.danmoo-admin-ban-btn', this.el);

            if (hideBtn) {
                hideBtn.addEventListener('click', async () => {
                    if (!confirm('이 글을 숨기시겠습니까?')) return;
                    try {
                        await api(`admin/posts/${this.hash}/hide`, { method: 'POST' });
                        alert('글이 숨겨졌습니다.');
                        location.reload();
                    } catch (err) {
                        alert(err.message);
                    }
                });
            }

            if (deleteBtn) {
                deleteBtn.addEventListener('click', async () => {
                    if (!confirm('이 글을 삭제하시겠습니까?')) return;
                    try {
                        await api(`admin/posts/${this.hash}/delete`, { method: 'POST' });
                        alert('글이 삭제되었습니다.');
                        window.history.back();
                    } catch (err) {
                        alert(err.message);
                    }
                });
            }

            if (banBtn) {
                banBtn.addEventListener('click', async () => {
                    const ip = banBtn.dataset.ip;
                    if (!confirm(`IP ${ip}를 차단하시겠습니까?`)) return;
                    try {
                        await api('admin/ip-ban', {
                            method: 'POST',
                            body: { ip, reason: '관리자에 의한 차단' },
                        });
                        alert('IP가 차단되었습니다.');
                    } catch (err) {
                        alert(err.message);
                    }
                });
            }
        }
    }

    // ─── Init ───

    document.addEventListener('DOMContentLoaded', () => {
        const feed = $('.danmoo-feed');
        if (feed) new DanmooFeed(feed);

        const submitForm = $('.danmoo-submit-form');
        if (submitForm) new DanmooSubmit(submitForm);

        const detail = $('.danmoo-detail');
        if (detail) new DanmooDetail(detail);
    });
})();
