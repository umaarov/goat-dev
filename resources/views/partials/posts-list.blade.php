@props(['posts', 'postCounter' => 0])

@foreach($posts as $post)
    @include('partials.post-card', [
        'post' => $post,
        'showManagementOptions' => $showManagementOptions ?? false,
        'profileOwnerToDisplay' => $profileOwnerToDisplay ?? null,
        'isFirst' => false,
    ])

    @php $postCounter++; @endphp

    @if ($postCounter % 4 === 0)
        <div class="my-3" style="display:none;">
            <div id="ezoic-pub-ad-placeholder-103"></div>
            <script>
                ezstandalone.cmd.push(function () {
                    ezstandalone.showAds(103);
                });
            </script>
        </div>
    @endif
@endforeach
