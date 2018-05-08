<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fotorama/4.6.4/fotorama.min.css">

<script defer src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/fotorama/4.6.4/fotorama.min.js"></script>

<div class="fotorama" {!! count($files) > 1 ? 'data-nav="thumbs"' : '' !!} data-width="100%" data-height="100%"
     data-ratio="16/9" data-allowfullscreen="native" data-thumbfit="cover" data-keyboard="true">
  @foreach($files as $file)
    @php /** @var \Febalist\Laravel\File\File $file */ @endphp
    @if($file->type == 'image')
      <a href="{{ $file->url() }}"></a>
    @else
      <div data-thumb="{{ $file->icon() }}">
        <iframe src="{{ $file->embedded() }}"></iframe>
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
