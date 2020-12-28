<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fotorama@4.6/fotorama.css">

<script defer src="https://cdn.jsdelivr.net/npm/jquery@3.5/dist/jquery.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/fotorama@4.6/fotorama.js"></script>

<div class="fotorama" {!! count($files) > 1 ? 'data-nav="thumbs"' : '' !!} data-width="100%" data-height="100%"
     data-ratio="16/9" data-allowfullscreen="native" data-thumbfit="cover" data-keyboard="true">
    @foreach($files as $file)
        @php /** @var \Febalist\Laravel\File\File $file */ @endphp
        @if($file->type() === 'image')
            <a href="{{ $file->url() }}"></a>
        @else
            <div data-thumb="{{ $file->iconUrl() }}" data-thumbratio="33/44">
                <iframe src="{{ $file->viewUrl() }}"></iframe>
            </div>
        @endif
    @endforeach
</div>

<style>
    html, body {
        overflow: hidden;
        margin: 0;
        padding: 0;
    }

    iframe {
        width: 100%;
        height: 100%;
        border: 0;
    }
</style>
