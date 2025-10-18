@props(['posts', 'showManagementOptions' => false, 'profileOwnerToDisplay' => null, 'postCounter' => 0])

@foreach($posts as $post)
    @include('partials.post-card', [
        'post' => $post,
        'showManagementOptions' => $showManagementOptions,
        'profileOwnerToDisplay' => $profileOwnerToDisplay,
    ])

    @if ((($postCounter + $loop->iteration) % 6) == 0)
        <div class="w-full min-w-[250px]">
            <ins class="adsbygoogle"
                 style="display:block; min-width: 250px; width: 100%;"
                 data-ad-format="fluid"
                 data-ad-layout-key="-6t+ed+2i-1n-4w"
                 data-ad-client="ca-pub-2989575196315667"
                 data-ad-slot="7674157999"></ins>
{{--            <script>--}}
{{--                (adsbygoogle = window.adsbygoogle || []).push({});--}}
{{--            </script>--}}
        </div>
    @endif
@endforeach
