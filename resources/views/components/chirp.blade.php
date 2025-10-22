@props(['chirp'])

<div class="card bg-base-100">
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
                        <p class="text-sm font-semibold">
                            {{ $chirp->user->name }}
                        </p>
                        <span class="text-base-content/60">·</span>
                        <p class="text-sm text-base-content/60">
                            {{ $chirp->created_at->diffForHumans() }}
                        </p>
                        @if ($chirp->updated_at->gt($chirp->created_at->addSeconds(5)))
                            <span class="text-base-content/60">·</span>
                            <span class="text-sm text-base-content/60 italic">edited</span>
                        @endif
                    </div>

                    @if (auth()->check() && auth()->id() === $chirp->user_id)
                        <!-- Edit/Delete Buttons -->
                        <div class="flex gap-1">
                            <a href="/chirps/{{ $chirp->id }}/edit" class="btn btn-ghost btn-xs">
                                Edit
                            </a>
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

                <p class="mt-1">
                    {{ $chirp->message }}
                </p>
            </div>
        </div>
    </div>
</div>
