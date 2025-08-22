@props(['posts', 'showManagementOptions' => false, 'profileOwnerToDisplay' => null, 'postCounter' => 0])

@foreach($posts as $post)
    @include('partials.post-card', [
        'post' => $post,
        'showManagementOptions' => $showManagementOptions,
        'profileOwnerToDisplay' => $profileOwnerToDisplay,
    ])

    @if ((($postCounter + $loop->iteration) % 6) == 1)
        <div class="w-full">
            <script async
                    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2989575196315667"
                    crossorigin="anonymous"></script>
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-format="fluid"
                 data-ad-layout-key="-6t+ed+2i-1n-4w"
                 data-ad-client="ca-pub-2989575196315667"
                 data-ad-slot="7674157999"></ins>
            <script>
                (function () {
                    const adIns = document.currentScript.previousElementSibling;
                    const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                    adIns.setAttribute('data-ad-ui-theme', theme);
                    (adsbygoogle = window.adsbygoogle || []).push({});
                })();
            </script>
        </div>
    @endif
@endforeach
