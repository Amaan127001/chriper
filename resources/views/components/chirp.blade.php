@props(['chirp'])

@php
    // initial reaction state for current user (avoid extra DB calls if not authenticated)
    $userReaction = null;
    if (auth()->check()) {
        $userReaction = $chirp->reactions()->where('user_id', auth()->id())->value('reaction_type');
    }

    // counts (prefer eager-loaded counts if available)
    $likesCount = $chirp->likes_count ?? $chirp->reactions()->where('reaction_type','like')->count();
    $dislikesCount = $chirp->dislikes_count ?? $chirp->reactions()->where('reaction_type','dislike')->count();
    $commentsCount = $chirp->comments_count ?? $chirp->comments()->count();

    // fetch top-level comments (limit to 3 for preview), eager load user and replies' user
    $commentsPreview = $chirp->comments()->whereNull('parent_id')->with(['user','replies.user'])->latest()->take(3)->get();
@endphp

<div class="card bg-base-100" id="chirp-{{ $chirp->id }}">
    <div class="card-body">
        <div class="flex space-x-3">
            <div class="avatar">
                <div class="size-10 rounded-full">
                    <img src="https://avatars.laravel.cloud/{{ urlencode($chirp->user->email) }}?vibe=ocean"
                         alt="{{ $chirp->user->name }}'s avatar" class="rounded-full" />
                </div>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-1">
                        <p class="text-sm font-semibold">{{ $chirp->user->name }}</p>
                        <span class="text-base-content/60">·</span>
                        <p class="text-sm text-base-content/60">{{ $chirp->created_at->diffForHumans() }}</p>
                        @if ($chirp->updated_at->gt($chirp->created_at->addSeconds(5)))
                            <span class="text-base-content/60">·</span>
                            <span class="text-sm text-base-content/60 italic">edited</span>
                        @endif
                    </div>

                    @if (auth()->check() && auth()->id() === $chirp->user_id)
                        <div class="flex gap-1">
                            <a href="/chirps/{{ $chirp->id }}/edit" class="btn btn-ghost btn-xs">Edit</a>
                            <form method="POST" action="/chirps/{{ $chirp->id }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        onclick="return confirm('Are you sure you want to delete this chirp?')"
                                        class="btn btn-ghost btn-xs text-error">
                                    Delete
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                <p class="mt-1">{{ $chirp->message }}</p>

                <!-- Reaction buttons + comment toggle -->
                <div class="flex items-center space-x-3 mt-2 text-sm">
                    @if(auth()->check())
                        <button
                            class="chirp-react-btn btn btn-ghost btn-xs inline-flex items-center gap-2"
                            data-chirp-id="{{ $chirp->id }}"
                            data-action="like"
                            data-active="{{ $userReaction === 'like' ? '1' : '0' }}"
                            aria-pressed="{{ $userReaction === 'like' ? 'true' : 'false' }}">
                            <span class="react-icon">👍</span>
                            <span id="like-count-{{ $chirp->id }}">{{ $likesCount }}</span>
                        </button>

                        <button
                            class="chirp-react-btn btn btn-ghost btn-xs inline-flex items-center gap-2"
                            data-chirp-id="{{ $chirp->id }}"
                            data-action="dislike"
                            data-active="{{ $userReaction === 'dislike' ? '1' : '0' }}"
                            aria-pressed="{{ $userReaction === 'dislike' ? 'true' : 'false' }}">
                            <span class="react-icon">👎</span>
                            <span id="dislike-count-{{ $chirp->id }}">{{ $dislikesCount }}</span>
                        </button>
                    @else
                        <a href="/login" class="btn btn-ghost btn-xs inline-flex items-center gap-2">
                            👍 <span id="like-count-{{ $chirp->id }}">{{ $likesCount }}</span>
                        </a>
                        <a href="/login" class="btn btn-ghost btn-xs inline-flex items-center gap-2">
                            👎 <span id="dislike-count-{{ $chirp->id }}">{{ $dislikesCount }}</span>
                        </a>
                    @endif

                    <!-- Comments toggle -->
                    <button class="toggle-comments btn btn-ghost btn-xs inline-flex items-center gap-2" data-chirp-id="{{ $chirp->id }}" aria-expanded="false">
                        💬 <span id="comments-count-{{ $chirp->id }}">{{ $commentsCount }}</span>
                    </button>
                </div>

                <!-- Comments dropdown (hidden by default) -->
                <div id="comments-section-{{ $chirp->id }}" class="mt-3 hidden">
                    <!-- new comment input -->
                    @auth
                        <form class="comment-form flex gap-2 mb-3" data-chirp-id="{{ $chirp->id }}" onsubmit="event.preventDefault(); ChirpUI.addComment({{ $chirp->id }});">
                            @csrf
                            <input type="text" id="comment-input-{{ $chirp->id }}" class="input input-bordered input-sm flex-1" placeholder="Chirp your reply..." maxlength="1000" required>
                            <button type="submit" class="btn btn-primary btn-sm">Reply</button>
                        </form>
                    @else
                        <div class="text-sm text-base-content/60 mb-3">
                            <a href="/login" class="link">Log in</a> to reply.
                        </div>
                    @endauth

                    <!-- comments list -->
                    <div id="comments-list-{{ $chirp->id }}" class="space-y-3">
                        @foreach($commentsPreview as $comment)
                            <div class="comment-item border rounded p-2" data-comment-id="{{ $comment->id }}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm"><strong>{{ $comment->user->name }}</strong> <span class="text-xs text-base-content/60">· {{ $comment->created_at->diffForHumans() }}</span></p>
                                        <p class="mt-1 text-sm">{{ $comment->body }}</p>
                                    </div>

                                    <div class="flex flex-col items-end space-y-2">
                                        <!-- comment like button -->
                                        <button class="comment-like-btn btn btn-ghost btn-xs" data-comment-id="{{ $comment->id }}" data-liked="{{ optional($comment->likes()->where('user_id', auth()->id())->first())->exists ? '1' : '0' }}">
                                            ❤️ <span class="comment-like-count" id="comment-like-count-{{ $comment->id }}">{{ $comment->likes()->count() }}</span>
                                        </button>

                                        <!-- reply toggle -->
                                        @auth
                                            <button class="reply-toggle btn btn-ghost btn-xs" data-comment-id="{{ $comment->id }}">Reply</button>
                                        @endif
                                    </div>
                                </div>

                                {{-- replies (one level) --}}
                                @if($comment->replies->isNotEmpty())
                                    <div class="replies mt-2 ml-4 space-y-2">
                                        @foreach($comment->replies as $reply)
                                            <div class="reply-item border rounded p-2" data-comment-id="{{ $reply->id }}">
                                                <p class="text-sm"><strong>{{ $reply->user->name }}</strong> <span class="text-xs text-base-content/60">· {{ $reply->created_at->diffForHumans() }}</span></p>
                                                <p class="mt-1 text-sm">{{ $reply->body }}</p>
                                                <div class="flex justify-end items-center space-x-2 mt-1">
                                                    <button class="comment-like-btn btn btn-ghost btn-xs" data-comment-id="{{ $reply->id }}" data-liked="{{ optional($reply->likes()->where('user_id', auth()->id())->first())->exists ? '1' : '0' }}">
                                                        ❤️ <span class="comment-like-count" id="comment-like-count-{{ $reply->id }}">{{ $reply->likes()->count() }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- reply form (hidden, toggled) -->
                                @auth
                                    <form class="reply-form mt-2 hidden" data-chirp-id="{{ $chirp->id }}" data-parent-id="{{ $comment->id }}" onsubmit="event.preventDefault(); ChirpUI.addComment({{ $chirp->id }}, {{ $comment->id }});">
                                        @csrf
                                        <div class="flex gap-2">
                                            <input type="text" id="reply-input-{{ $comment->id }}" class="input input-sm flex-1" placeholder="Reply to {{ $comment->user->name }}" maxlength="1000" required>
                                            <button type="submit" class="btn btn-sm">Reply</button>
                                        </div>
                                    </form>
                                @endauth
                            </div>
                        @endforeach

                        {{-- if there are more comments on server, show "View all comments" button --}}
                        @if($chirp->comments()->whereNull('parent_id')->count() > $commentsPreview->count())
                            <div class="text-center">
                                <button class="btn btn-ghost btn-sm view-all-comments" data-chirp-id="{{ $chirp->id }}">View all replies</button>
                            </div>
                        @endif
                    </div>
                </div>
                <!-- end comments section -->

            </div>
        </div>
    </div>
</div>

{{-- ---------- JS: AJAX for reactions, comments, replies & comment likes ---------- --}}
@once
    @push('scripts')
    <script>
    (function () {
        if (window.__chirp_ui_initialized) return;
        window.__chirp_ui_initialized = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
        const isAuth = {{ auth()->check() ? 'true' : 'false' }};

        // Helper: POST JSON
        async function postJSON(url, payload = {}) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            if (!res.ok) {
                const text = await res.text();
                throw new Error(text || 'Network error');
            }
            return res.json();
        }

        // Helper: toggle active classes for chirp reactions
        function updateChirpReactionUI(chirpId, userReaction, likesCount, dislikesCount) {
            const likeBtn = document.querySelector(`.chirp-react-btn[data-chirp-id="${chirpId}"][data-action="like"]`);
            const dislikeBtn = document.querySelector(`.chirp-react-btn[data-chirp-id="${chirpId}"][data-action="dislike"]`);
            if (likeBtn) {
                likeBtn.setAttribute('data-active', userReaction === 'like' ? '1' : '0');
                likeBtn.setAttribute('aria-pressed', userReaction === 'like' ? 'true' : 'false');
                likeBtn.classList.toggle('btn-active', userReaction === 'like');
            }
            if (dislikeBtn) {
                dislikeBtn.setAttribute('data-active', userReaction === 'dislike' ? '1' : '0');
                dislikeBtn.setAttribute('aria-pressed', userReaction === 'dislike' ? 'true' : 'false');
                dislikeBtn.classList.toggle('btn-active', userReaction === 'dislike');
            }
            const likeCountEl = document.getElementById(`like-count-${chirpId}`);
            const dislikeCountEl = document.getElementById(`dislike-count-${chirpId}`);
            if (likeCountEl && typeof likesCount !== 'undefined') likeCountEl.innerText = likesCount;
            if (dislikeCountEl && typeof dislikesCount !== 'undefined') dislikeCountEl.innerText = dislikesCount;
        }

        // Click handlers: chirp like/dislike (delegation)
        document.addEventListener('click', function (e) {
            const reactBtn = e.target.closest('.chirp-react-btn');
            if (!reactBtn) return;
            e.preventDefault();

            const chirpId = reactBtn.getAttribute('data-chirp-id');
            const action = reactBtn.getAttribute('data-action'); // 'like' or 'dislike'
            if (!isAuth) { window.location = '/login'; return; }

            postJSON(`/chirps/${chirpId}/react`, { reaction_type: action })
                .then(res => {
                    // support different response shapes (user_reaction or userReaction)
                    const userReaction = res.user_reaction ?? res.userReaction ?? (res.status === 'removed' ? null : (res.status === 'added' ? action : null));
                    const likesCount = res.likes_count ?? res.likes ?? res.likesCount ?? undefined;
                    const dislikesCount = res.dislikes_count ?? res.dislikes ?? res.dislikesCount ?? undefined;

                    updateChirpReactionUI(chirpId, userReaction, likesCount, dislikesCount);
                })
                .catch(err => {
                    console.error(err);
                    alert('Could not react to chirp. Please try again.');
                });
        });

        // Toggle comments section
        document.addEventListener('click', function (e) {
            const toggle = e.target.closest('.toggle-comments');
            if (!toggle) return;
            e.preventDefault();
            const chirpId = toggle.getAttribute('data-chirp-id');
            const section = document.getElementById(`comments-section-${chirpId}`);
            if (!section) return;
            const expanded = section.classList.toggle('hidden') === false;
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });

        // Add comment (top-level or reply)
        window.ChirpUI = window.ChirpUI || {};
        ChirpUI.addComment = async function (chirpId, parentId = null) {
            if (!isAuth) { window.location = '/login'; return; }

            const inputSelector = parentId ? `#reply-input-${parentId}` : `#comment-input-${chirpId}`;
            const input = document.querySelector(inputSelector);
            if (!input) return;
            const body = input.value.trim();
            if (!body) return;

            try {
                const payload = parentId ? { body: body, parent_id: parentId } : { body: body };
                const res = await postJSON(`/chirps/${chirpId}/comment`, payload);

                // server returns res.comment
                const c = res.comment ?? res; // handle different shapes
                // create DOM element for new comment or reply
                const commentHtml = buildCommentHtml(c);

                if (!parentId) {
                    // prepend to comments list
                    const list = document.getElementById(`comments-list-${chirpId}`);
                    if (list) {
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = commentHtml;
                        list.prepend(wrapper.firstElementChild);
                    }
                } else {
                    // find parent comment's replies container; create if missing
                    const parentEl = document.querySelector(`.comment-item[data-comment-id="${parentId}"]`);
                    if (parentEl) {
                        let repliesContainer = parentEl.querySelector('.replies');
                        if (!repliesContainer) {
                            repliesContainer = document.createElement('div');
                            repliesContainer.className = 'replies mt-2 ml-4 space-y-2';
                            parentEl.appendChild(repliesContainer);
                        }
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = commentHtml;
                        repliesContainer.prepend(wrapper.firstElementChild);
                        // hide reply form after posting
                        const replyForm = parentEl.querySelector('.reply-form');
                        if (replyForm) replyForm.classList.add('hidden');
                    }
                }

                // increment comments count
                const commentsBadge = document.getElementById(`comments-count-${chirpId}`);
                if (commentsBadge) {
                    commentsBadge.innerText = parseInt(commentsBadge.innerText || '0', 10) + 1;
                }

                input.value = '';
            } catch (err) {
                console.error(err);
                alert('Could not post comment.');
            }
        };

        // Build HTML for a single comment (server comment shape expected: { id, body, parent_id, user:{id,name}, created_at, created_human })
        function buildCommentHtml(c) {
            const userName = (c.user && c.user.name) ? c.user.name : (c.user_name ?? 'You');
            const createdHuman = c.created_human ?? c.created_at ?? 'just now';
            const commentId = c.id ?? c.comment_id ?? '';
            const body = c.body ?? c.comment ?? '';

            return `
                <div class="comment-item border rounded p-2" data-comment-id="${commentId}">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm"><strong>${escapeHtml(userName)}</strong> <span class="text-xs text-base-content/60">· ${escapeHtml(createdHuman)}</span></p>
                            <p class="mt-1 text-sm">${escapeHtml(body)}</p>
                        </div>
                        <div class="flex flex-col items-end space-y-2">
                            <button class="comment-like-btn btn btn-ghost btn-xs" data-comment-id="${commentId}" data-liked="0">
                                ❤️ <span class="comment-like-count" id="comment-like-count-${commentId}">0</span>
                            </button>
                            ${ isAuth ? `<button class="reply-toggle btn btn-ghost btn-xs" data-comment-id="${commentId}">Reply</button>` : '' }
                        </div>
                    </div>
                    ${ isAuth ? `
                        <form class="reply-form mt-2 hidden" data-chirp-id="${commentId}" data-parent-id="${commentId}" onsubmit="event.preventDefault(); ChirpUI.addComment(${commentId}, ${commentId});">
                            <div class="flex gap-2">
                                <input type="text" id="reply-input-${commentId}" class="input input-sm flex-1" placeholder="Reply..." maxlength="1000" required>
                                <button type="submit" class="btn btn-sm">Reply</button>
                            </div>
                        </form>
                    ` : '' }
                </div>
            `;
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            if (typeof text !== 'string') return text;
            return text.replace(/[&<>"'`=\/]/g, function (s) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                    '/': '&#x2F;',
                    '`': '&#x60;',
                    '=': '&#x3D;'
                })[s];
            });
        }

        // Reply toggle: show/hide reply form
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.reply-toggle');
            if (!btn) return;
            e.preventDefault();
            const commentId = btn.getAttribute('data-comment-id');
            const parentEl = document.querySelector(`.comment-item[data-comment-id="${commentId}"]`);
            if (!parentEl) return;
            const form = parentEl.querySelector('.reply-form');
            if (!form) return;
            form.classList.toggle('hidden');
            const input = form.querySelector('input');
            if (input) input.focus();
        });

        // View all comments (fetch full list)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.view-all-comments');
            if (!btn) return;
            e.preventDefault();
            const chirpId = btn.getAttribute('data-chirp-id');
            fetch(`/chirps/${chirpId}/comments`, { headers: { 'Accept': 'application/json' }})
                .then(resp => resp.json())
                .then(data => {
                    // expected: array of comments (with replies) - this depends on your server implementation
                    const list = document.getElementById(`comments-list-${chirpId}`);
                    if (!list) return;
                    list.innerHTML = '';
                    (data.comments ?? data).forEach(c => {
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = buildCommentHtml(c);
                        list.appendChild(wrapper.firstElementChild);
                        // if c.replies exists, append replies below
                        if (c.replies && c.replies.length) {
                            const parentEl = list.querySelector(`.comment-item[data-comment-id="${c.id}"]`);
                            if (parentEl) {
                                let repliesContainer = parentEl.querySelector('.replies');
                                if (!repliesContainer) {
                                    repliesContainer = document.createElement('div');
                                    repliesContainer.className = 'replies mt-2 ml-4 space-y-2';
                                    parentEl.appendChild(repliesContainer);
                                }
                                c.replies.forEach(r => {
                                    const rwrap = document.createElement('div');
                                    rwrap.innerHTML = buildCommentHtml(r);
                                    repliesContainer.appendChild(rwrap.firstElementChild);
                                });
                            }
                        }
                    });
                })
                .catch(err => {
                    console.error(err);
                    alert('Could not load comments.');
                });
        });

        // Comment like toggling (delegated)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.comment-like-btn');
            if (!btn) return;
            e.preventDefault();
            if (!isAuth) { window.location = '/login'; return; }

            const commentId = btn.getAttribute('data-comment-id');
            postJSON(`/comments/${commentId}/like`, {})
                .then(res => {
                    // expected shape: { likes_count: N, user_liked: true|false }
                    const likes = res.likes_count ?? res.likes ?? res.likesCount ?? undefined;
                    const liked = res.user_liked ?? res.userLiked ?? res.liked ?? undefined;

                    const countEl = document.getElementById(`comment-like-count-${commentId}`);
                    if (countEl && typeof likes !== 'undefined') countEl.innerText = likes;
                    btn.setAttribute('data-liked', liked ? '1' : '0');
                    btn.classList.toggle('btn-active', !!liked);
                })
                .catch(err => {
                    console.error(err);
                    alert('Could not toggle comment like.');
                });
        });

        // Prevent multiple initializations done
    })();
    </script>
    @endpush
@endonce
