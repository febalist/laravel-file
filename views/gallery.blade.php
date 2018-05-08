<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fotorama/4.6.4/fotorama.min.css">

<script defer src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/fotorama/4.6.4/fotorama.min.js"></script>

<div class="fotorama" data-nav="thumbs" data-width="100%" data-height="100%" data-ratio="16/9"
     data-allowfullscreen="native" data-thumbfit="cover" data-keyboard="true">
  @foreach($urls as $thumb => $full)
    <a href="{{ $full }}" data-thumb="{{ is_string($thumb) ? $thumb : $full }}"></a>
  @endforeach
</div>

<style>
  body {
    overflow: hidden;
  }
</style>
